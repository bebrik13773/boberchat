<?php
/**
 * POST /api/moderation_approve.php
 * Требует роли moderator/admin.
 * Тело запроса (JSON): { post_id }
 *
 * Меняет статус поста на "approved" и пишет запись в moderation_log.
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
if (!in_array($_SESSION['role'], ['moderator', 'admin'], true)) {
    respond(403, ['error' => 'Доступно только модераторам']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Метод не поддерживается']);
}

$input = json_decode(file_get_contents('php://input'), true);
$postId = (int)($input['post_id'] ?? 0);

if ($postId <= 0) {
    respond(400, ['error' => 'Некорректный post_id']);
}

$moderatorId = (int)$_SESSION['user_id'];

$conn = db_connect();

// Обновляем только если пост реально был на модерации — защита от повторной/гонки обработки
$stmt = mysqli_prepare($conn, "UPDATE posts SET status = 'approved' WHERE id = ? AND status = 'pending'");
mysqli_stmt_bind_param($stmt, 'i', $postId);
mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($affected === 0) {
    mysqli_close($conn);
    respond(404, ['error' => 'Пост не найден или уже обработан']);
}

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO moderation_log (target_type, target_id, moderator_id, decision) VALUES ('post', ?, ?, 'approved')"
);
mysqli_stmt_bind_param($stmt, 'ii', $postId, $moderatorId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ['success' => true]);
