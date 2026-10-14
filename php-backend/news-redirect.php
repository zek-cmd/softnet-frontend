<?php
/**
 * Send an old numeric news link to its readable address.
 *
 * Before the news pages moved into Nuxt, one story could be opened at two
 * addresses: /news/12 and /news/softnet-scholarship-2026. The Nuxt build writes
 * a page per readable address only — it cannot write one per number as well,
 * because a number is not part of the address a story is published at.
 *
 * So this file keeps the numeric form alive: it looks up the story and answers
 * with a permanent redirect to the readable address. Links already shared on
 * Facebook, and pages already listed by Google, therefore keep working.
 *
 * It renders nothing. It is routing, not a page.
 *
 * A number that matches nothing is answered with a real 404, so search engines
 * drop the dead address instead of treating it as alive.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/slug.php';

$id = sanitizeInt($_GET['a'] ?? ($_GET['id'] ?? 0));

if (!$id) {
    header('Location: /news', true, 302);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<title>News not found</title>'
        . '<meta name="robots" content="noindex">'
        . '</head><body style="font-family:system-ui,sans-serif;padding:40px">'
        . '<h1>That news item is not here</h1>'
        . '<p>It may have been removed, or the link may be incomplete.</p>'
        . '<p><a href="/news">See all news</a></p>'
        . '</body></html>';
    exit;
}

// An item that predates readable addresses gets one now, so its redirect has
// somewhere to point.
if (newsSlugReady($pdo)) {
    $item['slug'] = newsEnsureSlug($pdo, $id);
}

$target = newsUrl($item);

// Already the readable form (or the item has no slug at all): nothing to
// redirect to, so send the reader on to the list rather than looping.
if ($target === '/news/' . $id) {
    header('Location: /news', true, 302);
    exit;
}

header('Location: ' . $target, true, 301);
exit;
