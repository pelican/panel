<?php

return [
    'title' => 'Planifications',
    'new' => 'Nouvelle planification',
    'edit' => 'Modifier la planification',
    'save' => 'Enregistrer la planification',
    'delete' => 'Supprimer la planification',
    'import' => 'Importer une planification',
    'export' => 'Exporter la planification',
    'name' => 'Nom',
    'cron' => 'Cron',
    'status' => 'Statut',
    'schedule_status' => [
        'new' => 'Nouveau',
        'inactive' => 'Inactif',
        'processing' => 'Traitement en cours',
        'active' => 'Actif',
    ],
    'no_tasks' => 'Aucune tâche',
    'run_now' => 'Exécuter maintenant',
    'online_only' => 'Uniquement lorsque connecté',
    'last_run' => 'Dernière exécution',
    'next_run' => 'Prochaine exécution',
    'never' => 'Jamais',
    'cancel' => 'Annuler',

    'only_online' => 'Seulement lorsque le serveur est en ligne ?',
    'only_online_hint' => 'Exécute ce planning uniquement lorsque le serveur est en état d\'exécution.',
    'enabled' => 'Activer la planification ?',
    'enabled_hint' => 'Ce calendrier sera exécuté automatiquement si activé.',

    'cron_body' => 'N\'oubliez pas que les entrées cron ci-dessous utilisent toujours UTC.',
    'cron_timezone' => 'Prochaine exécution dans votre fuseau horaire (:timezone): <b> :next_run </b>',

    'invalid' => 'Invalide',

    'time' => [
        'minute' => 'Minute',
        'hour' => 'Heure',
        'day' => 'Jour',
        'week' => 'Semaine',
        'month' => 'Mois',
        'day_of_month' => 'Jour du mois',
        'day_of_week' => 'Jour de la semaine',

        'hourly' => 'Chaque heure',
        'daily' => 'Quotidiennement',
        'weekly_mon' => 'Hebdomadaire (lundi)',
        'weekly_sun' => 'Hebdomadaire (dimanche)',
        'monthly' => 'Mensuel',
        'every_min' => 'Toutes les x minutes',
        'every_hour' => 'Toutes les x heures',
        'every_day' => 'Tous les x jours',
        'every_week' => 'Toutes les x semaines',
        'every_month' => 'Tous les x mois',
        'every_day_of_week' => 'Tous les x jours de la semaine',

        'every' => 'Tous les',
        'minutes' => 'Minutes',
        'hours' => 'Heures',
        'days' => 'Jours',
        'months' => 'Mois',

        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ],

    'tasks' => [
        'title' => 'Tâches',
        'create' => 'Créer une tâche',
        'limit' => 'Limite de tâche atteinte',
        'action' => 'Action',
        'payload' => 'Charge utile',
        'no_payload' => 'Aucune charge utile',
        'time_offset' => 'Décalage horaire',
        'first_task' => 'Première tâche',
        'seconds' => 'Secondes',
        'continue_on_failure' => 'Continuer en cas d\'échec',

        'actions' => [
            'title' => 'Action',
            'power' => [
                'title' => 'Envoyer une action d\'alimentation',
                'action' => 'Action d\'alimentation',
                'start' => 'Démarrer',
                'stop' => 'Arrêter',
                'restart' => 'Redémarrer',
                'kill' => 'Tuer',
            ],
            'command' => [
                'title' => 'Envoyer une commande',
                'command' => 'Commande',
            ],
            'backup' => [
                'title' => 'Créer une sauvegarde',
                'files_to_ignore' => 'Fichiers à ignorer',
            ],
            'delete_files' => [
                'title' => 'Supprimer les fichiers',
                'files_to_delete' => 'Fichiers à supprimer',
            ],
        ],
    ],

    'notification_invalid_cron' => 'Les données cron fournies ne sont pas évaluées à une expression valide',

    'import_action' => [
        'file' => 'Fichier',
        'url' => 'URL',
        'schedule_help' => 'Ceci doit être le fichier .json brut ( schedule-daily-restart.json )',
        'url_help' => 'Les URL doivent pointer directement vers le fichier .json brut',
        'add_url' => 'Nouvelle URL',
        'import_failed' => 'Échec de l\'importation',
        'import_success' => 'Importation réussie',
    ],
];
