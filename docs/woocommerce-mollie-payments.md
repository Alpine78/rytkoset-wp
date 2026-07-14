# WooCommerce: Mollie-maksutavat

> **Lepäävä palautusohje (14.7.2026).** Paytrail on käytössä kokeilujakson ajan
> (#530). Tätä ohjetta ei käytetä nykyisen maksupalvelun konfigurointiin, mutta
> se säilytetään, jotta Mollieen voidaan tarvittaessa palata hallitusti.
> Mollie-lisäosaa ei deaktivoida ennen avoimien `pending`/`on-hold`-maksujen ja
> niiden webhook-päivitysten tarkistamista.

Tämä dokumentti kuvaa #145-tiketin paikallisen Mollie-käyttöönoton ja testimallin.

Varsinainen dev-live käyttöönotto ja oikeiden hyväksymistestien malli on dokumentoitu erikseen tiedostossa `docs/woocommerce-mollie-go-live.md`.

MobilePayn erillinen käyttöönotto ja mahdolliset Mollie-tilikohtaiset blockerit on dokumentoitu tiedostossa `docs/woocommerce-mollie-mobilepay.md`.

## Rajaus

Tässä vaiheessa Mollie otetaan käyttöön vain paikallisessa/testimoodin käyttöönotossa.

Ei tehdä tässä tiketissä:

- live-avaimia
- oikeita maksuja
- MobilePay-käyttöönottoa
- tuotantodomainin Apple Pay -validointia
- omaa maksupalveluintegraatiota virallisen Mollie-lisäosan ohi

MobilePay käsitellään erillisessä tiketissä #156 ja dokumentissa `docs/woocommerce-mollie-mobilepay.md`. Tuotantokäyttöönotto ja oikeat maksut käsitellään erillisessä tiketissä #157.

## Asennus

Mollie asennetaan WordPress Adminin kautta:

1. Avaa `Plugins -> Add New`.
2. Hae `Mollie Payments for WooCommerce`.
3. Valitse virallinen lisäosa: `Mollie Payments for WooCommerce` by `Mollie`.
4. Paina `Install Now`.
5. Paina `Activate`.

Pluginia ei lisätä versionhallintaan. `wp-content/plugins/*` on rajattu `.gitignore`ssa pois, joten paikallinen plugin-asennus ei kuulu commitattaviin muutoksiin.

## Asetukset

Mollien asetukset tehdään WooCommercen hallinnassa:

1. Avaa `WooCommerce -> Settings`.
2. Avaa `Mollie Settings`.
3. Aseta maksutilaksi `Test API`.
4. Lisää Mollien test API key.
5. Tallenna asetukset.

API-avainta ei saa tallentaa dokumentaatioon, koodiin tai GitHubiin.

Mollie Dashboardissa aktivoidaan tämän vaiheen maksutavat, jos tilin ja profiilin ehdot täyttyvät:

- `Pay by Bank`
- korttimaksut
- `Apple Pay`
- `Google Pay`

`Tilisiirto` pidetään WooCommercessa päällä fallback-maksutapana.

WooPaymentsia ei käytetä tässä toteutuksessa. Jos WooPaymentsin maksutapoja näkyy kassalla, ne poistetaan käytöstä WooCommercen maksuasetuksista.

## Paikallinen tila

Paikallisessa ympäristössä 19.4.2026 tarkistettu:

- `Mollie Payments for WooCommerce` on aktiivinen.
- Mollien testimoodi on päällä.
- Mollien test API key on asetettu.
- `Pay by Bank` on käytössä Mollien gateway-asetuksissa.
- Korttimaksut ovat käytössä Mollien gateway-asetuksissa.
- `Apple Pay` on käytössä Mollien gateway-asetuksissa.
- `Tilisiirto` on edelleen käytössä.
- WooPayments ei ole aktiivinen WordPress-plugin.

`Google Pay` ei näy välttämättä erillisenä WooCommerce-gatewayna. Mollien dokumentaation mukaan Google Pay näkyy Mollie Hosted Checkout -polussa, jos selain, laite ja maksuprofiilin asetukset tukevat sitä.

## Testaus

Testaa paikallisessa ympäristössä vähintään:

1. Lisää ostoskoriin Tampere 2026 -osallistumismaksu tai jäsenmaksutuote.
2. Siirry kassalle.
3. Varmista, että Mollie-maksutapa näkyy.
4. Varmista, että korttimaksun kentät latautuvat testimoodissa.
5. Tee testitilaus `Tilisiirto`-maksutavalla.
6. Varmista, että tilisiirto toimii edelleen fallbackina.

Testaa julkisella dev- tai tuotantodomainilla ennen live-käyttöönottoa:

1. Tee Mollien testimaksu onnistuneella maksutilalla.
2. Varmista, että WooCommerce-tilauksen tila päivittyy oikein.
3. Tee toinen testitilaus perutulla tai epäonnistuneella maksutilalla.
4. Varmista, ettei epäonnistunut maksu merkitse tilausta maksetuksi.

## Paikallisen testin tulos

Paikallisessa testissä 19.4.2026 varmistettiin:

- `Tilisiirto` toimii edelleen fallback-maksutapana ja luo tilauksen `on-hold`-tilaan.
- Mollien korttimaksu näkyy kassalla ja korttikentät latautuvat testimoodissa.
- WooCommerce Blocks -checkout ei näytä maksutapojen latauksen jälkeen virheellistä "no payment methods" -ilmoitusta.
- WooPayments ei näy kassalla rinnakkaisena maksupalveluna.

Varsinaista onnistunutta Mollie-testimaksua ei voi vahvistaa pelkässä `localhost`-ympäristössä ilman julkista webhook-osoitetta. Mollie hylkää paikallisen maksun luonnin, koska webhook-osoite `localhost:8000` ei ole Mollien palvelimilta saavutettavissa.

Tämä ei tarkoita, että WooCommerce-checkout tai Mollie-lisäosan paikallinen peruskonfiguraatio olisi rikki. Se tarkoittaa, että maksun onnistunut läpivienti, peruutettu maksu, epäonnistunut maksu ja webhookien statuspäivitykset pitää testata #157-vaiheessa julkisella dev- tai tuotantodomainilla, tai erillisellä tunnelilla, jonka webhook-URL Mollie pystyy tavoittamaan.

Localhost-ympäristössä `Store coming soon` -tila voi estää vierailijakassan näkyvyyden. Jos kassaa testataan ilman kirjautumista, tila pitää kytkeä paikallisesti väliaikaisesti pois ja palauttaa testin jälkeen.

## Maksusähköpostin aihe (#324)

Mollien lähettämän tilisiirron maksusähköpostin (`noreply@mollie.com`) aiheessa näkyi
englanninkielinen tilausviittaus, esim. `Maksutiedot tilauksestasi "Order 1093"`.

Aiheen lainattu osa on **maksun kuvaus**, jonka Mollie-lisäosa lähettää Mollien API:lle.
Kuvaus muodostetaan lisäosan `PaymentDescriptionMiddleware`-luokassa asetuksesta
`WooCommerce → Settings → Mollie Settings → Advanced → API Payment Description`,
jonka oletusarvo on `{orderNumber}`. Tällä oletusarvolla lisäosa käyttää käännettävää
lähdetekstiä `_x( 'Order {orderNumber}', 'Payment description for {orderNumber}', 'mollie-payments-for-woocommerce' )`,
josta englanninkielinen "Order" tulee.

**Ratkaisu (toteutettu koodissa):** `inc/woocommerce-mollie.php` lisää `gettext_with_context`-suodattimen
(`rytkoset_theme_mollie_finnish_contexts`), joka kääntää tämän lähdetekstin suomeksi
`Tilaus {orderNumber}`, kun WordPressin locale on `fi`. Mollie korvaa `{orderNumber}`-paikkamerkin
tilausnumerolla, joten aiheeksi tulee `Maksutiedot tilauksestasi "Tilaus 1093"`.

Ratkaisu pidettiin teemassa (versionhallinnassa) eikä WooCommerce-asetuksessa, jotta se
toimii automaattisesti jokaisessa ympäristössä eikä häviä asetusten resetoituessa.
Käännös vaikuttaa, kun "API Payment Description" -asetus on oletusarvossa `{orderNumber}`;
jos asetukseen syötetään oma kuvausteksti, se ohittaa käännöksen.

Vaihtoehtoinen koodittomuus: saman lopputuloksen saa myös asettamalla "API Payment Description"
-kenttään arvon `Tilaus {orderNumber}`, mutta tämä asetus elää tietokannassa ja pitäisi tehdä
erikseen jokaiseen ympäristöön.

## Maksutapojen järjestys ja kassan ohjeistus (#397, #398)

Mollie on hollantilainen maksunvälittäjä, joten pankkimaksut selvitetään ulkomaisen pankin kautta. Tämä aiheuttaa suomalaisille maksajille kaksi käytännön ongelmaa, jotka koskevat **kaikkia maksutapoja, myös kortteja** (juurisyyn selvitys ja kotimaisen maksunvälittäjän vertailu: tiketti #396):

- **Rajat ylittävä maksu:** osa suomalaisista pankeista (esim. POP Pankki) vaatii ulkomaanmaksun erillisen hyväksynnän ja maan valinnan. Koskee sekä `Tilisiirtoa` että `Pay by Bankia` (Pay by Bank ei kierrä ongelmaa). Korttimaksukin on schemen näkökulmasta rajat ylittävä, joten esim. korttiin asetettu alue­rajoitus (vain Pohjoismaat ja Baltia) voi estää sen.
- **RF-viite väliviivoilla:** Mollie muotoilee RF-viitteen väliviivoilla (`RF98-1937-…`), jotka suomalaiset verkkopankit hylkäävät. Teema poistaa väliviivat WooCommercen renderöimistä näkymistä (kiitossivu, tilausnäkymä, asiakassähköposti), mutta **ei voi muokata Mollien omaa maksusähköpostia** (`noreply@mollie.com`).

### Maksutapojen järjestys (koodissa)

`inc/woocommerce-mollie.php`:n `rytkoset_theme_demote_mollie_bank_gateways()` (`woocommerce_available_payment_gateways`) siirtää `mollie_wc_gateway_banktransfer`- ja `mollie_wc_gateway_paybybank`-tavat maksutapalistan loppuun, jolloin kortti / Apple Pay / Google Pay ovat ensin ja valikoituvat oletuksena. Tavoite on ohjata valtaosa maksajista pois ongelmallisilta pankkimaksuilta. RF-viiteilmoitus ohjaa kortille eikä lupaa MobilePayta, jota ei ole vielä aktivoitu Mollie-tilillä.

### Kassan ohjeistus (admin, ei versionhallinnassa)

Maksutavan **Description**-kenttään (WooCommerce → Asetukset → Maksutavat) kannattaa lisätä mieto maininta, että maksu kulkee Mollien kautta Alankomaihin. Teksti näkyy Block-kassassa maksutavan alla, kun asiakas valitsee sen. Suositellut tekstit:

- **Luottokortti:** "Maksunvälittäjänä toimii Mollie, ja korttimaksu käsitellään Alankomaissa. Jos kortillesi on asetettu maa- tai aluerajoitus (esim. vain Pohjoismaat ja Baltia), salli ulkomaanmaksut, jotta maksu menee läpi."
- **Tilisiirto:** "Maksu menee Mollien kautta hollantilaiseen pankkiin. Osa suomalaisista pankeista (esim. POP Pankki) vaatii ulkomaanmaksun erillisen hyväksynnän, ja maksun yhteydessä voi joutua valitsemaan maan. Maksat helpoiten kortilla."
- **Verkkopankkimaksu (Pay by Bank):** "Maksu välitetään Mollien kautta hollantilaiseen pankkiin, joten osa suomalaisista pankeista voi vaatia ulkomaanmaksun erillisen hyväksynnän. Maksat helpoiten kortilla."

Nämä tekstit elävät WooCommercen asetuksissa (tietokannassa), eivät versionhallinnassa, joten ne pitää lisätä erikseen dev- ja tuotantoympäristöön.

## Maksun jatkaminen epäonnistumisen jälkeen (#462)

Asiakas löytää omat tilauksensa polusta **Oma tili -> Tilaukset**
(`/oma-tili/tilaukset/`). Teema lisää kirjautuneen käyttäjän yläpalkin
tilivalikon fallbackiin suorat linkit **Oma tili** ja **Tilaukset**, ja
päävalikon `Kauppa -> Oma tili` -linkki pidetään edelleen suositeltuna
valikkoasetuksena.

Maksun uudelleenyritys tehdään WooCommercen omalla maksupolulla
(`maksa-tilaus`, WooCommercen endpoint-avain `order-pay`). Teema
ei luo omaa maksuprosessia eikä kutsu Mollien API:a suoraan, vaan näyttää
tilausrivillä painikkeen **Maksa / yritä uudelleen** silloin, kun
WooCommerce-tilausobjektin `needs_payment()` palauttaa `true` ja
`get_checkout_payment_url()` antaa maksulinkin.

Käyttäjäohjeissa pitää siksi käyttää ehdollista muotoa:

> Jos maksu jäi kesken, kirjaudu sisään ja avaa **Oma tili -> Tilaukset**.
> Jos tilauksen kohdalla näkyy **Maksa / yritä uudelleen**, voit jatkaa maksua
> ja valita kassalla toisen maksutavan. Jos painiketta ei näy, ota yhteyttä
> sähköpostitse: info@rytkoset.net.

Älä lupaa, että kaikki keskeneräiset Mollie-tilaukset voi vaihtaa itse toiselle
maksutavalle. Esimerkiksi pankki-/tilisiirtopolku voi jättää tilauksen tilaan,
jossa WooCommerce ei enää pidä tilausta maksettavana samalla maksupolulla.
Tällöin käyttäjälle näytetään korkeintaan peruutuspolku tai yhteydenotto-ohje.

## Lähteet

- Mollie WooCommerce plugin: https://wordpress.org/plugins/mollie-payments-for-woocommerce/
- Mollie WooCommerce test/go-live: https://docs.mollie.com/docs/woo-test-and-go-live
- Mollie WooCommerce payment options: https://docs.mollie.com/docs/woo-set-up-payment-options
- Mollie Apple Pay activation: https://help.mollie.com/hc/en-us/articles/360022855933-How-do-I-activate-Apple-Pay
- Mollie Google Pay: https://docs.mollie.com/docs/google-pay
- Mollie Pay by Bank: https://docs.mollie.com/docs/pay-by-bank
