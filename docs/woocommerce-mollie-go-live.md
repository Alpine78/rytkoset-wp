# WooCommerce: Mollie dev-live käyttöönotto ja hyväksymistestaus

Tämä dokumentti kuvaa `#157`-tiketin käyttöönotto- ja hyväksymistestausmallin.

## Rajaus

Tässä vaiheessa käyttöönotto tehdään ensisijaisesti osoitteessa `dev.rytkoset.net`.

Tämä dokumentti ei tarkoita vielä lopullista `rytkoset.net`-cutoveria, koska tuotantodomain on edelleen vanhassa Joomla-ympäristössä.

Tässä vaiheessa ei toteuteta:

- `MobilePay`-käyttöönottoa
- lopullista tuotantodomainin Apple Pay -validaatiota
- kirjanpitointegraatioita
- omia maksulogiikoita virallisen Mollie-lisäosan ohi

`MobilePay` kuuluu erilliseen tikettiin `#156`.

## Lähtötila

Tämän session aikana varmistettiin:

- `dev.rytkoset.net` on julkisesti saavutettavissa HTTPS:llä
- julkinen kauppasivu `https://dev.rytkoset.net/kauppa/` näyttää edelleen WooCommercen `coming soon` -näkymän
- `https://dev.rytkoset.net/wp-admin/` ohjautuu tässä sessiossa WordPressin kirjautumissivulle
- tästä sessiosta ei ole käytössä dev-ympäristön WordPress-admin-kirjautumista
- tästä sessiosta ei ole käytössä Mollien live-API-avaimia

Näistä syistä varsinainen live-avainten vaihto, maksutapojen aktivointi, oikeat maksut ja refund-testit eivät ole toteutettavissa pelkästään repoa muokkaamalla.

## Ennen käyttöönottoa

Varmista ennen dev-liveen siirtymistä:

- Mollien `website profile` on hyväksytty live-maksuja varten
- dev-sivuston WooCommerce-kauppa on oikeasti julkisesti käytettävissä eikä `coming soon` estä kassaa
- `Mollie Payments for WooCommerce` on jo asennettu ja toimiva testimoodissa
- WooPayments ei ole käytössä rinnakkaisena verkkomaksuna
- `Tilisiirto` on edelleen käytössä fallback-maksutapana

Mollien virallisen WooCommerce-ohjeen mukaan liveen siirtyminen tapahtuu lisäämällä live-API-avain ja vaihtamalla `Mollie Payment Mode` arvoon `Live API`.

## Dev-live käyttöönotto

Tee nämä vaiheet WordPress-adminissa dev-ympäristössä:

1. Avaa `WooCommerce -> Settings -> Mollie Settings`.
2. Lisää `Live API key` vain adminiin.
3. Vaihda `Mollie Payment Mode` arvoon `Live API`.
4. Tallenna muutokset.
5. Aktivoi Mollie Dashboardissa ja WooCommerce-gatewayissa vähintään:
   - `Pay by Bank`
   - korttimaksut
   - `Google Pay`, jos se on käytettävissä tilillä ja tuetussa selaimessa
   - `Apple Pay`, vain jos dev-domain voidaan validoida ja testilaite/selain tukee sitä
6. Varmista, että `Tilisiirto` jää näkyviin fallbackiksi.
7. Varmista, ettei WooPaymentsin maksutapoja näy kassalla.

## Live-testituote

Hyväksymistestejä varten käytä erillistä matala-arvoista tuotetta devissä:

- nimi: `Mollie live testituote`
- hinta: `1,00 €`
- tyyppi: `Simple product`
- `Virtual`: kyllä
- `Downloadable`: ei
- näkyvyys rajataan testikäyttöön

Tuotteen tarkoitus on erottaa hyväksymistestit jäsenmaksu- ja tapahtumatuotteista.

## Hyväksymistestaus

Tee vähintään seuraavat oikeat live-testit:

1. `Pay by Bank` onnistunut maksu
2. korttimaksu tai `Google Pay` onnistunut maksu
3. peruttu tai keskeytetty maksu
4. vähintään yksi refund WooCommerce-administa tai Mollien hallinnasta

Varmista jokaisessa testissä:

- tilausnumero syntyy oikein
- maksutapa tallentuu oikein
- asiakas palaa maksun jälkeen oikeaan vahvistus- tai virhenäkymään
- webhook päivittää WooCommerce-tilauksen tilan oikein
- epäonnistunut tai peruttu maksu ei jää virheellisesti maksetuksi
- refund näkyy oikein sekä WooCommercessa että Molliessa

## Testitulosten kirjausmalli

Kirjaa devissä vähintään nämä tiedot:

| Testi | Maksutapa | Tulos | WooCommerce-tilaus | Mollie payment ID | Huomio |
| --- | --- | --- | --- | --- | --- |
| Onnistunut maksu 1 | Pay by Bank | Pending |  |  |  |
| Onnistunut maksu 2 | Kortti / Google Pay | Pending |  |  |  |
| Peruttu maksu | Valittu maksutapa | Pending |  |  |  |
| Refund | Alkuperäinen maksutapa | Pending |  |  |  |

Merkitse `Pending`-arvon tilalle toteutunut tulos vasta oikean testin jälkeen.

## Toteutuneet dev-live testit 23.4.2026

Devissä toteutettiin oikeita live-maksutestejä ja varmistettiin seuraavat:

- Mollien live-API on käytössä devissä.
- Mollien payout-tili on lisätty ja vahvistettu yhdistyksen yritystilille.
- `Pay by Bank` onnistui oikealla maksulla.
- Korttimaksu onnistui oikealla maksulla.
- Korttimaksulle tehtiin hyvitys onnistuneesti.
- `Apple Pay` näkyi käytettävissä pankkitilin vahvistuksen jälkeen ja vaikutti toimivalta, mutta ensimmäisestä epäonnistuneesta yrityksestä ei jäänyt talteen tarkkaa virheilmoitusta.
- `Tilisiirto` jäi fallback-maksutavaksi.

Varmistetut havainnot:

- onnistunut `Pay by Bank` -tilaus: `#821`
- onnistunut korttitilaus: tehty devissä
- refund korttitilaukselle: tehty devissä

## SEPA-tilisiirron huomio

Dev-testissä havaittiin, että Mollien SEPA-tilisiirron RF-viite oli aluksi näytetty käyttäjälle väliviivoilla ryhmiteltynä, esimerkiksi muodossa `RF42-2671-9596-2636`.

Vaikka RF-viite oli teknisesti validi, ainakin OP ja POP Pankki hylkäsivät tämän muodon maksun viitekentässä.

Tämän vuoksi teemaan lisättiin rajattu korjaus, joka normalisoi Mollien maksuojeissa näkyvät RF-viitteet muotoon ilman väliviivoja ja välilyöntejä, esimerkiksi:

- `RF42267195962636`

Korjaus koskee:

- kiitossivun Mollie-ohjeita
- tilaussivun maksuojeita
- Mollien lähettämiä WooCommerce-sähköposteja
- tallennettua `_mollie_payment_instructions`-order-metaa

IBAN ryhmitellään edelleen luettavuuden vuoksi normaalisti. Vain RF-viite näytetään pankkien syöttökenttiä varten puhtaana aakkosnumeerisena merkkijonona.

## Apple Pay ja Google Pay

Mollien virallisen dokumentaation mukaan:

- `Apple Pay Direct` toimii vain tuotantoympäristössä, test- tai live-tilassa, Apple-laitteella tai Safarissa
- Apple Payn näyttäminen edellyttää, että verkkokaupan palvelimella on Apple validation file
- `Google Pay` voi näkyä Mollien Hosted Checkout -polussa ilman että se näkyy erillisenä gatewayna WooCommercessa

Jos `Apple Pay` tai `Google Pay` ei ole testattavissa devissä tilin, laitteen, selaimen tai domain-validaation vuoksi, este dokumentoidaan eikä sitä kierretä tässä tiketissä omalla teknisellä ratkaisulla.

## Lopullinen tuotantocutover myöhemmin

Kun WordPress siirretään osoitteeseen `rytkoset.net`, tee erillinen viimeinen cutover-checklist:

- lisää oikeat live-avaimet tuotantoon
- varmista webhookit tuotantodomainille
- tee Apple Payn lopullinen domain-validointi tuotannossa
- tee vähintään yksi oikea tuotantomaksu pienellä summalla
- varmista refund-polku tuotannossa
- pidä `Tilisiirto` fallbackina käyttöönoton ajan

## Lähteet

- Mollie WooCommerce: Test and go live  
  https://docs.mollie.com/docs/woo-test-and-go-live
- Mollie WooCommerce: Set up payment options  
  https://docs.mollie.com/docs/woo-set-up-payment-options
- Mollie go-live checklist  
  https://docs.mollie.com/docs/go-live-checklist
