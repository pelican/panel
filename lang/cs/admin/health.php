<?php

return [
    'title' => 'Zdraví',
    'results_refreshed' => 'Výsledky kontroly stavu byly aktualizovány',
    'checked' => 'Kontrolované výsledky od :time',
    'refresh' => 'Obnovit',
    'results' => [
        'cache' => [
            'label' => 'Mezipaměť',
            'ok' => 'Ok',
            'failed_retrieve' => 'Nelze nastavit nebo načíst hodnotu mezipaměti aplikace.',
            'failed' => 'Došlo k výjimce v mezipaměti aplikace: :error',
        ],
        'database' => [
            'label' => 'Databáze',
            'ok' => 'Ok',
            'failed' => 'Nelze se připojit k databázi: :error',
        ],
        'debugmode' => [
            'label' => 'Režim ladění',
            'ok' => 'Režim ladění je zakázán',
            'failed' => 'Režim ladění byl očekáván :expected, ale ve skutečnosti byl :actual',
        ],
        'environment' => [
            'label' => 'Prostředí',
            'ok' => 'Ok, nastavte na :actual',
            'failed' => 'Prostředí je nastaveno na :actual, očekáváno :expected',
        ],
        'nodeversions' => [
            'label' => 'Verze uzlu',
            'ok' => 'Uzly jsou aktuální',
            'failed' => ':zastaralý/:all uzly jsou zastaralé',
            'no_nodes_created' => 'Nebyly vytvořeny žádné uzly',
            'no_nodes' => 'Žádné uzly',
            'all_up_to_date' => 'Všechny aktuální',
            'outdated' => ':zastaralý/:all zastaralý',
        ],
        'panelversion' => [
            'label' => 'Verze panelu',
            'ok' => 'Máte nejnovější verzy panelu',
            'failed' => 'Nainstalovaná verze je :currentVersion ale nejnovější je :latestversion',
            'up_to_date' => 'Aktuální',
            'outdated' => 'Neaktuální',
            'unknown' => 'Neznámé',
            'unavailable' => 'Nejnovější verzi se nepodařilo načíst z GitHubu',
        ],
        'schedule' => [
            'label' => 'Rozvrh',
            'ok' => 'Ok',
            'failed_last_ran' => 'Poslední běh plánu byl před více než :time minutami',
            'failed_not_ran' => 'Plán se ještě nespustil.',
        ],
        'useddiskspace' => [
            'label' => 'Místo na disku',
        ],
        'queue' => [
            'label' => 'Fronta',
        ],
    ],
    'checks' => [
        'successful' => 'Úspěšné',
        'failed' => 'Selhání',
    ],
];
