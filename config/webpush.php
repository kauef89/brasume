<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:suporte@brasume.com.br'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem' => env('VAPID_PEM'),
    ],
    'model' => \NotificationChannels\WebPush\PushSubscription::class,
    'table_name' => 'push_subscriptions',
    'database_connection' => env('DB_CONNECTION', 'mysql'),
    'client_options' => [],
    'gcm' => [
        'key' => env('GCM_KEY'),
    ],
];