<?php

return [
    'title' => 'スケジュール',
    'new' => '新しいスケジュール',
    'edit' => 'スケジュールを編集',
    'save' => 'スケジュールを保存',
    'delete' => 'スケジュールを削除',
    'import' => 'スケジュールをインポート',
    'export' => 'スケジュールをエクスポート',
    'name' => '名前',
    'cron' => 'Cron',
    'status' => '状態',
    'schedule_status' => [
        'new' => '新規',
        'inactive' => '無効',
        'processing' => '処理中',
        'active' => '有効',
    ],
    'no_tasks' => 'タスクがありません',
    'run_now' => '今すぐ実行',
    'online_only' => 'オンライン時のみ',
    'last_run' => '前回実行',
    'next_run' => '次回実行',
    'never' => 'しない',
    'cancel' => 'キャンセル',

    'only_online' => 'サーバーがオンラインの時のみ実行しますか？',
    'only_online_hint' => 'サーバーが実行中の状態の時のみこのスケジュールを実行します。',
    'enabled' => 'スケジュールを有効にしますか？',
    'enabled_hint' => '有効にするとこのスケジュールは自動的に実行されます。',

    'cron_body' => 'Cron 入力は常に UTC を前提としていることに注意してください。',
    'cron_timezone' => 'お使いのタイムゾーン (:timezone) での次回実行時刻: <b> :next_run </b>',

    'invalid' => '無効',

    'time' => [
        'minute' => '分',
        'hour' => '時',
        'day' => '日',
        'week' => '週',
        'month' => '月',
        'day_of_month' => '日（月ごと）',
        'day_of_week' => '曜日',

        'hourly' => '毎時',
        'daily' => '毎日',
        'weekly_mon' => '毎週（月曜日）',
        'weekly_sun' => '毎週（日曜日）',
        'monthly' => '毎月',
        'every_min' => 'x 分ごと',
        'every_hour' => 'x 時間ごと',
        'every_day' => 'x 日ごと',
        'every_week' => 'x 週ごと',
        'every_month' => 'x か月ごと',
        'every_day_of_week' => '毎週 x 曜日',

        'every' => '毎',
        'minutes' => '分',
        'hours' => '時間',
        'days' => '日',
        'months' => '月',

        'monday' => '月曜日',
        'tuesday' => '火曜日',
        'wednesday' => '水曜日',
        'thursday' => '木曜日',
        'friday' => '金曜日',
        'saturday' => '土曜日',
        'sunday' => '日曜日',
    ],

    'tasks' => [
        'title' => 'タスク',
        'create' => 'タスクを作成',
        'limit' => 'タスクの上限に達しました',
        'action' => 'アクション',
        'payload' => 'ペイロード',
        'no_payload' => 'ペイロードなし',
        'time_offset' => '時間オフセット',
        'first_task' => '最初のタスク',
        'seconds' => '秒|秒',
        'continue_on_failure' => '失敗時も続行',

        'actions' => [
            'title' => 'アクション',
            'power' => [
                'title' => '電源アクションを送信',
                'action' => '電源アクション',
                'start' => '起動',
                'stop' => '停止',
                'restart' => '再起動',
                'kill' => '強制終了',
            ],
            'command' => [
                'title' => 'コマンドを送信',
                'command' => 'コマンド',
            ],
            'backup' => [
                'title' => 'バックアップを作成',
                'files_to_ignore' => '除外するファイル',
            ],
            'delete_files' => [
                'title' => 'ファイルを削除',
                'files_to_delete' => '削除するファイル',
            ],
        ],
    ],

    'notification_invalid_cron' => '指定された Cron データは有効な式として評価できません',

    'import_action' => [
        'file' => 'ファイル',
        'url' => 'URL',
        'schedule_help' => '生の .json ファイルを指定してください（例: schedule-daily-restart.json）',
        'url_help' => 'URL は直接生の .json ファイルを指している必要があります',
        'add_url' => '新しい URL',
        'import_failed' => 'インポートに失敗しました',
        'import_success' => 'インポートに成功しました',
    ],
];
