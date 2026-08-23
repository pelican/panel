<?php

return [
    'daemon_connection_failed' => 'Se produjo una excepción al intentar comunicarse con el daemon, lo que resultó en un código de respuesta HTTP/:code. Esta excepción ha sido registrada.',
    'node' => [
        'servers_attached' => 'Un nodo no debe tener servidores vinculados a él para poder ser eliminado.',
        'error_connecting' => 'Error al conectarse con :node',
        'daemon_off_config_updated' => 'La configuración del daemon <strong>se ha actualizado</strong>, sin embargo, se encontró un error al intentar actualizar automáticamente el archivo de configuración en el daemon. Deberás actualizar manualmente el archivo de configuración (config.yml) para que el daemon aplique estos cambios.',
    ],
    'allocations' => [
        'server_using' => 'Actualmente hay un servidor usando esta asignación. Una asignación solo puede eliminarse si ningún servidor la está usando.',
        'too_many_ports' => 'Agregar más de 1000 puertos en un solo rango a la vez no está soportado.',
        'invalid_mapping' => 'El mapeo proporcionado para el puerto :port no era válido y no pudo ser procesado.',
        'cidr_out_of_range' => 'La notación CIDR solo permite máscaras entre /25 y /32.',
        'port_out_of_range' => 'Los puertos en una asignación deben ser mayores o iguales que 1024 y menores o iguales a 65535.',
    ],
    'egg' => [
        'delete_has_servers' => 'Un egg con servidores activos vinculados a él no puede ser eliminado del panel.',
        'invalid_copy_id' => 'El egg seleccionado para copiar un script no existe o está copiando un script en sí mismo.',
        'has_children' => 'Este egg es padre de uno o más eggs. Por favor, elimina esos eggs antes de eliminar este egg.',
    ],
    'variables' => [
        'env_not_unique' => 'La variable de entorno :name debe ser única para este egg.',
        'reserved_name' => 'La variable de entorno :name está protegida y no se puede asignar a una variable.',
        'bad_validation_rule' => 'La regla de validación ":rule" no es una regla válida para esta aplicación.',
    ],
    'importer' => [
        'json_error' => 'Hubo un error al intentar analizar el archivo JSON: :error.',
        'file_error' => 'El archivo JSON proporcionado no era válido.',
        'invalid_json_provided' => 'El archivo JSON proporcionado no está en un formato que pueda ser reconocido.',
    ],
    'subusers' => [
        'editing_self' => 'No está permitido editar tu propia cuenta de subusuario.',
        'user_is_owner' => 'No puedes agregar al propietario del servidor como subusuario para este servidor.',
        'subuser_exists' => 'Ya hay un usuario con esa dirección de correo asignado como subusuario para este servidor.',
    ],
    'databases' => [
        'delete_has_databases' => 'No se puede eliminar un servidor de base de datos que tiene bases de datos activas vinculadas a él.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'El tiempo máximo de intervalo para una tarea encadenada es de 15 minutos.',
    ],
    'locations' => [
        'has_nodes' => 'No se puede eliminar una ubicación que tiene nodos activos vinculados a ella.',
    ],
    'users' => [
        'is_self' => 'No se puede eliminar tu propia cuenta de usuario.',
        'has_servers' => 'No se puede eliminar un usuario con servidores activos asociados a su cuenta. Por favor, elimina sus servidores antes de continuar.',
        'node_revocation_failed' => 'Error al revocar las claves en <a href=":link">Node #:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'No se encontraron nodos que satisfagan los requisitos especificados para el despliegue automático.',
        'no_viable_allocations' => 'No se encontraron asignaciones que satisfagan los requisitos para el despliegue automático.',
    ],
    'api' => [
        'resource_not_found' => 'El recurso solicitado no existe en este servidor.',
    ],
    'mount' => [
        'servers_attached' => 'Un volumen no debe tener servidores vinculados a él para poder ser eliminado.',
    ],
    'server' => [
        'marked_as_failed' => 'Este servidor aún no ha completado el proceso de instalación, por favor inténtalo de nuevo más tarde.',
        'file_too_large' => 'El archivo que está intentando abrir es demasiado largo para ver en el editor de archivos.',
        'state_conflict' => 'Este servidor está actualmente en un estado no compatible, por favor inténtalo de nuevo más tarde.',
        'suspended' => 'Este servidor está actualmente suspendido y la función solicitada no se encuentra disponible.',
        'maintenance' => 'El nodo de este servidor está actualmente en mantenimiento y la funcionalidad solicitada no se encuentra disponible.',
        'restoring_backup' => 'Este servidor está restaurándose desde una copia de seguridad, por favor inténtalo de nuevo más tarde.',
        'transferring' => 'Este servidor está siendo transferido a una nueva máquina, por favor inténtalo de nuevo más tarde.',
    ],
];
