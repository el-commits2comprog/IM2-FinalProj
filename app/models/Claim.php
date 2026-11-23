<?php
declare(strict_types=1);

/**
 * Claims table. Prepared statements only.
 */

/**
 * Submit a new claim.
 *
 * @param array{
 *   item_id: int,
 *   claimant_id: int,
 *   proof_description: string,
 *   proof_photo?: string|null
 * } $data
 */
function claim_create(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO claims (
            item_id,
            claimant_id,
            proof_description,
            proof_photo
        )
        VALUES (
            :item_id,
            :claimant_id,
            :proof_description,
            :proof_photo
        )'
    );

    $stmt->execute([
        'item_id' => $data['item_id'],
        'claimant_id' => $data['claimant_id'],
        'proof_description' => trim($data['proof_description']),
        'proof_photo' => $data['proof_photo'] ?? null,
    ]);

    return (int) db()->lastInsertId();
}

/**
 * Find one claim by its ID.
 *
 * @return array<string, mixed>|null
 */
function claim_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT c.*, 
                u.full_name AS claimant_name,
                i.item_name,
                i.item_type,
                i.status AS item_status
         FROM claims c
         JOIN users u ON c.claimant_id = u.user_id
         JOIN items i ON c.item_id = i.item_id
         WHERE c.claim_id = :claim_id
         LIMIT 1'
    );

    $stmt->execute(['claim_id' => $id]);
    $claim = $stmt->fetch();

    return $claim === false ? null : $claim;
}

/**
 * Get all claims submitted by one student.
 *
 * @return list<array<string, mixed>>
 */
function claim_all_by_student(int $studentId): array
{
    $stmt = db()->prepare(
        'SELECT c.*, i.item_name, i.item_type
         FROM claims c
         JOIN items i ON c.item_id = i.item_id
         WHERE c.claimant_id = :claimant_id
         ORDER BY c.created_at DESC'
    );

    $stmt->execute(['claimant_id' => $studentId]);

    return $stmt->fetchAll();
}

/**
 * Get all pending claims for admin review.
 *
 * @return list<array<string, mixed>>
 */
function claim_all_pending(): array
{
    $stmt = db()->query(
        "SELECT c.*,
                u.full_name AS claimant_name,
                i.item_name,
                i.item_type,
                i.status AS item_status
         FROM claims c
         JOIN users u ON c.claimant_id = u.user_id
         JOIN items i ON c.item_id = i.item_id
         WHERE c.status = 'Pending'
         ORDER BY c.created_at ASC"
    );

    return $stmt->fetchAll();
}

/**
 * Process a claim using the stored procedure.
 */
function claim_process(
    int $claimId,
    int $adminId,
    string $decision,
    ?string $remarks
): void {
    $stmt = db()->prepare(
        'CALL sp_process_claim(:claim_id, :admin_id, :decision, :remarks)'
    );

    $stmt->execute([
        'claim_id' => $claimId,
        'admin_id' => $adminId,
        'decision' => $decision,
        'remarks' => $remarks,
    ]);

    // Clear the stored procedure result set.
    $stmt->closeCursor();
}
