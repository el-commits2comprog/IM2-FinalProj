
-- ByteBack [M3] Claims Validation Trigger
-- Prevents claims on items that are not Approved Found items.

USE byteback;

DROP TRIGGER IF EXISTS before_claim_insert;

DELIMITER //

CREATE TRIGGER before_claim_insert
BEFORE INSERT ON claims
FOR EACH ROW
BEGIN
    DECLARE v_item_type VARCHAR(20);
    DECLARE v_item_status VARCHAR(20);

    -- Get the type and status of the item being claimed.
    SELECT item_type, status
    INTO v_item_type, v_item_status
    FROM items
    WHERE item_id = NEW.item_id;

    -- Only Approved Found items can receive claims.
    IF v_item_type <> 'Found' OR v_item_status <> 'Approved' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only approved Found items can be claimed.';
    END IF;
END //

DELIMITER ;
