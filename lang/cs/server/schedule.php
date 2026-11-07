<?php

return [
    'title' => 'Rozvrh',
    'new' => 'Nový rozvrh',
    'edit' => 'Upravit Rozvrh',
    'save' => 'Uložit rozvrh',
    'delete' => 'Smazat rozvrh',
    'import' => 'Importovat rozvrh',
    'export' => 'Exportovat rozvrh',
    'name' => 'Název',
    'cron' => 'Cron',
    'status' => 'Stav',
    'schedule_status' => [
        'new' => 'Nové',
        'inactive' => 'Neaktivní',
        'processing' => 'Zpracovávání',
        'active' => 'Aktivní',
    ],
    'no_tasks' => 'Žádné úlohy',
    'run_now' => 'Spustit teď',
    'online_only' => 'Pouze když je online',
    'last_run' => 'Poslední spuštění',
    'next_run' => 'Další spuštění',
    'never' => 'nikdy',
    'cancel' => 'Zrušit',

    'only_online' => 'Jen když je server online?',
    'only_online_hint' => 'Spustit tento plán pouze tehdy, když je server ve stavu běžící.',
    'enabled' => 'Povolit rozvrh?',
    'enabled_hint' => 'Tento plán bude proveden automaticky, pokud je povoleno.',

    'cron_body' => 'Níže uvedená pole cronu používají vaše časové pásmo.',
    'cron_timezone' => 'Další spuštění ve Vašem časovém pásmu (:timezone): <b> :next_run </b>',

    'invalid' => 'Neplatný',

    'time' => [
        'minute' => 'Minut',
        'hour' => 'Hodina',
        'day' => 'Den',
        'week' => 'Týden',
        'month' => 'Měsíc',
        'day_of_month' => 'Den v měsíci',
        'day_of_week' => 'Den v týdnu',

        'hourly' => 'Hodina',
        'daily' => 'Denně',
        'weekly_mon' => 'Týdně (pondělí)',
        'weekly_sun' => 'Týdně (Neděle)',
        'monthly' => 'Měsíčně',
        'every_min' => 'Každých x minut',
        'every_hour' => 'Každých x hodin',
        'every_day' => 'Každých x dní',
        'every_week' => 'Každých x týdnů',
        'every_month' => 'Každých x měsíců',
        'every_day_of_week' => 'Každý x den v týdnu.',

        'every' => 'Každý',
        'minutes' => 'Minuta',
        'hours' => 'Hodina',
        'days' => 'Den',
        'months' => 'Měsíc',

        'monday' => 'Pondělí',
        'tuesday' => 'Úterý',
        'wednesday' => 'Středa',
        'thursday' => 'Čtvrtek',
        'friday' => 'Pátek',
        'saturday' => 'Sobota',
        'sunday' => 'Neděle',
    ],

    'tasks' => [
        'title' => 'Úkoly',
        'create' => 'Vytvořit úkol',
        'limit' => 'Dosažen limit úkolu',
        'action' => 'Akce',
        'payload' => 'Uspořádání',
        'no_payload' => 'Žádná data',
        'time_offset' => 'Časový posun',
        'first_task' => 'První úloha',
        'seconds' => 'Sekunda',
        'continue_on_failure' => 'Pokračovat při selhání',
        'no_permission' => 'Nemáte oprávnění používat tuto akci úkolu.',

        'actions' => [
            'title' => 'Akce',
            'power' => [
                'title' => 'Poslat akci napájení',
                'action' => 'Aktivace napájení',
                'start' => 'Spustit',
                'stop' => 'Vypnout',
                'restart' => 'Restartovat',
                'kill' => 'Ukončit',
            ],
            'command' => [
                'title' => 'Poslat příkaz',
                'command' => 'Příkaz',
            ],
            'backup' => [
                'title' => 'Vytvořit zálohu',
                'files_to_ignore' => 'Ignorovat soubory',
            ],
            'delete_files' => [
                'title' => 'Odstranit soubory',
                'files_to_delete' => 'Soubory k odstranění',
            ],
        ],
    ],

    'notification_invalid_cron' => 'Poskytnutá data cron se nehodnotí na správný výraz',

    'import_action' => [
        'file' => 'Soubor',
        'url' => 'Odkaz',
        'schedule_help' => 'Měl by to být nezpracovaný soubor .json ( schedule-daily-restart.json )',
        'url_help' => 'Odkaz musí směřovat přímo na nezpracovaný .json soubor',
        'add_url' => 'Nový odkaz',
        'import_failed' => 'Import selhal',
        'import_success' => 'Import úspěšný',
    ],
];
