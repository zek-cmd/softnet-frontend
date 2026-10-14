<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sanitize.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

/**
 * The old posts feed, kept alive so an already-deployed copy of the Nuxt site
 * keeps working after the rename.
 *
 * It returns the SAME shape as before — each item still carries "id", "title",
 * "summary", "category", "image", "created_at" and "date_formatted" — plus the
 * new "tags" and "url". The extra fields are ignored by the old site.
 *
 * The file name no longer matches the data inside it; that is on purpose.
 * Once the updated site is deployed this can be deleted. See README.md.
 */
require __DIR__ . '/news.php';
