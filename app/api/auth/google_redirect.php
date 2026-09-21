<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';

// Generate token CSRF state untuk keamanan OAuth
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid profile email',
    'prompt'        => 'select_account',
    'state'         => $_SESSION['oauth_state']
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;