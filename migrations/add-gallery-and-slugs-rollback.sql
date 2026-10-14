-- Undo migrations/add-gallery-and-slugs.sql
-- ---------------------------------------------------------------------------
-- Run this only if you want to go back to numeric news addresses and remove
-- the gallery feature entirely.
--
-- THIS DELETES DATA: every sub image row is destroyed, and every slug is
-- thrown away. The sub image FILES stay on the server in uploads/news/ — the
-- database only forgets which news item they belong to. Copy that folder
-- somewhere safe first if you might want it back.
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS news_images;

ALTER TABLE news DROP INDEX uniq_news_slug;

ALTER TABLE news DROP COLUMN slug;

-- Afterwards the site falls back to /news/12 style addresses on its own.
