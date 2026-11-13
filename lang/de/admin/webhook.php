<?php

return [
    'nav_title' => 'Webhooks',
    'model_label' => 'Webhook',
    'model_label_plural' => 'Webhooks',
    'endpoint' => 'Endpunkt',
    'description' => 'Beschreibung',
    'name' => 'Name',
    'type' => 'Typ',
    'server' => 'Server',
    'scope' => [
        'global' => 'Allgemein',
        'server' => 'Server',
    ],
    'tabs' => [
        'global' => 'Globale Webhooks',
        'server' => 'Server Webhooks',
    ],
    'information' => 'Information',
    'payload' => 'Auslastung',
    'events' => 'Ereignisse',
    'no_webhooks' => 'Keine Webhooks',
    'help' => 'Hilfe',
    'help_text' => 'Du musst den Variablennamen zwischen {{ }} einschließen. Wenn Du z. B. den Namen aus der API abrufen möchten, kannst Du {{name}} verwenden.',
    'test_now' => 'Jetzt testen',
    'test_now_help' => 'Dadurch wird ein `created: Server`-Ereignis ausgelöst',
    'table' => [
        'description' => 'Beschreibung',
        'endpoint' => 'Endpunkt',
    ],
    'headers' => 'Kopfzeilen',
    'regular' => 'Regelmäßig',
    'reset_headers' => 'Kopfzeilen zurücksetzen',
    'unavailable_type' => 'Typ ist nicht verfügbar',
    'unavailable_type_option' => ':type (nicht verfügbar)',
    'unavailable_type_text' => 'Dieser Webhook verwendet den Typ ":type", der von einem Plugin bereitgestellt wird, das nicht installiert oder aktiviert ist. Es schießt immer wieder mit seiner gespeicherten Nutzlast ab, kann aber nicht bearbeitet werden, bis dieses Plugin wieder verfügbar ist.',
];
