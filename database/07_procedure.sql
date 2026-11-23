
-- ByteBack [M3] Stored Procedure
-- Processes an admin's decision on a student claim.

USE byteback;

DROP PROCEDURE IF EXISTS sp_process_claim;

DELIMITER //

CREATE PROCEDURE sp_process_claim(
    IN p_claim_id INT,
    IN p_admin_id INT,
    IN p_decision VARCHAR(20),
    IN p_remarks TEXT
)
BEGIN
    DECLARE v_item_id INT;
    DECLARE v_claim_status VARCHAR(20);
    DECLARE v_item_type VARCHAR(20);
    DECLARE v_item_status VARCHAR(20);
    DECLARE v_not_found BOOLEAN DEFAULT FALSE;

    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET v_not_found = TRUE;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT c.item_id, c.status, i.item_type, i.status
    INTO v_item_id, v_claim_status, v_item_type, v_item_status
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    WHERE c.claim_id = p_claim_id
    FOR UPDATE;

    IF v_not_found = TRUE THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Claim not found.';
    END IF;

    IF v_claim_status <> 'Pending' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Claim has already been reviewed.';
    END IF;

    IF v_item_type <> 'Found' OR v_item_status <> 'Approved' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Item is not available for claiming.';
    END IF;

    IF p_decision IS NULL OR p_decision NOT IN ('Approved', 'Rejected') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid claim decision.';
    END IF;

    UPDATE claims
    SET status = p_decision,
        reviewed_by = p_admin_id,
        review_remarks = p_remarks,
        reviewed_at = NOW()
    WHERE claim_id = p_claim_id;

    IF p_decision = 'Approved' THEN

        UPDATE items
        SET status = 'Claimed'
        WHERE item_id = v_item_id;

        INSERT INTO transactions (
            item_id,
            claim_id,
            admin_id,
            transaction_type,
            transaction_date,
            remarks
        )
        VALUES (
            v_item_id,
            p_claim_id,
            p_admin_id,
            'Claim Approved',
            NOW(),
            'Proof of ownership verified.'
        );

    END IF;

    COMMIT;
END //

DELIMITER ;
