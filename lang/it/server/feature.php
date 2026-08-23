<?php

return [
    'restart_now' => 'Il server verrà riavviato ora.',
    'close' => 'Chiudi',

    'eula' => [
        'heading' => 'EULA di Minecraft',
        'description' => 'Premendo "Accetto" qui sotto indichi di accettare la <x-filament::link href="https://minecraft.net/eula" target="_blank">EULA di Minecraft </x-filament::link>.',
        'accept' => 'Accetto',
        'accepted' => 'EULA di Minecraft accettata',
        'failed' => 'Impossibile accettare la EULA di Minecraft',
    ],

    'gsl_token' => [
        'heading' => 'Token GSL non valido',
        'description' => 'Sembra che il tuo Gameserver Login Token (token GSL) non sia valido o sia scaduto.',
        'submit' => 'Aggiorna token GSL',
        'info' => 'Puoi <x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">generarne uno nuovo</x-filament::link> e inserirlo qui sotto oppure lasciare il campo vuoto per rimuoverlo completamente.',
        'updated' => 'Token GSL aggiornato',
        'failed' => 'Impossibile aggiornare il token GSL',
    ],

    'java_version' => [
        'heading' => 'Versione Java non supportata',
        'description' => 'Questo server sta eseguendo una versione di Java non supportata e non può essere avviato.',
        'submit' => 'Aggiorna immagine Docker',
        'select_version' => 'Seleziona una versione supportata dall\'elenco qui sotto per continuare ad avviare il server.',
        'docker_image' => 'Immagine Docker',
        'updated' => 'Immagine Docker aggiornata',
        'failed' => 'Impossibile aggiornare l\'immagine Docker',
    ],

    'pid_limit' => [
        'heading_admin' => 'Limite memoria o processi raggiunto...',
        'heading_user' => 'Possibile limite risorse raggiunto...',
        'description_admin' => '<p>Questo server ha raggiunto il limite massimo di processi o memoria.</p><p class="mt-4">Aumentare <code>container_pid_limit</code> nella configurazione di Wings, <code>config.yml</code>, potrebbe aiutare a risolvere questo problema.</p><p class="mt-4"><b>Nota: Wings deve essere riavviato affinché le modifiche al file di configurazione abbiano effetto</b></p>',
        'description_user' => '<p>Questo server sta tentando di usare più risorse di quelle allocate. Contatta l\'amministratore e fornisci l\'errore qui sotto.</p><p class="mt-4"><code>pthread_create failed, Possibly out of memory or process/resource limits reached</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Spazio su disco disponibile esaurito...',
        'description_admin' => '<p>Questo server ha esaurito lo spazio su disco disponibile e non può completare il processo di installazione o aggiornamento.</p><p class="mt-4">Assicurati che la macchina disponga di spazio su disco sufficiente digitando <code class="rounded py-1 px-2">df -h</code> sulla macchina che ospita questo server. Elimina file o aumenta lo spazio disponibile su disco per risolvere il problema.</p>',
        'description_user' => '<p>Questo server ha esaurito lo spazio su disco disponibile e non può completare il processo di installazione o aggiornamento. Contatta gli amministratori e informali dei problemi di spazio su disco.</p>',
    ],
];
