<?php
/**
 * tests/UploadValidatorTest.php
 * Validasi upload: error code, ukuran 5MB, ekstensi, MIME finfo.
 */

use App\Upload\UploadValidator;
use PHPUnit\Framework\TestCase;

class UploadValidatorTest extends TestCase
{
    public function testRejectsMissingFile()
    {
        $r = UploadValidator::validate(['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'name' => '', 'tmp_name' => '']);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('error code', $r['message']);
    }

    public function testRejectsOversize()
    {
        $r = UploadValidator::validate([
            'error' => UPLOAD_ERR_OK,
            'size' => 5 * 1024 * 1024 + 1,
            'name' => 'data.xlsx',
            'tmp_name' => __FILE__,
        ]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('5 MB', $r['message']);
    }

    public function testRejectsBadExtension()
    {
        $r = UploadValidator::validate([
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
            'name' => 'data.php',
            'tmp_name' => __FILE__,
        ]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Ekstensi', $r['message']);
    }

    public function testAcceptsCsvFile()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv_val_');
        file_put_contents($tmp, "nama,email,tanggal,nominal\nAndi,a@b.id,2026-08-01,100000\n");
        $r = UploadValidator::validate([
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
            'name' => 'data.csv',
            'tmp_name' => $tmp,
        ]);
        unlink($tmp);
        $this->assertTrue($r['ok']);
        $this->assertSame('csv', $r['ext']);
        $this->assertSame('text/plain', $r['mime']); // finfo mendeteksi CSV polos sebagai text/plain (diizinkan)
    }
}
