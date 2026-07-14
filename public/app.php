<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /login.html');
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>БоберЧат</title>
<link rel="stylesheet" href="assets/style.css?v=7">
<style>
  #appContent {
    min-height: calc(100vh - 76px);
    animation: fadeIn 0.15s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>
</head>
<body>

<div id="appContent">
  <div class="empty-state">Загрузка…</div>
</div>

<nav class="bottom-nav">
  <a href="#feed" class="bottom-nav-item" data-route="feed">
    <span class="icon">🏠</span>
    <span>Лента</span>
  </a>
  <a href="#chats" class="bottom-nav-item" data-route="chats">
    <span class="icon">💬</span>
    <span>Чат</span>
  </a>
  <a href="#profile" class="bottom-nav-item" data-route="profile">
    <span class="icon">👤</span>
    <span>Профиль</span>
  </a>
  <a href="#moderation" class="bottom-nav-item" data-route="moderation" id="moderationNavItem" style="display:none;">
    <span class="icon">🛡️</span>
    <span>Модерация</span>
  </a>
</nav>

<script>
(function () {
  const appContent = document.getElementById('appContent');
  const navItems = document.querySelectorAll('.bottom-nav-item');
  const moderationNavItem = document.getElementById('moderationNavItem');

  const routes = {
    feed: 'views/feed.php',
    chats: 'views/chats.php',
    profile: 'views/profile.php',
    moderation: 'views/moderation.php',
  };

  async function loadRoute(route) {
    if (!routes[route]) route = 'feed';

    // Подсветка активного пункта меню
    navItems.forEach(item => {
      item.classList.toggle('active', item.dataset.route === route);
    });

    appContent.style.opacity = '0';

    try {
      const res = await fetch(routes[route], { cache: 'no-store' });
      if (!res.ok) throw new Error('view not found');
      const html = await res.text();

      appContent.innerHTML = html;

      // Скрипты, вставленные через innerHTML, не выполняются сами по себе —
      // пересоздаём их вручную, чтобы JS каждого фрагмента реально запускался
      appContent.querySelectorAll('script').forEach(oldScript => {
        const newScript = document.createElement('script');
        newScript.textContent = oldScript.textContent;
        oldScript.replaceWith(newScript);
      });

      appContent.style.opacity = '1';
    } catch (err) {
      appContent.innerHTML = '<div class="empty-state">Не удалось загрузить раздел</div>';
    }
  }

  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const route = item.dataset.route;
      window.location.hash = route;
    });
  });

  window.addEventListener('hashchange', () => {
    const route = window.location.hash.replace('#', '') || 'feed';
    loadRoute(route);
  });

  // Проверка авторизации перед первой загрузкой
  fetch('api/me.php', { cache: 'no-store' })
    .then(res => {
      if (!res.ok) throw new Error('not authorized');
      return res.json();
    })
    .then(user => {
      if (user.role === 'moderator' || user.role === 'admin') {
        moderationNavItem.style.display = '';
      }
      const initialRoute = window.location.hash.replace('#', '') || 'feed';
      loadRoute(initialRoute);
    })
    .catch(() => {
      window.location.href = 'login.html';
    });
})();
</script>

</body>
</html>
