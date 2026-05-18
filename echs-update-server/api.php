<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function echs_json(mixed $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$action     = trim($_GET['action']     ?? '');
$license    = trim($_GET['license']    ?? '');
$site       = trim($_GET['site']       ?? '');
$version    = trim($_GET['version']    ?? '');
$wp_version = trim($_GET['wp_version'] ?? '');

$db = new ECHS_DB(ECHS_DB_PATH);
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!$db->check_rate_limit($ip, ECHS_RATE_LIMIT)) {
    $db->log_request($ip, $action, $license, $site, 'rate_limited');
    echs_json(['error' => 'rate_limit'], 429);
}

if (empty($site) || !filter_var($site, FILTER_VALIDATE_URL)) {
    $db->log_request($ip, $action, $license, $site, 'invalid_site');
    echs_json(['error' => 'invalid_site']);
}

if ($action === 'activate') {
    $row = $db->get_license($license);
    if (!$row) {
        $db->log_request($ip, $action, $license, $site, 'not_found');
        echs_json(['activated' => false, 'error' => 'License key not found.']);
    }

    if ($row['status'] !== 'active') {
        $db->log_request($ip, $action, $license, $site, 'not_active');
        echs_json(['activated' => false, 'error' => 'License is ' . $row['status'] . '.']);
    }

    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        $db->log_request($ip, $action, $license, $site, 'expired');
        echs_json(['activated' => false, 'error' => 'License has expired.']);
    }

    $existing = $db->get_activation((int) $row['id'], $site);
    if ($existing) {
        $db->update_last_seen((int) $row['id'], $site, $version, $wp_version);
        $db->log_request($ip, $action, $license, $site, 'already_active');
        echs_json([
            'activated'   => true,
            'license_key' => $row['license_key'],
            'client_name' => $row['client_name'],
            'expires_at'  => $row['expires_at'],
            'max_sites'   => (int) $row['max_sites'],
        ]);
    }

    $count = $db->count_activations((int) $row['id']);
    if ($count >= (int) $row['max_sites']) {
        $db->log_request($ip, $action, $license, $site, 'limit_reached');
        echs_json(['activated' => false, 'error' => 'Activation limit reached (' . $count . '/' . $row['max_sites'] . ' sites).']);
    }

    $db->add_activation((int) $row['id'], $site, $version, $wp_version);
    $db->log_request($ip, $action, $license, $site, 'activated');
    echs_json([
        'activated'   => true,
        'license_key' => $row['license_key'],
        'client_name' => $row['client_name'],
        'expires_at'  => $row['expires_at'],
        'max_sites'   => (int) $row['max_sites'],
    ]);
}

if ($action === 'deactivate') {
    $row = $db->get_license($license);
    if ($row) {
        $db->remove_activation((int) $row['id'], $site);
        $db->log_request($ip, $action, $license, $site, 'deactivated');
    } else {
        $db->log_request($ip, $action, $license, $site, 'not_found');
    }
    echs_json(['deactivated' => true]);
}

if ($action === 'check') {
    $row = $db->get_license($license);
    if (!$row || $row['status'] !== 'active') {
        $db->log_request($ip, $action, $license, $site, 'invalid');
        echs_json(['valid' => false]);
    }

    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        $db->log_request($ip, $action, $license, $site, 'expired');
        echs_json(['valid' => false]);
    }

    $activation = $db->get_activation((int) $row['id'], $site);
    if (!$activation) {
        $db->log_request($ip, $action, $license, $site, 'not_activated');
        echs_json(['valid' => false]);
    }

    $sites_used = $db->count_activations((int) $row['id']);
    $db->log_request($ip, $action, $license, $site, 'valid');
    echs_json([
        'valid'       => true,
        'client_name' => $row['client_name'],
        'expires_at'  => $row['expires_at'],
        'max_sites'   => (int) $row['max_sites'],
        'sites_used'  => $sites_used,
    ]);
}

if ($action === 'info') {
    $row = $db->get_license($license);
    if (!$row || $row['status'] !== 'active') {
        $db->log_request($ip, $action, $license, $site, 'invalid');
        echs_json(['error' => 'invalid_license', 'message' => 'License key is not valid or not activated for this site.']);
    }

    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        $db->log_request($ip, $action, $license, $site, 'expired');
        echs_json(['error' => 'invalid_license', 'message' => 'License key is not valid or not activated for this site.']);
    }

    $activation = $db->get_activation((int) $row['id'], $site);
    if (!$activation) {
        $db->log_request($ip, $action, $license, $site, 'not_activated');
        echs_json(['error' => 'invalid_license', 'message' => 'License key is not valid or not activated for this site.']);
    }

    $db->update_last_seen((int) $row['id'], $site, $version, $wp_version);
    $db->log_request($ip, $action, $license, $site, 'ok');

    echs_json([
        'name'         => 'ECHoS SEO Analytics',
        'slug'         => 'echs',
        'version'      => ECHS_LATEST_VERSION,
        'download_url' => ECHS_API_BASE . '?action=download&license=' . urlencode($license) . '&site=' . urlencode($site),
        'url'          => 'https://mydigitalstride.com/echos-seo-analytics',
        'requires'     => '6.0',
        'requires_php' => '8.0',
        'tested'       => ECHS_TESTED_WP,
        'last_updated' => ECHS_LAST_UPDATED,
        'sections'     => [
            'description' => ECHS_DESCRIPTION,
            'changelog'   => ECHS_CHANGELOG,
        ],
    ]);
}

if ($action === 'download') {
    $row = $db->get_license($license);
    if (!$row || $row['status'] !== 'active') {
        $db->log_request($ip, $action, $license, $site, 'invalid');
        echs_json(['error' => 'invalid_license'], 403);
    }

    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        $db->log_request($ip, $action, $license, $site, 'expired');
        echs_json(['error' => 'invalid_license'], 403);
    }

    $activation = $db->get_activation((int) $row['id'], $site);
    if (!$activation) {
        $db->log_request($ip, $action, $license, $site, 'not_activated');
        echs_json(['error' => 'invalid_license'], 403);
    }

    $zip = ECHS_ZIP_DIR . ECHS_ZIP_FILENAME;
    if (!file_exists($zip)) {
        $db->log_request($ip, $action, $license, $site, 'zip_not_found');
        echs_json(['error' => 'zip_not_found'], 404);
    }

    $db->log_request($ip, $action, $license, $site, 'downloaded');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="echs.zip"');
    header('Content-Length: ' . filesize($zip));
    readfile($zip);
    exit;
}

$db->log_request($ip, $action, $license, $site, 'unknown_action');
echs_json(['error' => 'unknown_action']);
