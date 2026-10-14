-- SOFTNET news: sub images (gallery) + readable web addresses (slugs)
-- ---------------------------------------------------------------------------
-- Run this ONCE in phpMyAdmin, AFTER migrations/rename-posts-to-news.sql.
--
-- What it does
--   1. Adds a "slug" column to news, so an item can live at
--      /news/softnet-scholarship-2026 instead of /news/12.
--   2. Creates the news_images table, which holds the sub images of each
--      news item (the gallery shown under the cover photo).
--
-- Safe to skip for now: both features are optional at runtime. Until this
-- file is run the site keeps working exactly as it does today, using the
-- numeric addresses and showing no gallery. Nothing breaks either way.
--
-- ALREADY RUN IT ONCE? Do NOT run it again — ALTER TABLE would stop on the
-- duplicate column. Nothing bad happens, but nothing changes either.
-- ---------------------------------------------------------------------------


-- 1. Web addresses -----------------------------------------------------------
-- The column is allowed to be empty (NULL) on purpose. A UNIQUE index treats
-- empty values as "no value yet", so many news items can wait for their slug
-- at the same time. Each one gets its slug the first time it is opened, or the
-- next time it is saved in the admin.

ALTER TABLE news
  ADD COLUMN slug VARCHAR(100) DEFAULT NULL AFTER title;

ALTER TABLE news
  ADD UNIQUE KEY uniq_news_slug (slug);


-- 2. Sub images --------------------------------------------------------------
-- One row per sub image. "sort_order" keeps the order the admin chose, and
-- "caption" is the short description shown under the image and used as its
-- alt text (the words a screen reader reads out).
--
-- The film strip is attached to its news item by news_id. There is no
-- database-level foreign key on purpose: creating one needs an extra MySQL
-- privilege that some shared hosts do not give, and the admin already deletes
-- the sub images itself, in code, whenever a news item is deleted.

CREATE TABLE IF NOT EXISTS news_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  news_id INT NOT NULL,
  image VARCHAR(255) NOT NULL,
  caption VARCHAR(255) DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_news_images_news (news_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Check it worked:
--   SHOW COLUMNS FROM news LIKE 'slug';
--   SELECT * FROM news_images LIMIT 5;
--
-- Nothing to copy across: existing news items simply have an empty slug and
-- no sub images. Both fill in as you use the site.
-- ---------------------------------------------------------------------------
