<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'bitwarden_api#prelogin',    'url' => '/api/prelogin',      'verb' => 'POST'],
        ['name' => 'bitwarden_api#login',        'url' => '/api/login',         'verb' => 'POST'],
        ['name' => 'bitwarden_api#refresh',      'url' => '/api/refresh',       'verb' => 'POST'],
        ['name' => 'bitwarden_api#sync',         'url' => '/api/sync',          'verb' => 'GET'],
        ['name' => 'bitwarden_api#getCiphers',   'url' => '/api/ciphers',       'verb' => 'GET'],
        ['name' => 'bitwarden_api#createCipher', 'url' => '/api/ciphers',       'verb' => 'POST'],
        ['name' => 'bitwarden_api#updateCipher', 'url' => '/api/ciphers/{id}',  'verb' => 'PUT'],
        ['name' => 'bitwarden_api#deleteCipher', 'url' => '/api/ciphers/{id}',  'verb' => 'DELETE'],
        ['name' => 'bitwarden_api#getFolders',   'url' => '/api/folders',       'verb' => 'GET'],
        ['name' => 'bitwarden_api#createFolder', 'url' => '/api/folders',       'verb' => 'POST'],
        ['name' => 'bitwarden_api#deleteFolder', 'url' => '/api/folders/{id}',  'verb' => 'DELETE'],

        ['name' => 'settings#getSettings',  'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'settings#saveSettings', 'url' => '/settings', 'verb' => 'POST'],
    ],
];
