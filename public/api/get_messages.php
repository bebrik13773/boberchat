<?php
/**
 * GET /api/get_messages.php?chat_id=...&after_id=0
 * Требует авторизации.
 *
 * Возвращает сообщения чата. Если передан after_id — только сообщения
 * с id больше указанного (для поллинга новых сообщений).
 * Проверяет, что запрашивающий — участник чата.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/db.php';

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    respond(401, ['error' => 'Не авторизован']);
}

$chatId = (int)($_GET['chat_id'] ?? 0);
$afterId = (int)($_GET['after_id'] ?? 0);

if ($chatId <= 0) {
    respond(400, ['error' => 'Некорректный chat_id']);
}

$myId = (int)$_SESSION['user_id'];
$conn = db_connect();

$stmt = mysqli_prepare($conn, 'SELECT id FROM chat_participants WHERE chat_id = ? AND user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $chatId, $myId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$isParticipant = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if (!$isParticipant) {
    mysqli_close($conn);
    respond(403, ['error' => 'Вы не участник этого чата']);
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, sender_id, content, is_encrypted, created_at
     FROM messages
     WHERE chat_id = ? AND id > ?
     ORDER BY created_at ASC'
);
mysqli_stmt_bind_param($stmt, 'ii', $chatId, $afterId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'id' => (int)$row['id'],
        'sender_id' => (int)$row['sender_id'],
        'is_mine' => (int)$row['sender_id'] === $myId,
        'content' => $row['content'],
        'is_encrypted' => (bool)$row['is_encrypted'],
        'created_at' => $row['created_at'],
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ['messages' => $messages]);
