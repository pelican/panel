<?php

return [
    'nav_title' => 'Hôte de base de données',
    'model_label' => 'Hôte de base de données',
    'model_label_plural' => 'Hôte de base de données',
    'table' => [
        'database' => 'Base de données',
        'name' => 'Nom',
        'host' => 'Hôte',
        'port' => 'Port',
        'name_helper' => 'Laisser ce champ vide va générer automatiquement un nom aléatoire',
        'username' => 'Nom d\'utilisateur',
        'password' => 'Mot de passe',
        'remote' => 'Connexions depuis',
        'remote_helper' => 'Où les connexions doivent être autorisées. Laissez vide pour autoriser les connexions depuis n\'importe où.',
        'max_connections' => 'Nombre de connexions maximum',
        'created_at' => 'Créé le',
        'connection_string' => 'Chaîne de connexion JDBC',
    ],
    'error' => 'Erreur de connexion à l\'hôte',
    'host' => 'Hôte',
    'host_help' => 'L’adresse IP ou le nom de domaine qui doit être utilisé lorsque vous tentez de vous connecter à cet hôte MySQL depuis ce panel pour créer de nouvelles bases de données.',
    'port' => 'Port',
    'port_help' => 'Le port sur lequel MySQL est en cours d\'exécution pour cette machine.',
    'max_database' => 'Nombre maximum de bases de données',
    'max_databases_help' => 'Le nombre maximum de bases de données pouvant être créées sur cet hôte. Si la limite est atteinte, aucune nouvelle base de données ne peut être créée sur cet hôte. Le vide est illimité.',
    'display_name' => 'Nom affiché',
    'display_name_help' => 'L’adresse IP ou le nom de domaine qui doit être affiché au client final.',
    'username' => 'Nom d\'utilisateur',
    'username_help' => 'Nom d’utilisateur d’un compte qui a suffisamment de permissions pour créer de nouveaux utilisateurs et bases de données sur le système.',
    'password' => 'Mot de passe',
    'password_help' => 'Le mot de passe pour l\'utilisateur de la base de données.',
    'linked_nodes' => 'Nœud lié',
    'linked_nodes_help' => 'Ce paramètre n’est défini par défaut que pour cet hôte de base de données lors de l’ajout d’une base de données à un serveur sur le nœud sélectionné.',
    'connection_error' => 'Erreur de connexion à l’hôte de la base de données',
    'no_database_hosts' => 'Aucun hôte de base de données',
    'no_databases' => 'Aucune base de données',
    'no_nodes' => 'Aucun noeud',
    'nodes' => 'Nœuds',
    'delete_help' => 'L’hôte de base de données possède des bases de données',
    'unlimited' => 'Illimité',
    'anywhere' => 'N\'importe où',

    'rotate' => 'Rotation',
    'rotate_password' => 'Réinitialiser le mot de passe',
    'rotated' => 'Mot de passe tourné',
    'rotate_error' => 'La rotation du mot de passe a échoué',
    'databases' => 'Bases de données',

    'setup' => [
        'preparations' => 'Préparations',
        'database_setup' => 'Configuration de la base de données',
        'panel_setup' => 'Configuration du panel',

        'note' => 'Actuellement, seules les bases de données MySQL / MariaDB sont prises en charge pour les hôtes de base de données!',
        'different_server' => 'Le panel et la base de données <i>sont-elles pas</i> sur le même serveur ?',

        'database_user' => 'Utilisateur de la base de données',
        'cli_login' => 'Utilisez <code>mysql -u root -p</code> pour accéder à mysql cli.',
        'command_create_user' => 'Commande pour créer l\'utilisateur',
        'command_assign_permissions' => 'Commande pour assigner les permissions',
        'cli_exit' => 'Pour sortir de mysql cli, exécutez <code>exit</code>.',
        'external_access' => 'Accès externe',
        'allow_external_access' => '
                                    <p>Il est probable que vous aurez besoin d\'autoriser l\'accès externe à cette instance MySQL afin de permettre aux serveurs de s\'y connecter</p>
                                    <br>
                                    <p>Pour ce faire, ouvrez <code>my.cnf</code>, qui varie en fonction de l\'emplacement du système d\'exploitation et de la façon dont MySQL a été installé. Vous pouvez taper find <code>/etc -iname my.cnf</code> pour le localiser. </p>
                                    <br>
                                    <p>Ouvrez <code>my.cnf</code>, ajouter le texte ci-dessous au bas du fichier et l\'enregistrer :<br>
                                    <code>[mysqld]<br>bind-address=0.0.0.0.</code></p>
                                    <br>
                                    <p>Redémarrez MySQL / MariaDB pour appliquer ces modifications. Cela remplacera la configuration par défaut de MySQL, qui par défaut n\'acceptera que les requêtes de localhost. La mise à jour de cette option permettra des connexions sur toutes les interfaces, et donc des connexions externes. Assurez-vous d\'autoriser le port MySQL (par défaut 3306) dans votre pare-feu.</p>
                                ',
    ],
];
