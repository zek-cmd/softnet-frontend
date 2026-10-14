<?php
require_once __DIR__ . '/sanitize.php';

/**
 * Cleaning for news content.
 *
 * News content is written in the admin with the Quill editor and stored in the
 * database as raw HTML. That HTML is printed as markup on public/news-item.php,
 * so anything dangerous that reaches the database would run in the browser of
 * every visitor who opens the news item.
 *
 * cleanNewsHtml() rebuilds the content from a whitelist of tags and prints it
 * back. Scripts, event handlers (onclick, onerror, ...), javascript: links and
 * raw styles are dropped. Use it EVERY time stored news HTML is printed.
 *
 * The database keeps the original HTML: cleaning happens on display, so items
 * written before this file existed are covered too.
 */

/**
 * Tags the rich-text editor can produce and that are safe to print.
 */
function newsHtmlAllowedTags(): array
{
    return [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'h1', 'h2', 'h3', 'ol', 'ul', 'li', 'a', 'blockquote', 'span',
    ];
}

/**
 * Tags removed together with everything inside them.
 *
 * The editor never produces these, so nothing worth keeping is lost: news
 * images are added with the Image field on the news form, not inside the text.
 */
function newsHtmlDropTags(): array
{
    return [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
        'canvas', 'audio', 'video', 'source', 'track', 'img', 'map', 'area',
    ];
}

/**
 * Return news HTML that is safe to print inside a page.
 */
function cleanNewsHtml($html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    // No DOM extension: show the content as text instead of as live markup.
    if (!class_exists('DOMDocument')) {
        return sanitizeOutput($html);
    }

    // A random wrapper id, so content that contains markup cannot fake it.
    $marker = 'sitc-clean-' . bin2hex(random_bytes(4));

    $flags = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;
    if (defined('LIBXML_NONET')) {
        $flags |= LIBXML_NONET;
    }

    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $loaded = $doc->loadHTML(
        '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
        . '<div id="' . $marker . '">' . $html . '</div>',
        $flags
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $loaded ? cleanNewsHtmlFindRoot($doc, $marker) : null;
    if (!$root instanceof DOMElement) {
        // The parser could not make sense of it — never fall back to raw HTML.
        return sanitizeOutput($html);
    }

    cleanNewsHtmlChildren($root);

    $output = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $output .= (string) $doc->saveHTML($child);
    }

    return trim($output);
}

/**
 * Find the wrapper element the content was parsed into.
 */
function cleanNewsHtmlFindRoot(DOMDocument $doc, string $marker): ?DOMElement
{
    $byId = $doc->getElementById($marker);
    if ($byId instanceof DOMElement) {
        return $byId;
    }

    foreach ($doc->getElementsByTagName('div') as $div) {
        if ($div->getAttribute('id') === $marker) {
            return $div;
        }
    }

    return null;
}

/**
 * Walk the children of one element: drop dangerous tags, keep whitelisted ones,
 * and unwrap anything else so its text survives.
 */
function cleanNewsHtmlChildren(DOMNode $node): void
{
    $allowed = newsHtmlAllowedTags();
    $dropped = newsHtmlDropTags();

    $child = $node->firstChild;
    while ($child !== null) {
        $next = $child->nextSibling;

        if ($child instanceof DOMComment) {
            $node->removeChild($child);
        } elseif ($child instanceof DOMProcessingInstruction) {
            // A php opening tag that reached the database must stay plain text,
            // never be run as code.
            $text = $node->ownerDocument->createTextNode((string) $child->data);
            $node->replaceChild($text, $child);
        } elseif ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);

            if (in_array($tag, $dropped, true)) {
                $node->removeChild($child);
            } else {
                cleanNewsHtmlAttributes($child, $tag);
                cleanNewsHtmlChildren($child);

                if (!in_array($tag, $allowed, true)) {
                    // Unknown tag (div, table, font...): keep the text inside it,
                    // drop the tag itself. Children are already cleaned above.
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            }
        }

        $child = $next;
    }
}

/**
 * Strip every attribute except the few that are wanted.
 */
function cleanNewsHtmlAttributes(DOMElement $element, string $tag): void
{
    if (!$element->hasAttributes()) {
        return;
    }

    $classAllowed = in_array(
        $tag,
        ['p', 'span', 'li', 'div', 'h1', 'h2', 'h3', 'blockquote', 'ul', 'ol'],
        true
    );

    foreach (iterator_to_array($element->attributes) as $attribute) {
        $name = strtolower($attribute->name);
        $keep = false;

        if ($tag === 'a' && in_array($name, ['href', 'title', 'target', 'rel'], true)) {
            $keep = true;
        } elseif ($classAllowed && $name === 'class') {
            $keep = cleanNewsHtmlClass($attribute->value) !== '';
        }

        if (!$keep) {
            $element->removeAttribute($attribute->name);
            continue;
        }

        if ($name === 'class') {
            $element->setAttribute('class', cleanNewsHtmlClass($attribute->value));
        }
    }

    if ($tag === 'a') {
        $href = trim($element->getAttribute('href'));
        if ($href === '' || !cleanNewsHtmlUrlAllowed($href)) {
            $element->removeAttribute('href');
            $element->removeAttribute('target');
            $element->removeAttribute('rel');
            return;
        }

        $element->setAttribute('href', $href);

        if (preg_match('#^https?://#i', $href)) {
            // Outside links open in a new tab, safely.
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        } else {
            $element->removeAttribute('target');
            $element->removeAttribute('rel');
        }
    }
}

/**
 * Keep only editor class names such as "ql-align-center".
 */
function cleanNewsHtmlClass(string $value): string
{
    $kept = [];
    foreach (preg_split('/\s+/', trim($value)) ?: [] as $token) {
        if (preg_match('/^ql-[a-z0-9-]+$/i', $token)) {
            $kept[] = strtolower($token);
        }
    }

    return implode(' ', $kept);
}

/**
 * Allow web, mail and telephone links, plus links inside this site.
 * Anything else (javascript:, data: and friends) is refused.
 */
function cleanNewsHtmlUrlAllowed(string $url): bool
{
    // Control characters and spaces are used to hide "java\nscript:".
    $probe = preg_replace('/[\x00-\x20\x7f]/', '', strtolower(html_entity_decode($url, ENT_QUOTES, 'UTF-8')));
    if ($probe === null || $probe === '') {
        return false;
    }

    if (preg_match('/^[a-z][a-z0-9+.-]*:/', $probe)) {
        return (bool) preg_match('#^(https?|mailto|tel):#', $probe);
    }

    // No scheme: a relative link ("/news", "#top") is fine.
    return true;
}

/**
 * Clean an image path stored on a news item ("uploads/news/photo.jpg").
 *
 * The path is printed straight into an <img src="...">, so a stored
 * "javascript:" or "//evil.example" value is refused here.
 */
function cleanNewsImagePath($path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    $probe = preg_replace('/[\x00-\x20\x7f]/', '', strtolower(html_entity_decode($path, ENT_QUOTES, 'UTF-8')));
    if ($probe === null || $probe === '') {
        return '';
    }

    if (strpos($probe, '..') !== false) {
        return '';
    }

    // A full web address is allowed; any other "scheme:" is not.
    if (preg_match('#^[a-z][a-z0-9+.-]*:#', $probe)) {
        return preg_match('#^https?://#', $probe) ? $path : '';
    }

    // A leading slash means a path on this server; two slashes are a
    // protocol-relative address pointing at another site ("//evil.example").
    if ($probe[0] === '/') {
        return '';
    }

    return $path;
}
