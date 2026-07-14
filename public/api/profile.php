<?php
/**
 * GET /api/profile.php?username=...
 * Требует авторизации.
 *
 * Возвращает публичный профиль пользователя с учётом модерации:
 * непроверенные поля (имя/аватар/био) приходят как null,
 * фронт покажет вместо них "Загрузка…".
 *
 * Если смотрящий — сам владелец профиля, дополнительно возвращаются
 * pending-версии полей, чтобы он видел, что именно ждёт проверки.
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

$username = trim($_GET['username'] ?? '');
if ($username === '') {
    respond(400, ['error' => 'Не указан username']);
}

$conn = db_connect();

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, username, email,
            display_name, display_name_status, pending_display_name,
            avatar_path, avatar_status, pending_avatar_path,
            bio, bio_status, pending_bio,
            username_status, pending_username,
            role, created_at
     FROM users
     WHERE username = ?
     LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    mysqli_close($conn);
    respond(404, ['error' => 'Пользователь не найден']);
}

$isOwner = (int)$user['id'] === (int)$_SESSION['user_id'];

// Публичный username — сложный случай: сам логин уже виден по URL/сессии,
// но публично отображаемое поле показываем только если approved (или это сам владелец).
$publicUsername = $user['username_status'] === 'approved' || $isOwner
    ? $user['username']
    : null;

$posts_count_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS cnt FROM posts WHERE user_id = ? AND status = 'approved'"
);
mysqli_stmt_bind_param($posts_count_stmt, 'i', $user['id']);
mysqli_stmt_execute($posts_count_stmt);
$postsCount = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($posts_count_stmt))['cnt'];
mysqli_stmt_close($posts_count_stmt);

mysqli_close($conn);

$profile = [
    'id' => (int)$user['id'],
    'username' => $publicUsername,
    'display_name' => $user['display_name_status'] === 'approved' ? $user['display_name'] : null,
    'avatar_path' => $user['avatar_status'] === 'approved' ? $user['avatar_path'] : null,
    'bio' => $user['bio_status'] === 'approved' ? $user['bio'] : null,
    'role' => $user['role'],
    'posts_count' => $postsCount,
    'is_owner' => $isOwner,
];

if ($isOwner) {
    $profile['email'] = $user['email']; // видно только самому себе
    $profile['pending'] = [
        'username' => $user['username_status'] === 'pending' ? $user['pending_username'] : null,
        'display_name' => $user['display_name_status'] === 'pending' ? $user['pending_display_name'] : null,
        'avatar_path' => $user['avatar_status'] === 'pending' ? $user['pending_avatar_path'] : null,
        'bio' => $user['bio_status'] === 'pending' ? $user['pending_bio'] : null,
    ];
    $profile['statuses'] = [
        'username' => $user['username_status'],
        'display_name' => $user['display_name_status'],
        'avatar' => $user['avatar_status'],
        'bio' => $user['bio_status'],
    ];
    // Владельцу всегда показываем его реальный username, даже если он ещё не approved —
    // иначе человек не поймёт, кто он на собственной странице
    $profile['username'] = $user['username'];
}

respond(200, ['profile' => $profile]);
