<?php

return [
    'title' => 'Benutzer',
    'username' => 'Benutzername',
    'email' => 'E-Mail',
    'assign_all' => 'Alle zuordnen',
    'invite_user' => 'Benutzer einladen',
    'action' => 'Einladen',
    'remove' => 'Benutzer entfernen',
    'edit' => 'Benutzer bearbeiten',
    'editing' => 'Bearbeiten :user',
    'delete' => 'Benutzer Löschen',
    'notification_add' => 'Benutzer eingeladen!',
    'notification_edit' => 'Benutzer aktualisiert!',
    'notification_delete' => 'Benutzer entfernt!',
    'notification_failed' => 'Benutzer konnte nicht eingeladen werden!',
    'permissions' => [
        'title' => 'Rechte',

        'activity_title' => 'Aktivität',
        'activity_desc' => 'Berechtigungen, die den Zugriff eines Benutzers auf die Server-Aktivitätsprotokolle erlaubt.',

        'startup_title' => 'Startet',
        'startup_desc' => 'Berechtigungen, die den Zugriff eines Benutzers auf die Startparameter dieses Servers erlaubt.',

        'settings_title' => 'Einstellungen',
        'settings_desc' => 'Berechtigungen, die die Fähigkeit eines Benutzers steuern, die Einstellungen dieses Servers zu ändern.',

        'control_title' => 'Steuerung',
        'control_desc' => 'Berechtigungen, die die Fähigkeit eines Benutzers steuern, den Zustand eines Servers zu kontrollieren oder Befehle zu senden.',

        'user_title' => 'Benutzer',
        'user_desc' => 'Berechtigungen, die einem Benutzer ermöglichen, andere Unterbenutzer auf einem Server zu verwalten. Sie können niemals ihr eigenes Konto bearbeiten oder Berechtigungen vergeben, die sie selbst nicht haben.',

        'file_title' => 'Datei',
        'file_desc' => 'Berechtigungen, die Fähigkeit eines Benutzers steuern, Dateien für diesen Server zu ändern.',

        'allocation_title' => 'Allokation',
        'allocation_desc' => 'Berechtigungen, die Fähigkeit eines Benutzers steuern, die Port-Allokationen für diesen Server zu ändern.',

        'database_title' => 'Datenbank',
        'database_desc' => 'Berechtigungen, die Zugriff eines Benutzers auf die Datenbankverwaltung für diesen Server steuern.',

        'backup_title' => 'Backup',
        'backup_desc' => 'Berechtigungen, die die Fähigkeit eines Benutzers steuern, Server-Backups zu erstellen und zu verwalten.',

        'schedule_title' => 'Zeitplan',
        'schedule_desc' => 'Berechtigungen, die den Zugriff eines Benutzers auf den Zeitplan für diesen Server erlauben.',

        'startup_read' => 'Ermöglicht es einem Benutzer, die Start-Variablen für einen Server anzuzeigen.',
        'startup_update' => 'Ermöglicht es dem Benutzer, die Start-Variablen für den Server zu ändern.',
        'startup_docker_image' => 'Ermöglicht es einem Benutzer, das Docker-Image zu ändern, das beim Start des Servers verwendet wird.',

        'settings_rename' => 'Ermöglicht einem Benutzer, diesen Server umzubenennen und die Beschreibung zu ändern.',
        'settings_description' => 'Ermöglicht einem Benutzer, die Beschreibung des Servers zu ändern.',
        'settings_reinstall' => 'Erlaubt einem Benutzer, eine Neuinstallation für diesen Server zu starten.',
        'settings_change_icon' => 'Ermöglicht einem Benutzer, das Icon dieses Servers zu ändern.',

        'activity_read' => 'Ermöglicht einem Benutzer, die Aktivitätsprotokolle für den Server anzuzeigen.',

        'websocket_connect' => 'Ermöglicht einem Benutzer den Zugriff auf den Websocket für diesen Server.',

        'control_console' => 'Ermöglicht dem Benutzer, Daten an die Serverkonsole zu senden.',
        'control_start' => 'Ermöglicht dem Benutzer, die Serverinstanz zu starten.',
        'control_stop' => 'Ermöglicht dem Benutzer, die Serverinstanz zu stoppen.',
        'control_restart' => 'Ermöglicht dem Benutzer, die Serverinstanz neu zu starten.',
        'control_kill' => 'Ermöglicht dem Benutzer, die Server-Instanz zu beenden.',

        'user_create' => 'Ermöglicht dem Benutzer, neue Benutzerkonten für den Server zu erstellen.',
        'user_read' => 'Ermöglicht dem Benutzer, die mit diesem Server verbundenen Benutzer anzuzeigen.',
        'user_update' => 'Ermöglicht es einem Benutzer, andere mit diesem Server verbundene Benutzer zu ändern.',
        'user_delete' => 'Ermöglicht dem Benutzer, andere diesem Server zugeordnete Benutzer zu löschen.',

        'file_create' => 'Erlaubt es einem Benutzer, neue Dateien und Verzeichnisse zu erstellen.',
        'file_read' => 'Ermöglicht es einem Benutzer, den Inhalt eines Verzeichnisses einzusehen, aber nicht den Inhalt von Dateien einzusehen oder herunterzuladen.',
        'file_read_content' => 'Ermöglicht es dem Benutzer, den Inhalt einer bestimmten Datei einzusehen. Damit kann der Benutzer auch Dateien herunterladen.',
        'file_update' => 'Ermöglicht einem Benutzer die Aktualisierung von Dateien und Ordnern, die mit dem Server verbunden sind.',
        'file_delete' => 'Ermöglicht dem Benutzer, Dateien und Verzeichnisse zu löschen.',
        'file_archive' => 'Ermöglicht dem Benutzer, Dateiarchive zu erstellen und vorhandene Archive zu dekomprimieren.',
        'file_sftp' => 'Ermöglicht dem Benutzer, die oben genannten Dateiaktionen mit einem SFTP-Client auszuführen.',

        'allocation_read' => 'Ermöglicht einem Benutzer das Anzeigen aller Allokationen, die diesem Server derzeit zugewiesen sind. Benutzer mit einer beliebigen Zugriffsebene auf diesem Server können immer die primäre Allokation anzeigen.',
        'allocation_update' => 'Ermöglicht es einem Benutzer, die primäre Allokation des Servers zu ändern und jeder Allokation Notizen hinzuzufügen.',
        'allocation_delete' => 'Ermöglicht es einem Benutzer, eine Allokation vom Server zu löschen.',
        'allocation_create' => 'Ermöglicht es einem Benutzer, dem Server zusätzliche Allokationen zuzuweisen.',

        'database_create' => 'Ermöglicht es einem Benutzer, eine neue Datenbank für den Server zu erstellen.',
        'database_read' => 'Ermöglicht einem Benutzer die Einsicht in die Serverdatenbanken.',
        'database_update' => 'Erlaubt einem Benutzer, Änderungen an einer Datenbank vorzunehmen. Wenn der Benutzer nicht auch die Berechtigung „Passwort ansehen“ hat, kann er das Passwort nicht ändern.',
        'database_delete' => 'Erlaubt einem Benutzer das Löschen einer Datenbankinstanz.',
        'database_view_password' => 'Ermöglicht es einem Benutzer, ein Datenbankpasswort im System einzusehen.',

        'schedule_create' => 'Ermöglicht es einem Benutzer, einen neuen Zeitplan für den Server zu erstellen.',
        'schedule_read' => 'Ermöglicht es einem Benutzer, Zeitpläne für einen Server einzusehen.',
        'schedule_update' => 'Ermöglicht es einem Benutzer, Änderungen an einem bestehenden Zeitplan vorzunehmen.',
        'schedule_delete' => 'Erlaubt einem Benutzer, Zeitpläne für diesen Server zu löschen.',

        'backup_create' => 'Erlaubt einem Benutzer, neue Backups für diesen Server zu erstellen.',
        'backup_read' => 'Ermöglicht einem Benutzer, alle Backups für diesen Server anzuzeigen.',
        'backup_delete' => 'Erlaubt einem Benutzer, Backups vom System zu entfernen.',
        'backup_download' => 'Erlaubt einem Benutzer, ein Backup für den Server herunterzuladen. Achtung: Dies ermöglicht einem Benutzer Zugriff auf alle Dateien, die im Backup enthalten sind.',
        'backup_restore' => 'Ermöglicht einem Benutzer, ein Backup für den Server wiederherzustellen. Achtung: Dies erlaubt dem Benutzer auch alle Server-Dateien zu löschen.',

        'mount_title' => 'Mounts',
        'mount_desc' => 'Berechtigungen, die die Fähigkeit eines Benutzers kontrollieren, Mounts für diesen Server zu verwalten.',
        'mount_read' => 'Ermöglicht einem Benutzer, die Mounts-Seite anzusehen und verfügbare Mounts zu sehen.',
        'mount_update' => 'Ermöglicht einem Benutzer, Mounts für den Server ein- oder auszuschalten.',
    ],
];
