<?php
/**
 * src/Upload/UploadValidator.php
 * Validasi upload spreadsheet: error code, ukuran (5MB), ekstensi, MIME finfo.
 * Menyatukan validasi yang dulu diduplikasi di upload.php & api/upload-excel.php.
 */

namespace App\Upload;

class UploadValidator
{
    public const MAX_SIZE = 5 * 1024 * 1024;
    public const ALLOWED_EXT = ['xlsx', 'xls', 'csv'];

    private const ALLOWED_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel',                                          // .xls
        'application/octet-stream',                                          // beberapa .xls lama
        'text/csv',
        'text/plain',
    ];

    /**
     * @param array $file Elemen $_FILES['excel_file'].
     * @return array{ok: bool, message: string, ext: string, mime: string}
     */
    public static function validate(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'message' => 'Gagal mengunggah file (error code ' . (int)($file['error'] ?? 0) . ').',
                'ext' => '',
                'mime' => '',
            ];
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            return [
                'ok' => false,
                'message' => 'Ukuran file melebihi batas maksimal 5 MB.',
                'ext' => '',
                'mime' => '',
            ];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return [
                'ok' => false,
                'message' => 'Ekstensi file tidak diizinkan. Gunakan .xlsx, .xls, atau .csv.',
                'ext' => $ext,
                'mime' => '',
            ];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name'] ?? '') ?: '';
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return [
                'ok' => false,
                'message' => 'Tipe file tidak valid. Pastikan file yang diunggah benar-benar spreadsheet/CSV. (terdeteksi: ' . $mime . ')',
                'ext' => $ext,
                'mime' => $mime,
            ];
        }

        return ['ok' => true, 'message' => '', 'ext' => $ext, 'mime' => $mime];
    }
}
