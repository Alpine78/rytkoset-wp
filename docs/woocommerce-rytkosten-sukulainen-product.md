# WooCommerce: Rytkösten sukulainen nro 9 -ennakkotilaustuote

Tämä dokumentti kuvaa #142-tiketin tuotemallin painetulle `Rytkösten sukulainen` -lehdelle.

## Rajaus

Tässä vaiheessa lehti toteutetaan WooCommercen normaalina fyysisenä tuotteena ja pidetään luonnoksena, kunnes julkaisu voidaan hyväksyä.

Toteutus ei sisällä:

- kestotilausta
- automaattista uusintalaskutusta
- erillistä lehtitilausjärjestelmää
- varastosaldon automaatiota
- teemakoodin muutoksia

Vanhan nro 8 tilaussivun sisältöä voi käyttää referenssinä, mutta sitä ei kopioida sellaisenaan uuden tuotteen lopulliseksi sisällöksi.

## Paikallinen tuote

Paikalliseen WooCommerceen on luotu luonnostilainen tuote:

- nimi: `Rytkösten sukulainen nro 9 – ennakkotilaus`
- SKU: `RYTKOSTEN-SUKULAINEN-9-ENNAKKO`
- tyyppi: `Simple product`
- hinta: `15,00 €`
- status: `Draft`
- kategoria: `Sukulehdet`
- ei `Virtual`
- ei `Downloadable`
- varastonhallinta pois päältä MVP-vaiheessa

Tuote on paikallista WooCommerce-sisältöä. Sitä ei tallenneta repoon.

## Tuotekuvaus

Tuotekuvauksen pitää kertoa selkeästi, että kyse on painetun lehden ennakkotilauksesta.

Minimisisältö:

- kyseessä on painettu `Rytkösten sukulainen nro 9`
- tuote on ennakkotilaus
- toimitus tapahtuu painatuksen jälkeen
- asiakas voi valita postituksen tai noudon tapahtumasta / sovitusti
- lopullinen sisältö, hinta ja julkaisuajankohta vahvistetaan ennen julkaisua

## Toimitusmalli

Tuote käyttää fyysisten tuotteiden MVP-toimitusmallia:

- `Postitus`, hinta `5,90 €`
- `Nouto tapahtumasta / sovitusti`, hinta `0 €`

Toimitusmalli on dokumentoitu tarkemmin tiedostossa `docs/woocommerce-physical-products.md`.

## Julkaisun tarkistuslista

Ennen kuin tuote muutetaan luonnoksesta julkaistuksi:

- hallitus tai sovittu vastuuhenkilö vahvistaa hinnan
- painatusaikataulu ja arvioitu toimitusaika ovat tiedossa
- tuotekuvaus viimeistellään asiakkaalle ymmärrettäväksi
- toimitus- ja noutoteksti tarkistetaan
- mahdollinen tuotekuva lisätään
- postikulu `5,90 €` vahvistetaan oikealle painotuotteelle sopivaksi
- tuotteen status muutetaan `Draft` -> `Published` vasta hyväksynnän jälkeen

## Testaus

Paikallinen smoke-testi voidaan tehdä näin:

1. Muuta tuote väliaikaisesti julkaistuksi.
2. Lisää tuote ostoskoriin.
3. Siirry kassalle.
4. Varmista, että kassalla näkyy toimitusosoite.
5. Valitse `Postitus` ja varmista, että loppusummaan lisätään `5,90 €`.
6. Valitse `Nouto tapahtumasta / sovitusti` ja varmista, ettei toimituskulua lisätä.
7. Varmista, että `Tilisiirto` toimii fallback-maksutapana.
8. Palauta tuotteen status testin jälkeen takaisin luonnokseksi.

## Jatko

Mahdolliset myöhemmät tarkennukset:

- tuotteen lopullinen kansikuva
- varastosaldo, jos painosmäärä on rajallinen
- tarkempi toimitusviesti tilausvahvistukseen
- mahdollinen kampanjateksti Tampere 2026 -sukujuhlan yhteyteen
