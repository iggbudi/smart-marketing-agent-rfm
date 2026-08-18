<?php
/**
 * src/App/Customers/CustomerRepository.php
 * Slice vertikal "Customers": akses data + aturan bisnis pelanggan.
 * Dipakai oleh customers.php (list/search/pagination/CRUD), transactions.php
 * (dropdown), dashboard.php (count), api/export-customers.php (withStats).
 */

namespace App\Customers;

class CustomerRepository
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Jumlah seluruh pelanggan milik business. */
    public function count(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    /** Jumlah pelanggan yang pernah bertransaksi (distinct customer_id). */
    public function countActive(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT customer_id) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    /** Total nominal seluruh transaksi business. */
    public function totalSales(int $businessId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (float)$stmt->fetchColumn();
    }

    /** Dropdown pelanggan (dipakai transactions.php). */
    public function listForDropdown(int $businessId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, customer_name, phone FROM customers WHERE business_id = ? ORDER BY customer_name"
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Hitung baris hasil pencarian (untuk paginate). */
    public function countSearch(int $businessId, string $q): int
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers c WHERE " . $where);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Cari pelanggan + agregat transaksi, dengan pagination server-side.
     * LIMIT/OFFSET di-cast (int) — gotcha PDO (AGENTS.md §2.1).
     */
    public function search(int $businessId, string $q, int $perPage, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare("
            SELECT c.*,
                   COUNT(t.id) as total_transactions,
                   COALESCE(SUM(t.amount), 0) as total_spent,
                   MAX(t.transaction_date) as last_transaction
            FROM customers c
            LEFT JOIN transactions t ON c.id = t.customer_id
            WHERE " . $where . "
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Seluruh pelanggan + agregat (untuk export CSV/XLSX). */
    public function withStats(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*,
                   COUNT(t.id) as total_transactions,
                   COALESCE(SUM(t.amount), 0) as total_spent,
                   MAX(t.transaction_date) as last_transaction
            FROM customers c
            LEFT JOIN transactions t ON c.id = t.customer_id
            WHERE c.business_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Tambah pelanggan. Throw \InvalidArgumentException bila nama/HP kosong. */
    public function add(int $businessId, string $name, string $phone, string $email): int
    {
        if (trim($name) === '' || trim($phone) === '') {
            throw new \InvalidArgumentException('Nama dan nomor HP harus diisi!');
        }
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $businessId,
            trim($name),
            trim($phone),
            trim($email) !== '' ? trim($email) : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Hapus pelanggan milik business saja (tolak lintas-bisnis). */
    public function delete(int $businessId, int $customerId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM customers WHERE id = ? AND business_id = ?");
        $stmt->execute([$customerId, $businessId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array{0: string, 1: array} [where, params] */
    private function buildSearchWhere(int $businessId, string $q): array
    {
        $where = 'c.business_id = ?';
        $params = [$businessId];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= ' AND (c.customer_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        return [$where, $params];
    }
}
