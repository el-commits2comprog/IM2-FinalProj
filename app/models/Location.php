<?php
// [M2] Location model
declare(strict_types=1);

function location_all(PDO $pdo): array
{
    return $pdo->query('SELECT location_id, location_name FROM locations ORDER BY location_name')->fetchAll();
}
