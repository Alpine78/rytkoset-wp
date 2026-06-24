# Jäsenille rajatut sisältösivut

Tämä dokumentti kuvaa, miten ylläpitäjä merkitsee tavallisen WordPress-sivun
**vain jäsenille** näkyväksi. Toteutus on tiketissä `#392` (EPIC 10) ja koodi
moduulissa
[`inc/members-only-pages.php`](../wp-content/themes/rytkoset-theme/inc/members-only-pages.php).

Tämä on ensimmäinen jäsenetu: aktiivinen jäsen näkee rajatun sivun sisällön
normaalisti, muut näkevät sen tilalla kirjautumis- ja liittymiskehotteen.

## Tausta

Jäsenstatus tulee EPIC 10:n jaetusta helperistä
`rytkoset_theme_user_is_active_member()` (`#301`, ks.
[jasenyys.md](jasenyys.md)) — sama lähde kuin digilehtien käyttöoikeudella.
Rajaus tallennetaan sivun metatietoon (`_rytkoset_members_only = yes`); ei uusia
lisäosia eikä erillisiä tietokantatauluja.

Tämä tiketti kattaa **vain tavalliset sivut** (`page`). Digilehtien
käyttöoikeudet ovat oma kokonaisuutensa (EPIC 9, ks.
[digilehdet.md](digilehdet.md)), mutta käyttävät samaa jäsenstatuksen helperiä.

## Sivun merkitseminen jäsenille rajatuksi

1. Mene **Sivut → (valitse sivu)** muokkausnäkymään.
2. Etsi oikean reunan **Näkyvyys**-laatikko (näkyy vain sivun
   muokkausoikeudella, `edit_post`).
3. Rastita **Vain jäsenille** ja tallenna sivu (**Päivitä**).

Kun rasti on pois, sivu on tavallinen julkinen sivu.

## Mitä kävijä näkee

| Kävijä | Näkyvyys |
| --- | --- |
| Aktiivinen jäsen | Sivun sisältö normaalisti. |
| Kirjautunut, ei aktiivinen jäsen | Otsikko + kehote **Tutustu jäsenyyteen** (linkki jäsenyyssivulle). |
| Kirjautumaton | Otsikko + kehote **Kirjaudu sisään**, toissijaisena **Tutustu jäsenyyteen**. |
| Ylläpitäjä / sivun muokkaaja | Sisältö normaalisti (esikatselua varten). |

Sivun **otsikko näkyy aina** — vain sisältö korvataan kehotteella. Kehotteen
liittymislinkki osoittaa jäsenyyssivulle `/sukuseura/jasenyys`.

## Tietovuotojen esto

Rajatun sivun sisältö ei vuoda templaten lukutarkistuksen ohi (sama
tarkistuslista kuin digilehdillä, `#381`):

- **REST** (`rest_prepare_page`) tyhjentää `content`- ja `excerpt`-kentät
  ei-oikeutetulle. `show_in_rest` pysyy päällä, joten lohkoeditori toimii
  muokkausoikeudellisille.
- **Syötteet** (`get_the_excerpt` otesyötteelle ja `the_content_feed` koko
  sisällön syötteelle) ja **WP-haun** otteet korvataan lukkomerkinnällä.
- **oEmbed/`/embed/`** suojautuu saman `get_the_excerpt`-suodattimen kautta.
- **SEO-metakuvaus** (`<meta name="description">`, `og:description`,
  `twitter:description`) ja Rank Math -lisäosan JSON-LD-kuvaus eivät johdeta
  suojatusta sisällöstä (`inc/seo-meta.php`:
  `rytkoset_theme_content_is_access_restricted()` + Rank Math -suodattimet
  `rank_math/frontend/description` ja `rank_math/json_ld`). Sama suoja kattaa myös
  digilehdet (#381).

Käsin asetettu sivun ote säilyy tarkoituksellisena julkisena teaserina; vain
sisällöstä generoituva ote suodatetaan.

## Välimuisti (tärkeä huomio)

Rajatun sivun sisältö riippuu kirjautumis- ja jäsenstatuksesta. Jos sivutason
välimuisti (esim. LiteSpeed/sivuvälimuisti) otetaan joskus käyttöön, **rajatut
sivut on ohitettava cachesta** — muuten jäsenelle tallennettu sisältö voisi
näkyä kirjautumattomalle tai päinvastoin. Kirjautuneiden käyttäjien sivut ovat
yleensä jo oletusarvoisesti välimuistin ulkopuolella, mutta kirjautumattomille
näytettävä kehotesivu pitää joko jättää cachettomaksi tai varmistaa, että cache
ei koskaan tarjoile jäsensisältöä.

## Rajaukset (MVP)

Tässä toteutuksessa **ei** ole:

- sisällön osittaista rajaamista (koko sivu kerrallaan)
- valikoiden ehdollista näyttämistä jäsenstatuksen mukaan
- jäsenhintoja tai kuponkeja
- rajausta muille sisältötyypeille kuin tavallisille sivuille (`page`)

Rajatun sivun tulee käyttää teeman yleistä sivupohjaa
([`page.php`](../wp-content/themes/rytkoset-theme/page.php)). Mukautetut
sivupohjat eivät näytä lukituskehotetta, vaikka REST/syöte/haku-suojat ovatkin
silloinkin voimassa.
