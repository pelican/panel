<?php

return [
    'restart_now' => 'Palvelin käynnistyy uudelleen nyt.',
    'close' => 'Sulje',

    'eula' => [
        'heading' => 'Minecraft EULA',
        'description' => 'Painamalla alla olevaa "Hyväksyn"-painiketta vahvistat hyväksyväsi <x-filament::link href="https://minecraft.net/eula" target="_blank">Minecraft EULA -käyttöehdot</x-filament::link>',
        'accept' => 'Hyväksyn',
        'accepted' => 'Minecraft EULA hyväksytty',
        'failed' => 'Minecraft EULAa ei voitu hyväksyä',
    ],

    'gsl_token' => [
        'heading' => 'Virheellinen GSL- tunniste',
        'description' => 'Näyttää siltä, että Gameserverin kirjautumistunnus (GSL token) on virheellinen tai vanhentunut.',
        'submit' => 'Päivitä Gsl- Tunnus',
        'info' => 'Voit joko <x-filament::link href="https://steamcommunity.com/dev/managegameservers" target="_blank">luoda uuden</x-filament::link> ja syöttää sen alapuolelle tai jättää kentän tyhjäksi poistaaksesi sen kokonaan.',
        'updated' => 'GSL Token päivitetty',
        'failed' => 'GSL-tunnusta ei voitu päivittää',
    ],

    'java_version' => [
        'heading' => 'Ei Tuettu Java-versio',
        'description' => 'Tämän palvelimen Java-versio ei ole tuettu, eikä palvelinta voida käynnistää.',
        'submit' => 'Päivitä Docker-näköistiedosto',
        'select_version' => 'Ole hyvä ja valitse tuettu versio alla olevasta luettelosta jatkaaksesi palvelimen käynnistämistä.',
        'docker_image' => 'Docker-näköistiedosto',
        'updated' => 'Docker-näköistiedosto Päivitetty',
        'failed' => 'Docker-näköistiedostoa ei voitu päivittää',
    ],

    'pid_limit' => [
        'heading_admin' => 'Muistin tai prosessin raja saavutettu...',
        'heading_user' => 'Mahdollinen resurssiraja saavutettu...',
        'description_admin' => '<p>Tämä palvelin on saavuttanut maksimiprosessin tai muistin rajan.</p><p class="mt-4">Lisää <code>container_pid_limit</code> siipien asetuksissa, <code>config. ml</code>, saattaa auttaa ratkaisemaan tämän ongelman.</p><p class="mt-4"><b>Huomautus: Siivet tulee käynnistää uudelleen, jotta konfiguraatiotiedoston muutokset tulevat voimaan</b></p>',
        'description_user' => '<p>Tämä palvelin yrittää käyttää enemmän resursseja kuin on annettu. Ota yhteyttä järjestelmänvalvojaan ja anna heille alla oleva virhe.</p><p class="mt-4"><code>pthread_create epäonnistui, mahdollisesti poissa muistista tai prosessin/resurssin rajoista</code></p>',
    ],

    'steam_disk_space' => [
        'heading' => 'Ei saatavilla levytilaa...',
        'description_admin' => '<p>Tällä palvelimella on loppunut käytettävissä oleva levytila eikä se voi suorittaa asennus- tai päivitysprosessia.</p><p class="mt-4">Varmista, että koneessa on tarpeeksi levytilaa kirjoittamalla <code class="rounded py-1 px-2">df -h</code> koneeseen, joka isännöi tätä palvelinta. Poista tiedostoja tai lisää käytettävissä olevaa levytilaa ongelman ratkaisemiseksi.</p>',
        'description_user' => '<p>Tällä palvelimella on loppunut käytettävissä oleva levytila eikä se voi suorittaa asennus- tai päivitysprosessia. Ota yhteyttä ylläpitäjään/ylläpitäjiin ja ilmoita heille levytilaan liittyvistä ongelmista.</p>',
    ],
];
