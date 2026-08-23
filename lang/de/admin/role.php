<?php

return [
    'nav_title' => 'Rollen',
    'model_label' => 'Rolle',
    'model_label_plural' => 'Rollen',
    'no_roles' => 'Keine Rollen',
    'name' => 'Name',
    'permissions' => 'Berechtigungen',
    'in_use' => 'In Verwendung',
    'all' => 'Alle',
    'root_admin' => ':role besitzt alle Berechtigungen.',
    'root_admin_delete' => 'Root Admin kann nicht gelöscht werden',
    'users' => 'Benutzer',
    'nodes' => 'Nodes',
    'nodes_hint' => 'Leer lassen für Zugriff auf alle Nodes',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'API Schlüssel',
        'allocation' => 'Zuweisung',
        'databaseHost' => 'Datenbank Host',
        'database' => 'Datenbank',
        'egg' => 'Egg',
        'mount' => 'Einhängepunkt',
        'node' => 'Node',
        'role' => 'Rolle',
        'server' => 'Server',
        'user' => 'Nutzer',
        'webhook' => 'Webhook',
        'settings' => 'Einstellungen',
        'health' => 'Gesundheit',
        'activityLog' => 'Aktivitätslog',
        'panelLog' => 'Panel Log',
        'plugin' => 'Plugin',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Zeige Liste',
        'view' => 'Anzeigen',
        'create' => 'Erstellen',
        'update' => 'Aktualisieren',
        'delete' => 'Löschen',
        'import' => 'Importieren',
        'export' => 'Exportieren',
        'seeIps' => 'IP\'s anzeigen',
    ],
];
