# WooCommerce: Mollie MobilePay -käyttöönotto

> **Lepäävä palautusohje (14.7.2026).** Paytrail on käytössä kokeilujakson ajan
> (#530). Mollien MobilePay-ohje säilytetään mahdollista Mollieen palaamista
> varten, mutta se ei kuvaa nykyistä maksupalvelua.

Tämä dokumentti kuvaa `#156`-tiketin MobilePay-käyttöönottomallin.

MobilePay otetaan käyttöön vain virallisen `Mollie Payments for WooCommerce` -lisäosan kautta. Oma MobilePay-integraatio, erillinen API-toteutus tai WooPaymentsin käyttöönotto eivät kuulu tähän ratkaisuun.

## Rajaus

Tässä vaiheessa tehdään:

- MobilePayn saatavuuden tarkistus Mollie Dashboardissa
- MobilePayn aktivointi Molliessa, jos tilin ehdot täyttyvät
- MobilePayn aktivointi WooCommercen Mollie-asetuksissa, jos gateway on saatavilla
- pieni dev-live hyväksymistesti, jos maksutapa on aktivoitavissa
- blocker-dokumentointi, jos MobilePay ei ole vielä käytettävissä

Tässä vaiheessa ei tehdä:

- omaa MobilePay API -integraatiota
- WooPaymentsin käyttöönottoa
- MobilePayn kiertototeutusta maksutavan ohi
- salaisuuksien, API-avainten tai MobilePay Merchant ID:n tallentamista repoon

## Mollien vaatimukset

Mollien dokumentaation mukaan MobilePay on tällä hetkellä beta-maksutapa. Aktivointi voi vaatia yhteydenoton Mollien account manageriin tai supportiin.

MobilePayn käyttöönottoa varten pitää varmistaa vähintään:

- yhdistyksen Mollie-tili on suomalainen tai tanskalainen
- valuuttana on `EUR` tai `DKK`
- MobilePay on aktivoitavissa Mollie Dashboardissa
- yhdistyksellä on tarvittava MobilePay-sopimus, jos Mollie sitä edellyttää
- MobilePay Merchant ID / MSN voidaan lisätä Mollie Dashboardiin, jos sitä pyydetään
- WooCommerce käyttää virallista Mollie-lisäosaa tai Mollien Payments API:a

Jos jokin näistä puuttuu, MobilePayta ei oteta käyttöön kiertoteitse. Este kirjataan tämän dokumentin lopussa olevaan testitulostaulukkoon ja tiketti voidaan sulkea dokumentoituna blockerina.

## Aktivointi devissä

Tee nämä vaiheet `dev.rytkoset.net`-ympäristössä:

1. Avaa Mollie Dashboard.
2. Tarkista, näkyykö `MobilePay` maksutapana.
3. Jos MobilePay on aktivoitavissa, aktivoi se Mollie Dashboardissa.
4. Lisää mahdollinen MobilePay Merchant ID / MSN vain Mollie Dashboardiin.
5. Avaa WordPressissä `WooCommerce -> Settings -> Mollie Settings`.
6. Tarkista, näkyykö MobilePay omana Mollie-maksutapana.
7. Ota MobilePay käyttöön.
8. Rajaa `Sell to specific countries` tarvittaessa Suomeen.
9. Tallenna asetukset.
10. Varmista kassalla, että MobilePay näkyy vain silloin, kun se on teknisesti saatavilla.

`Tilisiirto`, korttimaksut ja `Pay by Bank` pidetään käytössä kuten #157-vaiheessa. WooPaymentsin maksutapoja ei oteta käyttöön.

## Hyväksymistestaus

Testaa devissä matala-arvoisella testituotteella:

1. Lisää ostoskoriin `Mollie live testituote` tai muu matala-arvoinen testituote.
2. Siirry kassalle.
3. Valitse `MobilePay`, jos se näkyy.
4. Tee onnistunut pieni live-maksu.
5. Varmista, että asiakas palaa WooCommercen tilausvahvistukseen.
6. Varmista WooCommerce-adminissa, että tilauksen maksutapa on MobilePay ja tila päivittyy oikein.
7. Tee erillinen peruttu tai keskeytetty MobilePay-maksu.
8. Varmista, ettei peruttu maksu päädy virheellisesti maksetuksi.
9. Testaa tarvittaessa hyvitys pienelle MobilePay-maksulle, jos Mollie näyttää refundin tuetuksi.

## Capture-huomio

Mollien MobilePay-dokumentaatio huomauttaa, että Norjassa, Tanskassa ja Suomessa maksua ei tule capture-käsitellä ennen kuin tuote tai palvelu on toimitettu asiakkaalle.

Tämä pitää huomioida ennen kuin MobilePay näytetään oikeille asiakkaille esimerkiksi:

- Tampere 2026 -osallistumismaksuissa
- ennakkotilattavissa fyysisissä tuotteissa
- muissa tuotteissa, joissa toimitus tai palvelu tapahtuu myöhemmin

Jos MobilePayn käyttö edellyttää manual capture -mallia, se pitää vahvistaa Mollien dokumentaatiosta ja testata erikseen ennen julkaisua. Tätä ei kierretä omalla koodilla tässä tiketissä.

## Testitulosten kirjaus

Täytä tämä taulukko, kun MobilePayn tila on tarkistettu:

| Tarkistus | Tulos | Huomio |
| --- | --- | --- |
| MobilePay näkyy Mollie Dashboardissa | Pending |  |
| MobilePay aktivoitu Molliessa | Pending |  |
| MobilePay näkyy WooCommerce Mollie -asetuksissa | Pending |  |
| MobilePay näkyy kassalla | Pending |  |
| Onnistunut MobilePay-testimaksu | Pending |  |
| Peruttu MobilePay-testi | Pending |  |
| Tilauksen status päivittyy oikein | Pending |  |
| Kortti, Pay by Bank ja Tilisiirto toimivat edelleen | Pending |  |
| Mahdollinen blocker | Pending |  |

Merkitse `Pending`-arvojen tilalle toteutunut tulos. Jos MobilePay ei ole käytettävissä, kirjaa tarkka este ja seuraava toimenpide, esimerkiksi yhteydenotto Mollie Supportiin tai MobilePay Merchant ID:n hankinta.

## Lähteet

- Mollie MobilePay  
  https://docs.mollie.com/docs/mobilepay
- Mollie WooCommerce: Set up your checkout  
  https://docs.mollie.com/docs/woo-set-up-your-checkout
- Mollie WooCommerce: Set up payment options  
  https://docs.mollie.com/docs/woo-set-up-payment-options
