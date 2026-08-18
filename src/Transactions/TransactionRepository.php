<?php
/**
 * src/Transactions/TransactionRepository.php
 * Slice vertikal "Transactions": akses data + aturan bisnis transaksi.
 * Dipakai oleh transactions.php, dashboard.php (recent/revenue),
 * api/export-transactions.php (allWithCustomer).
 */

namespace App\Transactions;

class TransactionRepository
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function count(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    public function totalRevenue(int $businessId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (float)$stmt->fetchColumn();
    }

    public function countActiveCustomers(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT customer_id) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    public function countSearch(int $businessId, string $q): int
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE " . $where
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function search(int $businessId, string $q, int $perPage, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare("
            SELECT t.*, c.customer_name, c.phone
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE " . $where . "
            ORDER BY t.transaction_date DESC, t.created_at DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Transaksi terbaru (dipakai dashboard). */
    public function recent(int $businessId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, c.customer_name
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE t.business_id = ?
            ORDER BY t.transaction_date DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Seluruh transaksi + data customer (untuk export). */
    public function allWithCustomer(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, c.customer_name, c.phone
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE t.business_id = ?
            ORDER BY t.transaction_date DESC, t.created_at DESC
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tambah transaksi. Throw \InvalidArgumentException bila customer/tanggal/amount invalid.
     * quantity di-clamp minimal 1 (sama dgn perilaku lama `(int)$_POST['quantity'] ?: 1`).
     */
    public function add(int $businessId, int $customerId, string $transactionDate, float $amount, ?string $productName, int $quantity): int
    {
        if ($customerId <= 0 || $transactionDate === '' || $amount <= 0) {
            throw new \InvalidArgumentException('Customer, tanggal, dan jumlah harus diisi!');
        }
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (business_id, customer_id, transaction_date, amount, product_name, quantity, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $businessId,
            $customerId,
            $transactionDate,
            $amount,
            $productName !== null && $productName !== '' ? $productName : null,
            max(1, $quantity),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $businessId, int $transactionId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM transactions WHERE id = ? AND business_id = ?");
        $stmt->execute([$transactionId, $businessId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array{0: string, 1: array} [where, params] */
    private function buildSearchWhere(int $businessId, string $q): array
    {
        $where = 't.business_id = ?';
        $params = [$businessId];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= ' AND (c.customer_name LIKE ? OR t.product_name LIKE ?)';
            array_push($params, $like, $like);
        }
        return [$where, $params];
    }
}
