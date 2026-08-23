<?php

return [
    'nav_title' => 'Datenbank Hosts',
    'model_label' => 'Datenbank Host',
    'model_label_plural' => 'Datenbank Hosts',
    'table' => [
        'database' => 'Datenbank',
        'name' => 'Name',
        'host' => 'Host',
        'port' => 'Port',
        'name_helper' => 'Wenn dieses Feld leer gelassen wird, wird automatisch ein zufälliger Name generiert',
        'username' => 'Benutzername',
        'password' => 'Passwort',
        'remote' => 'Verbindungen von',
        'remote_helper' => 'Von wo aus Verbindungen erlaubt werden sollen. Leer lassen, um Verbindungen von überall zu erlauben.',
        'max_connections' => 'Max. Verbindungen',
        'created_at' => 'Erstellt am',
        'connection_string' => 'JDBC Verbindungsstring',
    ],
    'error' => 'Fehler beim Verbinden mit dem Host',
    'host' => 'Host',
    'host_help' => 'Die IP-Adresse oder der Domain Name, der vom Panel verwendet werden soll, um eine Verbindung zum MySQL Host herzustellen, um neue Datenbanken zu erstellen.',
    'port' => 'Port',
    'port_help' => 'Der Port, auf dem MySQL für diesen Host läuft.',
    'max_database' => 'Max. Datenbanken',
    'max_databases_help' => 'Die maximale Anzahl von Datenbanken, die auf diesem Host erstellt werden können. Wenn das Limit erreicht ist, können keine neuen Datenbanken auf diesem Host erstellt werden. Leer ist unbegrenzt.',
    'display_name' => 'Anzeigename',
    'display_name_help' => 'Die IP-Adresse oder der Domain-Name, der dem Endbenutzer angezeigt werden soll.',
    'username' => 'Benutzername',
    'username_help' => 'Der Benutzername eines Kontos mit ausreichenden Berechtigungen, um neue Benutzer und Datenbanken auf dem System zu erstellen.',
    'password' => 'Passwort',
    'password_help' => 'Das Passwort für den Datenbank Benutzer.',
    'linked_nodes' => 'Verknüpfte Nodes',
    'linked_nodes_help' => 'Diese Einstellung bewirkt nur, dass standardmäßig auf dieser Datenbank Host genutzt wird, wenn eine Datenbank zu einem Server auf den ausgewählten Nodes hinzugefügt wird.',
    'connection_error' => 'Fehler beim Verbinden mit dem Datenbank Host',
    'no_database_hosts' => 'Keine Datenbank Hosts',
    'no_databases' => 'Keine Datenbanken',
    'no_nodes' => 'Keine Nodes',
    'nodes' => 'Nodes',
    'delete_help' => 'Datenbank Host hat Datenbanken',
    'unlimited' => 'Unbegrenzt',
    'anywhere' => 'Überall',

    'rotate' => 'Rotieren',
    'rotate_password' => 'Passwort rotieren',
    'rotated' => 'Passwort rotiert',
    'rotate_error' => 'Passwort rotieren fehlgeschlagen',
    'databases' => 'Datenbanken',

    'setup' => [
        'preparations' => 'Vorbereitungen',
        'database_setup' => 'Datenbank Einrichtung',
        'panel_setup' => 'Panel Einrichtung',

        'note' => 'Derzeit werden nur MySQL / MariaDB Datenbanken als Datenbank-Host unterstützt!',
        'different_server' => 'Sind das Panel und die Datenbank <i>nicht</i> auf dem gleichen Server?',

        'database_user' => 'Datenbank-Benutzer',
        'cli_login' => 'Verwende <code>mysql -u root -p</code> um auf die mysql cli zuzugreifen.',
        'command_create_user' => 'Befehl um den Benutzer zu erstellen',
        'command_assign_permissions' => 'Befehl um Berechtigungen zuzuweisen',
        'cli_exit' => 'Um mysql cli zu beenden, führe <code>exit</code> aus.',
        'external_access' => 'Externer Zugriff',
        'allow_external_access' => '
                                    <p>Möglicherweise musst Du externen Zugriff auf diese MySQL-Instanz erlauben, um den Servern die Verbindung zu ermöglichen.</p>
                                    <br>
                                    <p>Um dies zu tun öffne <code>my.cnf</code>, welche je nach Betriebssystem und wie MySQL installiert wurde variiert. Du kannst <code>/etc -iname my.cnf</code> eingeben, um sie zu finden.</p>
                                    <br>
                                    <p>Öffne <code>my.cnf</code>, füge den Text unten am Ende der Datei hinzu und speichere sie:<br>
                                    <code>[mysqld]<br>bind-address=0.0.0.0</code></p>
                                    <br>
                                    <p>Starte MySQL/ MariaDB neu, um diese Änderungen zu übernehmen. Dies überschreibt die Standard-MySQL-Konfiguration, die standardmäßig nur Anfragen von localhost akzeptiert. Das Aktualisieren dieser Option ermöglicht Verbindungen auf allen Schnittstellen und somit externe Verbindungen. Stelle sicher, dass Du den MySQL-Port (Standard 3306) in Deiner Firewall zulässt.<p>
                                ',
    ],
];
