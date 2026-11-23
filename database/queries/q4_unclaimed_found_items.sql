
-- ByteBack [M3] Query 4
-- Displays Approved Found items without an approved claim.

USE byteback;

SELECT
    i.item_id,
    i.item_name,
    i.description,
    i.location_id,
    i.event_date,
    i.photo_path,
    i.status
FROM items i
WHERE i.item_type = 'Found'
  AND i.status = 'Approved'
  AND NOT EXISTS (
      SELECT 1
      FROM claims c
      WHERE c.item_id = i.item_id
        AND c.status = 'Approved'
  )
ORDER BY i.created_at DESC;
