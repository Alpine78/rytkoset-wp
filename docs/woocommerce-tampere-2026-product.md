# WooCommerce: Tampere 2026 osallistumismaksutuote

Tämä dokumentti kuvaa tiketin `#139` tavoitetilan ja toteutusmallin paikallisessa WordPress-ympäristössä.

## Rajaus tässä vaiheessa

- Tuote koskee vain sukukokouksen varsinaista juhlapäivää `29.8.2026`
- Tuote kattaa vain osallistumisen lauantain ohjelmaan
- Perjantain `28.8.2026` buffet-illalliselle ilmoittaudutaan samalla kassapolulla osallistujakohtaisella valinnalla
- Perjantain buffet-illallinen maksetaan paikan päällä, joten siitä ei tehdä omaa maksullista tuotetta
- Majoitus ja varsinainen verkkomaksuintegraatio jäävät myöhempiin tiketteihin

## Tuotemalli

- Tuotteen nimi: `Tampere 2026 osallistumismaksu`
- Suositeltu SKU: `tampere-2026-osallistumismaksu`
- Tuotetyyppi: `Variable product`
- Virtuaalituote: `Kyllä`
- Ladattava tuote: `Ei`
- Variaatiot:
  - `Aikuinen`: `49 €`
  - `Lapsi 3-12 vuotta`: `24,50 €`
- `Sold individually`: `Ei`

## Myyntilogiikka

- Yksi variaation kappale vastaa yhtä osallistujaa
- Samalla tilauksella voidaan ostaa useampi kappale
- Tuotteelle valitaan osallistujatyyppi samalla tavalla kuin vaatteelle valittaisiin koko
- Variaatioiden kappalemäärät kertovat osallistujien lukumäärän
- Maksutapana riittää tässä vaiheessa nykyinen `Tilisiirto`
- Checkoutin osallistujakentät ja ilmoituslogiikka tunnistavat tuotteen parent-tuotteen SKU:lla `tampere-2026-osallistumismaksu` sekä sen variaatioilla
- Perjantain buffet-illallinen kerätään checkoutissa osallistujakohtaisena kyllä/ei-valintana

## Tuotekuvauksen minimitiedot

Tuotekuvauksessa pitää kertoa vähintään:

- tapahtuma: `Rytkösten sukukokous 29.8.2026`
- että osallistumismaksu koskee lauantain ohjelmaa
- että hinta sisältää buffetlounaan ja iltapäiväkahvin
- että aikuisen hinta on `49 €` ja lapsen `24,50 €`
- että ilmoittautumisen määräpäivä on `30.7.2026`
- että ostoskoriin lisätään oikea määrä aikuisia ja lapsia
- että perjantain buffet-illallinen valitaan kassalla ja maksetaan paikan päällä

## Esimerkkikuvaus

`Tampere 2026 osallistumismaksu` on Rytkösten sukukokouksen osallistumismaksu lauantaille `29.8.2026`.

Hinta on `49 € / aikuinen` ja `24,50 € / lapsi 3-12 vuotta`. Osallistumismaksu sisältää buffetlounaan sekä iltapäiväkahvin.

Valitse osallistujatyyppi ja lisää ostoskoriin oikea määrä aikuisia ja lapsia. Perjantain buffet-illallinen valitaan kassalla osallistujakohtaisesti ja maksetaan paikan päällä. Ilmoittautumisen määräpäivä on `30.7.2026`.

## Testaus

- Tuote näkyy WooCommerce-adminissa oikealla nimellä
- Tuote on `Variable product`
- Tuotteella on variaatiot `Aikuinen` ja `Lapsi 3-12 vuotta`
- Aikuisen hinta on `49 €`
- Lapsen hinta on `24,50 €`
- Tuote on `Virtual`
- Tuotetta voi lisätä ostoskoriin useamman kappaleen eri variaatioilla
- Ostoskorin summa muuttuu oikein variaatioiden ja kappalemäärien mukaan
- `Kassa` toimii tuotteen kanssa nykyisellä maksupolulla

## Jätetään seuraaviin tiketteihin

- tapahtuman linkitys maksutuotteeseen (`#138`)
- määräpäivän ja kapasiteetin hallinta (`#141`)
- varsinainen verkkomaksuintegraatio (`#145`)
- perjantain `28.8.2026` buffet-illallisen maksaminen verkossa
