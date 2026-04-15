# WooCommerce: Tampere 2026 osallistumismaksutuote

Tämä dokumentti kuvaa Tampere 2026 -osallistumismaksun tuotemallin paikallisessa WordPress-ympäristössä.

## Rajaus tässä vaiheessa

- Tuote koskee vain sukukokouksen varsinaista juhlapäivää `29.8.2026`.
- Perjantain `28.8.2026` iltaohjelmaa ei huomioida tässä vaiheessa.
- Tuote kattaa vain osallistumisen lauantain ohjelmaan.
- Illallinen, majoitus, verkkomaksuintegraatio ja osallistujakohtaiset jatkolaajennukset jätetään omiin tiketteihinsä.

## Tuotemalli

- Tuotteen nimi: `Tampere 2026 osallistumismaksu`
- Suositeltu SKU: `tampere-2026-osallistumismaksu`
- Tuotetyyppi: `Simple product`
- Virtuaalituote: `Kyllä`
- Ladattava tuote: `Ei`
- Hinta: `49 € / henkilö`
- `Sold individually`: `Ei`

## Myyntilogiikka

- Yksi kappale tuotetta vastaa yhtä osallistujaa.
- Samalla tilauksella voidaan ostaa useampi kappale.
- Tuotteen kappalemäärä kertoo osallistujien lukumäärän.
- Maksutapana riittää tässä vaiheessa `Tilisiirto`.
- Checkoutin osallistujakentät ja ilmoituslogiikka tunnistavat tuotteen ensisijaisesti SKU:lla `tampere-2026-osallistumismaksu`.

## Tuotekuvauksen minimitiedot

Tuotekuvauksessa pitää kertoa vähintään:

- tapahtuma: `Rytkösten sukukokous 29.8.2026`
- että osallistumismaksu koskee lauantain ohjelmaa
- että hinta sisältää buffetlounaan ja iltapäiväkahvin
- että ilmoittautumisen määräpäivä on `30.7.2026`
- että ostoskoriin lisätään yhtä monta kappaletta kuin osallistujia
