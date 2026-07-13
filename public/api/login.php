<?php
/**
 * POST /api/login.php
 * Тело запроса (JSON): { username, password }
 *
 * Проверяет логин+пароль, стартует сессию.
 * Логиниться можно и по username, и по email — оба уникальны.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Метод не поддерживается']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['error' => 'Некорректное тело запроса']);
}

$login = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');

if ($login === '' || $password === '') {
    respond(400, ['error' => 'Укажи логин и пароль']);
}

$conn = db_connect();

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, username, password_hash, display_name, pending_display_name,
            display_name_status, role
     FROM users
     WHERE username = ? OR email = ?
     LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'ss', $login, $login);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
mysqli_close($conn);

if (!$user || !password_verify($password, $user['password_hash'])) {
    respond(401, ['error' => 'Неверный логин или пароль']);
}

// --- Сессия ---
session_regenerate_id(true); // защита от session fixation
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

// Показываем approved-имя, если его ещё нет — то, что ждёт модерации, с пометкой
$visibleName = $user['display_name_status'] === 'approved'
    ? $user['display_name']
    : null;

respond(200, [
    'success' => true,
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'display_name' => $visibleName, // null = фронт покажет "Загрузка…"
        'pending_display_name' => $user['pending_display_name'],
        'role' => $user['role'],
    ],
]);
