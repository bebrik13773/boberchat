<?php
/**
 * GET /api/moderation_profile_queue.php
 * Требует роли moderator/admin.
 *
 * Возвращает пользователей, у которых есть хотя бы одно поле профиля
 * в статусе "pending".
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
    SELECT id, username,
           pending_display_name, display_name_status,
           pending_username, username_status,
           pending_avatar_path, avatar_status,
           pending_bio, bio_status
    FROM users
    WHERE display_name_status = 'pending'
       OR username_status = 'pending'
       OR avatar_status = 'pending'
       OR bio_status = 'pending'
";

$result = mysqli_query($conn, $sql);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $fields = [];
    if ($row['display_name_status'] === 'pending') {
        $fields[] = ['field' => 'display_name', 'label' => 'Имя', 'value' => $row['pending_display_name']];
    }
    if ($row['username_status'] === 'pending') {
        $fields[] = ['field' => 'username', 'label' => 'Username', 'value' => $row['pending_username']];
    }
    if ($row['avatar_status'] === 'pending') {
        $fields[] = ['field' => 'avatar', 'label' => 'Фото', 'value' => $row['pending_avatar_path']];
    }
    if ($row['bio_status'] === 'pending') {
        $fields[] = ['field' => 'bio', 'label' => 'Описание', 'value' => $row['pending_bio']];
    }

    $items[] = [
        'user_id' => (int)$row['id'],
        'username' => $row['username'],
        'fields' => $fields,
    ];
}

mysqli_close($conn);

respond(200, ['items' => $items]);
