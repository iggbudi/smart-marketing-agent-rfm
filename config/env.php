<?php
/**
 * config/env.php
 * Helper environment: baca konfigurasi dari environment variable dengan
 * fallback ke file .env di root project, lalu ke nilai default (Fase 3.5).
 *
 * Prioritas pembacaan:
 *   1. Environment variable asli (getenv / SetEnv Apache / systemd Environment / export shell)
 *   2. File .env di root project (jika ada; lihat .env.example)
 *   3. Nilai default pada parameter pemanggil
 *
 * Dipakai oleh config/database.php dan config/openai.php.
 * Zero-dependency: tidak perlu vlucas/phpdotenv.
 */

if (!function_exists('env')) {
    /**
     * Baca nilai konfigurasi dengan fallback ke .env lalu default.
     *
     * @param string     $key
     * @param mixed|null $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        $file = loadEnvFile();
        if (isset($file[$key]) && $file[$key] !== '') {
            return $file[$key];
        }

        return $default;
    }

    /**
     * Parse file .env di root project (jika ada); hasil di-cache statis.
     * Format: KEY=VALUE per baris; baris # komentar diabaikan;
     * prefix 'export ' diizinkan; tanda kutip pembungkus dihapus.
     *
     * @return array
     */
    function loadEnvFile()
    {
        static $vars = null;
        if ($vars !== null) {
            return $vars;
        }

        $vars = [];
        $path = dirname(__DIR__) . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            return $vars;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            // Izinkan prefix 'export ' (gaya shell)
            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            // Hapus tanda kutip pembungkus jika ada ("..." atau '...')
            $len = strlen($val);
            if ($len >= 2 && (($val[0] === '"' && $val[$len - 1] === '"')
                || ($val[0] === "'" && $val[$len - 1] === "'"))) {
                $val = substr($val, 1, -1);
            }
            $vars[$key] = $val;
        }

        return $vars;
    }
}
