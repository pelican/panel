<?php

return [
    'nav_title' => 'Webové háčky',
    'model_label' => 'Webový háček',
    'model_label_plural' => 'Webové háčky',
    'endpoint' => 'Koncový bod',
    'description' => 'Popis:',
    'name' => 'Název',
    'type' => 'Typ',
    'server' => 'Server',
    'scope' => [
        'global' => 'Globální',
        'server' => 'Server',
    ],
    'tabs' => [
        'global' => 'Globální webové háčky',
        'server' => 'Webové háčky serveru',
    ],
    'information' => 'Informace',
    'payload' => 'Payload',
    'events' => 'Akce',
    'no_webhooks' => 'Žádné webové háčky',
    'help' => 'Nápověda',
    'help_text' => 'Musíte zalomit název proměnné mezi {{ }} například pokud chcete získat jméno z api, můžete použít {{name}}.',
    'test_now' => 'Otestovat',
    'test_now_help' => 'Odešle zkušební událost `created: Server`',
    'table' => [
        'description' => 'Popis',
        'endpoint' => 'Koncový bod',
    ],
    'headers' => 'Hlavičky',
    'regular' => 'Běžný',
    'reset_headers' => 'Obnovit hlavičky',
    'unavailable_type' => 'Nedostupný typ',
    'unavailable_type_option' => ':type (nedostupný)',
    'unavailable_type_text' => 'Tento webový háček používá typ ":type", který poskytuje plugin, jenž není nainstalovaný ani zapnutý. Dál se spouští s uloženým payloadem, ale upravit ho nelze, dokud nebude plugin znovu dostupný.',
];
