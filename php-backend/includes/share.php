<?php
require_once __DIR__ . '/sanitize.php';

/**
 * Sharing a news item.
 *
 * Facebook reads the news page it is given and builds the preview card from
 * the Open Graph tags in its <head> (see includes/header.php). So the link
 * handed to Facebook must be the page the reader would open, not the API.
 */

/**
 * The site's own address, built from the request.
 *
 * The host is checked before it is used: it comes from the browser, and it ends
 * up inside a link. Anything that is not a plain host name is refused, and the
 * caller then falls back to a relative link.
 */
function newsSiteBaseUrl(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        return '';
    }

    $https = (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * The full web address of one news item, e.g. https://school.example/news/12
 */
function newsAbsoluteUrl(int $id): string
{
    return newsSiteBaseUrl() . '/news/' . $id;
}

/**
 * Facebook's share dialog address for a given page.
 *
 * This opens the normal Facebook share window with the page's own preview card
 * (title, summary, image) filled in. No Facebook app, key or login is needed.
 */
function facebookShareUrl(string $url): string
{
    return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url);
}

/**
 * The share row: Facebook, the phone's own share sheet, and copy link.
 *
 * All three are ordinary links or buttons. If a script never runs, the
 * Facebook link still works on its own — nothing important depends on JS.
 */
function newsShareBarHtml(int $id, string $title): string
{
    $url = newsAbsoluteUrl($id);
    $shareUrl = $url !== '' ? $url : '/news/' . $id;

    $html = '<div class="share-row" data-share-url="' . sanitizeOutput($shareUrl) . '"'
        . ' data-share-title="' . sanitizeOutput($title) . '">';
    $html .= '<span class="share-label">Share this</span>';

    $html .= '<a class="share-btn share-facebook"'
        . ' href="' . sanitizeOutput(facebookShareUrl($shareUrl)) . '"'
        . ' target="_blank" rel="noopener noreferrer">'
        . '<svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . '<path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/>'
        . '</svg>Share on Facebook</a>';

    $html .= '<button type="button" class="share-btn share-native" data-share-native hidden>'
        . '<svg class="share-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>'
        . '<path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>Share</button>';

    $html .= '<button type="button" class="share-btn share-copy" data-share-copy>'
        . '<svg class="share-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>'
        . '<span class="share-copy-text">Copy link</span></button>';

    $html .= '</div>';

    return $html;
}
