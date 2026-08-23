<?php

return [
    'restart_now' => 'Le serveur va redémarrer maintenant.',
    'close' => 'Fermer',

    'eula' => [
        'heading' => 'CLUF Minecraft',
        'description' => 'En cliquant sur « J\'accepte » ci-dessous, vous indiquez que vous acceptez le <x-filament::link href="https://minecraft.net/eula" target="_blank">CLUF de Minecraft</x-filament::link>.',
        'accept' => 'J\'accepte',
        'accepted' => 'CLUF Minecraft acceptée',
        'failed' => 'Impossible d\'accepter le CLUF Minecraft',
    ],

    'gsl_token' => [
        'heading' => 'Jeton GSL invalide',
        'description' => 'Il semble que votre jeton de connexion au serveur de jeu (jeton GSL) soit invalide ou a expiré.',
        'submit' => 'Mettre à jour le jeton GSL',
        'info' => 'Vous pouvez soit <x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">générer un nouveau</x-filament::link> et le saisir ci-dessous ou laisser le champ vide pour le supprimer complètement.',
        'updated' => 'Jeton GSL mis à jour',
        'failed' => 'Impossible de mettre à jour le jeton GSL',
    ],

    'java_version' => [
        'heading' => 'Version Java non prise en charge',
        'description' => 'Ce serveur utilise actuellement une version non prise en charge de Java et ne peut pas être démarré.',
        'submit' => 'Mettre à jour l\'image de Docker',
        'select_version' => 'Veuillez sélectionner une version prise en charge dans la liste ci-dessous pour continuer à démarrer le serveur.',
        'docker_image' => 'Image Docker',
        'updated' => 'Image Docker mise à jour',
        'failed' => 'Impossible de mettre à jour l\'image du docker',
    ],

    'pid_limit' => [
        'heading_admin' => 'Mémoire ou limite de processus atteinte ...',
        'heading_user' => 'Limite possible de ressources atteinte...',
        'description_admin' => '<p>Ce serveur a atteint la limite maximale de processus ou de mémoire.</p>
<p class="mt-4">Augmenter <code>container_pid_limit</code> dans la configuration de Wings, <code>config.yml</code>, peut aider à résoudre ce problème.</p>
<p class="mt-4"><b>Remarque : Wings doit être redémarré pour que les modifications du fichier de configuration prennent effet</b></p>',
        'description_user' => '<p>Ce serveur tente d’utiliser plus de ressources que celles qui lui sont allouées. Veuillez contacter l’administrateur et lui transmettre l’erreur ci-dessous.</p>
<p class="mt-4"><code>pthread_create a échoué, probablement en raison d’un manque de mémoire ou de limites de processus/ressources atteintes</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Espace disque insuffisant...',
        'description_admin' => '<p>Ce serveur n’a plus d’espace disque disponible et ne peut pas terminer le processus d’installation ou de mise à jour.</p>
<p class="mt-4">Assurez-vous que la machine dispose de suffisamment d’espace disque en tapant <code class="rounded py-1 px-2">df -h</code> sur la machine hébergeant ce serveur. Supprimez des fichiers ou augmentez l’espace disque disponible pour résoudre le problème.</p>',
        'description_user' => '<p>Ce serveur n’a plus d’espace disque disponible et ne peut pas terminer le processus d’installation ou de mise à jour. Veuillez contacter l’administrateur(s) et l’informer des problèmes d’espace disque.</p>',
    ],
];
