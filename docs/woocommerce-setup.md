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

## Kaupan tuotekategorianavigaatio

Kaupan etusivulla ja tuotekategoria-arkistoissa näkyvä kategoriapalkki näyttää
`Kaikki`-linkin sekä vain kategoriat, joissa on vähintään yksi julkaistu ja
WooCommercen katalogissa näkyvä tuote. Pelkkä kategorian WordPress-termimäärä
ei ratkaise näkyvyyttä: kataloginäkyvyydeltään **Piilotettu** tai vain haussa
näkyvä tuote ei pidä kategoriaa mukana palkissa.

Jos tyhjän kategorian osoite avataan suoraan, sama palkki renderöidään ennen
WooCommercen tyhjän tuloksen ilmoitusta. `Kaikki`-linkistä pääsee takaisin
kaupan etusivulle. Kategoria palaa palkkiin automaattisesti, kun siihen kuuluu
jälleen julkaistu katalogissa näkyvä tuote. Teema tukee tässä sekä
WooCommercen tavallista arkistotemplatea että nykyisen teeman käyttämää
shortcode-yhteensopivuuspolkua.

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
- Paytrailin kokeilujakson käyttöönotto, hyväksymistestaus ja Mollieen palaamisen turvarajat on dokumentoitu tiedostossa `docs/woocommerce-paytrail.md`.
- Fyysisten tuotteiden perustuki on dokumentoitu erikseen tiedostossa `docs/woocommerce-physical-products.md`.
- `Rytkösten sukulainen nro 9` -tuote on dokumentoitu erikseen tiedostossa `docs/woocommerce-rytkosten-sukulainen-product.md`.
- Kaupan, tapahtumien, albumien ja ostoskorin valikkorakenne on dokumentoitu erikseen tiedostossa `docs/menu-structure.md`.
- Checkoutin sisällöllinen ja saavutettava hienosäätö.
- Sähköpostien sisällön ja ulkoasun tarkempi viimeistely.
- Verot, painoperusteiset toimitukset ja muut tarkemmat myyntilogiikan asetukset.

## Tyhjän ostoskorin näkymä (#581)

Teema korvaa Cart-lohkon `woocommerce/empty-cart-block`-sisällön
`inc/woocommerce-empty-cart.php`-moduulissa. Näkymässä on kauppaan ohjaava hero,
`assets/images/home/home-kauppa-illustration.png`-kuvitus ja enintään neljä
julkaistua, katalogissa näkyvää ja ostettavaa tuotepoimintaa. Featured-tuotteet
ovat etusijalla, ja puuttuvat paikat täytetään uusimmilla tuotteilla.

Toissijainen linkki muodostetaan seuraavasta tulevasta julkaistusta
**maksullisesta** tapahtumasta `rytkoset_theme_get_next_upcoming_event_id( 'paid', true )`
-helperillä. Ostoskorin kutsu vaatii lisäksi, että tapahtumalla on linkitetystä
tuotteesta ratkaistava ilmoittautumisen määräaika eikä se ole mennyt umpeen.
Maksuttomia tapahtumia tai sulkeutuneita ilmoittautumisia ei nosteta ostoskorin
yhteydessä. Tapahtumapäivä lasketaan tulevaksi päivän loppuun asti. Jos sopivaa
maksullista ja ilmoittautumiseltaan avointa tapahtumaa ei ole, linkkiä ei
renderöidä. Täytetyn ostoskorin näkymään moduuli ei vaikuta.

## Suomenkieliset WooCommerce-loppuliitteet (#462)

WooCommerce tallentaa kassan ja Oma tili -sivun erityistoiminnot asetuksina.
Ne voi suomentaa kohdassa **WooCommerce -> Asetukset -> Lisäasetukset**.
Tallenna muutoksen jälkeen myös **Asetukset -> Osoiterakenne -> Tallenna
muutokset**, jotta rewrite-säännöt päivittyvät.

> **Huom. (#496):** tämä on ympäristökohtainen admin-asetus, ei koodia — se on
> tarkistettava/tallennettava erikseen sekä dev- että tuotantoympäristössä.
> Paikallisessa ympäristössä alla olevat arvot on todennettu käytössä oleviksi.
> Teeman koodi käyttää endpointteja aina avaimilla (`orders`, `edit-address`,
> …) `wc_get_account_endpoint_url()`-funktion kautta, joten julkisen
> loppuliitteen kieli ei vaikuta koodin toimintaan.

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

Lisäksi teema rekisteröi oman Oma tili -endpointin uutiskirjeen hallintaan:

| Endpoint-avain | Slug | Huomio |
|---|---|---|
| `rytkoset_newsletter` | `uutiskirje` | Teeman koodissa rekisteröity, ei WooCommercen admin-asetus. URL rakentuu `wc_get_account_endpoint_url()`-funktion kautta. |

## Huomio

Paytrail on nykyinen kokeilujakson maksunvälittäjä (#530). Dev-kassalla on
14.7.2026 varmennettu näkyviksi Paytrailin maksutaparyhmät sekä erillinen
`Pankkisiirto, SEPA-maksu` -vaihtoehto. Maksujen, paluu-URL:ien, sähköpostien,
tilasiirtymien ja tuotannon asetusten hyväksymistestaus tehdään
[`woocommerce-paytrail.md`](woocommerce-paytrail.md)-ohjeen mukaan.

Mollien kolme käyttöönotto-ohjetta ovat lepääviä palautusohjeita. Mollie-koodia
ei poisteta, eikä lisäosaa deaktivoida ennen kuin avoimet Mollie-maksut ja
niiden webhook-päivitysten tarve on tarkistettu.
