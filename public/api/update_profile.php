<?php
/**
 * POST /api/update_profile.php
 * Требует авторизации.
 * Тело запроса (JSON): { display_name?, username?, bio? }
 *
 * Каждое переданное поле уходит в pending-версию и статус "pending" —
 * КРОМЕ случая, когда точно такое же значение уже было одобрено раньше
 * (проверяем через moderation_cache) — тогда применяется сразу.
 *
 * Пустые/непереданные поля не трогаем.
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
if (!is_array($input)) {
    respond(400, ['error' => 'Некорректное тело запроса']);
}

$userId = (int)$_SESSION['user_id'];
$conn = db_connect();

/**
 * Проверяет moderation_cache: было ли это значение уже одобрено раньше.
 * Если да — можно применить сразу, без очереди.
 */
function wasApprovedBefore(mysqli $conn, string $fieldType, string $value): bool {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id FROM moderation_cache WHERE field_type = ? AND normalized_value = ? AND status = 'approved' LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $fieldType, $value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $found = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

$updates = [];   // SQL SET-фрагменты
$params = [];    // значения для bind_param
$types = '';      // типы для bind_param

// --- display_name ---
if (isset($input['display_name'])) {
    $displayName = trim($input['display_name']);
    if (mb_strlen($displayName) === 0 || mb_strlen($displayName) > 64) {
        respond(400, ['error' => 'Имя должно быть от 1 до 64 символов']);
    }
    if (wasApprovedBefore($conn, 'display_name', $displayName)) {
        $updates[] = 'display_name = ?, display_name_status = "approved", pending_display_name = NULL';
        $params[] = $displayName;
        $types .= 's';
    } else {
        $updates[] = 'pending_display_name = ?, display_name_status = "pending"';
        $params[] = $displayName;
        $types .= 's';
    }
}

// --- username ---
if (isset($input['username'])) {
    $username = trim($input['username']);
    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        respond(400, ['error' => 'Username: 3-32 символа, латиница/цифры/подчёркивание']);
    }

    // Проверка уникальности (не считая себя самого)
    $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'si', $username, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        respond(409, ['error' => 'Такой username уже занят']);
    }
    mysqli_stmt_close($stmt);

    if (wasApprovedBefore($conn, 'username', $username)) {
        $updates[] = 'username = ?, username_status = "approved", pending_username = NULL';
        $params[] = $username;
        $types .= 's';
    } else {
        $updates[] = 'pending_username = ?, username_status = "pending"';
        $params[] = $username;
        $types .= 's';
    }
}

// --- bio ---
if (isset($input['bio'])) {
    $bio = trim($input['bio']);
    if (mb_strlen($bio) > 500) {
        respond(400, ['error' => 'Описание слишком длинное (максимум 500 символов)']);
    }
    if (wasApprovedBefore($conn, 'bio', $bio)) {
        $updates[] = 'bio = ?, bio_status = "approved", pending_bio = NULL';
        $params[] = $bio;
        $types .= 's';
    } else {
        $updates[] = 'pending_bio = ?, bio_status = "pending"';
        $params[] = $bio;
        $types .= 's';
    }
}

if (empty($updates)) {
    mysqli_close($conn);
    respond(400, ['error' => 'Нечего сохранять']);
}

$sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
$params[] = $userId;
$types .= 'i';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    respond(500, ['error' => 'Не удалось сохранить профиль']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ['success' => true, 'message' => 'Профиль обновлён. Новые поля появятся у всех после модерации (если ещё не проверялись ранее).']);
