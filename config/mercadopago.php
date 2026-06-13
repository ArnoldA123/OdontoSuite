<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mercado Pago Configuration
    |--------------------------------------------------------------------------
    |
    | Credenciales para la integracion con Mercado Pago Checkout Bricks.
    | Se leen de las variables de entorno definidas en .env
    |
    | - MERCADOPAGO_ENVIRONMENT: 'sandbox' (testing) o 'production' (real)
    | - MERCADOPAGO_TEST_ACCESS_TOKEN: Access token de pruebas
    | - MERCADOPAGO_TEST_PUBLIC_KEY: Public key de pruebas
    | - MERCADOPAGO_PROD_ACCESS_TOKEN: Access token de produccion
    | - MERCADOPAGO_PROD_PUBLIC_KEY: Public key de produccion
    | - MERCADOPAGO_WEBHOOK_URL: URL publica para recibir notificaciones
    |   (en dev usar ngrok)
    |
    | Sprint 3 (plan #11): el usuario pego sus credenciales de sandbox
    | en .env. El admin puede sobreescribirlas desde /settings/payment-methods
    | via el campo gateway_config encriptado.
    |
    */

    'environment' => env('MERCADOPAGO_ENVIRONMENT', 'sandbox'),

    'test' => [
        'access_token' => env('MERCADOPAGO_TEST_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_TEST_PUBLIC_KEY'),
    ],

    'production' => [
        'access_token' => env('MERCADOPAGO_PROD_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PROD_PUBLIC_KEY'),
    ],

    'webhook_url' => env('MERCADOPAGO_WEBHOOK_URL', '/api/payments/webhooks/mercadopago'),

    /*
    |--------------------------------------------------------------------------
    | Nombre del sitio para mostrar en los pagos de MP
    |--------------------------------------------------------------------------
    */
    'site_name' => env('MERCADOPAGO_SITE_NAME', 'OdontoSuite'),
    'site_logo' => env('MERCADOPAGO_SITE_LOGO'),
    'back_urls' => [
        'success' => env('MERCADOPAGO_SUCCESS_URL', '/cash-register?payment=success'),
        'failure' => env('MERCADOPAGO_FAILURE_URL', '/cash-register?payment=failure'),
        'pending' => env('MERCADOPAGO_PENDING_URL', '/cash-register?payment=pending'),
    ],
];
