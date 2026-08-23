<?php

return [
    'nav_title' => 'Rôles',
    'model_label' => 'Rôle',
    'model_label_plural' => 'Rôles',
    'no_roles' => 'Pas de rôles',
    'name' => 'Nom du rôle',
    'permissions' => 'Permissions',
    'in_use' => 'Active',
    'all' => 'Tout',
    'root_admin' => 'Le :role a toutes les permissions.',
    'root_admin_delete' => 'Impossible de supprimer l\'administrateur racine',
    'users' => 'Utilisateurs',
    'nodes' => 'Nœuds',
    'nodes_hint' => 'Laisser vide pour autoriser l\'accès à tous les nœuds.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'Clé API',
        'allocation' => 'Allocation',
        'databaseHost' => 'Hôte de base de données',
        'database' => 'Base de données',
        'egg' => 'Œuf',
        'mount' => 'Montage',
        'node' => 'Nœud',
        'role' => 'Rôle',
        'server' => 'Serveur',
        'user' => 'Utilisateur',
        'webhook' => 'Webhook',
        'settings' => 'Paramètres',
        'health' => 'Santé',
        'activityLog' => 'Journal d\'activité',
        'panelLog' => 'Logs du Panel',
        'plugin' => 'Extension',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Afficher la liste',
        'view' => 'Afficher',
        'create' => 'Créer',
        'update' => 'Mettre à jour',
        'delete' => 'Supprimer',
        'import' => 'Importer',
        'export' => 'Exporter',
        'seeIps' => 'Voir les adresses IP',
    ],
];
