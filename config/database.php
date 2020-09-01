$DATABASE_URL=parse_url(‘DATABASE_URL’);
return [
    'default' => 'pgsql',
    'connections' => [
        'mysql' => [
            'driver' => 'pgsql',
            'host' => $DATABASE_URL["host"], 
            'port' => $DATABASE_URL["port"],
            'database' => ltrim($DATABASE_URL["path"], "/")
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