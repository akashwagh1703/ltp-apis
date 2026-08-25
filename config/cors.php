<?php

return [
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(
            ',',
            (string) env(
                'CORS_ALLOWED_ORIGINS',
                'https://playltp.in,https://www.playltp.in,https://admin.playltp.in,https://staging.playltp.in,https://admin-staging.playltp.in,http://localhost:5173,http://127.0.0.1:5173'
            )
        )
    ))),
];
