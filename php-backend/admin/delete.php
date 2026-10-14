<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/gallery.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin');
    exit;
}
verifyCsrf();

$id = sanitizeInt($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /admin');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: /admin');
    exit;
}

// The sub images belong to this news item, so they go with it: the rows first,
// then the files on disk. The cover photo is removed afterwards. This has to
// happen before the news row is deleted, because the sub images are found by
// their news_id.
newsGalleryRemoveAll($pdo, $id);

newsDeleteImageFile($item['image']);

$pdo->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
flash('success', 'News item deleted successfully.');
header('Location: /admin');
exit;
