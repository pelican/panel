<?php

return [
    'title' => 'időzítők',
    'new' => 'Új időzítő',
    'edit' => 'Időzítő szerkesztése',
    'save' => 'Időzítő mentése',
    'delete' => 'Időzítő törlése',
    'import' => 'Időzítő importálása',
    'export' => 'Időzítő exportálása',
    'name' => 'Név',
    'cron' => 'Időzítő',
    'status' => 'Állapot',
    'schedule_status' => [
        'new' => 'Új',
        'inactive' => 'Inaktív',
        'processing' => 'Feldolgozás alatt',
        'active' => 'Aktív',
    ],
    'no_tasks' => 'Nincsenek feladatok',
    'run_now' => 'Futtatás most',
    'online_only' => 'Csak online állapotban',
    'last_run' => 'Utoljára futott',
    'next_run' => 'Következő futtatás',
    'never' => 'soha',
    'cancel' => 'Mégse',

    'only_online' => 'Csak akkor, ha a szerver online?
',
    'only_online_hint' => 'Csak akkor hajtsa végre ezt az ütemezést, ha a szerver fut.
',
    'enabled' => 'Időzítő engedélyezése?',
    'enabled_hint' => 'Ez az ütemezés automatikusan végrehajtásra kerül, ha engedélyezve van.
',

    'cron_body' => 'Ne feledd, hogy az alábbi időzítő beállítások mindig az UTC időzónára vonatkoznak.
',
    'cron_timezone' => 'Következő futás a te időzónádban (:timezone): <b>:next_run</b>',

    'invalid' => 'Érvénytelen',

    'time' => [
        'minute' => 'Perc',
        'hour' => 'Óra',
        'day' => 'Nap',
        'week' => 'Hét',
        'month' => 'Hónap',
        'day_of_month' => 'A hónap napja',
        'day_of_week' => 'A hét napjai',

        'hourly' => 'Óránkénti',
        'daily' => 'Napi',
        'weekly_mon' => 'Heti (Hétfő)',
        'weekly_sun' => 'Heti (vasárnap)',
        'monthly' => 'Havi',
        'every_min' => 'x percenként',
        'every_hour' => 'x óránként',
        'every_day' => 'Minden x. nap',
        'every_week' => 'x hetente',
        'every_month' => 'x havonta',
        'every_day_of_week' => 'Minden x hét napján
',

        'every' => 'Minden',
        'minutes' => 'Perc',
        'hours' => 'Óra',
        'days' => 'Nap',
        'months' => 'Hónap',

        'monday' => 'Hétfő',
        'tuesday' => 'Kedd',
        'wednesday' => 'Szerda',
        'thursday' => 'Csütörtök',
        'friday' => 'Péntek',
        'saturday' => 'Szombat',
        'sunday' => 'Vasárnap',
    ],

    'tasks' => [
        'title' => 'Feladatok',
        'create' => 'Feladat létrehozása',
        'limit' => 'Elérted a feladatok maximális számát.',
        'action' => 'Művelet',
        'payload' => 'Adatcsomag',
        'no_payload' => 'Nincs adatcsomag',
        'time_offset' => 'Időeltolódás',
        'first_task' => 'Első feladat',
        'seconds' => 'Másodperc',
        'continue_on_failure' => 'Hiba esetén folytatás',

        'actions' => [
            'title' => 'Művelet',
            'power' => [
                'title' => 'Erőforrás művelet küldése',
                'action' => 'Erőforrás műveletek',
                'start' => 'Indítás',
                'stop' => 'Leállítás',
                'restart' => 'Újraindítás',
                'kill' => 'Kilövés',
            ],
            'command' => [
                'title' => 'Parancs küldése',
                'command' => 'Parancs',
            ],
            'backup' => [
                'title' => 'Biztonsági másolat létrehozása',
                'files_to_ignore' => 'Figyelmen kívül hagyandó fájlok',
            ],
            'delete_files' => [
                'title' => 'Fájlok törlése',
                'files_to_delete' => 'Törölni kívánt fájlok',
            ],
        ],
    ],

    'notification_invalid_cron' => 'A megadott időzítő adat nem értékelhető érvényes kifejezésként.
',

    'import_action' => [
        'file' => 'Fájl',
        'url' => 'URL',
        'schedule_help' => 'Ennek a nyers .json fájlnak ( schedule-daily-restart.json ) kell lennie.',
        'url_help' => 'Az URL-eknek közvetlenül a nyers .json fájlra kell mutatniuk.',
        'add_url' => 'Új URL',
        'import_failed' => 'Importálás sikertelen',
        'import_success' => 'Importálás sikeres',
    ],
];
