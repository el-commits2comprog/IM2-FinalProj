<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../app/auth/guard.php';

// Load the Claim model.
require_once __DIR__ . '/../app/models/Claim.php';

// Require the user to be logged in.
require_login();

// Get the current user.
$user = current_user();

// Only admins and students may view claim proof photos.
if (!in_array($user['role'], [ROLE_ADMIN, ROLE_STUDENT], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Get and validate the claim ID.
$claimId = filter_input(INPUT_GET, 'claim_id', FILTER_VALIDATE_INT);

if (!$claimId || $claimId < 1) {
    http_response_code(404);
    exit('Claim not found.');
}

// Find the claim.
$claim = claim_find($claimId);

if ($claim === null || empty($claim['proof_photo'])) {
    http_response_code(404);
    exit('Proof photo not found.');
}

// Students may only view their own claim photos.
if (
    $user['role'] === ROLE_STUDENT &&
    (int) $claim['claimant_id'] !== (int) $user['user_id']
) {
    http_response_code(403);
    exit('Access denied.');
}

// Validate the stored upload path.
$storedPath = (string) $claim['proof_photo'];

if (!str_starts_with($storedPath, 'uploads/')) {
    http_response_code(404);
    exit('Proof photo not found.');
}

// Get only the filename.
$fileName = substr($storedPath, strlen('uploads/'));

// Accept only the expected random filename format.
if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i', $fileName)) {
    http_response_code(404);
    exit('Proof photo not found.');
}

// Locate the uploads folder.
$uploadDirectory = realpath(dirname(__DIR__) . '/uploads');

if ($uploadDirectory === false) {
    http_response_code(404);
    exit('Proof photo not found.');
}

// Locate the requested file.
$filePath = realpath(
    $uploadDirectory . DIRECTORY_SEPARATOR . $fileName
);

// Make sure the file exists and stays inside uploads.
if (
    $filePath === false ||
    !str_starts_with($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit('Proof photo not found.');
}

// Detect the actual file type.
$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($filePath);

$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

if (!in_array($mimeType, $allowedTypes, true)) {
    http_response_code(404);
    exit('Invalid proof photo.');
}

// Send the image securely.
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($filePath);
exit;
