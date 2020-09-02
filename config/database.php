<?php

return [
    'default' => 'pgsql',
    'migrations' => 'migrations', 
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST'),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
        ]
    ]
];