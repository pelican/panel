<?php

return [
    'title' => 'Utenti',
    'username' => 'Nome Utente',
    'email' => 'Email',
    'assign_all' => 'Assegna tutti',
    'invite_user' => 'Invita Utente',
    'action' => 'Invita',
    'remove' => 'Rimuovi Utente',
    'edit' => 'Modifica Utente',
    'editing' => 'Modificando :user',
    'delete' => 'Elimina Utente',
    'notification_add' => 'Utente Invitato!',
    'notification_edit' => 'Utente Aggiornato!',
    'notification_delete' => 'Utente Eliminato!',
    'notification_failed' => 'Impossibile invitare l\'utente!',
    'permissions' => [
        'title' => 'Permessi',

        'activity_title' => 'Attività',
        'activity_desc' => 'Permessi che controllano l\'accesso di un utente ai log delle attività del server.',

        'startup_title' => 'Avvio',
        'startup_desc' => 'Permessi che controllano la capacità di un utente di visualizzare i parametri di avvio di questo server.',

        'settings_title' => 'Impostazioni',
        'settings_desc' => 'Permessi che controllano la capacità di un utente di modificare le impostazioni di questo server.',

        'control_title' => 'Controllo',
        'control_desc' => 'Permessi che controllano la capacità di un utente di controllare lo stato di alimentazione di un server, o di inviare comandi.',

        'user_title' => 'Utente',
        'user_desc' => 'Permessi che consentono a un utente di gestire altri subutenti su un server. Non saranno mai in grado di modificare il proprio account, o assegnare i permessi che non hanno loro stessi.',

        'file_title' => 'File',
        'file_desc' => 'Permessi che controllano la capacità di un utente di modificare i file di questo server.',

        'allocation_title' => 'Allocazione',
        'allocation_desc' => 'Permessi che controllano la capacità di un utente di modificare le allocazioni delle porte per questo server.',

        'database_title' => 'Database',
        'database_desc' => 'Permessi che controllano l\'accesso di un utente alla gestione del database per questo server.',

        'backup_title' => 'Backup',
        'backup_desc' => 'Permessi che controllano la capacità di un utente di generare e gestire backup del server.',

        'schedule_title' => 'Pianificazione',
        'schedule_desc' => 'Permessi che controllano l\'accesso di un utente alla gestione delle pianificazioni per questo server.',

        'startup_read' => 'Consente a un utente di visualizzare le variabili di avvio per il server.',
        'startup_update' => 'Consente a un utente di modificare le variabili di avvio per il server.',
        'startup_docker_image' => 'Consente a un utente di modificare l\'immagine Docker utilizzata quando si esegue il server.',

        'settings_rename' => 'Consente a un utente di rinominare questo server.',
        'settings_description' => 'Consente a un utente di cambiare la descrizione di questo server.',
        'settings_reinstall' => 'Consente a un utente di attivare una reinstallazione di questo server.',
        'settings_change_icon' => 'Consente a un utente di cambiare l\'icona di questo server.',

        'activity_read' => 'Consente a un utente di visualizzare i registri delle attività per il server.',

        'websocket_connect' => 'Consente a un utente di accedere alla websocket di questo server.',

        'control_console' => 'Consente a un utente di inviare dati alla console del server.',
        'control_start' => 'Consente a un utente di avviare l\'istanza del server.',
        'control_stop' => 'Consente a un utente di arrestare l\'istanza del server.',
        'control_restart' => 'Consente a un utente di riavviare l\'istanza del server.',
        'control_kill' => 'Consente a un utente di terminare l\'istanza del server.',

        'user_create' => 'Consente a un utente di creare nuovi account utente per il server.',
        'user_read' => 'Consente a un utente di visualizzare gli utenti associati a questo server.',
        'user_update' => 'Consente a un utente di modificare altri utenti associati a questo server.',
        'user_delete' => 'Consente a un utente di eliminare altri utenti associati a questo server.',

        'file_create' => 'Consente all\'utente di creare nuovi file e directory.',
        'file_read' => 'Consente a un utente di visualizzare il contenuto di una directory, ma non di visualizzare il contenuto dei file o scaricarli.',
        'file_read_content' => 'Consente a un utente di visualizzare il contenuto di un determinato file. Questo consentirà anche all\'utente di scaricare file.',
        'file_update' => 'Consente a un utente di aggiornare file e cartelle associati al server.',
        'file_delete' => 'Consente a un utente di eliminare file e directory.',
        'file_archive' => 'Consente a un utente di creare archivi di file e decomprimere archivi esistenti.',
        'file_sftp' => 'Consente a un utente di eseguire le azioni sui file indicate sopra usando un client SFTP.',

        'allocation_read' => 'Consente a un utente di visualizzare tutte le allocazioni attualmente assegnate a questo server. Gli utenti con qualsiasi livello di accesso a questo server possono sempre visualizzare l\'allocazione primaria.',
        'allocation_update' => 'Consente a un utente di cambiare l\'allocazione primaria del server e aggiungere note a ogni allocazione.',
        'allocation_delete' => 'Consente a un utente di eliminare un\'allocazione dal server.',
        'allocation_create' => 'Consente a un utente di assegnare allocazioni aggiuntive al server.',

        'database_create' => 'Consente a un utente di creare un nuovo database per il server.',
        'database_read' => 'Consente a un utente di visualizzare i database del server.',
        'database_update' => 'Consente all\'utente di apportare modifiche a un database. Se l\'utente non dispone dell\'autorizzazione "Mostra Password" non sarà in grado di modificare la password.',
        'database_delete' => 'Consente all\'utente di eliminare un\'istanza del database.',
        'database_view_password' => 'Consente a un utente di visualizzare una password del database nel sistema.',

        'schedule_create' => 'Consente a un utente di creare una nuova pianificazione per il server.',
        'schedule_read' => 'Consente all\'utente di visualizzare le pianificazioni per un server.',
        'schedule_update' => 'Consente all\'utente di apportare modifiche a una pianificazione server esistente.',
        'schedule_delete' => 'Consente all\'utente di eliminare una programmazione per il server.',

        'backup_create' => 'Consente a un utente di creare nuovi backup per questo server.',
        'backup_read' => 'Consente a un utente di visualizzare tutti i backup esistenti per questo server.',
        'backup_delete' => 'Consente a un utente di rimuovere backup dal sistema.',
        'backup_download' => 'Consente a un utente di scaricare un backup del server. Attenzione: questo consente all\'utente di accedere a tutti i file del server presenti nel backup.',
        'backup_restore' => 'Consente a un utente di ripristinare un backup del server. Attenzione: questo consente all\'utente di eliminare tutti i file del server durante il processo.',

        'mount_title' => 'Mount',
        'mount_desc' => 'Permessi che controllano la capacità di un utente di gestire i mount per questo server.',
        'mount_read' => 'Consente a un utente di visualizzare la pagina dei mount e vedere i mount disponibili.',
        'mount_update' => 'Consente a un utente di attivare o disattivare i mount per il server.',
    ],
];
