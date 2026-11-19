<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';

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

$locationNames = $pdo->query(
    "SELECT location_name FROM locations WHERE location_name <> 'Other' ORDER BY location_name"
)->fetchAll(PDO::FETCH_COLUMN);

function find_or_create_location(PDO $pdo, string $name): int
{
    $find = $pdo->prepare('SELECT location_id FROM locations WHERE location_name = :name LIMIT 1');
    $find->execute([':name' => $name]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO locations (location_name) VALUES (:name)')->execute([':name' => $name]);
    return (int)$pdo->lastInsertId();
}

$errors = [];
$old = [
    'item_type'   => 'Lost',
    'item_name'   => '',
    'category_id' => '',
    'location_id' => '',
    'event_date'  => '',
    'description' => '',
];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

const MAX_PHOTO_BYTES = 2 * 1024 * 1024; //2 MB
const PHOTO_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!in_array($old['item_type'], ['Lost', 'Found'], true)) {
        $errors['item_type'] = 'Choose Lost or Found.';
    }
    if ($old['item_name'] === '' || mb_strlen($old['item_name']) > 100) {
        $errors['item_name'] = 'Item name is required (max 100 characters).';
    }
    if (!in_array((int)$old['category_id'], array_column($categories, 'category_id'), true)) {
        $errors['category_id'] = 'Choose a category.';
    }
    if (mb_strlen($old['location_name']) < 2 || mb_strlen($old['location_name']) > 100) {
        $errors['location_name'] = 'Enter the location where it was lost or found (2 to 100 characters).';
    }

    $newLocation = '';
    if ($otherLocationId !== 0 && (int)$old['location_id'] === $otherLocationId) {
        $newLocation = preg_replace('/\s+/', ' ', $old['other_location']);
        if ($newLocation === '' || mb_strlen($newLocation) > 100) {
            $errors['other_location'] = 'Enter the location name (max 100 characters).';
        }
    }

    $date = DateTime::createFromFormat('Y-m-d', $old['event_date']);
    if (!$date || $date->format('Y-m-d') !== $old['event_date']) {
        $errors['event_date'] = 'Enter a valid date.';
    } elseif ($old['event_date'] > date('Y-m-d')) {
        $errors['event_date'] = 'The date cannot be in the future.';
    }
    if ($old['description'] === '' || mb_strlen($old['description']) > 1000) {
        $errors['description'] = 'Description is required (max 1000 characters).';
    }

    $photoExt = null;
    $photoTmp = null;
    $file = $_FILES['photo'] ?? null;

    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'The photo could not be uploaded. Try a smaller file.';
        } elseif ($file['size'] > MAX_PHOTO_BYTES) {
            $errors['photo'] = 'The photo must be 2 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset(PHOTO_TYPES[$mime])) {
                $errors['photo'] = 'Only JPG, PNG, or WebP photos are allowed.';
            } else {
                $photoExt = PHOTO_TYPES[$mime];
                $photoTmp = $file['tmp_name'];
            }
        }
    }

    if (!$errors) {
        $photoPath = null;
        $savedFile = null;

        try {
            if ($photoTmp !== null) {
                $uploadDir = __DIR__ . '/../uploads';
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
                    (user_id, item_type, item_name, category_id, location_id, event_date, photo_path, description, status)
                VALUES
                    (:user_id, :item_type, :item_name, :category_id, :location_id, :event_date, :photo_path, :description, :status)'
            );
            $stmt->execute([
                ':user_id'     => (int)($user['user_id'] ?? $user['id']),
                ':item_type'   => $old['item_type'],
                ':item_name'   => $old['item_name'],
                ':category_id' => (int)$old['category_id'],
                ':location_id' => (int)$old['location_id'],
                ':event_date'  => $old['event_date'],
                ':photo_path'  => $photoPath,
                ':description' => $old['description'],
                ':status'      => 'Pending',
            ]);

            $pdo->commit();
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

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main id="main" class="container">
    <section class="card">
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

            <div class="field-row">
                <div class="field">
                    <label for="location_name">Location</label>
                    <input id="location_name" name="location_name" maxlength="100"
                    list="location-suggestions" autocomplete="off"
                    placeholder="Enter location, e.g. Library">
                    <datalist id="location-suggestions">
                        <?php foreach ($locationNames as $name): ?>
                            <option value="<?= e($name) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <?php if (isset($errors['location_name'])): ?><span class="form-error"><?= e($errors['location_name']) ?></span><?php endif; ?>
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
                </div>
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

            <div class="form-actions">
                <a class="btn" href="<?= e(url('index.php')) ?>">Cancel</a>
                <button type="submit" class="btn btn-primary">Submit report</button>
            </div>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>