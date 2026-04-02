<?php
// Simple email notification function
function sendNotification($to, $subject, $message) {
    // For local development, we'll just log the email
    // In production, you'd use PHP's mail() function or SMTP
    
    $log_message = "To: $to\nSubject: $subject\nMessage: $message\n---\n";
    file_put_contents(__DIR__ . '/../email_log.txt', $log_message, FILE_APPEND);
    
    return true;
}

// Transaction notifications
function notifyNewTransaction($transaction_id, $buyer_email, $seller_email, $midman_email) {
    $subject = "New Transaction #$transaction_id Started";
    $message = "A new transaction has been initiated. Login to your dashboard to view details.";
    
    sendNotification($buyer_email, $subject, $message);
    sendNotification($seller_email, $subject, $message);
    sendNotification($midman_email, $subject, "You have been assigned as Midman for transaction #$transaction_id");
}

function notifyStatusChange($transaction_id, $user_email, $status) {
    $status_messages = [
        'pending' => 'Your transaction is pending and waiting for midman confirmation.',
        'in_progress' => 'Payment has been held by midman. Seller can now ship the item.',
        'shipped' => 'Seller has marked the item as shipped. Please confirm receipt when you receive it.',
        'delivered' => 'Buyer has confirmed receipt. Midman will release payment soon.',
        'completed' => 'Transaction completed successfully! Thank you for using Trusted Midman.',
        'disputed' => 'A dispute has been opened for your transaction. Admin will review.',
        'cancelled' => 'Transaction has been cancelled.'
    ];
    
    $message = $status_messages[$status] ?? "Transaction status updated to: $status";
    sendNotification($user_email, "Transaction #$transaction_id Status Update", $message);
}

function notifyDisputeResolution($dispute_id, $user_email, $resolution) {
    sendNotification($user_email, "Dispute #$dispute_id Resolved", 
                    "Your dispute has been resolved. Resolution: $resolution");
}
?>