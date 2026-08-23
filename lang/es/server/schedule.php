<?php

return [
    'title' => 'Programaciones',
    'new' => 'Nueva programación',
    'edit' => 'Editar programación',
    'save' => 'Guardar programación',
    'delete' => 'Eliminar programación',
    'import' => 'Importar programación',
    'export' => 'Exportar programación',
    'name' => 'Nombre',
    'cron' => 'Cron',
    'status' => 'Estado',
    'schedule_status' => [
        'new' => 'Nuevo',
        'inactive' => 'Inactivo',
        'processing' => 'Procesando',
        'active' => 'Activo',
    ],
    'no_tasks' => 'No hay tareas',
    'run_now' => 'Ejecutar ahora',
    'online_only' => 'Solo cuando esté iniciado',
    'last_run' => 'Última ejecución',
    'next_run' => 'Próxima ejecución',
    'never' => 'Nunca',
    'cancel' => 'Cancelar',

    'only_online' => '¿Solo cuando el servidor esté iniciado?',
    'only_online_hint' => 'Solo ejecutar esta programación cuando el servidor esté en ejecución.',
    'enabled' => '¿Activar programación?',
    'enabled_hint' => 'Esta programación se ejecutará automáticamente si está activada.',

    'cron_body' => 'Ten en cuenta que las entradas de cron a continuación siempre asumen UTC.',
    'cron_timezone' => 'Siguiente ejecución en tu zona horaria (:timezone): <b> :next_run </b>',

    'invalid' => 'Inválido',

    'time' => [
        'minute' => 'Minuto',
        'hour' => 'Hora',
        'day' => 'Día',
        'week' => 'Semana',
        'month' => 'Mes',
        'day_of_month' => 'Día del mes',
        'day_of_week' => 'Día de la semana',

        'hourly' => 'Cada hora',
        'daily' => 'Diariamente',
        'weekly_mon' => 'Semanal (Lunes)',
        'weekly_sun' => 'Semanal (Domingo)',
        'monthly' => 'Mensual',
        'every_min' => 'Cada x minutos',
        'every_hour' => 'Cada x horas',
        'every_day' => 'Cada x días',
        'every_week' => 'Cada x semanas',
        'every_month' => 'Cada x meses',
        'every_day_of_week' => 'Cada x día de la semana',

        'every' => 'Cada',
        'minutes' => 'Minutos',
        'hours' => 'Horas',
        'days' => 'Días',
        'months' => 'Meses',

        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ],

    'tasks' => [
        'title' => 'Tareas',
        'create' => 'Crear tarea',
        'limit' => 'Límite de tareas alcanzado',
        'action' => 'Acción',
        'payload' => 'Carga útil',
        'no_payload' => 'Sin carga útil',
        'time_offset' => 'Diferencia horaria',
        'first_task' => 'Primera tarea',
        'seconds' => 'Segundo|Segundos',
        'continue_on_failure' => 'Continuar en caso de error',

        'actions' => [
            'title' => 'Acción',
            'power' => [
                'title' => 'Enviar acción de energía',
                'action' => 'Acción de energía',
                'start' => 'Iniciar',
                'stop' => 'Detener',
                'restart' => 'Reiniciar',
                'kill' => 'Terminar proceso',
            ],
            'command' => [
                'title' => 'Enviar comando',
                'command' => 'Comando',
            ],
            'backup' => [
                'title' => 'Crear copia de seguridad',
                'files_to_ignore' => 'Archivos a ignorar',
            ],
            'delete_files' => [
                'title' => 'Borrar archivos',
                'files_to_delete' => 'Archivos a borrar',
            ],
        ],
    ],

    'notification_invalid_cron' => 'Los datos de cron proporcionados no evalúan a una expresión válida',

    'import_action' => [
        'file' => 'Archivo',
        'url' => 'URL',
        'schedule_help' => 'Este debería ser el archivo .json sin formato (schedule-daily-restart.json)',
        'url_help' => 'Las URLs deben apuntar directamente al archivo sin formato .json',
        'add_url' => 'Nueva URL',
        'import_failed' => 'Importación fallida',
        'import_success' => 'Importación realizada con éxito',
    ],
];
