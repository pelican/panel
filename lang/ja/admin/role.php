<?php

return [
    'nav_title' => 'ロール',
    'model_label' => 'ロール',
    'model_label_plural' => 'ロール',
    'no_roles' => 'ロールがありません',
    'name' => 'ロール名',
    'permissions' => '権限',
    'in_use' => '使用中',
    'all' => '全て',
    'root_admin' => ':role はすべての権限を持っています',
    'root_admin_delete' => 'ルート管理者は削除できません',
    'users' => 'ユーザー',
    'nodes' => 'ノード',
    'nodes_hint' => '空白のままにすると全てのNodeへのアクセスを許可します。',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'API キー',
        'allocation' => 'ポート割り当て',
        'databaseHost' => 'データベースホスト',
        'database' => 'データベース',
        'egg' => 'Egg',
        'mount' => 'マウント',
        'node' => 'ノード',
        'role' => 'ロール',
        'server' => 'サーバー',
        'user' => 'ユーザー',
        'webhook' => 'Webhook',
        'settings' => '設定',
        'health' => 'ヘルス',
        'activityLog' => 'アクティビティログ',
        'panelLog' => 'パネルログ',
        'plugin' => 'プラグイン',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => '一覧表示',
        'view' => '表示',
        'create' => '作成',
        'update' => '更新',
        'delete' => '削除',
        'import' => 'インポート',
        'export' => 'エクスポート',
        'seeIps' => 'IP を表示',
    ],
];
