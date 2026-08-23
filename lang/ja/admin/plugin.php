<?php

return [
    'nav_title' => 'プラグイン',
    'model_label' => 'プラグイン',
    'model_label_plural' => 'プラグイン',

    'name' => '名前',
    'update_available' => 'このプラグインは更新可能です',
    'author' => 'オーナー',
    'version' => 'バージョン',
    'category' => 'カテゴリー',
    'status' => 'ステータス',
    'visit_website' => 'Webサイトを閲覧',
    'settings' => '設定',
    'install' => 'インストール',
    'uninstall' => 'アンインストール',
    'update' => 'アップデート',
    'enable' => '有効',
    'disable' => '無効　',
    'import_from_file' => 'ファイルからインポート',
    'import_from_url' => 'URLからインポート',
    'file' => 'ファイル',
    'no_plugins' => 'プラグインがありません',
    'all' => 'すべて',
    'change_load_order' => '読み込み順を変更',
    'apply_load_order' => '読み込み順を適用',

    'enable_theme_modal' => [
        'heading' => 'テーマはすでに有効です',
        'description' => 'すでにテーマが有効になっています。複数のテーマを有効にすると表示のバグが起きる可能性があります。続行しますか？',
    ],

    'status_enum' => [
        'not_installed' => '未インストール',
        'disabled' => '無効',
        'enabled' => '有効',
        'errored' => 'エラー',
        'incompatible' => '非互換',
    ],

    'category_enum' => [
        'plugin' => 'プラグイン',
        'theme' => 'テーマ',
        'language' => '言語パック',
    ],

    'notifications' => [
        'goto_plugins' => 'プラグインへ移動',
        'background_info' => 'この処理には数秒かかる場合があります。完了したら通知されます。',

        'install_started' => 'プラグインのインストールをバックグラウンドで開始しました',
        'installed' => 'プラグインをインストールしました',
        'install_error' => 'プラグインをインストールできませんでした',

        'uninstall_started' => 'プラグインのアンインストールをバックグラウンドで開始しました',
        'uninstalled' => 'プラグインをアンインストールしました',
        'uninstall_error' => 'プラグインをアンインストールできませんでした',

        'update_started' => 'プラグインの更新をバックグラウンドで開始しました',
        'updated' => 'プラグインを更新しました',
        'update_error' => 'プラグインを更新できませんでした',

        'enabled' => 'プラグインを有効化しました',
        'disabled' => 'プラグインを無効化しました',
        'deleted' => 'プラグインを削除しました',

        'imported' => 'プラグインをインポートしました',
        'import_exists' => 'その ID を持つプラグインはすでに存在します',
        'import_failed' => 'プラグインをインポートできませんでした',
    ],
];
