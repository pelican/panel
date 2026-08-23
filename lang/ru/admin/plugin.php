<?php

return [
    'nav_title' => 'Плагины',
    'model_label' => 'Плагин',
    'model_label_plural' => 'Плагины',

    'name' => 'Название',
    'update_available' => 'Доступно обновление для этого плагина',
    'author' => 'Автор',
    'version' => 'Версия',
    'category' => 'Категория',
    'status' => 'Статус',
    'visit_website' => 'Посетить сайт',
    'settings' => 'Настройки',
    'install' => 'Установить',
    'uninstall' => 'Удалить',
    'update' => 'Обновить',
    'enable' => 'Включить',
    'disable' => 'Выключить',
    'import_from_file' => 'Импорт из файла',
    'import_from_url' => 'Импорт из URL',
    'file' => 'Файлы',
    'no_plugins' => 'Нет плагинов',
    'all' => 'Все',
    'change_load_order' => 'Изменить порядок загрузки',
    'apply_load_order' => 'Применить порядок загрузки',

    'enable_theme_modal' => [
        'heading' => 'Тема уже включена',
        'description' => 'У вас уже включена тема. Включение нескольких тем может привести к визуальным ошибкам. Вы хотите продолжить?',
    ],

    'status_enum' => [
        'not_installed' => 'Не установлено',
        'disabled' => 'Выключено',
        'enabled' => 'Включено',
        'errored' => 'Ошибка',
        'incompatible' => 'Несовместимо',
    ],

    'category_enum' => [
        'plugin' => 'Плагин',
        'theme' => 'Тема',
        'language' => 'Языковой пакет',
    ],

    'notifications' => [
        'goto_plugins' => 'Перейти в плагины',
        'background_info' => 'Этот процесс может занять несколько секунд. Вы будете уведомлены о его завершении.',

        'install_started' => 'Установка плагина началась в фоновом режиме',
        'installed' => 'Плагин установлен',
        'install_error' => 'Не удалось установить плагин',

        'uninstall_started' => 'Удаление плагинов запущено в фоновом режиме',
        'uninstalled' => 'Плагин удален',
        'uninstall_error' => 'Не удалось удалить плагин',

        'update_started' => 'Обновление плагина запущено в фоновом режиме',
        'updated' => 'Плагин обновлен',
        'update_error' => 'Не удалось обновить плагин',

        'enabled' => 'Плагин включен',
        'disabled' => 'Плагин отключен',
        'deleted' => 'Плагин удален',

        'imported' => 'Плагин импортирован',
        'import_exists' => 'Плагин с таким id уже существует',
        'import_failed' => 'Не удалось импортировать плагин',
    ],
];
