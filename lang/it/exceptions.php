<?php

return [
    'daemon_connection_failed' => 'C\'è stata un\'eccezione durante il tentativo di comunicare con il daemon risultante in un codice di risposta HTTP/:code. Questa eccezione è stata registrata.',
    'node' => [
        'servers_attached' => 'Un nodo non deve avere server collegati ad esso per essere eliminato.',
        'error_connecting' => 'Errore nella connessione a :node',
        'daemon_off_config_updated' => 'La configurazione del demone <strong>è stata aggiornata</strong>, tuttavia si è verificato un errore durante il tentativo di aggiornare automaticamente il file di configurazione sul demone. Per applicare queste modifiche è necessario aggiornare manualmente il file di configurazione (config.yml).',
    ],
    'allocations' => [
        'server_using' => 'Un server è attualmente assegnato a questa allocazione. Un\'allocazione può essere cancellata solo se nessun server è attualmente assegnato.',
        'too_many_ports' => 'Non è supportato aggiungere più di 1000 porte in un unico intervallo.',
        'invalid_mapping' => 'La mappatura fornita per :port non è valida e non può essere elaborata.',
        'cidr_out_of_range' => 'La notazione CIDR consente solo masks tra /25 e /32.',
        'port_out_of_range' => 'Le porte in un\'allocazione deve essere maggiori o uguali a 1024 e minori o uguali a 65535',
    ],
    'egg' => [
        'delete_has_servers' => 'Un uovo con server attivi ad esso collegati non può essere eliminato dal pannello.',
        'invalid_copy_id' => 'L\'uovo selezionato per copiare uno script da entrambi non esiste o sta copiando uno script stesso.',
        'has_children' => 'Questo Uovo è genitore di una o più uova. Si prega di eliminare queste Uova prima di eliminare questo Uovo.',
    ],
    'variables' => [
        'env_not_unique' => 'La variabile d\' ambiente :name deve essere univoca per questo Uovo.',
        'reserved_name' => 'La variabile d\'ambiente :name è protetta e non può essere assegnata ad una variabile.',
        'bad_validation_rule' => 'La regola di validazione ":rule" non è una regola valida per questa applicazione.',
    ],
    'importer' => [
        'json_error' => 'Si è verificato un errore durante il tentativo di analizzare il file JSON: :error.',
        'file_error' => 'Il file JSON fornito non è valido.',
        'invalid_json_provided' => 'Il file JSON fornito non è in un formato riconosciibile.',
    ],
    'subusers' => [
        'editing_self' => 'Modificare il proprio account subuser non è consentito.',
        'user_is_owner' => 'Non puoi aggiungere il proprietario del server come un sub-utente per questo server.',
        'subuser_exists' => 'Un utente con quell\'indirizzo email è già assegnato come un sub-utente per questo server.',
    ],
    'databases' => [
        'delete_has_databases' => 'Impossibile eliminare un server host del database che ha dei database attivi collegati ad esso.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'Il tempo massimo di intervallo per un\'attività catenata è di 15 minuti.',
    ],
    'locations' => [
        'has_nodes' => 'Impossibile eliminare una posizione che ha nodi attivi ad essa collegati.',
    ],
    'users' => [
        'is_self' => 'Non è possibile eliminare il proprio account.',
        'has_servers' => 'Impossibile eliminare un utente con server attivi collegati al proprio account. Si prega di eliminare i loro server prima di continuare.',
        'node_revocation_failed' => 'Impossibile revocare le chiavi sul nodo <a href=":link">#:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Non sono stati trovati nodi che soddisfano i requisiti specificati per la distribuzione automatica.',
        'no_viable_allocations' => 'Nessuna allocazione trovata soddisfa i requisiti per abilitare il deployment automatico su questo nodo.',
    ],
    'api' => [
        'resource_not_found' => 'La risorsa richiesta non esiste su questo server.',
    ],
    'mount' => [
        'servers_attached' => 'Un mount non deve avere server collegati ad esso per essere eliminato.',
    ],
    'server' => [
        'marked_as_failed' => 'Questo server non ha ancora completato il processo di installazione, riprova più tardi.',
        'file_too_large' => 'Il file che stai tentando di aprire è troppo grande per essere visualizzato nell\'editor dei file.',
        'state_conflict' => 'Questo server è attualmente in uno stato non supportato, riprova più tardi.',
        'suspended' => 'Questo server è attualmente sospeso e la funzionalità richiesta non è disponibile.',
        'maintenance' => 'Il nodo di questo server è attualmente in manutenzione e la funzionalità richiesta non è disponibile.',
        'restoring_backup' => 'Questo server sta ripristinando un backup, riprova più tardi.',
        'transferring' => 'Questo server è attualmente in trasferimento verso una nuova macchina, riprova più tardi.',
    ],
];
