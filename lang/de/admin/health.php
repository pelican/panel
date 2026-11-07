<?php

return [
    'title' => 'Status',
    'results_refreshed' => 'Ergebnisse der Statusprüfung aktualisiert',
    'checked' => 'Ergebnisse von :time',
    'refresh' => 'Aktualisieren',
    'results' => [
        'cache' => [
            'label' => 'Cache',
            'ok' => 'Ok',
            'failed_retrieve' => 'Konnte keinen App Cache Wert setzen oder abrufen.',
            'failed' => 'Ein Fehler ist mit dem App Cache aufgetreten: :error',
        ],
        'database' => [
            'label' => 'Datenbank',
            'ok' => 'Ok',
            'failed' => 'Verbindung zur Datenbank konnte nicht hergestellt werden: :error',
        ],
        'debugmode' => [
            'label' => 'Debug Modus',
            'ok' => 'Debug Modus ist deaktiviert',
            'failed' => 'Der Debug Modus sollte :expected sein, ist aber tatsächlich :actual',
        ],
        'environment' => [
            'label' => 'Umgebung',
            'ok' => 'Ok, ist :actual',
            'failed' => 'Umgebung ist auf :actual gesetzt, :expected erwartet',
        ],
        'nodeversions' => [
            'label' => 'Node Versionen',
            'ok' => 'Nodes sind aktuell',
            'failed' => ':outdated/:all Nodes sind veraltet',
            'no_nodes_created' => 'Keine Nodes gefunden',
            'no_nodes' => 'Keine Nodes',
            'all_up_to_date' => 'Alle aktuell',
            'outdated' => ':outdated/:all veraltet',
        ],
        'panelversion' => [
            'label' => 'Panel Version',
            'ok' => 'Panel ist aktuell',
            'failed' => 'Installierte Version ist :currentVersion, die neueste Version ist allerdings :latestVersion',
            'up_to_date' => 'Aktuell',
            'outdated' => 'Veraltet',
            'unknown' => 'Unbekannt',
            'unavailable' => 'Die neueste Version konnte nicht von GitHub geladen werden',
        ],
        'schedule' => [
            'label' => 'Schedule',
            'ok' => 'Ok',
            'failed_last_ran' => 'Der letzte Durchlauf des Schedulers war vor mehr als :time Minuten',
            'failed_not_ran' => 'Der Scheduler wurde noch nicht ausgeführt.',
        ],
        'useddiskspace' => [
            'label' => 'Speicherplatz',
        ],
        'queue' => [
            'label' => 'Warteschlange',
        ],
    ],
    'checks' => [
        'successful' => 'Erfolgreich',
        'failed' => 'Fehlgeschlagen',
    ],
];
