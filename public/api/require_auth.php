<?php
/**
 * Подключить в начале любой защищённой PHP-страницы, лежащей
 * непосредственно в public/ или в public/views/:
 *   require_once __DIR__ . '/../api/require_auth.php';
 *
 * Если пользователь не залогинен — редиректит на login.php.
 * После подключения доступны $_SESSION['user_id'], ['username'], ['role'].
 *
 * ВАЖНО: этот файл сейчас не используется в проекте — каждая страница
 * (feed.php, chats.php и т.д.) проверяет сессию самостоятельно, инлайн.
 * Оставлен как общий хелпер на случай будущего рефакторинга.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php'); // относительный путь: сработает из public/ и любой её подпапки на 1 уровень
    exit;
}
