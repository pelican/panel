<?php

return [
    'nav_title' => 'Szerepek',
    'model_label' => 'Szerepkör',
    'model_label_plural' => 'Szerepek',
    'no_roles' => 'Nincsenek Szerepek',
    'name' => 'Szerep Neve',
    'permissions' => 'Engedélyek',
    'in_use' => 'Használatban',
    'all' => 'Mind',
    'root_admin' => 'Ez a :role minden engedéllyel rendelkezik.',
    'root_admin_delete' => 'Root Adminisztrátort nem lehet törölni',
    'users' => 'Felhasználók',
    'nodes' => 'Csomópontok',
    'nodes_hint' => 'Hagyd üresen, ha minden csomóponthoz engedélyezed a hozzáférést.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'API Kulcs',
        'allocation' => 'AllocationAllocation',
        'databaseHost' => 'Adatbázis hoszt',
        'database' => 'Adatbázis',
        'egg' => 'Egg',
        'mount' => 'Mount',
        'node' => 'Node',
        'role' => 'Rang',
        'server' => 'Szerver',
        'user' => 'Felhasználó',
        'webhook' => 'Webhook',
        'settings' => 'Beállítások',
        'health' => 'Állapot',
        'activityLog' => 'Aktivitás Napló',
        'panelLog' => 'Panel Napló',
        'plugin' => 'Plugin',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Megtekintés Lista',
        'view' => 'Megtekintés',
        'create' => 'Létrehozás',
        'update' => 'Frissítés',
        'delete' => 'Törlés',
        'import' => 'Importálás',
        'export' => 'Exportálás',
        'seeIps' => 'IP-k megtekintése',
    ],
];
