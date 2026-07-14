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

<div class="feed page-content">

  <div class="card" style="margin-bottom:14px;">
    <div class="field-label" style="margin-bottom:8px;">Написать кому-то новому</div>
    <div style="display:flex; gap:8px;">
      <input type="text" class="input" id="newChatUsername" placeholder="username собеседника">
      <button class="btn btn-primary" id="newChatBtn">Начать</button>
    </div>
  </div>

  <div id="chatsListContainer">
    <div class="empty-state">Загрузка чатов…</div>
  </div>

</div>

<script>
(function () {
  const container = document.getElementById('chatsListContainer');
  const newChatUsername = document.getElementById('newChatUsername');
  const newChatBtn = document.getElementById('newChatBtn');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function renderAvatar(avatarPath, sizeClass) {
    if (avatarPath) {
      return `<img class="avatar ${sizeClass}" src="${avatarPath}" alt="">`;
    }
    return `<div class="avatar ${sizeClass} avatar-placeholder">🦫</div>`;
  }

  function timeAgo(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString.replace(' ', 'T') + 'Z');
    const diffSec = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diffSec < 60) return 'только что';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + ' мин';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + ' ч';
    return date.toLocaleDateString('ru-RU');
  }

  function renderChatItem(chat) {
    const name = chat.other_user
      ? (chat.other_user.display_name || '@' + escapeHtml(chat.other_user.username))
      : (chat.title || 'Групповой чат');

    const avatarPath = chat.other_user && chat.other_user.avatar_path ? chat.other_user.avatar_path : '';
    const preview = chat.last_message ? escapeHtml(chat.last_message) : 'Нет сообщений';

    return `
      <a href="#chat-${chat.chat_id}" class="card chat-list-item" data-chat-id="${chat.chat_id}" style="display:flex; gap:12px; align-items:center; margin-bottom:10px; text-decoration:none; color:inherit;">
        ${renderAvatar(avatarPath, 'avatar-md')}
        <div style="flex:1; min-width:0;">
          <div style="font-weight:600; color:var(--text-primary);">${name}</div>
          <div style="font-size:13px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${preview}</div>
        </div>
        <div style="font-size:12px; color:var(--text-muted); white-space:nowrap;">${timeAgo(chat.last_message_at)}</div>
      </a>
    `;
  }

  async function loadChats() {
    try {
      const res = await fetch('api/chats_list.php', { cache: 'no-store' });
      if (!res.ok) throw new Error('error');
      const data = await res.json();

      if (data.chats.length === 0) {
        container.innerHTML = '<div class="empty-state">Пока нет чатов. Напиши кому-нибудь!</div>';
        return;
      }
      container.innerHTML = data.chats.map(renderChatItem).join('');
    } catch (err) {
      container.innerHTML = '<div class="empty-state">Не удалось загрузить чаты</div>';
    }
  }

  newChatBtn.addEventListener('click', async () => {
    const username = newChatUsername.value.trim();
    if (!username) return;

    newChatBtn.disabled = true;
    try {
      const res = await fetch('api/get_or_create_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username }),
      });
      const data = await res.json();

      if (!res.ok) {
        alert(data.error || 'Не удалось открыть чат');
        return;
      }

      window.location.hash = 'chat-' + data.chat_id;
    } catch (err) {
      alert('Ошибка сети');
    } finally {
      newChatBtn.disabled = false;
    }
  });

  loadChats();
})();
</script>
