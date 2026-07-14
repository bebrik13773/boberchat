<?php
/**
 * POST /api/add_comment.php
 * Требует авторизации.
 * Тело запроса (JSON): { post_id, text }
 *
 * Добавляет комментарий к посту. Комментарии не проходят модерацию
 * (по ТЗ — модерация касается только постов).
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
$postId = (int)($input['post_id'] ?? 0);
$text = trim($input['text'] ?? '');

if ($postId <= 0) {
    respond(400, ['error' => 'Некорректный post_id']);
}
if ($text === '') {
    respond(400, ['error' => 'Комментарий не может быть пустым']);
}
if (mb_strlen($text) > 1000) {
    respond(400, ['error' => 'Комментарий слишком длинный (максимум 1000 символов)']);
}

$userId = (int)$_SESSION['user_id'];
$conn = db_connect();

// Пост должен существовать и быть видимым (approved, либо свой)
$stmt = mysqli_prepare($conn, "SELECT id FROM posts WHERE id = ? AND (status = 'approved' OR user_id = ?) LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $postId, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$exists = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if (!$exists) {
    mysqli_close($conn);
    respond(404, ['error' => 'Пост не найден']);
}

$stmt = mysqli_prepare($conn, 'INSERT INTO comments (post_id, user_id, text_content) VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'iis', $postId, $userId, $text);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    respond(500, ['error' => 'Не удалось добавить комментарий']);
}

$newCommentId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(201, ['success' => true, 'comment_id' => $newCommentId]);
