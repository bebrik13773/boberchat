<?php
/**
 * POST /api/toggle_like.php
 * Требует авторизации.
 * Тело запроса (JSON): { post_id }
 *
 * Если пользователь ещё не лайкал пост — ставит лайк.
 * Если уже лайкал — убирает.
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

if ($postId <= 0) {
    respond(400, ['error' => 'Некорректный post_id']);
}

$userId = (int)$_SESSION['user_id'];
$conn = db_connect();

// Убеждаемся, что пост существует и виден (approved, либо свой)
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

$stmt = mysqli_prepare($conn, 'SELECT id FROM likes WHERE post_id = ? AND user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $postId, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$alreadyLiked = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($alreadyLiked) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM likes WHERE post_id = ? AND user_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $postId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $liked = false;
} else {
    $stmt = mysqli_prepare($conn, 'INSERT INTO likes (post_id, user_id) VALUES (?, ?)');
    mysqli_stmt_bind_param($stmt, 'ii', $postId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $liked = true;
}

$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS cnt FROM likes WHERE post_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $postId);
mysqli_stmt_execute($stmt);
$likeCount = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
mysqli_stmt_close($stmt);

mysqli_close($conn);

respond(200, ['success' => true, 'liked' => $liked, 'like_count' => $likeCount]);
