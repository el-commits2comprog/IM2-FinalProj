-- q5_items_per_category.sql
-- ByteBack [M4] Query 5
-- Shows how many item reports each category has, split into Lost and Found.
-- Every category is listed, even one with no reports (it shows 0).
-- The category with the most reports comes first.

USE byteback;

SELECT c.category_name,
       COUNT(i.item_id) AS total_items,
       COALESCE(SUM(i.item_type = 'Lost'), 0) AS lost_items,
       COALESCE(SUM(i.item_type = 'Found'), 0) AS found_items
FROM categories c
LEFT JOIN items i ON i.category_id = c.category_id
GROUP BY c.category_id, c.category_name
ORDER BY total_items DESC, c.category_name ASC;