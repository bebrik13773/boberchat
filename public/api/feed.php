<?php
/**
 * GET /api/feed.php
 * Требует авторизации.
 *
 * Возвращает посты для общей ленты:
 *  - все посты со статусом "approved" (видны всем)
 *  - плюс собственные посты автора со статусом "pending"/"rejected"
 *    (видны только ему самому, с пометкой статуса)
 *
 * Имя автора показывается только если display_name_status = approved,
 * иначе — null (фронт покажет "Загрузка…").
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

$currentUserId = (int)$_SESSION['user_id'];

$conn = db_connect();

$sql = "
    SELECT
        p.id, p.text_content, p.status, p.rejection_reason, p.is_pinned, p.created_at,
        u.id AS author_id, u.username,
        u.display_name, u.display_name_status,
        u.avatar_path, u.avatar_status,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
        EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me,
        (SELECT image_path FROM post_images WHERE post_id = p.id ORDER BY sort_order LIMIT 1) AS image_path
    FROM posts p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = 'approved' OR p.user_id = ?
    ORDER BY p.is_pinned DESC, p.created_at DESC
    LIMIT 50
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $currentUserId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$posts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $posts[] = [
        'id' => (int)$row['id'],
        'text' => $row['text_content'],
        'status' => $row['status'],
        'rejection_reason' => $row['rejection_reason'],
        'is_pinned' => (bool)$row['is_pinned'],
        'created_at' => $row['created_at'],
        'is_mine' => (int)$row['author_id'] === $currentUserId,
        'like_count' => (int)$row['like_count'],
        'comment_count' => (int)$row['comment_count'],
        'liked_by_me' => (bool)$row['liked_by_me'],
        'image_path' => $row['image_path'],
        'author' => [
            'id' => (int)$row['author_id'],
            'username' => $row['username'],
            'display_name' => $row['display_name_status'] === 'approved' ? $row['display_name'] : null,
            'avatar_path' => $row['avatar_status'] === 'approved' ? $row['avatar_path'] : null,
        ],
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ['posts' => $posts]);
