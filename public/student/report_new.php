<?php
// [M2] Item report form (for students).
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';
require_once __DIR__ . '/../../app/handlers/item_submit_handler.php';

if (!is_logged_in()) {
    header('Location: ' . url('login.php'));
    exit;
}

$user = current_user();
if (($user['role'] ?? '') === ROLE_ADMIN) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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

$categories = $pdo->query(
    "SELECT category_id, category_name FROM categories ORDER BY (category_name = 'Other'), category_name"
)->fetchAll();
$categoryIds = array_map('intval', array_column($categories, 'category_id'));

$locations = $pdo->query(
    "SELECT location_id, location_name FROM locations ORDER BY location_name"
)->fetchAll();
$locationIds = array_map('intval', array_column($locations, 'location_id'));

$errors = [];
$old = [
    'item_type'   => 'Lost',
    'item_name'   => '',
    'category_id' => '',
    'location_id' => '',
    'custom_location' => '',
    'event_date'  => '',
    'description' => '',
];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    $errors += validate_item_report($old, $categories, $locations);

    $photo = validate_item_photo($_FILES['photo'] ?? null);
    if ($photo['error'] !== null) {
        $errors['photo'] = $photo['error'];
    }
    $photoExt = $photo['ext'];
    $photoTmp = $photo['tmp'];

    $locationId     = (int)$old['location_id'];
    $customLocation = normalize_custom_location($old['custom_location']);

    if (!$errors) {
        $photoPath = null;
        $savedFile = null;

        try {
            if ($photoTmp !== null) {
                $uploadDir = __DIR__ . '/../../uploads';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    throw new RuntimeException('Could not create the uploads folder.');
                }
                $fileName  = bin2hex(random_bytes(12)) . '.' . $photoExt;
                $savedFile = $uploadDir . '/' . $fileName;
                if (!move_uploaded_file($photoTmp, $savedFile)) {
                    throw new RuntimeException('Could not save the uploaded photo.');
                }
                $photoPath = 'uploads/' . $fileName;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO items
                    (user_id, item_type, item_name, category_id, location_id, custom_location, event_date, photo_path, description, status)
                VALUES
                    (:user_id, :item_type, :item_name, :category_id, :location_id, :custom_location, :event_date, :photo_path, :description, :status)'
            );
            $stmt->execute([
                ':user_id'     => (int)($user['user_id'] ?? $user['id']),
                ':item_type'   => $old['item_type'],
                ':item_name'   => $old['item_name'],
                ':category_id' => (int)$old['category_id'],
                ':location_id' => $locationId,
                ':custom_location' => $customLocation,
                ':event_date'  => $old['event_date'],
                ':photo_path'  => $photoPath,
                ':description' => $old['description'],
                ':status'      => 'Pending',
            ]);

            unset($_SESSION['csrf']);
            flash_set('success', 'Report submitted. An admin will review it.'); 
            header('Location: ' . url('student/my_reports.php'));
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($savedFile !== null && is_file($savedFile)) {
                unlink($savedFile); 
            }
            error_log('report_new.php: ' . $ex->getMessage());
            $errors['form'] = 'Something went wrong while saving your report. Please try again.';
        }
    }
}

$page_title = 'Report Item';
$active_nav = 'report';
$extra_css  = [url('assets/css/items.css')];
$extra_js   = [url('assets/js/items.js')];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main id="main" class="container">
    <section class="card report-form-section">
        <h1>Report an item</h1>
        <p class="muted">Your report will be reviewed by an admin before it appears in the feed.</p>

        <?php if (isset($errors['form'])): ?>
            <p class="form-error"><?= e($errors['form']) ?></p>
        <?php endif; ?>

        <form class="report-form" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

            <fieldset class="field">
                <legend>This item was</legend>
                <div>
                    <label class="choice"><input type="radio" name="item_type" value="Lost"  <?= $old['item_type'] === 'Lost'  ? 'checked' : '' ?>> Lost</label>
                    <label class="choice"><input type="radio" name="item_type" value="Found" <?= $old['item_type'] === 'Found' ? 'checked' : '' ?>> Found</label>
                </div>
                <?php if (isset($errors['item_type'])): ?><span class="form-error"><?= e($errors['item_type']) ?></span><?php endif; ?>
            </fieldset>

            <div class="field">
                <label for="item_name">Item name</label>
                <input id="item_name" name="item_name" maxlength="100" value="<?= e($old['item_name']) ?>" required>
                <?php if (isset($errors['item_name'])): ?><span class="form-error"><?= e($errors['item_name']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="location_id">Location</label>
                <select id="location_id" name="location_id" required>
                    <option value="">Select location</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= (int)$l['location_id'] ?>"
                            data-other="<?= $l['location_name'] === 'Other' ? '1' : '0' ?>"
                            <?= (string)$l['location_id'] === $old['location_id'] ? 'selected' : '' ?>>
                            <?= e($l['location_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['location_id'])): ?>
                    <span class="form-error"><?= e($errors['location_id']) ?></span>
                <?php endif; ?>

                <input id="custom_location" name="custom_location" maxlength="100"
                    placeholder="Specific spot (optional), e.g. 2nd floor hallway"
                    value="<?= e($old['custom_location']) ?>">
                <?php if (isset($errors['custom_location'])): ?>
                    <span class="form-error"><?= e($errors['custom_location']) ?></span>
                <?php endif; ?>
            </div>
                    
            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['category_id'] ?>" <?= (string)$c['category_id'] === $old['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?><span class="form-error"><?= e($errors['category_id']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="event_date">Date lost or found</label>
                <input id="event_date" type="date" name="event_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($old['event_date']) ?>" required>
                <?php if (isset($errors['event_date'])): ?><span class="form-error"><?= e($errors['event_date']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" maxlength="1000" required><?= e($old['description']) ?></textarea>
                <span class="muted">Describe the item (color, brand, marks). Do not include personal contact details.</span>
                <?php if (isset($errors['description'])): ?><span class="form-error"><?= e($errors['description']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="photo">Photo (optional)</label>
                <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                <span class="muted">JPG, PNG, or WebP, up to 2 MB.</span>
                <?php if (isset($errors['photo'])): ?><span class="form-error"><?= e($errors['photo']) ?></span><?php endif; ?>
            </div>

            <div class="important-notice">
                <i class="fa-solid fa-circle-exclamation"></i>
                <p>
                    <strong>Important:</strong> Found items must be handed over to the school Lost and Found Office. 
                    Please turn in the item as soon as possible after submitting this report. 
                    Do not give it directly to the person who lost it.
                </p>
            </div>
            <button type="button" class="btn btn-outline" id="previewReport">
                Preview Report
            </button>

            <div class="form-actions">
                <a class="btn" href="<?= e(url('./index.php')) ?>">Cancel</a>
                <button type="button" class="btn btn-primary" id="open-confirm">
                    Submit report
                </button>
            </div>

        </form>
        <dialog id="confirm-dialog" class="confirm-dialog">
            <form method="dialog">
                <button type="submit" value="cancel" class="confirm-close" aria-label="Close">&times;</button>

                <h2>Submit Report</h2>
                <p>Are you sure you want to submit this <span id="confirm-type">item</span> item report?</p>

                <div class="confirm-submit">
                    <button type="submit" value="cancel" class="btn btn-secondary">Cancel</button>
                    <button type="button" id="confirm-submit" class="btn btn-primary">
                        Submit
                    </button>
                </div>
            </form>
        </dialog>
    </section>
    <div id="previewModal" class="item-modal" hidden>
    <div class="item-modal-content">
            <button type="button" id="closePreviewModal" class="item-modal-close">&times;</button>
            <div id="previewModalBody"></div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>