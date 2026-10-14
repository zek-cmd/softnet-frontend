<?php
require_once __DIR__ . '/sanitize.php';

/**
 * Tags for a news item.
 *
 * Tags are stored in ONE column ("enrollment, sports, TESDA") instead of a
 * second table: the list is short, it is only ever read together with the news
 * item, and a school site does not need to search tags on their own.
 *
 * A tag is free text typed by the admin. It is cleaned here as well, because
 * the value is printed back into the page as a link.
 */

const NEWS_TAG_LIMIT = 8;
const NEWS_TAG_LENGTH = 24;

/**
 * Clean what the admin typed into one stored tag line.
 *
 * Splits on commas, semicolons, pipes and new lines, so pasting "sports;
 * TESDA" behaves the same as "sports, TESDA". Tags are de-duplicated without
 * caring about case, which keeps "Enrollment" and "enrollment" from both
 * appearing. The first spelling typed is the one shown.
 *
 * The ORDER the admin typed is kept: the first tag is the one shown first.
 */
function normalizeNewsTags($raw): string
{
    if (is_array($raw)) {
        $raw = implode(',', array_map('strval', $raw));
    }

    $raw = strip_tags((string) $raw);
    $raw = str_replace(["\r", "\n", ';', '|', '/'], ',', $raw);

    $seen = [];
    $kept = [];

    foreach (explode(',', $raw) as $piece) {
        $tag = preg_replace('/\s+/u', ' ', trim($piece));
        if ($tag === null) {
            continue;
        }
        $tag = trim($tag);
        if ($tag === '') {
            continue;
        }

        $tag = function_exists('mb_substr')
            ? mb_substr($tag, 0, NEWS_TAG_LENGTH)
            : substr($tag, 0, NEWS_TAG_LENGTH);
        $tag = trim($tag);
        if ($tag === '') {
            continue;
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($tag) : strtolower($tag);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $kept[] = $tag;

        if (count($kept) >= NEWS_TAG_LIMIT) {
            break;
        }
    }

    return implode(', ', $kept);
}

/**
 * The stored tag line as a plain list of tags, ready to print or loop over.
 */
function newsTagList($stored): array
{
    if (is_array($stored)) {
        $stored = implode(',', array_map('strval', $stored));
    }

    $tags = [];
    foreach (explode(',', (string) $stored) as $piece) {
        $tag = trim($piece);
        if ($tag !== '') {
            $tags[] = $tag;
        }
    }

    return $tags;
}

/**
 * The link for one tag: /news?tag=Enrollment
 *
 * urlencode keeps a tag such as "A&B" from breaking the address, and the value
 * is escaped again for the attribute when it is printed.
 */
function newsTagUrl(string $tag, string $base = '/news'): string
{
    return $base . '?tag=' . urlencode($tag);
}

/**
 * The tag chips, as HTML.
 *
 * Each chip links to the filtered list, so a reader can tap "Enrollment" and
 * see every enrollment news item.
 */
function newsTagChipsHtml($stored, string $base = '/news'): string
{
    $tags = newsTagList($stored);
    if (!$tags) {
        return '';
    }

    $html = '<div class="tag-row">';
    foreach ($tags as $tag) {
        $html .= '<a class="tag-chip" href="'
            . sanitizeOutput(newsTagUrl($tag, $base)) . '">#'
            . sanitizeOutput($tag) . '</a>';
    }
    $html .= '</div>';

    return $html;
}
