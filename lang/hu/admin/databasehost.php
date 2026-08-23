<?php

return [
    'nav_title' => 'Adatbázis szerverek',
    'model_label' => 'Adatbázis szerver',
    'model_label_plural' => 'Adatbázis szerverek',
    'table' => [
        'database' => 'Adatbázis',
        'name' => 'Név',
        'host' => 'Hoszt',
        'port' => 'Port',
        'name_helper' => 'Ha ezt üresen hagyod, akkor automatikusan generál egy véletlenszerű nevet.',
        'username' => 'Felhasználónév',
        'password' => 'Jelszó',
        'remote' => 'Csatlakozás innen',
        'remote_helper' => 'Honnan legyenek engedélyezve a kapcsolatok. Hagyd üresen, ha bárhonnan engedélyezni szeretnéd a kapcsolatokat.',
        'max_connections' => 'Maximum kapcsolatok',
        'created_at' => 'Létrehozva',
        'connection_string' => 'JDBC kapcsolatlánc',
    ],
    'error' => 'Hiba a hoszthoz való kapcsolódáskor',
    'host' => 'Hoszt',
    'host_help' => 'Az az IP-cím vagy domain név, amelyet a Panel használ a MySQL kiszolgálóhoz való csatlakozáskor új adatbázisok létrehozásához.',
    'port' => 'Port',
    'port_help' => 'Az a port, amelyen a MySQL elérhető ezen a kiszolgálón.',
    'max_database' => 'Maximum adatbázisok',
    'max_databases_help' => 'A kiszolgálón létrehozható adatbázisok maximális száma. Ha a megadott értéket elérik, nem lehet további adatbázisokat létrehozni. Üresen hagyva nincs korlátozás.',
    'display_name' => 'Megjelenítendő név',
    'display_name_help' => 'Egy rövid azonosító, amely megkülönbözteti ezt a kiszolgálót a többitől. 1 és 60 karakter között kell lennie, például: us.nyc.lvl3.',
    'username' => 'Felhasználónév',
    'username_help' => 'Egy olyan fiók felhasználóneve, amelynek elegendő jogosultsága van új felhasználók és adatbázisok létrehozására.',
    'password' => 'Jelszó',
    'password_help' => 'Az adatbázisfelhasználó jelszava.',
    'linked_nodes' => 'Kapcsolódó csomópont',
    'linked_nodes_help' => 'Ez a beállítás csak akkor lesz alapértelmezett ennél az adatbázis-kiszolgálónál, ha egy adatbázist adunk hozzá egy szerverhez a kiválasztott csomóponton.',
    'connection_error' => 'Hiba a hoszthoz való kapcsolódáskor',
    'no_database_hosts' => 'Nincs adatbázis kiszolgáló',
    'no_databases' => 'Nincs adatbázis',
    'no_nodes' => 'Nincsenek csomópontok',
    'nodes' => 'Nodes',
    'delete_help' => 'Az adatbázis hoszt adatbázisokkal rendelkezik',
    'unlimited' => 'Korlátlan',
    'anywhere' => 'Bárhol',

    'rotate' => 'Csere',
    'rotate_password' => 'Jelszó frissítése',
    'rotated' => 'Jelszó cserélve',
    'rotate_error' => 'A jelszó cserélés meghiúsult',
    'databases' => 'Adatbázisok',

    'setup' => [
        'preparations' => 'Előkészületek',
        'database_setup' => 'Adatbázis beállítások',
        'panel_setup' => 'Panel beállítások',

        'note' => 'Jelenleg csak a MySQL/ MariaDB adatbázisok támogatottak az adatbázis hosztok számára!',
        'different_server' => 'A panel és az adatbázis <i>nem</i> ugyanazon a szerveren van?',

        'database_user' => 'Adatbázis felhasználó',
        'cli_login' => 'A mysql cli eléréséhez használd a <code>mysql -u root -p</code> parancsot.',
        'command_create_user' => 'Parancs a felhasználó létrehozására',
        'command_assign_permissions' => 'Engedélyek hozzárendelésének parancsa',
        'cli_exit' => 'A mysql cli elhagyásához futtasd a <code>exit</code> parancsot.',
        'external_access' => 'Külső hozzáférés',
        'allow_external_access' => '
                                    <p>Valószínű, hogy engedélyezned kell a külső hozzáférést ehhez a MySQL példányhoz, hogy a szerverek tudjanak csatlakozni hozzá.</p>
                                    <br>

                                    <p>Ehhez nyisd meg a <code>my.cnf</code> fájlt, amelynek elérési útja az operációs rendszertől és a MySQL telepítési módjától függ. A <code>find /etc -iname my.cnf</code> parancs segítségével megtalálhatod.</p>
                                    <br>
                                    <p>Nyisd meg a <code>my.cnf</code> fájlt, add hozzá az alábbi szöveget a fájl végéhez, majd mentsd el:<br>
                                    <code>[mysqld]<br>bind-address=0.0.0.0</code></p>
                                    <br>
                                    <p>Indítsd újra a MySQL/MariaDB szolgáltatást, hogy az új beállítások érvénybe lépjenek. Ez felülírja az alapértelmezett MySQL konfigurációt, amely alapból csak a localhost-ról enged kéréseket. Ennek frissítésével az összes hálózati interfészen engedélyezett lesz a kapcsolat, így külső kapcsolatok is lehetségesek lesznek. Győződj meg róla, hogy a MySQL port (alapértelmezetten 3306) engedélyezve van a tűzfalban.</p>
                                ',
    ],
];
