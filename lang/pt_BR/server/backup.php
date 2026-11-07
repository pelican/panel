<?php

return [
    'title' => 'Backups',
    'empty' => 'Nenhum backup',
    'size' => 'Tamanho',
    'created_at' => 'Criado em',
    'status' => 'Status',
    'is_locked' => 'Status de bloqueio',
    'backup_status' => [
        'in_progress' => 'Em andamento',
        'successful' => 'Bem-sucedido',
        'failed' => 'Falhou',
    ],
    'notifications' => [
        'action' => 'Configurações de Notificação',
        'toggle_manual' => 'Notificações de Backup Manual',
        'helper_manual' => 'Receber uma notificação e um e-mail quando um backup criado manualmente para este servidor completar ou falhar.',
        'toggle_scheduled' => 'Notificações de Backup Agendado',
        'helper_scheduled' => 'Receba uma notificação e um e-mail quando o backup agendado deste servidor for concluído ou falhar.',
        'saved' => 'Configurações de notificação atualizadas',
    ],
    'actions' => [
        'create' => [
            'title' => 'Criar Backup',
            'limit' => 'Limite de Backup Atingido',
            'created' => ':name criado',
            'notification_success' => 'Backup Criado com Sucesso',
            'notification_fail' => 'Criação de Backup Falhou',
            'name' => 'Nome',
            'ignored' => 'Arquivos e Diretórios Ignorados',
            'locked' => 'Bloqueado?',
            'lock_helper' => 'Impede que esse backup seja excluído até ser explicitamente desbloqueado.',
        ],
        'lock' => [
            'lock' => 'Bloquear',
            'unlock' => 'Desbloquear',
        ],
        'download' => 'Fazer Download',
        'rename' => [
            'title' => 'Renomear',
            'new_name' => 'Nome do backup',
            'notification_success' => 'Backup renomeado com sucesso',
        ],
        'restore' => [
            'title' => 'Restaurar',
            'helper' => 'Seu servidor será desligado. Durante esse processo, você não poderá ligá-lo ou desligá-lo, acessar o gerenciador de arquivos ou criar backups adicionais.',
            'delete_all' => 'Excluir todos os arquivos antes de restaurar o backup?',
            'notification_started' => 'Restaurando Backup',
            'notification_success' => 'Backup Restaurado com Sucesso',
            'notification_fail' => 'Falha ao Restaurar Backup',
            'notification_fail_body_1' => 'Este servidor não está em um estado que permita a restauração de um backup.',
            'notification_fail_body_2' => 'Este backup não pode restaurado no momento: não concluído ou falhou.',
        ],
        'delete' => [
            'title' => 'Excluir Backup',
            'description' => 'Você deseja excluir :backup?',
            'notification_success' => 'Backup Excluído',
            'notification_fail' => 'Não foi possível excluir backup',
            'notification_fail_body' => 'Falha na conexão com o servidor. Por favor, tente novamente.',
        ],
    ],
];
