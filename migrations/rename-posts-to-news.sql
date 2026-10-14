-- Rename "posts" to "news", and add the new Tags column.
--
-- FOR A SITE THAT IS ALREADY LIVE. A fresh install uses database.sql instead.
--
-- Run this ONCE. Back up first: phpMyAdmin > Export (the database), and copy
-- the public_html folder from cPanel File Manager (the files and the images).
--
-- ORDER MATTERS:
--   1. Back up the database and the files.
--   2. Run this whole file in phpMyAdmin.
--   3. Upload the updated files from public/ to the web root (public_html).
--   4. If the site had images, do the folder step at the bottom of this file.
--   5. Deploy the updated website.
--
-- Steps 2 and 4 must both happen. Renaming the folder but not the table (or the
-- other way round) leaves every existing news image showing a broken picture.


-- 1. The table itself.
RENAME TABLE posts TO news;


-- 2. The new Tags column. Existing rows get an empty value, which is fine:
--    a news item without tags simply shows no chips.
ALTER TABLE news
  ADD COLUMN tags VARCHAR(255) NOT NULL DEFAULT '' AFTER category;


-- 3. Point existing image paths at the new folder name.
--    Nothing happens if no image was ever uploaded.
UPDATE news
   SET image = REPLACE(image, 'uploads/posts/', 'uploads/news/')
 WHERE image LIKE 'uploads/posts/%';


-- 4. THE FOLDER (step 4 above — this is not SQL, it is a File Manager job).
--
--    In cPanel > File Manager, open public_html/uploads and rename the folder
--    "posts" to "news". The files inside do not need to change their names:
--    step 3 above already pointed the database at the new path.
--
--    If uploads/posts does not exist, there were no images — skip this.
--    If uploads/news does not exist yet, the admin will create it on the next
--    image upload.


-- ---------------------------------------------------------------------------
-- ROLLBACK — only if you must undo the whole change. Run in this order, and
-- rename the folder back from uploads/news to uploads/posts as well.
-- ---------------------------------------------------------------------------
-- UPDATE news
--    SET image = REPLACE(image, 'uploads/news/', 'uploads/posts/')
--  WHERE image LIKE 'uploads/news/%';
-- ALTER TABLE news DROP COLUMN tags;
-- RENAME TABLE news TO posts;
