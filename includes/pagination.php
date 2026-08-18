<?php
/**
 * includes/pagination.php
 * Helper pagination server-side (LIMIT/OFFSET) untuk halaman daftar
 * (customers.php, transactions.php).
 */

if (!defined('DEFAULT_PER_PAGE')) {
    define('DEFAULT_PER_PAGE', 20);
}

/**
 * Hitung halaman aktif, offset, dan total halaman.
 *
 * @param int    $totalRows Jumlah total baris hasil filter (seluruh data bisnis).
 * @param int    $perPage   Jumlah baris per halaman.
 * @param int|null $page    Halaman yang diminta (default: dari $_GET['page']).
 * @return array [page, perPage, offset, totalPages]
 */
function paginate($totalRows, $perPage = DEFAULT_PER_PAGE, $page = null)
{
    if ($page === null) {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    }
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [$page, $perPage, $offset, $totalPages];
}

/**
 * Render tombol pagination Bootstrap (prev, nomor halaman, next).
 * Mempertahankan query string lain selain 'page' (mis. pencarian `q`).
 *
 * @param int $totalPages
 * @param int $page
 * @return string HTML pagination (kosong jika hanya 1 halaman).
 */
function renderPagination($totalPages, $page)
{
    if ($totalPages <= 1) {
        return '';
    }

    $qs = $_GET;
    unset($qs['page']);
    $query = http_build_query($qs);
    $sep = $query !== '' ? '&' : '';

    // Window: maksimal 7 nomor halaman agar tetap ringkas.
    $maxVisible = 7;
    $start = max(1, $page - 3);
    $end = min($totalPages, $start + $maxVisible - 1);
    $start = max(1, $end - $maxVisible + 1);

    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm justify-content-center mb-0">';

    if ($page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?' . $query . $sep . 'page=' . ($page - 1) . '">&laquo; Sebelumnya</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo; Sebelumnya</span></li>';
    }

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?' . $query . $sep . 'page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="?' . $query . $sep . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?' . $query . $sep . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }

    if ($page < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="?' . $query . $sep . 'page=' . ($page + 1) . '">&raquo; Berikutnya</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo; Berikutnya</span></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}
