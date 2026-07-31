<?php

return [
    'auth' => [
        'max_failed_login_attempts' => (int) env('SECURITY_MAX_FAILED_LOGIN_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('SECURITY_LOCKOUT_MINUTES', 15),
        'mfa_challenge_minutes' => (int) env('SECURITY_MFA_CHALLENGE_MINUTES', 5),
    ],

    'tokens' => [
        'web_token_lifetime_minutes' => (int) env('SECURITY_WEB_TOKEN_LIFETIME_MINUTES', 720),
    ],

    'rate_limits' => [
        'api_per_minute' => (int) env('SECURITY_API_RATE_LIMIT_PER_MINUTE', 120),
        'login_per_minute' => (int) env('SECURITY_LOGIN_RATE_LIMIT_PER_MINUTE', 10),
        'mfa_per_minute' => (int) env('SECURITY_MFA_RATE_LIMIT_PER_MINUTE', 8),
    ],

    'headers' => [
        'content_security_policy' => env(
            'SECURITY_CONTENT_SECURITY_POLICY',
            "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",
        ),
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=(self)',
        ),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],
];
