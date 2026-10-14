-- SOFTNET Admin + News Database
-- FRESH INSTALL: run this once in phpMyAdmin on x10hosting.
-- Already running the older version with a "posts" table?
--   Do NOT run this file. Run migrations/rename-posts-to-news.sql instead.

CREATE TABLE IF NOT EXISTS news (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  summary VARCHAR(500) DEFAULT '',
  content TEXT NOT NULL,
  category VARCHAR(100) DEFAULT 'General',
  tags VARCHAR(255) DEFAULT '',
  image VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Default login: admin / softnet2024
-- CHANGE IT on the Settings page (Admin > Settings) the first time you sign in.
INSERT INTO admin_users (username, password_hash) VALUES (
  'admin',
  '$2a$12$8868i6.M6t5eixTJSW1Rh.hgX1ePbo0K9ZfKfwIJeKOhL9S/Mm7qG'
);
