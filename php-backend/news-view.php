<?php
/**
 * Every readable news address a visitor opens.
 *
 *   /news                     the list
 *   /news/page/2              the list, one page further on
 *   /news/softnet-sports-day  one story
 *
 * WHY PHP IS HERE AT ALL
 *
 * Facebook does not run JavaScript. It reads the HTML the server sends and
 * builds its preview card from the tags in <head>. The app below is a Nuxt
 * single-page app, so the HTML it ships carries the site's generic card and
 * nothing about the story — a shared story link would preview as the bare
 * school name with the wrong photo.
 *
 * So this file does two things and nothing else:
 *
 *   1. it looks the story up in the database and writes the REAL title, the
 *      summary and the cover photo into the page's <head>;
 *   2. it hands the same page to the Nuxt app, which draws what the visitor
 *      sees — live, from the API, in the site's own design.
 *
 * One address, one design, always fresh. A story posted a minute ago has its
 * own page immediately, and nothing has to be rebuilt or re-uploaded for it.
 *
 * WHAT THIS FILE IS NOT
 *
 * It is not the old news.php. It renders no story body, no cards, no gallery —
 * no markup of its own except the head. The page it serves is the Nuxt build's
 * own shell (200.html), passed through byte for byte, so there is still exactly
 * ONE navbar, ONE footer and ONE stylesheet in this project.
 *
 * The old numeric address /news/12 never reaches this file: public/.htaccess
 * sends it to news-redirect.php first, which answers with a permanent redirect
 * to the readable address.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/news-head.php';

/**
 * The page the Nuxt build wrote, which the app boots from.
 *
 * It is looked for in the two places it can honestly be:
 *
 *   1. beside this file — this is how it is deployed. The built site and the
 *      PHP backend share one web root, so 200.html sits next to this script;
 *   2. in the build output inside the project — so a local copy under Laragon
 *      works without anything being copied about.
 *
 * A site that has not been built yet gets a plain page that says so. It is
 * deliberately NOT a lookalike of the real page: a wrong setup must be visible
 * immediately, not dressed up as the site.
 */
function newsShellHtml(): string
{
    $candidates = [
        __DIR__ . '/200.html',                          // deployed: beside this file
        dirname(__DIR__) . '/.output/public/200.html',   // the build output in the project
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $html = file_get_contents($path);
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }
    }

    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '</head><body style="font-family:system-ui,sans-serif;padding:40px">'
        . '<h1>News</h1>'
        . '<p>The website has not been built yet. Run the build, then upload its output '
        . 'beside this file.</p>'
        . '</body></html>';
}

$page = sanitizeInt($_GET['page'] ?? 0);
$slug = sanitizeString($_GET['slug'] ?? '', 100);

// A page number only travels on the list. /news/page/1 is treated as page 2,
// which is what the list page itself does — the two must agree or the list
// would announce one page and show another.
if ($page > 0) {
    $page = max(2, $page);
}

$shell = newsShellHtml();
$status = 200;

if ($slug === '') {
    $head = newsListHead(max(1, $page));
} else {
    // Both address shapes are accepted here (newsFindByAddress handles a slug
    // and a number), so a link that arrives in either form finds its story.
    $item = newsFindByAddress($pdo, $slug);

    if ($item) {
        $head = newsStoryHead($item);
    } else {
        // A real 404, with a real card, and the app below shows its own
        // "That news item is not here" page. Search engines drop the address
        // instead of treating a dead link as a live page.
        $status = 404;
        $head = newsHeadTags([
            'title' => 'News not found | ' . newsSiteName(),
            'description' => 'That news item is not here. It may have been removed, or the link may be incomplete.',
            'image' => newsSiteBaseUrl() . '/_sitc/images/cover.webp',
            'type' => 'website',
        ]);
    }
}

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// A story is corrected in the admin and must show the correction on the next
// visit, so the page is revalidated rather than stored. The hashed assets it
// loads are cached as usual — this is only the small HTML document.
header('Cache-Control: no-cache, must-revalidate');

echo newsInjectHead($shell, $head);
