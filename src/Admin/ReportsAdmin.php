<?php
/**
 * src/Admin/ReportsAdmin.php
 * Slice vertikal "Admin": data laporan per tipe untuk admin/reports.php.
 * Report 'businesses' memakai kolom business_type (kolom `category` TIDAK ada di schema
 * businesses — query lama memakai b.category dan selalu gagal "Unknown column"; diperbaiki di sini).
 */

namespace App\Admin;

class ReportsAdmin
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Data laporan per tipe (users/businesses/transactions/activity/rfm) dalam rentang tanggal. */
    public function reportData(string $type, string $start, string $end): array
    {
        switch ($type) {
            case 'users':
                $stmt = $this->db->prepare(
                    "SELECT DATE(u.created_at) as date, COUNT(*) as count, u.role, GROUP_CONCAT(u.full_name) as usernames
                     FROM users u WHERE DATE(u.created_at) BETWEEN ? AND ?
                     GROUP BY DATE(u.created_at), u.role ORDER BY date DESC"
                );
                break;
            case 'businesses':
                $stmt = $this->db->prepare(
                    "SELECT DATE(b.created_at) as date, COUNT(*) as count, b.business_type as category,
                            GROUP_CONCAT(b.name) as business_names
                     FROM businesses b WHERE DATE(b.created_at) BETWEEN ? AND ?
                     GROUP BY DATE(b.created_at), b.business_type ORDER BY date DESC"
                );
                break;
            case 'transactions':
                $stmt = $this->db->prepare(
                    "SELECT DATE(t.transaction_date) as date, COUNT(*) as transaction_count,
                            SUM(t.amount) as total_amount, AVG(t.amount) as avg_amount, b.name as business_name
                     FROM transactions t
                     JOIN customers c ON t.customer_id = c.id
                     JOIN businesses b ON c.business_id = b.id
                     WHERE DATE(t.transaction_date) BETWEEN ? AND ?
                     GROUP BY DATE(t.transaction_date), b.id
                     ORDER BY date DESC, total_amount DESC"
                );
                break;
            case 'activity':
                $stmt = $this->db->prepare(
                    "SELECT DATE(al.created_at) as date, al.action, COUNT(*) as count, u.full_name, u.role
                     FROM activity_logs al JOIN users u ON al.user_id = u.id
                     WHERE DATE(al.created_at) BETWEEN ? AND ?
                     GROUP BY DATE(al.created_at), al.action, u.id
                     ORDER BY date DESC, count DESC"
                );
                break;
            case 'rfm':
                $stmt = $this->db->prepare(
                    "SELECT DATE(r.created_at) as date, r.rfm_segment, COUNT(*) as customer_count,
                            AVG(r.recency_score) as avg_recency, AVG(r.frequency_score) as avg_frequency,
                            AVG(r.monetary_score) as avg_monetary, b.name as business_name
                     FROM rfm_analysis r
                     JOIN customers c ON r.customer_id = c.id
                     JOIN businesses b ON c.business_id = b.id
                     WHERE DATE(r.created_at) BETWEEN ? AND ?
                     GROUP BY DATE(r.created_at), r.rfm_segment, b.id
                     ORDER BY date DESC, customer_count DESC"
                );
                break;
            default:
                return [];
        }
        $stmt->execute([$start, $end]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Opsi quick date range (label Indonesia). */
    public function dateRangeOptions(): array
    {
        return [
            'today'        => 'Hari Ini',
            'yesterday'    => 'Kemarin',
            'this_week'    => 'Minggu Ini',
            'last_week'    => 'Minggu Lalu',
            'this_month'   => 'Bulan Ini',
            'last_month'   => 'Bulan Lalu',
            'this_quarter' => 'Kuartal Ini',
            'this_year'    => 'Tahun Ini',
            'custom'       => 'Rentang Kustom',
        ];
    }
}
