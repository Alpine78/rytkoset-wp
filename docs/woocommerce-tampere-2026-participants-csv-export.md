# WooCommerce: Tampere 2026 osallistujalistan CSV-vienti

Tämä dokumentti kuvaa tiketin `#149` toteutuksen.

## Tavoite

Tampere 2026 -osallistujat voidaan viedä administa CSV-tiedostoksi ilman tilausten käsin läpikäyntiä.

## Mistä vienti tehdään

CSV-vienti tehdään samalta admin-sivulta kuin osallistujalista:

- `WooCommerce > Tampere 2026 osallistujat`

Sivulla on painike:

- `Vie CSV`

Jos listaan on asetettu status-suodatin, sama rajaus käytetään myös CSV-viennissä.

## CSV:n sisältö

Tiedostossa on yksi rivi per osallistuja.

Mukana ovat:

- osallistujan nimi
- ruokarajoitteet / allergiat
- yhteyshenkilön nimi
- yhteyshenkilön sähköposti
- yhteyshenkilön puhelin
- tilausnumero
- tilauksen status

## Tekninen toteutus

- CSV rakennetaan samasta osallistujarividatasta kuin admin-lista
- vienti tehdään WordPressin `admin-post.php`-reitillä
- vienti on rajattu käyttäjille, joilla on oikeus `manage_woocommerce`
- vienti on nonce-suojattu
- tiedosto kirjoitetaan UTF-8 BOM -muodossa
- erottimena käytetään puolipistettä, jotta tiedosto aukeaa käytännössä suoraan myös suomalaisessa Excel-ympäristössä

## Rajaus tässä vaiheessa

- ei Excel-xlsx-vientiä
- ei PDF-vientiä
- ei automaattista sähköpostiliitettä
- ei yleistä vientityökalua kaikille tapahtumille
