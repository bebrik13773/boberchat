<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<div class="page-header">🦫 БоберЧат</div>

<div class="feed page-content">

  <!-- Форма создания поста -->
  <div class="card composer">
    <textarea class="textarea" id="postText" placeholder="Что нового, Бобёр?"></textarea>

    <div class="composer-photo-preview" id="photoPreview">
      <img id="photoPreviewImg" src="" alt="">
      <button class="composer-photo-remove" id="photoRemoveBtn">✕</button>
    </div>

    <div class="composer-footer">
      <button class="composer-photo-btn" id="photoPickBtn">📷 Фото</button>
      <input type="file" id="photoInput" accept="image/*" style="display:none;">
      <button class="btn btn-primary" id="postSubmit">Опубликовать</button>
    </div>
  </div>

  <div id="postsContainer">
    <div class="empty-state">Загрузка ленты…</div>
  </div>

</div>

<script>
(function () {
  const postsContainer = document.getElementById('postsContainer');
  const postText = document.getElementById('postText');
  const postSubmit = document.getElementById('postSubmit');
  const photoPickBtn = document.getElementById('photoPickBtn');
  const photoInput = document.getElementById('photoInput');
  const photoPreview = document.getElementById('photoPreview');
  const photoPreviewImg = document.getElementById('photoPreviewImg');
  const photoRemoveBtn = document.getElementById('photoRemoveBtn');

  let selectedPhotoFile = null;

  photoPickBtn.addEventListener('click', () => photoInput.click());

  photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) return;
    selectedPhotoFile = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      photoPreviewImg.src = e.target.result;
      photoPreview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });

  photoRemoveBtn.addEventListener('click', () => {
    selectedPhotoFile = null;
    photoInput.value = '';
    photoPreview.style.display = 'none';
    photoPreviewImg.src = '';
  });

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

  function renderPost(post) {
    const authorName = post.author.display_name || (post.is_mine ? 'Ты' : null);
    const nameHtml = authorName
      ? escapeHtml(authorName)
      : '<span class="moderation-placeholder">Загрузка…</span>';

    const avatarSrc = post.author.avatar_path || '';

    let statusBadge = '';
    if (post.status === 'pending') {
      statusBadge = '<span class="badge badge-pending">На модерации</span>';
    } else if (post.status === 'rejected') {
      statusBadge = '<span class="badge badge-rejected">Отклонён</span>';
    }

    const metaHtml = statusBadge || timeAgo(post.created_at);
    const likedClass = post.liked_by_me ? 'liked' : '';

    const imageHtml = post.image_path
      ? `<img class="post-image" src="${post.image_path}" alt="">`
      : '';

    return `
      <div class="card post-card" data-post-id="${post.id}">
        <div class="post-header">
          <img class="avatar avatar-md" src="${avatarSrc}" alt="">
          <div class="names">
            <span class="post-author-name">${nameHtml}</span>
            <span class="post-meta">${metaHtml}</span>
          </div>
        </div>
        <div class="post-text">${escapeHtml(post.text)}</div>
        ${imageHtml}
        <div class="post-actions">
          <button class="post-action like-btn ${likedClass}">❤ ${post.like_count}</button>
          <button class="post-action">💬 ${post.comment_count}</button>
        </div>
      </div>
    `;
  }

  async function loadFeed() {
    try {
      const res = await fetch('api/feed.php');
      if (!res.ok) throw new Error('feed error');
      const data = await res.json();

      if (data.posts.length === 0) {
        postsContainer.innerHTML = '<div class="empty-state">Пока пусто. Напиши первый пост!</div>';
        return;
      }
      postsContainer.innerHTML = data.posts.map(renderPost).join('');
    } catch (err) {
      postsContainer.innerHTML = '<div class="empty-state">Не удалось загрузить ленту</div>';
    }
  }

  postSubmit.addEventListener('click', async () => {
    const text = postText.value.trim();
    if (!text && !selectedPhotoFile) return;

    postSubmit.disabled = true;
    try {
      let imagePath = '';

      if (selectedPhotoFile) {
        const formData = new FormData();
        formData.append('photo', selectedPhotoFile);

        const uploadRes = await fetch('api/upload_photo.php', {
          method: 'POST',
          body: formData,
        });
        const uploadData = await uploadRes.json();

        if (!uploadRes.ok) {
          alert(uploadData.error || 'Не удалось загрузить фото');
          return;
        }
        imagePath = uploadData.path;
      }

      const res = await fetch('api/create_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text, image_path: imagePath }),
      });
      const data = await res.json();

      if (!res.ok) {
        alert(data.error || 'Не удалось опубликовать пост');
        return;
      }

      postText.value = '';
      photoRemoveBtn.click(); // сбрасываем выбранное фото и превью
      loadFeed();
    } catch (err) {
      alert('Ошибка сети');
    } finally {
      postSubmit.disabled = false;
    }
  });

  loadFeed();
})();
</script>
