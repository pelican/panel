<?php

return [
    'nav_title' => 'Webhooks',
    'model_label' => 'Webhook',
    'model_label_plural' => 'Webhooks',
    'endpoint' => 'Endpoint',
    'description' => 'Descripción',
    'name' => 'Nombre',
    'type' => 'Tipo',
    'server' => 'Servidor',
    'scope' => [
        'global' => 'Global',
        'server' => 'Servidor',
    ],
    'tabs' => [
        'global' => 'Webhooks Globales',
        'server' => 'Webhooks de servidor',
    ],
    'information' => 'Información',
    'payload' => 'Carga útil',
    'events' => 'Eventos',
    'no_webhooks' => 'Sin webhooks',
    'help' => 'Ayuda',
    'help_text' => 'Debes encerrar el nombre de la variable entre {{ }}. Por ejemplo, si quieres obtener el nombre desde la API, puedes usar {{name}}.',
    'test_now' => 'Probar ahora',
    'test_now_help' => 'Esto ejecutará el evento `Creado: Servidor`',
    'table' => [
        'description' => 'Descripción',
        'endpoint' => 'Endpoint',
    ],
    'headers' => 'Encabezados',
    'regular' => 'Regular',
    'reset_headers' => 'Restablecer encabezados',
    'unavailable_type' => 'Tipo no disponible',
    'unavailable_type_option' => ':type (no disponible)',
    'unavailable_type_text' => 'Este webhook utiliza el tipo ":type", proporcionado por un plugin que no está instalado o habilitado. Seguirá ejecutándose con los datos guardados, pero no se puede editar hasta que el plugin vuelva a estar disponible.',
];
