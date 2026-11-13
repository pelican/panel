<?php

return [
    'heading' => 'Bienvenue sur :app !',
    'version' => 'Version: :version',
    'advanced' => 'Avancé',
    'server' => 'Serveur',
    'user' => 'Utilisateur',
    'sections' => [
        'intro-developers' => [
            'heading' => 'Informations pour les développeurs',
            'content' => 'Merci d\'avoir essayé la version de développement !',
            'extra_note' => 'Si vous rencontrez des problèmes, veuillez les signaler sur GitHub.',
            'button_issues' => 'Signaler un problème',
            'button_features' => 'Discuter des fonctionnalités',
        ],
        'intro-update-available' => [
            'heading' => 'Une mise à jour est disponible',
            'content' => ':latestVersion est maintenant disponible ! Lisez notre documentation pour mettre à jour votre Panneau.',
            'content_container' => ':latestVersion est maintenant disponible ! Télécharger la nouvelle image et recréé le conteneur pour mettre à jour votre panneau.',
            'button_changelog' => 'Quoi de neuf ?',
        ],
        'intro-version-unavailable' => [
            'heading' => 'Vérification de la version indisponible',
            'content' => 'Vous utilisez actuellement :version. La dernière version n\'a pas pu être récupérée depuis GitHub, donc il est impossible de savoir si une mise à jour est disponible.',
        ],
        'intro-no-update' => [
            'heading' => 'Votre panneau est à jour',
            'content' => 'Vous utilisez la version :version de Pelican. Votre panel est à jour !',
        ],
        'intro-first-node' => [
            'heading' => 'Aucun nœud détecté',
            'content' => "Il semble que vous n'ayez pas encore de nœuds configurés, mais pas d'inquiétude, vous pouvez cliquer sur le bouton d'action pour créer votre premier nœud!",
            'extra_note' => 'Si vous rencontrez des problèmes, merci de les signaler sur GitHub.',
            'button_label' => 'Créer un premier nœud',
        ],
        'intro-support' => [
            'heading' => 'Soutenir Pelican',
            'content' => 'Merci d\'utiliser Pelican, cela n\'aurait pas été possible sans votre soutien, celui de nos contributeurs, et de tous nos autres partisans !',
            'extra_note' => 'Nous apprécions tout soutien, quel qu\'il soit, de la part de tout le monde.',
            'button_translate' => 'Aider à traduire',
            'button_donate' => 'Faire un don',
        ],
        'intro-help' => [
            'heading' => 'Besoin d’aide ?',
            'content' => 'Jetez d\'abord un œil à la documentation ! Si vous avez toujours besoin d\'aide, rejoignez notre serveur Discord !',
            'button_docs' => 'Lire la documentation',
        ],
    ],
];
