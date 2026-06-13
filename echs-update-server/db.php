<?php
class ECHS_DB {
    private PDO $pdo;

    public function __construct(string $db_path) {
        $dir = dirname($db_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $db_path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');

        $this->init_schema();
    }

    private function init_schema(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS licenses (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                license_key  TEXT NOT NULL UNIQUE,
                client_name  TEXT NOT NULL DEFAULT '',
                client_email TEXT NOT NULL DEFAULT '',
                max_sites    INTEGER NOT NULL DEFAULT 1,
                status       TEXT NOT NULL DEFAULT 'active',
                expires_at   TEXT NULL,
                notes        TEXT NOT NULL DEFAULT '',
                created_at   TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS activations (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                license_id   INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
                site_url     TEXT NOT NULL,
                echs_version TEXT NOT NULL DEFAULT '',
                wp_version   TEXT NOT NULL DEFAULT '',
                activated_at TEXT NOT NULL DEFAULT (datetime('now')),
                last_seen_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE(license_id, site_url)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS request_log (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                ip          TEXT NOT NULL,
                action      TEXT NOT NULL,
                license_key TEXT NOT NULL DEFAULT '',
                site_url    TEXT NOT NULL DEFAULT '',
                result      TEXT NOT NULL DEFAULT '',
                ts          TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
    }

    public function get_license(string $key): array|false {
        $stmt = $this->pdo->prepare('SELECT * FROM licenses WHERE license_key = ?');
        $stmt->execute([$key]);
        return $stmt->fetch();
    }

    public function create_license(string $client_name, string $client_email, int $max_sites, string|null $expires_at, string $notes): string {
        $key = 'ECHS-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $this->pdo->prepare('INSERT INTO licenses (license_key, client_name, client_email, max_sites, expires_at, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$key, $client_name, $client_email, $max_sites, $expires_at, $notes]);
        return $key;
    }

    public function revoke_license(int $id): void {
        $stmt = $this->pdo->prepare("UPDATE licenses SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function restore_license(int $id): void {
        $stmt = $this->pdo->prepare("UPDATE licenses SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function list_licenses(): array {
        return $this->pdo->query('SELECT * FROM licenses ORDER BY created_at DESC')->fetchAll();
    }

    public function count_licenses(): int {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM licenses')->fetchColumn();
    }

    public function get_activation(int $license_id, string $site_url): array|false {
        $stmt = $this->pdo->prepare('SELECT * FROM activations WHERE license_id = ? AND site_url = ?');
        $stmt->execute([$license_id, $site_url]);
        return $stmt->fetch();
    }

    public function count_activations(int $license_id): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM activations WHERE license_id = ?');
        $stmt->execute([$license_id]);
        return (int) $stmt->fetchColumn();
    }

    public function list_activations(int $license_id): array {
        $stmt = $this->pdo->prepare('SELECT * FROM activations WHERE license_id = ? ORDER BY activated_at DESC');
        $stmt->execute([$license_id]);
        return $stmt->fetchAll();
    }

    public function add_activation(int $license_id, string $site_url, string $echs_version, string $wp_version): void {
        $stmt = $this->pdo->prepare('INSERT INTO activations (license_id, site_url, echs_version, wp_version) VALUES (?, ?, ?, ?)');
        $stmt->execute([$license_id, $site_url, $echs_version, $wp_version]);
    }

    public function remove_activation(int $license_id, string $site_url): void {
        $stmt = $this->pdo->prepare('DELETE FROM activations WHERE license_id = ? AND site_url = ?');
        $stmt->execute([$license_id, $site_url]);
    }

    public function update_last_seen(int $license_id, string $site_url, string $echs_version, string $wp_version): void {
        $stmt = $this->pdo->prepare("UPDATE activations SET last_seen_at = datetime('now'), echs_version = ?, wp_version = ? WHERE license_id = ? AND site_url = ?");
        $stmt->execute([$echs_version, $wp_version, $license_id, $site_url]);
    }

    public function all_activations(): array {
        return $this->pdo->query('SELECT a.*, l.license_key, l.client_name FROM activations a JOIN licenses l ON l.id = a.license_id ORDER BY a.last_seen_at DESC')->fetchAll();
    }

    public function check_rate_limit(string $ip, int $max_per_minute): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM request_log WHERE ip = ? AND ts >= datetime('now', '-1 minute')");
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn() < $max_per_minute;
    }

    public function log_request(string $ip, string $action, string $license_key, string $site_url, string $result): void {
        $stmt = $this->pdo->prepare('INSERT INTO request_log (ip, action, license_key, site_url, result) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$ip, $action, $license_key, $site_url, $result]);
    }

    public function get_recent_logs(int $limit = 100): array {
        $stmt = $this->pdo->prepare('SELECT * FROM request_log ORDER BY id DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count_total_downloads(): int {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM request_log WHERE action = 'download' AND result = 'downloaded'")->fetchColumn();
    }

    public function count_unique_sites(): int {
        return (int) $this->pdo->query("SELECT COUNT(DISTINCT site_url) FROM request_log WHERE site_url != ''")->fetchColumn();
    }

    public function count_subscribers(): int {
        return (int) $this->pdo->query("SELECT COUNT(DISTINCT site_url) FROM request_log WHERE action = 'info' AND site_url != ''")->fetchColumn();
    }

    public function get_unlicensed_sites(): array {
        return $this->pdo->query("
            SELECT
                site_url,
                MAX(ts) as last_seen,
                COUNT(*) as request_count,
                MAX(CASE WHEN action = 'info' THEN ts ELSE NULL END) as last_update_check
            FROM request_log
            WHERE site_url != ''
              AND site_url NOT IN (
                  SELECT DISTINCT a.site_url
                  FROM activations a
                  JOIN licenses l ON l.id = a.license_id
                  WHERE l.status = 'active'
              )
            GROUP BY site_url
            ORDER BY last_seen DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
