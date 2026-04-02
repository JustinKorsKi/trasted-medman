<?php
// Pagination function
function paginate($current_page, $total_pages, $base_url) {
    if($total_pages <= 1) return '';
    
    $html = '<div class="pagination" style="text-align: center; margin: 20px 0;">';
    
    // Previous button
    if($current_page > 1) {
        $html .= '<a href="' . $base_url . 'page=' . ($current_page - 1) . '" class="btn">← Previous</a> ';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    for($i = $start; $i <= $end; $i++) {
        if($i == $current_page) {
            $html .= '<span class="btn btn-success" style="background: #3498db;">' . $i . '</span> ';
        } else {
            $html .= '<a href="' . $base_url . 'page=' . $i . '" class="btn">' . $i . '</a> ';
        }
    }
    
    // Next button
    if($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . 'page=' . ($current_page + 1) . '" class="btn">Next →</a>';
    }
    
    $html .= '</div>';
    return $html;
}

// Apply pagination to a query
function paginateQuery($conn, $base_query, $page = 1, $per_page = 20) {
    $page = max(1, intval($page));
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_query = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) as total FROM', $base_query);
    $count_query = preg_replace('/ORDER BY.*?$/s', '', $count_query);
    
    $result = mysqli_query($conn, $count_query);
    $total = mysqli_fetch_assoc($result)['total'];
    $total_pages = ceil($total / $per_page);
    
    // Get paginated results
    $paginated_query = $base_query . " LIMIT $offset, $per_page";
    $results = mysqli_query($conn, $paginated_query);
    
    return [
        'results' => $results,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total' => $total,
        'per_page' => $per_page
    ];
}
?>