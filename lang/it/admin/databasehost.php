<?php

return [
    'nav_title' => 'Host Database',
    'model_label' => 'Host Database',
    'model_label_plural' => 'Host Database',
    'table' => [
        'database' => 'Database',
        'name' => 'Nome',
        'host' => 'Host',
        'port' => 'Porta',
        'name_helper' => 'Lasciando questo vuoto, verrà generato automaticamente un nome casuale',
        'username' => 'Nome utente',
        'password' => 'Password',
        'remote' => 'Connessioni Da',
        'remote_helper' => 'Da dove dovrebbero essere consentite le connessioni. Lasciare vuoto per consentire connessioni da qualsiasi parte.',
        'max_connections' => 'Numero massimo di connessioni',
        'created_at' => 'Creato il',
        'connection_string' => 'Stringa Di Connessione JDBC',
    ],
    'error' => 'Errore nella connessione all\'host',
    'host' => 'Host',
    'host_help' => 'L\'indirizzo IP o il nome di dominio che dovrebbe essere utilizzato quando si tenta di connettersi a questo host MySQL da questo Pannello per creare nuovi database.',
    'port' => 'Porta',
    'port_help' => 'La porta su cui è attivo MySQL per questo host.',
    'max_database' => 'Numero massimo di database',
    'max_databases_help' => 'Il numero massimo di database che possono essere creati su questo host. Se il limite è raggiunto, nessun nuovo database può essere creato su questo host. Lasciare vuoto per illimitato.',
    'display_name' => 'Nome visualizzato',
    'display_name_help' => 'L\'indirizzo IP o il nome di dominio che deve essere mostrato all\'utente finale.',
    'username' => 'Nome Utente',
    'username_help' => 'Il nome utente di un account che ha abbastanza permessi per creare nuovi utenti e database sul sistema.',
    'password' => 'Password',
    'password_help' => 'La password per l\'utente del database.',
    'linked_nodes' => 'Nodi Collegati',
    'linked_nodes_help' => 'Questa impostazione rende questo host di database il predefinito solo quando si aggiunge un database a un server sul Nodo selezionato.',
    'connection_error' => 'Errore nella connessione all\'host del database',
    'no_database_hosts' => 'Nessun Host Dei Database',
    'no_databases' => 'Nessun database',
    'no_nodes' => 'Nessun Nodo',
    'nodes' => 'Nodi',
    'delete_help' => 'Questo Database Host Contiene Database',
    'unlimited' => 'Illimitato',
    'anywhere' => 'Ovunque',

    'rotate' => 'Ruotare',
    'rotate_password' => 'Rotazione della Password',
    'rotated' => 'Password Ruotata',
    'rotate_error' => 'Rotazione Della Password Non Riuscita',
    'databases' => 'Database',

    'setup' => [
        'preparations' => 'Preparativi',
        'database_setup' => 'Configurazione del database',
        'panel_setup' => 'Configurazione Del Pannello',

        'note' => 'Attualmente, solo i database MySQL/MariaDB sono supportati per gli host del database!',
        'different_server' => 'Il pannello e il database <i>non sono</i> sullo stesso server?',

        'database_user' => 'Utente del Database',
        'cli_login' => 'Usa <code>mysql -u root -p</code> per accedere alla riga di comando mysql.',
        'command_create_user' => 'Comando per creare l\'utente',
        'command_assign_permissions' => 'Comando per assegnare i permessi',
        'cli_exit' => 'Per uscire da mysql cli esegui <code>uscita</code>.',
        'external_access' => 'Accesso esterno',
        'allow_external_access' => '
<p>È probabile che dovrai consentire l\'accesso esterno a questa istanza MySQL per consentire ai server di connettersi ad essa.</p>
                                    <br>
                                    <p>Per fare ciò, apri <code>my.cnf</code>, che varia in posizione a seconda del tuo sistema operativo e di come è stato installato MySQL. Puoi digitare find <code>/etc -iname my.cnf</code> per individuarlo.</p>
                                    <br>
                                    <p>Apri <code>my.cnf</code>, aggiungi il testo in fondo al file e salvalo:<br>
                                    <code>[mysqld]<br>indirizzo-bind=0.0.0.0</code></p>
                                    <br>
                                    <p>Riavvia MySQL/MariaDB per applicare queste modifiche. Ciò sovrascriverà la configurazione MySQL predefinita, che per impostazione predefinita accetterà solo richieste da localhost. L\'aggiornamento consentirà le connessioni su tutte le interfacce e, quindi, le connessioni esterne. Assicurati di consentire la porta MySQL (predefinita 3306) nel tuo firewall.</p>                                ',
    ],
];
