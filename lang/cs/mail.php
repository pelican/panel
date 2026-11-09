<?php

return [
    'greeting' => 'Dobrý den :name!',

    'account_created' => [
        'body' => 'Obdrželi jste tento e-mail, protože vám byl vytvořen účet v aplikaci :app.',
        'username' => 'Uživatelské jméno: :username',
        'email' => 'E-mail: :email',
        'action' => 'Nastavte svůj účet',
    ],

    'added_to_server' => [
        'body' => 'Byl jsi přidán jako poduživatel pro následující server, díky čemuž budeš moci ovládat server.',
        'server_name' => 'Název serveru: :name',
        'action' => 'Zobrazit server',
    ],

    'removed_from_server' => [
        'body' => 'Byl jste odebrán jako dílčí uživatel pro následující server.',
        'server_name' => 'Název serveru: :name',
        'action' => 'Zobrazit panel',
    ],

    'server_installed' => [
        'body' => 'Váš server dokončil instalaci a je nyní připraven k použití.',
        'server_name' => 'Název serveru: :name',
        'action' => 'Přihlásit se a začít používat',
    ],

    'backup_completed' => [
        'body_success' => 'Záloha byla úspěšně vytvořena.',
        'body_failed' => 'Vytvoření zálohy selhalo.',
        'backup_name' => 'Název zálohy: :name',
        'server_name' => 'Název serveru: :name',
        'action' => 'Zobrazit zálohy',
    ],

    'mail_tested' => [
        'subject' => 'Testovací zpráva z panelu',
        'body' => 'Tohle je test poštovního systému panelu. Vše je připraveno, můžete začít!',
    ],
];
