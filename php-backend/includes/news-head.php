<?php
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/share.php';
require_once __DIR__ . '/html-clean.php';
require_once __DIR__ . '/slug.php';

/**
 * The <head> of a news page, written by PHP.
 *
 * WHY THIS EXISTS
 *
 * Facebook, Messenger, WhatsApp and X do not run JavaScript. They read the
 * HTML the server sends and build their preview card from the tags in
 * <head> — the title, the summary and the cover photo.
 *
 * The news pages were baked into plain HTML files at build time. That worked
 * for sharing, but it had two costs that could not be lived with:
 *
 *   1. a story was frozen at the moment of the build, so a story posted
 *      afterwards simply had no page until the next build and upload;
 *   2. the BUILD had to reach the live API. When the host answered slowly the
 *      build gave up, and every story page came out as "That news item is not
 *      here" while the build still reported success.
 *
 * So the head is now written here, on every request, in PHP — and the page
 * itself is drawn by the Nuxt app the visitor loads (public/news-view.php
 * hands it the page). One address, one design, always fresh.
 *
 * WHAT IS NOT HERE
 *
 * No markup for the story body, the cards or the gallery. That is the Nuxt
 * app's job, from the API. Repeating it here would give the site two copies of
 * the same design, which is exactly the drift the move to Nuxt removed.
 *
 * Everything printed is escaped with sanitizeOutput(). Nothing here is stored
 * or cached: it is rebuilt from the database on each request.
 */

/** The school's name, as it appears in a browser tab and a share card. */
function newsSiteName(): string
{
    return 'SOFTNET Information Technology Center, Inc.';
}

/**
 * A long text cut down to something that fits a preview card.
 *
 * Tags are stripped first (the story body is stored as HTML), runs of spaces
 * and newlines are folded into one space, and the cut is made on a word
 * boundary by mb_strimwidth when PHP has it.
 */
function newsClip(string $text, int $max = 200): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, '...');
    }

    return strlen($text) > $max ? rtrim(substr($text, 0, $max - 3)) . '...' : $text;
}

/**
 * The full web address of a news item's cover photo, for a share card.
 *
 * Facebook will not read a relative path, so this must be absolute. The path
 * itself goes through cleanNewsImagePath() first — the same check the API
 * applies — so a stored "javascript:" or "//evil.example" value can never end
 * up inside a tag. A story with no usable photo falls back to the site cover,
 * which is always there.
 */
function newsHeadImage(array $item): string
{
    $base = newsSiteBaseUrl();
    $path = cleanNewsImagePath($item['image'] ?? '');

    if ($path === '') {
        return $base . '/_sitc/images/cover.webp';
    }

    // An absolute http(s) address is kept as it is; anything else is a path on
    // this server. cleanNewsImagePath() has already refused every other shape.
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return $base . '/' . ltrim($path, '/');
}

/**
 * Build the tag block from plain values.
 *
 * @param array{title?:string,description?:string,image?:string,url?:string,type?:string,published?:string} $meta
 */
function newsHeadTags(array $meta): string
{
    $title = (string) ($meta['title'] ?? newsSiteName());
    $description = trim((string) ($meta['description'] ?? ''));
    $image = (string) ($meta['image'] ?? '');
    $url = (string) ($meta['url'] ?? '');
    $type = (string) ($meta['type'] ?? 'website');
    $published = (string) ($meta['published'] ?? '');

    $tags = ['<title>' . sanitizeOutput($title) . '</title>'];

    if ($description !== '') {
        $tags[] = '<meta name="description" content="' . sanitizeOutput($description) . '">';
    }

    if ($url !== '') {
        $tags[] = '<link rel="canonical" href="' . sanitizeOutput($url) . '">';
        $tags[] = '<meta property="og:url" content="' . sanitizeOutput($url) . '">';
    }

    $tags[] = '<meta property="og:type" content="' . sanitizeOutput($type) . '">';
    $tags[] = '<meta property="og:site_name" content="' . sanitizeOutput(newsSiteName()) . '">';
    $tags[] = '<meta property="og:title" content="' . sanitizeOutput($title) . '">';

    if ($description !== '') {
        $tags[] = '<meta property="og:description" content="' . sanitizeOutput($description) . '">';
    }

    if ($image !== '') {
        $tags[] = '<meta property="og:image" content="' . sanitizeOutput($image) . '">';
        $tags[] = '<meta property="og:image:alt" content="' . sanitizeOutput($title) . '">';
    }

    if ($published !== '') {
        $tags[] = '<meta property="article:published_time" content="' . sanitizeOutput($published) . '">';
    }

    // X (Twitter) uses the same card. Harmless where it is not read.
    $tags[] = '<meta name="twitter:card" content="summary_large_image">';
    $tags[] = '<meta name="twitter:title" content="' . sanitizeOutput($title) . '">';

    if ($description !== '') {
        $tags[] = '<meta name="twitter:description" content="' . sanitizeOutput($description) . '">';
    }

    if ($image !== '') {
        $tags[] = '<meta name="twitter:image" content="' . sanitizeOutput($image) . '">';
    }

    return implode("\n", $tags) . "\n";
}

/**
 * The head for ONE story.
 *
 * The summary is used when there is one; otherwise the beginning of the story
 * itself, so the card is never blank. A story published with the old numeric
 * address gets its readable one here as well: the canonical and the share card
 * must name the address the reader actually ends up on.
 */
function newsStoryHead(array $item): string
{
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        $title = 'News';
    }

    $summary = trim((string) ($item['summary'] ?? ''));
    if ($summary === '') {
        $summary = (string) ($item['content'] ?? '');
    }

    $description = newsClip($summary, 200);
    if ($description === '') {
        $description = 'News and updates from ' . newsSiteName() . '.';
    }

    $published = '';
    if (!empty($item['created_at'])) {
        $stamp = strtotime((string) $item['created_at']);
        if ($stamp) {
            $published = date('c', $stamp);
        }
    }

    return newsHeadTags([
        'title' => $title . ' | ' . newsSiteName(),
        'description' => $description,
        'image' => newsHeadImage($item),
        'url' => newsSiteBaseUrl() . newsUrl($item),
        'type' => 'article',
        'published' => $published,
    ]);
}

/**
 * The head for the news LIST at /news or /news/page/2.
 *
 * The whole list is live and worth indexing, and it cannot be reached by a
 * number of its own — page 2 is the address /news/page/2 — so the address and
 * the canonical both come from the page number.
 */
function newsListHead(int $page): string
{
    $page = max(1, $page);
    $base = newsSiteBaseUrl();

    $title = $page > 1
        ? 'News & Updates - page ' . $page . ' | ' . newsSiteName()
        : 'News & Updates | ' . newsSiteName();

    return newsHeadTags([
        'title' => $title,
        'description' => 'News, announcements and updates from ' . newsSiteName()
            . ' - enrollment, events and student achievements.',
        'image' => $base . '/_sitc/images/cover.webp',
        'url' => $base . ($page > 1 ? '/news/page/' . $page : '/news'),
        'type' => 'website',
    ]);
}

/**
 * Put the real tags into a page that already has the site's generic ones.
 *
 * The page handed in is the SPA shell the build writes (200.html). It carries
 * the site's own title and og: tags, which are removed FIRST: a crawler that
 * reads only the first tag of a kind would otherwise pick up the generic card
 * and the story would share as the bare site name.
 *
 * Only the <head> is touched. The rest of the shell — the entry script, the
 * empty #__nuxt — is passed through untouched, byte for byte, because that is
 * what boots the app that draws the page.
 */
function newsInjectHead(string $shell, string $tags): string
{
    $close = stripos($shell, '</head>');
    if ($close === false) {
        // No head to write into (an unexpected shell): the tags go first so
        // they are still found, and nothing of the shell is lost.
        return $tags . $shell;
    }

    $head = substr($shell, 0, $close);
    $rest = substr($shell, $close);

    $head = preg_replace('#<title>.*?</title>#is', '', $head) ?? $head;
    $head = preg_replace('#<script\s+id="unhead:payload"[^>]*>.*?</script>#is', '', $head) ?? $head;
    $head = preg_replace('#<meta\s+name="description"[^>]*>#i', '', $head) ?? $head;
    $head = preg_replace('#<meta\s+property="og:[^"]*"[^>]*>#i', '', $head) ?? $head;
    $head = preg_replace('#<meta\s+name="twitter:[^"]*"[^>]*>#i', '', $head) ?? $head;

    return $head . $tags . $rest;
}
