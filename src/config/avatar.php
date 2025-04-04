<?php

return [
    'settings' => [
        'baseUrl' => $_ENV['AVATAR_BASE_URL'] ?? 'https://api.dicebear.com/7.x',
        'style' => $_ENV['AVATAR_STYLE'] ?? 'personas',
        'backgroundColor' => $_ENV['AVATAR_BG_COLOR'] ?? '4f46e5',
        'size' => $_ENV['AVATAR_SIZE'] ?? 128,
        'defaultPath' => $_ENV['AVATAR_DEFAULT_PATH'] ?? '/uploads/avatars/',
        'allowedTypes' => ['image/jpeg', 'image/png', 'image/gif'],
        'maxFileSize' => $_ENV['AVATAR_MAX_SIZE'] ?? 5242880, // 5MB
    ]
];