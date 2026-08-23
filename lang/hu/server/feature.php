<?php

return [
    'restart_now' => 'A szerver újraindul...',
    'close' => 'Bezárás',

    'eula' => [
        'heading' => 'Minecraft EULA',
        'description' => 'A "Beleegyezem" gomb megnyomásával elfogadom a <x-filament::link href="https://minecraft.net/eula" target="_blank">Minecraft EULA-t </x-filament::link>.',
        'accept' => 'Beleegyezem',
        'accepted' => 'Minecraft EULA elfogadva',
        'failed' => 'Nem sikerült elfogadni az EULA-t',
    ],

    'gsl_token' => [
        'heading' => 'Rossz GSL token',
        'description' => 'Úgy néz ki, hogy a Gameserver Login Token (GSL Token) tokened rossz vagy lejárt',
        'submit' => 'GSL token frissítése',
        'info' => 'Használj új kulcsot a Steam fejlesztői felületén:
<x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">generálj újat</x-filament::link>, majd írd be, vagy hagyd üresen a mezőt az eltávolításhoz.',
        'updated' => 'GSL token frissítve',
        'failed' => 'Nem tudtuk a GSL tokent frissíteni',
    ],

    'java_version' => [
        'heading' => 'Nem támogatott Java verzió',
        'description' => 'Ez a szerver egy nem támogatott Java verziót használ és emiatt nem tudtuk elindítani.',
        'submit' => 'Docker Image frissítése',
        'select_version' => 'Kérlek válassz egy támogatott verziót az alábbi listából, hogy eltudjuk indítanii a szervert.',
        'docker_image' => 'Docker Image',
        'updated' => 'Docker Image frissítve',
        'failed' => 'Nem tudtuk frissíteni a Docker Image-t',
    ],

    'pid_limit' => [
        'heading_admin' => 'Memória korlát elérve...',
        'heading_user' => 'Elérted az erőforrás korlátot...',
        'description_admin' => '<p>A szerver elérte a maximális folyamat- vagy memóriakorlátot.</p>
<p class="mt-4">Próbáld meg növelni a <code>container_pid_limit</code> értéket a Wings konfigurációs fájlban <code>config.yml</code>.</p>
<p class="mt-4"><b>Fontos: a Wings-t újra kell indítani, hogy a módosítások érvénybe lépjenek.</b></p>',
        'description_user' => '<p>A szerver több erőforrást próbál használni, mint amennyi hozzá van rendelve. Kérd meg az adminisztrátort, és add át neki az alábbi hibát.</p>
<p class="mt-4"><code>pthread_create failed, valószínűleg elfogyott a memória, vagy elérte az erőforrás korlátot</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Nincs elérhető lemezterület.',
        'description_admin' => '<p>A szerver kifogyott az elérhető lemezterületből, ezért nem tudja befejezni a telepítési vagy frissítési folyamatot.</p>
<p class="mt-4">Ellenőrizd a gép szabad tárhelyét a <code class="rounded py-1 px-2">df -h</code> parancs futtatásával a szervergépen. Törölj fájlokat, vagy növeld a rendelkezésre álló lemezterületet a hiba megoldásához.</p>',
        'description_user' => '<p>A szerver kifogyott az elérhető lemezterületből, ezért nem tudja befejezni a telepítési vagy frissítési folyamatot. Vedd fel a kapcsolatot az adminisztrátorral(kal), és jelezd a problémát.</p>',
    ],
];
