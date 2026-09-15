<?php

return [
    'password_reset_enabled' => (bool) env('PASSWORD_RESET_ENABLED', false),

    'headers_enabled' => (bool) env('SECURITY_HEADERS_ENABLED', false),

    'max_active_users' => (int) env('SECURITY_MAX_ACTIVE_USERS', 10),

    'content_security_policy' => env(
        'SECURITY_CONTENT_POLICY',
        "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org; font-src 'self' data:; connect-src 'self'",
    ),
];
