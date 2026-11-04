<?php

return [
    'heading' => 'Witaj w :app!',
    'version' => 'Wersja: :version',
    'advanced' => 'Zaawansowane',
    'server' => 'Serwer',
    'user' => 'Użytkownik',
    'sections' => [
        'intro-developers' => [
            'heading' => 'Informacje dla programistów',
            'content' => 'Dziękujemy za wypróbowanie wersji rozwojowej!',
            'extra_note' => 'Jeśli napotkasz jakieś problemy, zgłoś je na GitHubie.',
            'button_issues' => 'Utwórz zgłoszenie',
            'button_features' => 'Omówienie funkcji',
        ],
        'intro-update-available' => [
            'heading' => 'Dostępna aktualizacja',
            'content' => ':latestVersion jest teraz dostępny! Przeczytaj naszą dokumentację, aby zaktualizować swój panel.',
            'content_container' => ':latestVersion jest teraz dostępny! Pobierz nowy obraz i odtwórz kontener aby zaktualizować swój panel.',
            'button_changelog' => 'Co nowego?',
        ],
        'intro-version-unavailable' => [
            'heading' => 'Sprawdzanie wersji niedostępne',
            'content' => 'Aktualnie używasz :version. Najnowsza wersja nie może zostać pobrana z GitHub, więc nie wiadomo, czy aktualizacja jest dostępna.',
        ],
        'intro-no-update' => [
            'heading' => 'Twój Panel jest aktualny',
            'content' => 'Używasz Pelican w wersji :version. Twój panel jest aktualny!',
        ],
        'intro-first-node' => [
            'heading' => 'Nie wykryto żadnych węzłów',
            'content' => 'Wygląda na to, że nie masz jeszcze skonfigurowanych żadnych węzłów, ale nie martw się, ponieważ wystarczy, że klikniesz przycisk akcji, aby utworzyć swój pierwszy węzeł!',
            'extra_note' => 'Jeśli napotkasz jakieś problemy, zgłoś je na GitHubie.',
            'button_label' => 'Stwórz pierwszy węzeł',
        ],
        'intro-support' => [
            'heading' => 'Wspomóż Pelican',
            'content' => 'Dziękujemy za korzystanie z Pelican, jest to możliwe jedynie dzięki waszemu wsparciu, naszym współtwórcom i pozostałym naszym zwolennikom!',
            'extra_note' => 'Doceniamy wszelkie wsparcie ze strony każdego.',
            'button_translate' => 'Pomóż w tłumaczeniu',
            'button_donate' => 'Darowizna',
        ],
        'intro-help' => [
            'heading' => 'Potrzebujesz pomocy?',
            'content' => 'Najpierw sprawdź dokumentację! Jeśli nadal potrzebujesz pomocy, wejdź na nasz serwer Discord!',
            'button_docs' => 'Przeczytaj dokumentację',
        ],
    ],
];
