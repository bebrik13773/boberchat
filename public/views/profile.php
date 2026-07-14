<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
?>
<div class="page-header">👤 Профиль</div>
<div class="page-content" style="padding: 0 16px;">
  <div class="empty-state">Профиль появится на Этапе 7</div>
</div>
