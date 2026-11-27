<?php

declare(strict_types=1);

/**
 * ByteBack [M4] Match model. Prepared statements only.
 * Table: matches (match_id, lost_item_id, found_item_id, is_confirmed, confirmed_by, created_at).
 *
 * Suggestions are computed on demand from approved Lost and Found items using
 * matching_score() in app/helpers/matching.php. A row is only written to `matches`
 * when an admin confirms a pair (item_match_confirm) or when item_match_create() is called.
 *
 * Throws RuntimeException with a message that is safe to show in a flash message.
 *
 * Load it with: require_once __DIR__ . '/../../app/models/itemMatch.php';
 * (the page or handler must already have loaded app/auth/guard.php).
 */

require_once __DIR__ . '/../helpers/matching.php';

/**
 * Shared SELECT list and joins for item rows used by matching.
 * location_name here is the location plus any custom text, so matching can compare places.
 */
function item_match_item_select(): string
{
    return "SELECT i.item_id,
                   i.user_id,
                   i.item_type,
                   i.item_name,
                   i.description,
                   i.category_id,
                   i.location_id,
                   i.event_date,
                   i.photo_path,
                   i.status,
                   i.created_at,
                   u.full_name AS reporter_name,
                   c.category_name,
                   CONCAT_WS(' ', l.location_name, i.custom_location) AS location_name,
                   CASE
                       WHEN l.location_name = 'Other'
                           THEN COALESCE(i.custom_location, 'Other')
                       ELSE CONCAT_WS(' - ', l.location_name, i.custom_location)
                   END AS location
            FROM items i
            JOIN users u ON i.user_id = u.user_id
            JOIN categories c ON i.category_id = c.category_id
            JOIN locations l ON i.location_id = l.location_id";
}

function item_match_student_candidates(int $lostItemId): array
{
    $select = str_replace(
        'SELECT i.item_id,',
        'SELECT m.is_confirmed AS match_confirmed, i.item_id,',
        item_match_item_select()
    );

    $sql = $select . '
        INNER JOIN matches m ON m.found_item_id = i.item_id
        WHERE m.lost_item_id = :lost_id
          AND i.item_type = :item_type
          AND (m.is_confirmed = 1 OR i.status = :status)
        ORDER BY m.is_confirmed DESC, m.created_at DESC
    ';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        'lost_id'   => $lostItemId,
        'item_type' => ITEM_TYPE_FOUND,
        'status'    => ITEM_STATUS_APPROVED,
    ]);

    $results = [];

    foreach ($stmt->fetchAll() as $row) {
        $confirmed = (int) $row['match_confirmed'] === 1;

        $results[] = [
            'item'      => $row,
            'confirmed' => $confirmed,
            'label'     => $confirmed
                ? 'Confirmed by an administrator.'
                : 'Suggested match awaiting administrator confirmation.',
        ];
    }

    if ($results && $results[0]['confirmed']) {
        $results = array_values(array_filter($results, static fn (array $m): bool => $m['confirmed']));
    }

    return $results;
}

/**
 * Find one item (any status) with the columns matching needs.
 *
 * @return array<string, mixed>|null
 */
function item_match_item_find(int $itemId): ?array
{
    $stmt = db()->prepare(item_match_item_select() . ' WHERE i.item_id = :item_id LIMIT 1');
    $stmt->execute(['item_id' => $itemId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * All Approved items of one type, oldest report first.
 *
 * @return list<array<string, mixed>>
 */
function item_match_items_available(string $itemType): array
{
    if ($itemType !== ITEM_TYPE_LOST && $itemType !== ITEM_TYPE_FOUND) {
        throw new InvalidArgumentException('Invalid item type.');
    }

    $stmt = db()->prepare(
        item_match_item_select() . ' WHERE i.item_type = :item_type AND i.status = :status ORDER BY i.created_at ASC, i.item_id ASC'
    );
    $stmt->execute(['item_type' => $itemType, 'status' => ITEM_STATUS_APPROVED]);

    return $stmt->fetchAll();
}

/**
 * Pair keys ("lostId:foundId") that already have a row in matches, and the items
 * that already have a confirmed match.
 *
 * @return array{pairs: array<string, true>, lost_confirmed: array<int, true>, found_confirmed: array<int, true>}
 */
function item_match_existing_state(): array
{
    $state = ['pairs' => [], 'lost_confirmed' => [], 'found_confirmed' => []];
    $rows = db()->query('SELECT lost_item_id, found_item_id, is_confirmed FROM matches')->fetchAll();

    foreach ($rows as $row) {
        $lostId = (int) $row['lost_item_id'];
        $foundId = (int) $row['found_item_id'];
        $state['pairs'][$lostId . ':' . $foundId] = true;

        if ((int) $row['is_confirmed'] === 1) {
            $state['lost_confirmed'][$lostId] = true;
            $state['found_confirmed'][$foundId] = true;
        }
    }

    return $state;
}

/**
 * Best matches for one Approved item: a Lost item gets Found candidates and the other way round.
 * Pairs that already exist in matches are left out.
 *
 * @return list<array{item: array<string, mixed>, score: int, label: string, parts: array<string, int>, reasons: list<string>}>
 */
function item_match_candidates(int $itemId, int $minScore = MATCHING_MIN_SCORE, int $limit = 10): array
{
    $source = item_match_item_find($itemId);

    if ($source === null || $source['status'] !== ITEM_STATUS_APPROVED) {
        return [];
    }

    $sourceIsLost = $source['item_type'] === ITEM_TYPE_LOST;
    $candidates = item_match_items_available($sourceIsLost ? ITEM_TYPE_FOUND : ITEM_TYPE_LOST);
    $state = item_match_existing_state();

    $candidates = array_values(array_filter(
        $candidates,
        static function (array $candidate) use ($source, $sourceIsLost, $state): bool {
            $lostId = $sourceIsLost ? (int) $source['item_id'] : (int) $candidate['item_id'];
            $foundId = $sourceIsLost ? (int) $candidate['item_id'] : (int) $source['item_id'];

            return !isset($state['pairs'][$lostId . ':' . $foundId])
                && !isset($state['lost_confirmed'][$lostId])
                && !isset($state['found_confirmed'][$foundId]);
        }
    ));

    return matching_rank($source, $candidates, $minScore, $limit);
}

/**
 * Suggested pairs for the admin matches page, best score first.
 * Only Approved items, only pairs that are not in matches yet, and no item that is already
 * part of a confirmed match. At most $perLost suggestions per Lost item.
 *
 * @return list<array{lost: array<string, mixed>, found: array<string, mixed>, score: int, label: string, parts: array<string, int>, reasons: list<string>}>
 */
function item_match_suggestions(int $minScore = MATCHING_MIN_SCORE, int $perLost = 3): array
{
    $lostItems = item_match_items_available(ITEM_TYPE_LOST);
    $foundItems = item_match_items_available(ITEM_TYPE_FOUND);
    $state = item_match_existing_state();

    $foundItems = array_values(array_filter(
        $foundItems,
        static fn (array $found): bool => !isset($state['found_confirmed'][(int) $found['item_id']])
    ));

    $suggestions = [];

    foreach ($lostItems as $lost) {
        $lostId = (int) $lost['item_id'];

        if (isset($state['lost_confirmed'][$lostId])) {
            continue;
        }

        $candidates = array_values(array_filter(
            $foundItems,
            static fn (array $found): bool => !isset($state['pairs'][$lostId . ':' . (int) $found['item_id']])
        ));

        foreach (matching_rank($lost, $candidates, $minScore, $perLost) as $ranked) {
            $suggestions[] = [
                'lost' => $lost,
                'found' => $ranked['item'],
                'score' => $ranked['score'],
                'label' => $ranked['label'],
                'parts' => $ranked['parts'],
                'reasons' => $ranked['reasons'],
            ];
        }
    }

    usort($suggestions, static function (array $x, array $y): int {
        return [$y['score'], (int) $x['lost']['item_id'], (int) $x['found']['item_id']]
            <=> [$x['score'], (int) $y['lost']['item_id'], (int) $y['found']['item_id']];
    });

    return $suggestions;
}

/**
 * Throw unless this is an Approved Lost item and an Approved Found item.
 *
 * @param array<string, mixed>|null $lost
 * @param array<string, mixed>|null $found
 */
function item_match_assert_pair(?array $lost, ?array $found): void
{
    if ($lost === null || $found === null) {
        throw new RuntimeException('One of the items no longer exists.');
    }

    if ($lost['item_type'] !== ITEM_TYPE_LOST || $found['item_type'] !== ITEM_TYPE_FOUND) {
        throw new RuntimeException('A match needs one Lost report and one Found report.');
    }

    if ($lost['status'] !== ITEM_STATUS_APPROVED || $found['status'] !== ITEM_STATUS_APPROVED) {
        throw new RuntimeException('Both reports must be approved before they can be matched.');
    }
}

/**
 * Find one match row by its ID, with item names and the confirming admin.
 *
 * @return array<string, mixed>|null
 */
function item_match_find(int $matchId): ?array
{
    $stmt = db()->prepare(
        'SELECT m.*,
                li.item_name AS lost_item_name,
                fi.item_name AS found_item_name,
                a.full_name AS confirmed_by_name
         FROM matches m
         JOIN items li ON m.lost_item_id = li.item_id
         JOIN items fi ON m.found_item_id = fi.item_id
         LEFT JOIN users a ON m.confirmed_by = a.user_id
         WHERE m.match_id = :match_id
         LIMIT 1'
    );
    $stmt->execute(['match_id' => $matchId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Save a pair as an unconfirmed match and return its match_id.
 * If the pair is already saved, the existing match_id is returned.
 */
function item_match_create(int $lostId, int $foundId): int
{
    item_match_assert_pair(item_match_item_find($lostId), item_match_item_find($foundId));

    $existing = db()->prepare(
        'SELECT match_id FROM matches WHERE lost_item_id = :lost_id AND found_item_id = :found_id LIMIT 1'
    );
    $existing->execute(['lost_id' => $lostId, 'found_id' => $foundId]);
    $existingId = $existing->fetchColumn();

    if ($existingId !== false) {
        return (int) $existingId;
    }

    $stmt = db()->prepare(
        'INSERT INTO matches (lost_item_id, found_item_id, is_confirmed) VALUES (:lost_id, :found_id, 0)'
    );

    try {
        $stmt->execute(['lost_id' => $lostId, 'found_id' => $foundId]);
    } catch (PDOException $e) {
        // Two admins saved the same pair at once; the unique key rejected the second one.
        if (($e->errorInfo[1] ?? null) === 1062) {
            $existing->execute(['lost_id' => $lostId, 'found_id' => $foundId]);

            return (int) $existing->fetchColumn();
        }

        throw $e;
    }

    return (int) db()->lastInsertId();
}

/**
 * Confirm a Lost/Found pair as an admin decision and return its match_id.
 * Creates the match row first when the pair was only a suggestion.
 * An item can belong to only one confirmed match.
 */
function item_match_confirm(int $lostId, int $foundId, int $adminId): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Lock both item rows (lowest id first) so two admins cannot confirm conflicting pairs.
        $lock = $pdo->prepare('SELECT item_id FROM items WHERE item_id IN (:a, :b) ORDER BY item_id FOR UPDATE');
        $lock->execute(['a' => $lostId, 'b' => $foundId]);

        item_match_assert_pair(item_match_item_find($lostId), item_match_item_find($foundId));

        $confirmed = $pdo->prepare(
            'SELECT match_id, lost_item_id, found_item_id
             FROM matches
             WHERE is_confirmed = 1 AND (lost_item_id = :lost_id OR found_item_id = :found_id)'
        );
        $confirmed->execute(['lost_id' => $lostId, 'found_id' => $foundId]);

        foreach ($confirmed->fetchAll() as $row) {
            if ((int) $row['lost_item_id'] === $lostId && (int) $row['found_item_id'] === $foundId) {
                $pdo->commit();

                return (int) $row['match_id']; // already confirmed, nothing to change
            }

            throw new RuntimeException('One of these items already has a confirmed match.');
        }

        $existing = $pdo->prepare(
            'SELECT match_id FROM matches WHERE lost_item_id = :lost_id AND found_item_id = :found_id LIMIT 1 FOR UPDATE'
        );
        $existing->execute(['lost_id' => $lostId, 'found_id' => $foundId]);
        $matchId = $existing->fetchColumn();

        if ($matchId === false) {
            $insert = $pdo->prepare(
                'INSERT INTO matches (lost_item_id, found_item_id, is_confirmed, confirmed_by)
                 VALUES (:lost_id, :found_id, 1, :admin_id)'
            );
            $insert->execute(['lost_id' => $lostId, 'found_id' => $foundId, 'admin_id' => $adminId]);
            $matchId = (int) $pdo->lastInsertId();
        } else {
            $update = $pdo->prepare(
                'UPDATE matches SET is_confirmed = 1, confirmed_by = :admin_id WHERE match_id = :match_id'
            );
            $update->execute(['admin_id' => $adminId, 'match_id' => (int) $matchId]);
        }

        $pdo->commit();

        return (int) $matchId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Remove a match that has not been confirmed (dismiss a saved suggestion).
 * Returns false when the match does not exist or is already confirmed.
 */
function item_match_delete_unconfirmed(int $matchId): bool
{
    $stmt = db()->prepare('DELETE FROM matches WHERE match_id = :match_id AND is_confirmed = 0');
    $stmt->execute(['match_id' => $matchId]);

    return $stmt->rowCount() === 1;
}

/**
 * List saved matches with item names, reporters, and the confirming admin. Newest first.
 * $confirmed: true = confirmed only, false = unconfirmed only, null = all.
 *
 * @return list<array<string, mixed>>
 */
function item_match_all(?bool $confirmed = null): array
{
    $sql = 'SELECT m.*,
                   li.item_name AS lost_item_name,
                   li.status AS lost_item_status,
                   lu.full_name AS lost_reporter_name,
                   fi.item_name AS found_item_name,
                   fi.status AS found_item_status,
                   fu.full_name AS found_reporter_name,
                   a.full_name AS confirmed_by_name
            FROM matches m
            JOIN items li ON m.lost_item_id = li.item_id
            JOIN users lu ON li.user_id = lu.user_id
            JOIN items fi ON m.found_item_id = fi.item_id
            JOIN users fu ON fi.user_id = fu.user_id
            LEFT JOIN users a ON m.confirmed_by = a.user_id';

    $params = [];

    if ($confirmed !== null) {
        $sql .= ' WHERE m.is_confirmed = :confirmed';
        $params['confirmed'] = $confirmed ? 1 : 0;
    }

    $sql .= ' ORDER BY m.created_at DESC, m.match_id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}