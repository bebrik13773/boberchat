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

<script>
(function () {
  const container = document.getElementById('moderationContainer');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function timeAgo(isoString) {
    const date = new Date(isoString.replace(' ', 'T'));
    const diffSec = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diffSec < 60) return 'только что';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + ' мин назад';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + ' ч назад';
    return date.toLocaleDateString('ru-RU');
  }

  function renderCard(post) {
    const authorLabel = post.author.display_name
      ? `${escapeHtml(post.author.display_name)} (@${escapeHtml(post.author.username)})`
      : `@${escapeHtml(post.author.username)}`;

    const imageHtml = post.image_path
      ? `<img class="post-image" src="${post.image_path}" alt="">`
      : '';

    return `
      <div class="card moderation-card" data-post-id="${post.id}">
        <div class="post-meta-author">
          <b>${authorLabel}</b> · ${timeAgo(post.created_at)}
        </div>
        <div class="post-text">${escapeHtml(post.text)}</div>
        ${imageHtml}
        <div class="moderation-actions">
          <button class="btn btn-secondary reject-btn">Отклонить</button>
          <button class="btn btn-primary approve-btn">Одобрить</button>
        </div>
      </div>
    `;
  }

  async function loadQueue() {
    try {
      const res = await fetch('api/moderation_queue.php', { cache: 'no-store' });
      if (!res.ok) throw new Error('queue error');
      const data = await res.json();

      if (data.posts.length === 0) {
        container.innerHTML = '<div class="empty-state">Очередь пуста 🎉</div>';
        return;
      }
      container.innerHTML = data.posts.map(renderCard).join('');
    } catch (err) {
      container.innerHTML = '<div class="empty-state">Не удалось загрузить очередь</div>';
    }
  }

  loadQueue();
})();
</script>
