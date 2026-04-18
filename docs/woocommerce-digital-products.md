# WooCommerce: digitaaliset tuotteet

Tämä dokumentti kuvaa digitaalisten WooCommerce-tuotteiden ensimmäisen MVP-toteutuksen paikallisessa ympäristössä.

## Toteutusmalli

Digitaaliset tuotteet toteutetaan WooCommercen omalla ladattavan tuotteen mallilla:

- tuotetyyppi: `Simple product`
- `Virtual`: kyllä
- `Downloadable`: kyllä
- tiedosto: PDF WordPressin mediakirjastosta
- maksutapa: nykyinen `Tilisiirto`

Tässä vaiheessa ei rakenneta omaa digitaalista kirjastoa, vesileimoja, jäsenkohtaisia käyttöoikeuksia tai erillistä tiedostonhallintaa.

## Testituote

Paikalliseen WooCommerceen luodaan testituote:

- nimi: `Digitaalinen testijulkaisu (PDF)`
- SKU: `DIGI-TEST-PDF`
- hinta: `1,00 €`
- kategoria: `Sukukirjat`
- tiedosto: `digitaalinen-testijulkaisu.pdf`
- lataustiedoston nimi: `Digitaalinen testijulkaisu`

Testitiedosto on tarkoitettu vain paikalliseen perustestaukseen. Sitä ei käytetä oikeana sukuseuran julkaisuna.

## Latausoikeus

Tilisiirron vuoksi latausoikeutta ei anneta heti tilauksen jälkeen.

WooCommercen asetus `Grant access to downloadable products after payment` on käytössä. Käytännössä tämä tarkoittaa, että `processing`-tilaan siirretty tilaus saa latausoikeuden. `on-hold`-tilassa latausoikeutta ei anneta.

Oletettu ylläpitomalli:

1. Asiakas ostaa digitaalisen tuotteen kassalla.
2. Tilaus siirtyy tilaan `on-hold`.
3. Ylläpito tarkistaa maksun saapumisen pankkitililtä.
4. Ylläpito merkitsee tilauksen käsitellyksi tai valmiiksi.
5. WooCommerce antaa latausoikeuden normaalin ladattavan tuotteen logiikan kautta.

Tämä on turvallisempi malli kuin latauksen avaaminen heti ennen tilisiirtomaksun vahvistumista.

## Tekniset huomiot

- WooCommercen approved download directories -suojaus on käytössä.
- Testi-PDF:n upload-hakemisto on lisätty WooCommercen hyväksyttyihin lataushakemistoihin paikallisessa ympäristössä.
- Testituotteen SKU on pysyvä tunniste: `DIGI-TEST-PDF`.
- Testitiedosto sijaitsee WordPressin uploads-hakemistossa, jota ei commitata repoon.

## Testattu nyt

- Testituote on `Virtual` ja `Downloadable`.
- Testituotteella on yksi PDF-lataustiedosto.
- Testituote kuuluu kategoriaan `Sukukirjat`.
- Paikallisessa testitilauksessa `on-hold`-tila ei luonut latausoikeutta.
- Sama testitilaus loi latausoikeuden, kun tilaus siirrettiin `processing`-tilaan.
- Paikallisessa ympäristössä sähköpostin lähetys ei onnistunut, koska kontissa ei ole `sendmail`-palvelua. Tämä ei estänyt latausoikeuden syntymisen testausta.

## Uuden digitaalisen tuotteen lisääminen

1. Lisää PDF WordPressin mediakirjastoon.
2. Luo WooCommerce-tuote.
3. Valitse tuotetyypiksi `Simple product`.
4. Merkitse tuote `Virtual` ja `Downloadable`.
5. Lisää PDF tuotteen `Downloadable files` -kohtaan.
6. Lisää tuotteelle selkeä nimi, kuvaus, hinta ja SKU.
7. Sijoita tuote sopivaan tuotekategoriaan, esimerkiksi `Sukukirjat` tai `Sukulehdet`.
8. Testaa ostoskori, kassa ja latausoikeuden syntyminen ennen julkaisua.

## Rajaukset

Tämä MVP ei sisällä:

- erillistä digitaalisten julkaisujen katalogia
- oikeuksien hallintaa WooCommercen oletuksia pidemmälle
- vesileimoja
- PDF-tiedostojen erillistä suojausratkaisua
- jäsenkohtaisia alennuksia
- erillistä teemaan rakennettua digitaalista kirjastonäkymää

Nämä voidaan tehdä myöhemmissä tiketeissä, jos tarve tarkentuu.
