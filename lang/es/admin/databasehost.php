<?php

return [
    'nav_title' => 'Bases de datos',
    'model_label' => 'Host de base de datos',
    'model_label_plural' => 'Bases de datos',
    'table' => [
        'database' => 'Base de datos',
        'name' => 'Nombre',
        'host' => 'Host',
        'port' => 'Puerto',
        'name_helper' => 'Dejar este espacio en blanco generará automáticamente un nombre aleatorio',
        'username' => 'Nombre de usuario',
        'password' => 'Contraseña',
        'remote' => 'Conexiones desde',
        'remote_helper' => 'Desde dónde se deben permitir las conexiones. Déjalo en blanco para permitir conexiones desde cualquier lugar.',
        'max_connections' => 'Número máximo de conexiones',
        'created_at' => 'Creado el',
        'connection_string' => 'Cadena de conexión JDBC',
    ],
    'error' => 'Error al conectar al host',
    'host' => 'Host',
    'host_help' => 'La dirección IP o nombre de dominio que debe ser usado cuando se intenta conectar a este host MySQL desde este panel para crear nuevas bases de datos.',
    'port' => 'Puerto',
    'port_help' => 'El puerto en el que MySQL se está ejecutando para este host.',
    'max_database' => 'Número máximo de bases de datos',
    'max_databases_help' => 'El número máximo de bases de datos que se pueden crear en este host. Si se alcanza el límite, no se podrán crear nuevas bases de datos en este host. Déjalo en blanco para que sea ilimitado.',
    'display_name' => 'Nombre visible',
    'display_name_help' => 'La dirección IP o el nombre de dominio que se mostrará al usuario final.',
    'username' => 'Nombre de usuario',
    'username_help' => 'El nombre de usuario que tiene permisos suficientes para crear nuevos usuarios y bases de datos en el sistema.',
    'password' => 'Contraseña',
    'password_help' => 'Contraseña para el usuario de la base de datos.',
    'linked_nodes' => 'Nodos vinculados',
    'linked_nodes_help' => 'Esta configuración se establece por defecto en este host de base de datos cuando se añade una base de datos a un servidor en el nodo seleccionado.',
    'connection_error' => 'Error al conectar al host de base de datos',
    'no_database_hosts' => 'No hay hosts de base de datos',
    'no_databases' => 'No hay bases de datos',
    'no_nodes' => 'No hay nodos',
    'nodes' => 'Nodos',
    'delete_help' => 'Este host de base de datos tiene bases de datos',
    'unlimited' => 'Ilimitado',
    'anywhere' => 'Cualquier lugar',

    'rotate' => 'Rotar',
    'rotate_password' => 'Renovar contraseña',
    'rotated' => 'Contraseña renovada',
    'rotate_error' => 'Error al renovar la contraseña',
    'databases' => 'Bases de datos',

    'setup' => [
        'preparations' => 'Preparativos',
        'database_setup' => 'Configuración de la base de datos',
        'panel_setup' => 'Configuración del panel',

        'note' => 'Actualmente, ¡solo bases de datos MySQL/ MariaDB están soportadas para hosts de bases de datos!',
        'different_server' => '¿El panel y la base de datos <i>no</i> están en el mismo servidor?',

        'database_user' => 'Usuario de la base de datos',
        'cli_login' => 'Usa <code>mysql -u root -p</code> para acceder a la CLI de MySQL.',
        'command_create_user' => 'Comando para crear el usuario',
        'command_assign_permissions' => 'Comando para asignar permisos',
        'cli_exit' => 'Para salir del cli de mysql, ejecuta <code>exit</code>.',
        'external_access' => 'Acceso externo',
        'allow_external_access' => '
<p>Es probable que necesites permitir el acceso externo a esta instancia de MySQL para que los servidores puedan conectarse a ella.</p>
<br>
<p>Para hacer esto, abre <code>my.cnf</code>, cuya ubicación varía según tu sistema operativo y cómo se haya instalado MySQL. Puedes escribir <code>find /etc -iname my.cnf</code> para localizarlo.</p>
<br>
<p>Abre <code>my.cnf</code>, añade el siguiente texto al final del archivo y guarda los cambios: <code>[mysqld] bind-address=0.0.0.0</code></p>
<br>
<p>Reinicia MySQL/MariaDB para aplicar estos cambios. Esto sobrescribirá la configuración predeterminada de MySQL, que por defecto solo acepta solicitudes desde localhost. Al actualizarla, se permitirán conexiones en todas las interfaces y, por lo tanto, conexiones externas. Asegúrate de permitir el puerto de MySQL (por defecto 3306) en tu firewall.</p>                                ',
    ],
];
