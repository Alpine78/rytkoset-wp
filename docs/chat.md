# AI-tukichatti: backend-proxy, Mistral-integraatio ja ylläpito

AI-tukichatti tarvitsee palvelinpuolen välityskerroksen, jotta Mistralin API-avain **ei koskaan** päädy selaimeen. Kokonaisuus on toteutettu useassa siivussa (EPIC 11 / #411): backend-proxy ja kulusuojat (**#412**), chat-widget (**#413**), Customizer-asetukset, dokumentaatio ja GDPR (**#414**), automaattisesti koottu ajantasainen tietolohko tapahtumille ja jäsenyystuotteille (**#459**), sen laajennus muuhun verkkokaupan tuotekatalogiin (**#471**) sekä kulusuojien osumien ja peruskäyttölukujen näkyminen wp-adminissa (**#472**).

Toteutus: `inc/chat.php`. Teeman koodia, ei ulkoisia kirjastoja. Tämä on teeman **ensimmäinen REST-päätepiste**.

## Päätepiste

```
POST /wp-json/rytkoset/v1/chat
```

- Julkinen reitti (`permission_callback => __return_true`).
- Suojana `wp_rest`-nonce (`X-WP-Nonce`-otsake), IP-pohjainen rate limit sekä syöte-, historia- ja token-rajat.
- Pyynnön runko (JSON): `messages`-taulukko, jossa kukin alkio `{ "role": "user" | "assistant", "content": "…" }`.
- Vastaus (JSON): `{ "reply": "…" }`. Virhetilanteessa WordPressin REST-virhemuoto (`code`, `message`, `data.status`).

## Konfiguraatio (`wp-config.php`-vakiot)

Avainta **ei kirjata repoon**. Aseta kohdeympäristön `wp-config.php`:hen:

```php
define( 'RYTKOSET_CHAT_API_KEY', 'sk-...' );                              // Pakollinen. Mistralin API-avain.
define( 'RYTKOSET_CHAT_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions' ); // Pakollinen. Mistralin EU-endpoint (koko URL).
define( 'RYTKOSET_CHAT_API_MODEL', 'mistral-small-latest' );             // Valinnainen. Oletus: mistral-small-latest.
```

Jos `RYTKOSET_CHAT_API_KEY` tai `RYTKOSET_CHAT_API_ENDPOINT` puuttuu, reitti palauttaa **hallitun virheen** (HTTP 503) — ei PHP-fatalia. Avain luetaan vain palvelimella eikä sitä koskaan tulosteta vasteeseen tai lokiin.

## Kulusuojat

Kaikki oletukset ovat suodatettavia:

| Suoja | Oletus | Suodatin |
|---|---|---|
| Rate limit (viestiä / IP / ikkuna) | 20 | `rytkoset_theme_chat_rate_limit` |
| Rate limit -ikkuna | `HOUR_IN_SECONDS` (1 h) | `rytkoset_theme_chat_rate_window` |
| Yksittäisen viestin merkkiraja | 1000 | `rytkoset_theme_chat_max_input_length` |
| Historian pituus (viimeisimmät viestit) | 8 | `rytkoset_theme_chat_max_history` |
| Vastauksen `max_tokens` | 800 | `rytkoset_theme_chat_max_tokens` |
| Mallin `temperature` (0–1) | 0.2 | `rytkoset_theme_chat_temperature` |
| Työkalukierrokset / käyttäjäviesti (#501) | 2 (kova yläraja 3) | `rytkoset_theme_chat_page_tool_max_rounds` |
| Työkalun palauttaman sivusisällön merkkiraja (#501) | 5000 | `rytkoset_theme_chat_page_tool_max_length` |

- **Rate limit**: kiinteä ikkuna transientilla (`rytkoset_chat_rl_<md5(ip)>`), IP luetaan vain `REMOTE_ADDR`:sta (välityspalvelinotsakkeisiin ei luoteta). Ylitys → HTTP 429. Huom: raja on IP-kohtainen, ei käyttäjäkohtainen — saman verkon (esim. sama WiFi tai operaattorin NAT) kävijät jakavat saman laskurin.
- **Syöte- ja historiarajat**: `rytkoset_theme_chat_prepare_messages()` säilyttää vain `user`/`assistant`-roolit, sanitoi sisällön (`sanitize_textarea_field`), katkaisee jokaisen viestin merkkirajaan ja leikkaa historian viimeisimpiin viesteihin **ennen** API-kutsua.
- **Temperature**: matala oletus (0.2), koska tukichatin vastaukset ovat faktavastauksia — satunnaisuus lisäisi vain epäjohdonmukaisuutta. Arvo rajataan välille 0–1.

### Dev-ympäristön löysemmät rajat

Pitkää testisessiota varten rate limitin ja keskustelumuistin voi ylikirjoittaa ympäristökohtaisesti `wp-config.php`-vakioilla (sama tiedosto, jossa chat-vakiot jo ovat — ei repossa, joten ei voi vuotaa tuotantoon):

```php
define( 'RYTKOSET_CHAT_RATE_LIMIT', 200 );  // dev: enemmän viestejä / IP / h (oletus 20)
define( 'RYTKOSET_CHAT_MAX_HISTORY', 30 );  // dev: pidempi keskustelumuisti (oletus 8 viestiä)
```

> Huom: `add_filter()`-kutsut **eivät** toimi `wp-config.php`:ssä (WordPress lataa sen ennen `plugin.php`:tä) — siksi ylikirjoitus on toteutettu vakioina, samaan tapaan kuin `RYTKOSET_CHAT_API_KEY`. Suodattimet (`rytkoset_theme_chat_rate_limit`, `..._max_history`) ajetaan vakion päälle ja sopivat teeman/mu-pluginin koodiin. Älä löysää tuotannon rajoja ilman erillistä päätöstä — ne ovat kulusuojia.

## System-prompt

`rytkoset_theme_chat_get_system_prompt()` kokoaa promptin, joka ohjeistaa assistentin: vastaa **vain suomeksi**, pysy yhdistyksen aiheissa, käytä faktoihin vain promptissa annettuja lähteitä, **älä keksi tietoa** ja ohjaa epävarmoissa sähköpostiin (`rytkoset_theme_get_contact_email()`). Se myös kieltää täydentämästä puuttuvia kohtia yleisellä tiedolla, WordPress-oletuksilla tai arvauksilla tulevista suunnitelmista, henkilöistä, julkaisujen saatavuudesta, tuotteiden ostettavuudesta, käyttöoikeuksista tai yksittäisten tilausten tilasta. Erillinen ohje kieltää nimenomaan **numeromuotoisten faktojen** (vuosiluvut, päivämäärät, hinnat, lukumäärät) arvaamisen tai päättelyn, kun tarkkaa lukua ei löydy lähteistä — nämä ovat kielimallien yleisin ja huomaamattomin hallusinaatiotyyppi, koska väärä luku ei itsessään paljasta epävarmuutta (#480: tuotannossa havaittu konkreettinen tapaus, jossa sama lähteetön kysymys tuotti kerran asianmukaisen kieltäytymisen ja kerran keksityn vuosiluvun). Lisäksi prompt sisältää tulostyylisäännöt: vastaus pelkkänä tekstinä ilman markdownia, ja sivuston linkit täysinä paljaina osoitteina (`home_url()`-pohja) — widget muuttaa ne turvallisesti klikattaviksi.

Promptin tietolähteet:

1. **Pysyvä sivustokonteksti** (`rytkoset_theme_chat_get_stable_site_context()`): sivuston peruspolut ja vakioidut toimintalogiikat, joita mallin ei pidä päätellä. Mukana ovat mm. `/kauppa/`, `/oma-tili/tilaukset/`, `/tapahtumat/`, `/albumit/`, `/foorumi/`, `/blogi/`, `/digilehdet/`, some-linkit, ehdollinen maksun jatkaminen, foorumin käytössäolo, blogitekstien vastaanotto ylläpidon kautta, digilehtien HTML-muoto, sukukirjan kirjastolainaus, julkaistu `Rytkösten sukulainen nro 9` -tuote sekä hallituksen sivu ja koko hallituslista.
2. **Sivustokartta** (`rytkoset_theme_chat_get_sitemap_context()`): automaattisesti WordPressistä koottu lista sivuston julkaistuista sivuista (otsikko + permalink) sekä tapahtuma- ja albumiarkistoista. Prompt kieltää viittaamasta muihin kuin lähteissä annettuihin osoitteisiin — tuotannossa havaittiin, että ilman sivukarttaa malli keksi osoitteen `/kuvat/`, jota ei ole olemassa. Uusi julkaistu sivu näkyy sivukartassa heti ilman koodimuutosta. Kun sivun lukutyökalu (#501) on käytössä, sivuriveillä on lisäksi `(sivu-id: N)` -merkintä ja julkisten sivujen otsikoista/erisnimistä koottu lyhyt `aiheita:`-hakuvihje, jolla malli voi valita oikean sivun `lue_sivu`-työkalulle. Hakuvihje ei ole faktavastauksen lähde.
3. **Customizerin Tietopohja/FAQ-kenttä** (#414): ylläpitäjän vapaamuotoinen tietopohja vakiintuneille yhdistys- ja toimintaohjeille.
4. **Automaattinen ajantasainen tietolohko** (#459, #471): tulevat tapahtumat, julkaistut jäsenyystuotteet ja muut verkkokaupan tuotteet sivuston omista lähteistä.

Promptin voi korvata tai laajentaa suodattimella `rytkoset_theme_chat_system_prompt` (argumentit: `$prompt`, `$contact_email`). Pysyvää sivustokontekstia voi muokata suodattimella `rytkoset_theme_chat_stable_site_context`. Sivustokartan suodattimet: `rytkoset_theme_chat_sitemap_enabled` (`true`; koko lohkon voi kytkeä pois), `..._sitemap_max_pages` (60) ja `..._sitemap_max_length` (6000 merkkiä).

## Customizer-asetukset (#414)

**Ulkoasu → Mukauta → Tukichatti** (`customize.php`, osio `rytkoset_theme_chat`). Kolme kenttää, jotka ei-tekninen ylläpitäjä voi muokata ilman koodimuutoksia:

| Kenttä | Setting | Vaikutus |
|---|---|---|
| Näytä tukichatti sivustolla (checkbox, oletus **päällä**) | `rytkoset_theme_chat_enabled` | Pois → widget piilotetaan **ja** REST-reitti palauttaa hallitun virheen (HTTP 503). Suora rajapintakutsukin siis estyy. |
| Tervetuloviesti (textarea) | `rytkoset_theme_chat_welcome_message` | Chatin avausviesti. Tyhjä = teeman oletusteksti. |
| Tietopohja / usein kysytyt kysymykset (textarea) | `rytkoset_theme_chat_faq` | Liitetään system-promptiin jokaiseen API-kutsuun. Muutos näkyy chatin vastauksissa heti julkaisun jälkeen. |

Getterit: `rytkoset_theme_chat_admin_enabled()`, `rytkoset_theme_chat_get_welcome_message()`, `rytkoset_theme_chat_get_faq_text()`. Sanitointi: checkbox → bool, tekstikentät → `sanitize_textarea_field`.

Huom: chatti näkyy sivustolla vain kun **molemmat** ehdot täyttyvät — API-avain on asetettu `wp-config.php`:hen **ja** Customizer-kytkin on päällä.

### Tietopohjan (FAQ) ylläpito

FAQ-teksti täydentää pysyvää sivustokontekstia ja automaattista tapahtuma-/tuotelohkoa. Malli ei hae tietoa internetistä eikä selaa sivustoa itse chat-pyynnön aikana. Kirjoitusohjeet:

- **Rakenne:** otsikot ISOLLA omilla riveillään, faktat luettelomerkkeinä (`- `). Selkeä rakenne auttaa mallia poimimaan oikean kohdan.
- **Sisältö:** vakiintuneet faktat ja toimintaohjeet — jäsenyystyypit ja -hinnat, maksaminen ja Mollie-erityistapaukset (ulkomaanmaksun hyväksyntä, RF-viitteen väliviivat Mollien sähköpostissa), maksun jatkaminen vain ehdollisesti ("jos tilauksella näkyy Maksa / yritä uudelleen -painike"), tilauksen peruutus, tapahtumiin ilmoittautuminen, kirjautumisongelmat, historian tiivistelmä, yhteystiedot.
- **Pituus:** teksti lähetetään Mistralille **jokaisen viestin mukana**, joten pidä se tiiviinä (nyrkkisääntö: alle ~5 000 merkkiä). Pitkä teksti kasvattaa kuluja ja heikentää vastausten tarkkuutta.
- **Ajantasaisuus:** kun hinnat, päivämäärät tai käytännöt muuttuvat sivustolla, päivitä myös FAQ — chatti ei huomaa sivuston muutoksia itse. Nopeasti muuttuvien tietojen (esim. yksittäisen tapahtuman aikataulu) osalta parempi tapa on viitata tapahtumasivuun kuin kopioida yksityiskohdat FAQ:hun.
- **Rajaukset:** älä laita FAQ:hun henkilötietoja äläkä mitään, mikä ei saa näkyä julkisesti — FAQ:n sisältö voi päätyä chatin vastauksiin kenelle tahansa kävijälle.

Testaa muutokset dev-ympäristössä kysymällä chatilta muutettuja kohtia ennen tuotantoon vientiä.

### Tuotantovalmiuden savutestit

Ennen chatin vientiä tuotantoon testaa devissä ainakin kysymykset, joissa malli on helposti taipuvainen arvaamaan:

- "Toimiiko foorumi vielä?" -> ei saa väittää foorumia suljetuksi.
- "Voinko lähettää blogitekstin?" -> pitää kertoa, että kirjoituksia voi ehdottaa/lähettää ylläpidolle sähköpostitse; käyttäjä ei itse julkaise ilman käyttöoikeuksia.
- "Onko sukukirjaa saatavana?" -> pitää kertoa, että sukukirjaa voi lainata eri kirjastoista; ei saa keksiä uutta käynnissä olevaa sukukirjaprojektia.
- "Onko Rytkösten sukulainen nro 9 julkaistu?" -> pitää kertoa, että numero 9 on julkaistu ja myynnissä verkkokaupassa.
- "Kuka on sukuseuran puheenjohtaja?" -> pitää vastata hallitussivun mukaan: Antti Rytkönen.
- "Keitä sukuseuran hallitukseen kuuluu?" -> pitää käyttää pysyvän kontekstin listaa, eikä vastauksessa saa olla muita nimiä kuin listatut hallituksen jäsenet.
- "Onko sukuseuralla some-tilejä?" -> pitää tunnistaa sivuston some-linkit.
- "Missä sivuston kuvat ovat?" -> pitää ohjata `/albumit/`-osoitteeseen; ei saa keksiä `/kuvat/`- tai muuta olematonta osoitetta.
- "Saanko digilehden PDF:nä?" -> ei saa luvata PDF-latausta, ellei tietopohjaan ole lisätty erillistä ohjetta.
- "Miten voin yrittää epäonnistunutta maksua uudelleen?" -> pitää käyttää ehdollista muotoa: vain jos tilauksella näkyy **Maksa / yritä uudelleen** -painike.
- "Mitä kaupassa on myynnissä?" -> pitää listata julkaistut tuotteet nimellä ja hinnalla (#471), ei saa väittää tuotteen olevan varastossa/loppu, ja pitää ohjata tuotesivulle ajantasaisen saatavuuden tarkistamiseen.

## Ajantasainen tietolohko (#459, #471)

FAQ:n lisäksi system-promptiin liitetään **automaattisesti koottu** tietolohko, joka luetaan suoraan samoista lähteistä kuin sivuston omat näkymät — sitä ei ylläpidetä käsin, eikä se vanhene:

- **Tulevat tapahtumat** (`rytkoset_event`, julkaistut, tapahtumapäivä tänään tai myöhemmin; tapahtumapäivä on voimassa päivän loppuun): nimi, päivämäärä, kellonaika, paikka, hinta, ilmoittautumisen takaraja samasta lähteestä kuin tapahtumasivun yhteenvetokortilla (maksuttomat: oma takaraja tai tapahtumapäivä; linkitetyt maksulliset tuotteet: tuotteen takaraja), #450-lisävalinnat (esim. bussin lähtöpaikat + määräkentän nimi) ja tapahtuman osoite. Maksulliset tapahtumat ohjataan tapahtumasivulle. Enintään 5 tapahtumaa aikajärjestyksessä. Jos tulevia tapahtumia ei ole, lohko toteaa sen eksplisiittisesti, ettei malli keksi tapahtumia.
- **Jäsenyystuotteet** (`_rytkoset_membership_product = yes`, vain julkaistut): nimi, hinta, jäsenyystyyppi ja "jäsenyys voimassa X asti" -päivä. Osio jää kokonaan pois, jos WooCommerce ei ole aktiivinen (fail-safe).
- **Muut verkkokaupan tuotteet** (#471, esim. sukulehdet, t-paidat): kaikki muut julkaistut ja hinnalliset WooCommerce-tuotteet — nimi, hinta, permalink — `menu_order`/nimi-järjestyksessä (sama järjestys kuin kaupan oletuslajittelu). Jäsenyystuotteet jätetään pois, koska niillä on jo oma osionsa yllä — ei duplikointia. Enintään 20 tuotetta. Osio jää kokonaan pois, jos julkaistuja ei-jäsenyystuotteita ei löydy.

Lohkon perään lisätään ohje, ettei malli arvioi vapaita paikkoja, ilmoittautumistilannetta tai tuotteiden varastotilannetta vaan ohjaa tapahtuman tai tuotteen sivulle. Toteutus: `rytkoset_theme_chat_get_live_context()` + apufunktiot (`..._get_upcoming_event_ids()`, `..._format_event_context()`, `..._get_membership_context()`, `..._get_shop_products_context()`) `inc/chat.php`:ssä; ei välimuistia (kevyet kyselyt ajetaan vain chat-pyynnön yhteydessä, rate limit rajaa volyymin).

Suodattimet:

| Suodatin | Oletus | Vaikutus |
|---|---|---|
| `rytkoset_theme_chat_live_context_enabled` | `true` | Koko lohkon voi kytkeä pois |
| `rytkoset_theme_chat_live_context_max_events` | 5 | Tapahtumien enimmäismäärä |
| `rytkoset_theme_chat_live_context_max_products` | 20 | Muiden verkkokaupan tuotteiden enimmäismäärä (#471) |
| `rytkoset_theme_chat_live_context_max_length` | 4000 | Lohkon merkkiraja (katkaisu) |

Työnjako FAQ:n kanssa: **rakenteinen, muuttuva tieto** (päivämäärät, hinnat, lähtöpaikat) tulee tästä lohkosta automaattisesti — sitä ei tarvitse eikä kannata kopioida FAQ:hun. FAQ:hun kirjoitetaan vain vakaat faktat ja toimintaohjeet (maksaminen, käytännöt, historia). Uuden tuotteen lisääminen kauppaan riittää — chatti kertoo siitä ilman FAQ- tai koodimuutosta heti kun tuote on julkaistu ja sillä on hinta.

## Sivun lukutyökalu (#501)

Chatin tietopohja laajenee promptin lähteiden yli **function calling** -työkalulla: malli saa `lue_sivu`-työkalun, jolla se voi hakea sivustokartassa listatun **julkaistun julkisen sivun** tekstisisällön, kun vastausta ei löydy promptin lähteistä. Tietopohja on siis sivusto itse — uusi julkaistu sivu on heti chatin luettavissa ilman FAQ- tai koodimuutosta.

**Toiminta:**

1. Sivustokartan sivuriveille lisätään `(sivu-id: N)` -merkintä ja julkisten sivujen otsikoista/erisnimistä koottu lyhyt `aiheita:`-hakuvihje. System-prompt ohjeistaa kutsumaan työkalua **ennen kieltäytymistä**, kun vastaus ei ole jo annetuissa lähteissä — mutta ei silloin, kun vastaus löytyy promptista. Hakuvihjeet auttavat valitsemaan oikean sivun, mutta niistä ei saa muodostaa faktavastausta ilman sivun lukemista työkalulla. Peruskysymykset vastataan edelleen **yhdellä API-kutsulla** täsmälleen kuten ennen. Sanamuoto on tarkoituksella matalakynnyksinen: ensimmäinen devissä testattu versio ehdollisti työkalun käytön sanalla "todennäköisesti", ja promptin arvauskieltojen (#480 ym.) rinnalla `mistral-medium-latest` jätti työkalun kokonaan käyttämättä (17 viestiä, 0 työkalukutsua) ja jopa väitti, ettei sivulla näkyvää nimeä mainita sivustolla. Nykyinen ohje sanoo eksplisiittisesti, että työkalulla haettu sisältö on sallittu lähde, työkalun kokeileminen ei ole kiellettyä arvaamista, ja ettei sivuston sisällöstä saa esittää kielteistä väitettä ("ei mainita sivustolla") tarkistamatta asiaa työkalulla.
2. Kun Mistral vastaa `tool_calls`-rakenteella, backend suorittaa työkalun (`rytkoset_theme_chat_run_page_tool()`), liittää keskusteluun assistentin tool-call-viestin + `role: "tool"` -viestin (`tool_call_id`) ja tekee jatkokutsun. Kierroksia sallitaan oletuksena **2** (nostettu 1:stä toisessa devin savutestissä): jos ensimmäinen sivuarvaus osoittautuu vääräksi tai riittämättömäksi, mallilla on mahdollisuus kokeilla toista sivustokartan sivua ennen kuin se vastaa, ettei tiedä — yhden kierroksen rajalla mallilla ei ollut tätä mahdollisuutta, mikä johti virheellisiin "ei mainita sivustolla" -väitteisiin, kun ensimmäinen arvaus (esim. henkilön nimi, joka ei ole minkään otsikon ilmeinen aihe) osui väärään sivuun. Viimeisellä sallitulla kierroksella `tool_choice: "none"` pakottaa tekstivastauksen.
3. Sivun sisältö luetaan raa'asta `post_content`ista ja riisutaan tekstiksi (`rytkoset_theme_chat_extract_page_text()`): block-kommentit, HTML ja shortcodet poistetaan — shortcodeja tai dynaamisia blokkeja **ei suoriteta** (MVP-rajaus; whitelistattu renderöinti mahdollinen jatkoaskel).

**Vuotosuoja:** työkalu palauttaa vain julkaistuja (`publish`), salasanattomia `page`-tyypin sivuja. Jäsenille rajatut sivut (#392, `_rytkoset_members_only`) suodatetaan **ehdottomasti ja katsojasta riippumatta** — chat-vastaukset menevät kolmannen osapuolen API:in, joten jäsensivua ei palauteta edes kirjautuneelle jäsenelle tai ylläpitäjälle. Kaikki epäämissyyt palauttavat saman geneerisen virhetekstin, ettei vastauksesta voi päätellä rajatun sisällön olemassaoloa. Sivustokartan `aiheita:`-hakuvihjeitä ei lisätä salasanalla suojatuille tai jäsenille rajatuille sivuille. Fail-closed: jos jäsensivumoduulia ei ole ladattu, mitään sivua ei palauteta eikä sivulle lisätä sisältövihjeitä.

**Kulusuojat:** työkalukierroksia enintään 2 per käyttäjäviesti oletuksena (suodatin `rytkoset_theme_chat_page_tool_max_rounds`, kova yläraja 3), sivusisältö katkaistaan 5000 merkkiin (`..._page_tool_max_length`), sivunluku suoritetaan enintään kolmelle työkalukutsulle per kierros ja jokainen suoritettu kutsu kirjataan käyttötilastoihin (#472). Rate limit kuluu kerran per käyttäjäviesti — sisäiset jatkokutsut eivät kierrä sitä. Huomaa, että työkalullinen kysymys kestää yleensä kaksi tai kolme API-kierrosta ja maksaa enemmän tokeneita.

**Pois kytkeminen:** suodatin `rytkoset_theme_chat_page_tool_enabled` (`false` → API-payload, sivustokartta ja system-prompt ovat täsmälleen entisellään, ei `tools`-kenttää). Mallihuomio: dev ja tuotanto käyttävät `mistral-medium-latest`-mallia (`RYTKOSET_CHAT_API_MODEL`; koodin oletusfallback on `mistral-small-latest`). Mistralin dokumentaation mukaan vahvin function calling -tuki on `mistral-large-latest`-mallilla — jos käytössä oleva malli käyttää työkalua huonosti (turhia kutsuja tai ei kutsu lainkaan), mallin voi vaihtaa ympäristökohtaisesti samalla vakiolla.

Maksuohjeissa ei pidä luvata, että kaikki epäonnistuneet tai keskeneräiset
Mollie-tilaukset voi aina vaihtaa itse toiseen maksutapaan. Käytä muotoa:
"Avaa Oma tili -> Tilaukset. Jos tilauksen kohdalla näkyy Maksa / yritä
uudelleen -painike, voit jatkaa maksua ja valita kassalla toisen maksutavan.
Jos painiketta ei näy, ota yhteyttä sähköpostitse." Toteutus ja tarkempi
rajaus on dokumentoitu tiedostossa `docs/woocommerce-mollie-payments.md`.

## Käyttötilastot ylläpitäjälle (#472)

Ennen tätä tikettiä kulusuojien osumat eivät näkyneet ylläpitäjälle mitenkään tuotannossa: `rytkoset_theme_chat_log_error()` kirjoittaa lokiin vain `WP_DEBUG`-tilassa, eikä rate limit -osumia kirjattu mihinkään. Nyt kolme kevyttä laskuria näyttävät suoraan wp-adminissa, käytetäänkö chattia, osuuko joku rate limitiin ja toimiiko Mistral-yhteys — ilman palvelimen lokien tarkistamista.

**Näkyvyys:** WordPressin Dashboard-widget **"Tukichatti"** (`rytkoset_theme_chat_register_dashboard_widget()`, koukku `wp_dashboard_setup`), näkyy vain `manage_options`-käyttäjille. Widget näyttää chatin tilan (käytössä / pois päältä Customizerista / API-avain puuttuu), lähetettyjen viestien kokonaismäärän + viimeisimmän ajankohdan, rate limit -osumien kokonaismäärän + viimeisimmän ajankohdan sekä viimeisimmän Mistral-/yhteysvirheen kokonaismäärän, ajankohdan ja tyypin.

**Tallennus:** kolme `wp_options`-riviä, `autoload = false`, päivitetään olemassa olevissa päätöspisteissä koodimuutoksella — ei erillistä seurantajärjestelmää:

| Option | Sisältö | Päivityskohta |
|---|---|---|
| `rytkoset_chat_stat_messages` | `count`, `last_at` | `rytkoset_theme_chat_handle_request()`:n onnistunut paluu (`rytkoset_theme_chat_record_message_sent_stat()`) |
| `rytkoset_chat_stat_rate_limit` | `count`, `last_at` | `rytkoset_theme_chat_register_rate_limit_hit()` palauttaa `true` (`rytkoset_theme_chat_record_rate_limit_hit_stat()`) |
| `rytkoset_chat_stat_error` | `count`, `last_at`, `last_type` | Samat kolme kohtaa kuin `rytkoset_theme_chat_log_error()` (verkkovirhe, ei-2xx-HTTP-vastaus, tyhjä/odottamaton vastaus) — `log_error()` säilyy ennallaan WP_DEBUG-lokitusta varten, `rytkoset_theme_chat_record_error_stat( $type )` on erillinen, rinnakkainen kutsu |
| `rytkoset_chat_stat_tool_calls` | `count`, `last_at` | Jokainen **suoritettu** `lue_sivu`-työkalukutsu (#501, `rytkoset_theme_chat_record_tool_call_stat()`) — kierroskaton ylittäneitä, ohitettuja kutsuja ei lasketa; viestimäärälaskuri kasvaa edelleen vain kerran per käyttäjäpyyntö |

`last_type`-arvo on lyhyt, staattinen tunniste (`network`, `http_<koodi>`, `empty_reply`) — ei koskaan dynaamista virhesanomaa. `rytkoset_theme_chat_get_error_type_label()` muotoilee sen ihmisluettavaksi widgetissä.

**Ei henkilötietoa:** laskurit eivät koskaan sisällä raakaa IP-osoitetta eivätkä viestisisältöä — vain lukumäärät, aikaleimat ja lyhyt virhetyypin tunniste, sama periaate kuin nykyisessä rate limit -transientissa (joka tallentaa vain MD5-tiivisteen). Koska data ei yksilöi ketään, se ei ole GDPR:n tarkoittamaa henkilötietoa eikä `docs/tietosuoja.md`-tietosuojaselosteen sisältöä tarvinnut tämän vuoksi muuttaa (ks. selosteen "AI-tukichatti"-kohta).

**Puhtaat apufunktiot** (testattu `tests/ChatUsageStatsTest.php`:ssä): `rytkoset_theme_chat_bump_stat()` / `..._bump_error_stat()` (laskurin kasvatus, ei kosketa `wp_options`-tauluun), `rytkoset_theme_chat_get_usage_stats()` (yhteenveto widgetiä varten) ja `rytkoset_theme_chat_get_error_type_label()`. Itse Dashboard-widgetin rekisteröinti ja renderöinti ovat ohutta admin-liimakoodia, joka on tarkoituksella jätetty yksikkötestien ulkopuolelle (ks. `CLAUDE.md`:n testausohje render-raskaille admin-näkymille) — todennettu manuaalisesti wp-adminissa.

## Palveluntarjoajan vaihto (Mistral ↔ Azure Sweden Central)

Integraatio on tarkoituksella tehty vaihdettavaksi: chatti kutsuu geneeristä **chat-completions-rajapintaa** (`POST {endpoint}` + `Authorization: Bearer {key}` + `{model, messages, max_tokens, temperature}` → `choices[0].message.content`), ja kaikki kolme parametria luetaan `wp-config.php`-vakioista. Palveluntarjoajan vaihto on siis konfiguraatiomuutos, **ei koodimuutos**, kunhan uusi tarjoaja toteuttaa saman rajapintamuodon:

1. Hanki uuden tarjoajan API-avain ja chat-completions-päätepisteen koko URL (esim. Mistral-malli Azure AI:n Sweden Central -alueella).
2. Päivitä `RYTKOSET_CHAT_API_KEY`, `RYTKOSET_CHAT_API_ENDPOINT` ja tarvittaessa `RYTKOSET_CHAT_API_MODEL` kohdeympäristön `wp-config.php`:hen.
3. Aja alla oleva curl-testi ja varmista suomenkielinen vastaus.

Tarkista vaihdon yhteydessä tarjoajan dokumentaatiosta erityisesti: (a) hyväksyykö päätepiste `Authorization: Bearer` -otsakkeen — koodi lähettää vain sen, joten esim. pelkkää `api-key`-otsaketta vaativa päätepiste vaatisi pienen muutoksen `inc/chat.php`:n `wp_remote_post()`-kutsuun; (b) mallin nimi kyseisessä palvelussa; (c) että data käsitellään EU:ssa (GDPR — päivitä myös tietosuojaseloste, ks. alla).

## Tietosuoja (GDPR)

Chatin tietosuojaominaisuudet, jotka selosteen ja dokumentaation väitteet perustuvat koodiin:

- Viestit välitetään Mistralin **EU-päätepisteeseen** palvelimen kautta; API-avain ja kävijän IP eivät koskaan välity Mistralille (Mistral näkee vain palvelimen IP:n).
- Keskusteluja **ei tallenneta** WordPressiin eikä pysyvästi selaimeen: historia säilyy selaimen **välilehtikohtaisessa istuntomuistissa** (`sessionStorage`, #498), jotta keskustelu jatkuu sivulatausten yli, ja tyhjenee kun välilehti suljetaan. Ei evästeitä eikä pysyvää tallennusta (`localStorage`); istuntomuisti palvelee vain kävijän itse aloittaman keskustelun jatkuvuutta (välttämätön tallennus) → ei suostumusbanneritarvetta.
- Rate limit käsittelee kävijän IP-osoitetta lyhytaikaisesti palvelimella (transient, MD5-tiivisteenä avaimessa, enintään rajoitusikkunan ajan). IP:tä ei yhdistetä keskustelun sisältöön.
- Widgetin disclaimer kehottaa olemaan syöttämättä arkaluonteisia tietoja, ja system-prompt ohjeistaa mallia olemaan pyytämättä niitä.

### Tekstiehdotus tietosuojaselosteeseen

Selosteteksti on sivun sisältöä (ei koodia), joten alla oleva on **ehdotus ylläpidolle** lisättäväksi tietosuojaseloste-sivulle. Täydennä yhteysosoite ja tarkista Mistral-linkin ajantasaisuus:

> **Tekoälyavusteinen tukichatti**
>
> Sivustolla on tekoälyavusteinen tukichatti, joka vastaa sukuseuraa ja sivuston käyttöä koskeviin kysymyksiin. Vastaukset tuottaa Mistral AI:n kielimalli. Chattiin kirjoitetut viestit lähetetään käsiteltäviksi Mistral AI:lle (Mistral AI SAS, Ranska), ja ne käsitellään Euroopan unionin alueella. Viestejä käytetään vain vastauksen tuottamiseen.
>
> Sivusto ei tallenna chat-keskusteluja: keskustelu säilyy vain oman selaimesi välilehtikohtaisessa istuntomuistissa, jotta se jatkuu sivulta toiselle siirryttäessä, ja tyhjenee, kun suljet välilehden. Chatti ei käytä evästeitä. Väärinkäytön estämiseksi sivusto käsittelee kävijän IP-osoitetta lyhytaikaisesti viestimäärän rajoittamista varten; IP-osoitetta ei yhdistetä keskustelujen sisältöön eikä luovuteta chat-palvelun tarjoajalle.
>
> Älä kirjoita chattiin henkilötietoja tai arkaluonteisia tietoja (esimerkiksi henkilötunnusta, salasanoja tai maksukortin tietoja). Henkilökohtaisissa asioissa ota yhteyttä sähköpostitse: **[yhteysosoite]**.
>
> Lisätietoa Mistral AI:n tietosuojakäytännöistä: https://mistral.ai/privacy-policy

## Testaus

1. Aseta yllä olevat vakiot dev-ympäristön `wp-config.php`:hen.
2. Generoi `wp_rest`-nonce (curl-testiä varten):

   ```bash
   docker compose run --rm wpcli wp eval 'echo wp_create_nonce("wp_rest");'
   ```

   WP-CLI ajaa kirjautumattomana (käyttäjä 0), joten nonce vastaa kirjautumattoman selainkävijän noncea.
3. Kutsu päätepistettä:

   ```bash
   curl -X POST 'https://dev.rytkoset.net/wp-json/rytkoset/v1/chat' \
     -H 'X-WP-Nonce: <nonce>' \
     -H 'Content-Type: application/json' \
     -d '{"messages":[{"role":"user","content":"Milloin on seuraava sukukokous?"}]}'
   ```

   → suomenkielinen `{"reply":"…"}`.
4. **Rate limit**: toista kutsua yli rajan → HTTP 429.
5. **Syöteraja**: lähetä yli 1000 merkin viesti → katkaistaan ennen API-kutsua.
6. **Puuttuva avain**: poista `RYTKOSET_CHAT_API_KEY` → HTTP 503, ei PHP-fatalia.
7. Varmista, ettei API-avain näy vasteessa eikä selaimen lähdekoodissa.
8. **Customizer-kytkin**: ota chatti pois päältä (Ulkoasu → Mukauta → Tukichatti) → widget ei renderöidy ja suora REST-kutsu palauttaa HTTP 503 (`rytkoset_chat_disabled`).
9. **FAQ**: lisää Tietopohja-kenttään tunnistettava fakta (esim. testihinta) → chatti käyttää sitä vastauksessa ilman koodimuutosta.
10. **Ajantasainen tieto (#459)**: luo/muokkaa tuleva tapahtuma adminissa → chatti kertoo sen päivämäärän, paikan ja lähtöpaikkavaihtoehdot ilman FAQ-muutosta; kysy jäsenmaksun hintaa → vastaus vastaa verkkokaupan tuotetta. Kysy vapaita paikkoja → chatti ohjaa tapahtumasivulle eikä arvaa.
11. **Kaupan tuotekatalogi (#471)**: julkaise uusi tuote (esim. t-paita, hinta asetettu) → chatti kertoo siitä nimellä ja hinnalla ilman FAQ- tai koodimuutosta. Lisää jäsenyystuote → se ei toistu "muut tuotteet" -listassa, koska se on jo jäsenyysosiossa. Aseta tuote luonnokseksi tai poista sen hinta → chatti ei enää mainitse sitä. Kysy tuotteen olevan varastossa → chatti ei väitä varastotilannetta vaan ohjaa tuotesivulle.
12. **Sivun lukutyökalu (#501)**: kysy asiaa, joka löytyy vain jonkin julkaistun sivun sisällöstä (ei FAQ:sta eikä pysyvästä kontekstista) → chatti vastaa sivun sisällön perusteella (vastaus kestää kaksi–kolme API-kierrosta). Kysy peruskysymys (esim. jäsenmaksun hinta) → vastaus tulee yhtä nopeasti kuin ennen (yksi kutsu, työkalua ei käytetä turhaan). Merkitse testisivu "Vain jäsenille" (#392) → chatti ei saa kertoa sen sisältöä millään kysymyksellä. Tarkista Dashboard-widgetistä, että sivunlukuhakujen laskuri kasvaa vain työkalullisista kysymyksistä. Kysy vielä sama pitkän, moniosaisen sivun (esim. sääntöjen) yksityiskohta useammalla eri kysymyksellä peräkkäin (esim. tilikausi, nimenkirjoitusoikeus) → jokaisen pitäisi onnistua itsenäisesti, koska jokainen käyttäjäviesti on backendille erillinen pyyntö eikä aiemman kierroksen työkalutulos säily seuraavaan viestiin.
13. **Käyttötilastot (#472)**: lähetä chatiin muutama viesti → wp-adminin Ohjausnäkymän **Tukichatti**-widget näyttää lähetettyjen viestien määrän kasvavan ja viimeisimmän ajankohdan päivittyvän. Täytä rate limit (esim. `RYTKOSET_CHAT_RATE_LIMIT` väliaikaisesti pieneksi devissä) → rate limit -osumien laskuri kasvaa. Aiheuta upstream-virhe (esim. väliaikaisesti virheellinen `RYTKOSET_CHAT_API_ENDPOINT`) → virhelaskuri kasvaa ja widget näyttää viimeisimmän virhetyypin. Poista `RYTKOSET_CHAT_API_KEY` → widgetin tila-rivi kertoo "API-avain puuttuu". Tarkista, ettei widgetissä näy IP-osoitteita eikä viestien sisältöä.

Yksikkötestit (`tests/ChatProxyTest.php`) kattavat puhtaat helperit: rate limit -päätös, viestien valmistelu/katkaisu, vastauksen poiminta ja system-prompt. `tests/ChatLiveContextTest.php` kattaa ajantasaisen tietolohkon (tapahtumat, jäsenyystuotteet, muut verkkokaupan tuotteet). `tests/ChatSitemapTest.php` kattaa sivustokarttalohkon (julkaistut sivut, arkistolinkit, rajaukset, kytkentä system-promptiin). `tests/ChatUsageStatsTest.php` kattaa käyttötilastojen laskurit, tallennuksen ja yhteenvedon (#472). `tests/ChatPageToolTest.php` kattaa sivun lukutyökalun puhtaat helperit (#501): tool_calls-poiminta, argumenttien jäsennys, sisällön riisunta, vuotosuojattu sivuhaku, sivu-id-merkinnät, prompt-kytkentä ja työkalulaskuri. Verkko- ja REST-kytkentä (ml. työkalusilmukka) sekä Dashboard-widget varmistetaan yllä olevalla manuaalisella curl-/selaintestillä.

## Käyttöliittymä (chat-widget, #413)

Kelluva chat-painike + paneeli (`inc/chat.php` renderöi kuoren `wp_footer`-koukussa, `assets/js/chat.js` + `assets/css/chat.css`). Rakentuu yllä kuvatun REST-reitin päälle.

- **Näkyy vain kun backend on konfiguroitu ja chatti on kytketty päälle.** `rytkoset_theme_chat_widget_is_enabled()` palauttaa `true` vasta kun `RYTKOSET_CHAT_API_KEY` + `RYTKOSET_CHAT_API_ENDPOINT` on asetettu **ja** Customizerin Tukichatti-kytkin on päällä (#414) — muuten widgetiä ei renderöidä eikä assetteja ladata. Näyttämisen voi pakottaa/estää suodattimella `rytkoset_theme_chat_widget_enabled`.
- **Keksitön:** keskusteluhistoria + paneelin auki-tila säilyvät **välilehtikohtaisessa `sessionStorage`ssa** (#498; versioitu avain `rytkosetChat.v1`, historiakatto 40 viestiä), joten keskustelu jatkuu sivulatausten yli ja tyhjenee välilehden sulkeutuessa. Ei `localStorage`a eikä keksejä → keksibanneria ei tarvita (välttämätön, kävijän itse pyytämän toiminnon tallennus). Palautetut viestit renderöidään samaa turvallista polkua kuin livenä. Jos selain estää storagen tai se on täynnä, chatti toimii muistinvaraisesti kuten ennen (sivulataus nollaa keskustelun).
- **Turvallinen renderöinti:** mallin vastaus lisätään DOMiin ilman `innerHTML`:ää (tekstisolmut) → ei XSS-riskiä. Rivinvaihdot säilyvät CSS:n `white-space: pre-wrap`illä. Vastauksessa olevat **paljaat oman sivuston https-osoitteet** (nykyinen isäntä, `rytkoset.net` ja sen alidomainit) muutetaan klikattaviksi linkeiksi turvallisesti (`createElement('a')`, `new URL()`-validointi, protokolla vain http/https, `rel="noopener noreferrer"` muille kuin oman hostin linkeille). Oman hostin linkit avautuvat **samassa välilehdessä**, jolloin keskustelu jatkuu `sessionStorage`sta (#498); muut sallitut hostit (esim. dev↔tuotanto-alidomainit) avautuvat uuteen välilehteen, koska `sessionStorage` ei siirry uuteen välilehteen eikä toiselle originille. Muut osoitteet ja mahdollinen markdown-syntaksi jäävät pelkäksi tekstiksi. System-prompt ohjeistaa mallia vastaamaan ilman markdownia ja kirjoittamaan sivuston linkit täysinä osoitteina (`home_url()`-pohja, toimii oikein dev- ja tuotantoympäristössä).
- **Saavutettavuus (WCAG 2.1 AA):** paneeli `role="dialog"`, viestiloki `role="log" aria-live="polite"` (uudet vastaukset ilmoitetaan ruudunlukijalle), näkyvä fokus, näppäimistökäyttö (Enter lähettää, Shift+Enter rivinvaihto, **Esc** sulkee ja palauttaa fokuksen painikkeeseen). Fokusansaa ei ole (paneeli ei ole modaali) — MVP-rajaus.
- **Tumma teema:** widget käyttää vain design-tokeneita, jotka adaptoivat `:root[data-theme="dark"]`-teemaan.
- **Mobiili (≤ 640px):** paneeli täyttää lähes koko ruudun (`chat.css`). iOS Safarissa kiinnitetyn (`position: fixed`) elementin sijainti lasketaan layout-viewportista, joka voi poiketa näkyvästä viewportista sekä virtuaalinäppäimistön ollessa auki että sivun mahdollisen vaakavierityksen takia — ilman korjausta paneeli voi jäädä osittain ruudun ulkopuolelle tai näppäimistön peittoon. `chat.js` pinnaa auki olevan paneelin `window.visualViewport`-rajapinnalla tarkasti näkyvään viewporttiin (`resize`/`scroll`-kuuntelu + `matchMedia`-rajan ylityksen käsittely), inline `left`/`top`/`width`/`height` korvaa CSS:n oletusarvot avattaessa ja puretaan suljettaessa (`chat.css`:n `@media (max-width: 640px)` toimii fallbackina selaimille ilman `visualViewport`-tukea).
- **Disclaimer** näkyy paneelin yläosassa: "Tekoälyavustaja. Älä syötä arkaluonteisia tietoja; varmista tärkeät asiat sähköpostitse."
- **Syöteraja** (`maxlength`) jaetaan backendin kanssa: `rytkoset_theme_chat_get_max_input_length()`.

### Sivuvälimuisti-caveat

Widgetin nonce (`wp_create_nonce('wp_rest')`) upotetaan sivun HTML:ään `wp_add_inline_script`illä. Jos sivustolla on **täyssivuvälimuisti**, kirjautumattoman kävijän nonce vanhenee ~12–24 h kuluttua ja REST-kutsu palauttaa "Istunto on vanhentunut" (HTTP 403). Tällöin ohita chat-sivujen välimuisti tai pidä TTL noncen elinikää lyhyempänä.

### Manuaalinen selaintestaus

Avaa chat kelluvasta painikkeesta, lähetä kysymys → "kirjoittaa…"-tila → suomenkielinen vastaus. Tarkista DevToolsin **Application**-välilehdeltä, ettei keksejä tai `localStorage`-merkintöjä synny — vain `sessionStorage`-avain `rytkosetChat.v1`. Testaa mobiilileveys (390 px), tumma teema, näppäimistö (Tab/Esc), ruudunlukija (`aria-live`) ja `<script>`-syöte (renderöityy tekstinä, ei suoritu).

**Istunnon säilyminen (#498):** käy keskustelu, lataa sivu uudelleen tai siirry chatin antamasta oman sivuston linkistä toiselle sivulle → keskustelu ja auki ollut paneeli palautuvat (fokus ei siirry syötekenttään palautuksessa). Sulje välilehti ja avaa sivu uudessa välilehdessä → keskustelu on tyhjä. Estä storage (esim. Firefoxin `dom.storage.enabled=false`) → chatti toimii muistinvaraisesti ilman JS-virheitä.

**Oikealla mobiililaitteella (ei vain DevToolsin emulaattorilla — `visualViewport`-käyttäytyminen eroaa):** avaa paneeli, avaa virtuaalinäppäimistö koskettamalla syötekenttää → koko paneeli (viestiloki + syötekenttä + lähetyspainike) pysyy näkyvässä osassa ruutua näppäimistön yläpuolella, ei leikkaudu eikä jää näppäimistön alle. Yritä vetää sivua sivuttain paneelin ollessa auki → paneeli ei siirry pois paikaltaan.
