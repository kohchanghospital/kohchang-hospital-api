<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',          // ⭐ เพิ่ม
        'logout',         // (เผื่ออนาคต)
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
    ],

    'allowed_headers' => ['*'],

    'supports_credentials' => true,

];
