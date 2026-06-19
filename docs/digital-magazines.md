# Digilehdet

Digilehdet ovat WordPressissä HTML-sisältöä. Niitä ei julkaista PDF-tiedostoina.

Käyttöoikeus- ja hinnoittelumalli (kaikille ilmainen / vain jäsenille / jäsenhinta + normaalihinta / kaikille maksullinen) on kuvattu erillisessä dokumentissa [digilehdet.md](digilehdet.md). Tämä dokumentti kuvaa pelkän sisältömallin ja ylläpidon.

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
