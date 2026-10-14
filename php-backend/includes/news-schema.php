<?php

/**
 * Does the database have this feature yet?
 *
 * The gallery and the readable addresses ("slugs") both need changes to the
 * database, and those changes are in migrations/add-gallery-and-slugs.sql —
 * a file that has to be run by hand in phpMyAdmin.
 *
 * Until it is run, the site must keep working exactly as before. So every
 * feature asks here first instead of assuming the change is in place. A missing
 * column or table would otherwise stop the page with a database error.
 *
 * The answers are remembered for the rest of the request, so a page that asks
 * five times still runs one check.
 */

/**
 * Is this column on the news table? ("slug")
 */
function newsColumnExists(PDO $pdo, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    // A one-row read is the surest test: if the column is missing MySQL raises
    // an error, and if it is there the query is instant on an empty table.
    try {
        $pdo->query('SELECT ' . newsSchemaQuoteName($column) . ' FROM news LIMIT 1');
        $cache[$column] = true;
    } catch (PDOException $e) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

/**
 * Is this table in the database? ("news_images")
 */
function newsTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . newsSchemaQuoteName($table) . ' LIMIT 1');
        $cache[$table] = true;
    } catch (PDOException $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

/**
 * Can news items have readable addresses yet?
 */
function newsSlugReady(PDO $pdo): bool
{
    return newsColumnExists($pdo, 'slug');
}

/**
 * Are sub images available yet?
 */
function newsGalleryReady(PDO $pdo): bool
{
    return newsTableExists($pdo, 'news_images');
}

/**
 * Wrap a table or column name in backticks.
 *
 * The names passed in are hard-coded in this project, never read from a
 * request — this only keeps MySQL happy about reserved words.
 */
function newsSchemaQuoteName(string $name): string
{
    return '`' . str_replace('`', '', $name) . '`';
}
