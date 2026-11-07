<?php

return [
    'daemon_connection_failed' => '尝试与守护进程通信时出现异常，导致 HTTP/:code 响应代码。此异常已被记录。',
    'node' => [
        'servers_attached' => '节点必须没有链接到它的服务器才能被删除。',
        'error_connecting' => '连接到 :node 时出错',
        'not_viable' => '节点 :node 没有足够的空闲资源来接收此服务器。',
        'daemon_off_config_updated' => '守护进程配置<strong>已更新</strong>，但在尝试自动更新守护进程上的配置文件时遇到了错误。您需要手动更新守护进程的配置文件 (config.yml) 以应用这些更改。',
    ],
    'allocations' => [
        'server_using' => '当前有服务器正在使用此分配。只有在未分配给任何服务器的情况下才能删除分配。',
        'too_many_ports' => '不支持一次在单个范围内添加超过 1000 个端口。',
        'invalid_mapping' => '为 :port 提供的映射无效，无法处理。',
        'cidr_out_of_range' => 'CIDR 表示法仅允许 /25 和 /32 之间的掩码。',
        'port_out_of_range' => '分配中的端口必须大于或等于 1024 且小于或等于 65535。',
    ],
    'egg' => [
        'delete_has_servers' => '无法从面板中删除附加了活动服务器的 Egg。',
        'invalid_copy_id' => '选择用于复制脚本的 Egg 不存在，或者它本身正在复制脚本。',
        'has_children' => '此 Egg 是一个或多个其他 Egg 的父级。请在删除此 Egg 之前删除那些 Egg。',
    ],
    'variables' => [
        'env_not_unique' => '环境变量 :name 必须对此 Egg 是唯一的。',
        'reserved_name' => '环境变量 :name 受保护，不能分配给变量。',
        'bad_validation_rule' => '验证规则 ":rule" 对此应用程序不是有效的规则。',
    ],
    'importer' => [
        'json_error' => '尝试解析 JSON 文件时出错： :error。',
        'file_error' => '提供的 JSON 文件无效。',
        'invalid_json_provided' => '提供的 JSON 文件格式无法识别。',
    ],
    'subusers' => [
        'editing_self' => '不允许编辑您自己的子用户账户。',
        'user_is_owner' => '您不能将服务器所有者添加为此服务器的子用户。',
        'subuser_exists' => '具有该电子邮件地址的用户已被分配为此服务器的子用户。',
    ],
    'databases' => [
        'delete_has_databases' => '无法删除链接了活动数据库的数据库主机服务器。',
    ],
    'tasks' => [
        'chain_interval_too_long' => '链式任务的最大间隔时间为 15 分钟。',
    ],
    'locations' => [
        'has_nodes' => '无法删除附加了活动节点的位置。',
    ],
    'users' => [
        'is_self' => '无法删除您自己的用户账户。',
        'has_servers' => '无法删除账户上附加了活动服务器的用户。请在继续之前删除其服务器。',
        'node_revocation_failed' => '撤销 <a href=":link">节点 #:node</a> 上的密钥失败。 :error',
    ],
    'deployment' => [
        'no_viable_nodes' => '找不到满足指定自动部署要求的节点。',
        'no_viable_allocations' => '找不到满足自动部署要求的分配。',
    ],
    'api' => [
        'resource_not_found' => '此服务器上不存在请求的资源。',
    ],
    'mount' => [
        'servers_attached' => '挂载必须没有附加到它的服务器才能被删除。',
    ],
    'server' => [
        'marked_as_failed' => '此服务器尚未完成其安装进程，请稍后重试。',
        'file_too_large' => '您尝试打开的文件太大，无法在文件编辑器中查看。',
        'state_conflict' => '此服务器目前处于不支持的状态，请稍后重试。',
        'suspended' => '此服务器当前已被挂起，请求的功能不可用。',
        'maintenance' => '此服务器的节点目前正在维护中，请求的功能不可用。',
        'restoring_backup' => '此服务器目前正在从备份中恢复，请稍后重试。',
        'transferring' => '此服务器目前正在转移到新机器，请稍后重试。',
    ],
];
