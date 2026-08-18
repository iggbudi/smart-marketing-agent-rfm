<?php
/**
 * src/Admin/BusinessAdmin.php
 * Slice vertikal "Admin": daftar bisnis (+jumlah customer/transaksi), daftar owner UMKM,
 * dan statistik bisnis (baca) untuk admin/businesses.php.
 * POST (add/edit/delete business) tetap ditangani halaman — slice ini hanya data baca.
 */

namespace App\Admin;

class BusinessAdmin
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Daftar bisnis + owner + jumlah customer & transaksi, urut terbaru. */
    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT b.*, u.full_name as owner_name, u.email as owner_email,
                    (SELECT COUNT(*) FROM customers c WHERE c.business_id = b.id) as customer_count,
                    (SELECT COUNT(*) FROM transactions t JOIN customers c ON t.customer_id = c.id
                      WHERE c.business_id = b.id) as transaction_count
             FROM businesses b
             LEFT JOIN users u ON b.user_id = u.id
             ORDER BY b.created_at DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Owner UMKM utk dropdown pemilihan owner. */
    public function umkmOwners(): array
    {
        $stmt = $this->db->query(
            "SELECT id, full_name, email FROM users WHERE role = 'umkm_owner' ORDER BY full_name"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Statistik ringkas: total/active businesses, total_customers, total_transactions. */
    public function getStats(): array
    {
        return [
            'total_businesses'  => (int)$this->db->query('SELECT COUNT(*) FROM businesses')->fetchColumn(),
            'active_businesses' => (int)$this->db->query(
                'SELECT COUNT(*) FROM businesses WHERE id IN (SELECT DISTINCT business_id FROM customers)'
            )->fetchColumn(),
            'total_customers'   => (int)$this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'total_transactions'=> (int)$this->db->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),
        ];
    }
}
