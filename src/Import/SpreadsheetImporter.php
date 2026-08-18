<?php
/**
 * src/Import/SpreadsheetImporter.php
 * Slice vertikal "Import": impor pelanggan + transaksi dari xlsx/xls/csv
 * untuk satu UMKM (business_id). Dipindah dari includes/import.php
 * (fungsi global -> class; helper _import* menjadi method private).
 *
 * Format kolom yang dikenali (header fleksibel, ID & EN):
 *   - Nama pelanggan  (wajib):  nama, name, pelanggan, customer
 *   - Email                      email
 *   - No HP                       hp, telp, phone, no hp, kontak
 *   - Tanggal transaksi (wajib):  tanggal, date, tgl, transaction date
 *   - Nominal (wajib):            nominal, amount, harga, total, unit price
 *   - Produk                      produk, product, nama produk
 *   - Qty                         jumlah, qty, quantity
 * Satu baris dihitung sebagai transaksi bila memiliki nama + tanggal + nominal
 * yang valid.
 */

namespace App\Import;

class SpreadsheetImporter
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Import spreadsheet untuk satu business (atomic: beginTransaction/commit/rollBack).
     *
     * @param int    $businessId   business_id pemilik data.
     * @param string $filePath     Path file yang akan dibaca.
     * @param string $originalName Nama file asli (untuk ekstensi & riwayat).
     * @return array{processed:int, failed:int, errors:array, message:string}
     */
    public function import(int $businessId, string $filePath, string $originalName): array
    {
        $result = ['processed' => 0, 'failed' => 0, 'errors' => [], 'message' => ''];

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            $result['message'] = 'Ekstensi tidak didukung. Gunakan .xlsx, .xls, atau .csv.';
            return $result;
        }

        if (!is_file($filePath)) {
            $result['message'] = 'File tidak ditemukan.';
            return $result;
        }

        try {
            // 1) Baca seluruh baris menjadi array 2D
            if ($ext === 'csv') {
                $rows = $this->readCsv($filePath);
            } else {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($filePath);
                $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            }

            if (count($rows) < 2) {
                $result['message'] = 'File tidak memiliki data (minimal header + 1 baris).';
                return $result;
            }

            // 2) Petakan header -> posisi kolom
            $header = array_map('strval', $rows[0]);
            $col = $this->mapColumns($header);

            if ($col['customer_name'] === null) {
                $result['message'] = 'Kolom "Nama Pelanggan" tidak ditemukan di header. Periksa format file.';
                return $result;
            }

            // 3) Import dalam satu transaksi DB agar atomic
            $this->db->beginTransaction();
            $uploadId = $this->logStart($businessId, $originalName, $filePath);

            for ($i = 1, $n = count($rows); $i < $n; $i++) {
                $row = $rows[$i];
                if (!is_array($row) || (count($row) === 1 && trim((string)$row[0]) === '')) {
                    continue; // baris kosong
                }
                $line = $i + 1;

                $name    = $this->cell($row, $col['customer_name'] ?? null);
                $email   = $this->cell($row, $col['email'] ?? null);
                $phone   = $this->cell($row, $col['phone'] ?? null);
                $dateRaw = $this->cell($row, $col['transaction_date'] ?? null);
                $amountRaw = $this->cell($row, $col['amount'] ?? null);
                $product = $this->cell($row, $col['product_name'] ?? null);
                $qtyRaw  = $this->cell($row, $col['quantity'] ?? null);

                if ($name === '') {
                    $result['errors'][] = "Baris {$line}: nama pelanggan kosong (dilewati).";
                    $result['failed']++;
                    continue;
                }

                $date = $this->normalizeDate($dateRaw);
                $amount = $this->normalizeAmount($amountRaw);

                if ($date === '') {
                    $result['errors'][] = "Baris {$line}: tanggal transaksi tidak valid ('{$dateRaw}'), dilewati.";
                    $result['failed']++;
                    continue;
                }
                if ($amount === null) {
                    $result['errors'][] = "Baris {$line}: nominal transaksi tidak valid ('{$amountRaw}'), dilewati.";
                    $result['failed']++;
                    continue;
                }

                // Upsert customer per business (bukan global by email)
                $customerId = $this->upsertCustomer($businessId, $name, $email, $phone);

                $qty = (int)$qtyRaw;
                if ($qty < 1) {
                    $qty = 1;
                }

                $stmt = $this->db->prepare(
                    "INSERT INTO transactions (business_id, customer_id, transaction_date, amount, product_name, quantity, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())"
                );
                $stmt->execute([$businessId, $customerId, $date, $amount, $product !== '' ? $product : null, $qty]);

                $result['processed']++;
            }

            $this->logFinish($uploadId, $result['processed']);
            $this->db->commit();

            if ($result['processed'] === 0) {
                $result['message'] = 'Tidak ada transaksi yang valid untuk diimport.';
            } else {
                $result['message'] = "Import selesai: {$result['processed']} transaksi berhasil, {$result['failed']} baris gagal/dilewati.";
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $result['message'] = 'Gagal import: ' . $e->getMessage();
            $result['failed']++;
        }

        return $result;
    }

    /** Riwayat upload terbaru (dipakai upload.php). */
    public function history(int $businessId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, filename, records_imported, status, error_message, created_at
             FROM upload_history WHERE business_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Baca CSV ke array 2D, tangani BOM UTF-8. */
    private function readCsv(string $path): array
    {
        $rows = [];
        $bom = "\xEF\xBB\xBF";
        if (($h = fopen($path, 'r')) === false) {
            return $rows;
        }
        while (($row = fgetcsv($h)) !== false) {
            if (!$rows && isset($row[0])) {
                $row[0] = preg_replace('/^' . preg_quote($bom, '/') . '/', '', (string)$row[0]);
            }
            $rows[] = $row;
        }
        fclose($h);
        return $rows;
    }

    /** Petakan header ke indeks kolom kanonik. */
    private function mapColumns(array $header): array
    {
        $col = [
            'customer_name'    => null,
            'email'            => null,
            'phone'            => null,
            'transaction_date' => null,
            'amount'           => null,
            'product_name'     => null,
            'quantity'         => null,
        ];

        foreach ($header as $i => $raw) {
            $h = strtolower((string)$raw);
            $h = preg_replace('/[\s_\.\-\(\)]+/', ' ', trim($h)) ?? $h;
            $h = trim($h);

            $has = function (array $keys) use ($h) {
                foreach ($keys as $k) {
                    if ($h !== '' && strpos($h, $k) !== false) {
                        return true;
                    }
                }
                return false;
            };

            if ($col['customer_name'] === null && $has(['nama', 'name', 'pelanggan', 'customer'])) {
                $col['customer_name'] = $i;
            } elseif ($col['email'] === null && $h === 'email') {
                $col['email'] = $i;
            } elseif ($col['phone'] === null && $has(['no hp', 'hp', 'telp', 'phone', 'kontak', 'whatsapp', 'wa'])) {
                $col['phone'] = $i;
            } elseif ($col['transaction_date'] === null && $has(['tanggal', 'date', 'tgl'])) {
                $col['transaction_date'] = $i;
            } elseif ($col['amount'] === null && $has(['nominal', 'amount', 'harga', 'total', 'uang', 'spent'])) {
                $col['amount'] = $i;
            } elseif ($col['product_name'] === null && $has(['produk', 'product', 'item'])) {
                $col['product_name'] = $i;
            } elseif ($col['quantity'] === null && $has(['qty', 'quantity', 'jumlah', 'kuantitas'])) {
                $col['quantity'] = $i;
            }
        }

        return $col;
    }

    /** Ambil nilai sel secara aman. */
    private function cell(array $row, $idx): string
    {
        if ($idx === null || !isset($row[$idx])) {
            return '';
        }
        $v = $row[$idx];
        return $v === null ? '' : trim((string)$v);
    }

    /** Normalisasi tanggal -> 'Y-m-d' atau ''. Mendukung Excel serial & berbagai format. */
    private function normalizeDate($raw): string
    {
        if ($raw === '') {
            return '';
        }

        // Excel date serial (angka)
        if (is_numeric($raw)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$raw)->format('Y-m-d');
            } catch (\Throwable $e) {
                return '';
            }
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'm/d/Y', 'Y/m/d', 'Y.m.d'];
        foreach ($formats as $f) {
            $d = \DateTime::createFromFormat($f, $raw);
            if ($d && $d->format($f) === $raw) {
                return $d->format('Y-m-d');
            }
        }

        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    /** Normalisasi nominal -> float atau null. */
    private function normalizeAmount($raw): ?float
    {
        if ($raw === '' || $raw === null) {
            return null;
        }
        if (is_int($raw)) {
            $s = (string)$raw;
        } elseif (is_float($raw)) {
            return (float)$raw;
        } else {
            $s = (string)$raw;
        }
        $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-') {
            return null;
        }
        // '1.500.000,50' (ribuan titik, desimal koma)
        if (substr_count($s, ',') === 1 && strpos($s, '.') !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif ($s !== '' && strpos($s, ',') !== false && strpos($s, '.') === false) {
            // '1.500.000' tanpa desimal -> kita perlakukan koma sebagai ribuan bila >1
            // khusus satu koma tanpa titik -> desimal koma
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s); // hapus ribuan pakai koma '1,500,000'
        }
        if (!is_numeric($s)) {
            return null;
        }
        return (float)$s;
    }

    /** Upsert customer per business_id (match by email, lalu phone, lalu nama). */
    private function upsertCustomer(int $businessId, string $name, string $email, string $phone): int
    {
        if ($email !== '') {
            $where = 'email = ?';
            $val = $email;
        } elseif ($phone !== '') {
            $where = 'phone = ?';
            $val = $phone;
        } else {
            $where = 'customer_name = ?';
            $val = $name;
        }

        $stmt = $this->db->prepare("SELECT id FROM customers WHERE business_id = ? AND {$where} LIMIT 1");
        $stmt->execute([$businessId, $val]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($existing) {
            return (int)$existing['id'];
        }

        $ins = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, email, phone, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $ins->execute([$businessId, $name, $email !== '' ? $email : null, $phone !== '' ? $phone : null]);

        return (int)$this->db->lastInsertId();
    }

    /** Mulai catatan di upload_history. */
    private function logStart(int $businessId, string $originalName, string $filePath): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO upload_history (business_id, filename, file_path, records_imported, status, created_at)
             VALUES (?, ?, ?, 0, 'processing', NOW())"
        );
        $stmt->execute([$businessId, $originalName, $filePath]);

        return (int)$this->db->lastInsertId();
    }

    /** Selesaikan catatan upload_history. */
    private function logFinish(int $uploadId, int $processed): void
    {
        $stmt = $this->db->prepare("UPDATE upload_history SET status = 'completed', records_imported = ? WHERE id = ?");
        $stmt->execute([$processed, $uploadId]);
    }
}
