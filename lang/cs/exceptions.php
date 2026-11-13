<?php

return [
    'daemon_connection_failed' => 'Při pokusu o komunikaci s daemonem došlo k výjimce, což vedlo k HTTP/:code kódu odpovědi. Tato výjimka byla zaznamenána.',
    'node' => [
        'servers_attached' => 'Uzel nesmí mít žádné servery spojené s ním, aby mohl být odstraněn.',
        'error_connecting' => 'Chyba při připojování k :node',
        'not_viable' => 'Uzel :node nemá dostatek volných prostředků pro tento server.',
        'daemon_off_config_updated' => 'Konfigurace daemonu <strong>byla aktualizována</strong>, ale byla zde chyba při automatické aktualizaci souborů konfigurace Daemonu. Je třeba soubory konfigurace Daemonu aktualizovat manuálně (config.yml), aby změny daemonu byly aplikovány.',
    ],
    'allocations' => [
        'server_using' => 'Server je v současné době přiřazen k této alokaci. Přidělení může být odstraněno pouze v případě, že žádný server není aktuálně přiřazen.',
        'too_many_ports' => 'Přidání více než 1000 portů v jednom rozsahu najednou není podporováno.',
        'invalid_mapping' => 'Mapování poskytováno pro :port bylo neplatné a nelze jej zpracovat.',
        'cidr_out_of_range' => 'Poznámka CIDR umožňuje pouze masky mezi /25 a /32.',
        'port_out_of_range' => 'Porty v alokacích musí být vyšší než 1024 a nížší nebo se rovnat 65535.',
    ],
    'egg' => [
        'delete_has_servers' => 'Vejce s aktivními servery, které jsou k němu připojeny, nemůže být odstraněna z panelu.',
        'invalid_copy_id' => 'Vejce vybraná pro kopírování skriptu buď neexistuje, nebo kopíruje samotný skript.',
        'has_children' => 'Toto vejce je nadřazeno jednomu či více vajec. Prosím vymažte tyto vejce předtím než smažete toto.',
    ],
    'variables' => [
        'env_not_unique' => 'Proměnná prostředí :name musí být pro toto vejce jedinečná.',
        'reserved_name' => 'Proměnná prostředí :name je chráněná a nemůže být přiřazena k proměnné.',
        'bad_validation_rule' => 'Pravidlo ověření „:rule“ není platným pravidlem pro tuto aplikaci.',
    ],
    'importer' => [
        'json_error' => 'Při pokusu o analyzování souboru JSON došlo k chybě: :error.',
        'file_error' => 'Poskytnutý soubor JSON není platný.',
        'invalid_json_provided' => 'Poskytnutý soubor JSON není ve formátu, který lze rozpoznat.',
    ],
    'subusers' => [
        'editing_self' => 'Úprava vlastního poduživatele není povolena.',
        'user_is_owner' => 'Nemůžete přidat vlastníka serveru jako poduživatele pro tento server.',
        'subuser_exists' => 'Uživatel s touto e-mailovou adresou je již přiřazen jako subuživatel pro tento server.',
    ],
    'databases' => [
        'delete_has_databases' => 'Nelze odstranit databázový hostitelský server, který obsahuje aktivní databáze, které jsou k němu připojeny.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'Maximální interval pro zadaný úkol je 15 minut.',
    ],
    'locations' => [
        'has_nodes' => 'Nelze odstranit umístění, které má k němu připojené aktivní uzly.',
    ],
    'users' => [
        'is_self' => 'Nelze odstranit vlastní uživatelský účet.',
        'has_servers' => 'Nelze odstranit uživatele s aktivním serverem připojeným k jeho účtu. Před pokračováním prosím odstraňte jeho servery.',
        'node_revocation_failed' => 'Nepodařilo se zrušit klíče na <a href=":link">uzel #:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Nebyly nalezeny žádné uzly splňující požadavky stanovené pro automatické spuštění.',
        'no_viable_allocations' => 'Nebyly nalezeny žádné příděly splňující požadavky pro automatické nasazení.',
    ],
    'api' => [
        'resource_not_found' => 'Požadovaný zdroj na tomto serveru neexistuje.',
    ],
    'mount' => [
        'servers_attached' => 'Připojení nesmí mít připojené žádné servery, aby mohlo být odstraněno.',
    ],
    'server' => [
        'marked_as_failed' => 'Tento server ještě nedokončil instalační proces, zkuste to prosím později.',
        'file_too_large' => 'Soubor, který se pokoušíte otevřít, je příliš velký na zobrazení v editoru souborů.',
        'state_conflict' => 'Tento server je nyní v nepodporovaném stavu, zkuste to prosím později.',
        'suspended' => 'Tento server je pozastavený a požadovaná funkce není dostupná.',
        'maintenance' => 'Uzel tohoto serveru je v režimu údržby a požadovaná funkce není dostupná.',
        'restoring_backup' => 'Tento server se právě obnovuje ze zálohy, zkuste to prosím později.',
        'transferring' => 'Tento server se právě přesouvá na jiný stroj, zkuste to prosím později.',
    ],
];
