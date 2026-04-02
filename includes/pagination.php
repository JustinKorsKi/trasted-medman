<?php
/**
 * Pagination Helper Functions
 * Trusted Midman System
 */

/**
 * Paginate a MySQL query result
 * 
 * @param mysqli $conn Database connection
 * @param string $query The base query (without LIMIT)
 * @param int $page Current page number
 * @param int $per_page Items per page
 * @return array Pagination data and results
 */
function paginateQuery($conn, $query, $page = 1, $per_page = 20) {
    // Get total count
    $count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
    $count_query = preg_replace('/ORDER BY.+?(LIMIT|GROUP BY|$)/', '', $count_query);
    
    // Remove any existing LIMIT clause
    $count_query = preg_replace('/LIMIT\s+\d+\s*(OFFSET\s*\d+)?/i', '', $count_query);
    
    $count_result = mysqli_query($conn, $count_query);
    $total_rows = mysqli_fetch_assoc($count_result)['total'];
    
    // Calculate pagination
    $total_pages = ceil($total_rows / $per_page);
    $current_page = max(1, min($page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    
    // Add LIMIT to original query
    $paginated_query = $query . " LIMIT $per_page OFFSET $offset";
    
    // Get results
    $results = mysqli_query($conn, $paginated_query);
    
    return [
        'results' => $results,
        'total_rows' => $total_rows,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'per_page' => $per_page,
        'offset' => $offset
    ];
}

/**
 * Generate pagination HTML links
 * 
 * @param int $current_page Current page
 * @param int $total_pages Total pages
 * @param string $base_url Base URL for links
 * @param array $params Additional query parameters
 * @return string HTML pagination links
 */
function generatePaginationLinks($current_page, $total_pages, $base_url, $params = []) {
    if ($total_pages <= 1) {
        return '';
    }
    
    // Build query string
    $query_string = '';
    if (!empty($params)) {
        $query_parts = [];
        foreach ($params as $key => $value) {
            if ($value !== '' && $key !== 'page') {
                $query_parts[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        if (!empty($query_parts)) {
            $query_string = '&' . implode('&', $query_parts);
        }
    }
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<a href="' . $base_url . '?page=' . ($current_page - 1) . $query_string . '" class="pagination-link">';
        $html .= '<i class="fas fa-chevron-left"></i> Previous</a>';
    } else {
        $html .= '<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i> Previous</span>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    // Show first page if needed
    if ($start_page > 1) {
        $html .= '<a href="' . $base_url . '?page=1' . $query_string . '" class="pagination-link">1</a>';
        if ($start_page > 2) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
    }
    
    // Page range
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="pagination-link active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . '?page=' . $i . $query_string . '" class="pagination-link">' . $i . '</a>';
        }
    }
    
    // Show last page if needed
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
        $html .= '<a href="' . $base_url . '?page=' . $total_pages . $query_string . '" class="pagination-link">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . '?page=' . ($current_page + 1) . $query_string . '" class="pagination-link">';
        $html .= 'Next <i class="fas fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="pagination-link disabled">Next <i class="fas fa-chevron-right"></i></span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get pagination info as array
 * 
 * @param array $pagination_data Pagination data from paginateQuery
 * @return array Pagination info
 */
function getPaginationInfo($pagination_data) {
    return [
        'showing_from' => $pagination_data['offset'] + 1,
        'showing_to' => min($pagination_data['offset'] + $pagination_data['per_page'], $pagination_data['total_rows']),
        'total_items' => $pagination_data['total_rows'],
        'current_page' => $pagination_data['current_page'],
        'total_pages' => $pagination_data['total_pages'],
        'items_per_page' => $pagination_data['per_page']
    ];
}
?>
