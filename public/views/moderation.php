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
    // MySQL хранит created_at в UTC — добавляем "Z", чтобы JS верно считал разницу
    const date = new Date(isoString.replace(' ', 'T') + 'Z');
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
          <button class="btn btn-secondary reject-btn" data-post-id="${post.id}">Отклонить</button>
          <button class="btn btn-primary approve-btn" data-post-id="${post.id}">Одобрить</button>
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

  function removeCard(postId) {
    const card = container.querySelector(`[data-post-id="${postId}"]`);
    if (card) card.remove();
    if (!container.querySelector('.moderation-card')) {
      container.innerHTML = '<div class="empty-state">Очередь пуста 🎉</div>';
    }
  }

  container.addEventListener('click', async (e) => {
    const approveBtn = e.target.closest('.approve-btn');
    const rejectBtn = e.target.closest('.reject-btn');

    if (approveBtn) {
      const postId = approveBtn.dataset.postId;
      approveBtn.disabled = true;
      try {
        const res = await fetch('api/moderation_approve.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ post_id: postId }),
        });
        if (!res.ok) {
          const data = await res.json();
          alert(data.error || 'Не удалось одобрить пост');
          approveBtn.disabled = false;
          return;
        }
        removeCard(postId);
      } catch (err) {
        alert('Ошибка сети');
        approveBtn.disabled = false;
      }
    }

    if (rejectBtn) {
      const postId = rejectBtn.dataset.postId;
      const reason = prompt('Причина отклонения (необязательно):', '') || '';

      rejectBtn.disabled = true;
      try {
        const res = await fetch('api/moderation_reject.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ post_id: postId, reason }),
        });
        if (!res.ok) {
          const data = await res.json();
          alert(data.error || 'Не удалось отклонить пост');
          rejectBtn.disabled = false;
          return;
        }
        removeCard(postId);
      } catch (err) {
        alert('Ошибка сети');
        rejectBtn.disabled = false;
      }
    }
  });

  loadQueue();
})();
</script>
