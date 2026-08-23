<?php

return [
    'daemon_connection_failed' => 'Une erreur est survenue lors de la tentative de communication avec le démon, entraînant un code de réponse HTTP/:code. Cette exception a été enregistrée.',
    'node' => [
        'servers_attached' => 'Une node ne doit avoir aucun serveur lié pour être supprimé.',
        'error_connecting' => 'Erreur de connexion au :node',
        'daemon_off_config_updated' => 'La configuration du daemon <strong>a été mis à jour</strong>, cependant, une erreur s\'est produite lors de la tentative de mise à jour automatique du fichier de configuration sur le daemon. Vous devrez mettre à jour manuellement le fichier de configuration (core.json) pour qu\'il puisse appliquer ces modifications.',
    ],
    'allocations' => [
        'server_using' => 'Un serveur est actuellement affecté à cette allocation. Une allocation ne peut être supprimée que si aucun serveur n\'utilise cette dernière.',
        'too_many_ports' => 'L\'ajout de plus de 1000 ports dans une seule plage à la fois n\'est pas supporté.',
        'invalid_mapping' => 'Le mappage fourni pour :port est invalide et n\'a pas pu être traitée.',
        'cidr_out_of_range' => 'La notation CIDR permet uniquement les masques entre /25 et /32.',
        'port_out_of_range' => 'Les ports d\'une allocation doivent être supérieurs à 1024 et inférieurs ou égaux à 65535.',
    ],
    'egg' => [
        'delete_has_servers' => 'Un egg avec des serveurs actifs qui y sont attachés ne peuvent pas être supprimés du Panel.',
        'invalid_copy_id' => 'L\'oeuf sélectionné pour copier un script de soit n\'existe pas, soit il copie un script lui-même.',
        'has_children' => 'Cet Egg est un parent pour un ou plusieurs autres Egg. Veuillez supprimer ces Egg avant de supprimer celui-ci.',
    ],
    'variables' => [
        'env_not_unique' => 'La variable d\'environnement :name doit être unique à cet Egg',
        'reserved_name' => 'La variable d\'environnement :name est protégée et ne peut pas être assignée à une variable.',
        'bad_validation_rule' => 'La règle de validation ":rule" n\'est pas une règle valide pour cette application.',
    ],
    'importer' => [
        'json_error' => 'Une erreur s\'est produite lors de l\'analyse du fichier JSON: :error.',
        'file_error' => 'Le fichier JSON fourni n\'est pas valide.',
        'invalid_json_provided' => 'Le fichier JSON fourni n\'est pas dans un format qui peut être reconnu.',
    ],
    'subusers' => [
        'editing_self' => 'Vous n\'êtes pas autorisé à modifier votre propre compte de sous-utilisateur',
        'user_is_owner' => 'Vous ne pouvez pas ajouter le propriétaire du serveur en tant que sous-utilisateur pour ce serveur.',
        'subuser_exists' => 'Un utilisateur avec cette adresse e-mail est déjà assigné en tant que sous-utilisateur pour ce serveur.',
    ],
    'databases' => [
        'delete_has_databases' => 'Impossible de supprimer un serveur hôte de base de données sur lequel des bases de données actives sont liées.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'L\'intervalle maximum pour une tâche chaînée est de 15 minutes.',
    ],
    'locations' => [
        'has_nodes' => 'Impossible de supprimer un emplacement auquel sont associés des nœuds actifs.',
    ],
    'users' => [
        'is_self' => 'Vous ne pouvez pas supprimer votre propre compte.',
        'has_servers' => 'Impossible de supprimer un utilisateur avec des serveurs actifs attachés à son compte. Veuillez supprimer ses serveurs avant de continuer.',
        'node_revocation_failed' => 'Échec de la révocation des clés <a href=":link">Node #:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Aucune node satisfaisant les exigences spécifiées pour le déploiement automatique n\'a pu être trouvé.',
        'no_viable_allocations' => 'Aucunes allocations satisfaisant les exigences pour le déploiement automatique n\'a pu être trouvé.',
    ],
    'api' => [
        'resource_not_found' => 'La ressource demandée n\'existe pas sur ce serveur.',
    ],
    'mount' => [
        'servers_attached' => 'Une node ne doit avoir aucun serveur lié pour être supprimé.',
    ],
    'server' => [
        'marked_as_failed' => 'Ce serveur n\'a pas encore terminé son processus d\'installation, veuillez réessayer plus tard.',
        'file_too_large' => 'Le fichier que vous essayez d\'ouvrir est trop volumineux pour être affiché dans l\'éditeur de fichiers.',
        'state_conflict' => 'Ce serveur est actuellement en état non pris en charge, veuillez réessayer plus tard.',
        'suspended' => 'Ce serveur est actuellement suspendu et la fonctionnalité demandée est indisponible.',
        'maintenance' => 'Le noeud de ce serveur est actuellement en maintenance et la fonctionnalité demandée est indisponible.',
        'restoring_backup' => 'Ce serveur est en cours de restauration à partir d\'une sauvegarde, veuillez réessayer plus tard.',
        'transferring' => 'Ce serveur est en cours de transfert vers une nouvelle machine, veuillez réessayer plus tard.',
    ],
];
