<?php

$DATABASE_URL=parse_url("postgres://wcrbaarmpseixj:1bff2c9412ee9476bf5bdd3a6f234b06001a5ad2d4f494e738009f3315dc9492@ec2-54-236-169-55.compute-1.amazonaws.com:5432/db0q936stg63at");
return [
    'default' => 'pgsql',
    'connections' => [
        'mysql' => [
            'driver' => 'pgsql',
            'host' => $DATABASE_URL["host"], 
            'port' => $DATABASE_URL["port"],
            'database' => ltrim($DATABASE_URL["path"], "/"),
            'username' => $DATABASE_URL["user"],
            'password' => $DATABASE_URL["pass"],
            'charset'   => 'utf8',
            'prefix'  => '',
            'prefix_indexes' => true, 
            'schema' => 'public',
            'sslmode' => 'prefer'
        ]
    ]
];