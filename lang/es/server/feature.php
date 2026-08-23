<?php

return [
    'restart_now' => 'El servidor se reiniciará ahora',
    'close' => 'Cerrar',

    'eula' => [
        'heading' => 'EULA de Minecraft',
        'description' => 'Apretando "Acepto" debajo indicas tu acuerdo con el <x-filament::link href="https://minecraft.net/eula" target="_blank">EULA </x-filament::link> de Minecraft.',
        'accept' => 'Estoy de acuerdo',
        'accepted' => 'EULA de Minecraft aceptado',
        'failed' => 'No se pudo aceptar el EULA de Minecraft',
    ],

    'gsl_token' => [
        'heading' => 'Token GSL inválido',
        'description' => 'Parece que su token de inicio de sesión de Gameserver (token GSL) no es válido o ha caducado.',
        'submit' => 'Actualizar token GSL',
        'info' => 'Puedes <x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">generar uno nuevo</x-filament::link> e introducirlo a continuación, o bien dejar el campo vacío para eliminarlo por completo.',
        'updated' => 'Token GSL actualizado',
        'failed' => 'No se pudo actualizar el token GSL',
    ],

    'java_version' => [
        'heading' => 'Versión de Java no compatible',
        'description' => 'Este servidor está ejecutando actualmente una versión no soportada de Java y no se puede iniciar.',
        'submit' => 'Actualizar imagen de Docker',
        'select_version' => 'Por favor, seleccione una versión soportada de la lista de abajo para continuar iniciando el servidor.',
        'docker_image' => 'Imagen de Docker',
        'updated' => 'Imagen de Docker actualizada.',
        'failed' => 'No se pudo actualizar la imagen de Docker',
    ],

    'pid_limit' => [
        'heading_admin' => 'Límite de memoria o procesos alcanzado...',
        'heading_user' => 'Posible límite de recursos alcanzado...',
        'description_admin' => '<p>Este servidor ha alcanzado el límite máximo de procesos o de memoria.</p><p class="mt-4">Aumentar <code>container_pid_limit</code> en la configuración de Wings, <code>config.yml</code>, podría ayudar a resolver este problema.</p><p class="mt-4"><b>Nota: Es necesario reiniciar Wings para que los cambios en el archivo de configuración surtan efecto.</b></p>',
        'description_user' => '<p>Este servidor está intentando utilizar más recursos de los asignados. Por favor, contacte al administrador y envíale el siguiente error.</p><p class="mt-4"><code>pthread_create falló; es posible que se haya agotado la memoria o que se hayan alcanzado los límites de procesos o recursos.</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Sin espacio en disco...',
        'description_admin' => '<p>Este servidor se ha quedado sin espacio en disco disponible y no puede completar el proceso de instalación o actualización.</p><p class="mt-4">Asegúrese de que la máquina disponga de suficiente espacio en disco ejecutando el comando <code class="rounded py-1 px-2">df -h</code> en la máquina que aloja este servidor. Elimine archivos o aumente el espacio en disco disponible para solucionar el problema.</p>',
        'description_user' => '<p>Este servidor se ha quedado sin espacio en disco disponible y no puede completar el proceso de instalación o actualización. Por favor, póngase en contacto con el administrador o el/los administrador(es) e infórmeles sobre los problemas de espacio en disco.</p>',
    ],
];
