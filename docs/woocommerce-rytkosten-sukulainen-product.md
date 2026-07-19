# WooCommerce: Rytkösten sukulainen nro 9

Tämä dokumentti kuvaa #142-tiketin tuotemallin painetulle `Rytkösten sukulainen` -lehdelle.

## Rajaus

Lehti toteutetaan WooCommercen normaalina fyysisenä tuotteena. `Rytkösten sukulainen nro 9` on julkaistu ja myynnissä verkkokaupassa.

Toteutus ei sisällä:

- kestotilausta
- automaattista uusintalaskutusta
- erillistä lehtitilausjärjestelmää
- varastosaldon automaatiota
- teemakoodin muutoksia

Ajantasainen hinta, varastotilanne ja ostettavuus tarkistetaan aina tuotteen omalta sivulta:
`/kauppa/sukulehdet/rytkosten-sukulainen-nro-9/`.

## Tuote

Tuotteen perusasetukset:

- nimi: `Rytkösten sukulainen nro 9`
- SKU: `RYTKOSTEN-SUKULAINEN-9`
- tyyppi: `Simple product`
- status: `Published`
- kategoria: `Sukulehdet`
- ei `Virtual`
- ei `Downloadable`
- varastonhallinta pois päältä MVP-vaiheessa

Tuote on WooCommerce-sisältöä. Sitä ei tallenneta repoon.

## Tuotekuvaus

Tuotekuvauksen pitää kertoa selkeästi, että kyse on painetusta lehdestä.

Minimisisältö:

- kyseessä on painettu `Rytkösten sukulainen nro 9`
- julkaisuajankohta: kesäkuu 2026
- sivumäärä ja koko
- sisällysluettelo tai lyhyt sisältökuvaus
- asiakas voi valita postituksen tai noudon, jos toimitustavat ovat kaupassa käytössä

## Toimitusmalli

Tuote käyttää fyysisten tuotteiden MVP-toimitusmallia:

- `Postitus`, hinta `5,90 €`
- `Nouto tapahtumasta / sovitusti`, hinta `0 €`

Toimitusmalli on dokumentoitu tarkemmin tiedostossa `docs/woocommerce-physical-products.md`.

## Rakennedata

Rank Math tuottaa tuotteen Product- ja Offer-rakennedatan. Teema lisää fyysiselle `Sukulehdet`-tuotteelle brändin **Rytkösten sukuseura**, jonka Finnan julkaisutietue vahvistaa lehden julkaisijaksi. Tietueessa ei ole ISSN- eikä ISBN-tunnusta, joten sellaista ei lisätä tuotteelle.

Arvostelu-, hinnan alkamis-, toimitusaika- ja palautustietoja ei lisätä pelkästään Search Console -varoitusten poistamiseksi. Avoimet tiedot ja rajauksen perusteet on kuvattu tiedostossa `docs/woocommerce-physical-products.md`.

## Ylläpidon tarkistuslista

Kun tuotetta päivitetään:

- tarkista hinta ja tuotteen ostettavuus
- tarkista toimitus- ja noutoteksti
- tarkista tuotekuva ja alt-teksti
- tarkista sisällysluettelon sivunumerot
- tarkista postikulu `5,90 €`, jos postitus on käytössä

## Testaus

Smoke-testi voidaan tehdä näin:

1. Avaa tuotteen sivu.
2. Lisää tuote ostoskoriin.
3. Siirry kassalle.
4. Varmista, että kassalla näkyy toimitusosoite.
5. Valitse `Postitus` ja varmista, että loppusummaan lisätään `5,90 €`, jos postitus on käytössä.
6. Valitse `Nouto tapahtumasta / sovitusti` ja varmista, ettei toimituskulua lisätä.
7. Varmista, että käytössä olevat maksutavat toimivat.

## Jatko

Mahdolliset myöhemmät tarkennukset:

- varastosaldo, jos painosmäärä on rajallinen
- tarkempi toimitusviesti tilausvahvistukseen
- mahdollinen kampanjateksti Tampere 2026 -sukujuhlan yhteyteen
