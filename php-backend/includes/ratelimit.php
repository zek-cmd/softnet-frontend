<?php

if (!defined('RL_BROWSER_MAX')) {
    define('RL_BROWSER_MAX', 3);
    define('RL_IP_MAX', 9);
    define('RL_LOCK_SECONDS', 30);
    define('RL_ENTRY_TTL', 86400);
    define('RL_SWEEP_EVERY', 3600);
    define('RL_STORAGE_DIR', dirname(__DIR__) . '/storage/ratelimits');
    define('RL_SESSION_KEY', '_rl_fallback');
}

function rlClientIp(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }
    return $ip;
}

function rlBucketKey(string $value): string
{
    return substr(hash('sha256', $value), 0, 40);
}

function rlBrowserKey(): string
{
    $sid = session_id();
    return rlBucketKey('sid:' . ($sid !== '' ? $sid : 'anonymous'));
}

function rlEnsureDir(): bool
{
    if (is_dir(RL_STORAGE_DIR)) {
        return true;
    }
    return @mkdir(RL_STORAGE_DIR, 0775, true) || is_dir(RL_STORAGE_DIR);
}

function rlUseFiles(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = rlEnsureDir() && is_writable(RL_STORAGE_DIR);
    if (!$ok) {
        error_log('Rate limit: ' . RL_STORAGE_DIR . ' is not writable, using session storage.');
    }
    return $ok;
}

function rlIpFile(string $ip): string
{
    return RL_STORAGE_DIR . '/' . rlBucketKey('ip:' . $ip) . '.json';
}

function rlCounter(): array
{
    return ['attempts' => 0, 'locked_until' => 0, 'updated' => 0];
}

function rlBlank(string $ip): array
{
    return [
        'ip' => $ip,
        'updated' => 0,
        'ip_bucket' => rlCounter(),
        'browsers' => [],
    ];
}

function rlLoad(string $ip): array
{
    if (!rlUseFiles()) {
        $bucket = $_SESSION[RL_SESSION_KEY] ?? [];
        $data = is_array($bucket) ? ($bucket[rlBucketKey('ip:' . $ip)] ?? null) : null;
        if (!is_array($data)) {
            return rlBlank($ip);
        }
        return rlNormalize($ip, $data);
    }

    $path = rlIpFile($ip);
    if (!is_file($path)) {
        return rlBlank($ip);
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return rlBlank($ip);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return rlBlank($ip);
    }

    return rlNormalize($ip, $data);
}

function rlNormalize(string $ip, array $data): array
{
    $data['ip'] = $ip;
    $data['updated'] = (int)($data['updated'] ?? 0);

    $ipBucket = $data['ip_bucket'] ?? null;
    $data['ip_bucket'] = is_array($ipBucket) ? $ipBucket : rlCounter();
    $data['ip_bucket'] = rlCounterFill($data['ip_bucket']);

    $browsers = $data['browsers'] ?? null;
    if (!is_array($browsers)) {
        $browsers = [];
    }
    foreach ($browsers as $key => $counter) {
        if (!is_array($counter)) {
            unset($browsers[$key]);
            continue;
        }
        $browsers[$key] = rlCounterFill($counter);
    }
    $data['browsers'] = $browsers;

    return $data;
}

function rlCounterFill(array $counter): array
{
    $counter['attempts'] = max(0, (int)($counter['attempts'] ?? 0));
    $counter['locked_until'] = max(0, (int)($counter['locked_until'] ?? 0));
    $counter['updated'] = (int)($counter['updated'] ?? 0);
    return $counter;
}

function rlSave(string $ip, array $data): bool
{
    $data['ip'] = $ip;
    $data['updated'] = time();

    if (!rlUseFiles()) {
        $_SESSION[RL_SESSION_KEY][rlBucketKey('ip:' . $ip)] = $data;
        return true;
    }

    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return @file_put_contents(rlIpFile($ip), $json, LOCK_EX) !== false;
}

function rlExpire(array $counter): array
{
    $now = time();
    if ($counter['locked_until'] > 0 && $counter['locked_until'] <= $now) {
        $counter['locked_until'] = 0;
        $counter['attempts'] = 0;
        $counter['updated'] = $now;
    }
    return $counter;
}

function rlState(array $browser, array $ipBucket): array
{
    $now = time();
    $browser = rlExpire($browser);
    $ipBucket = rlExpire($ipBucket);

    $browserLocked = $browser['locked_until'] > $now;
    $ipLocked = $ipBucket['locked_until'] > $now;

    $retryAfter = 0;
    if ($browserLocked) {
        $retryAfter = $browser['locked_until'] - $now;
    }
    if ($ipLocked) {
        $retryAfter = max($retryAfter, $ipBucket['locked_until'] - $now);
    }

    $browserLeft = max(0, RL_BROWSER_MAX - $browser['attempts']);
    $ipLeft = max(0, RL_IP_MAX - $ipBucket['attempts']);

    $scope = '';
    if ($browserLocked && $ipLocked) {
        $scope = 'both';
    } elseif ($browserLocked) {
        $scope = 'browser';
    } elseif ($ipLocked) {
        $scope = 'ip';
    }

    return [
        'locked' => $browserLocked || $ipLocked,
        'retryAfter' => $retryAfter,
        'attemptsLeft' => min($browserLeft, $ipLeft),
        'maxAttempts' => RL_BROWSER_MAX,
        'lockSeconds' => RL_LOCK_SECONDS,
        'scope' => $scope,
    ];
}

function rlCheck(string $ip): array
{
    $data = rlLoad($ip);
    $key = rlBrowserKey();
    $browser = $data['browsers'][$key] ?? rlCounter();

    return rlState($browser, $data['ip_bucket']);
}

function rlPruneBrowsers(array &$data): void
{
    $now = time();
    $cutoff = $now - RL_ENTRY_TTL;

    foreach ($data['browsers'] as $key => $counter) {
        if ($counter['locked_until'] > $now) {
            continue;
        }
        if ($counter['updated'] < $cutoff) {
            unset($data['browsers'][$key]);
        }
    }
}

function rlSweep(): void
{
    if (!rlUseFiles()) {
        return;
    }

    $marker = RL_STORAGE_DIR . '/.sweep';
    $last = is_file($marker) ? (int)@filemtime($marker) : 0;
    if ($last > time() - RL_SWEEP_EVERY) {
        return;
    }
    @touch($marker);

    $cutoff = time() - RL_ENTRY_TTL;
    $files = @glob(RL_STORAGE_DIR . '/*.json');
    if (!is_array($files)) {
        return;
    }

    foreach ($files as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime < $cutoff) {
            @unlink($file);
        }
    }
}

function rlFail(string $ip): array
{
    rlSweep();

    $data = rlLoad($ip);
    $key = rlBrowserKey();
    $now = time();

    $browser = rlExpire($data['browsers'][$key] ?? rlCounter());
    $ipBucket = rlExpire($data['ip_bucket']);

    if ($browser['locked_until'] > $now || $ipBucket['locked_until'] > $now) {
        $data['browsers'][$key] = $browser;
        $data['ip_bucket'] = $ipBucket;
        rlSave($ip, $data);
        return rlState($browser, $ipBucket);
    }

    $browser['attempts'] = $browser['attempts'] + 1;
    $browser['updated'] = $now;
    if ($browser['attempts'] >= RL_BROWSER_MAX) {
        $browser['locked_until'] = $now + RL_LOCK_SECONDS;
        $browser['attempts'] = 0;
    }

    $ipBucket['attempts'] = $ipBucket['attempts'] + 1;
    $ipBucket['updated'] = $now;
    if ($ipBucket['attempts'] >= RL_IP_MAX) {
        $ipBucket['locked_until'] = $now + RL_LOCK_SECONDS;
        $ipBucket['attempts'] = 0;
    }

    $data['browsers'][$key] = $browser;
    $data['ip_bucket'] = $ipBucket;
    rlPruneBrowsers($data);
    rlSave($ip, $data);

    return rlState($browser, $ipBucket);
}

function rlClear(string $ip): void
{
    $data = rlLoad($ip);
    $key = rlBrowserKey();

    $changed = isset($data['browsers'][$key]);
    unset($data['browsers'][$key]);

    if ($data['ip_bucket']['attempts'] > 0 || $data['ip_bucket']['locked_until'] > 0) {
        $data['ip_bucket'] = rlCounter();
        $changed = true;
    }

    if (!$changed) {
        return;
    }

    rlSave($ip, $data);
}
