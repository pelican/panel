<?php

return [
    'heading' => 'Vítejte v :app!',
    'version' => 'Verze: :version',
    'advanced' => 'Pokročilé',
    'server' => 'Server',
    'user' => 'Uživatel',
    'sections' => [
        'intro-developers' => [
            'heading' => 'Inofmace o vývojářích',
            'content' => 'Děkujeme za zkoušení naší vývojářské verze',
            'extra_note' => 'Pokud naleznete bugy nahlaste je přes GitHub',
            'button_issues' => 'Vytvořit problém',
            'button_features' => 'Diskutujte o funkcích',
        ],
        'intro-update-available' => [
            'heading' => 'Aktulizace je dostupná',
            'content' => ':latestVersion je nyní dostupná! Přečtěte si dokumentaci jak aktulizovat váš panel.',
            'content_container' => 'Je dostupná verze :latestVersion! Stáhněte nový image a znovu vytvořte kontejner, čímž panel aktualizujete.',
            'button_changelog' => 'Co je nového?',
        ],
        'intro-version-unavailable' => [
            'heading' => 'Kontrola verze není dostupná',
            'content' => 'Používáte verzi :version. Nejnovější verzi se nepodařilo načíst z GitHubu, takže není známo, zda je k dispozici aktualizace.',
        ],
        'intro-no-update' => [
            'heading' => 'Máte nejnovější verzy panelu',
            'content' => 'Právě používáte verzy :version. Váš panel je aktuální',
        ],
        'intro-first-node' => [
            'heading' => 'Nebyly nalezeny žádné uzly',
            'content' => 'Vypadá to že nemáš žádné Nodes nastavené, ale kliknutím na tlačítko akce můžete vytvořit váš první!',
            'extra_note' => 'Pokud naleznete bugy nahlaste je na GitHub',
            'button_label' => 'Vytvořit první uzel',
        ],
        'intro-support' => [
            'heading' => 'Podpořte Pelican!',
            'content' => 'Děkujeme za používání Pelican, toto je možné vytvářet jen díky vaší podpoře, přespěvatelům a dalším podporovatelům!',
            'extra_note' => 'Vážíme si jaké koliv podpory od kohokoliv.',
            'button_translate' => 'Pomocte Přeložit',
            'button_donate' => 'Darovat přímo',
        ],
        'intro-help' => [
            'heading' => 'Potřebujete pomoc?',
            'content' => 'Nejdříve se podívejte na dokumentaci! Pokud stále potřebujete pomoc, přejděte na náš Discord server!',
            'button_docs' => 'Přečtěte si dokumentaci',
        ],
    ],
];
