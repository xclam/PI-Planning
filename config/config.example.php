<?php
return [
    'database' => [
        'host' => 'localhost',
        'name' => 'piplanning',
        'user' => 'root',
        'pass' => ''
    ],
    'jira' => [
        'base_url' => 'https://gojira.enedis.fr/',
        'bearer' => 'VOTRE_TOKEN_JIRA',
        'project_key' => 'B4A',
        'art_board_id' => 11,
        'team_board_id' => 4,
    ],
    'confluence' => [
        'base_url' => 'https://goconfluence.enedis.fr/',
        'bearer' => 'VOTRE_TOKEN_CONFLUENCE'
    ],
    'openrouter' => [
        'api_key' => 'VOTRE_CLE_OPENROUTER',
        'model' => 'meta-llama/llama-3.3-70b-instruct'
    ]
];