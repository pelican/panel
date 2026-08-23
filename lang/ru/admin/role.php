<?php

return [
    'nav_title' => 'Роли',
    'model_label' => 'Роль',
    'model_label_plural' => 'Роли',
    'no_roles' => 'Нет ролей',
    'name' => 'Название роли',
    'permissions' => 'Права',
    'in_use' => 'Используется',
    'all' => 'Все',
    'root_admin' => ':role имеет все права.',
    'root_admin_delete' => 'Нельзя удалить Root Admin',
    'users' => 'Пользователи',
    'nodes' => 'Узлы',
    'nodes_hint' => 'Оставьте поле пустым, чтобы разрешить доступ ко всем узлам.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'API Ключ',
        'allocation' => 'Распределение',
        'databaseHost' => 'Хост баз данных',
        'database' => 'База данных',
        'egg' => 'Ядро',
        'mount' => 'Установка',
        'node' => 'Узел',
        'role' => 'Роль',
        'server' => 'Сервер',
        'user' => 'Пользователь',
        'webhook' => 'Веб хук',
        'settings' => 'Настройки',
        'health' => '',
        'activityLog' => 'Лог действий',
        'panelLog' => 'Лог действий панели',
        'plugin' => 'Плагин',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Просмотр списка',
        'view' => 'Просмотр',
        'create' => 'Создать',
        'update' => 'Обновить',
        'delete' => 'Удалить',
        'import' => 'Импортировать',
        'export' => 'Экспортировать',
        'seeIps' => 'Показать IP',
    ],
];
