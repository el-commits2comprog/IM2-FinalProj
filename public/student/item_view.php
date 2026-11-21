<?php
// [M2] Item Details Modal reused by Feed and My Reports
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';
require_once __DIR__ . '/../../app/models/Item.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

$user = current_user();

if (($user['role'] ?? '') === ROLE_ADMIN) {
    http_response_code(403);
    exit;
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$uid = (int)($user['user_id'] ?? $user['id']);
$item = item_find($pdo, (int)($_GET['id'] ?? 0));

$public = $item && in_array(
    $item['status'],
    ['Approved', 'Matched', 'Claimed', 'Returned'],
    true
);

if (!$item || !($public || (int)$item['user_id'] === $uid)) {
    http_response_code(404);
    exit;
}
?>

<div class="item-modal-content">
    <?php if ($item['photo_path']): ?>
        <img
            src="<?= e(url('/../app/helpers/upload.php?f=' . urlencode(basename($item['photo_path'])))) ?>"
            alt="<?= e($item['item_name']) ?>" class="item-photo" loading="lazy"
        >
    <?php else: ?>
        <div class="item-photo-empty">
            <i class="fa-regular fa-image"></i>
            <span>No photo attached</span>
        </div>
    <?php endif; ?>

    <?php $isOwn = ((int)$item['user_id'] === $uid); ?>
    <div class="item-modal-info">

        <div class="item-badges">
            <span class="badge badge-<?= e(strtolower($item['item_type'])) ?>">
                <?= e($item['item_type']) ?>
            </span>
            <?php if ($isOwn): ?>
                <span class="badge badge-own">Your report</span>
            <?php endif; ?>
        </div>

        <h2><?= e($item['item_name']) ?></h2>

        <p class="muted"><?= e($item['category_name']) ?></p>

        <div class="item-meta">
            <p>
                <i class="fa-solid fa-location-dot"></i>
                <?= e($item['location']) ?>
            </p>

            <p>
                <i class="fa-regular fa-calendar"></i>
                <?= e($item['event_date']) ?>
            </p>

            <p>
                <i class="fa-solid fa-user"></i>
                Reported by <?= $isOwn ? 'you' : e($item['reporter_name'] ?? 'Unknown') ?>
            </p>
        </div>

        <div class="item-description">
            <h3>Description</h3>
            <p><?= nl2br(e($item['description'])) ?></p>
        </div>

        <div class="item-modal-actions">
            <?php if (
                $item['item_type'] === 'Found' &&
                $item['status'] === 'Approved' &&
                (int)$item['user_id'] !== $uid
            ): ?>
                <a
                    class="btn btn-primary"
                    href="<?= e(url('student/claim_new.php?item_id=' . (int)$item['item_id'])) ?>"
                >
                    Claim this item
                </a>
            <?php endif; ?>
            <?php if (
                $item['item_type'] === 'Lost' &&
                $item['status'] === 'Approved' &&
                (int)$item['user_id'] !== $uid
            ): ?>
                <a
                    class="btn btn-primary"
                    href="<?= e(url('student/report_new.php?item_id=' . (int)$item['item_id'])) ?>"
                >
                    I found this
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>