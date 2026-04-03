<?php

/**
 * requireLogin()
 * Redirects to /admin/login if the admin session is not set.
 */
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * isLoggedIn()
 * Returns true if an admin session exists.
 */
function isLoggedIn(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_user'])
        && is_array($_SESSION['admin_user'])
        && !empty($_SESSION['admin_user']['id']);
}

/**
 * getCurrentUser()
 * Returns the current admin user array or null if not authenticated.
 *
 * @return array{id: int, username: string, role: string}|null
 */
function getCurrentUser(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user'])) {
        return $_SESSION['admin_user'];
    }
    return null;
}

/**
 * logout()
 * Destroys the session and redirects to /admin/login.
 */
function logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}
