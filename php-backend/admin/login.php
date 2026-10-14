<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/assets.php';
require_once __DIR__ . '/../includes/ratelimit.php';

if (!function_exists('loginState')) {
    function loginState(): array
    {
        return rlCheck(rlClientIp());
    }
}

if (!function_exists('loginLockMessage')) {
    function loginLockMessage(array $state): string
    {
        if (($state['scope'] ?? '') === 'ip') {
            return 'Too many failed attempts from this network. Try again in ' . $state['retryAfter'] . 's.';
        }
        if (($state['scope'] ?? '') === 'both') {
            return 'Too many failed attempts. Try again in ' . $state['retryAfter'] . 's.';
        }
        return 'Too many attempts. Try again in ' . $state['retryAfter'] . 's.';
    }
}

if (!function_exists('loginJson')) {
    function loginJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload);
        exit;
    }
}

$pageTitle = 'Admin Login';

if (isLoggedIn()) {
    header('Location: /admin');
    exit;
}

startSession();

$isAjax = (string)($_POST['ajax'] ?? '') === '1'
    || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

$result = null;
$csrfFailed = false;
$lastUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastUsername = sanitizeString($_POST['username'] ?? '', 100);
    $state = loginState();
    $token = $_POST['_csrf'] ?? '';

    if ($state['locked']) {
        $result = [
            'success' => false,
            'locked' => true,
            'message' => loginLockMessage($state),
        ] + $state;
    } elseif (!is_string($token) || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        $csrfFailed = true;
        $result = [
            'success' => false,
            'message' => 'Your session expired. Please reload the page.',
            'reload' => true,
        ] + $state;
    } elseif ($lastUsername === '' || (string)($_POST['password'] ?? '') === '') {
        $result = [
            'success' => false,
            'message' => 'Please enter both username and password.',
        ] + $state;
    } else {
        $password = (string)$_POST['password'];
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
        $stmt->execute([$lastUsername]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            rlClear(rlClientIp());
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            $result = ['success' => true, 'redirect' => '/admin'];
        } else {
            $after = rlFail(rlClientIp());

            if ($after['locked']) {
                $result = [
                    'success' => false,
                    'locked' => true,
                    'message' => loginLockMessage($after),
                ] + $after;
            } else {
                $result = [
                    'success' => false,
                    'message' => 'Invalid username or password.',
                ] + $after;
            }
        }
    }
}

if ($isAjax) {
    if ($result === null) {
        loginJson(['success' => false, 'message' => 'Invalid request.'], 400);
    }

    loginJson($result, $csrfFailed ? 403 : 200);
}

if ($result !== null && !empty($result['success'])) {
    header('Location: ' . $result['redirect']);
    exit;
}

$state = loginState();
$error = ($result !== null && empty($result['success'])) ? (string)$result['message'] : '';

$cardClass = 'status-card';
if ($state['locked']) {
    $cardClass .= ' is-locked';
} elseif ($error !== '') {
    $cardClass .= ' is-error';
} else {
    $cardClass .= ' is-idle';
}

if ($state['locked']) {
    $statusTitle = 'Too many attempts';
    $statusText = 'Try again in ' . $state['retryAfter'] . 's.';
} elseif ($error !== '') {
    $statusTitle = 'Sign in failed';
    $statusText = $error;
    if ($state['attemptsLeft'] > 0) {
        $statusText .= ' ' . $state['attemptsLeft'] . ' attempt' . ($state['attemptsLeft'] === 1 ? '' : 's') . ' left.';
    }
} else {
    $statusTitle = '';
    $statusText = '';
}

$authData = [
    'locked' => $state['locked'],
    'retryAfter' => $state['retryAfter'],
    'attemptsLeft' => $state['attemptsLeft'],
    'maxAttempts' => $state['maxAttempts'],
    'lockSeconds' => $state['lockSeconds'],
    'scope' => $state['scope'],
    'hasError' => $error !== '' && !$state['locked'],
    'errorMessage' => $error,
];

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/webp" href="/_sitc/images/favicon.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/login.css') ?>">
</head>
<body>
  <div class="login-wrap">
    <div class="login-box">
      <div class="login-logo">
        <img src="/_sitc/images/favicon.webp" alt="SOFTNET logo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%2316a34a%22/><text x=%2250%22 y=%2268%22 font-size=%2245%22 text-anchor=%22middle%22 fill=%22white%22 font-family=%22sans-serif%22 font-weight=%22bold%22>S</text></svg>'">
      </div>
      <h1>SOFTNET Admin</h1>
      <p class="subtitle">Sign in to manage news</p>

      <div class="<?= $cardClass ?>" id="auth-status" role="status" aria-live="polite" aria-atomic="true" data-auth="<?= htmlspecialchars(json_encode($authData), ENT_QUOTES) ?>">
        <span class="status-icon" aria-hidden="true">
          <svg class="ico ico-ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
          <svg class="ico ico-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          <svg class="ico ico-locked" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
        </span>
        <div class="status-body">
          <p class="status-title" id="auth-status-title"><?= htmlspecialchars($statusTitle) ?></p>
          <p class="status-text" id="auth-status-text"><?= htmlspecialchars($statusText) ?></p>
          <div class="status-track" id="auth-track"><span class="status-fill" id="auth-fill"></span></div>
        </div>
      </div>

      <form method="POST" action="" id="login-form">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <input class="form-input" type="text" id="username" name="username" required autocomplete="username" autofocus<?= $state['locked'] ? ' disabled' : '' ?>>
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input class="form-input" type="password" id="password" name="password" required autocomplete="current-password"<?= $state['locked'] ? ' disabled' : '' ?>>
        </div>
        <button type="submit" class="btn btn-primary"<?= $state['locked'] ? ' disabled' : '' ?>><?= $state['locked'] ? 'Locked' : 'Sign In' ?></button>
      </form>

      <p class="login-footer">&copy; <?= date('Y') ?> SOFTNET Information Technology Center, Inc.</p>
    </div>
  </div>

  <script>
    (function () {
      var card = document.getElementById('auth-status');
      var form = document.getElementById('login-form');
      if (!card || !form) { return; }

      var titleEl = document.getElementById('auth-status-title');
      var textEl = document.getElementById('auth-status-text');
      var fillEl = document.getElementById('auth-fill');
      var fields = form.querySelectorAll('input:not([type="hidden"]), button');
      var submitBtn = form.querySelector('button[type="submit"]');
      var usernameEl = document.getElementById('username');
      var passwordEl = document.getElementById('password');

      var state = {};
      try {
        state = JSON.parse(card.getAttribute('data-auth') || '{}') || {};
      } catch (err) {
        state = {};
      }

      var lockSeconds = state.lockSeconds || 30;
      var ticker = null;
      var busy = false;

      function setCard(kind, title, message) {
        card.className = 'status-card' + (kind ? ' is-' + kind : '');
        if (titleEl) { titleEl.textContent = title || ''; }
        if (typeof message === 'string') { textEl.textContent = message; }
      }

      function setDisabled(disabled) {
        Array.prototype.forEach.call(fields, function (el) { el.disabled = disabled; });
        if (submitBtn) { submitBtn.textContent = disabled ? 'Locked' : 'Sign In'; }
      }

      function stopTicker() {
        if (ticker !== null) { clearInterval(ticker); ticker = null; }
      }

      function paint(seconds, total) {
        textEl.textContent = 'Try again in ' + seconds + 's.';
        if (fillEl) { fillEl.style.width = Math.max(0, Math.min(100, (seconds / total) * 100)) + '%'; }
      }

      function ready() {
        stopTicker();
        if (fillEl) { fillEl.style.width = '0%'; }
        setCard('idle', '', '');
        setDisabled(false);
        if (passwordEl) { passwordEl.value = ''; }
        if (usernameEl) { usernameEl.focus(); }
      }

      function fail(message, left) {
        stopTicker();
        if (fillEl) { fillEl.style.width = '0%'; }
        var text = message || 'Invalid username or password.';
        if (typeof left === 'number' && left > 0) {
          text += ' ' + left + ' attempt' + (left === 1 ? '' : 's') + ' left.';
        }
        setCard('error', 'Sign in failed', text);
        setDisabled(false);
        if (passwordEl) { passwordEl.value = ''; }
      }

      function lock(seconds) {
        stopTicker();
        var left = seconds > 0 ? Math.ceil(seconds) : lockSeconds;
        var total = left > lockSeconds ? left : lockSeconds;
        setCard('locked', 'Too many attempts', '');
        setDisabled(true);
        paint(left, total);
        ticker = setInterval(function () {
          left -= 1;
          if (left <= 0) { ready(); return; }
          paint(left, total);
        }, 1000);
      }

      function handle(res) {
        busy = false;
        res = res || {};
        if (submitBtn) { submitBtn.textContent = 'Sign In'; }
        if (res.lockSeconds) { lockSeconds = res.lockSeconds; }

        if (res.success) {
          setCard('ok', 'Signed in', 'Opening the dashboard...');
          window.location.href = res.redirect || '/admin';
          return;
        }

        if (res.reload) {
          window.location.reload();
          return;
        }

        if (res.locked && res.retryAfter > 0) {
          lock(res.retryAfter);
          return;
        }

        fail(res.message, res.attemptsLeft);
      }

      form.addEventListener('submit', function (event) {
        if (!window.fetch || !window.FormData) { return; }
        event.preventDefault();
        if (busy) { return; }

        busy = true;
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Signing in...';
        }

        var body = new FormData(form);
        body.append('ajax', '1');

        fetch(form.getAttribute('action') || window.location.pathname, {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        }).then(function (response) {
          return response.json().catch(function () {
            return { success: false, message: 'Unexpected server response. Please reload the page.' };
          });
        }).then(function (res) {
          handle(res);
        }).catch(function () {
          busy = false;
          fail('Connection problem. Please try again.');
        });
      });

      if (state.locked) {
        lock(state.retryAfter > 0 ? state.retryAfter : 1);
      } else if (state.hasError) {
        fail(state.errorMessage, state.attemptsLeft);
      } else {
        ready();
      }
    })();
  </script>
</body>
</html>
