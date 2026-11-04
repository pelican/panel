<?php

return [
    'nav_title' => 'Webhooks',
    'model_label' => 'Webhook',
    'model_label_plural' => 'Webhooks',
    'endpoint' => 'Point de terminaison',
    'description' => 'Description',
    'name' => 'Nom',
    'type' => 'Type',
    'server' => 'Serveur',
    'scope' => [
        'global' => 'Global',
        'server' => 'Serveur',
    ],
    'tabs' => [
        'global' => 'Webhooks globaux',
        'server' => 'Webhooks serveur',
    ],
    'information' => 'Information',
    'payload' => 'Payload',
    'events' => 'Événements',
    'no_webhooks' => 'Aucun webhook',
    'help' => 'Aide',
    'help_text' => 'Vous devez englober les noms de variables dans {{ }}. Par exemple, si vous voulez obtenir le nom depuis l\'api, vous pouvez utiliser {{name}}',
    'test_now' => 'Tester',
    'test_now_help' => 'Cela va lancer l\'événement `created: Server`',
    'table' => [
        'description' => 'Description',
        'endpoint' => 'Point de terminaison',
    ],
    'headers' => 'En-Têtes',
    'regular' => 'Standard',
    'reset_headers' => 'Réinitialiser les en-têtes',
    'unavailable_type' => 'Type indisponible',
    'unavailable_type_option' => ':type (indisponible)',
    'unavailable_type_text' => 'Ce webhook utilise le type ":type", qui est fourni par un plugin qui n\'est pas installé ou activé. Il continue à tirer avec son bloc de données sauvegardé, mais ne peut pas être édité tant que ce plugin n\'est pas à nouveau disponible.',
];
