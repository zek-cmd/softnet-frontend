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

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_filter(array_map('intval', $ids), fn($n) => $n > 0));

if (count($ids) === 0) {
    flash('error', 'No news selected.');
    header('Location: /admin');
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id, image FROM news WHERE id IN ($placeholders)");
$stmt->execute($ids);
$rows = $stmt->fetchAll();

if (count($rows) === 0) {
    flash('error', 'Selected news not found.');
    header('Location: /admin');
    exit;
}

// Sub images belong to the news items being deleted, so they go too — the
// database rows first (found by news_id, so this must run before the news rows
// are gone), then every file on disk.
foreach ($ids as $newsId) {
    newsGalleryRemoveAll($pdo, (int) $newsId);
}

$del = $pdo->prepare("DELETE FROM news WHERE id IN ($placeholders)");
$del->execute($ids);
$deleted = $del->rowCount();

foreach ($rows as $row) {
    newsDeleteImageFile($row['image'] ?? '');
}

flash('success', $deleted . ' news item' . ($deleted === 1 ? '' : 's') . ' deleted.');
header('Location: /admin');
exit;
