<?php
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/tags.php';
require_once __DIR__ . '/news-schema.php';

/**
 * Reading news from the database.
 *
 * The public list, the tag filter and the JSON feed all need the same two
 * things: a search filter and a tag filter. They are built here once, so the
 * page and the API can never drift apart — and so every value the reader
 * typed is bound as a parameter instead of pasted into the SQL.
 */

/**
 * Escape the wildcards in a LIKE pattern.
 *
 * Without this, typing "%" in the search box would match every news item.
 * A backslash is MySQL's own escape character inside LIKE, so escaping the
 * wildcards here is enough and no ESCAPE clause is needed.
 */
function newsLikeEscape(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

/**
 * The WHERE clause and its bound values for a search word and a tag.
 *
 * @return array{0:string,1:array} SQL fragment (starting with WHERE, or empty)
 *                                 and the named parameters.
 */
function newsFilterSql(string $search, string $tag): array
{
    $clauses = [];
    $params = [];

    if ($search !== '') {
        $needle = '%' . newsLikeEscape($search) . '%';
        $clauses[] = '(title LIKE :s1 OR summary LIKE :s2 OR category LIKE :s3 OR tags LIKE :s4)';
        $params[':s1'] = $needle;
        $params[':s2'] = $needle;
        $params[':s3'] = $needle;
        $params[':s4'] = $needle;
    }

    if ($tag !== '') {
        // One tag inside the stored "a, b, c" line.
        //
        // Spaces are removed from both sides first, so a tag typed as
        // "Hotel and Restaurant" still matches "hotel and restaurant, tourism"
        // however it was spaced.
        //
        // The wrapping commas make "Art" match ",Art,Sports," but NOT
        // "Culinary Arts" — tags match whole, never as a piece of another tag.
        $clauses[] = "CONCAT(',', REPLACE(tags, ' ', ''), ',') LIKE :tag";
        $params[':tag'] = '%' . newsLikeEscape(',' . str_replace(' ', '', $tag)) . ',%';
    }

    return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
}

/**
 * One page of news, newest first.
 *
 * @return array{rows:array,total:int,pages:int,page:int}
 */
function newsFetchPage(PDO $pdo, array $options = []): array
{
    $perPage = max(1, min((int) ($options['perPage'] ?? 9), 50));
    $page = max(1, (int) ($options['page'] ?? 1));
    $search = trim((string) ($options['search'] ?? ''));
    $tag = trim((string) ($options['tag'] ?? ''));

    // "newest" is the normal list; "oldest" is available for archive pages.
    $order = ($options['order'] ?? 'newest') === 'oldest' ? 'ASC' : 'DESC';

    [$where, $params] = newsFilterSql($search, $tag);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM news $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    // "slug" only exists after migrations/add-gallery-and-slugs.sql has been
    // run. Asking first keeps an unmigrated database working: the column is
    // simply left out of the query and every link falls back to /news/12.
    $columns = 'id, title, summary, category, tags, image, created_at';
    if (newsSlugReady($pdo)) {
        $columns .= ', slug';
    }

    // $perPage, $offset and $order are values this function worked out itself,
    // never anything that came from the browser. $columns is built above from
    // fixed names, never from a request.
    $stmt = $pdo->prepare(
        "SELECT $columns
           FROM news $where
          ORDER BY created_at $order, id $order
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/**
 * Every tag in use, with how many news items carry it, busiest first.
 *
 * Counted from the stored tag lines, so the filter row always matches what is
 * actually published. Counting is case-insensitive; the newer spelling is the
 * one shown.
 *
 * @return array<int,array{tag:string,count:int}>
 */
function newsTagCounts(PDO $pdo, int $limit = 20): array
{
    $stmt = $pdo->query("SELECT tags FROM news WHERE tags <> ''");
    $counts = [];

    foreach ($stmt->fetchAll() as $row) {
        foreach (newsTagList($row['tags']) as $tag) {
            $key = function_exists('mb_strtolower') ? mb_strtolower($tag) : strtolower($tag);
            if (!isset($counts[$key])) {
                $counts[$key] = ['tag' => $tag, 'count' => 0];
            }
            $counts[$key]['count']++;
        }
    }

    usort($counts, function ($a, $b) {
        return $b['count'] <=> $a['count'] ?: strcmp($a['tag'], $b['tag']);
    });

    return array_slice($counts, 0, $limit);
}

/**
 * A link to one page of results with the current filters kept.
 *
 * $base is '/news' for the public list and '/admin' for the dashboard.
 */
function newsPageUrl(int $page, string $search = '', string $tag = '', string $base = '/news'): string
{
    $query = [];
    if ($search !== '') {
        $query['search'] = $search;
    }
    if ($tag !== '') {
        $query['tag'] = $tag;
    }
    if ($page > 1) {
        $query['page'] = $page;
    }

    $queryString = http_build_query($query);

    return $base . ($queryString !== '' ? '?' . $queryString : '');
}
