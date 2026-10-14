<?php
/**
 * Checks for the gallery (sub images) and readable addresses when they DO work
 * — ordering, the 8 image limit, deletion safety, slug uniqueness.
 *
 * A stub stands in for MySQL, so this needs no database. It answers the few
 * queries the code makes and records the writes, which is enough to check the
 * rules. Run it from the project root:
 *
 *     php tests/db-logic-check.php
 *
 * What this can NOT prove: that the SQL is valid MySQL, or that the migration
 * file runs. Only a real database can show that.
 */

$base = __DIR__ . '/../public/includes';
require_once $base . '/gallery.php';
require_once $base . '/slug.php';
require_once $base . '/html-clean.php';

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

/**
 * The stub's shared state. Two queues drive it: one entry per query for rows,
 * one per query for a single value. Answers are taken when the query runs.
 */
class Fake
{
    public static array $rowsQueue = [];
    public static array $colQueue = [];
    public static array $rows = [];
    public static array $writes = [];
    public static array $log = [];
}

class FakeStatement extends PDOStatement
{
    public function __construct(public string $sql = '') {}

    public function execute($params = null): bool
    {
        if (is_array($params)) {
            Fake::$writes[] = [$this->sql, $params];
        }
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_ASSOC, int $cursor = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed
    {
        return array_shift(Fake::$rows) ?: false;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC, ...$args): array
    {
        $rows = Fake::$rows;
        Fake::$rows = [];
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        // Taken only when asked for, so a statement that never reads a single
        // value does not eat the next answer.
        return Fake::$colQueue ? array_shift(Fake::$colQueue) : false;
    }

    public function rowCount(): int { return 1; }
}

class FakePdo extends PDO
{
    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        Fake::$log[] = $query;
        Fake::$rows = array_shift(Fake::$rowsQueue) ?? [];
        return new FakeStatement($query);
    }

    public function query(string $query, ?int $fetchMode = null, ...$args): PDOStatement|false
    {
        return $this->prepare($query);
    }
}

function reset_fake(array $rowsQueue = [], array $colQueue = []): void
{
    Fake::$rowsQueue = $rowsQueue;
    Fake::$colQueue = $colQueue;
    Fake::$rows = [];
    Fake::$writes = [];
    Fake::$log = [];
}

/** Did any prepared statement contain this text? */
function sql_seen(string $needle): bool
{
    foreach (Fake::$log as $sql) {
        if (strpos($sql, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/** Writes only: INSERT, UPDATE or DELETE, ignoring the SELECTs. */
function data_writes(): array
{
    $out = [];
    foreach (Fake::$writes as [$sql, $params]) {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $sql)) {
            $out[] = [$sql, $params];
        }
    }
    return $out;
}

$pdo = new FakePdo();

// These checks assume the migration HAS been run. The "not run yet" behaviour
// is checked separately, by tests/schema-check.php.
reset_fake([[]]);
if (!newsSlugReady($pdo)) {
    fwrite(STDERR, "The stub must report the schema as present for these checks.\n");
    exit(2);
}
// Warm the gallery guard too, so its own probe query does not consume an answer
// meant for a later check. The guard remembers its answer per request.
reset_fake([[]]);
newsGalleryReady($pdo);

$threeImages = [
    ['id' => 1, 'image' => 'a.jpg', 'caption' => '', 'sort_order' => 0],
    ['id' => 2, 'image' => 'b.jpg', 'caption' => '', 'sort_order' => 1],
    ['id' => 3, 'image' => 'c.jpg', 'caption' => '', 'sort_order' => 2],
];

echo "== a readable address is made unique ==\n";
reset_fake([], [false]);
check('a free address is used as it is', newsUniqueSlug($pdo, 'Scholarship 2026'), 'scholarship-2026');

reset_fake([], [5, false]);
check('a taken one becomes -2', newsUniqueSlug($pdo, 'Scholarship 2026'), 'scholarship-2026-2');

reset_fake([], [5, 6, false]);
check('taken twice becomes -3', newsUniqueSlug($pdo, 'Scholarship 2026'), 'scholarship-2026-3');

reset_fake([], [5, 5, 5]);
check('it never hangs when every name is taken', strlen(newsUniqueSlug($pdo, 'Scholarship 2026')) > 0, true);

reset_fake([], [false]);
newsUniqueSlug($pdo, 'Scholarship 2026', 7);
check("re-saving keeps the item's own address", sql_seen('id <> ?'), true);

echo "\n== sub images are read in the chosen order ==\n";
reset_fake([[
    ['id' => 3, 'image' => 'c.jpg', 'caption' => 'Third', 'sort_order' => 0],
    ['id' => 1, 'image' => 'a.jpg', 'caption' => '',      'sort_order' => 1],
]]);
$items = newsGalleryItems($pdo, 10);
check('two images returned', count($items), 2);
check('the first is the one ordered first', $items[0]['image'], 'c.jpg');
check('the query itself orders them', sql_seen('ORDER BY sort_order ASC'), true);

echo "\n== photo counts are one query, not one per news item ==\n";
reset_fake([[['news_id' => 4, 'total' => 3], ['news_id' => 5, 'total' => 1]]]);
$counts = newsGalleryCounts($pdo, [4, 5, 6]);
check('the count for item 4', $counts[4], 3);
check('the count for item 5', $counts[5], 1);
check('item 6 is simply absent', isset($counts[6]), false);
check('it used one grouped query', sql_seen('GROUP BY news_id'), true);

echo "\n== a sub image belongs to its own news item only ==\n";
reset_fake([[], [], []]);
check('a foreign image is not found', newsGalleryItem($pdo, 99, 10), null);
check('removing it is refused', newsGalleryRemove($pdo, 99, 10), false);
check('re-captioning it is refused', newsGalleryUpdateCaption($pdo, 99, 10, 'x'), false);
check('every lookup is scoped to the news item', sql_seen('WHERE id = ? AND news_id = ?'), true);

echo "\n== moving a photo renumbers the whole strip ==\n";
reset_fake([$threeImages]);
check('moving the last one up works', newsGalleryMove($pdo, 3, 10, 'up'), true);

$positions = [];
foreach (data_writes() as [$sql, $params]) {
    if (strpos($sql, 'UPDATE news_images SET sort_order') !== false && count($params) === 3) {
        $positions[(int) $params[1]] = (int) $params[0];
    }
}
ksort($positions);
// Moving the last of three up one place gives a, c, b.
check('a stays first', $positions[1] ?? null, 0);
check('c moved to second', $positions[3] ?? null, 1);
check('b slid to third', $positions[2] ?? null, 2);
$values = array_values($positions);
sort($values);
check('the numbers stay 0,1,2 with no gaps', $values, [0, 1, 2]);

echo "\n== moving off the ends changes nothing ==\n";
reset_fake([$threeImages]);
check('the first cannot move up', newsGalleryMove($pdo, 1, 10, 'up'), false);
check('nothing was written', data_writes(), []);

reset_fake([$threeImages]);
check('the last cannot move down', newsGalleryMove($pdo, 3, 10, 'down'), false);
check('nothing was written', data_writes(), []);

echo "\n== the 8 image limit ==\n";
reset_fake([array_fill(0, 8, ['id' => 1, 'image' => 'x.jpg', 'caption' => '', 'sort_order' => 0])]);
check('a full gallery refuses more', newsGalleryAdd($pdo, 10, 'uploads/news/new.jpg'), false);
$inserted = false;
foreach (data_writes() as [$sql, $params]) {
    if (strpos($sql, 'INSERT INTO news_images') !== false) { $inserted = true; }
}
check('no insert was attempted', $inserted, false);

echo "\n== a new photo goes last ==\n";
// The stub answers COALESCE(MAX(sort_order), -1) + 1 directly: 3 means the last
// image sits at 2, so the new one goes at 3.
reset_fake([[]], [3]);
check('adding works', newsGalleryAdd($pdo, 10, 'uploads/news/new.jpg', 'Caption'), true);
$sortOrder = null;
$storedCaption = null;
foreach (data_writes() as [$sql, $params]) {
    if (strpos($sql, 'INSERT INTO news_images') !== false) {
        $sortOrder = (int) $params[3];
        $storedCaption = $params[2];
    }
}
check('it is placed after the last one', $sortOrder, 3);
check('its caption is stored cleaned', $storedCaption, 'Caption');

echo "\n== removing a photo ==\n";
reset_fake([
    [['id' => 7, 'image' => 'uploads/news/gone.jpg', 'caption' => '', 'sort_order' => 1]],
    [],
    $threeImages,
]);
check('removing an own image works', newsGalleryRemove($pdo, 7, 10), true);
check('the delete is scoped to the news item', sql_seen('DELETE FROM news_images WHERE id = ? AND news_id = ?'), true);

echo "\n== the gallery grid that readers see ==\n";
reset_fake([[
    ['id' => 1, 'image' => 'uploads/news/a.jpg', 'caption' => 'The team', 'sort_order' => 0],
    ['id' => 2, 'image' => 'uploads/news/b.jpg', 'caption' => '', 'sort_order' => 1],
]]);
$html = newsGalleryHtml($pdo, 10, 'Sports Day');
check('the grid is printed', strpos($html, 'gallery-grid') !== false, true);
check('a caption becomes the alt text', strpos($html, 'alt="The team"') !== false, true);
check('no caption falls back to the title', strpos($html, 'alt="Sports Day — photo 2"') !== false, true);
check('no heading is printed above the grid', strpos($html, 'gallery-title'), false);
check('no photo count is printed', strpos($html, ' photos'), false);
check('a photo carries what the viewer needs', strpos($html, 'data-gallery-open') !== false, true);
check('the caption is handed to the viewer', strpos($html, 'data-gallery-caption="The team"') !== false, true);
check('photos are numbered for the viewer', strpos($html, 'data-gallery-index="2"') !== false, true);
// The mobile swipe strip needs somewhere to draw its dots and counter. It is
// printed hidden so a reader with JavaScript off never sees an empty row.
check('the swipe pager is printed', strpos($html, 'data-gallery-pager') !== false, true);
check('the pager starts hidden', strpos($html, 'data-gallery-pager hidden') !== false, true);
check('a photo still has a real link as a fallback', strpos($html, 'href="/uploads/news/a.jpg"') !== false, true);
check('the link no longer forces a new tab', strpos($html, 'target="_blank"'), false);

reset_fake([[['id' => 1, 'image' => 'uploads/news/a.jpg', 'caption' => 'One', 'sort_order' => 0]]]);
$single = newsGalleryHtml($pdo, 10, 'X');
check('one photo still prints the grid', strpos($single, 'gallery-grid') !== false, true);
check('one photo has no count line either', strpos($single, 'gallery-note'), false);

reset_fake([[['id' => 1, 'image' => '../../config.php', 'caption' => '', 'sort_order' => 0]]]);
check('a malicious stored path prints no image', strpos(newsGalleryHtml($pdo, 10, 'X'), 'config.php'), false);

reset_fake([[['id' => 1, 'image' => 'javascript:alert(1)', 'caption' => '', 'sort_order' => 0]]]);
check('a javascript path prints no image', strpos(newsGalleryHtml($pdo, 10, 'X'), 'javascript'), false);
check('and no empty gallery strip is left behind', newsGalleryHtml($pdo, 10, 'X'), '');

echo "\n== an old news item gains its address on first open ==\n";
reset_fake(
    [[['id' => 12, 'title' => 'SOFTNET Sports Day', 'slug' => null]], []],
    [false]
);
check('the address comes from the title', newsEnsureSlug($pdo, 12), 'softnet-sports-day');

reset_fake([[['id' => 12, 'title' => 'SOFTNET Sports Day', 'slug' => 'kept-address']]]);
check('an item that already has one keeps it', newsEnsureSlug($pdo, 12), 'kept-address');

reset_fake([[]]);
check('an item that does not exist yields nothing', newsEnsureSlug($pdo, 999), '');

echo "\n";
echo $fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n";
exit($fails === 0 ? 0 : 1);
