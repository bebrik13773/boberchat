<?php
/**
 * POST /api/register.php
 * Тело запроса (JSON): { username, email, password, invite_code, display_name }
 *
 * Создаёт пользователя. Имя, username, аватар, био — все со статусом
 * "pending" до модерации (кроме случаев, когда значение уже было одобрено
 * ранее — см. moderation_cache, это подключим на Этапе 7).
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

// Пока задаём инвайт-код прямо здесь. Позже можно вынести в отдельный секрет/конфиг.
const INVITE_CODE = 'bober2026';

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

$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = (string)($input['password'] ?? '');
$inviteCode = trim($input['invite_code'] ?? '');
$displayName = trim($input['display_name'] ?? '');

// --- Валидация ---

if ($inviteCode !== INVITE_CODE) {
    respond(403, ['error' => 'Неверный инвайт-код']);
}

if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
    respond(400, ['error' => 'Username должен быть 3-32 символа: латиница, цифры, подчёркивание']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['error' => 'Некорректный email']);
}

if (strlen($password) < 6) {
    respond(400, ['error' => 'Пароль должен быть не короче 6 символов']);
}

if ($displayName === '') {
    respond(400, ['error' => 'Укажи имя']);
}
if (mb_strlen($displayName) > 64) {
    respond(400, ['error' => 'Имя слишком длинное (максимум 64 символа)']);
}

// --- Проверка уникальности ---

$conn = db_connect();

$stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    respond(409, ['error' => 'Такой username или email уже занят']);
}
mysqli_stmt_close($stmt);

// --- Создание пользователя ---

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Username и имя уходят в pending-поля до модерации.
// Реальный логин при этом всё равно работает по полю username сразу же —
// модерация влияет только на то, что ВИДНО другим пользователям (публичный профиль),
// не на возможность войти в аккаунт.
$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO users (username, email, password_hash, pending_display_name, username_status, display_name_status)
     VALUES (?, ?, ?, ?, "pending", "pending")'
);
mysqli_stmt_bind_param($stmt, 'ssss', $username, $email, $passwordHash, $displayName);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    respond(500, ['error' => 'Не удалось создать аккаунт']);
}

$newUserId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(201, [
    'success' => true,
    'user_id' => $newUserId,
    'message' => 'Аккаунт создан. Имя и username появятся у всех после модерации.',
]);
