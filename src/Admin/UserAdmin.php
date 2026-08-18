<?php
/**
 * src/Admin/UserAdmin.php
 * Slice vertikal "Admin": statistik user & daftar user (baca) untuk admin/users.php.
 * POST (add/edit/delete user) tetap ditangani halaman — slice ini hanya data baca.
 */

namespace App\Admin;

class UserAdmin
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Statistik ringkas: total_users, active_sessions, super_admins, umkm_owners. */
    public function getStats(): array
    {
        return [
            'total_users'     => (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'active_sessions' => (int)$this->db->query('SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW()')->fetchColumn(),
            'super_admins'    => (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn(),
            'umkm_owners'     => (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role = 'umkm_owner'")->fetchColumn(),
        ];
    }

    /** Daftar user + business_name (LEFT JOIN), urut terbaru. */
    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT u.*, b.name as business_name
             FROM users u
             LEFT JOIN businesses b ON u.id = b.user_id
             ORDER BY u.created_at DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
