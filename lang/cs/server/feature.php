<?php

return [
    'restart_now' => 'Server se nyní restartuje.',
    'close' => 'Zavřít',

    'eula' => [
        'heading' => 'Minecraft EULA',
        'description' => 'Stisknutím tlačítka "Přijmout" níže vyjadřujete svůj souhlas s <x-filament::link href="https://minecraft.net/eula" target="_blank">Minecraft EULA </x-filament::link>.',
        'accept' => 'Přijmout',
        'accepted' => 'Minecraft EULA přijata',
        'failed' => 'Minecraft EULA se nepodařilo přijmout',
    ],

    'gsl_token' => [
        'heading' => 'Neplatný GSL token',
        'description' => 'Váš Gameserver Login Token (GSL token) je zřejmě neplatný nebo mu vypršela platnost.',
        'submit' => 'Aktualizovat GSL token',
        'info' => 'Můžete buď <x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">vygenerovat nový</x-filament::link> a zadat jej níže nebo ponechte pole prázdné, abyste je zcela odstranili.',
        'updated' => 'GSL token aktualizován',
        'failed' => 'GSL token se nepodařilo aktualizovat',
    ],

    'java_version' => [
        'heading' => 'Nepodporovaná verze Javy',
        'description' => 'Tento server používá nepodporovanou verzi Javy a nelze ho spustit.',
        'submit' => 'Aktualizovat Docker image',
        'select_version' => 'Vyberte prosím ze seznamu níže podporovanou verzi, aby mohl server nastartovat.',
        'docker_image' => 'Docker image',
        'updated' => 'Docker image aktualizován',
        'failed' => 'Docker image se nepodařilo aktualizovat',
    ],

    'pid_limit' => [
        'heading_admin' => 'Dosažen limit paměti nebo procesů...',
        'heading_user' => 'Možná dosažen limit prostředků...',
        'description_admin' => '<p>Tento server dosáhl maximálního limitu procesů nebo paměti.</p><p class="mt-4">Problém může vyřešit zvýšení hodnoty <code>container_pid_limit</code> v konfiguraci Wings, tedy v souboru <code>config.yml</code>.</p><p class="mt-4"><b>Poznámka: Aby se změny konfiguračního souboru projevily, musí být Wings restartován</b></p>',
        'description_user' => '<p>Tento server se pokouší využít více prostředků, než má přiděleno. Kontaktujte prosím administrátora a předejte mu chybu níže.</p><p class="mt-4"><code>pthread_create failed, Possibly out of memory or process/resource limits reached</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Došlo volné místo na disku...',
        'description_admin' => '<p>Tomuto serveru došlo volné místo na disku a nelze dokončit instalaci ani aktualizaci.</p><p class="mt-4">Zkontrolujte, zda má stroj dostatek místa — na počítači, kde server běží, spusťte <code class="rounded py-1 px-2">df -h</code>. Problém vyřešíte smazáním souborů nebo zvětšením dostupného místa.</p>',
        'description_user' => '<p>Tomuto serveru došlo volné místo na disku a nelze dokončit instalaci ani aktualizaci. Kontaktujte prosím administrátory a informujte je o problému s místem na disku.</p>',
    ],
];
