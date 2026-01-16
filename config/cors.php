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
        'http://localhost:5173', // Vue.js
        'http://localhost:3000', // Next.js
    ],

    'allowed_headers' => ['*'],

    'supports_credentials' => true,

];
