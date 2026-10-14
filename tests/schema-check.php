<?php
/**
 * What happens BEFORE migrations/add-gallery-and-slugs.sql has been run.
 *
 * Every table and column read fails, which is exactly what MySQL does when the
 * table or column is missing. The site must keep working: numeric addresses,
 * no gallery, no database errors on the page.
 *
 * This runs in its own process on purpose. The schema guards remember their
 * answer for the whole request, so a run that has already seen the migration
 * could not then pretend it is missing. Run it from the project root:
 *
 *     php tests/schema-check.php
 */

$base = __DIR__ . '/../public/includes';
require_once $base . '/gallery.php';
require_once $base . '/slug.php';

$fails = 0;
function check(string $label, $got, $want): void
{
    global $fails;
    if ($got !== $want) {
        $fails++;
        echo "FAIL  $label\n      got  " . var_export($got, true) . "\n      want " . var_export($want, true) . "\n";
    } else {
        echo "  ok  $label\n";
    }
}

/** A database that refuses every question — no table, no column. */
class BrokenPdo extends PDO
{
    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new PDOException('stub: no such table or column');
    }

    public function query(string $query, ?int $fetchMode = null, ...$args): PDOStatement|false
    {
        throw new PDOException('stub: no such table or column');
    }
}

$pdo = new BrokenPdo();

echo "== the migration has not been run ==\n";
check('the readable-address column is reported missing', newsSlugReady($pdo), false);
check('the sub-image table is reported missing', newsGalleryReady($pdo), false);

echo "\n== reading still works ==\n";
check('a news item just has no sub images', newsGalleryItems($pdo, 10), []);
check('there are no photo counts', newsGalleryCounts($pdo, [1, 2, 3]), []);
check('no gallery is printed', newsGalleryHtml($pdo, 10, 'Title'), '');

echo "\n== changing is refused, not crashed ==\n";
check('adding is refused', newsGalleryAdd($pdo, 10, 'uploads/news/a.jpg'), false);
check('adding with a caption is refused too', newsGalleryAdd($pdo, 10, 'x.jpg', 'Cap'), false);
check('removing is refused', newsGalleryRemove($pdo, 1, 10), false);
check('removing all is a safe no-op', (function () use ($pdo) {
    newsGalleryRemoveAll($pdo, 10);
    return true;
})(), true);
check('re-captioning is refused', newsGalleryUpdateCaption($pdo, 1, 10, 'x'), false);
check('moving is refused', newsGalleryMove($pdo, 1, 10, 'up'), false);
check('a single image lookup finds nothing', newsGalleryItem($pdo, 1, 10), null);

echo "\n== links still work, using the number ==\n";
check('a numeric address is produced', newsUrl(['id' => 12]), '/news/12');
check('a numeric address is produced with an empty slug too', newsUrl(['id' => 12, 'slug' => '']), '/news/12');

echo "\n";
echo $fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n";
exit($fails === 0 ? 0 : 1);
