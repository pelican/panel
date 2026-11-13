<?php

return [
    'title' => '备份',
    'empty' => '没有备份',
    'size' => '大小',
    'created_at' => '创建于',
    'status' => '状态',
    'is_locked' => '锁定状态',
    'backup_status' => [
        'in_progress' => '进行中',
        'successful' => '成功',
        'failed' => '失败',
    ],
    'notifications' => [
        'action' => '通知设置',
        'toggle_manual' => '手动备份通知',
        'helper_manual' => '当此服务器的手动备份完成或失败时，接收通知和电子邮件。',
        'toggle_scheduled' => '计划备份通知',
        'helper_scheduled' => '当此服务器的计划备份完成或失败时，接收通知和电子邮件。',
        'saved' => '通知设置已更新',
    ],
    'actions' => [
        'create' => [
            'title' => '创建备份',
            'limit' => '已达到备份限制',
            'created' => '已创建 :name',
            'notification_success' => '备份创建成功',
            'notification_fail' => '备份创建失败',
            'name' => '名称',
            'ignored' => '忽略的文件和目录',
            'locked' => '已锁定？',
            'lock_helper' => '防止删除此备份，直到明确解锁为止。',
        ],
        'lock' => [
            'lock' => '锁定',
            'unlock' => '解锁',
        ],
        'download' => '下载',
        'rename' => [
            'title' => '重命名',
            'new_name' => '备份名称',
            'notification_success' => '备份重命名成功',
        ],
        'restore' => [
            'title' => '恢复',
            'helper' => '您的服务器将被停止。在此过程完成之前，您将无法控制电源状态、访问文件管理器或创建其他备份。',
            'delete_all' => '在恢复备份之前删除所有文件吗？',
            'notification_started' => '正在恢复备份',
            'notification_success' => '备份恢复成功',
            'notification_fail' => '备份恢复失败',
            'notification_fail_body_1' => '此服务器目前未处于允许恢复备份的状态。',
            'notification_fail_body_2' => '此备份目前无法恢复：未完成或失败。',
        ],
        'delete' => [
            'title' => '删除备份',
            'description' => '您希望删除 :backup 吗？',
            'notification_success' => '备份已删除',
            'notification_fail' => '无法删除备份',
            'notification_fail_body' => '连接到节点失败。请重试。',
        ],
    ],
];
