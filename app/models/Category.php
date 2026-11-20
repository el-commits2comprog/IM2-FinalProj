<?php
// [M2] Category model
declare(strict_types=1);

function category_all(PDO $pdo): array
{
    return $pdo->query(
        "SELECT category_id, category_name FROM categories ORDER BY (category_name = 'Other'), category_name"
    )->fetchAll();
}
