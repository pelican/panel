<?php

return [
    'nav_title' => 'Hosts do Banco de Dados',
    'model_label' => 'Host do Banco de Dados',
    'model_label_plural' => 'Hosts do Banco de Dados',
    'table' => [
        'database' => 'Banco de Dados',
        'name' => 'Nome',
        'host' => 'Host',
        'port' => 'Porta',
        'name_helper' => 'Deixar isso em branco irá gerar automaticamente um nome aleatório',
        'username' => 'Nome de Usuário',
        'password' => 'Senha',
        'remote' => 'Conexões de',
        'remote_helper' => 'Para onde as conexões devem ser permitidas. Deixe em branco para permitir conexões de qualquer lugar.',
        'max_connections' => 'Máximo de Conexões',
        'created_at' => 'Criado em',
        'connection_string' => 'String de Conexão JDBC',
    ],
    'error' => 'Erro ao conectar-se ao host',
    'host' => 'Host',
    'host_help' => 'O endereço IP ou nome de domínio que deve ser usado quando tentar conectar a este host MySQL deste Painel para criar novas bases de dados.',
    'port' => 'Porta',
    'port_help' => 'A porta na qual o MySQL está sendo executado neste host.',
    'max_database' => 'Máximo de Bancos de Dados',
    'max_databases_help' => 'O número máximo de bancos de dados que podem ser criados neste host. Se o limite for atingido, não será possível criar novos bancos de dados neste host. Deixar em branco significa ilimitado.',
    'display_name' => 'Nome de Exibição',
    'display_name_help' => 'O endereço IP ou nome de domínio que deve ser mostrado ao usuário final.',
    'username' => 'Nome de Usuário',
    'username_help' => 'O nome de usuário de uma conta que possui permissões suficientes para criar novos usuários e bancos de dados no sistema.',
    'password' => 'Senha',
    'password_help' => 'Senha para o usuário do banco de dados.',
    'linked_nodes' => 'Nodes vinculados',
    'linked_nodes_help' => 'Esta configuração é apenas o padrão para esta máquina do banco de dados ao adicionar um banco de dados a um servidor no Node selecionado.',
    'connection_error' => 'Erro ao conectar-se host do banco de dados',
    'no_database_hosts' => 'Nenhum host de Banco de Dados',
    'no_databases' => 'Nenhum banco de dados',
    'no_nodes' => 'Sem Nodes',
    'nodes' => 'Nodes',
    'delete_help' => 'Host do Banco de Dados possui Bancos de Dados',
    'unlimited' => 'Ilimitado',
    'anywhere' => 'Qualquer',

    'rotate' => 'Gerar',
    'rotate_password' => 'Gerar Senha',
    'rotated' => 'Senha Gerada',
    'rotate_error' => 'Falha na Geração da Senha',
    'databases' => 'Bancos de Dados',

    'setup' => [
        'preparations' => 'Preparações',
        'database_setup' => 'Configuração do Banco de Dados',
        'panel_setup' => 'Configuração do Painel',

        'note' => 'Atualmente, apenas bancos de dados MySQL/ MariaDB são suportados para servidores de banco de dados!',
        'different_server' => 'O painel e o banco de dados <i>não são</i> no mesmo servidor?',

        'database_user' => 'Usuário do Banco de Dados',
        'cli_login' => 'Use <code>mysql -u root -p</code> para acessar o mysql cli.',
        'command_create_user' => 'Comando para criar o usuário',
        'command_assign_permissions' => 'Comando para atribuir permissões',
        'cli_exit' => 'Para sair do mysql cli execute <code>exit</code>',
        'external_access' => 'Acesso Externo',
        'allow_external_access' => '
                                    <p>Provavelmente você vai precisar liberar o acesso externo a essa instância do MySQL para permitir que servidores se conectem a ela.</p>
                                    <br>
                                    <p>Para fazer isso, abra o arquivo <code>my.cnf</code>, que pode estar em locais diferentes dependendo do seu sistema operacional e de como o MySQL foi instalado. Você pode usar o comando <code>find /etc -iname my.cnf</code> para localizá-lo.</p>
                                    <br>
                                    <p>Abra o arquivo <code>my.cnf</code>, adicione o texto abaixo no final do arquivo e salve:<br>
                                    <code>[mysqld]<br>bind-address=0.0.0.0</code></p>
                                    <br>
                                    <p>Reinicie o MySQL/MariaDB para aplicar essas mudanças. Isso vai sobrescrever a configuração padrão do MySQL, que por padrão aceita conexões apenas do localhost. Atualizar essa configuração permitirá conexões em todas as interfaces, ou seja, conexões externas. Certifique-se de liberar a porta do MySQL (padrão 3306) no seu firewall.</p>
                                ',
    ],
];
