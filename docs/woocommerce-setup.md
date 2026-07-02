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
- Digitaaliset tuotteet on dokumentoitu erikseen tiedostossa `docs/woocommerce-digital-products.md`.
- Tampere 2026 -osallistumismaksutuote on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-product.md`.
- Tampere 2026 -checkout-kentät on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-checkout-fields.md`.
- Tampere 2026 -tapahtumamaksun hallinta on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-management.md`.
- Tampere 2026 -osallistujat näkyvät yhteisessä `Tapahtumat > Osallistujat` -näkymässä; katso `docs/event-participants-admin.md`.
- Maksullisten tapahtumien tapahtumakohtaiset järjestäjäilmoitukset on dokumentoitu erikseen tiedostossa `docs/woocommerce-tampere-2026-notifications.md`.
- Mollie-maksutapojen paikallinen testikäyttöönotto on dokumentoitu erikseen tiedostossa `docs/woocommerce-mollie-payments.md`.
- Mollien dev-live käyttöönotto ja hyväksymistestaus on dokumentoitu erikseen tiedostossa `docs/woocommerce-mollie-go-live.md`.
- Mollie MobilePay -käyttöönotto ja mahdolliset tilikohtaiset blockerit on dokumentoitu erikseen tiedostossa `docs/woocommerce-mollie-mobilepay.md`.
- Fyysisten tuotteiden perustuki on dokumentoitu erikseen tiedostossa `docs/woocommerce-physical-products.md`.
- `Rytkösten sukulainen nro 9` -ennakkotilaustuote on dokumentoitu erikseen tiedostossa `docs/woocommerce-rytkosten-sukulainen-product.md`.
- Kaupan, tapahtumien, albumien ja ostoskorin valikkorakenne on dokumentoitu erikseen tiedostossa `docs/menu-structure.md`.
- Checkoutin sisällöllinen ja saavutettava hienosäätö.
- Sähköpostien sisällön ja ulkoasun tarkempi viimeistely.
- Verot, painoperusteiset toimitukset ja muut tarkemmat myyntilogiikan asetukset.

## Suomenkieliset WooCommerce-loppuliitteet (#462)

WooCommerce tallentaa kassan ja Oma tili -sivun erityistoiminnot asetuksina.
Ne voi suomentaa kohdassa **WooCommerce -> Asetukset -> Lisäasetukset**.
Tallenna muutoksen jälkeen myös **Asetukset -> Osoiterakenne -> Tallenna
muutokset**, jotta rewrite-säännöt päivittyvät.

### Kassa

| Kenttä | Arvo |
|---|---|
| Maksa | `maksa-tilaus` |
| Tilaus vastaanotettu | `tilaus-vastaanotettu` |
| Lisää maksutapa | `lisaa-maksutapa` |
| Poista maksutapa | `poista-maksutapa` |
| Aseta oletusmaksutapa | `aseta-oletusmaksutapa` |

### Oma tili

| Kenttä | Arvo |
|---|---|
| Tilaukset | `tilaukset` |
| Tarkastele tilausta | `tarkastele-tilausta` |
| Lataukset | `lataukset` |
| Muokkaa tiliä | `tilin-tiedot` |
| Osoite | `osoitteet` |
| Maksutavat | `maksutavat` |
| Salasana unohtunut | `unohtunut-salasana` |
| Kirjaudu ulos | `kirjaudu-ulos` |

## Huomio

`Tilisiirto` on käytössä paikallisessa ympäristössä fallback-maksutapana. Mollie on dokumentoitu erillisenä testikäyttöönottona, mutta ennen tuotantokäyttöä asetukset, maksutavat, webhookit ja pankkitiedot on vielä tarkistettava dev- ja tuotantoympäristöissä erikseen.
