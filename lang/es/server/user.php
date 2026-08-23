<?php

return [
    'title' => 'Usuarios',
    'username' => 'Nombre de usuario',
    'email' => 'Correo',
    'assign_all' => 'Asignar todo',
    'invite_user' => 'Invitar usuario',
    'action' => 'Invitar',
    'remove' => 'Eliminar usuario',
    'edit' => 'Editar usuario',
    'editing' => 'Editando :user',
    'delete' => 'Eliminar usuario',
    'notification_add' => '¡Usuario invitado!',
    'notification_edit' => '¡Usuario actualizado!',
    'notification_delete' => '¡Usuario eliminado!',
    'notification_failed' => '¡Error al invitar al usuario!',
    'permissions' => [
        'title' => 'Permisos',

        'activity_title' => 'Actividad',
        'activity_desc' => 'Permisos que controlan el acceso del usuario a los registros de actividad del servidor.',

        'startup_title' => 'Inicio',
        'startup_desc' => 'Permisos que controlan la capacidad del usuario para ver los parámetros de inicio de este servidor.',

        'settings_title' => 'Configuración',
        'settings_desc' => 'Permisos que controlan la capacidad del usuario para modificar la configuración de este servidor.',

        'control_title' => 'Control',
        'control_desc' => 'Permisos que controlan la capacidad del usuario para controlar el estado de un servidor, o enviar comandos.',

        'user_title' => 'Usuario',
        'user_desc' => 'Permisos que permiten al usuario administrar otros subusuarios en un servidor. Nunca podrán editar su propia cuenta o asignar permisos que no tienen ellos mismos.',

        'file_title' => 'Archivo',
        'file_desc' => 'Permisos que controlan la capacidad del usuario para modificar el sistema de archivos de este servidor.',

        'allocation_title' => 'Asignación',
        'allocation_desc' => 'Permisos que controlan la capacidad del usuario de modificar las asignaciones de puertos para este servidor.',

        'database_title' => 'Base de datos',
        'database_desc' => 'Permisos que controlan el acceso del usuario a la administración de base de datos de este servidor.',

        'backup_title' => 'Copia de seguridad',
        'backup_desc' => 'Permisos que controlan la capacidad del usuario para generar y administrar copias de seguridad del servidor.',

        'schedule_title' => 'Programación',
        'schedule_desc' => 'Permisos que controlan el acceso del usuario a la gestión de programaciones de este servidor.',

        'startup_read' => 'Permite al usuario ver las variables de inicio de un servidor.',
        'startup_update' => 'Permite al usuario modificar las variables de inicio del servidor.',
        'startup_docker_image' => 'Permite al usuario modificar la imagen de Docker utilizada al ejecutar el servidor.',

        'settings_rename' => 'Permite al usuario renombrar este servidor.',
        'settings_description' => 'Permite a un usuario cambiar la descripción de este servidor.',
        'settings_reinstall' => 'Permite al usuario reinstalar este servidor.',
        'settings_change_icon' => 'Permite al usuario cambiar el icono de este servidor.',

        'activity_read' => 'Permite al usuario ver los registros de actividad del servidor.',

        'websocket_connect' => 'Permite a un usuario el acceso al websocket para este servidor.',

        'control_console' => 'Permite al usuario enviar datos a la consola del servidor.',
        'control_start' => 'Permite al usuario iniciar la instancia del servidor.',
        'control_stop' => 'Permite al usuario detener la instancia del servidor.',
        'control_restart' => 'Permite al usuario reiniciar la instancia del servidor.',
        'control_kill' => 'Permite al usuario forzar el cierre de la instancia del servidor.',

        'user_create' => 'Permite al usuario crear nuevas cuentas de usuario para el servidor.',
        'user_read' => 'Permite al usuario ver los usuarios asociados con este servidor.',
        'user_update' => 'Permite al usuario modificar otros usuarios asociados con este servidor.',
        'user_delete' => 'Permite al usuario eliminar otros usuarios asociados con este servidor.',

        'file_create' => 'Permite al usuario crear nuevos archivos y directorios.',
        'file_read' => 'Permite al usuario ver los contenidos de un directorio, pero no ver los contenidos o descargar archivos.',
        'file_read_content' => 'Permite al usuario ver el contenido de un archivo dado. Esto también permitirá al usuario descargar archivos.',
        'file_update' => 'Permite al usuario actualizar archivos y carpetas asociados con el servidor.',
        'file_delete' => 'Permite al usuario eliminar archivos y directorios.',
        'file_archive' => 'Permite al usuario crear archivos de archivos y descomprimir archivos existentes.',
        'file_sftp' => 'Permite al usuario realizar las acciones de los archivos anteriores usando un cliente SFTP.',

        'allocation_read' => 'Permite al usuario ver todas las asignaciones actualmente asignadas a este servidor. Los usuarios con cualquier nivel de acceso a este servidor siempre pueden ver la asignación principal.',
        'allocation_update' => 'Permite al usuario cambiar la asignación principal del servidor y adjuntar notas a cada asignación.',
        'allocation_delete' => 'Permite al usuario eliminar una asignación del servidor.',
        'allocation_create' => 'Permite al usuario añadir asignaciones adicionales al servidor.',

        'database_create' => 'Permite al usuario crear una nueva base de datos para el servidor.',
        'database_read' => 'Permite al usuario acceder a las bases de datos del servidor.',
        'database_update' => 'Permite al usuario realizar modificaciones en una base de datos. Si el usuario no tiene el permiso "Ver contraseña", no podrá modificar la contraseña.',
        'database_delete' => 'Permite al usuario eliminar una instancia de base de datos.',
        'database_view_password' => 'Permite al usuario ver una contraseña de base de datos en el sistema.',

        'schedule_create' => 'Permite al usuario crear un nuevo programación para el servidor.',
        'schedule_read' => 'Permite al usuario ver los programaciones de un servidor.',
        'schedule_update' => 'Permite al usuario realizar modificaciones en una programación existente del servidor.',
        'schedule_delete' => 'Permite al usuario eliminar un programación del servidor.',

        'backup_create' => 'Permite al usuario crear nuevas copias de seguridad para el servidor.',
        'backup_read' => 'Permite al usuario ver todas las copias de seguridad que existen para este servidor.',
        'backup_delete' => 'Permite al usuario eliminar copias de seguridad del sistema.',
        'backup_download' => 'Permite al usuario descargar una copia de seguridad para el servidor. Peligro: esto permite a un usuario acceder a todos los archivos para el servidor en la copia de seguridad.',
        'backup_restore' => 'Permite al usuario restaurar una copia de seguridad del servidor. Peligro: esto permite al usuario borrar todos los archivos del servidor en el proceso.',

        'mount_title' => 'Montajes',
        'mount_desc' => 'Permisos que controlan la capacidad del usuario para gestionar los montajes de este servidor.',
        'mount_read' => 'Permite al usuario ver la página de montajes y los montajes disponibles.',
        'mount_update' => 'Permite al usuario activar o desactivar los montajes del servidor.',
    ],
];
