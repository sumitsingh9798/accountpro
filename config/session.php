<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}
function current_company_id(): int {
    return (int)($_SESSION['company_id'] ?? 0);
}
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}
