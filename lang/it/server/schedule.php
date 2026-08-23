<?php

return [
    'title' => 'Pianificazioni',
    'new' => 'Nuova Pianificazione',
    'edit' => 'Modifica Pianificazione',
    'save' => 'Salva Pianificazione',
    'delete' => 'Elimina Pianificazione',
    'import' => 'Importa Pianificazione',
    'export' => 'Esporta Pianificazione',
    'name' => 'Nome',
    'cron' => 'Cron',
    'status' => 'Stato',
    'schedule_status' => [
        'new' => 'Nuovo',
        'inactive' => 'Inattiva',
        'processing' => 'In elaborazione',
        'active' => 'Attiva',
    ],
    'no_tasks' => 'Nessuna attività',
    'run_now' => 'Esegui ora',
    'online_only' => 'Solo Quando Online',
    'last_run' => 'Ultima Esecuzione',
    'next_run' => 'Prossima Esecuzione',
    'never' => 'Mai',
    'cancel' => 'Annulla',

    'only_online' => 'Solo quando il server è online?',
    'only_online_hint' => 'Esegue questa pianificazione solo quando il server è in stato di esecuzione.',
    'enabled' => 'Abilita pianificazione?',
    'enabled_hint' => 'Questa pianificazione verrà eseguita automaticamente se abilitata.',

    'cron_body' => 'Tieni presente che i campi cron qui sotto assumono sempre UTC.',
    'cron_timezone' => 'Prossima esecuzione nel tuo fuso orario (:timezone): <b> :next_run </b>',

    'invalid' => 'Non valido',

    'time' => [
        'minute' => 'Minuto',
        'hour' => 'Ora',
        'day' => 'Giorno',
        'week' => 'Settimana',
        'month' => 'Mese',
        'day_of_month' => 'Giorno del mese',
        'day_of_week' => 'Giorno della settimana',

        'hourly' => 'Ogni ora',
        'daily' => 'Ogni giorno',
        'weekly_mon' => 'Settimanale (lunedì)',
        'weekly_sun' => 'Settimanale (domenica)',
        'monthly' => 'Mensile',
        'every_min' => 'Ogni x minuti',
        'every_hour' => 'Ogni x ore',
        'every_day' => 'Ogni x giorni',
        'every_week' => 'Ogni x settimane',
        'every_month' => 'Ogni x mesi',
        'every_day_of_week' => 'Ogni x giorno della settimana',

        'every' => 'Ogni',
        'minutes' => 'Minuti',
        'hours' => 'Ore',
        'days' => 'Giorni',
        'months' => 'Mesi',

        'monday' => 'Lunedì',
        'tuesday' => 'Martedì',
        'wednesday' => 'Mercoledì',
        'thursday' => 'Giovedì',
        'friday' => 'Venerdì',
        'saturday' => 'Sabato',
        'sunday' => 'Domenica',
    ],

    'tasks' => [
        'title' => 'Task',
        'create' => 'Crea task',
        'limit' => 'Limite task raggiunto',
        'action' => 'Azione',
        'payload' => 'Payload',
        'no_payload' => 'Nessun payload',
        'time_offset' => 'Offset temporale',
        'first_task' => 'Primo task',
        'seconds' => 'Secondo|Secondi',
        'continue_on_failure' => 'Continua in caso di errore',

        'actions' => [
            'title' => 'Azione',
            'power' => [
                'title' => 'Invia azione di alimentazione',
                'action' => 'Azione di alimentazione',
                'start' => 'Avvia',
                'stop' => 'Arresta',
                'restart' => 'Riavvia',
                'kill' => 'Termina',
            ],
            'command' => [
                'title' => 'Invia comando',
                'command' => 'Comando',
            ],
            'backup' => [
                'title' => 'Crea Backup',
                'files_to_ignore' => 'File da ignorare',
            ],
            'delete_files' => [
                'title' => 'Elimina file',
                'files_to_delete' => 'File da eliminare',
            ],
        ],
    ],

    'notification_invalid_cron' => 'I dati cron forniti non risultano una espressione valida',

    'import_action' => [
        'file' => 'File',
        'url' => 'URL',
        'schedule_help' => 'Questo dovrebbe essere il file grezzo .json ( schedule-daily-restart.json )',
        'url_help' => 'Gli URL devono puntare direttamente al file .json grezzo',
        'add_url' => 'Nuovo URL',
        'import_failed' => 'Importazione fallita',
        'import_success' => 'Importazione Riuscita',
    ],
];
