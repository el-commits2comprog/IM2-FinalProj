<?php
declare(strict_types=1);

/**
 * M3 Item Review Model.
 * Handles pending item reports and admin decisions.
 */

/**
 * Get all pending item reports for admin review.
 */
function item_review_pending_all(): array
{
    $stmt = db()->query(
        "SELECT
            i.*,
            u.full_name AS reporter_name,
            c.category_name,
            CASE
                WHEN l.location_name = 'Other'
                    THEN COALESCE(i.custom_location, 'Other')
                ELSE CONCAT_WS(' - ', l.location_name, i.custom_location)
            END AS location
         FROM items i
         JOIN users u ON i.user_id = u.user_id
         JOIN categories c ON i.category_id = c.category_id
         JOIN locations l ON i.location_id = l.location_id
         WHERE i.status = 'Pending'
         ORDER BY i.created_at ASC"
    );

    return $stmt->fetchAll();
}

/**
 * Approve or reject a pending item report.
 */
function item_review_process(
    int $itemId,
    int $adminId,
    string $decision,
    ?string $remarks
): void {

    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        throw new InvalidArgumentException('Invalid review decision.');
    }

    $stmt = db()->prepare(
        "UPDATE items
         SET status = :status,
             reviewed_by = :admin_id,
             review_remarks = :remarks,
             reviewed_at = NOW()
         WHERE item_id = :item_id
           AND status = 'Pending'"
    );

    $stmt->execute([
        'status' => $decision,
        'admin_id' => $adminId,
        'remarks' => $remarks,
        'item_id' => $itemId,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The item does not exist or has already been reviewed.'
        );
    }
}