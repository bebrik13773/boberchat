<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<div class="page-header">👤 Профиль</div>

<div class="feed page-content">
  <div id="profileContainer">
    <div class="empty-state">Загрузка профиля…</div>
  </div>
</div>

<script>
(function () {
  const container = document.getElementById('profileContainer');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function fieldOrPlaceholder(value) {
    return value
      ? escapeHtml(value)
      : '<span class="moderation-placeholder">Загрузка…</span>';
  }

  function renderAvatar(avatarPath, sizeClass) {
    if (avatarPath) {
      return `<img class="avatar ${sizeClass}" src="${avatarPath}" alt="" style="margin: 0 auto 12px;">`;
    }
    return `<div class="avatar ${sizeClass} avatar-placeholder" style="margin: 0 auto 12px;">🦫</div>`;
  }

  function renderPendingNote(profile) {
    if (!profile.pending) return '';

    const pendingLabels = [];
    if (profile.pending.display_name) pendingLabels.push('имя');
    if (profile.pending.username) pendingLabels.push('username');
    if (profile.pending.avatar_path) pendingLabels.push('фото');
    if (profile.pending.bio) pendingLabels.push('описание');

    if (pendingLabels.length === 0) return '';

    return `
      <div class="card" style="margin-bottom:14px;">
        <span class="badge badge-pending">На модерации</span>
        <p style="margin-top:8px; margin-bottom:0;">
          Ждут проверки: ${pendingLabels.join(', ')}
        </p>
      </div>
    `;
  }

  function render(profile) {
    const roleLabel = profile.role === 'admin'
      ? '<span class="badge badge-approved">Admin</span>'
      : profile.role === 'moderator'
        ? '<span class="badge badge-approved">Модератор</span>'
        : '';

    container.innerHTML = `
      ${renderPendingNote(profile)}
      <div class="card" style="text-align:center; margin-bottom:14px;">
        ${renderAvatar(profile.avatar_path, 'avatar-lg')}
        <h2 style="margin-bottom:4px;">${fieldOrPlaceholder(profile.display_name)}</h2>
        <p style="margin-bottom:8px; color: var(--text-muted);">
          @${profile.username ? escapeHtml(profile.username) : '...'}
          ${roleLabel}
        </p>
        <p style="margin-bottom:14px;">${profile.bio ? escapeHtml(profile.bio) : '<span class="moderation-placeholder">Описание не заполнено</span>'}</p>
        <p style="margin-bottom:16px; color: var(--text-muted); font-size:13px;">
          Постов: ${profile.posts_count}
        </p>
        <button class="btn btn-secondary" id="editProfileBtn">Редактировать профиль</button>
      </div>

      <div class="card" id="editForm" style="display:none; margin-bottom:14px;">
        <div class="auth-field" style="margin-bottom:14px;">
          <label class="field-label">Имя</label>
          <input type="text" class="input" id="editDisplayName" value="${profile.display_name ? escapeHtml(profile.display_name) : ''}">
        </div>
        <div class="auth-field" style="margin-bottom:14px;">
          <label class="field-label">Username</label>
          <input type="text" class="input" id="editUsername" value="${profile.username ? escapeHtml(profile.username) : ''}">
        </div>
        <div class="auth-field" style="margin-bottom:14px;">
          <label class="field-label">О себе</label>
          <textarea class="textarea" id="editBio">${profile.bio ? escapeHtml(profile.bio) : ''}</textarea>
        </div>
        <div style="display:flex; gap:10px;">
          <button class="btn btn-ghost" id="cancelEditBtn" style="flex:1;">Отмена</button>
          <button class="btn btn-primary" id="saveProfileBtn" style="flex:1;">Сохранить</button>
        </div>
      </div>
    `;

    const editForm = document.getElementById('editForm');

    document.getElementById('editProfileBtn').addEventListener('click', () => {
      editForm.style.display = editForm.style.display === 'none' ? '' : 'none';
    });

    document.getElementById('cancelEditBtn').addEventListener('click', () => {
      editForm.style.display = 'none';
    });

    document.getElementById('saveProfileBtn').addEventListener('click', async (e) => {
      const btn = e.target;
      const originalLabel = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Сохраняем…';

      try {
        const res = await fetch('api/update_profile.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            display_name: document.getElementById('editDisplayName').value.trim(),
            username: document.getElementById('editUsername').value.trim(),
            bio: document.getElementById('editBio').value.trim(),
          }),
        });
        const data = await res.json();

        if (!res.ok) {
          alert(data.error || 'Не удалось сохранить');
          return;
        }

        await loadProfile();
      } catch (err) {
        alert('Ошибка сети');
      } finally {
        btn.disabled = false;
        btn.textContent = originalLabel;
      }
    });
  }

  async function loadProfile() {
    try {
      const meRes = await fetch('api/me.php', { cache: 'no-store' });
      if (!meRes.ok) throw new Error('not authorized');
      const me = await meRes.json();

      const res = await fetch('api/profile.php?username=' + encodeURIComponent(me.username), { cache: 'no-store' });
      if (!res.ok) throw new Error('profile error');
      const data = await res.json();

      render(data.profile);
    } catch (err) {
      container.innerHTML = '<div class="empty-state">Не удалось загрузить профиль</div>';
    }
  }

  loadProfile();
})();
</script>
