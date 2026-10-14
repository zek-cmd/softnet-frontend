<?php
/**
 * Checks for the pure logic behind the news features: the readable addresses
 * ("slugs"), the upload checks and the error messages.
 *
 * No database and no web server needed. Run it from the project root:
 *
 *     php tests/logic-check.php
 *
 * Every line prints "ok" or "FAIL". It exits non-zero when anything fails, so
 * it can be used in a script.
 */

$base = __DIR__ . '/../public/includes';
require_once $base . '/slug.php';
require_once $base . '/upload.php';
require_once $base . '/gallery.php';
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

echo "== readable addresses (slugs) ==\n";
check('plain title', newsSlugify('SOFTNET Scholarship 2026'), 'softnet-scholarship-2026');
check('apostrophe joins the word', newsSlugify("SOFTNET's 2026 Scholarship"), 'softnets-2026-scholarship');
check('dash and punctuation', newsSlugify('Enrollment — Now Open!'), 'enrollment-now-open');
check('accented letters fold', newsSlugify('Año Nuevo Fiesta'), 'ano-nuevo-fiesta');
check('extra spaces collapse', newsSlugify('a    b   c'), 'a-b-c');
check('leading and trailing dashes go', newsSlugify('  --hello--  '), 'hello');
check('symbols only falls back', newsSlugify('!!! ??? ***'), 'news');
check('empty falls back', newsSlugify(''), 'news');
check('digits only is prefixed', newsSlugify('2026'), 'news-2026');
check('digits with a word are kept', newsSlugify('Batch 2026'), 'batch-2026');
check('numbers are kept', newsSlugify('Batch 12 Graduation'), 'batch-12-graduation');
check('underscore becomes a dash', newsSlugify('a_b'), 'a-b');

$long = newsSlugify(str_repeat('scholarship ', 20));
check('a long title is cut to 100', strlen($long) <= 100, true);
check('a long title is cut at a word', substr($long, -1) !== '-', true);

echo "\n== address shapes that are allowed ==\n";
// newsFindByAddress() accepts a number or a slug. Anything else is refused
// before it can reach the database.
$refused = ['../../etc/passwd', 'slug\' OR 1=1--', 'a b', 'slug;drop', '<script>', ''];
foreach ($refused as $bad) {
    $ok = ctype_digit($bad) || preg_match('/^[a-z0-9-]{1,100}$/', $bad);
    check('refused: ' . ($bad === '' ? '(empty)' : $bad), (bool) $ok, false);
}
foreach (['12', 'softnet-scholarship-2026', 'a'] as $good) {
    $ok = ctype_digit($good) || preg_match('/^[a-z0-9-]{1,100}$/', $good);
    check('accepted: ' . $good, (bool) $ok, true);
}

echo "\n== file sizes, as shown to the admin ==\n";
check('2MB', newsFormatBytes(2 * 1024 * 1024), '2MB');
check('512KB', newsFormatBytes(512 * 1024), '512KB');
check('900 bytes', newsFormatBytes(900), '900B');
check('1.5MB', newsFormatBytes(1572864), '1.5MB');

echo "\n== upload error messages ==\n";
check('the server limit is named', strpos(newsUploadErrorMessage(UPLOAD_ERR_INI_SIZE), 'server') !== false, true);
check('a cut-off upload says try again', strpos(newsUploadErrorMessage(UPLOAD_ERR_PARTIAL), 'try again') !== false, true);
check('every code has a message', newsUploadErrorMessage(999) !== '', true);

echo "\n== which file types are allowed ==\n";
check('jpeg allowed', isset(newsAllowedImageTypes()['image/jpeg']), true);
check('png allowed', isset(newsAllowedImageTypes()['image/png']), true);
check('webp allowed', isset(newsAllowedImageTypes()['image/webp']), true);
check('gif allowed', isset(newsAllowedImageTypes()['image/gif']), true);
check('php refused', isset(newsAllowedImageTypes()['application/x-php']), false);
check('svg refused', isset(newsAllowedImageTypes()['image/svg+xml']), false);
check('the gallery limit is 8', NEWS_GALLERY_LIMIT, 8);
check('the size limit is 2MB', NEWS_IMAGE_MAX_BYTES, 2097152);

echo "\n== several files chosen at once ==\n";
$_FILES['t'] = [
    'name' => ['a.jpg', 'b.jpg', ''],
    'type' => ['image/jpeg', 'image/jpeg', ''],
    'tmp_name' => ['/tmp/a', '/tmp/b', ''],
    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
    'size' => [100, 200, 0],
];
$collected = newsCollectUploadedFiles('t');
check('two real files kept, the empty one dropped', count($collected), 2);
check('the first keeps its name', $collected[0]['name'], 'a.jpg');
check('the second keeps its size', $collected[1]['size'], 200);

$_FILES['one'] = ['name' => 'x.jpg', 'tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK, 'size' => 10, 'type' => 'image/jpeg'];
check('a single file still works', count(newsCollectUploadedFiles('one')), 1);

$_FILES['none'] = ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'type' => ''];
check('no file chosen yields an empty list', newsCollectUploadedFiles('none'), []);

unset($_FILES['t'], $_FILES['one'], $_FILES['none']);

echo "\n== stored image paths, checked before anything is deleted ==\n";
check('a plain upload path is kept', cleanNewsImagePath('uploads/news/x.jpg'), 'uploads/news/x.jpg');
check('javascript is refused', cleanNewsImagePath('javascript:alert(1)'), '');
check('traversal is refused', cleanNewsImagePath('../../config.php'), '');
check('protocol-relative is refused', cleanNewsImagePath('//evil.example/x.jpg'), '');
check('an absolute server path is refused', cleanNewsImagePath('/etc/passwd'), '');
check('https is kept', cleanNewsImagePath('https://x.example/a.jpg'), 'https://x.example/a.jpg');

echo "\n== captions ==\n";
check('tags stripped', newsCaption('<b>Hi</b> there'), 'Hi there');
check('clipped to the column length', mb_strlen(newsCaption(str_repeat('a', 400))), 255);
check('an array is refused, not fatal', newsCaption(['evil']), '');
check('an object is refused, not fatal', newsCaption((object) ['x' => 1]), '');
check('null is empty', newsCaption(null), '');

echo "\n== the address a news item links to ==\n";
check('with a slug', newsUrl(['id' => 12, 'slug' => 'hello-world']), '/news/hello-world');
check('without a slug', newsUrl(['id' => 12, 'slug' => '']), '/news/12');
check('with no slug key at all', newsUrl(['id' => 12]), '/news/12');

echo "\n";
echo $fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n";
exit($fails === 0 ? 0 : 1);
