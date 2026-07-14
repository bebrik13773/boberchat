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
    const avatarSrc = profile.avatar_path || '';
    const roleLabel = profile.role === 'admin'
      ? '<span class="badge badge-approved">Admin</span>'
      : profile.role === 'moderator'
        ? '<span class="badge badge-approved">Модератор</span>'
        : '';

    container.innerHTML = `
      ${renderPendingNote(profile)}
      <div class="card" style="text-align:center; margin-bottom:14px;">
        <img class="avatar avatar-lg" src="${avatarSrc}" alt="" style="margin: 0 auto 12px;">
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
    `;

    document.getElementById('editProfileBtn').addEventListener('click', () => {
      alert('Редактирование профиля появится на следующем шаге');
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
