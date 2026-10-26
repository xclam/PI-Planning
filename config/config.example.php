<?php
/*
 * Copier vers config/config.php (jamais versionné).
 * Toute clé scalaire peut être surchargée par variable d'environnement :
 * PIPLANNING_JIRA_BEARER, PIPLANNING_DB_PASS, PIPLANNING_APP_DEBUG, ...
 *
 * ⚠ La clé s'appelle bien "db" (l'ancien exemple utilisait "database",
 *   ce que le code ne lisait pas → d'où les valeurs par défaut en dur).
 * ⚠ L'ancien mot de passe MySQL était écrit en dur dans Database.php :
 *   il est dans l'historique git, à changer.
 */
return [
    'app' => ['debug' => false],

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'piplanning',
        'user' => 'piplanning',
        'pass' => '',
    ],

    // Vérification TLS activée par défaut. Si la CA interne pose problème,
    // renseigner ca_bundle plutôt que de désactiver la vérification.
    'http' => [
        'verify_ssl' => true,
        'ca_bundle'  => null, // ex: '/etc/ssl/certs/ca-interne.pem'
        'timeout'    => 30,
    ],

    'jira' => [
        'base_url'              => 'https://gojira.enedis.fr/',
        'bearer'                => '',
        'project_key'           => 'B4A',
        'art_board_id'          => 11,
        'team_board_id'         => 4,
        'username'              => '', // pour "Mes tickets"
        'us_issuetype_id'       => '10001',
        'enabler_issuetype_id'  => '10007',
        'us_extra_fields'       => ['customfield_10107' => ['id' => '10003']],
        'link_types'            => ['Relates', 'Relatif', 'Concerne', 'Lien'],
        'story_points_field'    => 'customfield_10106',
    ],

    'confluence' => [
        'base_url'         => 'https://goconfluence.enedis.fr/',
        'bearer'           => '',
        'init_empty_pages' => true, // écrit un squelette dans les pages Confluence vides
    ],

    'openrouter' => [
        'api_key'               => '',
        'model'                 => 'meta-llama/llama-3.3-70b-instruct',
        'max_calls_per_request' => 15,
    ],

    'us' => [
        'hdfs_base' => '/projects/PRD/HAS/DLK/trusted-zone',
    ],
];