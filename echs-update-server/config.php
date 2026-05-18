<?php
define('ECHS_DB_PATH',         __DIR__ . '/data/licenses.db');
define('ECHS_ZIP_DIR',         __DIR__ . '/zips/');
define('ECHS_API_BASE',        'https://mydigitalstride.com/echos-updates/api.php');

define('ECHS_LATEST_VERSION',  '2.4.0');
define('ECHS_ZIP_FILENAME',    'echs-2.4.0.zip');
define('ECHS_TESTED_WP',       '6.7');
define('ECHS_LAST_UPDATED',    '2026-05-18');
define('ECHS_DESCRIPTION',     '<p>Engineering, Construction, Home Services SEO Analytics. All-in-one SEO, structured data, redirect manager, 404 monitoring, Google Business Profile integration, and more.</p>');
define('ECHS_CHANGELOG',       '<h4>2.4.0</h4><ul><li>Google Business Profile OAuth integration</li><li>Yoast SEO migration tool</li><li>SEO Tasks checklist</li><li>License-gated remote updates</li></ul>');

define('ECHS_ADMIN_PASSWORD_HASH', password_hash('change-me-before-deploy', PASSWORD_DEFAULT));

define('ECHS_RATE_LIMIT', 30);
