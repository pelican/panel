<?php

return [
    'nav_title' => 'Ruoli',
    'model_label' => 'Ruolo',
    'model_label_plural' => 'Ruoli',
    'no_roles' => 'Nessun ruolo',
    'name' => 'Nome del ruolo',
    'permissions' => 'Permessi',
    'in_use' => 'In uso',
    'all' => 'Tutti',
    'root_admin' => 'Il :role ha tutti i permessi.',
    'root_admin_delete' => 'Impossibile eliminare Root Admin',
    'users' => 'Utenti',
    'nodes' => 'Nodi',
    'nodes_hint' => 'Lasciare vuoto per consentire l\'accesso a tutti i nodi.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'Chiave API',
        'allocation' => 'Allocazione',
        'databaseHost' => 'Host database',
        'database' => 'Database',
        'egg' => 'Egg',
        'mount' => 'Mount',
        'node' => 'Nodo',
        'role' => 'Ruolo',
        'server' => 'Server',
        'user' => 'Utente',
        'webhook' => 'Webhook',
        'settings' => 'Impostazioni',
        'health' => 'Salute',
        'activityLog' => 'Log attività',
        'panelLog' => 'Log pannello',
        'plugin' => 'Plugin',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Visualizza elenco',
        'view' => 'Visualizza',
        'create' => 'Crea',
        'update' => 'Aggiorna',
        'delete' => 'Elimina',
        'import' => 'Importa',
        'export' => 'Esporta',
        'seeIps' => 'Vedi IP',
    ],
];
