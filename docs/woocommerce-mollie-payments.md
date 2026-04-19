# WooCommerce: Mollie-maksutavat

Tämä dokumentti kuvaa #145-tiketin paikallisen Mollie-käyttöönoton ja testimallin.

## Rajaus

Tässä vaiheessa Mollie otetaan käyttöön vain paikallisessa/testimoodin käyttöönotossa.

Ei tehdä tässä tiketissä:

- live-avaimia
- oikeita maksuja
- MobilePay-käyttöönottoa
- tuotantodomainin Apple Pay -validointia
- omaa maksupalveluintegraatiota virallisen Mollie-lisäosan ohi

MobilePay käsitellään erillisessä tiketissä #156. Tuotantokäyttöönotto ja oikeat maksut käsitellään erillisessä tiketissä #157.

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

## Lähteet

- Mollie WooCommerce plugin: https://wordpress.org/plugins/mollie-payments-for-woocommerce/
- Mollie WooCommerce test/go-live: https://docs.mollie.com/docs/woo-test-and-go-live
- Mollie WooCommerce payment options: https://docs.mollie.com/docs/woo-set-up-payment-options
- Mollie Apple Pay activation: https://help.mollie.com/hc/en-us/articles/360022855933-How-do-I-activate-Apple-Pay
- Mollie Google Pay: https://docs.mollie.com/docs/google-pay
- Mollie Pay by Bank: https://docs.mollie.com/docs/pay-by-bank
