<?php

return [
    'title' => 'Zeitpläne',
    'new' => 'Neue Zeitpläne',
    'edit' => 'Zeitpläne Editieren',
    'save' => 'Zeitpläne Speichern',
    'delete' => 'Zeitpläne Löschen',
    'import' => 'Zeitpläne Importieren',
    'export' => 'Zeitpläne Exportieren',
    'name' => 'Name',
    'cron' => 'Cron',
    'status' => 'Status',
    'schedule_status' => [
        'new' => 'Neu',
        'inactive' => 'Inaktiv',
        'processing' => 'Verarbeiten',
        'active' => 'Aktiv',
    ],
    'no_tasks' => 'Keine Aufgaben',
    'run_now' => 'Jetzt ausführen',
    'online_only' => 'Nur wenn online',
    'last_run' => 'Zuletzt ausgeführt',
    'next_run' => 'Nächste Ausführung',
    'never' => 'Nie',
    'cancel' => 'Abbrechen',

    'only_online' => 'Nur, wenn der Server online ist?',
    'only_online_hint' => 'Führen Sie diesen Zeitplan nur aus, wenn der Server ausgeführt wird.',
    'enabled' => 'Zeitplan aktivieren?',
    'enabled_hint' => 'Dieser Zeitplan wird automatisch ausgeführt, wenn er aktiviert ist.',

    'cron_body' => 'Bitte beachten Sie, dass die folgenden Kron-Eingaben immer von UTC ausgehen.',
    'cron_timezone' => 'Nächster Lauf in Ihrer Zeitzone (: time Zone): <b> :next_run </b>',

    'invalid' => 'Ungültig',

    'time' => [
        'minute' => 'Minuten',
        'hour' => 'Stunden',
        'day' => 'Tag',
        'week' => 'Woche',
        'month' => 'Monat',
        'day_of_month' => 'Tag das Monats',
        'day_of_week' => 'Tag der Woche',

        'hourly' => 'Stündlich',
        'daily' => 'Täglich',
        'weekly_mon' => 'Wöchentlich(jeden Montag)',
        'weekly_sun' => 'Wöchentlich(jeden Sonntag)',
        'monthly' => 'Monatlich',
        'every_min' => 'Alle x Minuten',
        'every_hour' => 'Alle x Stunden',
        'every_day' => 'Alle x Tage',
        'every_week' => 'Alle x Wochen',
        'every_month' => 'Alle x Monate',
        'every_day_of_week' => 'Alle x Tage der Woche',

        'every' => 'Alle',
        'minutes' => 'Minuten',
        'hours' => 'Stunden',
        'days' => 'Tage',
        'months' => 'Monate',

        'monday' => 'Montag',
        'tuesday' => 'Dienstag',
        'wednesday' => 'Mittwoch',
        'thursday' => 'Donnerstag',
        'friday' => 'Freitag',
        'saturday' => 'Samstag',
        'sunday' => 'Sonntag',
    ],

    'tasks' => [
        'title' => 'Aufgaben',
        'create' => 'Aufgabe erstellen',
        'limit' => 'Aufgaben Limit erreicht',
        'action' => 'Aktion',
        'payload' => '',
        'no_payload' => 'Keine Daten',
        'time_offset' => 'Zeit Verschiebung',
        'first_task' => 'Erste Aufgabe',
        'seconds' => 'Sekunden',
        'continue_on_failure' => 'Bei Fehlern fortführen',

        'actions' => [
            'title' => 'Aktion',
            'power' => [
                'title' => 'Server Kontrolle',
                'action' => 'Server Steuerungs Aktion',
                'start' => 'Starten',
                'stop' => 'Stoppen',
                'restart' => 'Neustarten',
                'kill' => 'Killen',
            ],
            'command' => [
                'title' => 'Befehl senden',
                'command' => 'Befehl',
            ],
            'backup' => [
                'title' => 'Backup erstellen',
                'files_to_ignore' => 'Datein zum ignorieren',
            ],
            'delete_files' => [
                'title' => 'Dateien löschen',
                'files_to_delete' => 'Dateien zum Löschen',
            ],
        ],
    ],

    'notification_invalid_cron' => 'Die bereitgestellten Kron-Daten ergeben keinen gültigen Ausdruck',

    'import_action' => [
        'file' => 'Datei',
        'url' => 'URL',
        'schedule_help' => 'Dies sollte die rohe JSON-Datei sein (schedule-daily-restart.json).',
        'url_help' => 'URLs müssen direkt auf die rohe .json-Datei verweisen.',
        'add_url' => 'Neue URL',
        'import_failed' => 'Import fehlgeschlagen',
        'import_success' => 'Import erfolgreich',
    ],
];
