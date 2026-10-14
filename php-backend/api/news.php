<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/html-clean.php';
require_once __DIR__ . '/../includes/tags.php';
require_once __DIR__ . '/../includes/news-query.php';
require_once __DIR__ . '/../includes/slug.php';
require_once __DIR__ . '/../includes/gallery.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

$limit = sanitizeInt($_GET['limit'] ?? 5);
$limit = max(1, min($limit, 50));

// The page number. It is used twice: the Nuxt build walks every page to
// collect the address of each news item, and the browser asks for page 2, 3,
// 4 as the reader moves through the list. newsFetchPage() clamps it to the
// real number of pages, so a request for page 999 simply returns the last one.
$page = sanitizeInt($_GET['page'] ?? 1);
$page = max(1, $page);

$tag = sanitizeString($_GET['tag'] ?? '', NEWS_TAG_LENGTH);
$search = sanitizeString($_GET['search'] ?? '', 100);

$result = newsFetchPage($pdo, [
    'perPage' => $limit,
    'page' => $page,
    'search' => $search,
    'tag' => $tag,
]);

$items = $result['rows'];

foreach ($items as &$item) {
    $item['date_formatted'] = date('F j, Y', strtotime($item['created_at']));
    $item['date_iso'] = date('Y-m-d', strtotime($item['created_at']));
    $item['tags'] = newsTagList($item['tags'] ?? '');
    // The readable address when the item has one, /news/12 while it does not.
    // The home page cards link straight to this, so it must always be a real
    // address on the PHP site.
    $item['url'] = newsUrl($item);

    // The links this API hands out are dropped straight into a page, so they
    // are checked here as well and not only in the admin form.
    if (!empty($item['image'])) {
        $item['image'] = '/' . ltrim(cleanNewsImagePath($item['image']), '/');
    }
}
unset($item);

// How many sub images each item on this page has. The list page shows a small
// "3 photos" marker, and asking once for the whole page beats asking per item.
$galleryCounts = newsGalleryCounts($pdo, array_column($result['rows'], 'id'));
foreach ($items as &$item) {
    $item['gallery_count'] = (int) ($galleryCounts[(int) $item['id']] ?? 0);
}
unset($item);

// Every tag in use, busiest first, for the filter row. Read from the whole
// table rather than this page, so the row always matches what is published.
$tagCounts = newsTagCounts($pdo, 14);

// "pages" tells the build how many more requests to make. Without it the
// build could only ever see the first page of news, and every older item
// would be missing from the generated site.
echo json_encode([
    'posts' => $items,
    'news' => $items,
    'total' => $result['total'],
    'pages' => $result['pages'],
    'page' => $result['page'],
    'per_page' => $limit,
    'tags' => $tagCounts,
]);
