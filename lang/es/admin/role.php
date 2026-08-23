<?php

return [
    'nav_title' => 'Roles',
    'model_label' => 'Rol',
    'model_label_plural' => 'Roles',
    'no_roles' => 'No hay roles',
    'name' => 'Nombre del rol',
    'permissions' => 'Permisos',
    'in_use' => 'En uso',
    'all' => 'Todos',
    'root_admin' => 'El rol :role tiene todos los permisos.',
    'root_admin_delete' => 'No es posible eliminar el administrador raíz',
    'users' => 'Usuarios',
    'nodes' => 'Nodes',
    'nodes_hint' => 'Déjalo vacío para permitir el acceso a todos los nodos.',

    // Permission section headings (RolePermissionModels enum + special permission groups)
    'models' => [
        'apiKey' => 'Clave API',
        'allocation' => 'Asignación',
        'databaseHost' => 'Host de la base de datos',
        'database' => 'Base de datos',
        'egg' => 'Huevo',
        'mount' => 'Montar',
        'node' => 'Nodo',
        'role' => 'Rol',
        'server' => 'Servidor',
        'user' => 'Usuario',
        'webhook' => 'Vincular Webhook',
        'settings' => 'Configuración',
        'health' => 'Estado del sistema',
        'activityLog' => 'Registros de Actividad',
        'panelLog' => 'Registros del Panel',
        'plugin' => 'Extensión',
    ],

    // Permission checkbox labels (RolePermissionPrefixes enum + model-specific/special prefixes)
    'permissions_list' => [
        'viewList' => 'Ver lista',
        'view' => 'Ver',
        'create' => 'Crear',
        'update' => 'Actualizar',
        'delete' => 'Eliminar',
        'import' => 'Importar',
        'export' => 'Exportar',
        'seeIps' => 'Ver IP',
    ],
];
