-- ============================================
-- БоберЧат — схема базы данных v1
-- Кодировка: utf8mb4 (эмодзи, кириллица)
-- ============================================

SET NAMES utf8mb4;

-- ============================================
-- 2.1 Пользователи
-- ============================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- логин / вход
    username VARCHAR(32) NOT NULL UNIQUE,           -- англ+цифры+_, уникален, служит и логином
    email VARCHAR(255) NOT NULL UNIQUE,              -- только вход/восстановление, не публично
    password_hash VARCHAR(255) NOT NULL,

    -- профиль (approved-значения, то что реально показывается всем)
    display_name VARCHAR(64) NULL,                   -- имя, любые символы
    avatar_path VARCHAR(255) NULL,                    -- путь к одобренному фото
    bio TEXT NULL,                                    -- описание, одобренное

    -- pending-версии полей (ждут модерации; NULL = нет ожидающих изменений)
    pending_display_name VARCHAR(64) NULL,
    pending_avatar_path VARCHAR(255) NULL,
    pending_bio TEXT NULL,
    pending_username VARCHAR(32) NULL,

    -- статусы модерации по каждому полю: pending | approved | rejected
    username_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    display_name_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    avatar_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    bio_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',

    role ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2.2 Посты
-- ============================================
CREATE TABLE posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    text_content TEXT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(255) NULL,               -- см. раздел "идеи" в ТЗ
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,           -- закреп поста (идея из ТЗ)
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_posts_status (status),
    INDEX idx_posts_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2.3 Фото постов, лайки, комментарии
-- ============================================
CREATE TABLE post_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,

    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_like (post_id, user_id)            -- один лайк на юзера на пост
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    text_content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comments_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2.4 Чат
-- ============================================
CREATE TABLE chats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    is_group TINYINT(1) NOT NULL DEFAULT 0,            -- 0 = личка (1-на-1), 1 = групповой
    title VARCHAR(64) NULL,                             -- нужно только для групповых чатов
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chat_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_participant (chat_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,

    -- для группового/обычного чата — открытый текст
    -- для личного (is_group=0) чата — сюда пишется шифротекст (E2E, libsodium), сервер его не читает
    content TEXT NOT NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,

    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_chat (chat_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2.5 Модерация: лог и кэш ранее одобренного
-- ============================================
CREATE TABLE moderation_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('post','profile_name','profile_username','profile_photo','profile_bio') NOT NULL,
    target_id INT UNSIGNED NOT NULL,                   -- id поста или id пользователя (в зависимости от target_type)
    moderator_id INT UNSIGNED NOT NULL,
    decision ENUM('approved','rejected') NOT NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_modlog_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE moderation_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_type ENUM('username','display_name','avatar_hash','bio') NOT NULL,
    normalized_value VARCHAR(255) NOT NULL,             -- для текста — сам текст/ник; для фото — sha256-хэш файла
    status ENUM('approved','rejected') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_cache_entry (field_type, normalized_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
