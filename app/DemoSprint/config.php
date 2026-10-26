<?php

declare(strict_types=1);

/**
 * Configuration centrale. Aucun secret en dur : tout passe par des variables d'environnement.
 *   export JIRA_TOKEN=...   (et éventuellement CA_BUNDLE=/chemin/ca-entreprise.pem)
 */
return [
    'jira' => [
        'base_url'  => getenv('JIRA_BASE_URL') ?: 'https://gojira.enedis.fr',
        'token'     => getenv('JIRA_TOKEN') ?: '',
        'ca_bundle' => getenv('CA_BUNDLE') ?: null,
        'timeout'   => 30,
        'board_id'  => 4,
        'project'   => 'B4A',
        'story_points_field' => 'customfield_10106',
    ],

    // Source de vérité optionnelle pour les descriptions de SI (tableau 2 colonnes : SI | description)
    'confluence' => [
        'enabled'  => false,
        'base_url' => getenv('CONFLUENCE_BASE_URL') ?: 'https://goconfluence.enedis.fr',
        'token'    => getenv('CONFLUENCE_TOKEN') ?: '',
        'space'    => 'B4A',
        'si_page'  => 'Catalogue des SI',
    ],

    'tickets' => [
        'type_order'      => ['User Story', 'User Story Enabler'], // l'ordre = ordre d'apparition
        'done_statuses'   => ['Terminé'],
        'review_statuses' => ['A tester', 'Recette', 'En recette'],
        'stats_types'     => ['User Story', 'User Story Enabler'],
    ],

    // Libellés (col. 1 du tableau de la description Jira) => identifiant interne
    'labels' => [
        'objectif'      => 'Objectif',
        'consommateurs' => 'Consommateurs',
        'path'          => 'PATH',
        'hive'          => 'HIVE',
        'type_flux'     => 'Type de flux',
        'topic'         => 'Topic',
        'choree'        => 'Chorée',
    ],
    // Champs "techniques" : on ne touche pas aux * (ex: /data/*/input)
    'code_fields' => ['path', 'hive'],

    'roles' => [
        'po'    => ['logins' => ['I10483'], 'names' => ['Romain']],
        'scrum' => ['logins' => ['AM3DF69N'], 'names' => ['Ana']],
    ],

    // Un ticket ajouté moins de N minutes après le démarrage du sprint n'est pas compté « ajouté en cours de sprint »
    'added_grace_minutes' => 240,

    // Surcharge optionnelle ; par défaut on utilise displayName renvoyé par Jira
    'user_overrides' => [
        'KS50A71L' => 'Kenza',
        'AJ2FF66L' => 'Arij',
        'AM3DF69N' => 'Ana',
        'TW560FCL' => 'Ting',
    ],

    'si_descriptions' => [
        'INGENG'     => "Outil d'ingestion de données pour le cloud",
        'DLK'        => "",
        'HDR'        => "HDR est un SI dont le cœur de métier est de connaitre l’intégralité de la topologie réseau détenu par ENEDIS. De l’aval connecté à RTE aux PRM représentants les compteurs linky.",
        'CINKE-EVOL' => "Le projet Cinke-Evolution vise à remplacer les applications historiques de bureau et les applications mobiles du technicien qui permettent de traiter la préparation et la programmation des activités réseau et clientèle jusqu’à l’intervention sur le terrain.",
        'DATALAKERH' => "Données patrimoniales RH issues du SIRH Corporate.",
    ],

    'theme' => [
        'colors' => [
            'blue' => 'FF1423DC', 'teal' => 'FF1ABC9C', 'purple' => 'FF8E44AD', 'grey' => 'FF7F8C8D',
            'greyText' => 'FF595959', 'track' => 'FFE0E0E0', 'black' => 'FF000000', 'white' => 'FFFFFFFF',
            'new' => 'FF339898', 'kafka' => 'FF8E44AD', 'file' => 'FFE67E22',
            'pathFill' => 'FFFFE6F2', 'pathBorder' => 'FFD87093',
            'hiveFill' => 'FFF0E6FF', 'hiveBorder' => 'FF9370DB',
        ],
        'fonts' => ['title' => 'Enedis', 'body' => 'Public Sans Light', 'mono' => 'Consolas'],
    ],

    'output_dir'    => __DIR__ . '/out',
    'max_objective' => 300,
];
