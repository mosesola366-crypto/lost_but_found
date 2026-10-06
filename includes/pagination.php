<?php
/**
 * Pagination Utility for Property Reporting and Recovery System (PRS).
 * Security Unit, The Polytechnic Ibadan.
 */

/**
 * Execute a paginated database query.
 *
 * @param PDO    $pdo
 * @param string $countSql   Query returning single COUNT(*) column
 * @param string $selectSql  Query returning row data (without LIMIT/OFFSET)
 * @param array  $params     Bound parameters for queries
 * @param int    $perPage    Number of items per page
 * @param string $pageParam  GET parameter name for page number
 * @return array
 */
function paginate(PDO $pdo, string $countSql, string $selectSql, array $params = [], int $perPage = 10, string $pageParam = 'page'): array
{
    // Fetch total matching records
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;

    $currentPage = filter_input(INPUT_GET, $pageParam, FILTER_VALIDATE_INT) ?: 1;
    if ($currentPage < 1) {
        $currentPage = 1;
    }
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $perPage;

    // Append LIMIT and OFFSET to query
    $pagedSql = $selectSql . " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $selectStmt = $pdo->prepare($pagedSql);
    $selectStmt->execute($params);
    $items = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'items'        => $items,
        'total'        => $totalRecords,
        'total_pages'  => $totalPages,
        'current_page' => $currentPage,
        'per_page'     => $perPage,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}

/**
 * Render accessible, responsive HTML pagination links preserving active GET parameters.
 *
 * @param int    $currentPage
 * @param int    $totalPages
 * @param array  $preserveParams Array of GET parameters to preserve
 * @param string $pageParam      GET parameter name
 * @return string
 */
function render_pagination(int $currentPage, int $totalPages, array $preserveParams = [], string $pageParam = 'page'): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $queryParams = $_GET;
    if (!empty($preserveParams)) {
        $queryParams = array_intersect_key($queryParams, array_flip($preserveParams));
    }

    $buildUrl = function ($page) use ($queryParams, $pageParam) {
        $params = array_merge($queryParams, [$pageParam => $page]);
        return '?' . http_build_query($params);
    };

    $html = '<nav class="pagination" aria-label="Page navigation">';

    // Previous Page Button
    if ($currentPage > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage - 1)) . '" aria-label="Previous page">&laquo; Prev</a>';
    } else {
        $html .= '<span class="disabled" aria-disabled="true">&laquo; Prev</span>';
    }

    // Windowed Page Numbers
    $range = 2; // Number of links on each side of current
    $start = max(1, $currentPage - $range);
    $end   = min($totalPages, $currentPage + $range);

    if ($start > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl(1)) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="dots">&hellip;</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $currentPage) {
            $html .= '<span class="current" aria-current="page">' . $i . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($buildUrl($i)) . '">' . $i . '</a>';
        }
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="dots">&hellip;</span>';
        }
        $html .= '<a href="' . htmlspecialchars($buildUrl($totalPages)) . '">' . $totalPages . '</a>';
    }

    // Next Page Button
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage + 1)) . '" aria-label="Next page">Next &raquo;</a>';
    } else {
        $html .= '<span class="disabled" aria-disabled="true">Next &raquo;</span>';
    }

    $html .= '</nav>';

    return $html;
}
