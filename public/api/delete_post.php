<?php
/**
 * POST /api/delete_post.php
 * Требует авторизации.
 * Тело запроса (JSON): { post_id }
 *
 * Удаляет пост, но только если он принадлежит текущему пользователю.
 * Каскадно удаляются фото/лайки/комментарии (FK ON DELETE CASCADE в схеме).
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
$postId = (int)($input['post_id'] ?? 0);

if ($postId <= 0) {
    respond(400, ['error' => 'Некорректный post_id']);
}

$userId = (int)$_SESSION['user_id'];
$conn = db_connect();

// Удаляем только если пост реально принадлежит текущему пользователю
$stmt = mysqli_prepare($conn, 'DELETE FROM posts WHERE id = ? AND user_id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $postId, $userId);
mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

if ($affected === 0) {
    respond(404, ['error' => 'Пост не найден или не принадлежит вам']);
}

respond(200, ['success' => true]);
