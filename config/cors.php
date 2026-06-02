<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The SPA frontend (Vite dev server) calls this API from a different
    | origin. Auth uses Bearer tokens (not cookies), so we do not need
    | credentialed requests and can safely allow any origin.
    |
    */

    'paths' => ['api/*', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
