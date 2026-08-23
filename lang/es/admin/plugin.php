<?php

return [
    'nav_title' => 'Plugins',
    'model_label' => 'Plugin',
    'model_label_plural' => 'Plugins',

    'name' => 'Nombre',
    'update_available' => 'Hay una actualización disponible para este plugin',
    'author' => 'Autor',
    'version' => 'Versión',
    'category' => 'Categoría',
    'status' => 'Estado',
    'visit_website' => 'Visita el sitio web',
    'settings' => 'Ajustes',
    'install' => 'Instalar',
    'uninstall' => 'Desinstalar',
    'update' => 'Actualizar',
    'enable' => 'Activar',
    'disable' => 'Desactivar',
    'import_from_file' => 'Importar desde archivo',
    'import_from_url' => 'Importar desde URL',
    'file' => 'Archivo',
    'no_plugins' => 'No hay plugins',
    'all' => 'Todos',
    'change_load_order' => 'Cambiar orden de carga',
    'apply_load_order' => 'Aplicar orden de carga',

    'enable_theme_modal' => [
        'heading' => 'El tema ya está activado',
        'description' => 'Ya tienes un tema activado. Activar varios temas puede provocar errores visuales. ¿Quieres continuar?',
    ],

    'status_enum' => [
        'not_installed' => 'No instalado',
        'disabled' => 'Desactivado',
        'enabled' => 'Activado',
        'errored' => 'Fallido',
        'incompatible' => 'Incompatible',
    ],

    'category_enum' => [
        'plugin' => 'Plugin',
        'theme' => 'Tema',
        'language' => 'Paquete de idioma',
    ],

    'notifications' => [
        'goto_plugins' => 'Ir a Plugins',
        'background_info' => 'Este proceso puede tardar unos segundos. Serás notificado una vez que haya terminado.',

        'install_started' => 'La instalación del plugin ha comenzado en segundo plano',
        'installed' => 'Plugin instalado',
        'install_error' => 'No se ha podido instalar el plugin',

        'uninstall_started' => 'La instalación del plugin ha comenzado en segundo plano',
        'uninstalled' => 'Plugin desinstalado',
        'uninstall_error' => 'No se ha podido desinstalar el plugin',

        'update_started' => 'La actualización del plugin ha comenzado en segundo plano',
        'updated' => 'Plugin actualizado',
        'update_error' => 'No se ha podido actualizar el plugin',

        'enabled' => 'Plugin activado',
        'disabled' => 'Plugin desactivado',
        'deleted' => 'Plugin eliminado',

        'imported' => 'Plugin importado',
        'import_exists' => 'Ya existe un plugin con ese id',
        'import_failed' => 'No se ha podido importar el plugin',
    ],
];
