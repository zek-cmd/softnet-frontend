<?php
/**
 * One news item, as JSON.
 *
 * The list feed (api/news.php) carries what a card needs: title, summary,
 * category, tags, image and date. It deliberately leaves out two things that
 * only the article page needs — the story body and the sub images. Those are
 * served here instead, so the list stays small and the home page does not
 * download every article's full text just to draw its cards.
 *
 * Two addresses work, exactly like the page did:
 *   /api/news-item?id=12
 *   /api/news-item?slug=softnet-scholarship-2026
 *
 * Not found answers 404 with a small JSON body, so the Vue page can show its
 * "That news item is not here" screen instead of failing silently.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/html-clean.php';
require_once __DIR__ . '/../includes/tags.php';
require_once __DIR__ . '/../includes/news-query.php';
require_once __DIR__ . '/../includes/slug.php';
require_once __DIR__ . '/../includes/gallery.php';
require_once __DIR__ . '/../includes/share.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

/**
 * Answer with JSON and stop.
 */
function newsItemApiOutput(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

// A number is the original address shape; anything else must be a slug.
$address = '';
if (isset($_GET['id'])) {
    $address = (string) sanitizeInt($_GET['id']);
} elseif (isset($_GET['slug'])) {
    $address = sanitizeString($_GET['slug'], 100);
}

if ($address === '') {
    newsItemApiOutput(['error' => 'missing', 'message' => 'Give either ?id= or ?slug='], 400);
}

$item = newsFindByAddress($pdo, $address);

if (!$item) {
    newsItemApiOutput(['error' => 'not_found', 'message' => 'That news item is not here'], 404);
}

$id = (int) $item['id'];
$title = (string) $item['title'];

// An item that predates readable addresses gets one the first time it is read,
// so the build can generate its page. Same one-time side effect the PHP page
// had.
if (newsSlugReady($pdo)) {
    $item['slug'] = newsEnsureSlug($pdo, $id);
}

// The story body is cleaned on the way out, never trusted raw. This is the
// same cleaner the PHP page used, so the HTML the reader gets is identical.
$content = cleanNewsHtml((string) ($item['content'] ?? ''));

$imagePath = cleanNewsImagePath($item['image'] ?? '');

// The sub images, in the admin's order. Every path is checked the same way the
// cover photo is, and an entry whose path is refused is dropped rather than
// handed to the browser.
$gallery = [];
foreach (newsGalleryItems($pdo, $id) as $photo) {
    $src = newsGalleryImageSrc((string) $photo['image']);
    if ($src === '') {
        continue;
    }

    $caption = trim((string) $photo['caption']);
    $gallery[] = [
        'src' => '/' . ltrim($src, '/'),
        'caption' => $caption,
        'alt' => $caption !== '' ? $caption : $title,
    ];
}

$tagList = newsTagList($item['tags'] ?? '');

$payload = [
    'id' => $id,
    'title' => $title,
    'summary' => (string) ($item['summary'] ?? ''),
    'category' => (string) ($item['category'] ?? ''),
    // Raw HTML that has already been through cleanNewsHtml(). The Vue page
    // renders it with v-html, which is safe because of this step.
    'content' => $content,
    'tags' => $tagList,
    'image' => $imagePath !== '' ? '/' . ltrim($imagePath, '/') : '',
    'created_at' => (string) $item['created_at'],
    'date_formatted' => date('F j, Y', strtotime((string) $item['created_at'])),
    'date_iso' => date('Y-m-d', strtotime((string) $item['created_at'])),
    'slug' => (string) ($item['slug'] ?? ''),
    // The path the reader should share: /news/<slug>, or /news/12 meanwhile.
    'url' => newsUrl($item),
    'gallery' => $gallery,
];

newsItemApiOutput(['news' => $payload, 'item' => $payload]);
