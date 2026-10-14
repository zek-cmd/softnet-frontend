<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/gallery.php';
require_once __DIR__ . '/../includes/news-schema.php';
requireLogin();

/**
 * Sub images of one news item.
 *
 * Opened from the news form ("Manage sub images"). Everything here is a POST
 * that changes something and then redirects back to this same page, so
 * refreshing the browser can never repeat an action by accident.
 */

$newsId = sanitizeInt($_GET['id'] ?? 0);
if (!$newsId) {
    header('Location: /admin');
    exit;
}

$stmt = $pdo->prepare('SELECT id, title, image FROM news WHERE id = ?');
$stmt->execute([$newsId]);
$news = $stmt->fetch();

if (!$news) {
    flash('error', 'That news item no longer exists.');
    header('Location: /admin');
    exit;
}

$galleryReady = newsGalleryReady($pdo);
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = sanitizeString($_POST['action'] ?? '', 20);

    if (!$galleryReady) {
        $error = 'The gallery needs its database table first. Run migrations/add-gallery-and-slugs.sql in phpMyAdmin.';
    } elseif ($action === 'upload') {
        $files = newsCollectUploadedFiles('images');

        if (!$files) {
            $error = 'Choose at least one image first.';
        } else {
            $added = 0;
            $problems = [];

            foreach ($files as $file) {
                // Stop at the limit rather than uploading files that would only
                // be rejected afterwards.
                if (count(newsGalleryItems($pdo, $newsId)) >= NEWS_GALLERY_LIMIT) {
                    $problems[] = 'Only ' . NEWS_GALLERY_LIMIT . ' sub images are allowed. The rest were skipped.';
                    break;
                }

                [$stored, $storageError] = newsStoreImage($file);

                if ($storageError !== null) {
                    $problems[] = $storageError;
                    continue;
                }

                if ($stored !== null && newsGalleryAdd($pdo, $newsId, $stored, '')) {
                    $added++;
                }
            }

            if ($added > 0) {
                $notice = $added . ($added === 1 ? ' image added.' : ' images added.');
            }
            if ($problems) {
                // Show the first real problem; repeating the same message five
                // times for five bad files helps nobody.
                $error = $problems[0];
            }
        }
    } elseif ($action === 'caption') {
        $imageId = sanitizeInt($_POST['image_id'] ?? 0);
        $caption = newsCaption((string) ($_POST['caption'] ?? ''));

        if (newsGalleryUpdateCaption($pdo, $imageId, $newsId, $caption)) {
            $notice = 'Caption saved.';
        } else {
            $error = 'That image could not be found.';
        }
    } elseif ($action === 'move') {
        $imageId = sanitizeInt($_POST['image_id'] ?? 0);
        $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';

        if (!newsGalleryMove($pdo, $imageId, $newsId, $direction)) {
            $error = 'That image is already at the ' . ($direction === 'up' ? 'start' : 'end') . '.';
        }
    } elseif ($action === 'delete') {
        $imageId = sanitizeInt($_POST['image_id'] ?? 0);

        if (newsGalleryRemove($pdo, $imageId, $newsId)) {
            $notice = 'Image removed.';
        } else {
            $error = 'That image could not be found.';
        }
    }

    // Every action ends with a redirect, so the browser's Back and Refresh
    // buttons cannot repeat it.
    if ($notice !== '') {
        flash('success', $notice);
    }
    if ($error !== '') {
        flash('error', $error);
    }

    header('Location: /admin/gallery/' . $newsId);
    exit;
}

$images = newsGalleryItems($pdo, $newsId);
$remaining = max(0, NEWS_GALLERY_LIMIT - count($images));
$pageTitle = 'Sub images';
$activeNav = 'news';
include __DIR__ . '/../includes/admin-header.php';
?>

<main class="admin-main">
  <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($msg = flash('error')): ?>
    <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if (!$galleryReady): ?>
    <div class="admin-card">
      <h1 class="admin-card-title">Sub images</h1>
      <div class="alert alert-error" style="margin-top:16px;">
        <strong>The gallery needs its database table first.</strong><br>
        Run <code>migrations/add-gallery-and-slugs.sql</code> in phpMyAdmin, then
        reload this page. Nothing else is affected &mdash; the news item itself
        keeps working, and its cover photo is untouched.
      </div>
      <a href="/admin/edit/<?= $newsId ?>" class="btn btn-ghost">Back to the news item</a>
    </div>
  <?php else: ?>

  <div class="admin-card">
    <div class="admin-card-header">
      <div>
        <h1 class="admin-card-title">Sub images</h1>
        <p class="form-hint" style="margin-top:2px;">
          For: <strong><?= htmlspecialchars($news['title']) ?></strong>
        </p>
      </div>
      <div class="card-header-actions">
        <a href="/admin/edit/<?= $newsId ?>" class="btn btn-ghost">&larr; Back to the news item</a>
      </div>
    </div>

    <p class="form-hint" style="margin-bottom:18px;">
      These are the extra photos shown under the cover photo, as a grid.
      <?= NEWS_GALLERY_LIMIT ?> allowed per news item &middot;
      <?= $remaining ?> still available.
      JPG, PNG, WebP or GIF, <?= newsFormatBytes(NEWS_IMAGE_MAX_BYTES) ?> each.
    </p>

    <?php if (!$images): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
        <p>No sub images yet. Add the first ones below.</p>
      </div>
    <?php else: ?>
      <div class="gallery-manage">
        <?php foreach ($images as $index => $image): ?>
          <?php $src = newsGalleryImageSrc((string) $image['image']); ?>
          <div class="gallery-manage-item">
            <div class="gallery-manage-thumb">
              <?php if ($src !== ''): ?>
                <img src="/<?= htmlspecialchars($src) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="cell-meta">Missing file</span>
              <?php endif; ?>
              <span class="gallery-manage-order"><?= $index + 1 ?></span>
            </div>

            <form method="POST" action="" class="gallery-manage-form">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="caption">
              <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
              <label class="visually-hidden" for="caption-<?= (int) $image['id'] ?>">Caption for image <?= $index + 1 ?></label>
              <input
                class="form-input form-input-sm"
                type="text"
                id="caption-<?= (int) $image['id'] ?>"
                name="caption"
                value="<?= htmlspecialchars($image['caption'] ?? '') ?>"
                placeholder="Caption (optional)"
                maxlength="255">
              <button type="submit" class="btn btn-ghost btn-sm">Save</button>
            </form>

            <div class="gallery-manage-actions">
              <form method="POST" action="" class="inline-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                <input type="hidden" name="direction" value="up">
                <button type="submit" class="btn btn-ghost btn-sm" <?= $index === 0 ? 'disabled' : '' ?> aria-label="Move image <?= $index + 1 ?> earlier">&uarr;</button>
              </form>
              <form method="POST" action="" class="inline-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                <input type="hidden" name="direction" value="down">
                <button type="submit" class="btn btn-ghost btn-sm" <?= $index === count($images) - 1 ? 'disabled' : '' ?> aria-label="Move image <?= $index + 1 ?> later">&darr;</button>
              </form>
              <form method="POST" action="" class="inline-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Remove this sub image? The file is deleted too. This cannot be undone.');">
                  Remove
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($remaining > 0): ?>
    <div class="admin-card" style="margin-top:20px;">
      <h2 class="admin-card-title">Add sub images</h2>
      <form method="POST" action="" enctype="multipart/form-data" class="gallery-upload-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
          <label class="form-label" for="galleryImages">Choose images</label>
          <input
            class="form-input"
            type="file"
            id="galleryImages"
            name="images[]"
            accept="image/jpeg,image/png,image/webp,image/gif"
            multiple
            required>
          <p class="form-hint">
            Hold Ctrl (or Cmd on a Mac) to pick several at once.
            You can add up to <?= $remaining ?> more here.
          </p>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Upload images</button>
          <a href="/admin/edit/<?= $newsId ?>" class="btn btn-ghost">Cancel</a>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div class="admin-card" style="margin-top:20px;">
      <p class="form-hint" style="margin:0;">
        All <?= NEWS_GALLERY_LIMIT ?> sub image places are used. Remove one to add another.
      </p>
    </div>
  <?php endif; ?>

  <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
