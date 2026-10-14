<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/tags.php';
require_once __DIR__ . '/../includes/news-query.php';
require_once __DIR__ . '/../includes/gallery.php';
requireLogin();

$pageTitle = 'Dashboard';

$perPage = 10;
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$search = sanitizeString($_GET['search'] ?? '', 100);
$isAjax = isset($_GET['ajax']);

$result = newsFetchPage($pdo, [
  'perPage' => $perPage,
  'page' => $page,
  'search' => $search,
]);

$items = $result['rows'];
$totalNews = $result['total'];
$totalPages = $result['pages'];
$page = $result['page'];

// How many sub images each item on this page has, in one query rather than one
// per row.
$galleryCounts = newsGalleryCounts($pdo, array_column($items, 'id'));

$renderRegion = function () use ($items, $search, $totalNews, $totalPages, $page, $galleryCounts) {
  ?>
  <div class="admin-card-header">
  <h1 class="admin-card-title">All News <span class="post-count"><?= $totalNews ?></span></h1>
  <div class="card-header-actions">
    <div class="search-wrap">
      <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" class="search-input" placeholder="Search news..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
    </div>
    <a href="/admin/edit" class="btn btn-primary">+ Add News</a>
  </div>
</div>

  <?php if (count($items) === 0): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <p><?= $search ? 'No news matches your search.' : 'No news yet. Post your first update!' ?></p>
    </div>
  <?php else: ?>
    <div id="bulkFormWrap">
      <form method="POST" action="/admin/bulk-delete" id="bulkForm">
        <?= csrfField() ?>
        <div id="hiddenIds"></div>

        <div class="bulk-bar" id="bulkBar">
          <span class="bulk-bar-count" id="bulkCount">0 selected</span>
          <div class="bulk-bar-actions">
            <button type="button" class="btn btn-ghost btn-sm" id="bulkClear">Clear</button>
            <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
          </div>
        </div>

        <table class="admin-table">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" id="selectAll" aria-label="Select all news on this page"></th>
              <th>Title</th>
              <th>Category</th>
              <th>Tags</th>
              <th>Photos</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td class="col-check" data-label="Select">
                  <input type="checkbox" class="row-check" name="ids[]" value="<?= (int)$item['id'] ?>" data-id="<?= (int)$item['id'] ?>" aria-label="Select news item">
                </td>
                <td data-label="Title">
                  <div class="cell-title"><?= htmlspecialchars($item['title']) ?></div>
                  <?php if ($item['summary']): ?>
                    <div class="cell-meta"><?= htmlspecialchars(mb_strimwidth($item['summary'], 0, 80, '...', 'UTF-8')) ?></div>
                  <?php endif; ?>
                </td>
                <td data-label="Category"><span class="badge badge-green"><?= htmlspecialchars($item['category'] ?? 'General') ?></span></td>
                <td data-label="Tags">
                  <?php $rowTags = newsTagList($item['tags'] ?? ''); ?>
                  <?php if ($rowTags): ?>
                    <div class="tag-row tag-row-table">
                      <?php foreach ($rowTags as $rowTag): ?>
                        <span class="tag-chip tag-chip-static">#<?= htmlspecialchars($rowTag) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span class="cell-meta">—</span>
                  <?php endif; ?>
                </td>
                <td data-label="Photos">
                  <?php $photoCount = (int) ($galleryCounts[(int) $item['id']] ?? 0); ?>
                  <?php if ($photoCount > 0): ?>
                    <a href="/admin/gallery/<?= (int) $item['id'] ?>" class="photo-marker">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                      <?= $photoCount ?>
                    </a>
                  <?php else: ?>
                    <span class="cell-meta">—</span>
                  <?php endif; ?>
                </td>
                <td data-label="Date" class="cell-meta"><?= date('M j, Y', strtotime($item['created_at'])) ?></td>
                <td data-label="Actions">
                  <div class="cell-actions">
                    <a href="/admin/edit/<?= (int)$item['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <a href="/admin/gallery/<?= (int)$item['id'] ?>" class="btn btn-ghost btn-sm">Photos</a>
                    <button type="submit"
                            class="btn btn-danger btn-sm"
                            form="singleDeleteForm"
                            name="id"
                            value="<?= (int)$item['id'] ?>"
                            onclick="return confirm('Delete this news item?');">Delete</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    </div>

    <form id="singleDeleteForm" method="POST" action="/admin/delete" style="display:none;">
      <?= csrfField() ?>
    </form>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <div class="pagination-links">
          <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(newsPageUrl($page - 1, $search, '', '/admin')) ?>" class="page-btn" data-page="<?= $page - 1 ?>">&larr; Prev</a>
          <?php endif; ?>
          <?php
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          if ($start > 1) {
            echo '<a href="' . htmlspecialchars(newsPageUrl(1, $search, '', '/admin')) . '" class="page-btn" data-page="1">1</a>';
            if ($start > 2) echo '<span class="page-ellipsis">&hellip;</span>';
          }
          for ($i = $start; $i <= $end; $i++):
          ?>
            <?php if ($i === $page): ?>
              <span class="page-btn active"><?= $i ?></span>
            <?php else: ?>
              <a href="<?= htmlspecialchars(newsPageUrl($i, $search, '', '/admin')) ?>" class="page-btn" data-page="<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php
          if ($end < $totalPages) {
            if ($end < $totalPages - 1) echo '<span class="page-ellipsis">&hellip;</span>';
            echo '<a href="' . htmlspecialchars(newsPageUrl($totalPages, $search, '', '/admin')) . '" class="page-btn" data-page="' . $totalPages . '">' . $totalPages . '</a>';
          }
          ?>
          <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(newsPageUrl($page + 1, $search, '', '/admin')) ?>" class="page-btn" data-page="<?= $page + 1 ?>">Next &rarr;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif;
};

if ($isAjax) {
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-store');
  $renderRegion();
  exit;
}

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

  <div class="admin-card" id="newsRegion">
    <?php $renderRegion(); ?>
  </div>
</main>

<script src="<?= asset('assets/js/dashboard.js') ?>"></script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
