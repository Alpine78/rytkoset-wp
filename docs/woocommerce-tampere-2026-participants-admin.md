# WooCommerce: Tampere 2026 osallistujalista adminissa

Tämä dokumentti kuvaa tiketin `#148` toteutuksen.

## Tavoite

Järjestelytoimikunta näkee kaikki Tampere 2026 -ilmoittautumiset yhdestä admin-näkymästä ilman, että jokainen WooCommerce-tilaus täytyy avata erikseen.

## Mitä näkymä näyttää

WooCommerceen lisätään admin-sivu:

- `WooCommerce > Tampere 2026 osallistujat`

Lista näyttää osallistujat riveinä, ei pelkkinä tilauksina.

Jokaiselta riviltä näkyy:

- osallistujan nimi
- ruokarajoitteet / allergiat
- yhteyshenkilön nimi
- yhteyshenkilön sähköposti
- yhteyshenkilön puhelin
- tilausnumero linkillä tilaukseen
- tilauksen status

## Suodatus

Näkymässä voi suodattaa rivejä tilauksen statuksen perusteella.

MVP-vaiheessa tämä riittää käytännön järjestelytyöhön:

- nähdään aktiiviset ilmoittautumiset
- nähdään perutut tai epäonnistuneet tilaukset tarvittaessa erikseen

## Tekninen toteutus

- näkymä käyttää nykyistä Tampere 2026 -tuotteen tunnistuslogiikkaa
- näkymä käyttää tilaukselle jo tallennettuja osallistujakenttiä
- osallistujat puretaan tilauksilta riveiksi admin-näkymää varten
- ratkaisu ei luo uusia tietokantatauluja
- ratkaisu ei vielä tee CSV-vientiä

## Rajaus tässä vaiheessa

- ei CSV- tai Excel-vientiä
- ei massatoimintoja
- ei yleistä monen tapahtuman osallistujaraporttia
- ei erillistä julkista osallistujanäkymää

CSV-vienti tehdään erillisessä tiketissä `#149`.
