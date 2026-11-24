<?php

declare(strict_types=1);

/**
 * ByteBack [M4] Transaction model. Prepared statements only.
 * Table: transactions (transaction_id, item_id, claim_id, admin_id,
 *                      transaction_type, transaction_date, remarks).
 *
 * How an item moves through the system:
 *   1. M3's claim approval (sp_process_claim) sets the Found item to "Claimed"
 *      and adds a transactions row of type "Claim Approved".
 *   2. This file's transaction_record_return() runs when the admin hands the item over.
 *      It sets the item to "Returned" and adds a transactions row of type "Returned".
 *
 * Errors are thrown as RuntimeException with a message that is safe to show in a flash message.
 *
 * Load it with: require_once __DIR__ . '/../../app/models/Transaction.php';
 * (the page or handler must already have loaded app/auth/guard.php).
 */

// These two values must match the transaction_type column in 05_matches_transactions.sql.
if (!defined('TRANSACTION_TYPE_CLAIM_APPROVED')) {
    define('TRANSACTION_TYPE_CLAIM_APPROVED', 'Claim Approved');
}

if (!defined('TRANSACTION_TYPE_RETURNED')) {
    define('TRANSACTION_TYPE_RETURNED', 'Returned');
}

/**
 * Approved claims whose item is still waiting to be handed over.
 * This is the list for the admin Returns page. Oldest approval first.
 *
 * @return list<array<string, mixed>>
 */
function transaction_awaiting_return(): array
{
    $stmt = db()->prepare(
        'SELECT c.claim_id,
                c.item_id,
                c.proof_description,
                c.reviewed_at AS claim_approved_at,
                i.item_name,
                i.photo_path,
                cat.category_name,
                owner.full_name AS claimant_name,
                owner.student_number AS claimant_student_number,
                owner.email AS claimant_email
         FROM claims c
         JOIN items i ON c.item_id = i.item_id
         JOIN categories cat ON i.category_id = cat.category_id
         JOIN users owner ON c.claimant_id = owner.user_id
         WHERE c.status = :claim_status
           AND i.item_type = :item_type
           AND i.status = :item_status
         ORDER BY c.reviewed_at ASC, c.claim_id ASC'
    );

    $stmt->execute([
        'claim_status' => CLAIM_STATUS_APPROVED,
        'item_type' => ITEM_TYPE_FOUND,
        'item_status' => ITEM_STATUS_CLAIMED,
    ]);

    return $stmt->fetchAll();
}

/**
 * Record that an item was handed over to its owner.
 * Sets the item to "Returned", adds a "Returned" row, and returns the new transaction_id.
 * Either both changes are saved or neither is.
 */
function transaction_record_return(int $claimId, int $adminId, ?string $remarks = null): int
{
    $remarks = $remarks === null ? '' : trim($remarks);

    if (strlen($remarks) > 255) {
        throw new RuntimeException('Remarks must not exceed 255 characters.');
    }

    if ($remarks === '') {
        $remarks = 'Item handed over to the owner.';
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // FOR UPDATE makes a second admin wait, so the same item cannot be returned twice.
        $check = $pdo->prepare(
            'SELECT c.item_id,
                    c.status AS claim_status,
                    i.item_type,
                    i.status AS item_status
             FROM claims c
             JOIN items i ON c.item_id = i.item_id
             WHERE c.claim_id = :claim_id
             FOR UPDATE'
        );
        $check->execute(['claim_id' => $claimId]);
        $row = $check->fetch();

        if ($row === false) {
            throw new RuntimeException('Claim not found.');
        }

        if ($row['claim_status'] !== CLAIM_STATUS_APPROVED) {
            throw new RuntimeException('Only an approved claim can be returned.');
        }

        if ($row['item_type'] !== ITEM_TYPE_FOUND) {
            throw new RuntimeException('Only a Found item can be returned.');
        }

        if ($row['item_status'] === ITEM_STATUS_RETURNED) {
            throw new RuntimeException('This item has already been returned.');
        }

        if ($row['item_status'] !== ITEM_STATUS_CLAIMED) {
            throw new RuntimeException('This item is not ready to be returned.');
        }

        $itemId = (int) $row['item_id'];

        $update = $pdo->prepare(
            'UPDATE items SET status = :new_status WHERE item_id = :item_id AND status = :old_status'
        );
        $update->execute([
            'new_status' => ITEM_STATUS_RETURNED,
            'item_id' => $itemId,
            'old_status' => ITEM_STATUS_CLAIMED,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('This item could not be updated. Please try again.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO transactions (item_id, claim_id, admin_id, transaction_type, transaction_date, remarks)
             VALUES (:item_id, :claim_id, :admin_id, :type, NOW(), :remarks)'
        );
        $insert->execute([
            'item_id' => $itemId,
            'claim_id' => $claimId,
            'admin_id' => $adminId,
            'type' => TRANSACTION_TYPE_RETURNED,
            'remarks' => $remarks,
        ]);

        $transactionId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return $transactionId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Find one transaction by its ID, with the item, owner, and admin names.
 *
 * @return array<string, mixed>|null
 */
function transaction_find(int $transactionId): ?array
{
    $stmt = db()->prepare(
        'SELECT t.*,
                i.item_name,
                owner.full_name AS claimant_name,
                adm.full_name AS admin_name
         FROM transactions t
         JOIN items i ON t.item_id = i.item_id
         JOIN claims c ON t.claim_id = c.claim_id
         JOIN users owner ON c.claimant_id = owner.user_id
         JOIN users adm ON t.admin_id = adm.user_id
         WHERE t.transaction_id = :transaction_id
         LIMIT 1'
    );
    $stmt->execute(['transaction_id' => $transactionId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * History list, newest first. Pass TRANSACTION_TYPE_RETURNED or TRANSACTION_TYPE_CLAIM_APPROVED
 * to show one kind only, or nothing to show all.
 *
 * @return list<array<string, mixed>>
 */
function transaction_all(?string $type = null): array
{
    $sql = 'SELECT t.*,
                   i.item_name,
                   owner.full_name AS claimant_name,
                   adm.full_name AS admin_name
            FROM transactions t
            JOIN items i ON t.item_id = i.item_id
            JOIN claims c ON t.claim_id = c.claim_id
            JOIN users owner ON c.claimant_id = owner.user_id
            JOIN users adm ON t.admin_id = adm.user_id';

    $params = [];

    if ($type !== null) {
        if ($type !== TRANSACTION_TYPE_RETURNED && $type !== TRANSACTION_TYPE_CLAIM_APPROVED) {
            throw new InvalidArgumentException('Invalid transaction type.');
        }

        $sql .= ' WHERE t.transaction_type = :type';
        $params['type'] = $type;
    }

    $sql .= ' ORDER BY t.transaction_date DESC, t.transaction_id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Number of approved claims still waiting for the item to be handed over (for the dashboard).
 */
function transaction_count_awaiting_return(): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM claims c
         JOIN items i ON c.item_id = i.item_id
         WHERE c.status = :claim_status AND i.item_type = :item_type AND i.status = :item_status'
    );
    $stmt->execute([
        'claim_status' => CLAIM_STATUS_APPROVED,
        'item_type' => ITEM_TYPE_FOUND,
        'item_status' => ITEM_STATUS_CLAIMED,
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Number of items handed over so far (for the dashboard).
 */
function transaction_count_returned(): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM transactions WHERE transaction_type = :type');
    $stmt->execute(['type' => TRANSACTION_TYPE_RETURNED]);

    return (int) $stmt->fetchColumn();
}