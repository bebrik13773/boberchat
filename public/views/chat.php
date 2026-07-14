<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$chatId = (int)($_GET['id'] ?? 0);
if ($chatId <= 0) {
    echo '<div class="page-header">Чат</div><div class="page-content" style="padding:0 16px;"><div class="empty-state">Некорректный чат</div></div>';
    exit;
}
?>
<div class="page-header">
  <a href="#chats" class="btn btn-ghost" style="padding:6px 10px;" id="backToChatsBtn">←</a>
  <span id="chatPartnerName">Чат</span>
</div>

<div class="page-content" style="display:flex; flex-direction:column; height: calc(100vh - 76px - 56px);">
  <div id="messagesContainer" style="flex:1; overflow-y:auto; padding: 0 16px;">
    <div class="empty-state">Загрузка сообщений…</div>
  </div>

  <div style="display:flex; gap:8px; padding: 10px 16px; border-top:1px solid var(--border-soft);">
    <input type="text" class="input" id="messageInput" placeholder="Написать сообщение…">
    <button class="btn btn-primary" id="sendMessageBtn">➤</button>
  </div>
</div>

<style>
  .message-row {
    display: flex;
    margin-bottom: 8px;
  }
  .message-row.mine {
    justify-content: flex-end;
  }
  .message-bubble {
    max-width: 75%;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 14px;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .message-row.mine .message-bubble {
    background: var(--accent);
    color: var(--text-on-accent);
  }
</style>

<script>
(function () {
  const chatId = <?php echo json_encode($chatId); ?>;
  const messagesContainer = document.getElementById('messagesContainer');
  const messageInput = document.getElementById('messageInput');
  const sendBtn = document.getElementById('sendMessageBtn');
  const partnerNameEl = document.getElementById('chatPartnerName');
  const backBtn = document.getElementById('backToChatsBtn');

  let lastMessageId = 0;
  let pollTimer = null;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function renderMessage(msg) {
    const rowClass = msg.is_mine ? 'message-row mine' : 'message-row';
    return `<div class="${rowClass}"><div class="message-bubble">${escapeHtml(msg.content)}</div></div>`;
  }

  function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }

  async function loadInitialMessages() {
    try {
      const res = await fetch(`api/get_messages.php?chat_id=${chatId}&after_id=0`, { cache: 'no-store' });
      if (!res.ok) throw new Error('error');
      const data = await res.json();

      if (data.messages.length === 0) {
        messagesContainer.innerHTML = '<div class="empty-state">Пока нет сообщений. Напиши первым!</div>';
      } else {
        messagesContainer.innerHTML = data.messages.map(renderMessage).join('');
        lastMessageId = data.messages[data.messages.length - 1].id;
        scrollToBottom();
      }
    } catch (err) {
      messagesContainer.innerHTML = '<div class="empty-state">Не удалось загрузить сообщения</div>';
    }
  }

  async function pollNewMessages() {
    try {
      const res = await fetch(`api/get_messages.php?chat_id=${chatId}&after_id=${lastMessageId}`, { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();

      if (data.messages.length > 0) {
        const emptyState = messagesContainer.querySelector('.empty-state');
        if (emptyState) messagesContainer.innerHTML = '';

        data.messages.forEach(msg => {
          messagesContainer.insertAdjacentHTML('beforeend', renderMessage(msg));
        });
        lastMessageId = data.messages[data.messages.length - 1].id;
        scrollToBottom();
      }
    } catch (err) {
      // тихо игнорируем ошибки поллинга
    }
  }

  async function sendMessage() {
    const content = messageInput.value.trim();
    if (!content) return;

    sendBtn.disabled = true;
    try {
      const res = await fetch('api/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ chat_id: chatId, content }),
      });
      const data = await res.json();

      if (!res.ok) {
        alert(data.error || 'Не удалось отправить сообщение');
        return;
      }

      messageInput.value = '';
      await pollNewMessages();
    } catch (err) {
      alert('Ошибка сети');
    } finally {
      sendBtn.disabled = false;
    }
  }

  sendBtn.addEventListener('click', sendMessage);
  messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') sendMessage();
  });

  backBtn.addEventListener('click', (e) => {
    e.preventDefault();
    window.location.hash = 'chats';
  });

  fetch('api/chats_list.php', { cache: 'no-store' })
    .then(res => res.json())
    .then(data => {
      const chat = data.chats.find(c => c.chat_id === chatId);
      if (chat && chat.other_user) {
        partnerNameEl.textContent = chat.other_user.display_name || '@' + chat.other_user.username;
      }
    });

  loadInitialMessages();

  pollTimer = setInterval(pollNewMessages, 3000);

  window.addEventListener('hashchange', () => {
    if (pollTimer) clearInterval(pollTimer);
  }, { once: true });
})();
</script>
