<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../auth/guard.php';

// Load the Claim model.
require_once __DIR__ . '/../models/Claim.php';

// Only admins can review claims.
require_role(ROLE_ADMIN);

// Only accept POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(url('admin/claims.php'));
    exit;
}

// Verify the CSRF token.
if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('admin/claims.php'));
    exit;
}

// Get the logged-in admin.
$admin = current_user();

// Get the submitted claim ID.
$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);

// Get the admin's decision.
$decision = $_POST['decision'] ?? '';

// Get the admin's remarks.
$remarks = $_POST['remarks'] ?? '';

// Check if the claim ID is valid.
if (!$claimId || $claimId < 1) {
    flash_set('error', 'Invalid claim selected.');
    redirect(url('admin/claims.php'));
    exit;
}

// Check if the decision is valid.
if (!in_array($decision, ['Approved', 'Rejected'], true)) {
    flash_set('error', 'Invalid claim decision.');
    redirect(url('admin/claims.php'));
    exit;
}

// Make sure the remarks are text.
if (!is_string($remarks)) {
    flash_set('error', 'Invalid remarks.');
    redirect(url('admin/claims.php'));
    exit;
}

// Remove unnecessary spaces.
$remarks = trim($remarks);

// Limit the length of remarks.
if (strlen($remarks) > 1000) {
    flash_set('error', 'Remarks must not exceed 1000 characters.');
    redirect(url('admin/claims.php'));
    exit;
}

// Process the admin's decision.
try {

    claim_process(
        (int) $claimId,
        (int) $admin['user_id'],
        $decision,
        $remarks !== '' ? $remarks : null
    );

    flash_set('success', 'Claim successfully ' . strtolower($decision) . '.');

} catch (PDOException $e) {

    flash_set('error', 'Unable to process this claim. It may have already been reviewed or is no longer available.');

}

// Return to the admin claims page.
redirect(url('admin/claims.php'));
exit;
