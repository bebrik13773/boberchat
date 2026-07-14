<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
if (!in_array($_SESSION['role'], ['moderator', 'admin'], true)) {
    http_response_code(403);
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<div class="page-header">🛡️ Модерация</div>

<div class="feed page-content">
  <div id="moderationContainer">
    <div class="empty-state">Загрузка очереди…</div>
  </div>
</div>
