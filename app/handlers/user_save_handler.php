<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('admin/users.php'));
}

require_role(ROLE_ADMIN);

if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('admin/users.php'));
}

$action = user_save_input('action');
$actorId = (int) current_user()['user_id'];
$userId = user_save_input('user_id') === '' ? 0 : (int) user_save_input('user_id');

if ($action === 'deactivate' || $action === 'reactivate') {
    user_save_change_status($action, $userId, $actorId);
}

if ($action !== 'save') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('admin/users.php'));
}

$fullName = user_save_clean_name(user_save_input('full_name'));
$studentNumber = strtoupper(trim(user_save_input('student_number')));
$email = strtolower(trim(user_save_input('email')));
$role = user_save_input('role');
$status = user_save_input('status');
$password = user_save_raw('password');
$confirm = user_save_raw('password_confirm');
$isCreate = $userId <= 0;

$old = [
    'full_name' => $fullName,
    'student_number' => $studentNumber,
    'email' => $email,
    'role' => $role,
    'status' => $status,
];

$errors = [];
$existing = null;

if (!$isCreate) {
    $existing = user_find_by_id($userId);
    if ($existing === null) {
        flash_set('error', 'That user does not exist.');
        redirect(url('admin/users.php'));
    }
}

if (!preg_match("/^[\p{L}][\p{L}\s.'-]{1,99}$/u", $fullName)) {
    $errors[] = 'Enter a full name using letters, spaces, hyphens, or apostrophes.';
}

if ($role !== ROLE_ADMIN && $role !== ROLE_STUDENT) {
    $errors[] = 'Choose a valid role.';
}

if ($status !== USER_STATUS_ACTIVE && $status !== USER_STATUS_INACTIVE) {
    $errors[] = 'Choose a valid status.';
}

if ($studentNumber === '') {
    if ($role === ROLE_STUDENT) {
        $errors[] = 'Students need a student number.';
    }
} elseif (!preg_match('/^\d{4}-\d{5}$/', $studentNumber)) {
    $errors[] = 'Student number must look like 2023-10421 (year, a hyphen, then 5 digits).';
}

if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}

$wantsPassword = $password !== '' || $confirm !== '';

if ($isCreate || $wantsPassword) {
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password must be at most 72 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }
}

if ($userId === $actorId && $role !== ROLE_ADMIN) {
    $errors[] = 'You cannot change your own role.';
}

if ($userId === $actorId && $status !== USER_STATUS_ACTIVE) {
    $errors[] = 'You cannot deactivate your own account.';
}

if ($errors === []) {
    $exceptId = $isCreate ? null : $userId;

    if (user_email_exists($email, $exceptId)) {
        $errors[] = 'That email is already registered.';
    }

    if ($studentNumber !== '') {
        $taken = user_find_by_student_number($studentNumber);
        if ($taken !== null && (int) $taken['user_id'] !== $userId) {
            $errors[] = 'That student number is already registered.';
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        flash_set('error', $error);
    }
    flash_old($old);
    $back = $isCreate ? url('admin/user_form.php') : url('admin/user_form.php?id=' . $userId);
    redirect($back);
}

$payload = [
    'student_number' => $studentNumber === '' ? null : $studentNumber,
    'full_name' => $fullName,
    'email' => $email,
    'role' => $role,
    'status' => $status,
];

if ($isCreate || $wantsPassword) {
    $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
}

try {
    if ($isCreate) {
        user_create($payload);
        flash_set('success', 'User created.');
    } else {
        user_update($userId, $payload);
        flash_set('success', 'User updated.');
    }
} catch (PDOException $exception) {
    if ($exception->getCode() !== '23000') {
        throw $exception;
    }

    flash_set('error', 'That email or student number is already registered.');
    flash_old($old);
    $back = $isCreate ? url('admin/user_form.php') : url('admin/user_form.php?id=' . $userId);
    redirect($back);
}

redirect(url('admin/users.php'));

function user_save_change_status(string $action, int $userId, int $actorId): void
{
    $target = user_find_by_id($userId);

    if ($target === null) {
        flash_set('error', 'That user does not exist.');
        redirect(url('admin/users.php'));
    }

    if ($action === 'deactivate' && $userId === $actorId) {
        flash_set('error', 'You cannot deactivate your own account.');
        redirect(url('admin/users.php'));
    }

    $status = $action === 'deactivate' ? USER_STATUS_INACTIVE : USER_STATUS_ACTIVE;
    user_set_status($userId, $status);
    flash_set('success', $action === 'deactivate' ? 'User deactivated.' : 'User reactivated.');
    redirect(url('admin/users.php'));
}

function user_save_input(string $key): string
{
    return trim(user_save_raw($key));
}

function user_save_raw(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

function user_save_clean_name(string $name): string
{
    return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
}
