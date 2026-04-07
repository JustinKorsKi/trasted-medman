<?php
require_once 'includes/config.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['messages' => []]);
    exit();
}

$chat_id = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : 0;
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if(!$chat_id) {
    echo json_encode(['messages' => []]);
    exit();
}

$result = mysqli_query($conn,
    "SELECT cm.*, u.username, u.role
     FROM chat_messages cm
     JOIN users u ON cm.user_id = u.id
     WHERE cm.group_chat_id = $chat_id
     AND cm.id > $last_id
     ORDER BY cm.created_at ASC"
);

$messages = [];
while($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

header('Content-Type: application/json');
echo json_encode(['messages' => $messages]);
?>
