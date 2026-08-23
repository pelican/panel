<?php

return [
    'restart_now' => 'サーバーが今すぐ再起動されます。',
    'close' => '閉じる',

    'eula' => [
        'heading' => 'Minecraft EULA',
        'description' => '下の「同意する」を押すことで、<x-filament::link href="https://minecraft.net/eula" target="_blank">Minecraft EULA</x-filament::link> への同意を示します。',
        'accept' => '同意する',
        'accepted' => 'Minecraft EULA に同意しました',
        'failed' => 'Minecraft EULA に同意できませんでした',
    ],

    'gsl_token' => [
        'heading' => '無効な GSL トークン',
        'description' => 'Gameserver Login Token（GSL トークン）が無効であるか、期限切れのようです。',
        'submit' => 'GSL トークンを更新',
        'info' => '<x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">新しいトークンを生成</x-filament::link>して以下に入力するか、完全に削除する場合はフィールドを空白のままにしてください。',
        'updated' => 'GSL トークンを更新しました',
        'failed' => 'GSL トークンを更新できませんでした',
    ],

    'java_version' => [
        'heading' => 'サポートされていない Java バージョン',
        'description' => 'このサーバーはサポートされていない Java バージョンで動作しているため、起動できません。',
        'submit' => 'Docker イメージを更新',
        'select_version' => '下のリストからサポートされているバージョンを選択して、サーバーの起動を続けてください。',
        'docker_image' => 'Docker イメージ',
        'updated' => 'Docker イメージを更新しました',
        'failed' => 'Docker イメージを更新できませんでした',
    ],

    'pid_limit' => [
        'heading_admin' => 'メモリまたはプロセス制限に達しました...',
        'heading_user' => 'リソース制限に達した可能性があります...',
        'description_admin' => '<p>このサーバーはプロセスまたはメモリの最大制限に達しました。</p><p class="mt-4">Wings の設定ファイル <code>config.yml</code> 内の <code>container_pid_limit</code> を増やすと、この問題が解決される可能性があります。</p><p class="mt-4"><b>注意: 設定ファイルへの変更を反映させるには Wings を再起動する必要があります</b></p>',
        'description_user' => '<p>このサーバーは割り当てられているよりも多くのリソースを使用しようとしています。管理者に連絡し、以下のエラーを伝えてください。</p><p class="mt-4"><code>pthread_create failed, Possibly out of memory or process/resource limits reached</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'ディスク容量が不足しています...',
        'description_admin' => '<p>このサーバーはディスク容量が不足しており、インストールまたは更新プロセスを完了できません。</p><p class="mt-4">このサーバーをホストしているマシンで <code class="rounded py-1 px-2">df -h</code> を実行して空き容量を確認してください。ファイルを削除するかディスク容量を増やして問題を解決してください。</p>',
        'description_user' => '<p>このサーバーはディスク容量が不足しており、インストールまたは更新プロセスを完了できません。管理者に連絡してディスク容量の問題を報告してください。</p>',
    ],
];
