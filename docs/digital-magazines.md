# Digilehdet

Digilehdet ovat WordPressissä HTML-sisältöä. Niitä ei julkaista PDF-tiedostoina.

Tämä dokumentti kuvaa digilehtien sisältömallin, käyttöoikeudet, hinnoittelun
ja ylläpidon. Sisältö pysyy `digital_magazine`-sisältötyypissä; jäsenyys,
hinnat ja ostaminen ratkaistaan käyttäjämetan ja WooCommercen kautta ilman
erillisiä tietokantatauluja.

## Käyttöoikeusmallit

Käyttöoikeus asetetaan lehden yläkohteelle. Kaikki saman lehden jutut perivät
sen, eikä jutuille aseteta erillistä käyttöoikeusmallia.

| Malli | Tunniste | Käyttäytyminen |
| --- | --- | --- |
| Kaikille ilmainen | `free` | Kuka tahansa lukee ilman kirjautumista tai ostoa. |
| Vain jäsenille | `members_only` | Aktiivinen jäsen lukee ilmaiseksi; muille näytetään jäsenyys- tai kirjautumiskehote. Ei erillistä ostomahdollisuutta. |
| Jäsenhinta + normaalihinta | `member_and_regular` | Kaikki ostavat; aktiiviselle jäsenelle tarjotaan jäsenhintatuote ja muille normaalihintatuote. |
| Kaikille maksullinen | `paid` | Kaikki ostavat saman normaalihintatuotteen. |

Puuttuva käyttöoikeusmeta tulkitaan `free`-malliksi, jotta vanhat lehdet eivät
lukitu päivityksessä. Tuntematon arvo normalisoidaan turvallisesti
`members_only`-malliksi, joten virheellinen meta ei avaa sisältöä julkiseksi.

Jäsenstatus tulee käyttäjän metatiedosta ja yhteisestä
`rytkoset_theme_user_is_active_member()`-helperistä, ei WordPress-roolista.
Epävarma jäsenstatus käsitellään ei-jäsenenä: rajattu sisältö ei avaudu ja
`member_and_regular`-mallissa käyttäjälle tarjotaan normaalihintatuote.

| Malli | Kirjautumaton | Kirjautunut ei-jäsen | Aktiivinen jäsen |
| --- | --- | --- | --- |
| `free` | Lukee | Lukee | Lukee |
| `members_only` | Kehote | Kehote | Lukee |
| `member_and_regular` | Normaalihintainen ostokehote | Normaalihintainen ostokehote | Jäsenhintainen ostokehote |
| `paid` | Ostokehote | Ostokehote | Ostokehote |

Maksullisen lehden aiempi hyväksytty osto avaa lehden riippumatta käyttäjän
myöhemmästä jäsenstatuksesta. Jäsenstatus vaikuttaa tarjottavaan tuotteeseen
ostovaiheessa, ei jo ostetun lukuoikeuden säilymiseen.

## Sisältömalli

Digilehti käyttää yhtä hierarkkista sisältötyyppiä:

- Post type: `digital_magazine`
- Julkinen arkisto: `/digilehdet/`
- Lehti: yläkohde, jolla ei ole vanhempaa
- Juttu: saman sisältötyypin alakohde, jonka vanhemmaksi valitaan lehti

URL-rakenne:

- lehti: `/digilehdet/{lehden-polku}/`
- juttu: `/digilehdet/{lehden-polku}/{jutun-polku}/`

Sisällysluetteloa ei tallenneta erilliseen kenttään. Lehden sivu muodostaa sisällysluettelon automaattisesti julkaistuista lapsijutuista.

## Lehden luominen

1. Avaa WordPress-adminissa `Digilehdet`.
2. Valitse `Lisää uusi`.
3. Kirjoita lehden nimi otsikoksi.
4. Jätä vanhempi tyhjäksi.
5. Kirjoita lehden johdanto tai kuvaus editoriin.
6. Lisää halutessasi ote ja artikkelikuva.
7. Valitse **Käyttöoikeus**-laatikosta lehden käyttöoikeusmalli.
8. Julkaise lehti.

Jos käyttöoikeus jätetään oletukseen, lehti on kaikille ilmainen. Käyttöoikeus
asetetaan vain lehdelle; lehden jutut perivät emolehden käyttöoikeusmallin.

## Jutun lisääminen lehteen

1. Avaa `Digilehdet > Lisää uusi`.
2. Kirjoita jutun otsikko.
3. Valitse sivupalkissa vanhemmaksi oikea lehti.
4. Kirjoita jutun varsinainen sisältö editoriin.
5. Aseta `Järjestys` / `Menu order` -kenttään numero, jos haluat määrittää sisällysluettelon järjestyksen.
6. Julkaise juttu.

Suositeltu järjestysnumerointi on esimerkiksi `10`, `20`, `30`. Silloin väliin voi lisätä myöhemmin uusia juttuja ilman kaikkien numeroiden muuttamista.

## Julkinen näkymä

Lehden sivulla näkyy:

- lehden otsikko
- lehden kuvaus tai johdanto
- automaattinen sisällysluettelo

Jos lehti on rajattu eikä käyttäjällä ole lukuoikeutta, sivu näyttää otsikon,
kansikuvan, lukuoikeuskehotteen ja sisällysluettelon otsikkotasolla. Varsinainen
lehden tai jutun sisältö ei näy.

Jutun sivulla näkyy:

- linkki takaisin lehteen
- jutun otsikko ja sisältö
- edellinen ja seuraava juttu samassa lehdessä, jos niitä on

Jos käyttäjällä ei ole emolehden lukuoikeutta, jutun suora URL näyttää jutun
otsikon ja lukuoikeuskehotteen sisällön sijaan.

Ensimmäisellä jutulla ei näytetä edellinen-linkkiä. Viimeisellä jutulla ei näytetä seuraava-linkkiä.

## Maksullinen digilehti: WooCommerce-tuotteet (#201)

`paid`- ja `member_and_regular`-lehdet linkitetään WooCommerce-tuotteeseen, joka
hoitaa maksamisen. Sisältö pysyy digilehdessä — tuote on vain maksuväline.

**Kaksi erillistä tuotetta** (#199):

| Lehden malli | Normaalihintatuote | Jäsenhintatuote |
| --- | --- | --- |
| Kaikille maksullinen (`paid`) | pakollinen | — |
| Jäsenhinta + normaalihinta (`member_and_regular`) | pakollinen | valinnainen |
| Vain jäsenille / Kaikille ilmainen | — | — |

### Tuotteen luonti

1. Luo **Tuotteet > Lisää uusi** -näkymässä tuote lehden hintaa varten.
2. Merkitse tuote **virtuaaliseksi** (Tuotetiedot → rasti *Virtuaalinen*): ei
   toimitusta. **Älä** lisää ladattavaa tiedostoa — sisältö luetaan sivustolta,
   ei PDF-liitteenä.
3. Aseta hinta ja julkaise tuote.
4. `member_and_regular`-lehdelle luo erikseen normaalihinta- ja
   jäsenhintatuotteet (kaksi tuotetta, eri hinnat).

### Tuotteen linkitys lehteen

1. Avaa lehti (ylälehti) ja etsi **Maksutuotteet**-laatikko.
2. Valitse **Normaalihintatuote** ja tarvittaessa **Jäsenhintatuote**. Vain
   julkaistut tuotteet kelpaavat.
3. Tallenna lehti.

Lukitun lehden ostokehote ohjaa oikeaan tuotteeseen käyttäjän jäsenstatuksen
mukaan: aktiivinen jäsen näkee jäsenhintatuotteen, muut normaalihintatuotteen.
Jos jäsenstatusta ei voida varmistaa (kirjautumaton), ohjataan normaalihintaan.

### Ostaminen ja lukuoikeus

- **Vain jäsen voi ostaa jäsenhintatuotteen.** Tuotteen lisääminen koriin ja
  kassalla eteneminen estyy ilman aktiivista jäsenyyttä.
- Lukuoikeus avautuu, kun ostajan tilauksessa on lehteen linkitetty tuote ja
  tilaus on **valmis** (`processing`/`completed`). `on-hold`-tilisiirto ei vielä
  avaa sisältöä.
- Pääsy on tilikohtainen. Kun korissa on lehteen linkitetty tuote, kassa vaatii
  kirjautumisen tai luo ostajalle käyttäjätilin automaattisesti. Kassan alussa
  näytetään tästä suomenkielinen ohje. Tavallisten tuotteiden vieraskassa seuraa
  edelleen WooCommercen omaa asetusta.
- Tilipakko toteutetaan WooCommercen
  `woocommerce_checkout_registration_enabled`- ja
  `woocommerce_checkout_registration_required`-suodattimilla vain
  digilehtikorille. WooCommerce luo asiakkaan ennen klassisen kassan tilausta ja
  Checkout Blockissa ennen maksua. Lisäksi
  `woocommerce_checkout_validate_order_before_payment` estää Store API:ssa
  digilehtitilauksen maksamisen, jos tilaukselta silti puuttuu käyttäjä-ID
  esimerkiksi muokatun suoran API-pyynnön vuoksi (#558).
- Ostaja saa lukuoikeudesta saman sähköposti-ilmoituksen kuin manuaalisesta
  myönnöstä (#420), kerran tilausta ja lehteä kohti.

> **Synkronointi:** lehden ja tuotteen linkitys (`_rytkoset_magazine_*_product_id`)
> tallennetaan lehden metatietoon ja viittaa tuotteen ID:hen. Tuote-ID:t ovat eri
> local- ja dev-ympäristössä, joten linkitys **ei** siirry tuotesynkronointi-
> työkalulla — aseta tuotteet jokaisessa ympäristössä erikseen.

### Peruuttamisoikeuden menettämisen suostumus kassalla (#477)

Kuluttajansuojalaki (38/1978) 6:15 § 2 mom ja 6:24 § 2 mom edellyttävät
digitaalisen sisällön kohdalla kuluttajan **nimenomaista ennakkosuostumusta**
sisällön välittömään toimitukseen ja **hyväksyntää** siitä, että
peruuttamisoikeus päättyy toimituksen alkaessa. Ilman tätä digilehden ostaja
säilyttää oletusarvoisen 14 vrk:n peruuttamisoikeuden riippumatta siitä, onko
lukuoikeus jo myönnetty (ks. `docs/maksu-ja-toimitusehdot.md`, "Digitaaliset
tuotteet").

Kassalle (WooCommerce Block Checkout) on lisätty pakollinen valintaruutu
(`inc/woocommerce-digital-magazine.php`), joka näkyy vain, kun ostoskorissa on
vähintään yksi digilehteen linkitetty tuote (normaali- tai jäsenhintatuote):

- **Kenttä:** `rytkoset/digital_magazine_cancellation_consent`, sijainti
  `order` (näkyy checkoutin "Tilauksen lisätiedot" -osiossa, samoin kuin
  uutiskirjetilaus).
- **Näkyvyys ja pakollisuus:** teema julkaisee Store API:n
  `cart.extensions.rytkoset_digital_magazine.consent_required`-kentän
  (`rytkoset_theme_cart_has_digital_magazine_product()`); kenttä on piilotettu
  ja vapaaehtoinen, kun arvo on epätosi, ja pakollinen (rasti on valittava
  ennen tilauksen lähettämistä) kun arvo on tosi. Sama malli kuin Tampere
  2026- ja jäsenmaksukenttien ehdollisuudessa.
- **Tallennus:** WooCommerce Blocksin lisäkenttärajapinta tallentaa arvon
  tilaukselle (`_wc_other/rytkoset/digital_magazine_cancellation_consent`) —
  näkyy siis tilauksella todisteena annetusta suostumuksesta.
  `rytkoset_theme_order_has_digital_magazine_cancellation_consent( $order )`
  lukee arvon takaisin.
- **Rajaus:** tämä tiketti lisää vain suostumuksen keräämisen ja tallennuksen.
  Itsepalvelutilauksen peruutuspainike (`inc/woocommerce-cancellation.php`,
  `docs/woocommerce-peruutus.md`) ei toistaiseksi tarkista tätä suostumusta —
  maksettu digilehtitilaus ohjautuu joka tapauksessa manuaaliseen käsittelyyn
  samoin kuin muutkin `processing`-tilaukset, jolloin ylläpitäjä voi tarkistaa
  suostumuksen tilaukselta ennen palautuspäätöstä. Suostumuksen kytkeminen
  automaattisesti peruutuksen estävään logiikkaan on mahdollinen jatkoaskel.

Todennettu paikallisesti selaimella (Playwright, Block Checkout): kenttä näkyy
ja on pakollinen, kun korissa on digilehtituote (tyhjänä lähetys pysäytyy
WooCommerce Blocksin omalla "Valitse tämä ruutu jatkaaksesi eteen päin"
-virheellä); kenttä puuttuu kokonaan, kun korissa ei ole digilehteä; rastittu
arvo tallentuu tilaukselle ja on luettavissa takaisin. #558:n varmennuksessa
kirjautumaton digilehtiostos loi käyttäjätilin ja tilaus sai saman käyttäjä-ID:n,
kun taas tavallinen tuote säilyi vieraskassana. Tilivaatimuksen ohje näkyi myös
390 px leveässä näkymässä ilman vaakavieritystä.

**Vuotokorjaus muihin tilauksiin (#491):** WooCommerce Blocks tallentaa
piilotetun, rastittamattoman lisäkentän arvoksi `false` **jokaiselle**
tilaukselle riippumatta siitä, sisälsikö tilaus digilehteä — tämä näkyi mm.
Tampere 2026 -osallistumismaksun tilauksissa harhaanjohtavana "Haluan
digilehden lukuoikeuden…: Ei" -rivinä, vaikka tilauksessa ei ollut lainkaan
digilehteä. Korjattu samalla kolmen vartijan mallilla kuin Tampere 2026
-osallistujakentät (`inc/woocommerce-tampere-2026.php`):
`rytkoset_theme_order_has_digital_magazine_product( $order )` tarkistaa onko
tilauksella digilehteen linkitetty tuote, ja tätä käyttävät
`woocommerce_filter_fields_for_order_confirmation` (piilottaa vahvistuksesta ja
sähköpostista), `woocommerce_admin_shipping_fields` (piilottaa admin-tilaus­
näkymästä — kattaa myös ennen korjausta syntyneet tilaukset) sekä
`woocommerce_store_api_checkout_order_processed` (siivoaa turhan
`false`-metan uusilta tilauksilta).

## Käyttöoikeuden myöntäminen manuaalisesti (#420)

Osa digilehdistä myydään verkkokaupan ulkopuolella (käteinen, tilaisuudet,
postimyynti). Näissä tapauksissa ylläpitäjä voi avata `paid`- tai
`member_and_regular`-lehden suojatun sisällön rekisteröidylle käyttäjälle ilman
verkkokauppaostoa.

1. Avaa **Käyttäjät** ja muokkaa käyttäjää (vaatii `edit_users`-oikeuden).
2. Vieritä **Digilehtien käyttöoikeudet** -osioon. Listassa näkyvät vain
   maksulliset ja jäsenhinta + normaalihinta -lehdet — ilmaisia ja vain
   jäsenille -lehtiä ei myönnetä tässä, koska ne avautuvat muilla säännöillä.
3. Rastita lehdet, joihin käyttäjällä on pääsy, ja tallenna.

Pääsystä:

- **Pysyvä:** myönnetty pääsy ei vanhene. Rastin poisto peruu pääsyn.
- **Tilikohtainen:** sisältö avautuu vain, kun käyttäjä on kirjautuneena samalle
  tilille. Pääsyä ei voi myöntää sähköpostiosoitteelle ilman käyttäjätiliä.
- **Sähköposti-ilmoitus:** käyttäjä saa uudesta myönnöstä sähköpostin, jossa on
  linkki lehteen ja muistutus kirjautumisesta. Ilmoitus lähtee vain uudesta
  myönnöstä — ei uudelleentallennuksesta eikä pääsyn poistosta.

Manuaaliset myönnöt tallennetaan käyttäjän metatietoon (`rytkoset_magazine_access`).
Verkkokauppaostoon perustuva pääsy (#201) tarkistetaan erikseen tilauksista, eikä
sitä näytetä tässä listassa myöntörastina.

## Sisällön suojaus muilla reiteillä (#381)

Templatetason lukutarkistus (`#200`) suojaa vain lehden ja jutun varsinaiset
sivut. `digital_magazine` on rekisteröity julkiseksi CPT:ksi (`public => true`,
`show_in_rest => true`, `has_archive => true`), joten WordPress avaa sisällölle
muitakin reittejä. Rajatun lehden ja sen juttujen sisältö suodatetaan pois myös
näistä, jotta sisältö ei vuoda templaten ohi.

Kaikki tarkistukset kulkevat saman lukuoikeus-helperin
`rytkoset_theme_user_can_read_digital_magazine()` kautta (jutut normalisoidaan
emolehteen). Jaettu apufunktio `rytkoset_theme_should_hide_digital_magazine_content()`
palauttaa `true`, kun kyseessä on digilehti/juttu eikä katsojalla ole lukuoikeutta.
Lehden ylläpitäjä (`edit_post`), aktiivinen jäsen ja ostaja säilyttävät sisällön.

| Reitti | Suoja | Toteutus (`inc/digital-magazines.php`, ellei muuta mainita) |
| --- | --- | --- |
| **REST** `…/wp/v2/digital_magazine` | `content`- ja `excerpt`-kentät tyhjennetään ei-oikeutetulle. `show_in_rest` pysyy päällä, joten lohkoeditori toimii muokkausoikeudellisille. | `rest_prepare_digital_magazine` |
| **Syötteet** (`/digilehdet/feed/`, `?post_type=digital_magazine&feed=rss2`) | Otesyötteen ote ja koko sisällön syötteen runko korvataan lukuoikeuskehotteella. | `get_the_excerpt` (otesyöte) + `the_content_feed` (koko sisältö) |
| **WP-haku** (`search.php`) | Hakutulosote ei generoidu rajatusta sisällöstä, vaan näyttää kehotteen. Otsikko ja URL näkyvät edelleen. | `get_the_excerpt` |
| **oEmbed / `/embed/`** | Embed-näkymän ote korvataan kehotteella; oEmbed-JSON ei muutenkaan sisällä sisältöä (vain otsikko, kansikuva ja iframe). | `get_the_excerpt` (kautta `the_excerpt_embed`) |
| **Open Graph / Twitter** | `og:description` / `twitter:description` ei putoa enää `post_content`-otteeseen rajatulle lehdelle. | `inc/seo-meta.php` |

Käsin asetettu **ote** säilyy tarkoituksellisena julkisena teaserina (sama linja
kuin arkistokortilla): vain automaattisesti sisällöstä generoituva ote
suodatetaan. Ilman käsin asetettua otetta näytetään kehote "Sisältö avautuu, kun
lukuoikeus on voimassa."

**Sitemap:** core-sitemap (`/wp-sitemap.xml`) saa listata rajattujen lehtien
URL:t. Tämä on tietoinen päätös: sitemap sisältää vain osoitteet, ei
digilehden sisältöä, ja itse sisältö on suojattu yllä kuvatuilla reiteillä.

## Testaus

Perustestissä varmista:

- `/digilehdet/` näyttää vain lehdet, ei yksittäisiä juttuja
- lehden sivu näyttää jutut sisällysluettelossa oikeassa järjestyksessä
- jutun sivu näyttää takaisin-linkin lehteen
- jutun edellinen/seuraava-navigaatio toimii
- vain jäsenille rajattu lehti näyttää sisällön aktiiviselle jäsenelle ja
  kehotteen kirjautumattomalle / ei-jäsenelle
- rajatun lehden juttu ei avaudu suoralla URL:lla ilman lukuoikeutta
- rajatun lehden arkistokortti ei generoi otetta lehden sisällöstä
- mobiilinäkymässä sisällysluettelo ja lukunäkymä pysyvät luettavina

### Vuotoreittien testaus (#381)

Luo paikallisesti yksi **vain jäsenille** -lehti + yksi juttu, joiden sisältöön
laitat tunnistettavan merkkijonon (esim. `SALAINEN_SISALTO`). Aja
**kirjautumattomana** ja varmista, ettei merkkijono esiinny vastauksissa
(`http://localhost:8000`):

```bash
# REST: content.rendered ja excerpt.rendered tyhjiä rajatulle lehdelle
curl -s "http://localhost:8000/wp-json/wp/v2/digital_magazine?per_page=50" | grep -c SALAINEN_SISALTO   # → 0

# Syötteet: arkistosyöte ja rss2-syöte ilman rajattua sisältöä
curl -s "http://localhost:8000/digilehdet/feed/" | grep -c SALAINEN_SISALTO                              # → 0
curl -s "http://localhost:8000/?post_type=digital_magazine&feed=rss2" | grep -c SALAINEN_SISALTO         # → 0

# Haku: tulosote = kehote, ei sisältöä (merkkijonon osumat ovat vain hakukenttä/otsikko)
curl -s "http://localhost:8000/?s=SALAINEN_SISALTO" | grep "search-result__excerpt"                      # → "Sisältö avautuu…"

# oEmbed + embed-näkymä
curl -s "http://localhost:8000/wp-json/oembed/1.0/embed?url=<lehden-url>&format=json" | grep -c SALAINEN_SISALTO  # → 0
curl -s "http://localhost:8000/<lehden-url>/embed/" | grep -c SALAINEN_SISALTO                                    # → 0
```

Toista **aktiivisella jäsenellä** (tai ylläpitäjänä): lukunäkymä, REST-sisältö ja
syötteet palauttavat sisällön normaalisti. Varmista lisäksi, että **kaikille
ilmainen** lehti palauttaa sisältönsä kaikissa reiteissä kuten ennen ja että
lohkoeditori avaa rajatun lehden sisällön muokattavaksi adminissa.
