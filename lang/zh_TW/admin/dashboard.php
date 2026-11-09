<?php

return [
    'heading' => '歡迎使用 :app！',
    'version' => '版本：:version',
    'advanced' => '進階',
    'server' => '伺服器',
    'user' => '使用者',
    'sections' => [
        'intro-developers' => [
            'heading' => '開發者資訊',
            'content' => '感謝您試用開發版本！',
            'extra_note' => '如果您遇到任何問題，請在 GitHub 上回報。',
            'button_issues' => '建立 Issue',
            'button_features' => '討論功能',
        ],
        'intro-update-available' => [
            'heading' => '有可用的更新',
            'content' => ':latestVersion 現已推出！請閱讀我們的文件來更新您的面板。',
            'content_container' => ':latestVersion 目前已經可以使用！取得新的映像檔並重新建立容器來更新你的面板。',
            'button_changelog' => '最新消息',
        ],
        'intro-version-unavailable' => [
            'heading' => '無法檢查版本',
            'content' => '目前使用的版本為 :version。無法從 GitHub 獲取最新版本，因此無法確認可用的更新。',
        ],
        'intro-no-update' => [
            'heading' => '您的面板已是最新版本',
            'content' => '您目前使用的是 :version。您的面板已是最新版本！',
        ],
        'intro-first-node' => [
            'heading' => '未偵測到節點',
            'content' => '看起來您尚未設定任何節點，但別擔心，您可以點擊動作按鈕來建立第一個節點！',
            'extra_note' => '如果您遇到任何問題，請在 GitHub 上回報。',
            'button_label' => '建立第一個節點',
        ],
        'intro-support' => [
            'heading' => '支持 Pelican',
            'content' => '感謝您使用 Pelican，這一切都要歸功於您、我們的貢獻者以及所有支持者的幫助！',
            'extra_note' => '我們感謝任何人提供的任何支持。',
            'button_translate' => '協助翻譯',
            'button_donate' => '直接贊助',
        ],
        'intro-help' => [
            'heading' => '需要協助嗎？',
            'content' => '請先查看文件！如果您仍然需要幫助，請前往我們的 Discord 伺服器！',
            'button_docs' => '閱讀文件',
        ],
    ],
];
