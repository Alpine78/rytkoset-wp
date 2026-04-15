# WooCommerce: Tampere 2026 osallistumismaksutuote

Tämä dokumentti kuvaa tiketin `#139` tavoitetilan ja toteutusmallin paikallisessa WordPress-ympäristössä.

## Rajaus tässä vaiheessa

- Tuote koskee vain sukukokouksen varsinaista juhlapäivää `29.8.2026`
- Perjantain `28.8.2026` iltaohjelmaa ei huomioida tässä vaiheessa
- Tuote kattaa vain osallistumisen lauantain ohjelmaan
- Illallinen, majoitus, verkkomaksuintegraatio ja osallistujakohtaiset checkout-kentät jäävät myöhempiin tiketteihin

## Tuotemalli

- Tuotteen nimi: `Tampere 2026 osallistumismaksu`
- Suositeltu SKU: `tampere-2026-osallistumismaksu`
- Tuotetyyppi: `Simple product`
- Virtuaalituote: `Kyllä`
- Ladattava tuote: `Ei`
- Hinta: `49 € / henkilö`
- `Sold individually`: `Ei`

## Myyntilogiikka

- Yksi kappale tuotetta vastaa yhtä osallistujaa
- Samalla tilauksella voidaan ostaa useampi kappale
- Tuotteen kappalemäärä kertoo osallistujien lukumäärän
- Maksutapana riittää tässä vaiheessa nykyinen `Tilisiirto`
- Checkoutin osallistujakentät tunnistavat tuotteen ensisijaisesti SKU:lla `tampere-2026-osallistumismaksu`

## Tuotekuvauksen minimitiedot

Tuotekuvauksessa pitää kertoa vähintään:

- tapahtuma: `Rytkösten sukukokous 29.8.2026`
- että osallistumismaksu koskee lauantain ohjelmaa
- että hinta sisältää buffetlounaan ja iltapäiväkahvin
- että ilmoittautumisen määräpäivä on `30.7.2026`
- että ostoskoriin lisätään yhtä monta kappaletta kuin osallistujia

## Esimerkkikuvaus

`Tampere 2026 osallistumismaksu` on Rytkösten sukukokouksen osallistumismaksu lauantaille `29.8.2026`.

Hinta on `49 € / henkilö` ja sisältää buffetlounaan sekä iltapäiväkahvin.

Lisää ostoskoriin yhtä monta kappaletta kuin osallistujia. Ilmoittautumisen määräpäivä on `30.7.2026`.

## Testaus

- Tuote näkyy WooCommerce-adminissa oikealla nimellä
- Tuotteen hinta on `49 €`
- Tuote on `Simple product`
- Tuote on `Virtual`
- Tuotetta voi lisätä ostoskoriin useamman kappaleen
- Ostoskorin summa muuttuu oikein kappalemäärän mukaan
- `Kassa` toimii tuotteen kanssa nykyisellä maksupolulla

## Jätetään seuraaviin tiketteihin

- osallistujakohtaiset tiedot kassalla (`#140`)
- tapahtuman linkitys maksutuotteeseen (`#138`)
- määräpäivän ja kapasiteetin hallinta (`#141`)
- varsinainen verkkomaksuintegraatio (`#145`)
- perjantain `28.8.2026` iltaohjelman mahdollinen myyntipolku
