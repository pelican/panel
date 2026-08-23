<?php

return [
    'title' => 'Utilisateurs',
    'username' => 'Nom d\'utilisateur',
    'email' => 'E-mail',
    'assign_all' => 'Assigner tout',
    'invite_user' => 'Inviter un utilisateur',
    'action' => 'Inviter',
    'remove' => 'Supprimer l\'utilisateur',
    'edit' => 'Editer l\'utilisateur',
    'editing' => 'Modification de :user',
    'delete' => 'Supprimer l\'utilisateur',
    'notification_add' => 'Utilisateur invité !',
    'notification_edit' => 'Utilisateur mis à jour !',
    'notification_delete' => 'Utilisateur supprimé !',
    'notification_failed' => 'Impossible d\'inviter l\'utilisateur !',
    'permissions' => [
        'title' => 'Permissions',

        'activity_title' => 'Activité',
        'activity_desc' => 'Autorisations qui contrôlent l\'accès d\'un utilisateur aux journaux d\'activité du serveur.',

        'startup_title' => 'Démarrage',
        'startup_desc' => 'Autorisations qui contrôlent la capacité d\'un utilisateur à voir les paramètres de démarrage de ce serveur.',

        'settings_title' => 'Paramètres',
        'settings_desc' => 'Autorisations qui contrôlent la capacité d\'un utilisateur à voir les paramètres de démarrage de ce serveur.',

        'control_title' => 'Contrôle',
        'control_desc' => 'Autorisations qui contrôlent la capacité d\'un utilisateur à contrôler l\'état de puissance d\'un serveur, ou envoyer des commandes.',

        'user_title' => 'Utilisateur',
        'user_desc' => 'Permissions qui permettent à un utilisateur de gérer d\'autres sous-utilisateurs sur un serveur. Ils ne pourront jamais modifier leur propre compte ou assigner des autorisations qu\'ils n\'ont pas eux-mêmes.',

        'file_title' => 'Fichier',
        'file_desc' => 'Autorisations qui contrôlent la possibilité pour un utilisateur de modifier le système de fichiers pour ce serveur.',

        'allocation_title' => 'Allocation',
        'allocation_desc' => 'Autorisations qui contrôlent la possibilité pour un utilisateur de modifier les allocations de port pour ce serveur.',

        'database_title' => 'Base de données',
        'database_desc' => 'Autorisations qui contrôlent l\'accès d\'un utilisateur à la gestion de la base de données pour ce serveur.',

        'backup_title' => 'Sauvegarde',
        'backup_desc' => 'Autorisations qui contrôlent la capacité d\'un utilisateur à générer et gérer des sauvegardes de serveur.',

        'schedule_title' => 'Planifier',
        'schedule_desc' => 'Autorisations qui contrôlent l\'accès d\'un utilisateur à la gestion des programmations pour ce serveur.',

        'startup_read' => 'Permet à un utilisateur de voir les variables de démarrage d\'un serveur.',
        'startup_update' => 'Permet à un utilisateur de voir les variables de démarrage d\'un serveur.',
        'startup_docker_image' => 'Permet à un utilisateur de modifier l\'image Docker utilisée lors de l\'exécution du serveur.',

        'settings_rename' => 'Permet à un utilisateur de renommer et de modifier la description de ce serveur',
        'settings_description' => 'Permet à un utilisateur de changer la description de ce serveur.',
        'settings_reinstall' => 'Permet à un utilisateur de déclencher une réinstallation de ce serveur.',
        'settings_change_icon' => 'Permet à un utilisateur de modifier l\'icône de ce serveur.',

        'activity_read' => 'Permet à un utilisateur de voir les journaux d\'activité du serveur.',

        'websocket_connect' => 'Permet à un utilisateur d\'accéder à la Websocket pour ce serveur.',

        'control_console' => 'Permet à un utilisateur d\'envoyer des données à la console du serveur.',
        'control_start' => 'Permet à un utilisateur de démarrer l\'instance du serveur.',
        'control_stop' => 'Permet à un utilisateur d\'arrêter l\'instance du serveur.',
        'control_restart' => 'Permet à un utilisateur de redémarrer l\'instance du serveur.',
        'control_kill' => 'Permet à un utilisateur de tuer l\'instance du serveur.',

        'user_create' => 'Permet à un utilisateur de créer de nouveaux comptes utilisateur pour le serveur.',
        'user_read' => 'Autorise l\'utilisateur à voir les utilisateurs associés à ce serveur.',
        'user_update' => 'Autorise l\'utilisateur à modifier les autres utilisateurs associés à ce serveur.',
        'user_delete' => 'Autorise l\'utilisateur à supprimer les autres utilisateurs associés à ce serveur.',

        'file_create' => 'Autorise l\'utilisateur à créer de nouveaux fichiers et dossiers.',
        'file_read' => 'Permet à un utilisateur de visualiser le contenu d\'un répertoire, mais pas de visualiser le contenu ou de télécharger des fichiers.',
        'file_read_content' => 'Permet à un utilisateur de visualiser le contenu d\'un fichier donné. Cela permettra également à l\'utilisateur de télécharger des fichiers.',
        'file_update' => 'Permet à un utilisateur de mettre à jour les fichiers et dossiers associés au serveur.',
        'file_delete' => 'Permet à un utilisateur de supprimer des fichiers et des répertoires.',
        'file_archive' => 'Permet à un utilisateur de créer des archives de fichiers et de décompresser des archives existantes.',
        'file_sftp' => 'Permet à un utilisateur d\'effectuer les actions de fichier ci-dessus en utilisant un client SFTP.',

        'allocation_read' => 'Permet à un utilisateur de voir toutes les allocations actuellement assignées à ce serveur. Les utilisateurs ayant n\'importe quel niveau d\'accès à ce serveur peuvent toujours voir l\'allocation principale.',
        'allocation_update' => 'Permet à un utilisateur de modifier l\'allocation principale du serveur et d\'attacher des notes à chaque allocation.',
        'allocation_delete' => 'Permet à un utilisateur de supprimer une allocation du serveur.',
        'allocation_create' => 'Permet à un utilisateur d\'affecter des allocations supplémentaires au serveur.',

        'database_create' => 'Autorise un utilisateur à créer une nouvelle base de données pour le serveur.',
        'database_read' => 'Autorise un utilisateur à voir les bases de données du serveur.',
        'database_update' => 'Autorise un utilisateur à modifier une base de données. Si l\'utilisateur n\'a pas la permission "Voir le mot de passe", il ne sera pas en mesure de modifier le mot de passe.',
        'database_delete' => 'Autorise un utilisateur à supprimer une base de données.',
        'database_view_password' => 'Autorise un utilisateur à visualiser un mot de passe de base de données.',

        'schedule_create' => 'Autorise un utilisateur à créer une planification pour le serveur.',
        'schedule_read' => 'Autorise un utilisateur à voir les planifications d\'un serveur.',
        'schedule_update' => 'Autorise un utilisateur à apporter des modifications à une planification d\'un serveur existant.',
        'schedule_delete' => 'Autorise un utilisateur à supprimer une planification pour le serveur.',

        'backup_create' => 'Permet à un utilisateur de créer de nouveaux comptes utilisateur pour le serveur.',
        'backup_read' => 'Permet à un utilisateur de voir toutes les sauvegardes qui existent pour ce serveur.',
        'backup_delete' => 'Permet à un utilisateur de supprimer les sauvegardes du système.',
        'backup_download' => 'Permet à un utilisateur de télécharger une sauvegarde pour le serveur. Danger : cela permet à un utilisateur d\'accéder à tous les fichiers du serveur dans la sauvegarde.',
        'backup_restore' => 'Permet à un utilisateur de restaurer une sauvegarde pour le serveur. Danger : cela permet à l\'utilisateur de supprimer tous les fichiers du serveur.',

        'mount_title' => 'Montages',
        'mount_desc' => 'Autorisations qui contrôlent la capacité d\'un utilisateur à gérer les montages pour ce serveur.',
        'mount_read' => 'Permet à un utilisateur de visualiser la page des montages et de voir les montages disponibles.',
        'mount_update' => 'Permet à un utilisateur d\'activer/désactiver les montages pour le serveur.',
    ],
];
