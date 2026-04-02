<?php
/**
 * KYC/Verification Helper Functions
 */

function getUserVerificationStatus($user_id) {
    global $conn;
    
    $query = "SELECT verification_level, verification_document, verification_submitted_at,
                     verification_reviewed_at, verification_notes
              FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row;
    }
    
    return [
        'verification_level' => 'unverified',
        'verification_document' => null,
        'verification_submitted_at' => null,
        'verification_reviewed_at' => null,
        'verification_notes' => null
    ];
}

function hasPendingVerification($user_id) {
    global $conn;
    
    $query = "SELECT id FROM verification_requests 
              WHERE user_id = $user_id AND status = 'pending'";
    $result = mysqli_query($conn, $query);
    
    return mysqli_num_rows($result) > 0;
}

function getVerificationBadge($level) {
    switch($level) {
        case 'verified':
            return '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Verified</span>';
        case 'pending':
            return '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending Review</span>';
        case 'rejected':
            return '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Rejected</span>';
        default:
            return '<span class="badge badge-secondary"><i class="fas fa-exclamation-circle"></i> Unverified</span>';
    }
}

function canApplyForMidman($user_id) {
    global $conn;
    
    $query = "SELECT verification_level, role FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
    
    // Must be verified to become midman
    return $user['verification_level'] == 'verified' && $user['role'] != 'midman';
}
?>