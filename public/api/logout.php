<?php
/**
 * POST /api/logout.php
 * Завершает сессию.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$_SESSION = [];
session_destroy();

echo json_encode(['success' => true]);
