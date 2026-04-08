<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nextcloud Talk Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Nextcloud Talk integration to send messages to chats
    |
    */

    'url' => env('NEXTCLOUD_URL', ''),
    'username' => env('NEXTCLOUD_USERNAME', ''),
    'password' => env('NEXTCLOUD_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Nextcloud Talk Room Token
    |--------------------------------------------------------------------------
    |
    | The token of the Nextcloud Talk room where roster notifications should be sent.
    | You can find the token in the URL when you open the chat in Nextcloud Talk.
    | Example: https://nextcloud.example.com/call/TOKEN_HERE
    |
    */
    'roster_chat_token' => env('NEXTCLOUD_ROSTER_CHAT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Nextcloud Talk Integration
    |--------------------------------------------------------------------------
    |
    | Set to true to enable Nextcloud Talk integration
    |
    */
    'enabled' => env('NEXTCLOUD_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Nextcloud Personal – Personalakte-Dokumentenmanagement
    |--------------------------------------------------------------------------
    |
    | Separate Konfiguration für die Personalakte-Integration.
    | Alle Dateioperationen werden asynchron über Queue-Jobs abgewickelt.
    |
    */
    'personal' => [
        'url'       => env('NEXTCLOUD_PERSONAL_URL', env('NEXTCLOUD_URL', '')),
        'username'  => env('NEXTCLOUD_PERSONAL_USERNAME', env('NEXTCLOUD_USERNAME', '')),
        'password'  => env('NEXTCLOUD_PERSONAL_PASSWORD', env('NEXTCLOUD_PASSWORD', '')),
        'enabled'   => env('NEXTCLOUD_PERSONAL_ENABLED', false),
        'base_path' => env('NEXTCLOUD_PERSONAL_BASE_PATH', '/Personal'),
    ],
];
