<?php

return [
    'title' => 'Copias de seguridad',
    'empty' => 'Sin copias de seguridad',
    'size' => 'Tamaño',
    'created_at' => 'Creado el',
    'status' => 'Estado',
    'is_locked' => 'Estado del bloqueo',
    'backup_status' => [
        'in_progress' => 'En curso',
        'successful' => 'Completado',
        'failed' => 'Fallido',
    ],
    'notifications' => [
        'action' => 'Ajustes de notificaciones',
        'toggle_manual' => 'Notificaciones de copia de seguridad manual',
        'helper_manual' => 'Recibir una notificación y correo electrónico cuando una copia de seguridad creada manualmente para este servidor se complete o falle.',
        'toggle_scheduled' => 'Notificaciones de copias de seguridad programadas',
        'helper_scheduled' => 'Recibir una notificación y correo electrónico cuando una copia de seguridad programada de este servidor se complete o falle.',
        'saved' => 'Ajustes de notificaciones actualizados',
    ],
    'actions' => [
        'create' => [
            'title' => 'Crear copia de seguridad',
            'limit' => 'Límite de copia de seguridad alcanzado',
            'created' => ':name creado',
            'notification_success' => 'Copia de seguridad creada con éxito',
            'notification_fail' => 'Creación de copia de seguridad fallida',
            'name' => 'Nombre',
            'ignored' => 'Archivos y directorios ignorados',
            'locked' => '¿Bloqueada?',
            'lock_helper' => 'Evita que esta copia de seguridad sea eliminada hasta que se desbloquee explícitamente.',
        ],
        'lock' => [
            'lock' => 'Bloquear',
            'unlock' => 'Desbloquear',
        ],
        'download' => 'Descargar',
        'rename' => [
            'title' => 'Renombrar',
            'new_name' => 'Nombre de la copia de seguridad',
            'notification_success' => 'Copia de seguridad renombrada correctamente',
        ],
        'restore' => [
            'title' => 'Restaurar',
            'helper' => 'Tu servidor se detendrá. No podrás controlar el estado del servidor, acceder al administrador de archivos o crear copias de seguridad adicionales hasta que este proceso sea completado.',
            'delete_all' => '¿Borrar todos los archivos antes de restaurar la copia de seguridad?',
            'notification_started' => 'Restaurando copia de seguridad',
            'notification_success' => 'Copia de seguridad restaurada con éxito',
            'notification_fail' => 'Error al recuperar la copia de seguridad',
            'notification_fail_body_1' => 'Este servidor no se encuentra actualmente en un estado que permita restaurar una copia de seguridad.',
            'notification_fail_body_2' => 'Esta copia de seguridad no se puede restaurar en este momento: no se ha completado o ha fallado.',
        ],
        'delete' => [
            'title' => 'Eliminar copia de seguridad',
            'description' => '¿Deseas eliminar :backup?',
            'notification_success' => 'Copia de seguridad eliminada',
            'notification_fail' => 'No se pudo eliminar la copia de seguridad',
            'notification_fail_body' => 'Error de conexión al node. Por favor, inténtalo de nuevo.',
        ],
    ],
];
