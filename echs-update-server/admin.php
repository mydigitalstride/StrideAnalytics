<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

session_start();

$db = new ECHS_DB(ECHS_DB_PATH);

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$login_error = '';

if (!isset($_SESSION['echs_admin'])) {
    if (($_POST['password'] ?? '') !== '') {
        if (password_verify($_POST['password'], ECHS_ADMIN_PASSWORD_HASH)) {
            $_SESSION['echs_admin'] = true;
        } else {
            $login_error = 'Invalid password.';
        }
    }
}

if (!isset($_SESSION['echs_admin'])) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ECHoS Update Server — Login</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #1a1d23; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .login-box { background: #fff; border-radius: 8px; padding: 40px; width: 360px; box-shadow: 0 4px 24px rgba(0,0,0,.4); }
  .login-box h1 { font-size: 1.25rem; margin-bottom: 8px; color: #111; }
  .login-box p { font-size: .85rem; color: #666; margin-bottom: 24px; }
  label { display: block; font-size: .85rem; font-weight: 600; color: #333; margin-bottom: 6px; }
  input[type=password] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 5px; font-size: .95rem; outline: none; }
  input[type=password]:focus { border-color: #4f6ef7; box-shadow: 0 0 0 3px rgba(79,110,247,.15); }
  .btn { display: block; width: 100%; margin-top: 18px; padding: 11px; background: #4f6ef7; color: #fff; border: none; border-radius: 5px; font-size: .95rem; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #3b58e0; }
  .error { margin-top: 14px; padding: 10px 12px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 5px; color: #b91c1c; font-size: .85rem; }
</style>
</head>
<body>
<div class="login-box">
  <h1>ECHoS Update Server</h1>
  <p>Admin access only. Enter your password to continue.</p>
  <form method="post">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autofocus>
    <button class="btn" type="submit">Sign in</button>
    <?php if ($login_error !== '') : ?>
      <div class="error"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
  </form>
</div>
</body>
</html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['echs_admin'])) {
    $post_action = $_POST['post_action'] ?? '';

    if ($post_action === 'create_license') {
        $client_name  = trim($_POST['client_name']  ?? '');
        $client_email = trim($_POST['client_email'] ?? '');
        $max_sites    = max(1, (int) ($_POST['max_sites'] ?? 1));
        $expires_at   = trim($_POST['expires_at']   ?? '') ?: null;
        $notes        = trim($_POST['notes']        ?? '');
        $db->create_license($client_name, $client_email, $max_sites, $expires_at, $notes);
    }

    if ($post_action === 'revoke_license') {
        $db->revoke_license((int) ($_POST['id'] ?? 0));
    }

    if ($post_action === 'restore_license') {
        $db->restore_license((int) ($_POST['id'] ?? 0));
    }

    if ($post_action === 'deactivate_site') {
        $license_id = (int) ($_POST['license_id'] ?? 0);
        $site_url   = trim($_POST['site_url'] ?? '');
        $db->remove_activation($license_id, $site_url);
    }

    $redirect_page = $_POST['redirect_page'] ?? 'licenses';
    $redirect_id   = isset($_POST['redirect_id']) ? '&id=' . (int) $_POST['redirect_id'] : '';
    header('Location: admin.php?page=' . urlencode($redirect_page) . $redirect_id);
    exit;
}

$page = $_GET['page'] ?? 'licenses';

$total_licenses   = $db->count_licenses();
$all_activations  = $db->all_activations();
$active_sites     = count($all_activations);

$stmt_today = (new PDO('sqlite:' . ECHS_DB_PATH));
$stmt_today->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$requests_today = (int) $stmt_today->query("SELECT COUNT(*) FROM request_log WHERE ts >= date('now')")->fetchColumn();

function echs_badge(string $status): string {
    $map = ['active' => 'badge-green', 'revoked' => 'badge-red', 'expired' => 'badge-orange'];
    $cls = $map[$status] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

function echs_form_action(string $post_action, array $hidden = []): string {
    $fields = '<input type="hidden" name="post_action" value="' . htmlspecialchars($post_action) . '">';
    foreach ($hidden as $k => $v) {
        $fields .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string) $v) . '">';
    }
    return $fields;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ECHoS Update Server — Admin</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; display: flex; min-height: 100vh; color: #111; }

  .sidebar { width: 220px; flex-shrink: 0; background: #1a1d23; color: #c9cdd6; display: flex; flex-direction: column; }
  .sidebar-brand { padding: 24px 20px 18px; font-size: 1rem; font-weight: 700; color: #fff; border-bottom: 1px solid #2d3140; letter-spacing: .02em; }
  .sidebar-brand span { display: block; font-size: .72rem; font-weight: 400; color: #6b7280; margin-top: 2px; }
  .sidebar nav { flex: 1; padding: 12px 0; }
  .sidebar nav a { display: block; padding: 10px 20px; font-size: .88rem; color: #9ca3af; text-decoration: none; border-left: 3px solid transparent; }
  .sidebar nav a:hover, .sidebar nav a.active { color: #fff; background: #22263a; border-left-color: #4f6ef7; }
  .sidebar-footer { padding: 16px 20px; border-top: 1px solid #2d3140; }
  .sidebar-footer a { font-size: .82rem; color: #6b7280; text-decoration: none; }
  .sidebar-footer a:hover { color: #ef4444; }

  .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

  .stats-bar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 14px 28px; display: flex; gap: 40px; }
  .stat { text-align: center; }
  .stat-val { font-size: 1.5rem; font-weight: 700; color: #111; }
  .stat-lbl { font-size: .75rem; color: #6b7280; margin-top: 2px; text-transform: uppercase; letter-spacing: .05em; }

  .content { padding: 28px; overflow-y: auto; flex: 1; }
  h2 { font-size: 1.2rem; margin-bottom: 20px; color: #111; }
  h3 { font-size: 1rem; margin-bottom: 14px; color: #374151; }

  .card { background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; padding: 22px 24px; margin-bottom: 24px; }

  table { width: 100%; border-collapse: collapse; font-size: .88rem; }
  th { text-align: left; padding: 10px 12px; background: #f9fafb; border-bottom: 2px solid #e5e7eb; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
  td { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:nth-child(even) td { background: #fafafa; }

  .badge { display: inline-block; padding: 2px 9px; border-radius: 99px; font-size: .75rem; font-weight: 600; }
  .badge-green  { background: #dcfce7; color: #166534; }
  .badge-red    { background: #fee2e2; color: #991b1b; }
  .badge-orange { background: #ffedd5; color: #9a3412; }
  .badge-gray   { background: #f3f4f6; color: #374151; }

  .btn-sm { display: inline-block; padding: 5px 12px; border-radius: 5px; font-size: .8rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; }
  .btn-danger  { background: #fee2e2; color: #b91c1c; }
  .btn-danger:hover  { background: #fca5a5; }
  .btn-success { background: #dcfce7; color: #166534; }
  .btn-success:hover { background: #86efac; }
  .btn-primary { background: #4f6ef7; color: #fff; }
  .btn-primary:hover { background: #3b58e0; }
  .btn-link { background: none; color: #4f6ef7; padding: 5px 0; }
  .btn-link:hover { text-decoration: underline; }

  form.inline { display: inline; }

  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .form-grid .full { grid-column: 1 / -1; }
  .form-group label { display: block; font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
  .form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 5px; font-size: .9rem; outline: none; font-family: inherit;
  }
  .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: #4f6ef7; box-shadow: 0 0 0 3px rgba(79,110,247,.15);
  }
  .form-actions { margin-top: 18px; }

  .meta { font-size: .8rem; color: #6b7280; }
  .mono { font-family: 'Courier New', monospace; font-size: .85rem; }
  a.plain { color: #4f6ef7; text-decoration: none; }
  a.plain:hover { text-decoration: underline; }
  .back-link { margin-bottom: 18px; display: inline-block; font-size: .85rem; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    ECHoS Updates
    <span>License Management</span>
  </div>
  <nav>
    <a href="admin.php?page=licenses"<?= $page === 'licenses' || $page === 'license_detail' ? ' class="active"' : '' ?>>Licenses</a>
    <a href="admin.php?page=activity"<?= $page === 'activity' ? ' class="active"' : '' ?>>Request Log</a>
  </nav>
  <div class="sidebar-footer">
    <a href="admin.php?action=logout">Sign out</a>
  </div>
</aside>

<div class="main">
  <div class="stats-bar">
    <div class="stat">
      <div class="stat-val"><?= $total_licenses ?></div>
      <div class="stat-lbl">Total Licenses</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $active_sites ?></div>
      <div class="stat-lbl">Active Sites</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $requests_today ?></div>
      <div class="stat-lbl">Requests Today</div>
    </div>
  </div>

  <div class="content">

<?php if ($page === 'licenses') : ?>

  <h2>Licenses</h2>

  <div class="card">
    <h3>Issue New License</h3>
    <form method="post">
      <?= echs_form_action('create_license', ['redirect_page' => 'licenses']) ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Client Name</label>
          <input type="text" name="client_name" required>
        </div>
        <div class="form-group">
          <label>Client Email</label>
          <input type="email" name="client_email">
        </div>
        <div class="form-group">
          <label>Max Sites</label>
          <input type="number" name="max_sites" value="1" min="1">
        </div>
        <div class="form-group">
          <label>Expires At <span class="meta">(leave blank = never)</span></label>
          <input type="date" name="expires_at">
        </div>
        <div class="form-group full">
          <label>Notes</label>
          <textarea name="notes" rows="2"></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-sm btn-primary" type="submit">Issue License</button>
      </div>
    </form>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>Key</th>
          <th>Client</th>
          <th>Email</th>
          <th>Sites</th>
          <th>Expires</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($db->list_licenses() as $lic) : ?>
          <?php $used = $db->count_activations((int) $lic['id']); ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($lic['license_key']) ?></td>
            <td><a class="plain" href="admin.php?page=license_detail&id=<?= (int) $lic['id'] ?>"><?= htmlspecialchars($lic['client_name']) ?></a></td>
            <td><?= htmlspecialchars($lic['client_email']) ?></td>
            <td><?= $used ?>/<?= (int) $lic['max_sites'] ?></td>
            <td><?= $lic['expires_at'] ? htmlspecialchars($lic['expires_at']) : '<span class="meta">Never</span>' ?></td>
            <td><?= echs_badge($lic['status']) ?></td>
            <td>
              <a class="btn-sm btn-link plain" href="admin.php?page=license_detail&id=<?= (int) $lic['id'] ?>">Detail</a>
              <?php if ($lic['status'] === 'active') : ?>
                <form class="inline" method="post">
                  <?= echs_form_action('revoke_license', ['id' => $lic['id'], 'redirect_page' => 'licenses']) ?>
                  <button class="btn-sm btn-danger" type="submit" onclick="return confirm('Revoke this license?')">Revoke</button>
                </form>
              <?php else : ?>
                <form class="inline" method="post">
                  <?= echs_form_action('restore_license', ['id' => $lic['id'], 'redirect_page' => 'licenses']) ?>
                  <button class="btn-sm btn-success" type="submit">Restore</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($page === 'license_detail') : ?>

  <?php
    $detail_id  = (int) ($_GET['id'] ?? 0);
    $lic        = null;
    foreach ($db->list_licenses() as $l) {
        if ((int) $l['id'] === $detail_id) { $lic = $l; break; }
    }
  ?>

  <a class="btn-sm btn-link back-link plain" href="admin.php?page=licenses">&larr; Back to Licenses</a>

  <?php if ($lic === null) : ?>
    <p>License not found.</p>
  <?php else : ?>

    <h2>License: <span class="mono"><?= htmlspecialchars($lic['license_key']) ?></span></h2>

    <div class="card">
      <table>
        <tr><th>Client</th><td><?= htmlspecialchars($lic['client_name']) ?></td></tr>
        <tr><th>Email</th><td><?= htmlspecialchars($lic['client_email']) ?></td></tr>
        <tr><th>Max Sites</th><td><?= (int) $lic['max_sites'] ?></td></tr>
        <tr><th>Expires</th><td><?= $lic['expires_at'] ? htmlspecialchars($lic['expires_at']) : 'Never' ?></td></tr>
        <tr><th>Status</th><td><?= echs_badge($lic['status']) ?></td></tr>
        <tr><th>Created</th><td><?= htmlspecialchars($lic['created_at']) ?></td></tr>
        <tr><th>Notes</th><td><?= htmlspecialchars($lic['notes']) ?></td></tr>
      </table>
      <div class="form-actions">
        <?php if ($lic['status'] === 'active') : ?>
          <form class="inline" method="post">
            <?= echs_form_action('revoke_license', ['id' => $lic['id'], 'redirect_page' => 'license_detail', 'redirect_id' => $lic['id']]) ?>
            <button class="btn-sm btn-danger" type="submit" onclick="return confirm('Revoke this license?')">Revoke License</button>
          </form>
        <?php else : ?>
          <form class="inline" method="post">
            <?= echs_form_action('restore_license', ['id' => $lic['id'], 'redirect_page' => 'license_detail', 'redirect_id' => $lic['id']]) ?>
            <button class="btn-sm btn-success" type="submit">Restore License</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <h3>Active Sites</h3>
    <div class="card">
      <?php $activations = $db->list_activations($detail_id); ?>
      <?php if (empty($activations)) : ?>
        <p class="meta">No active sites for this license.</p>
      <?php else : ?>
        <table>
          <thead>
            <tr>
              <th>Site URL</th>
              <th>Plugin Version</th>
              <th>WP Version</th>
              <th>Activated</th>
              <th>Last Seen</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activations as $act) : ?>
              <tr>
                <td><?= htmlspecialchars($act['site_url']) ?></td>
                <td><?= htmlspecialchars($act['echs_version']) ?></td>
                <td><?= htmlspecialchars($act['wp_version']) ?></td>
                <td><?= htmlspecialchars($act['activated_at']) ?></td>
                <td><?= htmlspecialchars($act['last_seen_at']) ?></td>
                <td>
                  <form class="inline" method="post">
                    <?= echs_form_action('deactivate_site', ['license_id' => $detail_id, 'site_url' => $act['site_url'], 'redirect_page' => 'license_detail', 'redirect_id' => $detail_id]) ?>
                    <button class="btn-sm btn-danger" type="submit" onclick="return confirm('Remove this site activation?')">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <?php endif; ?>

<?php elseif ($page === 'activity') : ?>

  <h2>Request Log <span class="meta">(last 100)</span></h2>

  <div class="card">
    <?php
      $log_db   = new PDO('sqlite:' . ECHS_DB_PATH);
      $log_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $log_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      $log_rows = $log_db->query('SELECT * FROM request_log ORDER BY ts DESC LIMIT 100')->fetchAll();
    ?>
    <?php if (empty($log_rows)) : ?>
      <p class="meta">No requests logged yet.</p>
    <?php else : ?>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>IP</th>
            <th>Action</th>
            <th>License Key</th>
            <th>Site</th>
            <th>Result</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log_rows as $row) : ?>
            <tr>
              <td class="meta"><?= htmlspecialchars($row['ts']) ?></td>
              <td class="mono"><?= htmlspecialchars($row['ip']) ?></td>
              <td><?= htmlspecialchars($row['action']) ?></td>
              <td class="mono"><?= htmlspecialchars($row['license_key']) ?></td>
              <td><?= htmlspecialchars($row['site_url']) ?></td>
              <td><?= htmlspecialchars($row['result']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php endif; ?>

  </div>
</div>

</body>
</html>
