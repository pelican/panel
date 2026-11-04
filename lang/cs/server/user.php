<?php

return [
    'title' => 'Uživatelé',
    'username' => 'Uživatelské jméno',
    'email' => 'E-mail',
    'assign_all' => 'Přiřadit vše',
    'invite_user' => 'Pozvat uživatele',
    'action' => 'Pozvat',
    'remove' => 'Odebrat uživatele',
    'edit' => 'Upravit uživatele',
    'editing' => 'Editace :user',
    'delete' => 'Smazat uživatele',
    'notification_add' => 'Uživatel pozván!',
    'notification_edit' => 'Uživatel aktualizován!',
    'notification_delete' => 'Uživatel smazán!',
    'notification_failed' => 'Uživatele se nepodařilo pozvat',
    'permissions' => [
        'title' => 'Oprávnění',

        'activity_title' => 'Aktivita',
        'activity_desc' => 'Oprávnění, která ovládají přístup uživatele k protokolům aktivity serveru.',

        'startup_title' => 'Spouštění',
        'startup_desc' => 'Oprávnění, která ovládají schopnost uživatele zobrazit spouštěcí parametry tohoto serveru.',

        'settings_title' => 'Nastavení',
        'settings_desc' => 'Oprávnění, která ovládají schopnost uživatele změnit nastavení tohoto serveru.',

        'control_title' => 'Ovládání',
        'control_desc' => 'Oprávnění, která ovládají schopnost uživatele ovládat stav napájení serveru nebo posílat příkazy.',

        'user_title' => 'Uživatel',
        'user_desc' => 'Oprávnění, která uživateli umožňují spravovat ostatní poduživatele na serveru. Nikdy nebudou moci upravovat svůj vlastní účet nebo udělovat oprávnění, která sami nemají.',

        'file_title' => 'Soubor',
        'file_desc' => 'Oprávnění, která řídí uživatelskou schopnost měnit souborový systém pro tento server.',

        'allocation_title' => 'Alokace',
        'allocation_desc' => 'Oprávnění, která řídí uživatelskou schopnost měnit alokace portů pro tento server.',

        'database_title' => 'Databáze',
        'database_desc' => 'Oprávnění, která ovládají přístup uživatele k databázi pro tento server.',

        'backup_title' => 'Záloha',
        'backup_desc' => 'Oprávnění, která ovládají schopnost uživatele generovat a spravovat zálohy serveru.',

        'schedule_title' => 'Plán',
        'schedule_desc' => 'Oprávnění, která řídí přístup uživatele k správě harmonogramu pro tento server.',

        'startup_read' => 'Umožňuje uživateli zobrazit spouštěcí proměnné pro server.',
        'startup_update' => 'Umožňuje uživateli změnit spouštěcí proměnné pro server.',
        'startup_docker_image' => 'Umožňuje uživateli upravovat obrázek Dockeru použitý při spuštění serveru.',

        'settings_rename' => 'Umožňuje uživateli přejmenovat tento server a změnit jeho popis.',
        'settings_description' => 'Umožňuje uživateli změnit popis tohoto serveru.',
        'settings_reinstall' => 'Umožňuje uživateli spustit přeinstalaci tohoto serveru.',
        'settings_change_icon' => 'Umožňuje uživateli změnit ikonu tohoto serveru.',

        'activity_read' => 'Umožňuje uživateli zobrazit záznamy aktivit na serveru.',

        'websocket_connect' => 'Umožňuje uživateli přístup k websocketu pro tento server.',

        'control_console' => 'Umožňuje uživateli posílat data do konzole serveru.',
        'control_start' => 'Umožňuje uživateli spustit instanci serveru.',
        'control_stop' => 'Umožní uživateli zastavit instanci serveru.',
        'control_restart' => 'Umožní uživateli restartovat instanci serveru.',
        'control_kill' => 'Umožňuje uživateli ukončit instanci serveru.',

        'user_create' => 'Umožňuje uživateli vytvářet nové uživatelské účty pro server.',
        'user_read' => 'Umožňuje uživateli oprávnění k zobrazení uživatelů přidružených k tomuto serveru.',
        'user_update' => 'Umožňuje uživateli upravovat ostatní uživatele spojené s tímto serverem.',
        'user_delete' => 'Umožňuje uživateli odstranit další uživatele přidružené k tomuto serveru.',

        'file_create' => 'Umožňuje uživatelům oprávnění vytvářet nové soubory a adresáře.',
        'file_read' => 'Umožňuje uživateli zobrazit obsah adresáře, ale nezobrazovat obsah nebo stahovat soubory.',
        'file_read_content' => 'Umožňuje uživateli zobrazit obsah daného souboru. To také umožní uživateli stahovat soubory.',
        'file_update' => 'Umožňuje uživateli aktualizovat soubory a složky spojené se serverem.',
        'file_delete' => 'Umožňuje uživateli odstranit soubory a adresáře.',
        'file_archive' => 'Umožňuje uživateli vytvářet soubory a dekomprimovat existující archivy.',
        'file_sftp' => 'Umožňuje uživateli provést výše uvedené akce souborů pomocí SFTP klienta.',

        'allocation_read' => 'Umožňuje uživateli zobrazit všechny alokace aktuálně přiřazené k tomuto serveru. Uživatelé s jakoukoli úrovní přístupu k tomuto serveru mohou vždy zobrazit primární alokaci.',
        'allocation_update' => 'Umožňuje uživateli změnit primární alokaci serveru a připojit poznámky ke každé alokaci.',
        'allocation_delete' => 'Umožňuje uživateli odstranit alokaci ze serveru.',
        'allocation_create' => 'Umožňuje uživateli přiřadit k serveru další alokace.',

        'database_create' => 'Umožňuje uživateli oprávnění vytvořit novou databázi pro server.',
        'database_read' => 'Umožňuje uživateli oprávnění k zobrazení serverových databází.',
        'database_update' => 'Umožňuje uživateli oprávnění provádět změny v databázi. Pokud uživatel nemá také oprávnění "Zobrazit heslo", nebude moci heslo upravit.',
        'database_delete' => 'Umožňuje uživateli oprávnění odstranit instanci databáze.',
        'database_view_password' => 'Umožňuje uživateli oprávnění zobrazit heslo k databázi v systému.',

        'schedule_create' => 'Umožňuje uživateli vytvořit pro server nový plán.',
        'schedule_read' => 'Umožňuje uživateli oprávnění zobrazovat plány serveru.',
        'schedule_update' => 'Umožňuje uživateli oprávnění provádět změny v rozvrhu stávajícího serveru.',
        'schedule_delete' => 'Umožňuje uživateli smazat plán pro server.',

        'backup_create' => 'Umožňuje uživateli vytvářet pro tento server nové zálohy.',
        'backup_read' => 'Umožňuje uživateli zobrazit všechny existující zálohy.',
        'backup_delete' => 'Umožňuje uživateli odebrat zálohy ze systému.',
        'backup_download' => 'Umožňuje uživateli stáhnout zálohu serveru. Nebezpečí: umožňuje uživateli přístup ke všem souborům pro server v záloze.',
        'backup_restore' => 'Umožňuje uživateli obnovit zálohu serveru. Nebezpečí: umožní uživateli odstranit všechny soubory serveru.',

        'mount_title' => 'Připojení',
        'mount_desc' => 'Oprávnění, která řídí, co může uživatel dělat s připojeními tohoto serveru.',
        'mount_read' => 'Umožňuje uživateli otevřít stránku s připojeními a vidět dostupná připojení.',
        'mount_update' => 'Umožňuje uživateli zapínat a vypínat připojení serveru.',
    ],
];
