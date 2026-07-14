<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<div class="page-header">💬 Чат</div>
<div class="page-content" style="padding: 0 16px;">
  <div class="empty-state">Чат появится на Этапе 9</div>
</div>
