<?php

return [
    'nav_title' => 'データベースホスト',
    'model_label' => 'データベースホスト',
    'model_label_plural' => 'データベースホスト',
    'table' => [
        'database' => 'データベース',
        'name' => '名前',
        'host' => 'ホスト',
        'port' => 'ポート',
        'name_helper' => '空白の場合は自動でランダムな名前が生成されます',
        'username' => 'ユーザー名',
        'password' => 'パスワード',
        'remote' => '接続元',
        'remote_helper' => '接続を許可する場所です。空白の場合はどこからでも接続可能になります。',
        'max_connections' => '最大接続数',
        'created_at' => '作成日時',
        'connection_string' => 'JDBC 接続文字列',
    ],
    'error' => 'ホストへの接続エラー',
    'host' => 'ホスト',
    'host_help' => 'パネルから新しいデータベースを作成する際に、MySQLホストへ接続するために使用するIPアドレスまたはドメイン名。',
    'port' => 'ポート',
    'port_help' => 'ホストでMySQLが動作しているポート',
    'max_database' => '最大データベース数',
    'max_databases_help' => 'ホストで作成可能なデータベースの最大数です。上限に達した場合、新しいデータベースは作成できません。空白の場合は無制限となります。',
    'display_name' => '表示名',
    'display_name_help' => 'ホストを他と区別するための短い識別子です。1～60文字で指定してください（例：us.nyc.lvl3）',
    'username' => 'ユーザー名',
    'username_help' => 'システム上で新しいユーザーやデータベースを作成するための必要な権限を持つアカウントのユーザー名',
    'password' => 'パスワード',
    'password_help' => 'データベースユーザーのパスワード',
    'linked_nodes' => 'リンクされたノード',
    'linked_nodes_help' => 'この設定は、選択したノードでサーバーにデータベースを追加する際に、このデータベースホストをデフォルトとして使用する場合にのみ適用されます',
    'connection_error' => 'データベースホストへの接続エラー',
    'no_database_hosts' => 'データベースホストが存在しません',
    'no_databases' => 'データベースがありません',
    'no_nodes' => 'ノードが存在しません',
    'nodes' => 'ノード',
    'delete_help' => 'このデータベースホストには既にデータベースが存在します',
    'unlimited' => '無制限',
    'anywhere' => 'どこからでも',

    'rotate' => '変更',
    'rotate_password' => 'パスワードを更新',
    'rotated' => 'パスワードが更新されました',
    'rotate_error' => 'パスワードの更新に失敗しました',
    'databases' => 'データベース',

    'setup' => [
        'preparations' => '準備',
        'database_setup' => 'データベース設定',
        'panel_setup' => 'パネル設定',

        'note' => 'データベースでサポートされているのはMySQL/ MariaDBです',
        'different_server' => 'パネルとデータベースは<i>別々の</i>サーバー上にありますか？',

        'database_user' => 'データベースユーザー',
        'cli_login' => 'Cliで<code>mysql -u root -p</code>を使用してmysqlにアクセスしてください',
        'command_create_user' => 'ユーザー作成コマンド',
        'command_assign_permissions' => '権限割当コマンド',
        'cli_exit' => '<code>exit</code>を使用してmysqlを終了してください',
        'external_access' => '外部のアクセス',
        'allow_external_access' => '
                                    <p>サーバーが接続できるようにするため、このMySQLインスタンスへの外部アクセスを許可する必要があります。</p>
                                    <br>
                                    <p>設定するには <code>my.cnf</code> を開いてください(OSやインストール方法によって場所が異なります。<code>find /etc -iname my.cnf</code> で見つけることができます）。</p>
                                    <br>
                                    <p><code>my.cnf</code> を開き、ファイルの末尾に以下を追加して保存してください:<br>
                                    <code>[mysqld]<br>bind-address=0.0.0.0</code></p>
                                    <br>
                                    <p>MySQL/MariaDBを再起動して変更を適用してください。これによりすべてのインターフェースからの接続が許可されます。ファイアウォールでMySQLポート(デフォルト3306)を開放することを忘れないでください。</p>
                                ',
    ],
];
