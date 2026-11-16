<?php

declare(strict_types=1);

/**
 * Users table. Prepared statements only.
 * password_hash is returned only by user_find_by_email(), for password_verify() at login.
 */

function user_assert_role(string $role): void
{
    if ($role !== ROLE_ADMIN && $role !== ROLE_STUDENT) {
        throw new InvalidArgumentException('Invalid role.');
    }
}

function user_assert_status(string $status): void
{
    if ($status !== USER_STATUS_ACTIVE && $status !== USER_STATUS_INACTIVE) {
        throw new InvalidArgumentException('Invalid status.');
    }
}

/**
 * @return array<string, mixed>|null
 */
function user_find_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT user_id, student_number, full_name, email, role, status, created_at, updated_at
         FROM users
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $id]);
    $user = $stmt->fetch();

    return $user === false ? null : $user;
}

/**
 * Includes password_hash so the login handler can call password_verify().
 * Do not pass this array to a view.
 *
 * @return array<string, mixed>|null
 */
function user_find_by_email(string $email): ?array
{
    $email = normalize_email($email);

    $stmt = db()->prepare(
        'SELECT user_id, student_number, full_name, email, password_hash, role, status, created_at, updated_at
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user === false ? null : $user;
}

/**
 * @return array<string, mixed>|null
 */
function user_find_by_student_number(string $studentNumber): ?array
{
    if ($studentNumber === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT user_id, student_number, full_name, email, role, status, created_at, updated_at
         FROM users
         WHERE student_number = :student_number
         LIMIT 1'
    );
    $stmt->execute(['student_number' => $studentNumber]);
    $user = $stmt->fetch();

    return $user === false ? null : $user;
}

function user_email_exists(string $email, ?int $exceptId = null): bool
{
    $email = normalize_email($email);
    $sql = 'SELECT user_id FROM users WHERE email = :email';
    $params = ['email' => $email];

    if ($exceptId !== null) {
        $sql .= ' AND user_id <> :user_id';
        $params['user_id'] = $exceptId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetch() !== false;
}

/**
 * @param array{
 *   student_number?: string|null,
 *   full_name: string,
 *   email: string,
 *   password_hash: string,
 *   role?: string,
 *   status?: string
 * } $data
 */
function user_create(array $data): int
{
    $studentNumber = $data['student_number'] ?? null;
    if ($studentNumber === '') {
        $studentNumber = null;
    }

    if (array_key_exists('email', $data)) {
        $data['email'] = normalize_email((string) $data['email']);
    }

    $role = $data['role'] ?? ROLE_STUDENT;
    $status = $data['status'] ?? USER_STATUS_ACTIVE;
    user_assert_role($role);
    user_assert_status($status);

    $stmt = db()->prepare(
        'INSERT INTO users (student_number, full_name, email, password_hash, role, status)
         VALUES (:student_number, :full_name, :email, :password_hash, :role, :status)'
    );

    if ($studentNumber === null) {
        $stmt->bindValue(':student_number', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':student_number', $studentNumber, PDO::PARAM_STR);
    }

    $stmt->bindValue(':full_name', $data['full_name'], PDO::PARAM_STR);
    $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
    $stmt->bindValue(':password_hash', $data['password_hash'], PDO::PARAM_STR);
    $stmt->bindValue(':role', $role, PDO::PARAM_STR);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->execute();

    return (int) db()->lastInsertId();
}

/**
 * Update only the columns present in $data. password_hash is optional.
 *
 * @param array<string, string|null> $data
 */
function user_update(int $id, array $data): bool
{
    if (array_key_exists('email', $data)) {
        $data['email'] = normalize_email((string) $data['email']);
    }

    $allowed = ['student_number', 'full_name', 'email', 'password_hash', 'role', 'status'];
    $sets = [];
    $params = ['user_id' => $id];

    foreach ($allowed as $column) {
        if (!array_key_exists($column, $data)) {
            continue;
        }

        if ($column === 'role') {
            user_assert_role((string) $data[$column]);
        }

        if ($column === 'status') {
            user_assert_status((string) $data[$column]);
        }

        $sets[] = $column . ' = :' . $column;
        $params[$column] = $data[$column] === '' && $column === 'student_number' ? null : $data[$column];
    }

    if ($sets === []) {
        return false;
    }

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE user_id = :user_id';
    $stmt = db()->prepare($sql);

    foreach ($params as $name => $value) {
        if ($value === null) {
            $stmt->bindValue(':' . $name, null, PDO::PARAM_NULL);
            continue;
        }

        $type = $name === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue(':' . $name, $value, $type);
    }

    $stmt->execute();

    return true;
}

function user_set_status(int $id, string $status): bool
{
    user_assert_status($status);

    $stmt = db()->prepare('UPDATE users SET status = :status WHERE user_id = :user_id');
    $stmt->execute([
        'status' => $status,
        'user_id' => $id,
    ]);

    return true;
}

/**
 * @return list<array<string, mixed>>
 */
function user_all(): array
{
    $stmt = db()->query(
        'SELECT user_id, student_number, full_name, email, role, status, created_at, updated_at
         FROM users
         ORDER BY full_name ASC'
    );

    return $stmt->fetchAll();
}

/**
 * @return array{Admin: int, Student: int}
 */
function user_count_by_role(): array
{
    $counts = [
        ROLE_ADMIN => 0,
        ROLE_STUDENT => 0,
    ];

    $stmt = db()->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');

    foreach ($stmt->fetchAll() as $row) {
        $role = (string) $row['role'];
        if (array_key_exists($role, $counts)) {
            $counts[$role] = (int) $row['total'];
        }
    }

    return $counts;
}
