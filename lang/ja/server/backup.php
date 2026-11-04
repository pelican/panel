<?php

return [
    'title' => 'バックアップ',
    'empty' => 'バックアップがありません',
    'size' => 'サイズ',
    'created_at' => '作成日時',
    'status' => '状態',
    'is_locked' => 'ロック状態',
    'backup_status' => [
        'in_progress' => '処理中',
        'successful' => '成功',
        'failed' => '失敗',
    ],
    'notifications' => [
        'action' => '通知設定',
        'toggle_manual' => '手動バックアップの通知',
        'helper_manual' => 'このサーバーの手動バックアップが完了または失敗した際に、通知とメールを受け取ります。',
        'toggle_scheduled' => 'スケジュールされたバックアップの通知',
        'helper_scheduled' => 'このサーバーのスケジュールされたバックアップが完了または失敗した際に、通知とメールを受け取ります。',
        'saved' => '通知設定が更新されました',
    ],
    'actions' => [
        'create' => [
            'title' => 'バックアップを作成',
            'limit' => 'バックアップの上限に達しました',
            'created' => ':name を作成しました',
            'notification_success' => 'バックアップを正常に作成しました',
            'notification_fail' => 'バックアップの作成に失敗しました',
            'name' => '名前',
            'ignored' => '除外するファイルとディレクトリ',
            'locked' => 'ロック？',
            'lock_helper' => '明示的にロック解除されるまで、このバックアップの削除を防ぎます。',
        ],
        'lock' => [
            'lock' => 'ロック',
            'unlock' => 'ロック解除',
        ],
        'download' => 'ダウンロード',
        'rename' => [
            'title' => '名前を変更',
            'new_name' => 'バックアップ名',
            'notification_success' => 'バックアップ名を正常に変更しました',
        ],
        'restore' => [
            'title' => '復元',
            'helper' => 'サーバーは停止します。このプロセスが完了するまで、電源状態の制御、ファイルマネージャーへのアクセス、追加バックアップの作成はできません。',
            'delete_all' => 'バックアップを復元する前にすべてのファイルを削除しますか？',
            'notification_started' => 'バックアップを復元中',
            'notification_success' => 'バックアップを正常に復元しました',
            'notification_fail' => 'バックアップの復元に失敗しました',
            'notification_fail_body_1' => 'このサーバーは現在、バックアップを復元できる状態ではありません。',
            'notification_fail_body_2' => 'このバックアップは現在復元できません: 未完了または失敗状態です。',
        ],
        'delete' => [
            'title' => 'バックアップを削除',
            'description' => ':backup を削除してよろしいですか？',
            'notification_success' => 'バックアップを削除しました',
            'notification_fail' => 'バックアップを削除できませんでした',
            'notification_fail_body' => 'ノードへの接続に失敗しました。再試行してください。',
        ],
    ],
];
