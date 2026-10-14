<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/html-clean.php';
require_once __DIR__ . '/../includes/tags.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/slug.php';
require_once __DIR__ . '/../includes/gallery.php';
require_once __DIR__ . '/../includes/news-schema.php';
requireLogin();

$pageTitle = 'Add News';
$item = null;
$error = '';

$id = sanitizeInt($_GET['id'] ?? 0);
if ($id) {
    // "news.*" would fail on a database that has not been migrated yet, so the
    // columns are named outright. slug is added on top only when it exists.
    $columns = 'id, title, summary, content, category, tags, image, created_at';
    if (newsSlugReady($pdo)) {
        $columns .= ', slug';
    }

    $stmt = $pdo->prepare("SELECT $columns FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        header('Location: /admin');
        exit;
    }
    $pageTitle = 'Edit News';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title = sanitizeString($_POST['title'] ?? '', 200);
    $summary = sanitizeString($_POST['summary'] ?? '', 500);
    $content = trim($_POST['content'] ?? '');
    $content = mb_substr($content, 0, 50000);
    $category = sanitizeString($_POST['category'] ?? 'General', 50);
    // Tags are free text. normalizeNewsTags() splits, cleans and de-duplicates.
    $tags = normalizeNewsTags($_POST['tags'] ?? '');

    if ($title === '' || $content === '') {
        $error = 'Title and content are required.';
    } else {
        $image = $item['image'] ?? '';

        // The cover photo. One file, up to 2MB. Every check — really an image,
        // allowed type, size, safe name — lives in newsStoreImage(), which the
        // sub images use too.
        if (isset($_FILES['image'])) {
            [$stored, $uploadError] = newsStoreImage($_FILES['image']);

            if ($uploadError !== null) {
                $error = $uploadError;
            } elseif ($stored !== null) {
                // The old cover photo is only removed once the new one is
                // safely saved, so a failed upload never loses the old image.
                newsDeleteImageFile($image);
                $image = $stored;
            }
        }

        if ($error === '') {
            if ($item) {
                $stmt = $pdo->prepare('UPDATE news SET title=?, summary=?, content=?, category=?, tags=?, image=? WHERE id=?');
                $stmt->execute([$title, $summary, $content, $category, $tags, $image, $item['id']]);
                $savedId = (int) $item['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO news (title, summary, content, category, tags, image) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $summary, $content, $category, $tags, $image]);
                $savedId = (int) $pdo->lastInsertId();
            }

            // The readable address. An item that already has one keeps it: its
            // address may already be shared, and changing it would break every
            // link. New items get theirs from the title.
            if (newsSlugReady($pdo) && $savedId > 0) {
                $haveSlug = trim((string) ($item['slug'] ?? ''));
                if ($haveSlug === '') {
                    $slug = newsUniqueSlug($pdo, $title, $savedId);
                    $slugStmt = $pdo->prepare('UPDATE news SET slug = ? WHERE id = ?');
                    $slugStmt->execute([$slug, $savedId]);
                }
            }

            flash('success', $item ? 'News item updated successfully.' : 'News item published successfully.');

            // A brand new item is most likely to need its sub images next, so
            // go straight there instead of back to the list.
            header('Location: ' . ($item ? '/admin' : '/admin/gallery/' . $savedId));
            exit;
        }
    }
}

$activeNav = 'news';
include __DIR__ . '/../includes/admin-header.php';
?>

<main class="admin-main">
  <div class="admin-card">
    <div class="admin-card-header">
      <h1 class="admin-card-title"><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" id="newsForm">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label" for="title">Title *</label>
        <input class="form-input" type="text" id="title" name="title" required value="<?= htmlspecialchars($item['title'] ?? '') ?>" placeholder="News title" maxlength="200">
      </div>

      <div class="form-group">
        <label class="form-label" for="summary">Summary</label>
        <input class="form-input" type="text" id="summary" name="summary" value="<?= htmlspecialchars($item['summary'] ?? '') ?>" placeholder="One line shown on cards and in Facebook previews" maxlength="500">
        <p class="form-hint">This is the text readers see under the title on the news list and in a shared link's preview.</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="category">Category</label>
        <input class="form-input" type="text" id="category" name="category" value="<?= htmlspecialchars($item['category'] ?? 'General') ?>" placeholder="e.g. Announcement, Event, Achievement" maxlength="50">
      </div>

      <div class="form-group">
        <label class="form-label" for="tags">Tags</label>
        <input class="form-input" type="text" id="tags" name="tags" value="<?= htmlspecialchars($item['tags'] ?? '') ?>" placeholder="enrollment, sports, TESDA" maxlength="255">
        <p class="form-hint">
          Separate tags with commas. Up to <?= NEWS_TAG_LIMIT ?> tags.
          Readers can tap a tag to see every news item with it.
        </p>
      </div>

      <div class="form-group">
        <label class="form-label">Content *</label>
        <div id="quill-editor" data-content="<?= htmlspecialchars($item['content'] ?? '', ENT_QUOTES) ?>"></div>
        <input type="hidden" id="content" name="content" required>
        <p class="form-hint">Use the toolbar to format: bold, italic, headings, lists, links, and more.</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="image">Cover photo</label>
        <input class="form-input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
        <p class="form-hint">JPG, PNG, WebP or GIF. Optional. Max 2MB. This is the one photo shown on the news list, on the home page, and in the Facebook preview. Extra photos go in <strong>Sub images</strong> below.</p>
        <?php if (!empty($item['image'])): ?>
          <img src="/<?= htmlspecialchars(cleanNewsImagePath($item['image'])) ?>" alt="Current cover photo" class="img-preview">
        <?php endif; ?>
      </div>

      <?php if ($item): ?>
        <?php
        // The cover photo is saved already, so this item can have sub images.
        // A NEW item has no id yet — the sub images button appears after the
        // first save, and saving a new item lands on that page automatically.
        $subImageCount = newsGalleryReady($pdo) ? count(newsGalleryItems($pdo, (int) $item['id'])) : 0;
        ?>
        <div class="form-group">
          <label class="form-label">Sub images</label>
          <div class="subimage-panel">
            <div>
              <p class="subimage-panel-title">
                <?php if (!newsGalleryReady($pdo)): ?>
                  Not set up yet
                <?php elseif ($subImageCount === 0): ?>
                  No sub images yet
                <?php else: ?>
                  <?= $subImageCount ?> <?= $subImageCount === 1 ? 'sub image' : 'sub images' ?>
                <?php endif; ?>
              </p>
              <p class="form-hint" style="margin-top:2px;">
                The extra photos shown as a grid under the story, on the news page.
              </p>
            </div>
            <?php if (newsGalleryReady($pdo)): ?>
              <a href="/admin/gallery/<?= (int) $item['id'] ?>" class="btn btn-ghost btn-sm">Manage sub images</a>
            <?php endif; ?>
          </div>
          <?php if (!newsGalleryReady($pdo)): ?>
            <p class="form-hint">
              To switch sub images on, run <code>migrations/add-gallery-and-slugs.sql</code>
              in phpMyAdmin. Until then the news item works as it always has, with just its cover photo.
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($item && newsSlugReady($pdo) && !empty($item['slug'])): ?>
        <?php // Shown for reference, not editable on purpose: changing a live
              // address would break links already shared on Facebook. ?>
        <div class="form-group">
          <label class="form-label">Web address</label>
          <p class="slug-display">/news/<?= htmlspecialchars($item['slug']) ?></p>
          <p class="form-hint">
            This is the readable link for this news item. It is set once from the title and
            then stays put, so links already shared keep working. The old
            <code>/news/<?= (int) $item['id'] ?></code> address still works too and redirects here.
          </p>
        </div>
      <?php endif; ?>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $item ? 'Update News' : 'Publish News' ?></button>
        <a href="/admin" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</main>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="<?= asset('assets/js/editor.js') ?>"></script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
