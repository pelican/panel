<?php

return [
    'title' => 'Santé',
    'results_refreshed' => 'Les résultats du bilan de santé ont été mis à jour',
    'checked' => 'Résultats vérifiés à partir de :time',
    'refresh' => 'Actualiser',
    'results' => [
        'cache' => [
            'label' => 'Cache',
            'ok' => 'OK',
            'failed_retrieve' => 'Impossible de définir ou de récupérer une valeur de cache de l\'application.',
            'failed' => 'Une exception est survenue avec le cache de l\'application : :error',
        ],
        'database' => [
            'label' => 'Base de données',
            'ok' => 'OK',
            'failed' => 'Impossible de se connecter à la base de données: :error',
        ],
        'debugmode' => [
            'label' => 'Mode de débogage',
            'ok' => 'Le mode débogage est désactivé',
            'failed' => 'Le mode de débogage devait être :expected, mais était en fait :actual',
        ],
        'environment' => [
            'label' => 'Environnement',
            'ok' => 'Ok, réglé sur :actual',
            'failed' => 'L\'environnement est défini à :actual, attendu :expected',
        ],
        'nodeversions' => [
            'label' => 'Version du nœud',
            'ok' => 'Les nœuds sont à jour',
            'failed' => ':outdated/:all Nodes sont obsolètes',
            'no_nodes_created' => 'Aucun noeud créé',
            'no_nodes' => 'Aucun noeud',
            'all_up_to_date' => 'Tout est à jour',
            'outdated' => ':outdated/:all obsolète',
        ],
        'panelversion' => [
            'label' => 'Version du panel',
            'ok' => 'Votre panel est à jour',
            'failed' => 'La version installée est :currentVersion mais la dernière est :latestVersion',
            'up_to_date' => 'À jour',
            'outdated' => 'Obsolète',
            'unknown' => 'Inconnu',
            'unavailable' => 'La dernière version n\'a pas pu être récupérée depuis GitHub',
        ],
        'schedule' => [
            'label' => 'Planifier',
            'ok' => 'OK',
            'failed_last_ran' => 'La dernière exécution du calendrier a été plus de :time minutes il y a plus de :time',
            'failed_not_ran' => 'Le calendrier n\'a pas encore été exécuté.',
        ],
        'useddiskspace' => [
            'label' => 'Espace disque',
        ],
        'queue' => [
            'label' => 'File d\'attente',
        ],
    ],
    'checks' => [
        'successful' => 'Succès',
        'failed' => 'Échec',
    ],
];
