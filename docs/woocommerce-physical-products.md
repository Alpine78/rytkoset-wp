# WooCommerce: fyysiset tuotteet

Tämä dokumentti kuvaa #146-tiketin fyysisten tuotteiden MVP-mallin.

## Rajaus

Fyysisten tuotteiden perustuki tehdään WooCommercen omilla asetuksilla ilman uusia lisäosia tai teemakoodia.

Tässä vaiheessa tuetaan yksinkertaisia fyysisiä tuotteita, kuten:

- painettu `Rytkösten sukulainen` -lehti
- sukulehdet
- sukukirjat
- muut pienet painotuotteet

Tässä vaiheessa ei toteuteta:

- paino- tai kokoperusteisia toimitushintoja
- monimutkaista varastonhallintaa
- t-paitojen koko- ja värivariaatioita
- ulkoista logistiikka- tai varastointegraatiota
- print-on-demand-mallia

## Toimitusmalli

MVP:n toimitusmalli on `Postitus + nouto`.

WooCommercen toimitusalue:

- nimi: `Suomi`
- maa: `Finland`

Toimitustavat:

- `Postitus`
  - tyyppi: `Flat rate`
  - hinta: `5,90 €`
- `Nouto tapahtumasta / sovitusti`
  - tyyppi: `Local pickup`
  - hinta: `0 €`

Postitus rajataan tässä vaiheessa Suomeen. Nouto tarkoittaa käytännössä tapahtumanoutoa tai erikseen sovittua luovutusta, ei jatkuvaa myymälänoutoa.

Kiinteä `5,90 €` on väliaikainen MVP-postikulu pienille painotuotteille. Hinta pitää tarkistaa ennen oikeiden tuotteiden julkaisua.

## Merchant listing- ja Product-rakennedata

Rank Math muodostaa WooCommerce-tuotteiden Product- ja Offer-rakennedatan. Teema täydentää fyysisille `Sukulehdet`-kategorian tuotteille brändin **Rytkösten sukuseura**. Rajaus tarkistaa myös, ettei tuote ole virtuaalinen tai ladattava, joten jäsenmaksut, tapahtumamaksut ja digitaaliset tuotteet eivät peri fyysisten lehtien tietoja.

Brändi perustuu [Finnan Rytkösten sukulainen -tietueeseen](https://www.finna.fi/Record/jykdok.797937), jossa julkaisijaksi on merkitty Rytkösten sukuseura. Samassa tietueessa ei ole ISSN- eikä ISBN-tunnusta, joten tuotteille ei lisätä globaalia tunnistetta arvaamalla.

Seuraavat Search Consolen ei-kriittiset huomiot hyväksytään toistaiseksi tietoisesti:

- `aggregateRating` ja `review`: lisätään vain, jos tuotteilla on aitoja, hyväksyttyjä ja sivulla näkyviä tuotearvosteluja
- `validFrom`: lisätään vain hinnalle, jolla on todellinen ja ylläpidetty alkamisajankohta
- `shippingDetails`: lisätään vasta, kun todellinen käsittely- ja toimitusaikaväli on vahvistettu
- `hasMerchantReturnPolicy`: lisätään vasta, kun fyysisten tuotteiden palautusajan laskenta, palautustapa ja palautuskulujen vastuu on yhdenmukaistettu julkaistujen ehtojen kanssa

Google määrittelee `merchantReturnDays`-ajan laskettavaksi toimituspäivästä. Nykyinen itsepalveluperuutus ja ehtoteksti käyttävät tilauspäivää, joten rakennedata jätetään tältä osin pois, kunnes käytäntö on päätetty ja dokumentit sekä toiminnallisuus voidaan päivittää yhtenäisesti.

## Fyysisen tuotteen luonti

Luo uusi fyysinen tuote WooCommerce-adminissa:

1. Avaa `Products -> Add new product`.
2. Anna tuotteelle selkeä nimi.
3. Valitse tuotetyypiksi `Simple product`.
4. Älä valitse `Virtual`.
5. Älä valitse `Downloadable`.
6. Lisää hinta.
7. Valitse sopiva tuotekategoria, esimerkiksi `Muut tuotteet`, `Sukulehdet` tai `Sukukirjat`.
8. Lisää kuvaus, jossa kerrotaan toimitus- ja noutomahdollisuudesta.
9. Julkaise tuote.

Varastonhallintaa ei oteta MVP:ssä käyttöön oletuksena. Jos tuotteen määrä on rajallinen, määrä hallitaan aluksi manuaalisesti tai otetaan WooCommercen oma varastosaldo käyttöön tuotekohtaisesti.

## Paikallinen testituote

Paikalliseen ympäristöön on luotu testituote:

- nimi: `Fyysinen testituote`
- SKU: `FYYSINEN-TESTI`
- tyyppi: `Simple product`
- hinta: `1,00 €`
- kategoria: `Muut tuotteet`
- ei `Virtual`
- ei `Downloadable`

Testituote on paikallista WooCommerce-sisältöä. Sitä ei tallenneta repoon.

## Testaus

Fyysisen tuotteen testipolku:

1. Lisää `Fyysinen testituote` ostoskoriin.
2. Siirry kassalle.
3. Varmista, että kassalla näkyy toimitusosoite.
4. Valitse toimitustavaksi `Postitus`.
5. Varmista, että loppusummaan lisätään `5,90 €`.
6. Valitse toimitustavaksi `Nouto tapahtumasta / sovitusti`.
7. Varmista, ettei toimituskulua lisätä.
8. Tee testitilaus `Tilisiirto`-maksutavalla.
9. Varmista WooCommerce-adminissa, että tilauksella näkyy fyysinen tuote ja valittu toimitustapa.

Regressiotestit:

- Digitaalinen ladattava tuote ei saa toimitusvalintaa.
- Jäsenmaksutuotteet eivät saa toimitusvalintaa.
- Tampere 2026 -virtuaalituote ei saa toimitusvalintaa.
- Maksutapojen näkyvyys ei muutu tämän mallin takia.

## Paikallisen testin tulos

Paikallisessa ympäristössä 19.4.2026 varmistettiin:

- `Suomi`-toimitusalue on olemassa.
- `Postitus` näkyy kassalla hinnalla `5,90 €`.
- `Nouto tapahtumasta / sovitusti` näkyy kassalla maksuttomana toimitustapana.
- `Fyysinen testituote` näyttää kassalla toimitusosoitteen ja toimitustavan valinnan.
- `Postitus` nostaa `1,00 €` testituotteen loppusumman arvoon `6,90 €`.
- `Nouto tapahtumasta / sovitusti` pitää `1,00 €` testituotteen loppusumman arvossa `1,00 €`.
- `Tilisiirto`-testitilaus onnistuu ja tilaukselle tallentuu valittu toimitustapa.
- Digitaalinen testituote ei näytä toimitusvalintoja.
- Jäsenmaksutuotteet ja Tampere 2026 -osallistumismaksu ovat edelleen virtuaalisia tuotteita.

## Jatko

`Rytkösten sukulainen nro 9` -tuote on dokumentoitu erikseen tiedostossa `docs/woocommerce-rytkosten-sukulainen-product.md`.

Myöhempiin tiketteihin jäävät:

- postikulun vahvistaminen oikeiden tuotteiden perusteella
- todellisen käsittely- ja toimitusaikavälin vahvistaminen rakennedataa varten
- fyysisten tuotteiden palautusajan, palautustavan ja palautuskulujen vastuun vahvistaminen
- mahdolliset tuotevariantit, kuten t-paitojen koot ja värit
- tuotekohtainen varastosaldo, jos tuotteita on rajattu määrä
- toimitusmallin tarkennus, jos myyntiä halutaan Suomen ulkopuolelle
