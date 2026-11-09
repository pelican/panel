<?php

return [
    'nav_title' => 'Role',
    'model_label' => 'Role',
    'model_label_plural' => 'Role',
    'no_roles' => 'Žádné role',
    'name' => 'Název role',
    'permissions' => 'Oprávnění',
    'in_use' => 'Používá se',
    'all' => 'Vše',
    'root_admin' => ':role má všechna oprávnění.',
    'root_admin_delete' => 'Nelze odstranit Root Admin',
    'users' => 'Uživatelé',
    'nodes' => 'Síťové uzly',
    'nodes_hint' => 'Ponechte prázdné pro povolení přístupu ke všem uzlům.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'API klíč',
        'allocation' => 'Alokace',
        'databaseHost' => 'Databázový hostitel',
        'database' => 'Databáze',
        'egg' => 'Egg',
        'mount' => 'Připojení',
        'node' => 'Uzel',
        'role' => 'Role',
        'server' => 'Server',
        'user' => 'Uživatel',
        'webhook' => 'Webový háček',
        'settings' => 'Nastavení',
        'health' => 'Zdraví',
        'activityLog' => 'Protokol aktivity',
        'panelLog' => 'Protokol panelu',
        'plugin' => 'Plugin',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Zobrazit seznam',
        'view' => 'Zobrazit',
        'create' => 'Vytvořit',
        'update' => 'Upravit',
        'delete' => 'Smazat',
        'import' => 'Importovat',
        'export' => 'Exportovat',
        'seeIps' => 'Zobrazit IP adresy',
    ],
];
