<?php

return [
    'title' => 'Usuários',
    'username' => 'Nome de Usuário',
    'email' => 'Email',
    'assign_all' => 'Atribuir tudo',
    'invite_user' => 'Convidar usuário',
    'action' => 'Convidar',
    'remove' => 'Remover usuário',
    'edit' => 'Editar usuário',
    'editing' => 'Editando :user',
    'delete' => 'Deletar usuário',
    'notification_add' => 'Usuário convidado!',
    'notification_edit' => 'Usuário atualizado!',
    'notification_delete' => 'Usuário deletado!',
    'notification_failed' => 'Falha ao convidar usuário!',
    'permissions' => [
        'title' => 'Permissões',

        'activity_title' => 'Atividade',
        'activity_desc' => 'Permissões que controlam o acesso do usuário aos registros de atividade do servidor.',

        'startup_title' => 'Inicialização',
        'startup_desc' => 'Permissões que controlam a capacidade do usuário de visualizar os parâmetros de inicialização deste servidor.',

        'settings_title' => 'Configurações',
        'settings_desc' => 'Permissões que controlam a capacidade do usuário de modificar as configurações deste servidor.',

        'control_title' => 'Controles',
        'control_desc' => 'Permissões que controlam a capacidade do usuário de controlar o estado de energia do servidor ou enviar comandos.',

        'user_title' => 'Usuário',
        'user_desc' => 'Permissões que permitem a um usuário gerenciar outros sub-usuários em um servidor. Eles nunca poderão editar a própria conta ou conceder permissões que não possuam.',

        'file_title' => 'Arquivo',
        'file_desc' => 'Permissões que controlam a capacidade do usuário de modificar o sistema de arquivos deste servidor.',

        'allocation_title' => 'Alocação',
        'allocation_desc' => 'Permissões que controlam a capacidade do usuário de modificar as alocações de portas deste servidor.',

        'database_title' => 'Banco de Dados',
        'database_desc' => 'Permissões que controlam o acesso do usuário ao gerenciamento de banco de dados deste servidor.',

        'backup_title' => 'Backup',
        'backup_desc' => 'Permissões que controlam a capacidade do usuário de gerar e gerenciar backups do servidor.',

        'schedule_title' => 'Agendamento',
        'schedule_desc' => 'Permissões que controlam o acesso do usuário ao gerenciamento de agendamentos deste servidor.',

        'startup_read' => 'Permite o usuário visualizar os parâmetros de inicialização do servidor',
        'startup_update' => 'Permite o usuário editar os parâmetros de inicialização do servidor',
        'startup_docker_image' => 'Permite que o usuário modifique a imagem Docker usada ao executar o servidor.',

        'settings_rename' => 'Permite que o usuário renomeie este servidor.',
        'settings_description' => 'Permite que o usuário troque a descrição desse servidor.',
        'settings_reinstall' => 'Permite que o usuário reinstale o servidor.',
        'settings_change_icon' => 'Permite que o usuário troque a descrição desse servidor.',

        'activity_read' => 'Permite que o usuário visualize os registros de atividades deste servidor.',

        'websocket_connect' => 'Permite que um usuário acesse o websocket para este servidor.',

        'control_console' => 'Permite que o usuário envie dados no console desse servidor.',
        'control_start' => 'Permite que o usuário inicie esse servidor.',
        'control_stop' => 'Permite que o usuário pare esse servidor.',
        'control_restart' => 'Permite que o usuário reinicie esse servidor.',
        'control_kill' => 'Permite que o usuário encerre esse servidor.',

        'user_create' => 'Permite que o usuário crie contas de usuário para o servidor.',
        'user_read' => 'Permite que o usuário veja os usuários associados a esse servidor.',
        'user_update' => 'Permite que o usuário modifique outros usuários associados a esse servidor.',
        'user_delete' => 'Permite que o usuário delete outros usuários associados a esse servidor.',

        'file_create' => 'Permite que o usuário crie arquivos e diretórios.',
        'file_read' => 'Permite que o usuário veja o conteúdo de um diretório, mas não visualize nem baixe os arquivos.',
        'file_read_content' => 'Permite que o usuário veja o conteúdo de um arquivo específico. Isso também permite que o usuário faça o download dos arquivos.',
        'file_update' => 'Permite que o usuário atualize arquivos e pastas associados ao servidor.',
        'file_delete' => 'Permite que o usuário apague arquivos e diretórios.',
        'file_archive' => 'Permite que o usuário crie arquivos compactados e descompacte arquivos existentes.',
        'file_sftp' => 'Permite que o usuário execute as ações de arquivo acima usando um cliente SFTP.',

        'allocation_read' => 'Permite que o usuário veja todas as alocações atualmente atribuídas a este servidor. Usuários com qualquer nível de acesso a este servidor sempre podem ver a alocação primária.',
        'allocation_update' => 'Permite que o usuário altere a alocação primária do servidor e adicione notas a cada alocação.',
        'allocation_delete' => 'Permite que o usuário apague uma alocação do servidor.',
        'allocation_create' => 'Permite que o usuário atribua novas alocações a esse servidor.',

        'database_create' => 'Permite que o usuário crie uma base de dados para o servidor.',
        'database_read' => 'Permite que usuários visualize as bases de dados do servidor',
        'database_update' => 'Permite que o usuário faça modificações em um banco de dados. Se o usuário não tiver a permissão para visualizar a senha, ele não poderá modificar a senha.',
        'database_delete' => 'Permite que o usuário apague um banco de dados.',
        'database_view_password' => 'Permite que o usuário veja a senha de um banco de dados no sistema.',

        'schedule_create' => 'Permite que o usuário crie agendamentos para esse servidor.',
        'schedule_read' => 'Permite que o usuário veja os agendamentos desse servidor.',
        'schedule_update' => 'Permite que o usuário faça modificações em agendamentos já existentes.',
        'schedule_delete' => 'Permite que o usuário delete um agendamento desse servidor.',

        'backup_create' => 'Permite que o usuário crie backups do servidor.',
        'backup_read' => 'Permite que o usuário veja todos os backups existentes desse servidor.',
        'backup_delete' => 'Permite que o usuário apague backups desse servidor.',
        'backup_download' => 'Permite que o usuário faça download dos backups desse servidor. Perigo: isso permite que um usuário acesse todos os arquivos do servidor no backup.',
        'backup_restore' => 'Permite que o usuário restaure um backup desse servidor. Perigo: isso permite que um usuário acesse todos os arquivos do servidor no backup.',

        'mount_title' => 'Montagens',
        'mount_desc' => 'Permissões que controlam a capacidade do usuário de modificar o sistema de arquivos deste servidor.',
        'mount_read' => 'Permite que um usuário veja a página de montagens e veja as montagens disponíveis.',
        'mount_update' => 'Permite que um usuário alterne montagens para o servidor',
    ],
];
