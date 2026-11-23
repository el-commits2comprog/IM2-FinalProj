<?php
declare(strict_types=1);

// Load authentication, session, database, and shared functions.
require_once __DIR__ . '/../auth/guard.php';

// Load M2's Item model.
require_once __DIR__ . '/../models/Item.php';

// Load your M3 Claim model.
require_once __DIR__ . '/../models/Claim.php';

// Only logged-in students can submit claims.
require_role(ROLE_STUDENT);

// Accept POST requests only.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'Invalid request.');
    redirect(url('index.php'));
}

// Verify the form's CSRF token.
if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('index.php'));
}

// Get the currently logged-in student.
$user = current_user();
$studentId = (int) $user['user_id'];

// Get the submitted item ID.
$itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);

if (!$itemId || $itemId < 1) {
    flash_set('error', 'Invalid item selected.');
    redirect(url('index.php'));
}

// Connect to the database.
$pdo = db();

// Find the selected item in the database.
$item = item_find($pdo, $itemId);

// Check if the item exists.
if ($item === null) {
    flash_set('error', 'The selected item could not be found.');
    redirect(url('index.php'));
}

// Check if the item is a Found item.
if ($item['item_type'] !== ITEM_TYPE_FOUND) {
    flash_set('error', 'Only Found items can be claimed.');
    redirect(url('index.php'));
}

// Check if the item has been approved.
if ($item['status'] !== ITEM_STATUS_APPROVED) {
    flash_set('error', 'This item is not available for claiming.');
    redirect(url('index.php'));
}

// Prevent students from claiming their own reported items.
if ((int) $item['user_id'] === $studentId) {
    flash_set('error', 'You cannot claim your own reported item.');
    redirect(url('index.php'));
}

// Get the student's proof description.
$proofDescription = trim($_POST['proof_description'] ?? '');

// Check if the proof description is valid.
if ($proofDescription === '' || mb_strlen($proofDescription) > 1000) {
    flash_set('error', 'Please provide a proof description (maximum 1000 characters).');
    redirect(url('student/claim_new.php?item_id=' . $itemId));
}

// Prepare the optional proof photo.
$proofPhoto = null;

if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['proof_photo'];

    // Check for upload errors.
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'The photo could not be uploaded. Please try again.');
        redirect(url('student/claim_new.php?item_id=' . $itemId));
    }

    // Limit the photo size to 2 MB.
    if ($file['size'] > 2 * 1024 * 1024) {
        flash_set('error', 'The photo must be 2 MB or smaller.');
        redirect(url('student/claim_new.php?item_id=' . $itemId));
    }

    // Check the actual file type.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mime])) {
        flash_set('error', 'Only JPG, PNG, or WebP photos are allowed.');
        redirect(url('student/claim_new.php?item_id=' . $itemId));
    }

    // Remember the validated file extension and temporary path.
    $extension = $allowedTypes[$mime];
    $temporaryPath = $file['tmp_name'];
}

// Save the validated proof photo.
if (isset($temporaryPath, $extension)) {

    // Get the project's uploads folder.
    $uploadDirectory = dirname(__DIR__, 2) . '/uploads';

// Create the folder if it does not exist.
if (!is_dir($uploadDirectory)) {
    if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        flash_set('error', 'Unable to prepare the photo upload folder.');
        redirect(url('student/claim_new.php?item_id=' . $itemId));
    }
}

// Check if the folder is writable.
if (!is_writable($uploadDirectory)) {
    flash_set('error', 'The photo upload folder is not writable.');
    redirect(url('student/claim_new.php?item_id=' . $itemId));
}

    // Generate a unique filename.
    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

    // Move the uploaded photo into the uploads folder.
    $destination = $uploadDirectory . '/' . $fileName;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        flash_set('error', 'Unable to save the proof photo. Please try again.');
        redirect(url('student/claim_new.php?item_id=' . $itemId));
    }

    // Store the relative path for the database.
    $proofPhoto = 'uploads/' . $fileName;
}

try {
    // Save the claim details in the database.
    claim_create([
        'item_id' => $itemId,
        'claimant_id' => $studentId,
        'proof_description' => $proofDescription,
        'proof_photo' => $proofPhoto,
    ]);

    // Show a success message.
    flash_set('success', 'Your claim has been submitted successfully.');

    // Redirect the student to My Claims.
    redirect(url('student/my_claims.php'));

} catch (PDOException $e) {

    // Delete the uploaded photo if saving the claim fails.
    if ($proofPhoto !== null) {
        $savedPhoto = dirname(__DIR__, 2) . '/' . $proofPhoto;

        if (is_file($savedPhoto)) {
            unlink($savedPhoto);
        }
    }

    flash_set('error', 'Unable to submit your claim. Please try again.');
    redirect(url('student/claim_new.php?item_id=' . $itemId));
}
