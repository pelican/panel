<?php

return [
    'greeting' => 'Ciao :name!',

    'account_created' => [
        'body' => 'Stai ricevendo questa mail perché un account è stato creato per te su :app.',
        'username' => 'Username: :username',
        'email' => 'Email: :email',
        'action' => 'Configura il tuo account',
    ],

    'added_to_server' => [
        'body' => 'Sei stato aggiunto come subuser per il seguente server, permettendoti un certo grado di controllo sullo stesso.',
        'server_name' => 'Nome del server: :name',
        'action' => 'Visita il server',
    ],

    'removed_from_server' => [
        'body' => 'Sei stato rimosso come sotto-utente per il seguente server.',
        'server_name' => 'Nome del server: :name',
        'action' => 'Vai al pannello',
    ],

    'server_installed' => [
        'body' => 'Il tuo server ha finito la configurazione ed è ora pronto per l\'uso.',
        'server_name' => 'Nome del server: :name',
        'action' => 'Autenticati e inizia ad utilizzare',
    ],

    'backup_completed' => [
        'body_success' => 'Il backup è stato creato correttamente.',
        'body_failed' => 'La creazione del backup non è riuscita.',
        'backup_name' => 'Nome backup: :name',
        'server_name' => 'Nome server: :name',
        'action' => 'Visualizza backup',
    ],

    'mail_tested' => [
        'subject' => 'Messaggio di prova del pannello',
        'body' => 'Questo è un test del sistema di posta elettronica del pannello. Se lo hai ricevuto, è tutto OK!',
    ],
];
