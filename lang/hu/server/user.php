<?php

return [
    'title' => 'Felhasználók',
    'username' => 'Felhasználónév',
    'email' => 'Email',
    'assign_all' => 'Mind hozzárendelése',
    'invite_user' => 'Felhasználó meghívása',
    'action' => 'Meghívás',
    'remove' => 'Felhasználó eltávolítása',
    'edit' => 'Felhasználó szerkesztése',
    'editing' => ':user szerkesztése',
    'delete' => 'Felhasználó törlése',
    'notification_add' => 'Felhasználó meghívva!',
    'notification_edit' => 'Felhasználó frissítve!',
    'notification_delete' => 'Felhasználó törölve!',
    'notification_failed' => 'A felhasználó meghívása sikertelen!
',
    'permissions' => [
        'title' => 'Engedélyek',

        'activity_title' => 'Aktivitás',
        'activity_desc' => 'Jogosultságok a szerver tevékenységnaplóinak eléréséhez.',

        'startup_title' => 'Indítás',
        'startup_desc' => 'Jogosultságok a szerver indítási paramétereinek megtekintéséhez.',

        'settings_title' => 'Beállítások',
        'settings_desc' => 'Jogosultságok a szerver beállításainak módosításához.',

        'control_title' => 'Vezérlés',
        'control_desc' => 'Jogosultságok a szerver energiaállapotának vezérléséhez és parancsok küldéséhez.',

        'user_title' => 'Felhasználó',
        'user_desc' => 'Jogosultságok más felhasználók kezeléséhez a szerveren. Saját fiókjukat nem szerkeszthetik, és olyan jogosultságot nem adhatnak, ami nincs meg nekik.',

        'file_title' => 'Fájl',
        'file_desc' => 'Jogosultságok a szerver fájlrendszerének módosításához.',

        'allocation_title' => 'Allokációk',
        'allocation_desc' => 'Jogosultságok a szerver portallokációinak módosításához.',

        'database_title' => 'Adatbázis',
        'database_desc' => 'Jogosultságok a szerver adatbázis-kezeléséhez.',

        'backup_title' => 'Biztonsági Mentés',
        'backup_desc' => 'Jogosultságok a szerver mentéseinek létrehozásához és kezeléséhez.',

        'schedule_title' => 'Ütemezés',
        'schedule_desc' => 'Jogosultságok a szerver ütemezés-kezeléséhez.',

        'startup_read' => 'Lehetővé teszi a felhasználó számára a szerver indítási változóinak megtekintését.',
        'startup_update' => 'Lehetővé teszi a felhasználó számára a szerver indítási változóinak módosítását.',
        'startup_docker_image' => 'Lehetővé teszi a felhasználó számára a szerver Docker image-jének módosítását.',

        'settings_rename' => 'Lehetővé teszi a felhasználó számára a szerver átnevezését és leírásának módosítását.',
        'settings_description' => 'Lehetővé teszi a felhasználó számára, hogy megváltoztassa a kiszolgáló leírását.',
        'settings_reinstall' => 'Lehetővé teszi a felhasználó számára a szerver újratelepítését.',
        'settings_change_icon' => 'Lehetővé teszi a felhasználó számára, hogy megváltoztassa a szerver ikonját.',

        'activity_read' => 'Lehetővé teszi a felhasználó számára a szerver tevékenységnaplóinak megtekintését.',

        'websocket_connect' => 'Websocket elérését engedélyezik felhasználóknak.',

        'control_console' => 'Lehetővé teszi a felhasználó számára adatok küldését a szerver konzoljára.',
        'control_start' => 'Lehetővé teszi a felhasználó számára a szerver példány indítását.',
        'control_stop' => 'Lehetővé teszi a felhasználó számára a szerver példány leállítását.',
        'control_restart' => 'Lehetővé teszi a felhasználó számára a szerver példány újraindítását.',
        'control_kill' => 'Lehetővé teszi a felhasználó számára a szerver példány kényszerített leállítását.',

        'user_create' => 'Lehetővé teszi a felhasználó számára új felhasználói fiókok létrehozását a szerveren.',
        'user_read' => 'Lehetővé teszi a felhasználó számára a szerverhez társított felhasználók megtekintését.',
        'user_update' => 'Lehetővé teszi a felhasználó számára a szerverhez társított felhasználók módosítását.',
        'user_delete' => 'Lehetővé teszi a felhasználó számára a szerverhez társított felhasználók törlését.',

        'file_create' => 'Lehetővé teszi a felhasználó számára új fájlok és könyvtárak létrehozását.',
        'file_read' => 'Lehetővé teszi a felhasználó számára egy könyvtár tartalmának megtekintését, de nem engedi meg a fájlok tartalmának vagy letöltésének megtekintését.',
        'file_read_content' => 'Lehetővé teszi a felhasználó számára egy adott fájl tartalmának megtekintését. Ez lehetővé teszi a fájlok letöltését is.',
        'file_update' => 'Lehetővé teszi a felhasználó számára a szerverhez társított fájlok és mappák módosítását.',
        'file_delete' => 'Lehetővé teszi a felhasználó számára a fájlok és könyvtárak törlését.',
        'file_archive' => 'Lehetővé teszi a felhasználó számára fájlarchívumok létrehozását és meglévő archívumok kibontását.',
        'file_sftp' => 'Lehetővé teszi a felhasználó számára a fenti fájlműveletek végrehajtását SFTP kliens segítségével.',

        'allocation_read' => 'Lehetővé teszi a felhasználó számára a szerverhez jelenleg rendelt összes allokáció megtekintését. A szerverhez bármilyen hozzáféréssel rendelkező felhasználók mindig láthatják az elsődleges allokációt.',
        'allocation_update' => 'Lehetővé teszi a felhasználó számára a szerver elsődleges allokációjának megváltoztatását és megjegyzések csatolását az egyes allokációkhoz.',
        'allocation_delete' => 'Lehetővé teszi a felhasználó számára egy allokáció törlését a szerverről.',
        'allocation_create' => 'Lehetővé teszi a felhasználó számára további allokációk rendelését a szerverhez.',

        'database_create' => 'Lehetővé teszi a felhasználó számára új adatbázis létrehozását a szerveren.',
        'database_read' => 'Lehetővé teszi a felhasználó számára a szerver adatbázisainak megtekintését.',
        'database_update' => 'Elérés megadása a felhasználónak hogy módosításokat végezzen a szerver adatbázisaiban. Ha a felhasználó nem rendelkezik a "Jelszó Megtekintése" jogosultsággal nem képes módosítást végezni az adatbázis jelszavában.',
        'database_delete' => 'Elérés megadása a felhasználónak hogy képes legyen adatbázist törölni.',
        'database_view_password' => 'Elérés megadása a felhasználónak hogy adatbázis jelszó megtekintési lehetősége legyen.',

        'schedule_create' => 'Elérés megadása a felhasználónak hogy új időbeosztást társítson a szerverhez.',
        'schedule_read' => 'Elérés megadása a felhasználónak hogy megtekintse az időbeosztásokat a szerveren.',
        'schedule_update' => 'Elérés megadása a felhasználónak hogy módosítson már létrehozott időbeosztásokon.',
        'schedule_delete' => 'Elérés megadása a felhasználónak hogy törölhessen már meglévő időbeosztásokat.',

        'backup_create' => 'Lehetővé teszi a felhasználó számára új mentések létrehozását a szerverhez.',
        'backup_read' => 'Lehetővé teszi a felhasználó számára a szerver összes mentésének megtekintését.',
        'backup_delete' => 'Lehetővé teszi a felhasználó számára mentések eltávolítását a rendszerből.',
        'backup_download' => 'Lehetővé teszi a felhasználó számára a szerver mentésének letöltését. Figyelem: ez lehetővé teszi a felhasználó számára a mentésben lévő összes szerverfájl elérését.',
        'backup_restore' => 'Lehetővé teszi a felhasználó számára a szerver mentésből történő visszaállítását. Figyelem: ez lehetővé teszi a felhasználó számára az összes szerverfájl törlését a folyamat során.',

        'mount_title' => 'Mounts',
        'mount_desc' => 'Azok a jogosultságok, amelyek szabályozzák a felhasználó azon képességét, hogy kezelje a szerverhez tartozó csatlakozási pontokat.',
        'mount_read' => 'Lehetővé teszi a felhasználó számára, hogy megtekintse a lovak oldalát és megnézze a rendelkezésre álló lovakat.',
        'mount_update' => 'Lehetővé teszi a felhasználó számára, hogy be- vagy kikapcsolja a szerver csatlakozási pontjait.',
    ],
];
