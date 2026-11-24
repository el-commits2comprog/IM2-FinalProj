<?php

declare(strict_types=1);

/**
 * ByteBack [M4] Confirm a Lost/Found match.
 * Receives a POST from public/admin/matches.php with lost_item_id and found_item_id.
 */

// Load authentication and shared functions.
require_once __DIR__ . '/../auth/guard.php';

// Load the M4 match model.
require_once __DIR__ . '/../models/itemMatch.php';

// Only accept POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('admin/matches.php'));
}

// Only administrators can confirm matches.
require_role(ROLE_ADMIN);

// Verify the CSRF token.
if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('admin/matches.php'));
}

// Get the logged-in administrator.
$adminId = (int) current_user()['user_id'];

// Get the two item IDs from the form.
$lostItemId = filter_input(INPUT_POST, 'lost_item_id', FILTER_VALIDATE_INT);
$foundItemId = filter_input(INPUT_POST, 'found_item_id', FILTER_VALIDATE_INT);

// Check that both IDs are valid numbers.
if (!is_int($lostItemId) || $lostItemId <= 0 || !is_int($foundItemId) || $foundItemId <= 0) {
    flash_set('error', 'Invalid match selected.');
    redirect(url('admin/matches.php'));
}

try {
    // Save the confirmation (creates the match first if it was only a suggestion).
    item_match_confirm($lostItemId, $foundItemId, $adminId);

    flash_set('success', 'Match confirmed.');

} catch (InvalidArgumentException | RuntimeException $exception) {
    // These messages are written to be shown to the admin.
    flash_set('error', $exception->getMessage());

} catch (PDOException $exception) {
    flash_set('error', 'A database error occurred while confirming the match. Please try again.');
}

// Return to the matches page.
redirect(url('admin/matches.php'));