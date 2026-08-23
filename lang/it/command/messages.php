<?php

return [
    'user' => [
        'search_users' => 'Inserisci un nome utente, un ID utente o un indirizzo email',
        'select_search_user' => 'ID dell\'utente da eliminare (Inserisci \'0\' per ri-cercare)',
        'deleted' => 'Utente eliminato correttamente dal pannello.',
        'confirm_delete' => 'Sicuro di voler eliminare questo utente dal gruppo?',
        'no_users_found' => 'Nessun utente è stato trovato per il termine di ricerca fornito.',
        'multiple_found' => 'Sono stati trovati più account per l\'utente fornito, non sei in grado di eliminare un utente a causa del flag --no-interaction .',
        'ask_admin' => 'L\'utente è un amministratore?',
        'ask_email' => 'Indirizzo email',
        'ask_username' => 'Nome utente',
        'ask_password' => 'Password',
        'ask_password_tip' => 'Se si desidera creare un account con una password casuale inviata via email all\'utente, eseguire di nuovo questo comando (CTRL+C) e passare il flag `--no-password`.',
        'ask_password_help' => 'Le password devono essere di almeno 8 caratteri e contenere almeno una lettera maiuscola e un numero.',
        '2fa_help_text' => 'Questo comando disabiliterà l\'autenticazione a due fattori per l\'account di un utente, se abilitata. Deve essere usato solo come comando di recupero account se l\'utente è bloccato fuori dal proprio account. Se non è ciò che volevi fare, premi CTRL+C per uscire da questo processo.',
        '2fa_disabled' => 'L\'autenticazione a 2 fattori è stata disabilitata per :email.',
    ],
    'schedule' => [
        'output_line' => 'Invio del job per il primo task in `:schedule` (:id).',
    ],
    'maintenance' => [
        'deleting_service_backup' => 'Eliminazione del file di backup del servizio :file.',
    ],
    'server' => [
        'rebuild_failed' => 'Ricostruire la richiesta di ":name" (#:id) sul nodo ":node" non è riuscita con errore: :message',
        'reinstall' => [
            'failed' => 'Ricostruire la richiesta di ":name" (#:id) sul nodo ":node" non è riuscita con errore: :message',
            'confirm' => 'Stai per reinstallare nuovamente un gruppo di server. Vuoi continuare?',
        ],
        'power' => [
            'confirm' => 'Si sta per eseguire :action  verso  :count server. Si desidera continuare?',
            'action_failed' => 'Ricostruire la richiesta di ":name" (#:id) sul nodo ":node" non è riuscita con errore: :message',
        ],
    ],
    'environment' => [
        'mail' => [
            'ask_smtp_host' => 'Host SMTP (es. smtp.gmail.com)',
            'ask_smtp_port' => 'Porta SMTP',
            'ask_smtp_username' => 'Utente SMTP',
            'ask_smtp_password' => 'Password SMTP',
            'ask_mailgun_domain' => 'Dominio Mailgun',
            'ask_mailgun_endpoint' => 'Endpoint Mailgun',
            'ask_mailgun_secret' => 'Segreto Mailgun',
            'ask_mandrill_secret' => 'Segreto di Mandrill',
            'ask_postmark_username' => 'Chiave API Postmark',
            'ask_driver' => 'Quale driver deve essere utilizzato per l\'invio di email?',
            'ask_mail_from' => 'Le email di indirizzo email dovrebbero provenire da',
            'ask_mail_name' => 'Nome che le email devono apparire da',
            'ask_encryption' => 'Metodo di crittografia da utilizzare',
        ],
    ],
];
