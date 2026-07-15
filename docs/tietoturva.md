# Tietoturva

Tämä dokumentti kokoaa Rytkösten sukuseuran sivuston tietoturvakovennukset: mitä on toteutettu teemakoodissa ja mitkä asiat hoidetaan palvelin- ja ylläpitotasolla. Liittyy tikettiin #336.

Sivustolla käsitellään henkilötietoja (tapahtumailmoittautumiset, jäsenyydet, WooCommerce-tilaukset), joten tietoturva ja [tietosuoja](tietosuoja.md) kulkevat käsi kädessä.

## Mitä kuuluu mihinkin

Repossa on versioituna vain `wp-content/`. WordPress-core, liitännäiset, palvelimen `.htaccess` ja palvelinkonfiguraatio ovat repon ulkopuolella. Siksi tietoturva jakautuu kahteen osaan:

- **Teemakoodi** (`inc/security.php`) — alla "Toteutetut kovennukset".
- **Käyttöönotto / CI** (`.github/workflows/`) — alla "Käyttöönoton tietoturva (GitHub Actions)".
- **Palvelin / ylläpito** — alla "Palvelintason checklist". Näitä ei voi toteuttaa teemassa.

## Toteutetut kovennukset (teemakoodi)

Kaikki alla oleva on moduulissa [`inc/security.php`](../wp-content/themes/rytkoset-theme/inc/security.php). Koko kovennuspaketin voi poistaa käytöstä suodattimella `rytkoset_theme_enable_security_hardening`.

### Käyttäjien luetteloinnin esto

Estää bottiverkkoja keräämästä kirjautumisnimiä brute-force-hyökkäyksiä varten:

- **REST API:** `/wp/v2/users` ja `/wp/v2/users/<id>` poistetaan kirjautumattomilta (`rest_endpoints`-suodatin). Kirjautuneet käyttäjät säilyttävät pääsyn, joten wp-admin ja blokkieditorin tekijävalinta toimivat normaalisti.
- **`?author=N`:** numerokysely ohjataan etusivulle (301) ennen kuin WordPress paljastaa kirjautumisnimen `/author/<slug>/`-osoitteena. `/author/<slug>/`-arkistot säilyvät ennallaan.
- **Käyttäjäsitemap:** coren `/wp-sitemap-users-1.xml` poistetaan (`wp_sitemaps_add_provider`-suodatin). WordPress 5.5+ listaa siinä kaikki julkaisseet tekijät ja paljastaa heidän `/author/<nicename>/`-arkistonsa. Muut sitemapit (postit, sivut, taksonomiat) säilyvät.
- **Kirjautumisvirheet:** `wp-login.php` näyttää saman virheen tuntemattomasta käyttäjänimestä, tuntemattomasta sähköpostista ja väärästä salasanasta (`wp_login_errors`-suodatin). Tyhjien kenttien, evästeiden, lisäosien ja salasanan palautuksen viestit säilyvät ennallaan.

### XML-RPC estetty

`xmlrpc.php` on yleinen brute-force- ja DDoS-vahvistuskohde, eikä sivusto käytä sitä (ei Jetpackia, mobiilisovellusta eikä pingbackejä):

- `xmlrpc_enabled` palautetaan epätodeksi → kaikki todennusta vaativat metodit (mm. `system.multicall`) estetään.
- `pingback.ping`-metodit poistetaan → sivustoa ei voi käyttää DDoS-heijastimena.
- `X-Pingback`-otsake riisutaan vastauksista.

Erikseen kytkettävissä suodattimella `rytkoset_theme_disable_xmlrpc`.

### Selaintason tietoturvaotsakkeet

Lähetetään etusivun pyynnöille (`send_headers`); wp-admin ohitetaan. Muokattavissa suodattimella `rytkoset_theme_security_headers`.

| Otsake                   | Arvo                                       | Suoja                                   |
| ------------------------ | ------------------------------------------ | --------------------------------------- |
| `X-Content-Type-Options` | `nosniff`                                  | Estää MIME-tyypin arvauksen             |
| `X-Frame-Options`        | `SAMEORIGIN`                               | Clickjacking-suoja                      |
| `Referrer-Policy`        | `strict-origin-when-cross-origin`          | Rajoittaa referrer-tiedon vuotamista    |
| `Permissions-Policy`     | `geolocation=(), microphone=(), camera=()` | Estää käyttämättömät selainominaisuudet |

> **HSTS** (`Strict-Transport-Security`) ja **Content-Security-Policy** on jätetty tarkoituksella pois teemasta. HSTS kuuluu HTTPS-/palvelintasolle, ja tiukka CSP rikkoisi helposti wp-adminin, WooCommercen ja kolmansien osapuolten maksukomponentit. Nämä hoidetaan palvelintasolla, ks. alla.

### Roskarekisteröitymisten esto

Sivustolla on avoin WordPress-rekisteröityminen (*Asetukset → Yleiset → Jäsenyys*). Roskabotit (esim. uhkapelidomainit, nimi+satunnaisnumero -tunnukset) löytävät rekisteröitymislomakkeen automaattisesti. Kevyt suoja ilman kolmannen osapuolen liitännäisiä:

- **Honeypot-kenttä:** rekisteröitymislomakkeeseen (`register_form`) lisätään piilotettu tekstikenttä, jota ihminen ei näe (pois ruudulta, `aria-hidden`, `tabindex="-1"`). Lomakkeet automaattisesti täyttävät botit kirjoittavat siihen ja paljastuvat; rekisteröityminen hylätään `registration_errors`-suodattimessa.
- **Estetyt sähköpostidomainit:** rekisteröityminen tunnetuilla uhkapeli-TLD:illä (`.casino`, `.bet`, `.poker`) estetään. Listaa voi laajentaa suodattimella `rytkoset_theme_blocked_registration_email_patterns` (vertailu domainin loppuosaan).

> Tämä ei korvaa palvelintason kirjautumisrajoitusta. Jos roskarekisteröinnit jatkuvat suuressa mittakaavassa, harkitse CAPTCHAa tai rekisteröitymisen sulkemista kokonaan (WooCommercen tilin luonti kassalla on erillinen asetus ja säilyy).

### CSV-kaavainjektion esto (osallistujavienti)

Osallistujalistan CSV-vienti (`Events > Participants`, `inc/event-participants-admin.php`) sisältää osallistujien itse syöttämiä kenttiä (nimi, sähköposti, puhelin, ruokavalio/huomiot, yhteyshenkilö). Taulukkolaskenta (Excel, LibreOffice, Google Sheets) tulkitsee `=`, `+`, `-`, `@` tai sarkain-/rivinvaihtomerkillä alkavan solun **kaavaksi**, joten haitallinen ilmoittautuja voisi piilottaa CSV:hen ajettavan kaavan (CSV-injektio, CWE-1236).

- **Neutralisointi:** `rytkoset_theme_csv_neutralize_formula()` lisää kaavamerkillä alkavan solun eteen heittomerkin (`'`). Taulukkolaskenta käsittelee sen tekstimerkkinä ja piilottaa sen, joten esim. puhelinnumero `+358401234567` näkyy normaalisti mutta `=HYPERLINK(...)` jää passiiviseksi tekstiksi. Sovelletaan jokaiseen datasoluun `array_map`-kutsulla ennen `fputcsv`:ää.

> Manuaalinen tarkistus: lisää osallistujaksi nimi tai huomio-kenttä arvolla `=1+1` tai `@SUM(...)`, vie CSV ja avaa Excelissä — solu näkyy tekstinä (`'=1+1`), ei laskettuna kaavana. Tavalliset `+`-alkuiset puhelinnumerot näkyvät oikein.

### Ilmoittautumislomakkeen lähetysrajoitus (mail abuse)

Maksuttoman tapahtuman julkinen ilmoittautumislomake (`inc/event-registrations.php`) lähettää onnistuneesta lähetyksestä kuittisähköpostin. Ilman rajoitusta honeypotin ohittava botti voisi lähettää lomakkeen toistuvasti ja synnyttää rajattomasti kuittiviestejä (uhrin postilaatikon pommitus, isännän ~18 sähköpostia/h -rajan polttaminen) ja ilmoittautumistietueita.

- **Per-IP-throttle:** rullaava ikkuna (oletus 5 lähetystä / 10 min) `REMOTE_ADDR`-osoitetta kohden, tallennettuna transienttiin. Rajan ylittävä lähetys hylätään ennen tallennusta ja sähköpostia. Tavallinen yksittäinen ilmoittautuminen ei osu rajaan. Säädettävissä suodattimilla `rytkoset_theme_event_registration_rate_limit` ja `rytkoset_theme_event_registration_rate_limit_window`.
- **Rajoitteet:** vain `REMOTE_ADDR` (väärennettäviä forwarded-otsakkeita ei luoteta). Käänteisen proxyn takana raja kohdistuu proxyn IP:hen, mikä heikentää suojaa — tämä on palvelintason huoli. IP-kierrätys voi kiertää rajan, joten kyseessä on kevyt mitigaatio, ei tae.

### Chat-rajapinnan väärinkäytön ja kuluriskin esto

`rytkoset/v1/chat` ([`inc/chat.php`](../wp-content/themes/rytkoset-theme/inc/chat.php)) on teeman ensimmäinen julkinen REST-reitti, joka kutsuu maksullista kolmannen osapuolen tekoälyrajapintaa (Mistral). Ilman rajoituksia botti tai skripti voisi ajaa rajattomasti API-kuluja tai käyttää reittiä väärin.

- **Nonce:** pyyntö vaatii voimassa olevan `wp_rest`-noncen (`X-WP-Nonce`-otsake), joten kutsun on tultava sivustolta.
- **Per-IP rate limit:** kiinteä ikkuna transientilla, oletus 20 viestiä / IP / tunti (`REMOTE_ADDR` vain — välityspalvelinotsakkeisiin ei luoteta). Ylitys → HTTP 429. Säädettävissä suodattimilla `rytkoset_theme_chat_rate_limit` / `rytkoset_theme_chat_rate_window`.
- **Syöte- ja vastausrajat:** viestin merkkiraja (1000), historian pituus (8 viestiä) ja vastauksen `max_tokens` (512) rajaavat yhden kutsun kulua.
- **Ylläpitäjän kytkin:** chatti voidaan sulkea kokonaan Customizerista (*Ulkoasu → Mukauta → Tukichatti*), jolloin sekä widget että REST-reitti poistuvat käytöstä.
- **Avain ei vuoda:** API-avain luetaan vain palvelimella `wp-config.php`-vakiosta eikä koskaan päädy vasteeseen tai lokiin (paitsi tekninen virheviesti `WP_DEBUG`-tilassa, ei koskaan itse avainta).

> Dokumentoitu tarkemmin [`docs/chat.md`](chat.md):ssä (#412–#414).

### Testaus dev-ympäristössä

Kirjautuneena ulos:

- `https://dev.rytkoset.net/wp-json/wp/v2/users` → `rest_no_route` (404), ei käyttäjälistaa.
- `https://dev.rytkoset.net/?author=1` → ohjaus etusivulle.
- `https://dev.rytkoset.net/wp-sitemap-users-1.xml` → 404; `https://dev.rytkoset.net/wp-sitemap.xml`-hakemistossa ei users-riviä (muut sitemapit näkyvät).
- Tuntematon käyttäjänimi/sähköposti ja olemassa olevan tunnuksen väärä salasana → sama yleinen kirjautumisvirhe, joka ei sisällä syötettyä tunnistetta. Tyhjä kenttä → edelleen kenttäkohtainen virhe.
- `https://dev.rytkoset.net/xmlrpc.php` (POST `system.listMethods`) → ei `pingback.ping`-metodia; todennetut metodit palauttavat virheen.
- `curl -I https://dev.rytkoset.net/` → neljä tietoturvaotsaketta näkyvät; `X-Pingback` puuttuu.
- Rekisteröityminen `.casino`-osoitteella → hylätään virheilmoituksella; honeypot-kentän täyttäminen (esim. devtoolsilla) → rekisteröityminen estyy. Tavallinen osoite ja tyhjä honeypot → rekisteröityminen onnistuu normaalisti.

Kirjautuneena: wp-admin ja blokkieditori toimivat normaalisti.

## Käyttöönoton tietoturva (GitHub Actions)

Teema viedään dev- ja tuotantoympäristöön GitHub Actions -työnkuluilla ([`deploy-dev.yml`](../.github/workflows/deploy-dev.yml), [`deploy-production.yml`](../.github/workflows/deploy-production.yml)). Molemmat käyttävät kolmannen osapuolen FTPS-deploy-actionia ja saavat FTPS-tunnukset GitHub-secretteinä (dev: `FTP_*`, tuotanto: `PROD_FTP_*`). Jos action viitataan **muuttuvalla** versiotagilla (esim. `@v4.4.0`), tagin uudelleenkohdennus tai actionin toimitusketjun kompromissi voisi vuotaa deploy-tunnukset tai muuttaa vietäviä tiedostoja kesken hyväksytyn julkaisun (supply chain, CWE-829).

- **SHA-pinnays:** kaikki actionit — sekä kolmannen osapuolen `SamKirkland/FTP-Deploy-Action` että first-party `actions/checkout` ja `shivammathur/setup-php` — on kiinnitetty tarkistettuun commit-SHA:han, ja perään on kommentoitu vastaava versio (esim. `@110f9186…c287 # v4.4.0`). SHA on muuttumaton, joten ajettava koodi ei voi vaihtua tagin alta.
- **Versiopäivitykset tarkoituksella:** kun action päivitetään, vaihdetaan SHA uuteen tarkistettuun arvoon ja päivitetään versiokommentti. Secrettejä käyttävissä askelissa ei käytetä `@v*`-tageja. Versiokommentti pitää Dependabotin/manuaalisen päivityksen luettavana.
- **Ops-jatkotoimi:** jos tagit olivat aiemmin alttiina, harkitse tuotannon FTPS-tunnusten (`PROD_FTP_*`) rotaatiota pinnauksen jälkeen. Tarkista ja päivitä pinnatut SHA:t hallitusti.

> Toteutettu PR #369:ssä (Codex-scanin löydökset 2 ja 5; tiketit #348 ja #349).

## Palvelintason checklist (ylläpito)

Nämä eivät ole teemakoodia. Tee ja ylläpidä palvelimella / WordPress-adminissa.
Tuotannon tila todennetaan ja tarkistuspäivä kirjataan jatkotiketissä [#564](https://github.com/Alpine78/rytkoset-wp/issues/564); rastit jätetään avoimiksi, kunnes kukin kohta on käytännössä tarkistettu.

### Kirjautumissuojaus (tärkein)

- [ ] **Kaksivaiheinen tunnistautuminen (2FA)** ylläpitäjätileille.
- [ ] **Kirjautumisyritysten rajoitus** (brute-force-suoja) — liitännäinen tai palvelintason rajoitus.
- [ ] **Käyttäjä- ja roolihygienia:** mahdollisimman vähän admin-tilejä; tarkista olemassa olevat käyttäjät säännöllisesti.
- [ ] **Vahvat salasanat** varmistettu kaikilla tileillä.

### wp-login-sivun piilottaminen

Login-URL:n vaihtaminen on "security through obscurity" — vähentää bottiliikennettä, mutta ei korvaa oikeita kontrolleja. Teemassa on lisäksi oma `wp-login.php`-uudelleensuunnittelu ([`inc/login.php`](../wp-content/themes/rytkoset-theme/inc/login.php)), jonka kanssa piilotusliitännäinen voi olla ristiriidassa. **Matala prioriteetti** — panosta ensin 2FA:han ja kirjautumisrajoitukseen.

### Päivitykset ja varmuuskopiot

- [ ] WP-core, liitännäiset ja teema ajan tasalla; päivitysrutiini sovittu. (Suurin yksittäinen riski WP-sivustoilla.)
- [ ] Säännölliset varmuuskopiot ja palautus testattu.

### HTTP-suojaus (palvelin / `.htaccess`)

- [ ] **HTTPS pakotettuna.**
- [ ] **HSTS** (`Strict-Transport-Security`) — vain HTTPS:n yli.
- [ ] **Content-Security-Policy** — vaatii huolellisen määrittelyn wp-adminin, WooCommercen ja käytössä olevan maksupalvelun komponenttien vuoksi; rakennetaan asteittain (report-only ensin).
- [ ] **PHP-tiedostojen suoritus estetty** `wp-content/uploads/`-kansiossa.

### wp-config.php

- [ ] `DISALLOW_FILE_EDIT` päällä (estää teema-/plugin-editorin wp-adminissa).
- [ ] Suolakkeet (`AUTH_KEY` ym.) kunnossa ja uniikit.
- [ ] Tiedosto-oikeudet tarkistettu.

## Yhteys tietosuojaan

Tietoturva on edellytys tietosuojalle. Kun arvioit tämän dokumentin kohtia, tarkista yhteensopivuus [tietosuoja.md](tietosuoja.md):n kanssa erityisesti henkilötietojen (ilmoittautumiset, jäsenyydet, tilaukset) suojaamisen osalta.
