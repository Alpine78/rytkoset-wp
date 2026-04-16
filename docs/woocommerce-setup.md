# WooCommerce: peruskonfiguraatio

Tämä dokumentti kuvaa ensimmäisen WooCommerce-slicen paikallisessa Docker-ympäristössä.

## Tehty nyt

- WooCommercen perussivut tarkistettu ja roolit sidottu asetuksiin.
- Kaupan sivuksi asetettu olemassa oleva `Kauppa`-sivu.
- WooCommercen automaattisesti luoma erillinen `Shop`-sivu poistettu käytöstä asettamalla se luonnokseksi.
- Perussivut lokalisoitu suomeksi:
  - `Kauppa` -> `/kauppa/`
  - `Ostoskori` -> `/ostoskori/`
  - `Kassa` -> `/kassa/`
  - `Oma tili` -> `/oma-tili/`
- Kaupan perusasetukset varmistettu:
  - valuutta `EUR`
  - maa `FI`
- Kaupan osoitetiedot täydennetty wp-adminissa.
- Sähköpostin minimiasetukset varmistettu:
  - lähettäjän nimi `Rytkösten sukuseura`
  - lähettäjän osoite `ilkka@rytkoset.net`
- Ensimmäinen maksutapa otettu käyttöön:
  - `Tilisiirto`
- Tilisiirron pankkitiedot täydennetty wp-adminissa.
- Tuotekategoriat luotu:
  - `Sukulehdet`
  - `Sukukirjat`
  - `Muut tuotteet`

## Testattu nyt

- Etusivu vastaa ilman PHP-virhettä.
- WooCommercen cart Store API toimii.
- Ostoskoriin voitiin lisätä väliaikainen virtuaalituote smoke-testiä varten.
- `Kassa`-sivu vastaa HTTP 200.
- `Tilisiirto`-gateway on aktiivinen WooCommercen sisäisessä gateway-listassa.
- Väliaikainen smoke-testituote poistettiin testin jälkeen.

## Jätetään seuraaviin tiketteihin

- Lasku maksutapana, jos se halutaan erillisenä vaihtoehtona.
- Tuotepohjat ja WooCommerce-layoutin sovitus teemaan.
- Oikeat tuotteet:
  - sukulehdet
  - sukukirjat
  - digitaaliset tuotteet
- Jäsenmaksutuotteet on dokumentoitu erikseen tiedostossa `docs/woocommerce-membership-products.md`.
- Tampere 2026 -osallistumismaksutuote on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-product.md`.
- Tampere 2026 -checkout-kentät on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-checkout-fields.md`.
- Tampere 2026 -osallistujalista adminissa on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-participants-admin.md`.
- Tampere 2026 -osallistujalistan CSV-vienti on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-participants-csv-export.md`.
- Tampere 2026 -järjestäjäilmoitukset on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-notifications.md`.
- Checkoutin sisällöllinen ja saavutettava hienosäätö.
- Sähköpostien sisällön ja ulkoasun tarkempi viimeistely.
- Verot, toimitukset ja muut myyntilogiikan asetukset.

## Huomio

`Tilisiirto` on nyt käytössä paikallisessa ympäristössä. Ennen tuotantokäyttöä asetukset ja pankkitiedot on vielä tarkistettava dev- ja tuotantoympäristöissä erikseen.
