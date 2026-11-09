<?php

return [
    'nav_title' => 'Database Hosts',
    'model_label' => 'Database Host',
    'model_label_plural' => 'Database Hosts',
    'table' => [
        'database' => 'Database',
        'name' => 'Naam',
        'host' => 'Host',
        'port' => 'Poort',
        'name_helper' => 'Dit leeg laten zal automatisch een willekeurige naam genereren',
        'username' => 'Gebruikersnaam',
        'password' => 'Wachtwoord',
        'remote' => 'Verbindingen van',
        'remote_helper' => 'Van waar verbindingen toegestaan moeten worden. Laat leeg om verbindingen van overal toe te staan.',
        'max_connections' => 'Max. Aantal verbindingen',
        'created_at' => 'Gemaakt Op',
        'connection_string' => 'JDBC Verbinding Koppeling',
    ],
    'error' => 'Fout bij verbinden met host',
    'host' => 'Host',
    'host_help' => 'Het IP-adres of de domeinnaam die moet worden gebruikt bij het verbinden met deze MySQL-host vanuit dit paneel om nieuwe databases aan te maken.',
    'port' => 'Poort',
    'port_help' => 'De poort waarop MySQL draait voor deze host.',
    'max_database' => 'Maximaal aantal databases',
    'max_databases_help' => 'Het maximale aantal databases dat op deze host kan worden aangemaakt. Als de limiet is bereikt, kunnen er geen nieuwe databanken worden gecreëerd over deze host. Blank is onbeperkt.',
    'display_name' => 'Weergavenaam',
    'display_name_help' => 'Het IP-adres of domeinnaam die aan de eindgebruiker getoond moet worden.',
    'username' => 'Gebruikersnaam',
    'username_help' => 'De gebruikersnaam van een account die voldoende rechten heeft om nieuwe gebruikers en databases aan te maken op het systeem.',
    'password' => 'Wachtwoord',
    'password_help' => 'Het wachtwoord voor de gebruiker van de database.',
    'linked_nodes' => 'Gekoppelde Nodes',
    'linked_nodes_help' => 'Deze instelling is alleen standaard ingesteld op deze database host bij het toevoegen van een database aan een server op de geselecteerde Node.',
    'connection_error' => 'Fout bij het verbinden met database host',
    'no_database_hosts' => 'Geen Database Hosts',
    'no_databases' => 'Geen Databases',
    'no_nodes' => 'Geen Nodes',
    'nodes' => 'Nodes',
    'delete_help' => 'De Database Host heeft Databases',
    'unlimited' => 'Onbeperkt',
    'anywhere' => 'Alle richtingen',

    'rotate' => 'Roteren',
    'rotate_password' => 'Wachtwoord wijzigen',
    'rotated' => 'Wachtwoord gewijzigd',
    'rotate_error' => 'Wachtwoord Wijzigen mislukt',
    'databases' => 'Databases',

    'setup' => [
        'preparations' => 'Voorbereidingen',
        'database_setup' => 'Database Instellen',
        'panel_setup' => 'Paneel Instellen',

        'note' => 'Momenteel worden alleen MySQL/ MariaDB databases ondersteund voor database hosts!',
        'different_server' => 'Zijn het paneel en de database <i>niet</i> op dezelfde server?',

        'database_user' => 'Database Gebruiker',
        'cli_login' => 'Gebruik <code>MySQL u root p</code> voor toegang tot MySQL cli.',
        'command_create_user' => 'Commando om de gebruiker aan te maken',
        'command_assign_permissions' => 'Commando om machtigingen toe te wijzen',
        'cli_exit' => 'Om MySQL cli te verlaten voer <code>exit</code> uit.',
        'external_access' => 'Externe Toegang',
        'allow_external_access' => '
                                    <p>Kansen zult u externe toegang tot deze MySQL-instantie nodig hebben om servers verbinding te laten maken.</p>
                                    <br>
                                    <p>Om dit te doen open <code>mijne. nf</code>, welke de locatie varieert afhankelijk van uw besturingssysteem en hoe MySQL is geïnstalleerd. Je kunt <code>/etc -iname my.cnf</code> typen om het te vinden.</p>
                                    <br>
                                    <p>Open <code>mijne. nf</code>, voeg hieronder tekst toe aan de onderkant van het bestand en sla deze op:<br>
                                    <code>[mysqld]<br>bind-address=0. .0.</code></p>
                                    <br>
                                    
                                    <p>Herstart MySQL/ MariaDB om deze wijzigingen toe te passen. Dit overschrijft de standaard MySQL configuratie, die standaard alleen verzoeken van localhost accepteert. Door dit te updaten worden verbindingen op alle interfaces mogelijk, dus externe verbindingen. Zorg ervoor dat de MySQL poort (standaard 3306) in uw firewall is toegestaan.</p>
                                ',
    ],
];
