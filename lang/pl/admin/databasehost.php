<?php

return [
    'nav_title' => 'Hosty bazy danych',
    'model_label' => 'Host bazy danych',
    'model_label_plural' => 'Hosty bazy danych',
    'table' => [
        'database' => 'Baza danych',
        'name' => 'Nazwa',
        'host' => 'Host',
        'port' => 'Port',
        'name_helper' => 'Pozostawienie pustego pola spowoduje automatyczne wygenerowanie losowej nazwy',
        'username' => 'Użytkownik',
        'password' => 'Hasło',
        'remote' => 'Połączenia z',
        'remote_helper' => 'Skąd połączenia powinny być dozwolone. Pozostawienie pustego pola pozwoli na połączenia z dowolnego miejsca.',
        'max_connections' => 'Maksymalna ilość połączeń',
        'created_at' => 'Data utworzenia',
        'connection_string' => 'Połączenie JDBC',
    ],
    'error' => 'Błąd połączenia z hostem',
    'host' => 'Host',
    'host_help' => 'Adres IP lub nazwa domeny, które powinny być używane podczas próby połączenia się z tym hostem MySQL z tego Panelu do tworzenia nowych baz danych.',
    'port' => 'Port',
    'port_help' => 'Port używany przez MySQL na tym hoście',
    'max_database' => 'Maksymalna liczba baz danych',
    'max_databases_help' => 'Maksymalna liczba baz danych, które można utworzyć na tym hoście. Jeśli limit zostanie osiągnięty, nie będzie można utworzyć nowych baz danych dla tego hosta. Pozostawienie pustego pola pozwala na nieograniczoną ilość baz danych.',
    'display_name' => 'Wyświetlana nazwa',
    'display_name_help' => 'Krótki identyfikator używany do odróżnienia tej lokalizacji od innych. Musi mieć od 1 do 60 znaków, na przykład nas.nyc.lvl3.',
    'username' => 'Użytkownik',
    'username_help' => 'Nazwa użytkownika konta, które ma wystarczające uprawnienia do tworzenia nowych użytkowników i baz danych w systemie.',
    'password' => 'Hasło',
    'password_help' => 'Hasło dla użytkownika bazy danych.',
    'linked_nodes' => 'Połączone węzły',
    'linked_nodes_help' => 'To ustawienie jest domyślne tylko dla tego hosta bazy danych podczas dodawania bazy danych do serwera na wybranym Node.',
    'connection_error' => 'Błąd połączenia z hostem bazy danych',
    'no_database_hosts' => 'Brak hostów bazy danych',
    'no_databases' => 'Brak baz danych',
    'no_nodes' => 'Brak węzłów',
    'nodes' => 'Węzły',
    'delete_help' => 'Host bazy danych posiada bazy danych',
    'unlimited' => 'Bez ograniczeń',
    'anywhere' => 'Gdziekolwiek',

    'rotate' => 'Zmień',
    'rotate_password' => 'Zaktualizuj hasło',
    'rotated' => 'Hasło zostało zmienione',
    'rotate_error' => 'Zmiana hasła nie powiodła się',
    'databases' => 'Bazy danych',

    'setup' => [
        'preparations' => 'Przygotowanie',
        'database_setup' => 'Konfiguracja bazy danych',
        'panel_setup' => 'Ustawienia Panelu',

        'note' => 'Obecnie obsługiwane są tylko bazy danych MySQL/ MariaDB dla hostów bazy danych!',
        'different_server' => 'Czy panel i baza danych <i>nie są</i> na tym samym serwerze?',

        'database_user' => 'Użytkownik bazy danych',
        'cli_login' => 'Użyj <code>mysql -u root -p</code> aby uzyskać dostęp do mysql cli.',
        'command_create_user' => 'Polecenie do tworzenia użytkownika',
        'command_assign_permissions' => 'Polecenie do przydzielenia uprawnień',
        'cli_exit' => 'Aby wyjść z mysql cli uruchom <code>exit</code>.',
        'external_access' => 'Dostęp zewnętrzny',
        'allow_external_access' => '
                                    <p>Prawdopodobieństwa, że będziesz musiał zezwolić na dostęp zewnętrzny do tej instancji MySQL, aby serwery mogły się z nią połączyć.</p>
                                    <br>
                                    <p>Aby to zrobić, otwórz <code>my. nf</code>, które różnią się w zależności od Twojego systemu operacyjnego i jak zainstalowano MySQL. Możesz znaleźć <code>/etc -iname my.cnf</code> , aby go zlokalizować.</p>
                                    <br>
                                    <p>Otwórz <code>my. nf</code>dodaj tekst poniżej na dole pliku i zapisz:<br>
                                    <code>[mysqld]<br>bind-address=0.0.0.0</code></p>
                                    <br>
                                    <p>Uruchom ponownie MySQL/ MariaDB aby zastosować te zmiany. To zastąpi domyślną konfigurację MySQL, która domyślnie będzie przyjmować żądania tylko od hosta lokalnego. Aktualizacja tego pozwoli na połączenia we wszystkich interfejsach, a tym samym na połączenia zewnętrzne. Upewnij się, że zezwolisz na port MySQL (domyślnie 3306) w zaporze.</p>

                                ',
    ],
];
