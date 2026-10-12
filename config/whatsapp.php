<?php

return [
    // log: hanya dicatat di laravel.log. fonnte: kirim lewat gateway Fonnte.
    'driver' => env('WHATSAPP_DRIVER', 'log'),
    'url' => env('WHATSAPP_URL', 'https://api.fonnte.com/send'),
    'token' => env('WHATSAPP_TOKEN'),
];