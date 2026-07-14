<?php
/**
 * POST /api/upload_photo.php
 * Требует авторизации.
 * multipart/form-data с полем "photo".
 *
 * Загружает фото на сервер, возвращает путь к файлу.
 * Сам путь потом передаётся в create_post.php, чтобы прикрепить фото к посту.
 * Пост и фото создаются в 2 запроса, а не в 1 — так проще на shared-хостинге
 * без сложной обработки multipart+json одновременно.
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

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    respond(400, ['error' => 'Файл не получен']);
}

$file = $_FILES['photo'];

// --- Валидация ---

$maxSizeBytes = 5 * 1024 * 1024; // 5 МБ — с запасом под фото с телефона, но не давая раздувать хостинг
if ($file['size'] > $maxSizeBytes) {
    respond(400, ['error' => 'Файл слишком большой (максимум 5 МБ)']);
}

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

// Проверяем реальный MIME-тип по содержимому, не доверяя имени файла от клиента
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$actualMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedTypes[$actualMime])) {
    respond(400, ['error' => 'Разрешены только изображения: JPG, PNG, WEBP, GIF']);
}

$extension = $allowedTypes[$actualMime];

// --- Сохранение файла ---

$uploadDir = __DIR__ . '/../assets/uploads/posts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$userId = (int)$_SESSION['user_id'];
$uniqueName = $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$destPath = $uploadDir . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respond(500, ['error' => 'Не удалось сохранить файл']);
}

// Публичный путь, который будет храниться в БД и использоваться в <img src="...">
$publicPath = 'assets/uploads/posts/' . $uniqueName;

respond(201, [
    'success' => true,
    'path' => $publicPath,
]);
