<?php

return [
    'nav_title' => 'Webhook',
    'model_label' => 'Webhook',
    'model_label_plural' => 'Webhook',
    'endpoint' => '端点',
    'description' => '描述',
    'name' => '名称',
    'type' => '类型',
    'server' => '服务器',
    'scope' => [
        'global' => '全局',
        'server' => '服务器',
    ],
    'tabs' => [
        'global' => '全局 Webhook',
        'server' => '服务器 Webhook',
    ],
    'information' => '信息',
    'payload' => '负载',
    'events' => '事件',
    'no_webhooks' => '无 Webhooks',
    'help' => '帮助',
    'help_text' => '您必须将变量名称包裹在 {{ }} 之间，例如，如果您想从 API 获取名称，可以使用 {{name}}。',
    'test_now' => '立即测试',
    'test_now_help' => '这将触发一个 `created: Server` 事件',
    'table' => [
        'description' => '描述',
        'endpoint' => '端点',
    ],
    'headers' => '标头',
    'regular' => '常规',
    'reset_headers' => '重置标头',
    'unavailable_type' => '不可用的类型',
    'unavailable_type_option' => ':type（不可用）',
    'unavailable_type_text' => '此 Webhook 使用的是 ":type" 类型，该类型由一个未安装或未启用的插件提供。它仍会使用已保存的载荷触发，但在该插件重新可用之前无法编辑。',
];
