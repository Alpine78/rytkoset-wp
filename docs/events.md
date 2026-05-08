# Tapahtumat

Sivuston tapahtumat ylläpidetään WordPressin `Tapahtumat`-sisältötyypillä.

## Tapahtumapäivä

Tapahtuman muokkausnäkymässä on kenttä `Tapahtumapäivä`.

- Päivämäärä tallennetaan post metaan avaimella `_rytkoset_event_date`.
- Tallennusmuoto on `YYYY-MM-DD`, esimerkiksi `2026-08-29`.
- Kenttä kannattaa täyttää sekä tuleville että menneille tapahtumille.
- Jos päivämäärä puuttuu, tapahtuma jää näkyviin, mutta se näytetään arkiston päivämäärättömien tapahtumien osiossa.

## Tapahtuman tiedot

Tapahtuman muokkausnäkymän oikeassa sivupalkissa on lisäksi metabox `Tapahtuman tiedot`.

Kentät ovat:

- `Alkamisaika`: tallennetaan post metaan `_rytkoset_event_start_time`, kiinteässä 24 tunnin muodossa `HH:MM`.
- `Päättymisaika`: tallennetaan post metaan `_rytkoset_event_end_time`, kiinteässä 24 tunnin muodossa `HH:MM`. Kenttä on valinnainen.
- `Paikka`: tallennetaan post metaan `_rytkoset_event_location`, esimerkiksi `Hotelli Rosendahl, Pyynikintie 13, Tampere`.
- `Maksullisuus`: tallennetaan post metaan `_rytkoset_event_fee_type`. Arvot ovat `free`, `paid` tai tyhjä.
- `Hintateksti`: tallennetaan post metaan `_rytkoset_event_price_text`, esimerkiksi `49 € / henkilö`.

Kellonajat syötetään tekstikenttiin kiinteässä 24 tunnin muodossa, esimerkiksi `11:30` ja `18:00`. Virheellistä aikaa ei tallenneta. Tyhjä kenttä poistaa vastaavan metatiedon.

## Näkyminen sivustolla

Yksittäisellä tapahtumasivulla näytetään täytetyt perustiedot tapahtuman otsikon jälkeen:

- päivämäärä
- kellonaika tai aikaväli
- paikka
- maksullisuus ja hintateksti

Tapahtuma-arkistossa `/tapahtumat/` näytetään vähintään päivämäärä, jos se on asetettu. Jos tapahtumalle on asetettu paikka, se näytetään arkistolistauksessa päivämäärän rinnalla.

## Tapahtuma-arkiston järjestys

Tapahtuma-arkisto `/tapahtumat/` jakaa tapahtumat kolmeen osioon:

1. Tulevat tapahtumat
2. Menneet tapahtumat
3. Päivämäärättömät tapahtumat

Tulevat tapahtumat näytetään lähimmästä tulevasta tapahtumasta alkaen. Menneet tapahtumat näytetään uusimmasta vanhimpaan.

## Maksulliset tapahtumat

Tapahtuman maksullisuus ja hintateksti ovat informatiivisia kenttiä. Varsinainen maksaminen hoidetaan edelleen WooCommerce-tuotteella, joka voidaan linkittää tapahtumaan erillisellä `Maksutuote`-kentällä.

Tämä tarkoittaa:

- tapahtuman hintateksti ei muuta WooCommerce-tuotteen hintaa
- maksullisuus ei luo tuotetta automaattisesti
- ilmoittautuminen ja maksaminen kulkevat WooCommerce-tuotesivun ja kassan kautta

## Ensimmäiset tapahtumat

Aseta nykyisille tapahtumille vähintään nämä päivämäärät:

- Rytkösten sukuseuran Etelä-Suomen tapaaminen: `2025-10-07`
- Rytkösten sukukokous Tampereella: `2026-08-29`

Tampereen sukukokoukselle voidaan lisäksi asettaa:

- Alkamisaika: `11:30`
- Päättymisaika: `18:00`
- Paikka: `Hotelli Rosendahl, Pyynikintie 13, Tampere`
- Maksullisuus: `Maksullinen`
- Hintateksti: `49 € / henkilö`

## Rajaukset

Tässä vaiheessa tapahtumalla on yksi päivämäärä, yksi aikaväli, yksi vapaa paikkateksti ja informatiivinen maksullisuustieto.

Toteutus ei sisällä:

- erillistä päättymispäivää
- karttalinkkiä tai karttaupotusta
- toistuvia tapahtumia
- numeerista hintamallia
- automaattista WooCommerce-tuotteen luontia tai muuttamista
