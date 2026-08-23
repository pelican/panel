<?php

return [
    'nav_title' => 'Extensions',
    'model_label' => 'Extension',
    'model_label_plural' => 'Extensions',

    'name' => 'Nom',
    'update_available' => 'Une mise à jour de cette extension est disponible',
    'author' => 'Auteur',
    'version' => 'Version',
    'category' => 'Catégorie',
    'status' => 'État',
    'visit_website' => 'Visiter le site',
    'settings' => 'Paramètres',
    'install' => 'Installer',
    'uninstall' => 'Désinstaller',
    'update' => 'Mettre à jour',
    'enable' => 'Activer',
    'disable' => 'Désactiver',
    'import_from_file' => 'Importer depuis un fichier',
    'import_from_url' => 'Importer depuis une URL',
    'file' => 'Fichier',
    'no_plugins' => 'Aucune extension',
    'all' => 'Tout',
    'change_load_order' => 'Modifier l’ordre de chargement',
    'apply_load_order' => 'Appliquer l’ordre de chargement',

    'enable_theme_modal' => [
        'heading' => 'Thème déjà activé',
        'description' => 'Vous avez déjà un thème activé. Activer plusieurs thèmes peut provoquer des bugs visuels. Voulez-vous continuer ?',
    ],

    'status_enum' => [
        'not_installed' => 'Non installé',
        'disabled' => 'Désactivé',
        'enabled' => 'Activé',
        'errored' => 'Erreur',
        'incompatible' => 'Incompatible',
    ],

    'category_enum' => [
        'plugin' => 'Extension',
        'theme' => 'Thème',
        'language' => 'Pack de langue',
    ],

    'notifications' => [
        'goto_plugins' => 'Aller aux Plugins',
        'background_info' => 'Ce processus peut prendre quelques secondes. Vous serez notifié une fois terminé.',

        'install_started' => 'L\'installation du plugin a commencé en arrière-plan',
        'installed' => 'Extension installée',
        'install_error' => 'Impossible d’installer l’extension',

        'uninstall_started' => 'Désinstallation du plugin démarrée en arrière-plan',
        'uninstalled' => 'Extension désinstallée',
        'uninstall_error' => 'Impossible de désinstaller l’extension',

        'update_started' => 'La mise à jour du plugin a commencé en arrière-plan',
        'updated' => 'Extension mise à jour',
        'update_error' => 'Impossible de mettre à jour l’extension',

        'enabled' => 'Extension activée',
        'disabled' => 'Extension désactivée',
        'deleted' => 'Extension supprimée',

        'imported' => 'Extension importée',
        'import_exists' => 'Une extension avec cet id existe déjà',
        'import_failed' => 'Impossible d’importer l’extension',
    ],
];
