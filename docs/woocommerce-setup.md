# WooCommerce: peruskonfiguraatio

Tama dokumentti kuvaa ensimmaisen WooCommerce-slicen paikallisessa Docker-ymparistossa.

## Tehty nyt

- WooCommercen perussivut tarkistettu ja roolit sidottu asetuksiin.
- Kaupan sivuksi asetettu olemassa oleva `Kauppa`-sivu.
- WooCommercen automaattisesti luoma erillinen `Shop`-sivu poistettu kaytosta asettamalla se luonnokseksi.
- Perussivut lokalisoitu suomeksi:
  - `Kauppa` -> `/kauppa/`
  - `Ostoskori` -> `/ostoskori/`
  - `Kassa` -> `/kassa/`
  - `Oma tili` -> `/oma-tili/`
- Kaupan perusasetukset varmistettu:
  - valuutta `EUR`
  - maa `FI`
- Sahkopostin minimiasetukset varmistettu:
  - lahettajan nimi `Rytkosten sukuseura`
  - lahettajan osoite `ilkka@rytkoset.net`
- Ensimmainen maksutapa otettu kayttoon:
  - `Tilisiirto`
- Tuotekategoriat luotu:
  - `Sukulehdet`
  - `Sukukirjat`
  - `Muut tuotteet`

## Testattu nyt

- Etusivu vastaa ilman PHP-virhetta.
- WooCommercen cart Store API toimii.
- Ostoskoriin voitiin lisata valiaikainen virtuaalituote smoke-testia varten.
- `Kassa`-sivu vastaa HTTP 200.
- `Tilisiirto`-gateway on aktiivinen WooCommercen sisaisessa gateway-listassa.
- Valiaikainen smoke-testituote poistettiin testin jalkeen.

## Jatetaan seuraaviin tiketteihin

- Kaupan katuosoite, kaupunki ja postinumero.
  Osoite on asetetettu wp-administa.
- Tilisiirron oikeat pankkitiedot on asetettu:
  - tilinomistaja
  - IBAN
  - BIC
  - mahdollinen viitenumerokaytanto
- Lasku maksutapana, jos se halutaan erillisena vaihtoehtona.
- Tuotepohjat ja WooCommerce-layoutin sovitus teemaan.
- Oikeat tuotteet:
  - jasenmaksut
  - sukulehdet
  - sukukirjat
  - digitaaliset tuotteet
- Checkoutin sisallollinen ja saavutettava hienosaato.
- Sahkopostien sisallon ja ulkoasun tarkempi viimeistely.
- Verot, toimitukset ja muut myyntilogiikan asetukset.

## Huomio

`Tilisiirto` on otettu kayttoon minimikonfiguraatiolla, mutta sita ei voi ottaa oikeaan tuotantokayttoon ennen pankkitietojen taydentamista.
