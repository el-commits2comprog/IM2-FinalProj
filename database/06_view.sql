-- 06_view.sql [M2] v_approved_feed: what students see in the feed
-- Approved items only, joined with category, location and reporter name.

SET NAMES utf8mb4;
USE byteback;

DROP VIEW IF EXISTS v_approved_feed;

CREATE VIEW v_approved_feed AS
SELECT  i.item_id,
        i.item_type,
        i.item_name,
        i.description,
        i.photo_path,
        i.event_date,
        i.created_at,
        i.category_id,
        c.category_name,
        i.location_id,
        l.location_name,
        CASE
            WHEN l.location_name = 'Other' THEN COALESCE(i.custom_location, 'Other')
            ELSE CONCAT_WS(' - ', l.location_name, i.custom_location)
        END AS location,
        u.full_name AS reporter_name,
        i.status
FROM    items i
JOIN    categories c ON c.category_id = i.category_id
JOIN    locations  l ON l.location_id = i.location_id
JOIN    users      u ON u.user_id     = i.user_id
WHERE   i.status = 'Approved';