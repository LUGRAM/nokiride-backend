<?php

return [
    'provider' => env('OTP_PROVIDER', 'fake'),
    'length' => (int) env('OTP_LENGTH', 6),
    'expires_minutes' => (int) env('OTP_EXPIRES_MINUTES', 5),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
];
