<?php
/**
 * GET /api/get_comments.php?post_id=...
 * Требует авторизации.
 *
 * Возвращает комментарии к посту, от старых к новым.
 * Имя автора комментария показывается только если approved, иначе null.
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

$postId = (int)($_GET['post_id'] ?? 0);
if ($postId <= 0) {
    respond(400, ['error' => 'Некорректный post_id']);
}

$currentUserId = (int)$_SESSION['user_id'];
$conn = db_connect();

// Пост должен быть видимым (approved, либо свой)
$stmt = mysqli_prepare($conn, "SELECT id FROM posts WHERE id = ? AND (status = 'approved' OR user_id = ?) LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $postId, $currentUserId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$exists = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if (!$exists) {
    mysqli_close($conn);
    respond(404, ['error' => 'Пост не найден']);
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT c.id, c.text_content, c.created_at,
            u.id AS author_id, u.username, u.display_name, u.display_name_status
     FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.post_id = ?
     ORDER BY c.created_at ASC'
);
mysqli_stmt_bind_param($stmt, 'i', $postId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$comments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $comments[] = [
        'id' => (int)$row['id'],
        'text' => $row['text_content'],
        'created_at' => $row['created_at'],
        'author' => [
            'id' => (int)$row['author_id'],
            'username' => $row['username'],
            'display_name' => $row['display_name_status'] === 'approved' ? $row['display_name'] : null,
        ],
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ['comments' => $comments]);
