<?php
/**
 * POST /api/moderation_profile_decide.php
 * Требует роли moderator/admin.
 * Тело запроса (JSON): { user_id, field, decision, reason? }
 *   field: display_name | username | avatar | bio
 *   decision: approved | rejected
 *
 * При approved: применяет pending-значение как основное, статус -> approved,
 * плюс добавляет запись в moderation_cache (для будущего авто-пропуска).
 * При rejected: статус -> rejected, pending-значение остаётся (для истории/повторной попытки).
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Метод не поддерживается']);
}

$input = json_decode(file_get_contents('php://input'), true);
$targetUserId = (int)($input['user_id'] ?? 0);
$field = $input['field'] ?? '';
$decision = $input['decision'] ?? '';
$reason = trim($input['reason'] ?? '');

$fieldMap = [
    'display_name' => ['column' => 'display_name', 'pending' => 'pending_display_name', 'status' => 'display_name_status', 'cache_type' => 'display_name'],
    'username'     => ['column' => 'username',     'pending' => 'pending_username',     'status' => 'username_status',     'cache_type' => 'username'],
    'avatar'       => ['column' => 'avatar_path',  'pending' => 'pending_avatar_path',  'status' => 'avatar_status',       'cache_type' => 'avatar_hash'],
    'bio'          => ['column' => 'bio',          'pending' => 'pending_bio',          'status' => 'bio_status',          'cache_type' => 'bio'],
];

if ($targetUserId <= 0 || !isset($fieldMap[$field]) || !in_array($decision, ['approved', 'rejected'], true)) {
    respond(400, ['error' => 'Некорректные параметры']);
}

$map = $fieldMap[$field];
$moderatorId = (int)$_SESSION['user_id'];

$conn = db_connect();

// Достаём текущее pending-значение
$stmt = mysqli_prepare($conn, "SELECT {$map['pending']} AS pending_value FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $targetUserId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    mysqli_close($conn);
    respond(404, ['error' => 'Пользователь не найден']);
}

$pendingValue = $row['pending_value'];

if ($decision === 'approved') {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users SET {$map['column']} = ?, {$map['status']} = 'approved', {$map['pending']} = NULL WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $pendingValue, $targetUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Кэшируем для будущего авто-пропуска
    if ($pendingValue !== null) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO moderation_cache (field_type, normalized_value, status)
             VALUES (?, ?, 'approved')
             ON DUPLICATE KEY UPDATE status = 'approved'"
        );
        mysqli_stmt_bind_param($stmt, 'ss', $map['cache_type'], $pendingValue);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
} else {
    $stmt = mysqli_prepare($conn, "UPDATE users SET {$map['status']} = 'rejected' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $targetUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$targetType = 'profile_' . $field;
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO moderation_log (target_type, target_id, moderator_id, decision, reason) VALUES (?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'siiss', $targetType, $targetUserId, $moderatorId, $decision, $reason);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conn);

respond(200, ['success' => true]);
