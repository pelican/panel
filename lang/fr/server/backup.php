<?php

return [
    'title' => 'Sauvegardes',
    'empty' => 'Aucune sauvegarde',
    'size' => 'Taille',
    'created_at' => 'Créé à',
    'status' => 'Statut',
    'is_locked' => 'Statut de verrouillage',
    'backup_status' => [
        'in_progress' => 'En cours',
        'successful' => 'Succès',
        'failed' => 'Échec',
    ],
    'notifications' => [
        'action' => 'Paramètres de notification',
        'toggle_manual' => 'Notifications de sauvegarde manuelle',
        'helper_manual' => 'Recevoir une notification et un e-mail lorsqu\'une sauvegarde créée manuellement pour ce serveur se termine ou échoue.',
        'toggle_scheduled' => 'Notifications de sauvegarde planifiées',
        'helper_scheduled' => 'Recevoir une notification et un courriel lorsqu\'une sauvegarde planifiée de ce serveur se termine ou échoue.',
        'saved' => 'Paramètres de notification mis à jour',
    ],
    'actions' => [
        'create' => [
            'title' => 'Créer une sauvegarde',
            'limit' => 'Limite de sauvegarde atteinte',
            'created' => ':name créé',
            'notification_success' => 'Sauvegarde créée avec succès',
            'notification_fail' => 'Échec de la création de la sauvegarde',
            'name' => 'Nom',
            'ignored' => 'Fichiers et répertoires ignorés',
            'locked' => 'Verrouillé ?',
            'lock_helper' => 'Empêche cette sauvegarde d\'être supprimée jusqu\'à ce qu\'elle soit explicitement déverrouillée.',
        ],
        'lock' => [
            'lock' => 'Verrouiller',
            'unlock' => 'Déverrouiller',
        ],
        'download' => 'Télécharger',
        'rename' => [
            'title' => 'Renommer',
            'new_name' => 'Nom de la sauvegarde',
            'notification_success' => 'Sauvegarde renommée avec succès',
        ],
        'restore' => [
            'title' => 'Restaurer',
            'helper' => 'Votre serveur va être arrêté. Vous ne serez pas en mesure de contrôler l\'état d\'alimentation, d\'accéder au gestionnaire de fichiers ou de créer des sauvegardes supplémentaires tant que ce processus n\'est pas terminé.',
            'delete_all' => 'Supprimer tous les fichiers avant de restaurer la sauvegarde ?',
            'notification_started' => 'Restauration de la sauvegarde',
            'notification_success' => 'Sauvegarde restaurée avec succès',
            'notification_fail' => 'Échec de restauration de la sauvegarde',
            'notification_fail_body_1' => 'Ce serveur n\'est pas dans un état qui permet de restaurer une sauvegarde.',
            'notification_fail_body_2' => 'Cette sauvegarde ne peut pas être restaurée pour le moment : pas terminé ou a échoué.',
        ],
        'delete' => [
            'title' => 'Supprimer la sauvegarde',
            'description' => 'Voulez-vous supprimer :backup ?',
            'notification_success' => 'Sauvegarde supprimée',
            'notification_fail' => 'Impossible de supprimer la sauvegarde',
            'notification_fail_body' => 'La connexion au noeud a échoué. Veuillez réessayer.',
        ],
    ],
];
