<?php

return [
    /** Repository Pagination Limit Default */
    'pagination' => [
        'limit' => 15
    ],

    /** Generator Config */
    'generator' => [
        'basePath' => app_path(),
        'rootNamespace' => 'App\\',
        'stubsOverridePath' => app_path(),
        'names' => [
            'models' => '<entity>',
            'repositories' => '<entity>RepositoryEloquent',
            'contracts' => '<entity>Repository',
            'requests' => '<entity><action>Request',
            'controllers' => '<entity>Controller'
        ],
        'paths' => [
            'models' => 'Models',
            'repositories' => 'Repositories/Eloquent',
            'contracts' => 'Repositories/Contracts',
            'requests' => 'Http/Requests',
            'controllers' => 'Http/Controllers',
            'provider' => 'RepositoryServiceProvider'
        ]
    ]
];
