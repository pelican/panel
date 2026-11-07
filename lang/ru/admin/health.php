<?php

return [
    'title' => 'Состояние',
    'results_refreshed' => 'Результаты проверки состояния обновлены',
    'checked' => 'Состояние проверено :time',
    'refresh' => 'Обновить',
    'results' => [
        'cache' => [
            'label' => 'Кэш',
            'ok' => 'Ок',
            'failed_retrieve' => 'Не удалось установить или получить состояние кэша.',
            'failed' => 'Произошла ошибка с кэшем приложения: :error',
        ],
        'database' => [
            'label' => 'База данных',
            'ok' => 'Ок',
            'failed' => 'Не удалось подключиться к базе данных: :error',
        ],
        'debugmode' => [
            'label' => 'Режим отладки',
            'ok' => 'Режим отладки выключен',
            'failed' => 'Режим отладки должен был быть :expected, но на самом деле :actual',
        ],
        'environment' => [
            'label' => 'Окружение',
            'ok' => 'Ок, установлено на :actual',
            'failed' => 'Окружение установлено на :actual, ожидалось :expected',
        ],
        'nodeversions' => [
            'label' => 'Версии узлов',
            'ok' => 'Узлы обновлены',
            'failed' => ':outdated/:all Узлов устарели',
            'no_nodes_created' => 'Узлы не обнаружены',
            'no_nodes' => 'Нет узлов',
            'all_up_to_date' => 'Все актуальны',
            'outdated' => ':outdated/:all устарели',
        ],
        'panelversion' => [
            'label' => 'Версия панели',
            'ok' => 'Версия вашей панели актуальна',
            'failed' => 'Установленная версия :currentVersion, но последняя это :latestVersion',
            'up_to_date' => 'Актуальна',
            'outdated' => 'Устарела',
            'unknown' => 'Неизвестно',
            'unavailable' => 'Не удалось загрузить последнюю версию с GitHub',
        ],
        'schedule' => [
            'label' => 'Расписания',
            'ok' => 'Ок',
            'failed_last_ran' => 'Последний запуск расписания был более чем :time минут назад',
            'failed_not_ran' => 'Расписания ещё не запускались',
        ],
        'useddiskspace' => [
            'label' => 'Использование диска',
        ],
        'queue' => [
            'label' => 'Очередь',
        ],
    ],
    'checks' => [
        'successful' => 'Успешно',
        'failed' => 'Не удалось',
    ],
];
