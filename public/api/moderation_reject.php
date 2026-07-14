<?php
/**
 * POST /api/moderation_reject.php
 * Требует роли moderator/admin.
 * Тело запроса (JSON): { post_id, reason? }
 *
 * Меняет статус поста на "rejected", сохраняет причину (если указана)
 * и пишет запись в moderation_log.
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
$reason = trim($input['reason'] ?? '');

if ($postId <= 0) {
    respond(400, ['error' => 'Некорректный post_id']);
}
if (mb_strlen($reason) > 255) {
    respond(400, ['error' => 'Причина слишком длинная (максимум 255 символов)']);
}

$moderatorId = (int)$_SESSION['user_id'];

$conn = db_connect();

$stmt = mysqli_prepare(
    $conn,
    "UPDATE posts SET status = 'rejected', rejection_reason = ? WHERE id = ? AND status = 'pending'"
);
mysqli_stmt_bind_param($stmt, 'si', $reason, $postId);
mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($affected === 0) {
    mysqli_close($conn);
    respond(404, ['error' => 'Пост не найден или уже обработан']);
}

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO moderation_log (target_type, target_id, moderator_id, decision, reason) VALUES ('post', ?, ?, 'rejected', ?)"
);
mysqli_stmt_bind_param($stmt, 'iis', $postId, $moderatorId, $reason);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ['success' => true]);
