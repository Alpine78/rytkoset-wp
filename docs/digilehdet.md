# Digilehtien käyttöoikeus- ja hinnoittelumalli

Tämä dokumentti kuvaa tiketin `#199` (EPIC 9, `#202`) päätökset digilehtien
käyttöoikeuksista ja hinnoittelusta. Päätökset ohjaavat jatkototeutusta
tiketeissä `#200` (käyttöoikeustarkistus), `#381` (vuotoreittien sulkeminen) ja
`#201` (WooCommerce-tuotelinkitys ja jäsenhinnoittelu).

Tämä tiketti **ei toteuta koodia** — se lyö lukkoon mallin. Sisältö pysyy
`digital_magazine`-CPT:ssä (ks. [digital-magazines.md](digital-magazines.md));
maksaminen ja hinnat hoidetaan WooCommerce-tuotteilla. Digilehteen tallennetaan
vain käyttöoikeusmalli ja tarvittava linkitys maksutuotteisiin.

## Periaate

- Digilehden **sisältö** pysyy `digital_magazine`-CPT:ssä.
- **Jäsenyys, hinnat ja ostaminen** hoidetaan WooCommerce-/jäsenyyslogiikan kautta.
- Käyttöoikeusmalli ja tuotelinkitys ovat digilehden metatietoa, eivät erillistä tietokantataulua.

## Käyttöoikeustaso: lehti (yläkohde)

Käyttöoikeusmalli asetetaan **lehdelle** (yläkohde, jolla ei ole vanhempaa).
Saman lehden jutut (lapsikohteet) perivät lehden käyttöoikeusmallin. Jutuille ei
aseteta omaa käyttöoikeusmallia. Tämä pitää mallin yksinkertaisena: yksi lehti =
yksi käyttöoikeusmalli koko sisällölleen.

## Käyttöoikeusmallit

Ylläpidossa pitää pystyä valitsemaan lehdelle yksi neljästä mallista:

| Malli | Tunniste (suunniteltu) | Kuvaus |
| --- | --- | --- |
| **Kaikille ilmainen** | `free` | Kuka tahansa lukee ilman kirjautumista tai ostoa. |
| **Vain jäsenille** | `members_only` | Vain aktiivinen jäsen lukee, ilmaiseksi. Ei ostomahdollisuutta ei-jäsenelle. |
| **Jäsenhinta + normaalihinta** | `member_and_regular` | Kaikki ostavat; aktiivinen jäsen ohjataan jäsentuotteeseen (halvempi), muut normaalituotteeseen. |
| **Kaikille maksullinen** | `paid` | Kaikki ostavat saman tuotteen samaan hintaan; jäsenyys ei vaikuta. |

Toteutettu meta-avain on `_rytkoset_magazine_access_mode`. Kaikki julkisen
lukunäkymän tarkistukset kulkevat helperin
`rytkoset_theme_user_can_read_digital_magazine( $post_id, $user_id = null )`
kautta, jotta lehden pääsivu, jutut ja jatkotiketit käyttävät samaa sääntöä.

### Oletus nykyisille lehdille

Puuttuva käyttöoikeusmeta tulkitaan `free`-malliksi. Tämä säilyttää nykyiset
digilehdet luettavina myös silloin, kun niille ei ole vielä tallennettu uutta
käyttöoikeusmallia.

Toteutuksessa meta-arvo tallennetaan vain sallitusta arvolistasta. Jos
lukutarkistus kohtaa tuntemattoman tai virheellisen arvon, se käsitellään
korjattavana virhetilana eikä sitä käytetä jäsenhinnan tai rajatun sisällön
avaamiseen.

### Käyttäytyminen käyttäjästatuksen mukaan

| Malli | Kirjautumaton | Kirjautunut ei-jäsen | Aktiivinen jäsen |
| --- | --- | --- | --- |
| `free` | Lukee | Lukee | Lukee |
| `members_only` | Jäsenyyskehote (ei ostoa) | Jäsenyyskehote (ei ostoa) | Lukee ilmaiseksi |
| `member_and_regular` | Ostokehote normaalihintaan¹ | Ostokehote normaalihintaan¹ | Ostokehote jäsenhintaan¹ |
| `paid` | Ostokehote¹ | Ostokehote¹ | Ostokehote¹ |

¹ Lukuoikeus avautuu vasta hyväksytyn oston jälkeen (ks. *Lukuoikeuden avaavat tilausstatukset*).

### Päätökset malleittain

- **Kaikille ilmainen (`free`):** Kirjautumaton käyttäjä lukee koko lehden ja sen
  jutut ilman kirjautumista tai ostoa. Ei eroa nykyiseen digilehden näkymään.
- **Vain jäsenille (`members_only`):** Aktiivinen jäsen lukee ilman erillistä
  ostoa. Kirjautumattomalle ja kirjautuneelle ei-jäsenelle näytetään
  jäsenyys-/kirjautumiskehote. **Ei-jäsen ei voi ostaa tätä lehteä erikseen** —
  pääsy edellyttää aktiivista jäsenyyttä. Tämä pitää "vain jäsenille" selkeänä
  jäsenetuna eikä sekoita sitä `member_and_regular`-malliin.
- **Jäsenhinta + normaalihinta (`member_and_regular`):** Kaikki maksavat, mutta
  aktiivinen jäsen halvemmalla. Aktiiviselle jäsenelle näytetään jäsentuote,
  muille normaalituote. Lukuoikeus avautuu hyväksytystä ostosta.
- **Kaikille maksullinen (`paid`):** Sama tuote ja hinta kaikille; jäsenyys ei
  vaikuta. Lukuoikeus avautuu hyväksytystä ostosta.

## Jäsenstatuksen lähde

Jäsenyys on **käyttäjän metatieto**, ei WordPress-rooli (tiketti `#301`:
"Lisää WordPress-käyttäjälle manuaalisesti hallittava jäsenyystila").

Perustelu: WordPressin admin-roolivalikko asettaa vain yhden roolin kerrallaan ja
korvaa entisen, joten jäsenyyttä ei voi luotettavasti pitää roolina menettämättä
käyttäjän muuta roolia (`subscriber`/`customer`). Käyttäjä säilyttää normaalin
roolinsa, ja jäsenyys talletetaan erilliseen user-metaan. Tämä noudattaa
projektin linjaa (suosi user/post metaa, vältä custom-tauluja) eikä vaadi
roolipohjaista hinnoittelupluginia.

Digilehtien käyttöoikeustarkistus (`#200`) ja jäsenhinnoittelu (`#201`) käyttävät
`#301`:ssä toteutettavaa yhteistä jäsenstatuksen lähdettä ja sen helperiä (esim.
`rytkoset_theme_user_is_active_member()`).

`#200`:ssa ostotarkistus on tarkoituksella laajennuspiste:
`rytkoset_theme_user_has_purchased_digital_magazine()` palauttaa tässä vaiheessa
`false`, mutta tarjoaa suodattimen
`rytkoset_theme_user_has_purchased_digital_magazine`, johon `#201` kytkee
WooCommerce-tuotelinkityksen ja `wc_customer_bought_product()`-tarkistuksen.

### Jos jäsenstatusta ei voida varmistaa

Epäselvässä tilanteessa (kirjautumaton käyttäjä, helper ei palauta varmaa
"aktiivinen jäsen" -tulosta) käyttäjää **kohdellaan ei-jäsenenä** (fail closed):

- `members_only`-lehti näyttää jäsenyyskehotteen, ei sisältöä.
- `member_and_regular`-lehti ohjaa normaalihintaan.

Tämä estää sisällön vuotamisen ja jäsenhinnan myöntämisen väärin perustein.

## WooCommerce-hinnoittelumalli

**Päätös: kaksi erillistä tuotetta** jäsen- ja normaalihinnalle.

Maksullinen digilehti linkitetään WooCommerce-tuotteeseen (tai kahteen)
digilehden metatietona, samaan tapaan kuin tapahtuma linkitetään maksutuotteeseen
(`_rytkoset_event_product_id`, ks.
[woocommerce-event-product-link.md](woocommerce-event-product-link.md)).

Suunnitellut meta-avaimet (vahvistetaan `#201`:ssä):

| Malli | Jäsentuote | Normaalituote |
| --- | --- | --- |
| `member_and_regular` | `_rytkoset_magazine_member_product_id` | `_rytkoset_magazine_regular_product_id` |
| `paid` | — | `_rytkoset_magazine_regular_product_id` |
| `members_only` | — (ei tuotetta) | — (ei tuotetta) |
| `free` | — | — |

Perustelu erillisille tuotteille yhden tuotteen dynaamisen alennuksen sijaan:

- **Ei lisäpluginia eikä custom-hintalogiikkaa.** Yhden tuotteen jäsenalennus
  vaatisi roolipohjaisen hinnoittelupluginin tai oman hintafiltterin koriin ja
  kassalle. Jäsenyys ei myöskään ole rooli (ks. yllä), joten roolipohjainen
  hinnoittelu ei toimisi suoraan.
- **Sama todennettu malli kuin tapahtumilla.** Digilehti viittaa tuotteeseen
  meta-id:llä; tuote hoitaa hinnan, verot ja ostoprosessin.
- **Näkyvä hinta valitaan jäsenstatuksen mukaan**: aktiiviselle jäsenelle
  jäsentuote, muille normaalituote.

### Jäsenhintatuotteen osto-oikeus

Jäsenhintatuotteen ostaminen estetään ilman aktiivista jäsenyyttä. Pelkkä
jäsentuotteen piilottaminen julkisista listauksista ei riitä, koska tuotteen voi
muuten avata tai lisätä koriin suoralla URL:lla.

Toteutus tehdään tiketin `#201` ohjeen mukaan:

- `woocommerce_add_to_cart_validation` estää jäsenhintatuotteen lisäämisen koriin
  ilman aktiivista jäsenyyttä.
- `woocommerce_check_cart_items` varmistaa saman vielä ostoskorissa ja kassalla.
- Julkinen lukitun lehden CTA ohjaa tuotesivulle; se ei rakenna omaa
  add-to-cart-ohitusta.

## Ostaminen ja tili

**Päätös: tili pakotetaan kassalla, kun korissa on digilehtituote.**

Lukuoikeus ostosta tunnistetaan käytännössä
`wc_customer_bought_product( $email, $user_id, $product_id )` -tarkistuksella,
joka vaatii kirjautuneen käyttäjän (tai saman laskutussähköpostin). Jos vieras
ostaisi kirjautumatta, hän ei pääsisi lukemaan ostamaansa lehteä.

Siksi kun korissa on digilehtituote, kassalla vaaditaan kirjautuminen tai tilin
luonti (WooCommercen "luo tili" -asetus tai korivalidointi). Näin osto sidotaan
käyttäjätiliin ja lukuoikeus voidaan tarkistaa myöhemmin luotettavasti.

## Lukuoikeuden avaavat tilausstatukset

**Päätös: lukuoikeus avautuu vain `processing`- ja `completed`-tilauksista.**

Ei `on-hold`-tilasta. Mollie-tilisiirto voi olla `on-hold`-tilassa päiviä ennen
maksun kirjautumista, eikä sisältöä luovuteta ennen maksun varmistumista.
(Vertaa: tapahtumien järjestäjäilmoitukset lähetetään jo `on-hold`-tilasta, mutta
siinä ei luovuteta maksullista sisältöä.)

Tilisiirrolla maksanut ostaja ei siis pääse lukemaan maksullista digilehteä ennen
kuin maksu on kirjautunut ja tilaus siirtyy maksun vahvistavaan tilaan.

Ostettu lukuoikeus riippuu maksetusta tilauksesta, ei myöhemmästä
jäsenstatuksesta. Jos käyttäjä ostaa `member_and_regular`-lehden jäsenhintaan
aktiivisena jäsenenä ja tilaus on `processing`/`completed`, kyseinen osto avaa
lehden myös myöhemmin. Jäsenstatus vaikuttaa siihen, mitä hintaa käyttäjälle
tarjotaan ostovaiheessa.

## Näkyvyys arkistossa, haussa ja sisällysluettelossa

**Päätös: rajatut lehdet näkyvät otsikko- ja kansikuvatasolla, sisältö ei vuoda.**

- Rajatut lehdet (`members_only`, `member_and_regular`, `paid`) **näkyvät**
  arkistossa (`/digilehdet/`) ja haussa otsikon ja kansikuvan tasolla
  lukko-/hintamerkinnällä. Tämä toimii samalla myynnin ja jäsenedun esittelynä.
- Ei-oikeutettu käyttäjä näkee lehden pääsivulla **sisällysluettelon teaserina**:
  juttujen otsikot näkyvät, jotta lehden sisältöä voi arvioida, mutta varsinainen
  juttusisältö ei avaudu ilman lukuoikeutta. Toteutuksessa voidaan näyttää otsikot
  joko lukituille juttusivuille vievinä linkkeinä tai lukittuina otsikkoriveinä,
  kunhan yksittäisen jutun suora URL käyttää samaa lukuoikeustarkistusta.
- Juttujen varsinainen sisältö **ei ole luettavissa** ilman lukuoikeutta, ei
  myöskään REST-rajapinnan, syötteiden, haun otteiden eikä oEmbedin kautta. Tämä
  linjaa tiketin `#381` haku-/syöte-/REST-suodatuksen: rajatun lehden ja sen
  juttujen sisältö suodatetaan pois ei-oikeutetuilta reiteiltä, mutta otsikko- ja
  kansitaso arkistossa säilyy.
- Sitemapissa URL:ien näkyminen on hyväksyttävää, koska sitemap ei sisällä
  varsinaista digilehden sisältöä. Tämä tarkistetaan ja dokumentoidaan tarkemmin
  tiketin `#381` yhteydessä.

## Rajaus

Tämä epic / tiketti ei kata:

- PDF-jakelua
- koko WooCommerce-kaupan hinnoittelumallin uudistusta
- automaattista kaikkien vanhojen lehtien tuotteistusta
- erillisiä tietokantatauluja
- paperisen jäsenrekisterin massatuontia
- jäsenyyden automaattipäivitystä jäsenmaksutilauksesta (`#302`, myöhempi jatko)

## Toteutusjärjestys (EPIC 9)

1. `#199` (tämä dokumentti) — päätökset
2. `#301` — jäsenstatuksen lähde + helper
3. `#200` — käyttöoikeustarkistus templateissa
4. `#381` — vuotoreittien sulkeminen (heti `#200`:n perään tai samassa PR:ssä)
5. `#201` — tuotelinkitys ja jäsenhinnoittelu
6. `#302` — jäsenyyden automaattipäivitys (myöhempi jatko)

## Testauksen vähimmäisvaatimus

Ratkaisu testataan vähintään yhdellä lehdellä kustakin keskeisestä mallista:

- yksi **kaikille ilmainen** (luettavissa kirjautumatta),
- yksi **vain jäsenille** (aktiivinen jäsen lukee, ei-jäsen saa kehotteen),
- yksi **jäsenhinta + normaalihinta** (ei-jäsen saa normaalihinnan, aktiivinen
  jäsen jäsenhinnan, jäsenhintatuotetta ei voi ostaa ilman jäsenyyttä),
- yksi **kaikille maksullinen** (osto avaa lukuoikeuden
  `processing`/`completed`-tilassa).
