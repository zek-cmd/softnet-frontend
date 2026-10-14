<?php
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/assets.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

$activeNav = isset($activeNav) ? $activeNav : 'news';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'SOFTNET Admin') ?></title>
  <link rel="icon" type="image/webp" href="/_sitc/images/favicon.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/site.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body>

<nav class="admin-nav">
  <a href="/admin" class="admin-nav-brand">
    <img src="/_sitc/images/favicon.webp" alt="" onerror="this.style.display='none'">
    <span>SOFTNET Admin</span>
  </a>
</nav>

<div class="admin-nav-links" id="adminNavLinks">
  <a href="/admin"<?= $activeNav === 'news' ? ' class="active"' : '' ?>>News</a>
  <a href="/admin/settings"<?= $activeNav === 'settings' ? ' class="active"' : '' ?>>Settings</a>
  <a href="/admin/logout">Log Out</a>
</div>
<button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false" aria-controls="adminNavLinks">&#9776;</button>
<div class="nav-overlay" id="navOverlay"></div>

<script src="<?= asset('assets/js/admin-nav.js') ?>"></script>
