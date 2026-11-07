<?php

return [
    'title' => 'Biztonsági mentések',
    'empty' => 'Nincs biztonsági mentés',
    'size' => 'Méret',
    'created_at' => 'Létrehozva',
    'status' => 'Állapot',
    'is_locked' => 'Zár státusza',
    'backup_status' => [
        'in_progress' => 'Folyamatban',
        'successful' => 'Sikeres',
        'failed' => 'Sikertelen',
    ],
    'notifications' => [
        'action' => 'Értesítési beállítások',
        'toggle_manual' => 'Kézi mentési értesítések',
        'helper_manual' => 'Kapjon értesítést és e-mailt, ha a szerver kézi mentése sikeresen befejeződik vagy meghiúsul.',
        'toggle_scheduled' => 'Ütemezett mentési értesítések',
        'helper_scheduled' => 'Kapjon értesítést és e-mailt, ha a szerver ütemezett mentése sikeresen befejeződik vagy meghiúsul.',
        'saved' => 'Értesítési beállítások frissítve',
    ],
    'actions' => [
        'create' => [
            'title' => 'Biztonsági másolat létrehozása',
            'limit' => 'Elérte a biztonsági mentési korlátot',
            'created' => ':name létrehozta',
            'notification_success' => 'Biztonsági másolat sikeresen létrehozva',
            'notification_fail' => 'A biztonsági másolat létrehozása sikertelen',
            'name' => 'Név',
            'ignored' => 'Figyelmen kívül hagyott fájlok és könyvtárak',
            'locked' => 'Zárt?',
            'lock_helper' => 'Megakadályozza, hogy ez a biztonsági másolat törlésre kerüljön, amíg kifejezetten fel nem oldják.',
        ],
        'lock' => [
            'lock' => 'Zárolás',
            'unlock' => 'Feloldás',
        ],
        'download' => 'Letöltés',
        'rename' => [
            'title' => 'Átnevezés',
            'new_name' => 'A biztonsági mentés neve',
            'notification_success' => 'A biztonsági másolat visszaállítása sikeresen megtörtént',
        ],
        'restore' => [
            'title' => 'Visszaállítás',
            'helper' => 'A szerver leállításra kerül. Amíg a folyamat tart, nem tudja majd kezelni a szerver állapotát, használni a fájlkezelőt vagy új biztonsági mentést készíteni.',
            'delete_all' => 'A biztonsági másolat visszaállítása előtt töröli az összes fájlt?',
            'notification_started' => 'Mentés visszaállítása',
            'notification_success' => 'A biztonsági másolat visszaállítása sikeresen megtörtént',
            'notification_fail' => 'Mentés visszaállítása sikertelen',
            'notification_fail_body_1' => 'Ez a szerver jelenleg nem alkalmas biztonsági másolat visszaállítására.',
            'notification_fail_body_2' => 'Ez a biztonsági másolat jelenleg nem állítható vissza: nem fejeződött be vagy sikertelen volt.',
        ],
        'delete' => [
            'title' => 'Biztonsági másolat törlése',
            'description' => 'Szeretné törölni a következőt? :backup',
            'notification_success' => 'Biztonsági mentés törölve',
            'notification_fail' => 'A biztonsági másolat nem törölhető',
            'notification_fail_body' => 'A csomóponttal való kapcsolat megszakadt. Kérlek, próbáld meg újra.',
        ],
    ],
];
