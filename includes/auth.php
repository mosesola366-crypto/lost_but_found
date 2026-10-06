<?php
/**
 * Session helpers shared across all pages.
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function is_logged_in()
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function is_admin()
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function check_account_status()
{
    global $pdo;
    if (isset($pdo) && !empty($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $status = $stmt->fetchColumn();

            if ($status === 'suspended') {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params['path'], $params['domain'],
                        $params['secure'], $params['httponly']
                    );
                }
                session_destroy();
                session_start();
                flash('error', 'Your account has been suspended by the Security Unit. Please contact an administrator.');
                $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
                $loginUrl = str_contains($script, '/admin/') ? '../login.php' : 'login.php';
                header("Location: {$loginUrl}");
                exit;
            }
        } catch (PDOException $e) {
            // Silently ignore if table is not yet created
        }
    }
}

function require_login()
{
    if (!is_logged_in()) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $loginUrl = str_contains($script, '/admin/') ? '../login.php' : 'login.php';
        header("Location: {$loginUrl}");
        exit;
    }
    check_account_status();
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Your form session has expired. Please go back, refresh the page, and try again.');
    }
}

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

function require_admin()
{
    if (!is_admin()) {
        header('Location: ../login.php');
        exit;
    }
    check_account_status();
}

function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}
