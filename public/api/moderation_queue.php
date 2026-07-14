<?php
/**
 * GET /api/moderation_queue.php
 * Требует авторизации и роли moderator/admin.
 *
 * Возвращает посты со статусом "pending", ожидающие проверки,
 * от старых к новым (кто раньше написал — тот раньше проверяется).
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

if (!in_array($_SESSION['role'], ['moderator', 'admin'], true)) {
    respond(403, ['error' => 'Доступно только модераторам']);
}

$conn = db_connect();

$sql = "
    SELECT
        p.id, p.text_content, p.created_at,
        u.id AS author_id, u.username, u.display_name, u.display_name_status,
        (SELECT image_path FROM post_images WHERE post_id = p.id ORDER BY sort_order LIMIT 1) AS image_path
    FROM posts p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = 'pending'
    ORDER BY p.created_at ASC
";

$result = mysqli_query($conn, $sql);

$posts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $posts[] = [
        'id' => (int)$row['id'],
        'text' => $row['text_content'],
        'created_at' => $row['created_at'],
        'image_path' => $row['image_path'],
        'author' => [
            'id' => (int)$row['author_id'],
            'username' => $row['username'],
            // В очереди модерации показываем username всегда — это внутренний
            // инструмент для модератора, а не публичная лента, ему нужно точно знать, кто автор.
            'display_name' => $row['display_name'],
        ],
    ];
}

mysqli_close($conn);

respond(200, ['posts' => $posts]);
