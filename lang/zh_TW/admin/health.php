<?php

return [
    'title' => '健康狀態',
    'results_refreshed' => '健康檢查結果已更新',
    'checked' => '已檢查來自 :time 的結果',
    'refresh' => '重新整理',
    'results' => [
        'cache' => [
            'label' => '快取',
            'ok' => '正常',
            'failed_retrieve' => '無法設定或擷取應用程式快取值。',
            'failed' => '應用程式快取發生例外狀況：:error',
        ],
        'database' => [
            'label' => '資料庫',
            'ok' => '正常',
            'failed' => '無法連線至資料庫：:error',
        ],
        'debugmode' => [
            'label' => '除錯模式',
            'ok' => '除錯模式已停用',
            'failed' => '預期的除錯模式狀態為 :expected，但實際為 :actual',
        ],
        'environment' => [
            'label' => '環境',
            'ok' => '正常，已設定為 :actual',
            'failed' => '目前環境設定為 :actual，但預期應為 :expected',
        ],
        'nodeversions' => [
            'label' => '節點版本',
            'ok' => '節點已是最新版本',
            'failed' => '有 :outdated / :all 個節點已過時',
            'no_nodes_created' => '未建立任何節點',
            'no_nodes' => '沒有節點',
            'all_up_to_date' => '全部已是最新版本',
            'outdated' => '有 :outdated / :all 個已過時',
        ],
        'panelversion' => [
            'label' => '面板版本',
            'ok' => '面板已是最新版本',
            'failed' => '已安裝的版本為 :currentVersion，但最新版本為 :latestVersion',
            'up_to_date' => '已是最新版本',
            'outdated' => '已過時',
            'unknown' => '未知',
            'unavailable' => '無法從 GitHub 取得最新版本',
        ],
        'schedule' => [
            'label' => '排程',
            'ok' => '正常',
            'failed_last_ran' => '排程的最後一次執行是在 :time 分鐘之前',
            'failed_not_ran' => '排程尚未執行。',
        ],
        'useddiskspace' => [
            'label' => '磁碟空間',
        ],
        'queue' => [
            'label' => '佇列',
        ],
    ],
    'checks' => [
        'successful' => '成功',
        'failed' => '失敗 :checks 個',
    ],
];
