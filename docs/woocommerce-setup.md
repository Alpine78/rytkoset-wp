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
- Kaupan osoitetiedot taydennetty wp-adminissa.
- Sahkopostin minimiasetukset varmistettu:
  - lahettajan nimi `Rytkosten sukuseura`
  - lahettajan osoite `ilkka@rytkoset.net`
- Ensimmainen maksutapa otettu kayttoon:
  - `Tilisiirto`
- Tilisiirron pankkitiedot taydennetty wp-adminissa.
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

- Lasku maksutapana, jos se halutaan erillisena vaihtoehtona.
- Tuotepohjat ja WooCommerce-layoutin sovitus teemaan.
- Oikeat tuotteet:
  - sukulehdet
  - sukukirjat
  - digitaaliset tuotteet
- Jasenmaksutuotteet on dokumentoitu erikseen tiedostossa `docs/woocommerce-membership-products.md`.
- Checkoutin sisallollinen ja saavutettava hienosaato.
- Sahkopostien sisallon ja ulkoasun tarkempi viimeistely.
- Verot, toimitukset ja muut myyntilogiikan asetukset.

## Huomio

`Tilisiirto` on nyt kaytossa paikallisessa ymparistossa. Ennen tuotantokayttoa asetukset ja pankkitiedot on viela tarkistettava dev- ja tuotantoymparistoissa erikseen.
