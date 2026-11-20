<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/guard.php';    
require_once __DIR__ . '/../app/models/Item.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Location.php';  

if (!is_logged_in()) {
    header('Location: ' . url('login.php'));
    exit;
}                     // guests go to login.php

$user = current_user();
$uid = (int)($user['user_id'] ?? $user['id']);

// Admins have their own area; send them there directly.
// Do NOT call redirect_by_role() here: it sends students back to index.php (a loop).
if (($user['role'] ?? '') === ROLE_ADMIN) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'found', 'lost'], true)) {
    $type = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT i.item_id    AS id,
           i.item_type  AS type,
           i.item_name,
           i.user_id as reporter_id,
           COALESCE(u.full_name, 'Unknown') AS reporter_name,
           c.category_name AS category,
           CASE
               WHEN l.location_name = 'Other' THEN COALESCE(i.custom_location, 'Other')
               ELSE CONCAT_WS(' - ', l.location_name, i.custom_location)
           END AS location, 
           i.event_date    AS date,
           i.photo_path AS photo,
           i.description
    FROM items i
    JOIN categories c ON c.category_id = i.category_id
    JOIN locations  l ON l.location_id = i.location_id
    LEFT JOIN users u ON u.user_id = i.user_id
    WHERE i.status = 'Approved'                                                                                    
";
$params = [];

$categoryId = (int)($_GET['category'] ?? 0);
$locationId = (int)($_GET['location'] ?? 0);

$categories = category_all($pdo);
$locations  = location_all($pdo);
$items = item_feed($pdo, [
    'q' => $q, 'type' => ucfirst($type),
    'category_id' => $categoryId, 'location_id' => $locationId,
]);

$isFiltered = ($type !== 'all' || $q !== '' || $categoryId > 0 || $locationId > 0);

if ($categoryId > 0) {
    $sql .= " AND i.category_id = :cat";
    $params[':cat'] = $categoryId;
}
if ($locationId > 0) {
    $sql .= " AND i.location_id = :loc";
    $params[':loc'] = $locationId;
}

if ($type !== 'all') {
    $sql .= " AND i.item_type = :type";
    $params[':type'] = ucfirst($type);         
}
if ($q !== '') {
    $sql .= " AND (i.item_name LIKE :q OR i.description LIKE :q OR i.custom_location LIKE :q OR l.location_name LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
$sql .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$items = $stmt->fetchAll();

$page_title = 'Feed';
$active_nav = 'feed';  
$extra_css  = [url('assets/css/items.css')];
$extra_js   = [url('assets/js/items.js')];                   

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<main id="main" class="container">
    <section class="card page-head">
        <div>
            <h1>Welcome, <?= e($user['full_name'] ?? '') ?></h1>
            <p>Browse items reported on campus.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('student/report_new.php')) ?>">Report an item</a>
    </section>

    <?php
    $tabQuery = static function (string $t) use ($q, $categoryId, $locationId): string {
        return '?' . http_build_query(array_filter([
            'category' => $categoryId ?: null,
            'location' => $locationId ?: null,
        ], static fn($v) => $v !== null && $v !== ''));
    };
    ?>

    <!-- Feed filters -->
    <form class="feed-filters" method="get">
        <div class="feed-tabs">
            <a href="?type=all"<?= $type === 'all' ? ' aria-current="true"' : '' ?>>All</a>
            <a href="?type=found"<?= $type === 'found' ? ' aria-current="true"' : '' ?>>Found</a>
            <a href="?type=lost"<?= $type === 'lost' ? ' aria-current="true"' : '' ?>>Lost</a>
            <input type="hidden" name="type" value="<?= e($type) ?>">
        </div>
        <input type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Search items...">
        
        <select name="category" aria-label="Category" onchange="this.form.submit()">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['category_id'] ?>"<?= $categoryId === (int)$c['category_id'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option>
        <?php endforeach; ?>
        </select>

        <select name="location" aria-label="Location" onchange="this.form.submit()">
            <option value="">All locations</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= (int)$l['location_id'] ?>"<?= $locationId === (int)$l['location_id'] ? ' selected' : '' ?>><?= e($l['location_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- Feed items -->
    <?php if (!$items): ?>
        <section class="card empty-state">
            <h2><?= $isFiltered ? 'No matching items' : 'No items yet' ?></h2>
            <p><?= $isFiltered ? 'Nothing matches your search. Try another word or clear the filters.' : 'Approved reports will appear here.' ?></p>
        </section>
    <?php else: ?>
        <div class="feed-grid">
            <?php foreach ($items as $item): ?>
                <?php $isOwn = ((int)$item['reporter_id'] === $uid); ?>
                <article class="card feed-card<?= $isOwn ? ' feed-card-own' : '' ?>">
                    <?php if ($item['photo']): ?>
                        <img src="<?= e(url('/../app/helpers/upload.php?f=' . urlencode(basename($item['photo'])))) ?>"
                            alt="<?= e($item['item_name']) ?>" class="feed-photo" loading="lazy">
                    <?php else: ?>
                        <div class="feed-photo-empty" aria-hidden="true">
                            <i class="fa-regular fa-image"></i>
                            <span>No photo attached</span>
                        </div>
                    <?php endif; ?>
                    <div class="feed-badges">
                        <span class="badge badge-<?= e(strtolower($item['type'])) ?>"><?= e($item['type']) ?></span>
                        <?php if ($isOwn): ?>
                            <span class="badge badge-own">Your report</span>
                        <?php endif; ?>
                    </div>
                    <h3><?= e($item['item_name']) ?></h3>
                    <p class="muted"><?= e($item['category']) ?></p>
                    
                    <div class="item-meta">                                                                                                     
                        <p class="item-location">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= e($item['location']) ?> 
                        </p>
                        
                        <p class="item-date">
                            <i class="fa-solid fa-calendar"></i>
                            <?= e($item['date']) ?>
                        </p>

                        <p class="item-reporter">
                            <i class="fa-solid fa-user"></i>
                            Reported by <?= $isOwn ? 'you' : e($item['reporter_name']) ?>
                        </p>
                    </div>
                    
                    <button type="button" class="btn view-detail" data-item-id="<?= (int)$item['id'] ?>">View details</button>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
   <div id="itemModal" class="item-modal" hidden role="dialog" aria-modal="true" aria-labelledby="itemModalTitle" data-url="<?= e(url('student/item_view.php')) ?>">
        <div class="item-modal-content">
            <button type="button" class="item-modal-close" id="closeItemModal" aria-label="Close">&times;</button>
            <div id="itemModalBody"></div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>