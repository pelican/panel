<?php

return [
    'nav_title' => 'Rollen',
    'model_label' => 'Rol',
    'model_label_plural' => 'Rollen',
    'no_roles' => 'Geen rollen',
    'name' => 'Rol naam',
    'permissions' => 'Permissies',
    'in_use' => 'In gebruik',
    'all' => 'All',
    'root_admin' => 'De :role heeft alle rechten.',
    'root_admin_delete' => 'Kan Root Admin niet verwijderen',
    'users' => 'Gebruikers',
    'nodes' => 'Nodes',
    'nodes_hint' => 'Laat leeg om toegang tot alle nodes te hebben.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'API-sleutel',
        'allocation' => 'Toewijzing',
        'databaseHost' => 'Databank server',
        'database' => 'Databanken',
        'egg' => 'Ei',
        'mount' => 'Koppelen',
        'node' => 'Knooppunt',
        'role' => 'Rol',
        'server' => 'Server',
        'user' => 'Gebruiker',
        'webhook' => 'Web Koppeling',
        'settings' => 'Instellingen',
        'health' => 'Gezondheid',
        'activityLog' => 'Activiteitenlogboek',
        'panelLog' => 'Paneel Logboek',
        'plugin' => 'Plug-in',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Weergavelijst',
        'view' => 'Bekijken',
        'create' => 'Aanmaken',
        'update' => 'Bijwerken',
        'delete' => 'Verwijderen',
        'import' => 'Importeren',
        'export' => 'Exporteren',
        'seeIps' => 'Bekijk IP\'s',
    ],
];
