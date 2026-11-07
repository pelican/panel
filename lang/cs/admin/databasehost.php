<?php

return [
    'nav_title' => 'Hostitelé databáze',
    'model_label' => 'Hostitel databáze',
    'model_label_plural' => 'Hostitelé databáze',
    'table' => [
        'database' => 'Databáze',
        'name' => 'Název',
        'host' => 'Hostitel',
        'port' => 'Port',
        'name_helper' => 'Ponecháním tohoto prázdného bude automaticky generováno náhodné jméno',
        'username' => 'Uživatelské jméno',
        'password' => 'Heslo',
        'remote' => 'Připojení od',
        'remote_helper' => 'Kde by mělo být povoleno připojení. Ponechte prázdné pro povolení připojení odkudkoliv.',
        'max_connections' => 'Maximální počet připojení',
        'created_at' => 'Vytvořeno v',
        'connection_string' => 'JDBC Connection String',
    ],
    'error' => 'Chyba při připojování k serveru',
    'host' => 'Hostitel',
    'host_help' => 'IP adresa nebo název domény, které by měly být použity při pokusu o připojení k tomuto MySQL hostiteli z tohoto panelu pro vytvoření nových databází.',
    'port' => 'Port',
    'port_help' => 'Port který MySQL používá na hostiteli',
    'max_database' => 'Maximální počet databází',
    'max_databases_help' => 'Maximální počet databází, které mohou být vytvořeny na tomto serveru. Pokud je dosaženo limitu, na tomto hostiteli nelze vytvořit žádné nové databáze. Prázdné je neomezené.',
    'display_name' => 'Zobrazené jméno',
    'display_name_help' => 'Krátký identifikátor používaný k odlišení tohoto umístění od ostatních. Musí mít 1 až 60 znaků, například us.nyc.lvl3.',
    'username' => 'Uživatelské jméno',
    'username_help' => 'Uživatelské jméno účtu, který má dostatečná oprávnění pro vytvoření nových uživatelů a databází v systému.',
    'password' => 'Heslo',
    'password_help' => 'Heslo pro uživatele databáze.',
    'linked_nodes' => 'Propojený Nodes',
    'linked_nodes_help' => 'Toto nastavení je výchozí pouze pro tuto databázi hostitele při přidání databáze na server vybraného uzlu.',
    'connection_error' => 'Chyba při připojování k serveru',
    'no_database_hosts' => 'Žádné hostitele databáze',
    'no_databases' => 'Žádné databáze',
    'no_nodes' => 'Žádné Nodes',
    'nodes' => 'Uzly',
    'delete_help' => 'Databáze hostitel má databáze',
    'unlimited' => 'Neomezené',
    'anywhere' => 'Kdekoliv',

    'rotate' => 'Otočit',
    'rotate_password' => 'Změnit heslo',
    'rotated' => 'Heslo změněné',
    'rotate_error' => 'Změna hesla se nezdařila',
    'databases' => 'Databáze',

    'setup' => [
        'preparations' => 'Přípravy',
        'database_setup' => 'Nastavení databáze',
        'panel_setup' => 'Nastavení panelu',

        'note' => 'V současné době jsou podporovány pouze databáze MySQL/ MariaDB!',
        'different_server' => 'Jsou panel a databáze <i>ne</i> na stejném serveru?',

        'database_user' => 'Uživatel databáze',
        'cli_login' => 'Použijte <code>mysql -u root -p</code> pro přístup mysql CLI.',
        'command_create_user' => 'Příkaz k vytvoření uživatele',
        'command_assign_permissions' => 'Příkaz k přiřazení oprávnění',
        'cli_exit' => 'Pro ukončení mysql cli spusťte <code>exit</code>.',
        'external_access' => 'Externí přístup',
        'allow_external_access' => '
                                    <p>Šance budete muset povolit externí přístup k této instanci MySQL, abyste se k ní mohli připojit.</p>
                                    <br>
                                    <p>otevřít <code>my. nf</code>, které se liší v umístění v závislosti na vašem OS a jak byl MySQL nainstalován. Můžete napsat <code>/etc -iname my.cnf</code> a najít ji.</p>
                                    <br>
                                    <p>Open <code>my. nf</code>, přidejte text níže do spodní části souboru a uložte jej:<br>
                                    <code>[mysqld]<br>bind-address=0. .0.</code></p>
                                    <br>
                                    <p>Restart MySQL/ MariaDB, aby se tyto změny použily. Toto přepíše výchozí konfiguraci MySQL, která bude ve výchozím nastavení přijímat pouze žádosti od localhost. Aktualizace umožní připojení na všech rozhraních, a tedy i externí připojení. Ujistěte se, že povolíte MySQL port (výchozí 3306) ve vaší firewall.</p>
                                ',
    ],
];
