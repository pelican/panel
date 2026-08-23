<?php

return [
    'daemon_connection_failed' => 'Probléma lépett fel a daemonnal való kommunikáció során a HTTP/:code válasz kód alatt. Naplózásra került a probléma.',
    'node' => [
        'servers_attached' => 'A node nem tartalmazhat szervereket a törlés végrehajtásához.',
        'error_connecting' => 'Hiba a csatlakozásnál: :node',
        'daemon_off_config_updated' => 'A daemon konfiguráció<strong>frissítve</strong>, azonban probléma lépett fel a daemon automatikus konfiguráció frissítése során. Manuálisan kell frissítened a konfigurációs fájlt (config.yml) hogy életbe lépjenek a daemonon végzett módosítások.',
    ],
    'allocations' => [
        'server_using' => 'Ez a szerver társított egy allokációhoz, csak akkor törölhetsz egy allokációt ha ahhoz nincsen szerver társítva.',
        'too_many_ports' => 'Több mint 1000 port megadása egy megadott tartományban nem támogatott.',
        'invalid_mapping' => 'A következő porthoz érvénytelen a hozzárendelés és nem sikerült feldolgozni: {port}.',
        'cidr_out_of_range' => 'A CIDR maszk csak /25 és /32es tartomány között engedélyezett.',
        'port_out_of_range' => 'Az allokációban megadott portoknak 1024 és 65535 között kell lenniük.',
    ],
    'egg' => [
        'delete_has_servers' => 'Egy aktív szerverhez társított Egg nem törölhető a panelből.',
        'invalid_copy_id' => 'A script másolására kiválasztott Egg nem létezik, vagy magát a scriptet másolja.',
        'has_children' => 'Ez az Egg szülője egy vagy több más Egg-nek. Töröld először az alárendelt Egg-eket mielőtt ezt törölnéd.',
    ],
    'variables' => [
        'env_not_unique' => 'A(z) :name környezeti változónak egyedinek kell lennie ehhez az Egg-hez.',
        'reserved_name' => 'A(z) :name környezeti változó védett és nem lehet hozzárendelni.',
        'bad_validation_rule' => 'A(z) ":rule" érvényesítési szabály nem érvényes az alkalmazásban.',
    ],
    'importer' => [
        'json_error' => 'Hiba történt a JSON fájl feldolgozása közben: :error.',
        'file_error' => 'A megadott JSON fájl érvénytelen.',
        'invalid_json_provided' => 'A megadott JSON fájl formátuma nem felismerhető.',
    ],
    'subusers' => [
        'editing_self' => 'Nem módosíthatod a saját alfelhasználói fiókodat.',
        'user_is_owner' => 'Nem adhatod hozzá a szerver tulajdonosát mint alfelhasználót.',
        'subuser_exists' => 'Ez az email cím már szerepel mint alfelhasználó ehhez a szerverhez.',
    ],
    'databases' => [
        'delete_has_databases' => 'Nem törölhetsz egy adatbázis hoszt szervert amihez aktív adatbázisok kapcsolódnak.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'A láncolt feladatok maximális időköze 15 perc lehet.',
    ],
    'locations' => [
        'has_nodes' => 'Nem törölhetsz egy lokációt amihez aktív node-ok kapcsolódnak.',
    ],
    'users' => [
        'is_self' => 'Nem törölheted a saját felhasználói fiókodat.',
        'has_servers' => 'Nem törölhetsz egy felhasználót akinek aktív szerverei vannak. Töröld először a szervereit.',
        'node_revocation_failed' => 'Sikertelen kulcs visszavonás a(z) <a href=":link">Node #:node</a> esetén. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Nem található megfelelő node az automatikus telepítéshez megadott követelmények alapján.',
        'no_viable_allocations' => 'Nem található megfelelő allokáció az automatikus telepítéshez.',
    ],
    'api' => [
        'resource_not_found' => 'A kért erőforrás nem létezik ezen a kiszolgálón.',
    ],
    'mount' => [
        'servers_attached' => 'Egy mount nem törölhető amíg szerverek kapcsolódnak hozzá.',
    ],
    'server' => [
        'marked_as_failed' => 'Ez a szerver még nem fejezte be a telepítési folyamatot, kérlek próbáld újra később.',
        'file_too_large' => 'Ez a fájl túl nagy, hogy itt meg tudd nyitni.',
        'state_conflict' => 'A szerver jelenleg nem elérhető, próbáld újra később.',
        'suspended' => 'Ez a szerver fel van függesztve és ez nem elérhető.',
        'maintenance' => 'A node szerver jelenleg karbantartás alatt van így ez nem elérhető.',
        'restoring_backup' => 'A szerver jelenleg egy mentést állít vissza, kérlek próbáld újra később.',
        'transferring' => 'Ez a szerver jelenleg egy új gépre van migrálva, kérlek próbáld újra később.',
    ],
];
