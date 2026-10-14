<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';
requireLogin();

$pageTitle = 'Settings';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newUsername = sanitizeString($_POST['new_username'] ?? '', 100);
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
    $stmt->execute([$_SESSION['admin_username']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($newUsername === '' && $newPassword === '') {
        $error = 'Please enter a new username or password.';
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } else {
        if ($newUsername !== '' && $newUsername !== $user['username']) {
            $stmt2 = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ?');
            $stmt2->execute([$newUsername, $user['id']]);
            if ($stmt2->fetch()) {
                $error = 'That username is already taken.';
            }
        }

        if ($error === '') {
            $updates = [];
            $params = [];

            if ($newUsername !== '' && $newUsername !== $user['username']) {
                $updates[] = 'username = ?';
                $params[] = $newUsername;
            }
            if ($newPassword !== '') {
                $updates[] = 'password_hash = ?';
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            if (count($updates) > 0) {
                $sql = 'UPDATE admin_users SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $params[] = $user['id'];
                $pdo->prepare($sql)->execute($params);

                // Update session username
                if ($newUsername !== '' && $newUsername !== $user['username']) {
                    $_SESSION['admin_username'] = $newUsername;
                }

                $success = 'Settings updated successfully.';
            }
        }
    }
}

$activeNav = 'settings';
include __DIR__ . '/../includes/admin-header.php';
?>

<main class="admin-main">
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="admin-card" style="max-width:520px;margin:0 auto">
    <div class="admin-card-header">
      <h1 class="admin-card-title">Account Settings</h1>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label" for="new_username">Username</label>
        <input class="form-input" type="text" id="new_username" name="new_username" value="<?= htmlspecialchars($_SESSION['admin_username']) ?>" placeholder="Enter new username" maxlength="100">
        <p class="form-hint">Leave unchanged to keep your current username.</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="new_password">New Password</label>
        <input class="form-input" type="password" id="new_password" name="new_password" placeholder="Enter new password">
        <p class="form-hint">Leave blank to keep your current password.</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <input class="form-input" type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">
      </div>

      <div class="form-group" style="padding:14px 16px;background:#fef2f2;border-radius:10px;border:1px solid #fecaca;margin-bottom:20px">
        <label class="form-label" for="current_password" style="color:#991b1b">Current Password *</label>
        <input class="form-input" type="password" id="current_password" name="current_password" required style="border-color:#fca5a5" placeholder="Enter your current password to confirm changes">
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="/admin" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
