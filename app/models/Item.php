<?php
// [M2] Item model: SQL for the feed, search, My Reports and item details.
// Every function takes the PDO connection and uses prepared statements only.
declare(strict_types=1);

require_once __DIR__ . '/../helpers/search.php';

function item_feed(PDO $pdo, array $f = []): array
{
    $where  = ['v.status = ?'];
    $params = ['Approved'];

    $words = search_keywords((string)($f['q'] ?? ''));
    foreach ($words as $w) {
        $ors = [];
        foreach (search_expand($w) as $syn) {
            $ors[] = '(v.item_name LIKE ? OR v.description LIKE ? OR v.location LIKE ?)';
            array_push($params, search_like($syn), search_like($syn), search_like($syn));
        }
        $where[] = '(' . implode(' OR ', $ors) . ')';
    }
    if (!empty($f['category_id'])) { $where[] = 'v.category_id = ?'; $params[] = (int)$f['category_id']; }
    if (!empty($f['location_id'])) { $where[] = 'v.location_id = ?'; $params[] = (int)$f['location_id']; }
    if (in_array($f['type'] ?? '', ['Lost', 'Found'], true)) { $where[] = 'v.item_type = ?'; $params[] = $f['type']; }

    $select = 'v.*';
    $order  = 'v.created_at DESC, v.item_id DESC';
    $lead   = [];
    if ($words) {
        $terms = [];
        foreach ($words as $w) { $terms = array_merge($terms, search_expand($w)); }
        
        $select = 'v.*, (SELECT MATCH(t.item_name, t.description) AGAINST (? IN NATURAL LANGUAGE MODE)
                         FROM items t WHERE t.item_id = v.item_id) AS relevance';
        $lead   = [implode(' ', array_unique($terms))];
        $order  = 'relevance DESC, v.created_at DESC';
    }

    $st = $pdo->prepare("SELECT $select FROM v_approved_feed v WHERE " . implode(' AND ', $where) . " ORDER BY $order");
    $st->execute(array_merge($lead, $params));
    return $st->fetchAll();
}

/* Every report one student submitted, newest first, with its current status. */
function item_my_reports(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        'SELECT i.item_id, i.item_type, i.item_name, i.event_date, i.status,
                i.review_remarks, c.category_name
         FROM items i
         JOIN categories c ON c.category_id = i.category_id
         WHERE i.user_id = ?
         ORDER BY i.created_at DESC, i.item_id DESC'
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}
/* One item with names, for the details page. */
function item_find(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        "SELECT i.*, c.category_name,
            COALESCE(u.full_name, 'Unknown') AS reporter_name,
            CASE WHEN l.location_name = 'Other' THEN COALESCE(i.custom_location, 'Other')
                ELSE CONCAT_WS(' - ', l.location_name, i.custom_location) END AS location
        FROM items i
        JOIN categories c ON c.category_id = i.category_id
        JOIN locations  l ON l.location_id = i.location_id
        LEFT JOIN users u ON u.user_id = i.user_id
        WHERE i.item_id = ?"
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}