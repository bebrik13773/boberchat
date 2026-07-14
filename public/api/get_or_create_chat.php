<?php
/**
 * POST /api/get_or_create_chat.php
 * Требует авторизации.
 * Тело запроса (JSON): { username } — с кем открыть личный чат.
 *
 * Если чат между этими двумя пользователями уже существует — возвращает его id.
 * Если нет — создаёт новый (is_group = 0) и добавляет обоих участников.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

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
$targetUsername = trim($input['username'] ?? '');

if ($targetUsername === '') {
    respond(400, ['error' => 'Не указан username']);
}

$myId = (int)$_SESSION['user_id'];
$conn = db_connect();

// Находим целевого пользователя
$stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $targetUsername);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$targetUser = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$targetUser) {
    mysqli_close($conn);
    respond(404, ['error' => 'Пользователь не найден']);
}

$targetId = (int)$targetUser['id'];

if ($targetId === $myId) {
    mysqli_close($conn);
    respond(400, ['error' => 'Нельзя открыть чат с самим собой']);
}

// Ищем существующий личный чат между этими двумя пользователями
$stmt = mysqli_prepare(
    $conn,
    "SELECT c.id
     FROM chats c
     JOIN chat_participants p1 ON p1.chat_id = c.id AND p1.user_id = ?
     JOIN chat_participants p2 ON p2.chat_id = c.id AND p2.user_id = ?
     WHERE c.is_group = 0
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $myId, $targetId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$existing = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($existing) {
    mysqli_close($conn);
    respond(200, ['chat_id' => (int)$existing['id'], 'created' => false]);
}

// Создаём новый чат
mysqli_begin_transaction($conn);

try {
    mysqli_query($conn, 'INSERT INTO chats (is_group) VALUES (0)');
    $newChatId = mysqli_insert_id($conn);

    $stmt = mysqli_prepare($conn, 'INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?), (?, ?)');
    mysqli_stmt_bind_param($stmt, 'iiii', $newChatId, $myId, $newChatId, $targetId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_close($conn);
    respond(500, ['error' => 'Не удалось создать чат']);
}

mysqli_close($conn);

respond(201, ['chat_id' => $newChatId, 'created' => true]);
