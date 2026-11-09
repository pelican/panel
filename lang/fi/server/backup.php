<?php

return [
    'title' => 'Varmuuskopiot',
    'empty' => 'Ei Varmuuskopioita',
    'size' => 'Koko',
    'created_at' => 'Luotu',
    'status' => 'Tila',
    'is_locked' => 'Lukituksen tila',
    'backup_status' => [
        'in_progress' => 'Tekeillä',
        'successful' => 'Onnistui',
        'failed' => 'Epäonnistui',
    ],
    'notifications' => [
        'action' => 'Ilmoitusasetukset',
        'toggle_manual' => 'Manuaalisen varmuuskopioinnin ilmoitukset',
        'helper_manual' => 'Vastaanota ilmoitus ja sähköposti kun manuaalisesti luotu varmuuskopio tälle palvelimelle valmistuu tai epäonnistuu.',
        'toggle_scheduled' => 'Ajastetun varmuuskopioinnin ilmoitukset',
        'helper_scheduled' => 'Saat ilmoituksen ja sähköpostin, kun tämän palvelimen ajastettu varmuuskopiointi valmistuu tai epäonnistuu.',
        'saved' => 'Ilmoitusasetukset päivitetty',
    ],
    'actions' => [
        'create' => [
            'title' => 'Luo varmuuskopio',
            'limit' => 'Varmuuskopiointiraja saavutettu',
            'created' => ':name luotu',
            'notification_success' => 'Varmuuskopio luotu onnistuneesti',
            'notification_fail' => 'Varmuuskopioinnin luonti epäonnistui',
            'name' => 'Nimi',
            'ignored' => 'Ohitetut Tiedostot & Hakemistot',
            'locked' => 'Lukittu?',
            'lock_helper' => 'Estää tämän varmuuskopion poistamisen, kunnes lukitus poistetaan erikseen.',
        ],
        'lock' => [
            'lock' => 'Lukitse',
            'unlock' => 'Avaa',
        ],
        'download' => 'Lataa',
        'rename' => [
            'title' => 'Uudelleennimeä',
            'new_name' => 'Varmuuskopion nimi',
            'notification_success' => 'Varmuuskopion Uudelleennimeäminen Onnistui',
        ],
        'restore' => [
            'title' => 'Palauta',
            'helper' => 'Palvelimesi sammutetaan. Et voi hallita sen virtatilaa, käyttää tiedostonhallintaa tai luoda uusia varmuuskopioita ennen kuin tämä prosessi on valmis.',
            'delete_all' => 'Poista kaikki tiedostot ennen varmuuskopioin palauttamista?',
            'notification_started' => 'Palautetaan varmuuskopiota',
            'notification_success' => 'Varmuuskopio Palautettu Onnistuneesti',
            'notification_fail' => 'Varmuuskopion Palautus Epäonnistui',
            'notification_fail_body_1' => 'Tämä palvelin ei ole tällä hetkellä tilassa, joka salliii varmuuskopion palauttamisen.',
            'notification_fail_body_2' => 'Varmuuskopiota ei voida palauttaa tällä hetkellä: ei suoritettu tai epäonnistui.',
        ],
        'delete' => [
            'title' => 'Poista varmuuskopio',
            'description' => 'Haluatko poistaa varmuuskopion :backup?',
            'notification_success' => 'Varmuuskopio poistettu',
            'notification_fail' => 'Varmuuskopiota ei voitu poistaa',
            'notification_fail_body' => 'Yhteys nodeen epäonnistui. Yritä uudelleen.',
        ],
    ],
];
