<?php

return [
    'title' => 'Zálohy',
    'empty' => 'Žádné Zálohy',
    'size' => 'Velikost',
    'created_at' => 'Vytvořeno v',
    'status' => 'Stav',
    'is_locked' => 'Stav zamknutí',
    'backup_status' => [
        'in_progress' => 'V průběhu',
        'successful' => 'Úspěšné',
        'failed' => 'Selhání',
    ],
    'notifications' => [
        'action' => 'Nastavení upozornění',
        'toggle_manual' => 'Upozornění na ruční zálohy',
        'helper_manual' => 'Dostávat upozornění a e-mail, když ručně vytvořená záloha tohoto serveru doběhne nebo selže.',
        'toggle_scheduled' => 'Upozornění na plánované zálohy',
        'helper_scheduled' => 'Dostávat upozornění a e-mail, když plánovaná záloha tohoto serveru doběhne nebo selže.',
        'saved' => 'Nastavení upozornění uloženo',
    ],
    'actions' => [
        'create' => [
            'title' => 'Vytvořit zálohu',
            'limit' => 'Dosažen limit zálohy',
            'created' => ':name vytvořeno',
            'notification_success' => 'Záloha úspěšně vytvořena',
            'notification_fail' => 'Vytvoření zálohy se nezdařilo',
            'name' => 'Název',
            'ignored' => 'Ignorované soubory a složky',
            'locked' => 'Zamknuto?',
            'lock_helper' => 'Zabraňuje tomu, aby byla tato záloha odstraněna, dokud nebude výslovně odemčena.',
        ],
        'lock' => [
            'lock' => 'Zamknout',
            'unlock' => 'Odemknout',
        ],
        'download' => 'Stáhnout',
        'rename' => [
            'title' => 'Přejmenovat',
            'new_name' => 'Název zálohy',
            'notification_success' => 'Záloha úspěšně přejmenovaná',
        ],
        'restore' => [
            'title' => 'Obnovit',
            'helper' => 'Váš server bude zastaven. Nebudete moci ovládat stav napájení, přístup ke správci souborů nebo vytvářet další zálohy, dokud nebude tento proces dokončen.',
            'delete_all' => 'Smazat všechny soubory před obnovením zálohy?',
            'notification_started' => 'Obnovení zálohy',
            'notification_success' => 'Záloha úspěšně obnovena',
            'notification_fail' => 'Obnovení zálohy se nezdařilo',
            'notification_fail_body_1' => 'Tento server není v současné době ve stavu, který umožňuje obnovení zálohy.',
            'notification_fail_body_2' => 'Záloha nemůže být v tuto chvíli obnovena: není dokončena nebo se nezdařila.',
        ],
        'delete' => [
            'title' => 'Smazat zálohu',
            'description' => 'Přejete si odstranit :backup?',
            'notification_success' => 'Záloha smazána',
            'notification_fail' => 'Zálohu nelze odstranit',
            'notification_fail_body' => 'Připojení k uzlu se nezdařilo. Zkuste to prosím znovu.',
        ],
    ],
];
