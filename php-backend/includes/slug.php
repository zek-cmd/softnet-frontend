<?php
require_once __DIR__ . '/sanitize.php';

/**
 * Readable web addresses for news items ("slugs").
 *
 * A news item can be opened at two addresses:
 *
 *   /news/12                            the original numeric address
 *   /news/softnet-scholarship-2026      the readable one
 *
 * BOTH keep working, forever. That matters: links already posted on Facebook
 * and pages already listed by Google use the numeric form, and they must not
 * break when an item gets its readable address.
 *
 * The readable address is never typed by the admin — it is worked out from the
 * title, so it is always clean, lowercase and safe inside a link.
 */

/**
 * Turn a title into a web address piece.
 *
 * "SOFTNET's 2026 Scholarship — Now Open!" becomes
 * "softnets-2026-scholarship-now-open".
 *
 * Letters and numbers survive, everything else becomes a single dash. Filipino
 * and Spanish letters (ñ, é, ü) are folded to their plain form first, so
 * "Año Nuevo" becomes "ano-nuevo" rather than losing the letters entirely.
 */
function newsSlugify(string $text, int $maxLength = 100): string
{
    $text = trim($text);

    // Accented letters are folded to their plain form BEFORE anything else, so
    // "Año Nuevo" becomes "ano-nuevo" and not "a-no-nuevo". The order
    // matters: on many servers iconv turns "ñ" into "~n", and the tilde would
    // then be treated as a separator, splitting the word in two. Replacing the
    // letters we know about first avoids that entirely.
    $text = strtr($text, [
        'ñ' => 'n', 'Ñ' => 'N', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        // Quotes are dropped rather than turned into a dash, so "SOFTNET's"
        // reads as "softnets" and not "softnet-s".
        '"' => '', "'" => '', '’' => '', '‘' => '', '“' => '', '”' => '',
        '—' => '-', '–' => '-',
    ]);

    // iconv then catches any accent outside the table above.
    if (function_exists('iconv')) {
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($folded !== false && $folded !== '') {
            $text = $folded;
        }
    }

    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

    // Anything that is not a letter, a number or a dash becomes a dash.
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

    // Collapse runs of dashes and trim the edges: "--a--b--" -> "a-b".
    $text = trim(preg_replace('/-+/', '-', $text) ?? '', '-');

    // Too long? Cut at the last complete word so the address stays readable.
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
        $lastDash = strrpos($text, '-');
        if ($lastDash !== false && $lastDash > $maxLength * 0.6) {
            $text = substr($text, 0, $lastDash);
        }
        $text = trim($text, '-');
    }

    // A title made only of symbols leaves nothing behind. "news" is a valid
    // address on its own and the number added later keeps it unique.
    if ($text === '') {
        return 'news';
    }

    // A title made only of digits ("Batch 2026" is fine, but "2026" alone is not)
    // would create an address that looks exactly like a news number. /news/2026
    // would then be read as "news item 2026" and never reach this slug, so the
    // address would silently open the wrong story. A short prefix keeps the two
    // shapes apart for good.
    if (ctype_digit($text)) {
        $text = 'news-' . $text;
    }

    return $text;
}

/**
 * A slug that no other news item is using yet.
 *
 * "scholarship" is taken, so the next one becomes "scholarship-2", then
 * "scholarship-3". $ignoreId lets an item keep its own slug while being saved
 * again (otherwise it would always collide with itself).
 */
function newsUniqueSlug(PDO $pdo, string $desired, int $ignoreId = 0): string
{
    $base = newsSlugify($desired);
    $slug = $base;
    $suffix = 1;

    // 100 attempts is far more than a school site will ever need; the counter
    // guard means a broken loop can never hang the page.
    while ($suffix <= 100) {
        $stmt = $pdo->prepare('SELECT id FROM news WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);

        if (!$stmt->fetchColumn()) {
            return $slug;
        }

        $suffix++;
        $slug = $base . '-' . $suffix;
    }

    // Last resort: the id-looking form, which is unique by the clock.
    return $base . '-' . substr((string) time(), -6);
}

/**
 * Make sure a news item has a slug, and return it.
 *
 * Called when an item is opened or saved. An item that already has a slug
 * keeps it: changing a live address would break every link already shared.
 * Items that predate this feature get their slug on first use.
 */
function newsEnsureSlug(PDO $pdo, int $id): string
{
    $stmt = $pdo->prepare('SELECT id, title, slug FROM news WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        return '';
    }

    $existing = trim((string) ($row['slug'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    $slug = newsUniqueSlug($pdo, (string) $row['title'], $id);

    // Only fill in an empty slug — never overwrite one that was set meanwhile
    // by another request in between.
    $update = $pdo->prepare('UPDATE news SET slug = ? WHERE id = ? AND (slug IS NULL OR slug = "")');
    $update->execute([$slug, $id]);

    return $slug;
}

/**
 * Find a news item from the piece in the address bar.
 *
 * Accepts BOTH shapes, so old shared links keep working:
 *   "12"                          -> the item with id 12
 *   "softnet-scholarship-2026"    -> the item with that slug
 *
 * @return array|false the news row, or false when nothing matches.
 */
function newsFindByAddress(PDO $pdo, string $address)
{
    $address = trim($address);
    if ($address === '') {
        return false;
    }

    // A plain number is the original address shape.
    if (ctype_digit($address)) {
        $id = sanitizeInt($address);
        if (!$id) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // Otherwise it must be one of our own slugs: lowercase letters, numbers
    // and dashes only. Anything else is refused before it reaches the query.
    //
    // Capital letters are folded down rather than refused, so a link someone
    // typed by hand (or a chat app that capitalised it) still finds the page.
    $address = function_exists('mb_strtolower') ? mb_strtolower($address, 'UTF-8') : strtolower($address);

    if (!preg_match('/^[a-z0-9-]{1,100}$/', $address)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT * FROM news WHERE slug = ?');
    $stmt->execute([$address]);
    $row = $stmt->fetch();

    if ($row) {
        return $row;
    }

    // The address may be one we handed out before the database was updated.
    // Falling back to the number keeps those links alive.
    if (preg_match('/-(\d+)$/', $address, $matches)) {
        $stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
        $stmt->execute([sanitizeInt($matches[1])]);
        return $stmt->fetch();
    }

    return false;
}

/**
 * The address of one news item, readable when possible.
 *
 * Used for every link the site prints. Falls back to /news/12 while an item
 * has no slug yet, so a page never links to "/news/".
 */
function newsUrl(array $item): string
{
    $slug = trim((string) ($item['slug'] ?? ''));
    if ($slug !== '') {
        return '/news/' . $slug;
    }

    return '/news/' . (int) ($item['id'] ?? 0);
}

/**
 * The FULL address of one news item, for sharing and for Facebook's preview.
 *
 * Facebook will not read a relative path, so this is built from the request.
 */
function newsAbsoluteItemUrl(array $item): string
{
    require_once __DIR__ . '/share.php';
    return newsSiteBaseUrl() . newsUrl($item);
}
