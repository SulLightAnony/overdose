<?php
require_once __DIR__ . '/../../config/config.php';

// Parameter untuk OAuth 2.0 Google
$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid profile email',
    'prompt'        => 'select_account' // Selalu tampilkan pemilih akun Google
];

// Buat Auth URL Google
$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

// Arahkan browser user ke halaman login Google
header('Location: ' . $authUrl);
exit;