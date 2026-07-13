<?php
/**
 * Подключить в начале любой защищённой PHP-страницы:
 *   require_once __DIR__ . '/api/require_auth.php';
 *
 * Если пользователь не залогинен — редиректит на login.html.
 * После подключения доступны $_SESSION['user_id'], ['username'], ['role'].
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login.html');
    exit;
}
