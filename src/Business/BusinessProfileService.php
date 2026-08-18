<?php
/**
 * src/Business/BusinessProfileService.php
 * Slice vertikal "Profil Bisnis": validasi + update data bisnis UMKM.
 * Dipakai oleh profile.php (email unik dicek lintas bisnis, kecuali diri sendiri).
 */

namespace App\Business;

class BusinessProfileService
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Validasi + update profil bisnis.
     * @param int   $businessId business pemilik (dari session, bukan input user).
     * @param array $data       name, owner_name, email, phone, address, business_type.
     * @return array{ok: bool, message: string}
     */
    public function update(int $businessId, array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $ownerName = trim((string)($data['owner_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));
        $businessType = trim((string)($data['business_type'] ?? ''));

        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama bisnis wajib diisi'];
        }
        if ($ownerName === '') {
            return ['ok' => false, 'message' => 'Nama pemilik wajib diisi'];
        }
        if ($email === '') {
            return ['ok' => false, 'message' => 'Email wajib diisi'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Format email tidak valid'];
        }

        // Email unik untuk bisnis LAIN (boleh sama dengan email sendiri)
        $stmt = $this->db->prepare("SELECT id FROM businesses WHERE email = ? AND id != ?");
        $stmt->execute([$email, $businessId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Email sudah digunakan oleh bisnis lain'];
        }

        $stmt = $this->db->prepare(
            "UPDATE businesses
             SET name = ?, owner_name = ?, email = ?, phone = ?, address = ?, business_type = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$name, $ownerName, $email, $phone, $address, $businessType, $businessId]);

        return ['ok' => true, 'message' => 'Profil bisnis berhasil diperbarui'];
    }

    /** Ambil satu business (untuk refresh setelah update). */
    public function get(int $businessId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$businessId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Daftar jenis bisnis untuk dropdown. */
    public static function businessTypes(): array
    {
        return [
            'Retail/Eceran',
            'F&B/Kuliner',
            'Fashion/Pakaian',
            'Kecantikan/Kosmetik',
            'Elektronik',
            'Otomotif',
            'Kesehatan',
            'Pendidikan',
            'Jasa',
            'Teknologi',
            'Pertanian',
            'Lainnya',
        ];
    }
}
