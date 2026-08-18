<?php
/**
 * tests/BusinessProfileServiceTest.php
 * Slice Profil Bisnis: validasi (wajib + format email + unik), update — DB test.
 * Semua email bisnis memakai uniqid() (kolom businesses.email UNIQUE; DB test persisten).
 */

use App\Business\BusinessProfileService;
use PHPUnit\Framework\TestCase;

class BusinessProfileServiceTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $email = 'biz' . uniqid() . '@test.local';
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['ProfileBiz', 'Owner', $email]);
        return (int)$this->db->lastInsertId();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Batik Semarang Jaya',
            'owner_name' => 'Budi Santoso',
            'email' => 'budi' . uniqid() . '@batiksemarang.com',
            'phone' => '08123456789',
            'address' => 'Jl. Pandanaran 123',
            'business_type' => 'Fashion/Pakaian',
        ], $overrides);
    }

    public function testUpdateValidatesRequiredFields()
    {
        $biz = $this->createBusiness();
        $svc = new BusinessProfileService($this->db);

        $r = $svc->update($biz, $this->validData(['name' => '  ']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Nama bisnis wajib diisi', $r['message']);

        $r = $svc->update($biz, $this->validData(['owner_name' => '']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Nama pemilik wajib diisi', $r['message']);

        $r = $svc->update($biz, $this->validData(['email' => 'bukan-email']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Format email tidak valid', $r['message']);
    }

    public function testUpdateRejectsEmailUsedByOtherBusiness()
    {
        $svc = new BusinessProfileService($this->db);
        $bizA = $this->createBusiness();
        $bizB = $this->createBusiness();
        $emailA = $svc->get($bizA)['email'];

        // bizB mencoba pakai email milik bizA -> tolak
        $r = $svc->update($bizB, $this->validData(['email' => $emailA]));
        $this->assertFalse($r['ok']);
        $this->assertSame('Email sudah digunakan oleh bisnis lain', $r['message']);

        // bizA memakai email sendiri (tidak berubah) -> boleh
        $r = $svc->update($bizA, $this->validData(['email' => $emailA]));
        $this->assertTrue($r['ok']);
    }

    public function testUpdatePersistsAndRefreshes()
    {
        $biz = $this->createBusiness();
        $svc = new BusinessProfileService($this->db);

        $r = $svc->update($biz, $this->validData(['phone' => '08999999999']));
        $this->assertTrue($r['ok']);
        $this->assertSame('Profil bisnis berhasil diperbarui', $r['message']);

        $row = $svc->get($biz);
        $this->assertSame('Batik Semarang Jaya', $row['name']);
        $this->assertSame('08999999999', $row['phone']);
        $this->assertSame('Fashion/Pakaian', $row['business_type']);
    }

    public function testBusinessTypesList()
    {
        $types = BusinessProfileService::businessTypes();
        $this->assertContains('Retail/Eceran', $types);
        $this->assertContains('Lainnya', $types);
    }
}
