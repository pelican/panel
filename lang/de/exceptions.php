<?php

return [
    'daemon_connection_failed' => 'Beim Versuch, mit dem Daemon zu kommunizieren, gab es einen Fehler, was zu einem HTTP/:code Antwortcode führte. Dieser Fehler wurde protokolliert.',
    'node' => [
        'servers_attached' => 'Ein Node darf keine Server haben, die mit ihm verknüpft sind, um gelöscht zu werden.',
        'error_connecting' => 'Fehler beim Verbinden zu :node',
        'daemon_off_config_updated' => 'Die Daemon-Konfiguration <strong>wurde aktualisiert</strong>, jedoch ist beim Versuch, die Konfigurationsdatei des Daemons automatisch zu aktualisieren, ein Fehler aufgetreten. Du musst die Konfigurationsdatei (config.yml) für den Daemon manuell aktualisieren, um diese Änderungen zu übernehmen.',
    ],
    'allocations' => [
        'server_using' => 'Dieser Allokation ist derzeit ein Server zugewiesen. Eine Allokation kann nur gelöscht werden, wenn sie keinem Server zugewiesen ist.',
        'too_many_ports' => 'Das Hinzufügen von mehr als 1000 Ports in einem einzigen Bereich wird nicht unterstützt.',
        'invalid_mapping' => 'Das für :port angegebene Mapping war ungültig und konnte nicht verarbeitet werden.',
        'cidr_out_of_range' => 'CIDR-Notation nur für Masken zwischen /25 und /32 erlaubt.',
        'port_out_of_range' => 'Ports in einer Zuteilung müssen größer als 1024 und kleiner oder gleich 65535 sein.',
    ],
    'egg' => [
        'delete_has_servers' => 'Ein Egg mit aktiven Servern kann nicht aus dem Panel gelöscht werden.',
        'invalid_copy_id' => 'Das Egg, das für das Kopieren eines Skripts ausgewählt wurde, existiert nicht oder kopiert selbst ein Skript.',
        'has_children' => 'Dieses Egg ist ein Parent-Egg für ein oder mehrere Eggs. Bitte lösche diese Eggs bevor Du dieses Egg löschst.',
    ],
    'variables' => [
        'env_not_unique' => 'Die Umgebungsvariable :name muss für dieses Egg einzigartig sein.',
        'reserved_name' => 'Die Umgebungsvariable :name ist geschützt und kann nicht zugewiesen werden.',
        'bad_validation_rule' => 'Die Validierungsregel ":rule" ist keine gültige Regel für diese Anwendung.',
    ],
    'importer' => [
        'json_error' => 'Beim Verarbeiten der JSON-Datei ist ein Fehler aufgetreten: :error.',
        'file_error' => 'Die angegebene JSON-Datei war ungültig.',
        'invalid_json_provided' => 'Die angegebene JSON-Datei ist nicht in einem Format, das erkannt werden kann.',
    ],
    'subusers' => [
        'editing_self' => 'Das Bearbeiten Deines eigenen Unterbenutzerkontos ist nicht zulässig.',
        'user_is_owner' => 'Du kannst den Serverbesitzer nicht als Unterbenutzer für diesen Server hinzufügen.',
        'subuser_exists' => 'Ein Benutzer mit dieser E-Mail-Adresse ist bereits als Unterbenutzer für diesen Server zugewiesen.',
    ],
    'databases' => [
        'delete_has_databases' => 'Ein Datenbank-Host kann nicht gelöscht werden, der aktive Datenbanken enthält.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'Das maximale Intervall einer verketteten Aufgabe beträgt 15 Minuten.',
    ],
    'locations' => [
        'has_nodes' => 'Ein Standort, der aktive Nodes hat, kann nicht gelöscht werden.',
    ],
    'users' => [
        'is_self' => 'Du kannst dein eigenes Benutzerkonto nicht löschen.',
        'has_servers' => 'Ein Benutzer mit aktiven Servern, die mit seinem Konto verknüpft sind, kann nicht gelöscht werden. Bitte lösche die Server, bevor du fortfährst.',
        'node_revocation_failed' => 'Fehler beim Widerrufen der Schlüssel auf <a href=":link">Node #:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Es konnten keine Nodes gefunden werden, die die für das automatische Deployment angegebenen Anforderungen erfüllen.',
        'no_viable_allocations' => 'Es wurden keine Allokationen gefunden, die die Anforderungen für das automatische Deployment erfüllen.',
    ],
    'api' => [
        'resource_not_found' => 'Die angeforderte Ressource existiert nicht auf diesem Server.',
    ],
    'mount' => [
        'servers_attached' => 'Ein Mount darf keine Server haben, die mit ihm verknüpft sind, um gelöscht zu werden.',
    ],
    'server' => [
        'marked_as_failed' => 'Dieser Server hat seinen Installationsprozess noch nicht abgeschlossen, bitte versuche es später erneut.',
        'file_too_large' => 'Die Datei, die du versuchst zu öffnen, ist zu groß, um es im Dateieditor anzuzeigen.',
        'state_conflict' => 'Dieser Server ist im Moment in einem nicht unterstütztem Zustand, bitte versuchen Sie es später erneut.',
        'suspended' => 'Dieser Server ist im Moment suspendiert und die erwünschte Funktionalität ist nicht verfügbar.',
        'maintenance' => 'Der Node dieses Servers ist im Moment in Wartung und die erwünschte Funktionalität ist nicht verfügbar.',
        'restoring_backup' => 'Dieser Server ist im Moment am Wiederherstellen von einem Backup, bitte versuchen Sie es später erneut.',
        'transferring' => 'Dieser Server wird im Moment zu einer neuen Maschine bewegt, bitte versuchen Sie es später erneut.',
    ],
];
