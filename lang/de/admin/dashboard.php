<?php

return [
    'heading' => 'Willkommen bei :app!',
    'version' => 'Version: :version',
    'advanced' => 'Erweitert',
    'server' => 'Server',
    'user' => 'Benutzer',
    'sections' => [
        'intro-developers' => [
            'heading' => 'Informationen für Entwickler',
            'content' => 'Vielen Dank für das Ausprobieren der Entwicklungsversion!',
            'extra_note' => 'Wenn Sie auf Probleme stoßen, melden Sie diese bitte auf GitHub.',
            'button_issues' => 'Issue erstellen',
            'button_features' => 'Features diskutieren',
        ],
        'intro-update-available' => [
            'heading' => 'Update verfügbar',
            'content' => ':latestVersion ist jetzt verfügbar! Lesen Sie unsere Dokumentation, um Ihr Panel zu aktualisieren.',
            'content_container' => ':latestVersion ist jetzt verfügbar! Ziehen Sie das neueste Abbild und erstellen Sie den Container neu, für ein Panel update.',
            'button_changelog' => 'Was ist neu?',
        ],
        'intro-version-unavailable' => [
            'heading' => 'Versionsprüfung nicht verfügbar',
            'content' => 'Sie verwenden derzeit :version. Die neueste Version konnte nicht von GitHub abgerufen werden. Es ist also unbekannt, ob ein Update verfügbar ist.',
        ],
        'intro-no-update' => [
            'heading' => 'Ihr Panel ist aktuell',
            'content' => 'Sie verwenden derzeit :version. Ihr Panel ist aktuell!',
        ],
        'intro-first-node' => [
            'heading' => 'Keine Nodes gefunden',
            'content' => 'Es sieht so aus, als hätten Sie noch keine Nodes eingerichtet, aber keine Sorge, klicken Sie auf die Schaltfläche, um Ihre erste Node zu erstellen!',
            'extra_note' => 'Wenn Sie auf Probleme stoßen, melden Sie diese bitte auf GitHub.',
            'button_label' => 'Erste Node erstellen',
        ],
        'intro-support' => [
            'heading' => 'Pelican unterstützen',
            'content' => 'Vielen Dank für die Verwendung von Pelican, dies konnte nur durch die Unterstützung von Ihnen, unseren Mitwirkenden und den Rest unserer Unterstützer erreicht werden!',
            'extra_note' => 'Wir freuen uns über jede Unterstützung.',
            'button_translate' => 'Beim Übersetzen helfen',
            'button_donate' => 'Direkt spenden',
        ],
        'intro-help' => [
            'heading' => 'Benötigen Sie Hilfe?',
            'content' => 'Schauen Sie sich zuerst die Dokumentation an! Wenn Sie immer noch Hilfe brauchen, besuchen Sie unseren Discord Server!',
            'button_docs' => 'Dokumentation lesen',
        ],
    ],
];
