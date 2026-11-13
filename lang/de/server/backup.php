<?php

return [
    'title' => 'Backups',
    'empty' => 'Keine Backups',
    'size' => 'Größe',
    'created_at' => 'Erstellt am',
    'status' => 'Status',
    'is_locked' => 'Sperrstatus',
    'backup_status' => [
        'in_progress' => 'In Bearbeitung',
        'successful' => 'Erfolgreich',
        'failed' => 'Fehlgeschlagen',
    ],
    'notifications' => [
        'action' => 'Benachrichtigungseinstellungen',
        'toggle_manual' => 'Manuelle Backup-Benachrichtigungen',
        'helper_manual' => 'Erhalten Sie eine Benachrichtigung und eine E-Mail, wenn ein manuell erstelltes Backup für diesen Server beendet oder fehlschlägt.',
        'toggle_scheduled' => 'Geplante Backup-Benachrichtigungen',
        'helper_scheduled' => 'Erhalten Sie eine Benachrichtigung und eine E-Mail, wenn ein geplantes Backup für diesen Server abgeschlossen oder fehlschlägt.',
        'saved' => 'Benachrichtigungseinstellungen aktualisiert',
    ],
    'actions' => [
        'create' => [
            'title' => 'Backup erstellen',
            'limit' => 'Maximale Anzahl von Backups erreicht',
            'created' => ':name erstellt',
            'notification_success' => 'Backup erfolgreich erstellt',
            'notification_fail' => 'Backup Fehlgeschlagen',
            'name' => 'Name',
            'ignored' => 'Ignorierte Dateien & Ordner',
            'locked' => 'Gesperrt?',
            'lock_helper' => 'Verhindert das Löschen dieses Backups bis es explizit entsperrt wird',
        ],
        'lock' => [
            'lock' => 'Sperren',
            'unlock' => 'Entsperren',
        ],
        'download' => 'Herunterladen',
        'rename' => [
            'title' => 'Umbenennen',
            'new_name' => 'Sicherungsname',
            'notification_success' => 'Sicherung erfolgreich umbenannt',
        ],
        'restore' => [
            'title' => 'Wiederherstellen',
            'helper' => 'Dein Server wird gestoppt. Du kannst deinen Server nicht mehr steuern, auf die Dateien zugreifen oder zusätzliche Backups erstellen, solange dieser Prozess läuft.',
            'delete_all' => 'Alle Dateien vor der Wiederherstellung löschen?',
            'notification_started' => 'Backup wiederherstellen',
            'notification_success' => 'Backup wurde erfolgreich wiederhergestellt',
            'notification_fail' => 'Backup Wiederherstellung fehlgeschlagen',
            'notification_fail_body_1' => 'Dieser Server befindet sich aktuell in einem Status, indem keine Backups wiederhergestellt werden können',
            'notification_fail_body_2' => 'Dieses Backup kann aktuell nicht wiederhergestellt werden: nicht komplett oder fehlgeschlagen',
        ],
        'delete' => [
            'title' => 'Backup löschen',
            'description' => 'Möchtest du :backup löschen?',
            'notification_success' => 'Backup gelöscht',
            'notification_fail' => 'Backup konnte nicht gelöscht werden',
            'notification_fail_body' => 'Verbindung zur Node fehlgeschlagen. Bitte versuche es erneut.',
        ],
    ],
];
