<?php
/**
 * GET /api/me.php
 * Возвращает текущего залогиненного пользователя или 401, если сессии нет.
 * Используется фронтом, чтобы решить — показывать контент или редиректить на вход.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

echo json_encode([
    'id' => (int)$_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
], JSON_UNESCAPED_UNICODE);
