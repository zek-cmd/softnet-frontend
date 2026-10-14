<?php
/**
 * Checks the address table in public/.htaccess.
 *
 * Two things are verified:
 *
 *   1. Every address the website uses (links, form actions) is reachable —
 *      either by a rewrite rule, or by a real file that the server serves on
 *      its own. An address that matches nothing is a 404 for a real visitor.
 *   2. Every rule points at a file that exists. A rule aimed at a missing
 *      file is a 500 - the mistake made earlier with api/post.php.
 *
 * Point 1 covers both halves of the site. The news addresses (/news,
 * /news/page/2, /news/<slug>) are served by public/news-view.php — it writes the
 * story's real <head> tags for Facebook and Google, then hands the page to the
 * Nuxt app to draw live from the API — so each of them is reached through a
 * rewrite rule.
 *
 * Note the one thing that is NOT true any more: the build no longer writes a
 * news/ folder. It used to (one frozen page per story, which is why the build
 * needed internet and could publish "not found" pages), and a leftover folder
 * from those builds would still be found by the checks below.
 *
 * This is the check that was missing when the Delete button started throwing
 * 404: the button posts to /admin/delete, but .htaccess only had a rule for
 * /admin/delete/123, so the address silently matched nothing.
 *
 * No database and no web server needed. Run it from the project root:
 *
 *     php tests/routing-check.php
 */

$root = dirname(__DIR__);
$htaccess = $root . '/php-backend/.htaccess';
$publicDir = $root . '/php-backend';

$fails = 0;
function check(string $label, $got, $want): void
{
    global $fails;
    if ($got !== $want) {
        $fails++;
        echo "FAIL  $label\n      got  " . var_export($got, true) . "\n      want " . var_export($want, true) . "\n";
    } else {
        echo "  ok  $label\n";
    }
}

if (!is_file($htaccess)) {
    echo "FAIL  public/.htaccess is missing\n";
    exit(1);
}

$lines = file($htaccess, FILE_IGNORE_NEW_LINES);

// ---------------------------------------------------------------
// Read the rules
// ---------------------------------------------------------------

$rules = []; // each: ['pattern' => regex as written, 'target' => file or '-', 'line' => n]
foreach ($lines as $i => $line) {
    if (!preg_match('/^\s*RewriteRule\s+(\S+)\s+(\S+)/', $line, $m)) {
        continue;
    }
    $rules[] = ['pattern' => $m[1], 'target' => $m[2], 'line' => $i + 1];
}

echo "== the rule table reads cleanly ==\n";
check('rules were found', count($rules) > 5, true);

// A pattern with no ^ ... $ still works in Apache, but this project writes them
// anchored, and an unanchored one is nearly always a typo. The storage rule
// ends in ")" on purpose, because it matches two shapes: "storage" and
// "storage/anything".
foreach ($rules as $r) {
    $last = substr($r['pattern'], -1);
    $anchored = $r['pattern'][0] === '^' && ($last === '$' || $last === ')');
    check("rule on line {$r['line']} is anchored ({$r['pattern']})", $anchored, true);
}

// ---------------------------------------------------------------
// Every rule must point at a file that exists
// ---------------------------------------------------------------

echo "\n== every rule points at a real file ==\n";
foreach ($rules as $r) {
    if ($r['target'] === '-') {
        continue; // the storage block: "-" means "refuse, serve nothing"
    }
    $file = preg_replace('/\?.*$/', '', $r['target']);
    $path = $publicDir . '/' . $file;
    check("line {$r['line']}: {$file} exists", is_file($path), true);
}

// ---------------------------------------------------------------
// Every address the site uses must be matched by a rule
// ---------------------------------------------------------------

/**
 * Does any rule match this address? Apache matches RewriteRule against the
 * path with no leading slash, so that is stripped first.
 */
function addressMatches(array $rules, string $url): bool
{
    $path = ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
    foreach ($rules as $r) {
        if (@preg_match('#' . $r['pattern'] . '#', $path) === 1) {
            return true;
        }
    }
    return false;
}

/**
 * Is this address served by a file the build generated?
 *
 * A static host answers /news from news/index.html and /news/abc from
 * news/abc/index.html, with no rule involved. This is how the public news
 * pages are served now, so an address counts as reachable when either a rule
 * matches it or one of these files is on disk.
 *
 * Both the build output and public/ are checked: public/ is where the PHP side
 * lives, and .output/public is where the built site lands.
 */
function addressHasFile(string $url): bool
{
    global $publicDir;

    $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
    if ($path === '') {
        return false;
    }

    // A generated page is a directory holding an index.html.
    foreach ([$publicDir, dirname($publicDir) . '/.output/public'] as $base) {
        if (is_file($base . '/' . $path . '/index.html')) {
            return true;
        }
        if (is_file($base . '/' . $path . '.html')) {
            return true;
        }
        if (is_file($base . '/' . $path)) {
            return true;
        }
    }

    return false;
}

/**
 * Reachable either way: a rewrite rule, or a file the server finds itself.
 */
function addressReachable(array $rules, string $url): bool
{
    return addressMatches($rules, $url) || addressHasFile($url);
}

// The addresses the site links to or posts to, written out by hand so the
// templated ones (which carry an id) are covered too.
$usedAddresses = [
    '/admin',
    '/admin/edit',
    '/admin/edit/12',
    '/admin/delete',        // <-- the POST target that was returning 404
    '/admin/delete/12',
    '/admin/bulk-delete',
    '/admin/gallery/12',
    '/admin/settings',
    '/admin/login',
    '/admin/logout',
    '/api/news',
    '/api/posts',
    '/news',
    '/news/page/2',
    '/news/12',
    '/news/softnet-scholarship-2026',
];

echo "\n== every address the site uses is reachable ==\n";
foreach ($usedAddresses as $url) {
    check("reached: $url", addressReachable($rules, $url), true);
}

// A trailing slash must behave the same way, or a typed address 404s.
echo "\n== a trailing slash changes nothing ==\n";
foreach (['/admin/delete/', '/admin/', '/news/', '/api/news/'] as $url) {
    check("reached: $url", addressReachable($rules, $url), true);
}

// ---------------------------------------------------------------
// Addresses built inside the source code
// ---------------------------------------------------------------
// The hand-written list above can go stale. This scans the PHP and JavaScript
// for plain /admin... and /api... addresses so a newly added link is caught
// even if nobody remembers to add it to the list.

echo "\n== addresses written in the source ==\n";
$found = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!in_array($file->getExtension(), ['php', 'js'], true)) {
        continue;
    }
    $text = (string) file_get_contents($file->getPathname());
    if (preg_match_all('#["\'](/(?:admin|api)/[A-Za-z0-9/_-]*)#', $text, $m)) {
        foreach ($m[1] as $url) {
            $found[$url] = true;
        }
    }
}
ksort($found);
check('the scan found addresses to test', count($found) > 5, true);

foreach (array_keys($found) as $url) {
    // "/admin/gallery/" is a prefix glued to an id in the code, so it only
    // makes sense with one. Test the finished address instead.
    if (substr($url, -1) === '/') {
        check("reached (with an id): {$url}12", addressReachable($rules, $url . '12'), true);
        continue;
    }
    check("reached: $url", addressReachable($rules, $url), true);
}

echo "\n== every readable news address reaches news-view.php ==\n";
// /news, /news/page/2 and /news/<slug> are ALL served by public/news-view.php:
// it writes the story's real title, summary and cover photo into the <head>
// (which is what Facebook, Messenger and Google read — they never run
// JavaScript), then hands the same page to the Nuxt app, which draws the story
// live from the API.
//
// Two facts have to stay true together, and this is the pair:
//
//   * every readable news address is matched by a rule that points at
//     news-view.php, and
//   * the build writes NO news/ folder. If it did, a frozen page per story
//     would be back — a story posted after the build would have no page until
//     the next upload — and because mod_rewrite runs BEFORE the server looks
//     for a file, those built pages would sit on disk unread anyway.

foreach (['/news', '/news/', '/news/page/2', '/news/softnet-scholarship-2026'] as $url) {
    check("reached: $url", addressMatches($rules, $url), true);
}

$newsRules = array_filter($rules, function ($r) {
    return (bool) preg_match('#^\^news#', $r['pattern']);
});

// Three: the list, a further page of the list, and one story.
check('the news addresses are handled by rules', count($newsRules) >= 3, true);

$storyRule = null;
foreach ($newsRules as $r) {
    if (strpos($r['pattern'], '[a-z]') !== false) {
        $storyRule = $r;
    }
}

// A story rule must exist and must be a readable address, not a number: the
// numeric form belongs to news-redirect.php below, and two rules matching the
// same address is exactly how a redirect turns into a loop.
check('a rule serves one story address', $storyRule !== null, true);
if ($storyRule) {
    check(
        '/news/my-story is matched by it',
        @preg_match('#' . $storyRule['pattern'] . '#', 'news/my-story') === 1,
        true
    );
    check(
        '/news/12 is NOT matched by it (the redirect keeps the numbers)',
        @preg_match('#' . $storyRule['pattern'] . '#', 'news/12') === 0,
        true
    );
}

// The build output itself. A news/ folder in it means the old, frozen design is
// back, so this is reported rather than ignored.
$builtNewsDir = $publicDir . '/../.output/public/news';
$builtNewsIndex = $builtNewsDir . '/index.html';
check(
    'the build wrote no news folder',
    is_dir($builtNewsDir) || is_file($builtNewsDir . '.html'),
    false
);
if (is_file($builtNewsIndex)) {
    echo "      a news/ folder is present from an earlier build — delete it before uploading.\n";
}

echo "\n== the old /posts and /post addresses are redirected ==\n";
// These are RedirectMatch, not RewriteRule, so they are read separately.
$redirects = array_filter($lines, function ($l) {
    return (bool) preg_match('/^\s*RedirectMatch\s+301\s+/', $l);
});
check('redirect rules exist', count($redirects) >= 3, true);

// A RedirectMatch target can carry a backreference ("$1"), so the check runs
// the real match and substitutes the captured groups before comparing. That is
// how Apache builds the final address.
foreach ([['/posts', '/news'], ['/post', '/news'], ['/post/123', '/news/123']] as [$from, $to]) {
    $hit = false;
    foreach ($redirects as $line) {
        if (!preg_match('/^\s*RedirectMatch\s+301\s+(\S+)\s+(\S+)\s*$/', $line, $m)) {
            continue;
        }
        if (preg_match('#' . $m[1] . '#', $from, $groups) !== 1) {
            continue;
        }
        $resolved = $m[2];
        foreach (array_slice($groups, 1) as $n => $value) {
            $resolved = str_replace('$' . ($n + 1), $value, $resolved);
        }
        if ($resolved === $to) {
            $hit = true;
        }
    }
    check("$from redirects to $to", $hit, true);
}

// The numeric news address is a rewrite, not a RedirectMatch, because it has to
// look the story up in the database first. It must still be there, or every
// link shared as /news/12 before the move would 404.
echo "\n== the old numeric news address still redirects ==\n";
$numeric = array_filter($rules, function ($r) {
    return $r['pattern'] === '^news/([0-9]+)/?$';
});
check('the numeric news rule exists', count($numeric) === 1, true);
check(
    'it points at news-redirect.php',
    $numeric ? reset($numeric)['target'] === 'news-redirect.php?a=$1' : false,
    true
);
check('/news/12 is matched by it', addressMatches($rules, '/news/12'), true);

// And a readable address must NOT be caught by the NUMBER rule, or the redirect
// would loop: news-redirect.php would send /news/my-story to /news/my-story.
//
// Only that one rule is tested here, not all of them — a readable address IS
// matched by the story rule above, which is exactly right and is what serves it.
check('/news/some-story is not matched by it', @preg_match('#' . reset($numeric)['pattern'] . '#', 'news/some-story') === 0, true);

echo "\n";
echo $fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n";
exit($fails === 0 ? 0 : 1);
