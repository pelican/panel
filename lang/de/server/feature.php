<?php

return [
    'restart_now' => 'Server wird jetzt neu starten.',
    'close' => 'Schließen',

    'eula' => [
        'heading' => 'Minecraft EULA',
        'description' => 'Durch das Drücken von "Ich stimme zu" stimmen Sie der <x-filament::link href="https://minecraft.net/eula" target="_blank">>Minecraft EULA</x-filament::link>zu.',
        'accept' => 'Ich stimme zu',
        'accepted' => 'Minecraft EULA zugestimmt.',
        'failed' => 'Minecraft EULA konnte nicht zugestimmt werden.',
    ],

    'gsl_token' => [
        'heading' => 'Ungültiger GSL Token',
        'description' => 'Scheint, als wäre Ihr Gameserver Anmelde-Token (GSL Token) ungültig oder abgelaufen.',
        'submit' => 'GSL Token aktualisieren',
        'info' => 'Sie können entweder <x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">einen neuen generieren</x-filament::link>und unten eingeben oder dieses Feld leer lassen, um es zu entfernen.',
        'updated' => 'GSL Token aktualisiert',
        'failed' => 'GSL Token konnte nicht aktualisiert werden.',
    ],

    'java_version' => [
        'heading' => 'Nicht unterstützte Java Version',
        'description' => 'Dieser Server läuft im Moment eine nicht unterstützte Version von Java und kann nicht gestartet werden.',
        'submit' => 'Docker Image aktualisieren',
        'select_version' => 'Bitte wählen Sie eine unterstützte Version aus der Liste unten um das Starten des Servers fortzufahren.',
        'docker_image' => 'Docker Image',
        'updated' => 'Docker Image aktualisiert',
        'failed' => 'Docker Image konnte nicht aktualisiert werden',
    ],

    'pid_limit' => [
        'heading_admin' => 'Speicher- oder Prozesslimit erreicht...',
        'heading_user' => 'Mögliches Ressourcenlimit erreicht',
        'description_admin' => '<p>Dieser Server hat das maximale Prozess- oder Speicherlimit erreicht.</p><p class="mt-4">Erhöhung <code>container_pid_limit</code> in der Konfiguration der Wings, <code>config.yml</code>könnte helfen, dieses Problem zu beheben.</p><p class="mt-4"><b>Hinweis: Wings müssen neu gestartet werden, damit die Änderungen der Konfigurationsdatei in Kraft treten</b></p>',
        'description_user' => '<p>Dieser Server versucht, mehr Ressourcen zu nutzen als verfügbar-gestellt. Bitte kontaktieren Sie den Administrator und geben Sie ihm den Fehler unten.</p><p class="mt-4"><code>pthread_create fehlgeschlagen, möglicherweise kein Speicher verfügbar oder Prozess-/Ressourcenlimit erreicht</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Kein Speicherplatz mehr verfügbar...',
        'description_admin' => '<p>Auf diesem Server ist der verfügbare Speicherplatz aufgebraucht, sodass der Installations- oder Aktualisierungsvorgang nicht abgeschlossen werden kann.</p><p class="mt-4">Stellen Sie sicher, dass auf dem Host dieses Servers ausreichend Speicherplatz vorhanden ist, indem Sie dort den Befehl <code class="rounded py-1 px-2">df -h</code> eingeben. Löschen Sie Dateien oder erweitern Sie den verfügbaren Speicherplatz, um das Problem zu beheben.</p>',
        'description_user' => '<p>Dieser Server hat kein Speicherplatz mehr verfügbar und kann den Installations- oder Aktualisierungsprozess nicht vollenden. Bitte kontaktieren Sie den/die Administrator(en) und informieren Sie die über Speicherplatzprobleme.</p>',
    ],
];
