<?php

return [
    'nav_title' => 'Role',
    'model_label' => 'Rola',
    'model_label_plural' => 'Role',
    'no_roles' => 'Brak ról',
    'name' => 'Nazwa roli',
    'permissions' => 'Uprawnienia',
    'in_use' => 'W użyciu',
    'all' => 'Wszystkie',
    'root_admin' => ':role ma wszystkie uprawnienia.',
    'root_admin_delete' => 'Nie można usunąć głównego administratora',
    'users' => 'Użytkownicy',
    'nodes' => 'Węzły',
    'nodes_hint' => 'Pozostaw puste, aby zezwolić na dostęp do wszystkich węzłów.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'Klucz API',
        'allocation' => 'Alokacja',
        'databaseHost' => 'Host bazy danych',
        'database' => 'Baza danych',
        'egg' => 'Jajko',
        'mount' => 'Punkt montowania',
        'node' => 'Węzeł',
        'role' => 'Rola',
        'server' => 'Serwer',
        'user' => 'Użytkownik',
        'webhook' => 'Webhook',
        'settings' => 'Ustawienia',
        'health' => 'Kondycja',
        'activityLog' => 'Log aktywności',
        'panelLog' => 'Logi Panelu',
        'plugin' => 'Wtyczka',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Zobacz listę',
        'view' => 'Wyświetl',
        'create' => 'Utwórz',
        'update' => 'Aktualizuj',
        'delete' => 'Usuń',
        'import' => 'Importuj',
        'export' => 'Eksportuj',
        'seeIps' => 'Zobacz adresy IP',
    ],
];
