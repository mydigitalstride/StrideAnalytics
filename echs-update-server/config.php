<?php
define('ECHS_DB_PATH',         __DIR__ . '/data/licenses.db');
define('ECHS_ZIP_DIR',         __DIR__ . '/zips/');
define('ECHS_API_BASE',        'https://mydigitalstride.com/echos-updates/api.php');
define('ECHS_RELEASE_FILE',    __DIR__ . '/release.json');

$echs_release = file_exists(ECHS_RELEASE_FILE)
    ? json_decode(file_get_contents(ECHS_RELEASE_FILE), true)
    : [];

define('ECHS_LATEST_VERSION',  $echs_release['version']      ?? '0.0.0');
define('ECHS_ZIP_FILENAME',    $echs_release['zip_filename']  ?? '');
define('ECHS_TESTED_WP',       $echs_release['tested_wp']     ?? '6.7');
define('ECHS_LAST_UPDATED',    $echs_release['last_updated']  ?? '');
define('ECHS_DESCRIPTION',     $echs_release['description']   ?? '');
define('ECHS_CHANGELOG',       $echs_release['changelog']     ?? '');

unset($echs_release);

define('ECHS_ADMIN_PASSWORD_HASH', password_hash('change-me-before-deploy', PASSWORD_DEFAULT));

define('ECHS_RATE_LIMIT', 30);
