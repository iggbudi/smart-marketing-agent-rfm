<?php
/**
 * src/Admin/SettingsAdmin.php
 * Slice vertikal "Admin": pengaturan sistem, info sistem, & statistik platform (baca)
 * untuk admin/settings.php. POST (update general/email/security) tetap ditangani halaman.
 */

namespace App\Admin;

class SettingsAdmin
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Pengaturan sistem saat ini (system_settings). */
    public function settings(): array
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Informasi lingkungan server (PHP, DB, disk, limit). */
    public function systemInfo(): array
    {
        return [
            'php_version'        => phpversion(),
            'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_version'   => $this->db->query('SELECT VERSION()')->fetchColumn(),
            'disk_space'         => disk_free_space('.') ? round(disk_free_space('.') / 1024 / 1024 / 1024, 2) . ' GB' : 'Unknown',
            'memory_limit'       => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize'=> ini_get('upload_max_filesize'),
            'post_max_size'      => ini_get('post_max_size'),
        ];
    }

    /** Statistik platform + ukuran database. */
    public function platformStats(): array
    {
        $dbSize = $this->db->query(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size
             FROM information_schema.tables WHERE table_schema = DATABASE()"
        )->fetchColumn();
        return [
            'total_users'        => (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'total_businesses'   => (int)$this->db->query('SELECT COUNT(*) FROM businesses')->fetchColumn(),
            'total_customers'    => (int)$this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'total_transactions' => (int)$this->db->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),
            'database_size'      => $dbSize === null ? 0 : $dbSize,
        ];
    }
}
