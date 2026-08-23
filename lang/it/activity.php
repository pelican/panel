<?php

/**
 * Contains all of the translation strings for different activity log
 * events. These should be keyed by the value in front of the colon (:)
 * in the event name. If there is no colon present, they should live at
 * the top level.
 */
return [
    'auth' => [
        'fail' => 'Log in fallito',
        'success' => 'Accesso effettuato',
        'password-reset' => 'Reimposta Password',
        'checkpoint' => 'Autenticazione a due fattori necessaria',
        'recovery-token' => 'Token di recupero a due fattori utilizzato',
        'token' => 'Verifica a due fattori risolta',
        'ip-blocked' => 'Richiesta bloccata da un indirizzo IP non elencato per <b>:identifier</b>',
        'sftp' => [
            'fail' => 'Accesso SFTP non riuscito',
        ],
    ],
    'user' => [
        'account' => [
            'username-changed' => 'Cambiato il nome utente da <b>:old</b> a <b>:new</b>',
            'email-changed' => 'Email modificata da <b>:old</b> a <b>:new</b>',
            'password-changed' => 'Password modificata',
        ],
        'api-key' => [
            'create' => 'Creata una nuova chiave API <b>:identifier</b>',
            'delete' => 'Chiave API <b>:identifier</b> eliminata',
        ],
        'ssh-key' => [
            'create' => 'Aggiunta la chiave SSH <b>:impronta digitale</b> all\'account',
            'delete' => 'Chiave SSH <b>:fingerprint</b> rimossa dall\'account',
        ],
        'two-factor' => [
            'create' => 'Autenticazione a due fattori attivata',
            'delete' => 'Autenticazione a due fattori disattivata',
        ],
    ],
    'server' => [
        'console' => [
            'command' => 'Eseguito "<b>:command</b>" sul server',
        ],
        'power' => [
            'start' => 'Server avviato',
            'stop' => 'Server arrestato',
            'restart' => 'Server riavviato',
            'kill' => 'Processo del server terminato',
        ],
        'backup' => [
            'download' => 'Scaricato il backup <b>:name</b>',
            'delete' => 'Eliminato il backup <b>:name</b>',
            'restore' => 'Ripristinato il backup <b>:name</b> (file rimossi: <b>:truncate</b>)',
            'restore-complete' => 'Ripristino completato del backup <b>:name</b>',
            'restore-failed' => 'Impossibile completare il ripristino del backup <b>:name</b>',
            'start' => 'Avviato un nuovo backup <b>:name</b>',
            'complete' => 'Contrassegnato il backup <b>:name</b> come completo',
            'fail' => 'Contrassegnato il backup <b>:name</b> come non riuscito',
            'lock' => 'Bloccato il backup <b>:name</b>',
            'unlock' => 'Sbloccato il backup <b>:name</b>',
            'rename' => 'Backup rinominato da "<b>:old_name</b>" a "<b>:new_name</b>"',
        ],
        'database' => [
            'create' => 'Creato il database <b>:name</b>',
            'rotate-password' => 'Password ruotata per il database <b>:name</b>',
            'delete' => 'Database <b>:name</b> eliminato',
        ],
        'file' => [
            'compress' => 'Compresso <b>:directory:files</b>|Compresso <b>:count</b> file in <b>:directory</b>',
            'read' => 'Visualizzato i contenuti di <b>:file</b>',
            'copy' => 'Creato una copia di <b>:file</b>',
            'create-directory' => 'Creata cartella <b>:directory:name</b>',
            'decompress' => 'Decompresso <b>:file</b> in <b>:directory</b>',
            'delete' => 'Eliminati <b>:directory:files</b>|Eliminati <b>:count</b> file in <b>:directory</b>',
            'download' => 'Scaricato <b>:file</b>',
            'pull' => 'Scaricato un file remoto da <b>:url</b> in <b>:directory</b>',
            'rename' => 'Mosso/ Rinominato <b>:from</b> a <b>:to</b>|Mosso/ Rinominato <b>:count</b> file in <b>:directory</b>',
            'write' => 'Scritto il nuovo contenuto in <b>:file</b>',
            'upload' => 'Iniziato il caricamento di un file',
            'uploaded' => 'Caricato <b>:directory:file</b>',
        ],
        'sftp' => [
            'denied' => 'Accesso SFTP bloccato a causa di permessi',
            'create' => 'Creato <b>:files</b>|Creati <b>:count</b> nuovi file',
            'write' => 'Modificato i contenuti di <b>:files</b>|Modificati i contenuti di <b>:count</b> file',
            'delete' => 'Cancellato <b>:files</b>|Cancellati <b>:count</b> file',
            'create-directory' => 'Creata la cartella <b>:files</b>|Create <b>:count</b> cartelle',
            'rename' => 'Rinominato <b>:from</b> a <b>:to</b>|Rinominato o spostato <b>:count</b> file',
        ],
        'allocation' => [
            'create' => 'Aggiunto <b>:allocation</b> al server',
            'notes' => 'Aggiornate le note per <b>:allocation</b> da "<b>:old</b>" a"<b>:new</b>"',
            'primary' => 'Impostato <b>:allocation</b> come allocazione primaria del server',
            'delete' => 'Rimosso la <b>:allocation</b> allocazione',
        ],
        'schedule' => [
            'create' => 'Creato la <b>:name</b> pianificazione',
            'update' => 'Aggiornato il programma con nome:<b>:name</b>',
            'execute' => 'Hai eseguito manualmente il programma <b>:name</b>',
            'delete' => 'Aggiornato il programma <b>:name</b>',
        ],
        'task' => [
            'create' => 'Creato una nuova task "<b>:action</b>" per il programma <b>:name</b>',
            'update' => 'Aggiornata la task "<b>:action</b>" per il programma <b>:name</b>',
            'delete' => 'Eliminata la task "<b>:action</b>" per la pianificazione <b>:name</b>',
        ],
        'settings' => [
            'rename' => 'Rinominato il server da "<b>:old</b>" a "<b>:new</b>"',
            'description' => 'Cambiata la descrizione del server da "<b>:old</b>" a "<b>:new</b>"',
            'reinstall' => 'Server reinstallato',
        ],
        'startup' => [
            'edit' => 'Cambiata la variabile <b>:variable</b> da "<b>:old</b>" a "<b>:new</b>"',
            'image' => 'Aggiornato l\'immagine Docker per il server da <b>:old</b> a <b>:new</b>',
            'command' => 'Aggiornato il comando di avvio per il server da <b>:old</b> a <b>:new</b>',
        ],
        'subuser' => [
            'create' => 'Aggiunto <b>:email</b> come sotto-utente',
            'update' => 'Aggiornato i permessi del sotto-utente con email:<b>:email</b>',
            'delete' => 'Rimosso <b>:email</b> come sotto-utente',
        ],
        'mount' => [
            'update' => 'Mount del server aggiornati',
        ],
        'crashed' => 'Server crashato',
    ],
];
