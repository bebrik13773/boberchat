<?php
/**
 * GET /api/chats_list.php
 * Требует авторизации.
 *
 * Возвращает список чатов текущего пользователя с превью последнего сообщения,
 * отсортированные по времени последней активности.
 * Для личных чатов (is_group=0) также отдаёт данные собеседника.
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

$myId = (int)$_SESSION['user_id'];
$conn = db_connect();

$sql = "
    SELECT
        c.id AS chat_id, c.is_group, c.title,
        (SELECT content FROM messages WHERE chat_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
        (SELECT is_encrypted FROM messages WHERE chat_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_encrypted,
        (SELECT created_at FROM messages WHERE chat_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_at,
        other_u.id AS other_user_id, other_u.username AS other_username,
        other_u.display_name AS other_display_name, other_u.display_name_status AS other_display_name_status,
        other_u.avatar_path AS other_avatar_path, other_u.avatar_status AS other_avatar_status
    FROM chats c
    JOIN chat_participants my_p ON my_p.chat_id = c.id AND my_p.user_id = ?
    LEFT JOIN chat_participants other_p ON other_p.chat_id = c.id AND other_p.user_id != ? AND c.is_group = 0
    LEFT JOIN users other_u ON other_u.id = other_p.user_id
    ORDER BY last_message_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $myId, $myId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$chats = [];
while ($row = mysqli_fetch_assoc($result)) {
    $chats[] = [
        'chat_id' => (int)$row['chat_id'],
        'is_group' => (bool)$row['is_group'],
        'title' => $row['title'],
        'last_message' => $row['last_message_encrypted'] ? '🔒 Зашифрованное сообщение' : $row['last_message'],
        'last_message_at' => $row['last_message_at'],
        'other_user' => $row['other_user_id'] ? [
            'id' => (int)$row['other_user_id'],
            'username' => $row['other_username'],
            'display_name' => $row['other_display_name_status'] === 'approved' ? $row['other_display_name'] : null,
            'avatar_path' => $row['other_avatar_status'] === 'approved' ? $row['other_avatar_path'] : null,
        ] : null,
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ['chats' => $chats]);
