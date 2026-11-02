<?php

const REMEMBER_COOKIE_NAME = 'simbar_remember';
const REMEMBER_TOKEN_LIFETIME = 2592000;

function remember_cookie_options(int $expires): array
{
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off';

    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function remember_clear_cookie(PDO $pdo): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (is_string($cookie) && preg_match('/\A[a-f0-9]{32}:[a-f0-9]{64}\z/', $cookie) === 1) {
        [$selector] = explode(':', $cookie, 2);
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute([':selector' => $selector]);
    }

    setcookie(REMEMBER_COOKIE_NAME, '', remember_cookie_options(time() - 3600));
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

function remember_issue_token(PDO $pdo, int $userId): void
{
    $currentCookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (is_string($currentCookie) && preg_match('/\A([a-f0-9]{32}):[a-f0-9]{64}\z/', $currentCookie, $matches) === 1) {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute([':selector' => $matches[1]]);
    }
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);

    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_TOKEN_LIFETIME;

    $stmt = $pdo->prepare(
        'INSERT INTO remember_tokens (selector, user_id, token_hash, expires_at)
         VALUES (:selector, :user_id, :token_hash, FROM_UNIXTIME(:expires_at))'
    );
    $stmt->execute([
        ':selector' => $selector,
        ':user_id' => $userId,
        ':token_hash' => hash('sha256', $validator),
        ':expires_at' => $expires,
    ]);

    setcookie(
        REMEMBER_COOKIE_NAME,
        $selector . ':' . $validator,
        remember_cookie_options($expires)
    );
}

function remember_restore_session(PDO $pdo): void
{
    if (isset($_SESSION['user_id'])) {
        return;
    }

    $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (!is_string($cookie) || preg_match('/\A([a-f0-9]{32}):([a-f0-9]{64})\z/', $cookie, $matches) !== 1) {
        if ($cookie !== '') {
            remember_clear_cookie($pdo);
        }
        return;
    }

    $selector = $matches[1];
    $validator = $matches[2];
    $stmt = $pdo->prepare(
        'SELECT t.user_id, t.token_hash, u.nama, u.username, u.role
         FROM remember_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.selector = :selector AND t.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([':selector' => $selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token || !hash_equals($token['token_hash'], hash('sha256', $validator))) {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute([':selector' => $selector]);
        remember_clear_cookie($pdo);
        return;
    }

    $nextValidator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_TOKEN_LIFETIME;
    $stmt = $pdo->prepare(
        'UPDATE remember_tokens
         SET token_hash = :next_hash, expires_at = FROM_UNIXTIME(:expires_at)
         WHERE selector = :selector AND token_hash = :current_hash AND expires_at > NOW()'
    );
    $stmt->execute([
        ':next_hash' => hash('sha256', $nextValidator),
        ':expires_at' => $expires,
        ':selector' => $selector,
        ':current_hash' => $token['token_hash'],
    ]);

    if ($stmt->rowCount() !== 1) {
        remember_clear_cookie($pdo);
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$token['user_id'];
    $_SESSION['nama'] = $token['nama'];
    $_SESSION['username'] = $token['username'];
    $_SESSION['role'] = $token['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if (in_array($token['role'], ['admin', 'petugas'], true)) {
        require_once __DIR__ . '/audit.php';
        try {
            catat_aktivitas($pdo, 'login.diingat', 'auth', 'Sesi login dipulihkan dari perangkat tepercaya.', (int)$token['user_id'], [], [
                'id' => (int)$token['user_id'],
                'nama' => $token['nama'],
                'role' => $token['role'],
            ]);
        } catch (PDOException $e) {
            error_log('SIMBAR remembered login audit write failed: ' . $e->getMessage());
        }
    }

    setcookie(
        REMEMBER_COOKIE_NAME,
        $selector . ':' . $nextValidator,
        remember_cookie_options($expires)
    );
}
