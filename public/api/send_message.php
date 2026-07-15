<?php
/**
 * POST /api/send_message.php
 * Требует авторизации.
 * Тело запроса (JSON): { chat_id, content }
 *
 * Отправляет сообщение в чат. Проверяет, что отправитель — участник чата.
 * E2E-шифрование (libsodium) для личных чатов — задел на v1.1,
 * пока is_encrypted всегда 0 (сообщения хранятся открытым текстом).
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Метод не поддерживается']);
}

$input = json_decode(file_get_contents('php://input'), true);
$chatId = (int)($input['chat_id'] ?? 0);
$content = trim($input['content'] ?? '');

if ($chatId <= 0) {
    respond(400, ['error' => 'Некорректный chat_id']);
}
if ($content === '') {
    respond(400, ['error' => 'Сообщение не может быть пустым']);
}
if (mb_strlen($content) > 5000) {
    respond(400, ['error' => 'Сообщение слишком длинное']);
}

$myId = (int)$_SESSION['user_id'];
$conn = db_connect();

// Проверяем, что отправитель реально участник этого чата
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
    'INSERT INTO messages (chat_id, sender_id, content, is_encrypted) VALUES (?, ?, ?, 0)'
);
mysqli_stmt_bind_param($stmt, 'iis', $chatId, $myId, $content);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    respond(500, ['error' => 'Не удалось отправить сообщение']);
}

$newMessageId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(201, ['success' => true, 'message_id' => $newMessageId]);
