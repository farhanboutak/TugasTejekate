<?php

function simbar_username_in_use(PDO $pdo, string $username, int $excludeUserId = 0): bool
{
    $sql = 'SELECT id FROM users WHERE username = :username';
    $parameters = [':username' => $username];

    if ($excludeUserId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $parameters[':exclude_id'] = $excludeUserId;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($parameters);

    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function simbar_next_user_id(PDO $pdo): int
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('A transaction is required to allocate a user ID.');
    }

    $stmt = $pdo->query('SELECT id FROM users ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $lastId = $stmt->fetchColumn();
    $nextId = (int)$lastId + 1;
    if ($nextId > 2147483647) {
        throw new RuntimeException('The users table has reached its ID limit.');
    }

    return $nextId;
}

function simbar_is_duplicate_username(PDOException $exception): bool
{
    if (!isset($exception->errorInfo[1], $exception->errorInfo[2]) || (int)$exception->errorInfo[1] !== 1062) {
        return false;
    }

    return preg_match('/for key [\'"`](?:[^\'"`]*\.)?username[\'"`]/i', (string)$exception->errorInfo[2]) === 1;
}