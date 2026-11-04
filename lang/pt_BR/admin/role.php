<?php

return [
    'nav_title' => 'Permissões',
    'model_label' => 'Permissão',
    'model_label_plural' => 'Permissões',
    'no_roles' => 'Sem Permissões',
    'name' => 'Nome da Permissão',
    'permissions' => 'Permissões',
    'in_use' => 'Em Uso',
    'all' => 'Todas',
    'root_admin' => 'O :role tem todas as permissões.',
    'root_admin_delete' => 'Não é possível excluir o Root Admin',
    'users' => 'Usuários',
    'nodes' => 'Nodes',
    'nodes_hint' => 'Deixe em branco para permitir acesso a todos os Nodes.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'Chave da API',
        'allocation' => 'Alocação',
        'databaseHost' => 'Hospedagens do banco de dados',
        'database' => 'Banco de Dados',
        'egg' => 'Ovo',
        'mount' => 'Montagem',
        'node' => 'Node',
        'role' => 'Cargo',
        'server' => 'Servidor',
        'user' => 'Usuário',
        'webhook' => 'Webhook',
        'settings' => 'Configurações',
        'health' => 'Saúde',
        'activityLog' => 'Registro de atividades',
        'panelLog' => 'Registros do painel',
        'plugin' => 'Extensão',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Ver lista',
        'view' => 'Visualizar',
        'create' => 'Criar',
        'update' => 'Atualizar',
        'delete' => 'Excluir',
        'import' => 'Importar',
        'export' => 'Exportar',
        'seeIps' => 'Ver IPs',
    ],
];
