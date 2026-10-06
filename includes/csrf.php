<?php
/**
 * Cross-Site Request Forgery (CSRF) Protection Utilities.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get or generate the current CSRF token.
 *
 * @return string
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate a hidden HTML input field containing the CSRF token.
 *
 * @return string
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify that the submitted token matches the session token.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate CSRF token for state-changing requests or terminate execution with 403.
 *
 * @return void
 */
function validate_csrf_or_abort(): void
{
    if (!verify_csrf_token()) {
        http_response_code(403);
        if (function_exists('flash')) {
            flash('error', 'Security check failed (invalid or expired session token). Please try again.');
        }
        die('403 Forbidden: Invalid or missing CSRF token. Please refresh the page and try again.');
    }
}
