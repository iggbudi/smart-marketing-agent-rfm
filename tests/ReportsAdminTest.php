<?php
/**
 * tests/ReportsAdminTest.php
 * Slice Admin\ReportsAdmin: data laporan per tipe (users/businesses/transactions/activity/rfm)
 * untuk admin/reports.php — prepared statement, filter tanggal.
 */

use App\Admin\ReportsAdmin;
use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class ReportsAdminTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    public function testReportDataPerTipeMemuatDataHariIni(): void
    {
        $email = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare("INSERT INTO users (email, password, full_name, role, is_active, email_verified, created_at)
                            VALUES (?, ?, ?, 'umkm_owner', 1, 1, NOW())")
            ->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Owner R']);
        $ownerId = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO businesses (user_id, name, owner_name, email, created_at)
                            VALUES (?, 'Rubiz', 'Owner R', ?, NOW())")
            ->execute([$ownerId, 'rp_' . uniqid() . '@test.local']);
        $biz = (int)$this->db->lastInsertId();
        $cust = (new CustomerRepository($this->db))->add($biz, 'Andi', '0811', '');
        (new TransactionRepository($this->db))->add($biz, $cust, date('Y-m-d'), 120000, 'Batik', 1);
        $this->db->prepare("INSERT INTO rfm_analysis (business_id, customer_id, recency_score, frequency_score, monetary_score, rfm_segment, analysis_date, created_at)
                            VALUES (?, ?, 4, 4, 4, 'Loyal', CURDATE(), NOW())")->execute([$biz, $cust]);
        $this->db->prepare("INSERT INTO activity_logs (user_id, business_id, action, description, created_at)
                            VALUES (?, ?, 'login', 'report test', NOW())")->execute([$ownerId, $biz]);

        try {
            $r = new ReportsAdmin($this->db);
            $today = date('Y-m-d');
            $this->assertNotEmpty($r->reportData('users', $today, $today), 'report users harus ada');
            $this->assertNotEmpty($r->reportData('businesses', $today, $today), 'report businesses harus ada');
            $this->assertNotEmpty($r->reportData('transactions', $today, $today), 'report transactions harus ada');
            $this->assertNotEmpty($r->reportData('activity', $today, $today), 'report activity harus ada');
            $this->assertNotEmpty($r->reportData('rfm', $today, $today), 'report rfm harus ada');
            $this->assertSame([], $r->reportData('users', '2000-01-01', '2000-01-02'), 'range kosong harus []');
            $this->assertIsArray($r->dateRangeOptions(), 'date range option harus array');
        } finally {
            $this->db->prepare("DELETE FROM activity_logs WHERE business_id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM rfm_analysis WHERE business_id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM transactions WHERE customer_id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$ownerId]);
        }
    }
}
