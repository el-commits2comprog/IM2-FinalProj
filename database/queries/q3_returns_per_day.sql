-- q3_returns_per_day.sql
-- ByteBack [M4] Query 3
-- Shows how many items were handed back to their owners on each day,
-- with the names of the items returned that day. Latest day first.
-- Only 'Returned' records are counted ('Claim Approved' records are not returns).

USE byteback;

SELECT DATE(t.transaction_date) AS return_date,
       COUNT(*) AS total_returns,
       GROUP_CONCAT(i.item_name ORDER BY i.item_name SEPARATOR ', ') AS items_returned
FROM transactions t
JOIN items i ON i.item_id = t.item_id
WHERE t.transaction_type = 'Returned'
GROUP BY DATE(t.transaction_date)
ORDER BY return_date DESC;