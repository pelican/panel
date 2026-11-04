<?php

return [
    'restart_now' => 'O servidor reiniciará agora.',
    'close' => 'Fechar',

    'eula' => [
        'heading' => 'EULA Minecraft',
        'description' => 'Ao pressionar "Aceitar" abaixo, você indica seu acordo com o EULA <x-filament::link href="https://minecraft.net/eula" target="_blank">Minecraft </x-filament::link>.',
        'accept' => 'Eu Aceito',
        'accepted' => 'EULA do Minecraft aceito',
        'failed' => 'Não foi possível aceitar Minecraft EULA',
    ],

    'gsl_token' => [
        'heading' => 'Token GSL inválido',
        'description' => 'Parece que seu Token de Login de Gameserver (Token GSM) é inválido ou expirou.',
        'submit' => 'Atualizar Token GSL',
        'info' => 'Você pode <x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">gerar uma nova</x-filament::link> e inseri-la abaixo ou deixar o campo em branco para removê-la completamente.',
        'updated' => 'Token GSL atualizado',
        'failed' => 'Não foi possível atualizar o Token GSL',
    ],

    'java_version' => [
        'heading' => 'Versão Java não suportada',
        'description' => 'Este servidor está executando uma versão do Java sem suporte e não pode ser iniciado.',
        'submit' => 'Atualizar imagem do Docker',
        'select_version' => 'Por favor, selecione uma versão suportada na lista abaixo para continuar a iniciar o servidor.',
        'docker_image' => 'Imagem Docker',
        'updated' => 'Imagem Docker atualizada',
        'failed' => 'Não foi possível atualizar a imagem docker',
    ],

    'pid_limit' => [
        'heading_admin' => 'Limite de memória ou processo atingido...',
        'heading_user' => 'Possível limite de recursos atingido...',
        'description_admin' => '<p>Este servidor atingiu o limite máximo de processos ou memória.</p><p class="mt-4">Aumentar <code>container_pid_limit</code> na configuração do Wings, <code>config.yml</code>, pode ajudar a resolver este problema.</p><p class="mt-4"><b>Observação: o Wings precisa ser reiniciado para que as alterações no arquivo de configuração tenham efeito</b></p>',
        'description_user' => '<p>Este servidor está tentando utilizar mais recursos do que os alocados. Entre em contato com o administrador e informe o erro abaixo.</p><p class="mt-4"><code>Falha ao criar pthread. Possivelmente sem memória ou os limites de processos/recursos foram atingidos.</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Sem espaço disponível no disco...',
        'description_admin' => '<p>Este servidor ficou sem espaço em disco disponível e não é possível concluir a instalação ou atualização.</p><p class="mt-4">Certifique-se de que a máquina tenha espaço em disco suficiente digitando <code class="rounded py-1 px-2">df</code> na máquina que hospeda este servidor. Exclua arquivos ou aumente o espaço disponível em disco para resolver o problema.</p>',
        'description_user' => '<p>Este servidor ficou sem espaço disponível em disco e não pode concluir o processo de instalação ou atualização. Entre em contato com os administradores e informe-os sobre os problemas de espaço em disco.</p>',
    ],
];
