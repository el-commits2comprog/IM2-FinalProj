USE byteback;
SELECT i.item_id, i.item_type, i.item_name, c.category_name,
       CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
       i.event_date, i.created_at
FROM   items i
JOIN   users u      ON u.user_id     = i.user_id
JOIN   categories c ON c.category_id = i.category_id
WHERE  i.status = 'Pending'
ORDER BY i.created_at ASC;