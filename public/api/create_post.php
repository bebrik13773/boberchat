<?php
/**
 * POST /api/create_post.php
 * Требует авторизации.
 * Тело запроса (JSON): { text, image_path? }
 *
 * image_path — необязательный путь, полученный ранее от api/upload_photo.php.
 *
 * Создаёт пост со статусом "pending" — попадёт в общую ленту
 * только после одобрения модератором (Этап 6).
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Метод не поддерживается']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['error' => 'Некорректное тело запроса']);
}

$text = trim($input['text'] ?? '');
$imagePath = trim($input['image_path'] ?? '');

if ($text === '' && $imagePath === '') {
    respond(400, ['error' => 'Пост не может быть пустым']);
}
if (mb_strlen($text) > 3000) {
    respond(400, ['error' => 'Слишком длинный текст (максимум 3000 символов)']);
}

// Простая проверка, что путь похож на наш собственный upload, а не произвольная строка/URL
if ($imagePath !== '' && !preg_match('#^assets/uploads/posts/[a-zA-Z0-9_.]+$#', $imagePath)) {
    respond(400, ['error' => 'Некорректный путь к изображению']);
}

$userId = (int)$_SESSION['user_id'];

$conn = db_connect();

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO posts (user_id, text_content, status) VALUES (?, ?, "pending")'
);
mysqli_stmt_bind_param($stmt, 'is', $userId, $text);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    respond(500, ['error' => 'Не удалось создать пост']);
}

$newPostId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if ($imagePath !== '') {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO post_images (post_id, image_path, sort_order) VALUES (?, ?, 0)'
    );
    mysqli_stmt_bind_param($stmt, 'is', $newPostId, $imagePath);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);

respond(201, [
    'success' => true,
    'post_id' => $newPostId,
    'status' => 'pending',
    'message' => 'Пост отправлен на модерацию',
]);
