<?php

return [
    'heading' => '¡Bienvenido a :app!',
    'version' => 'Versión: :version',
    'advanced' => 'Avanzado',
    'server' => 'Servidor',
    'user' => 'Usuario',
    'sections' => [
        'intro-developers' => [
            'heading' => 'Información para desarrolladores',
            'content' => '¡Gracias por probar la versión en desarrollo!',
            'extra_note' => 'Si tienes algún problema, por favor repórtalo en GitHub.',
            'button_issues' => 'Crear incidencia',
            'button_features' => 'Discutir funcionalidades',
        ],
        'intro-update-available' => [
            'heading' => 'Actualización disponible',
            'content' => '¡La versión :latestVersion ya está disponible! Consulta nuestra documentación para actualizar tu panel.',
            'content_container' => '¡Una nueva versión ya está disponible! Descarga la nueva imagen del contenedor Docker para actualizar el panel.',
            'button_changelog' => '¿Qué hay de nuevo?',
        ],
        'intro-version-unavailable' => [
            'heading' => 'No se ha podido comprobar la versión',
            'content' => 'Estás usando la versión :version . No hemos podido comprobar la versión más reciente mediante GitHub, así que no podemos buscar actualizaciones disponibles.',
        ],
        'intro-no-update' => [
            'heading' => 'Tu panel está actualizado',
            'content' => 'Actualmente estás usando la versión :version. ¡Tu panel está actualizado!',
        ],
        'intro-first-node' => [
            'heading' => 'No se han detectado nodos',
            'content' => 'Parece que aún no tienes ningún nodo configurado, pero no te preocupes: haz clic en el botón de acción para crear el primero.',
            'extra_note' => 'Si tienes algún problema, por favor repórtalo en GitHub.',
            'button_label' => 'Crea el primer nodo',
        ],
        'intro-support' => [
            'heading' => 'Apoyar a Pelican',
            'content' => 'Gracias por usar Pelican. Esto solo ha sido posible gracias a vuestro apoyo, al de nuestros colaboradores y al de toda la comunidad.',
            'extra_note' => 'Agradecemos todo el apoyo de cualquier persona.',
            'button_translate' => 'Ayudar a traducir',
            'button_donate' => 'Donar directamente',
        ],
        'intro-help' => [
            'heading' => '¿Necesitas ayuda?',
            'content' => '¡Echa un vistazo a la documentación primero! ¡Si todavía necesitas ayuda, dirígete a nuestro servidor de discord!',
            'button_docs' => 'Leer la documentación',
        ],
    ],
];
