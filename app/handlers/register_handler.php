<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('register.php'));
}

if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('register.php'));
}

$fullName = register_clean_name(register_input('full_name'));
$studentNumber = trim(register_input('student_number'));
$email = normalize_email(register_input('email'));
$password = register_input('password');
$confirm = register_input('password_confirm');

$old = [
    'full_name' => $fullName,
    'student_number' => $studentNumber,
    'email' => $email,
];

$errors = [];

if (!preg_match("/^[\p{L}][\p{L}\s.'-]{1,99}$/u", $fullName)) {
    $errors[] = 'Enter your full name using letters, spaces, hyphens, or apostrophes.';
}

if (!is_valid_student_number($studentNumber)) {
    $errors[] = 'Use the format YYYY-NN-NNNNN, such as ' . STUDENT_NUMBER_EXAMPLE . '.';
}

if (!is_allowed_email_domain($email)) {
    $errors[] = 'Only @bicol-u.edu.ph email addresses can register.';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
} elseif (strlen($password) > 72) {
    $errors[] = 'Password must be at most 72 characters.';
}

if ($password !== $confirm) {
    $errors[] = 'Password and confirmation do not match.';
}

if ($errors === []) {
    if (user_email_exists($email)) {
        $errors[] = 'That email is already registered.';
    }

    if (user_find_by_student_number($studentNumber) !== null) {
        $errors[] = 'That student number is already registered.';
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        flash_set('error', $error);
    }
    flash_old($old);
    redirect(url('register.php'));
}

try {
    user_create([
        'student_number' => $studentNumber,
        'full_name' => $fullName,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => ROLE_STUDENT,
        'status' => USER_STATUS_ACTIVE,
    ]);
} catch (PDOException $exception) {
    if ($exception->getCode() !== '23000') {
        throw $exception;
    }

    flash_set('error', 'That email or student number is already registered.');
    flash_old($old);
    redirect(url('register.php'));
}

flash_set('success', 'Account created. You can log in.');
redirect(url('login.php'));

function register_input(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

function register_clean_name(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

    return $name;
}
