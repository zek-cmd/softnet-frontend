<?php
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/news-schema.php';

/**
 * Sub images ("the gallery") for one news item.
 *
 * The cover photo stays a single column on the news table — it is what the
 * cards and the Facebook preview use. The sub images are extra photos shown
 * under the cover on the news item page, as a grid that opens into a viewer.
 *
 * They live in their own table (news_images) rather than a JSON column,
 * because rows are easy to reorder, easy to delete one at a time, and easy to
 * count without reading the whole news record.
 *
 * The table only exists after migrations/add-gallery-and-slugs.sql has been
 * run. Every function here checks first, so an unmigrated database simply
 * behaves as "no sub images" instead of failing.
 */

/** How many sub images one news item may have. */
const NEWS_GALLERY_LIMIT = NEWS_GALLERY_MAX_FILES;

/**
 * Every sub image of one news item, in the order the admin chose.
 *
 * @return array<int,array{id:int,image:string,caption:string,sort_order:int}>
 */
function newsGalleryItems(PDO $pdo, int $newsId): array
{
    if ($newsId <= 0 || !newsGalleryReady($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, image, caption, sort_order
           FROM news_images
          WHERE news_id = ?
          ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$newsId]);

    return $stmt->fetchAll();
}

/**
 * How many sub images each news item has, for a whole page of results.
 *
 * The list page shows a small "3 photos" marker, and asking the database once
 * for the whole page is far cheaper than asking per news item.
 *
 * @param int[] $newsIds
 * @return array<int,int> news id => count
 */
function newsGalleryCounts(PDO $pdo, array $newsIds): array
{
    $newsIds = array_values(array_filter(array_map('intval', $newsIds), fn($n) => $n > 0));
    if (!$newsIds || !newsGalleryReady($pdo)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($newsIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT news_id, COUNT(*) AS total
           FROM news_images
          WHERE news_id IN ($placeholders)
          GROUP BY news_id"
    );
    $stmt->execute($newsIds);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['news_id']] = (int) $row['total'];
    }

    return $counts;
}

/**
 * Add one already-uploaded sub image to a news item.
 */
function newsGalleryAdd(PDO $pdo, int $newsId, string $storedPath, $caption = ''): bool
{
    if ($newsId <= 0 || $storedPath === '' || !newsGalleryReady($pdo)) {
        return false;
    }

    if (count(newsGalleryItems($pdo, $newsId)) >= NEWS_GALLERY_LIMIT) {
        return false;
    }

    // New pictures go last, so the order the admin uploaded them is kept.
    $position = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM news_images WHERE news_id = ?');
    $position->execute([$newsId]);
    $sortOrder = (int) $position->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO news_images (news_id, image, caption, sort_order) VALUES (?, ?, ?, ?)'
    );

    return $stmt->execute([$newsId, $storedPath, newsCaption($caption), $sortOrder]);
}

/**
 * One sub image, checked to belong to this news item.
 *
 * The news_id is part of the lookup on purpose: it means a request cannot
 * delete another news item's photo by guessing its number.
 */
function newsGalleryItem(PDO $pdo, int $imageId, int $newsId): ?array
{
    if ($imageId <= 0 || $newsId <= 0 || !newsGalleryReady($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, image, caption, sort_order FROM news_images WHERE id = ? AND news_id = ?');
    $stmt->execute([$imageId, $newsId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Remove one sub image: the database row and the file on disk.
 */
function newsGalleryRemove(PDO $pdo, int $imageId, int $newsId): bool
{
    $item = newsGalleryItem($pdo, $imageId, $newsId);
    if (!$item) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM news_images WHERE id = ? AND news_id = ?');
    $stmt->execute([$imageId, $newsId]);

    newsDeleteImageFile($item['image']);
    newsGalleryCloseGap($pdo, $newsId);

    return true;
}

/**
 * Remove every sub image of a news item. Used when the news item is deleted.
 */
function newsGalleryRemoveAll(PDO $pdo, int $newsId): void
{
    foreach (newsGalleryItems($pdo, $newsId) as $item) {
        newsGalleryRemove($pdo, (int) $item['id'], $newsId);
    }
}

/**
 * Change the caption of one sub image.
 */
function newsGalleryUpdateCaption(PDO $pdo, int $imageId, int $newsId, $caption): bool
{
    if (!newsGalleryItem($pdo, $imageId, $newsId)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE news_images SET caption = ? WHERE id = ? AND news_id = ?');

    return $stmt->execute([newsCaption($caption), $imageId, $newsId]);
}

/**
 * Move one sub image one place earlier or later.
 *
 * The neighbours swap numbers, so the order stays a clean 0,1,2,3 with no
 * gaps — which keeps every later change simple.
 */
function newsGalleryMove(PDO $pdo, int $imageId, int $newsId, string $direction): bool
{
    $items = newsGalleryItems($pdo, $newsId);
    if (!$items) {
        return false;
    }

    $index = null;
    foreach ($items as $i => $item) {
        if ((int) $item['id'] === $imageId) {
            $index = $i;
            break;
        }
    }

    if ($index === null) {
        return false;
    }

    $target = $direction === 'up' ? $index - 1 : $index + 1;
    if ($target < 0 || $target >= count($items)) {
        return false;
    }

    // Renumber the whole strip from the new order. One statement per row, but a
    // gallery is at most eight rows, and this can never leave duplicates behind
    // the way swapping two numbers can if a request fails halfway.
    $ordered = $items;
    $moved = $ordered[$index];
    array_splice($ordered, $index, 1);
    array_splice($ordered, $target, 0, [$moved]);

    $stmt = $pdo->prepare('UPDATE news_images SET sort_order = ? WHERE id = ? AND news_id = ?');
    foreach ($ordered as $position => $item) {
        $stmt->execute([$position, (int) $item['id'], $newsId]);
    }

    return true;
}

/**
 * Renumber a news item's sub images 0,1,2,3...
 *
 * Called after a delete so the numbers never keep a hole in them.
 */
function newsGalleryCloseGap(PDO $pdo, int $newsId): void
{
    $items = newsGalleryItems($pdo, $newsId);
    $stmt = $pdo->prepare('UPDATE news_images SET sort_order = ? WHERE id = ?');

    foreach ($items as $position => $item) {
        if ((int) $item['sort_order'] !== $position) {
            $stmt->execute([$position, (int) $item['id']]);
        }
    }
}

/**
 * A caption, cleaned to the length the column allows.
 *
 * Takes anything: sanitizeString() already turns an array or an object into an
 * empty string, which matters because this value can come straight from a
 * request. Typing the parameter as string would instead stop the page with a
 * fatal error when something odd is posted.
 */
function newsCaption($caption, int $maxLength = 255): string
{
    return sanitizeString($caption, $maxLength);
}

/**
 * An <img> src for a stored sub image.
 *
 * The stored path is checked the same way cover photos are, so a row that was
 * written before this file existed cannot point the browser somewhere else.
 */
function newsGalleryImageSrc(string $storedPath): string
{
    require_once __DIR__ . '/html-clean.php';

    return cleanNewsImagePath($storedPath);
}

/**
 * The gallery block on the news item page: the photos under the cover.
 *
 * On a phone the photos are a sideways strip: one fills the width, and a flick
 * slides to the next. From 640px up the same markup is a grid again — see
 * site.css — so there is one set of markup for both, and no JavaScript is
 * needed to tell them apart. The pager below the strip (dots plus a "2 of 5"
 * counter) is filled in by gallery.js and starts hidden, so a reader with
 * JavaScript off sees the strip and nothing empty underneath it.
 *
 * Every item keeps a real link as its href, so a reader with JavaScript turned
 * off can still open the full-size photo the old way.
 */
function newsGalleryHtml(PDO $pdo, int $newsId, string $title = ''): string
{
    $items = newsGalleryItems($pdo, $newsId);
    if (!$items) {
        return '';
    }

    $section = '<section class="gallery" aria-label="Photo gallery" data-gallery>';
    $figures = '';

    // The viewer needs one counter for the whole grid, so it numbers the photos
    // in reading order as they are printed.
    $position = 0;

    foreach ($items as $item) {
        $src = newsGalleryImageSrc((string) $item['image']);
        if ($src === '') {
            continue;
        }

        $position++;
        $caption = trim((string) $item['caption']);
        // The caption doubles as the alt text. With no caption, the news title
        // plus the position is the honest description for a screen reader.
        $alt = $caption !== ''
            ? $caption
            : ($title !== '' ? $title . ' — photo ' . $position : 'Photo ' . $position);

        $figures .= '<figure class="gallery-item">';
        $figures .= '<a class="gallery-link" href="/' . sanitizeOutput($src) . '"'
            . ' data-gallery-open data-gallery-src="/' . sanitizeOutput($src) . '"'
            . ' data-gallery-alt="' . sanitizeOutput($alt) . '"'
            . ' data-gallery-caption="' . sanitizeOutput($caption) . '"'
            . ' data-gallery-index="' . $position . '">';
        $figures .= '<img src="/' . sanitizeOutput($src) . '" alt="' . sanitizeOutput($alt) . '"'
            . ' loading="lazy" decoding="async">';
        $figures .= '</a>';

        if ($caption !== '') {
            $figures .= '<figcaption class="gallery-caption">' . sanitizeOutput($caption) . '</figcaption>';
        }

        $figures .= '</figure>';
    }

    // Every stored path was refused, so there is no grid to show. Printing the
    // section anyway would leave an empty bordered strip under the story.
    if ($figures === '') {
        return '';
    }

    return $section
        . '<div class="gallery-grid">' . $figures . '</div>'
        . '<div class="gallery-pager" data-gallery-pager hidden></div></section>';
}
