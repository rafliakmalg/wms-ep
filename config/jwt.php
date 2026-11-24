<?php

return [
    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', base_path('../keys/jwt-private.pem')),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', base_path('../keys/jwt-public.pem')),
    'ttl' => (int) env('JWT_TTL', 3600),
];
