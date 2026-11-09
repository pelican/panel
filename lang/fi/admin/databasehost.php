<?php

return [
    'nav_title' => 'Tietokantaisännät',
    'model_label' => 'Tietokantaisäntä',
    'model_label_plural' => 'Tietokantaisännät',
    'table' => [
        'database' => 'Tietokanta',
        'name' => 'Nimi',
        'host' => 'Palvelin',
        'port' => 'Portti',
        'name_helper' => 'Jättämällä tämän tyhjäksi luodaan satunnainen nimi automaattisesti',
        'username' => 'Käyttäjänimi',
        'password' => 'Salasana',
        'remote' => 'Yhteydet lähteestä',
        'remote_helper' => 'Yhteyksien sallittu lähde. Tyhjä = yhteydet sallitaan kaikkialta.',
        'max_connections' => 'Yhteyksien enimmäismäärä',
        'created_at' => 'Luotu ajankohtana',
        'connection_string' => 'JDBC-yhteysmerkkijono',
    ],
    'error' => 'Virhe yhdistettäessä palvelimeen',
    'host' => 'Palvelin',
    'host_help' => 'IP-osoite tai verkkotunnus, jota tulee käyttää yhdistettäessä tähän MySQL-isäntään tämän hallintapaneelin kautta uusien tietokantojen luomiseksi.',
    'port' => 'Portti',
    'port_help' => 'MySQL:n käyttämä portti tällä palvelimella.',
    'max_database' => 'Suurin sallittu tietokantojen määrä',
    'max_databases_help' => 'Suurin määrä tietokantoja, jotka voidaan luoda tällä isännällä. Kun raja on saavutettu, uusia tietokantoja ei voi enää luoda. Tyhjä tarkoittaa rajatonta määrää.',
    'display_name' => 'Näyttönimi',
    'display_name_help' => 'IP-osoite tai verkkotunnus, joka näytetään loppukäyttäjälle.',
    'username' => 'Käyttäjänimi',
    'username_help' => 'Käyttäjätunnus tilille, jolla on riittävät oikeudet luoda uusia käyttäjiä ja tietokantoja järjestelmään.',
    'password' => 'Salasana',
    'password_help' => 'Tietokantakäyttäjän salasana.',
    'linked_nodes' => 'Linkitetyt solmut',
    'linked_nodes_help' => 'Tämä asetus käyttää oletuksena tätä tietokantapalvelinta, kun valitulle solmulle lisätään tietokanta.',
    'connection_error' => 'Virhe yhdistettäessä tietokantapalvelimeen',
    'no_database_hosts' => 'Ei tietokantapalvelimia',
    'no_databases' => 'Ei Tietokantoja',
    'no_nodes' => 'Solmuja ei ole',
    'nodes' => 'Nodet',
    'delete_help' => 'Tietokantapalvelimella on tietokantoja',
    'unlimited' => 'Rajaton',
    'anywhere' => 'Missä tahansa',

    'rotate' => 'Vaihda',
    'rotate_password' => 'Vaihda salasana',
    'rotated' => 'Salasana vaihdettu',
    'rotate_error' => 'Salasanan vaihtaminen epäonnistui',
    'databases' => 'Tietokannat',

    'setup' => [
        'preparations' => 'Valmistelut',
        'database_setup' => 'Tietokannan määritys',
        'panel_setup' => 'Paneelin asetukset',

        'note' => 'Tällä hetkellä tietokantapalvelimissa tuetaan vain MySQL/MariaDB-tietokantoja!',
        'different_server' => 'Paneeli ja tietokanta <i>eivät</i> ole samalla palvelimella.',

        'database_user' => 'Tietokannan käyttäjä',
        'cli_login' => 'Käytä komentoa <code>mysql -u root -p</code> päästäksesi MySQL CLI:hin.',
        'command_create_user' => 'Komento käyttäjän luomiseen',
        'command_assign_permissions' => 'Komento käyttöoikeuksien määrittämiseen',
        'cli_exit' => 'Poistuaksesi MySQL CLI:stä käytä komentoa <code>exit</code>.',
        'external_access' => 'Ulkoinen Käyttöoikeus',
        'allow_external_access' => '
Todennäköisesti sinun täytyy sallia ulkoinen pääsy tähän MySQL-instanssiin, jotta palvelimet voivat yhdistää siihen.

Avaa <code>my.cnf</code> (sijainti riippuu käyttöjärjestelmästäsi ja asennustavasta). Sen löytämiseen voit käyttää komentoa:
<code>find /etc -iname my.cnf</code>

Lisää tiedoston loppuun seuraava rivi ja tallenna:
<code>[mysqld]<br>bind-address=0.0.0.0</code>

Käynnistä MySQL/MariaDB uudelleen, jotta muutokset tulevat voimaan. Tämä ohittaa MySQL:n oletusasetuksen, joka sallii yhteydet vain localhostista. Nyt yhteydet onnistuvat kaikilta verkkoliitännöiltä. Muista myös sallia MySQL-portti (oletus 3306) palomuurissa.                                ',
    ],
];
