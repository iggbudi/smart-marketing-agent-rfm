<?php
/**
 * tests/UploadDragDropCsrfTest.php
 * Mengunci perbaikan bug: upload drag-and-drop di upload.php TIDAK mengirim token
 * CSRF pada fetch() POST -> requireCsrf() menolak dengan 403 sebelum file diproses,
 * dan halaman di-reload tanpa impor sukses. Root cause: FormData hanya berisi
 * excel_file, tanpa field csrf_token.
 *
 * Test memverifikasi (struktur, tanpa headless browser):
 * 1. handleFileUpload() wajib menambahkan 'csrf_token' ke FormData.
 * 2. Zona drop (dropZone) wajib punya input hidden csrf_field() self-contained
 *    agar JS bisa membaca token tanpa bergantung form lain.
 */

use PHPUnit\Framework\TestCase;

class UploadDragDropCsrfTest extends TestCase
{
    public function testHandleFileUploadMenyertakanCsrfToken(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/upload.php');
        $this->assertNotFalse($src, 'upload.php harus bisa dibaca');

        $this->assertStringContainsString(
            "formData.append('csrf_token'",
            $src,
            'handleFileUpload wajib mengirim token CSRF pada upload drag&drop'
        );
    }

    public function testDropZonePunyaInputCsrfSendiri(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/upload.php');
        $this->assertStringContainsString('id="dropZone"', $src, 'dropZone harus ada');
        $this->assertStringContainsString(
            'input[name="csrf_token"]',
            $src,
            'JS harus membaca token CSRF dari input hidden'
        );
    }
}
