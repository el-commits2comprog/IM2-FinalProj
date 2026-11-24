<?php

declare(strict_types=1);

/**
 * ByteBack [M4] Record that an item was handed over to its owner.
 * Receives a POST from public/admin/returns.php with claim_id and optional remarks.
 */

// Load authentication and shared functions.
require_once __DIR__ . '/../auth/guard.php';

// Load the M4 transaction model.
require_once __DIR__ . '/../models/Transaction.php';

// Only accept POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('admin/returns.php'));
}

// Only administrators can record a return.
require_role(ROLE_ADMIN);

// Verify the CSRF token.
if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('admin/returns.php'));
}

// Get the logged-in administrator.
$adminId = (int) current_user()['user_id'];

// Get the form inputs.
$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$remarks = $_POST['remarks'] ?? '';

// Check that the claim ID is a valid number.
if (!is_int($claimId) || $claimId <= 0) {
    flash_set('error', 'Invalid claim selected.');
    redirect(url('admin/returns.php'));
}

// Make sure the remarks are text.
if (!is_string($remarks)) {
    flash_set('error', 'Invalid remarks.');
    redirect(url('admin/returns.php'));
}

// Remove unnecessary spaces.
$remarks = trim($remarks);

// The remarks column holds up to 255 characters.
if (strlen($remarks) > 255) {
    flash_set('error', 'Remarks must not exceed 255 characters.');
    redirect(url('admin/returns.php'));
}

try {
    // Mark the item as Returned and save the record.
    transaction_record_return(
        $claimId,
        $adminId,
        $remarks === '' ? null : $remarks
    );

    flash_set('success', 'Return recorded. The item is now marked as Returned.');

} catch (InvalidArgumentException | RuntimeException $exception) {
    // These messages are written to be shown to the admin.
    flash_set('error', $exception->getMessage());

} catch (PDOException $exception) {
    flash_set('error', 'A database error occurred while recording the return. Please try again.');
}

// Return to the returns page.
redirect(url('admin/returns.php'));