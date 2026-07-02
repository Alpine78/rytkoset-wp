# AI-tukichatti: backend-proxy, Mistral-integraatio ja ylläpito

AI-tukichatti tarvitsee palvelinpuolen välityskerroksen, jotta Mistralin API-avain **ei koskaan** päädy selaimeen. Kokonaisuus on toteutettu neljässä siivussa (EPIC 11 / #411): backend-proxy ja kulusuojat (**#412**), chat-widget (**#413**), Customizer-asetukset, dokumentaatio ja GDPR (**#414**) sekä automaattisesti koottu ajantasainen tietolohko (**#459**).

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
| Vastauksen `max_tokens` | 512 | `rytkoset_theme_chat_max_tokens` |

- **Rate limit**: kiinteä ikkuna transientilla (`rytkoset_chat_rl_<md5(ip)>`), IP luetaan vain `REMOTE_ADDR`:sta (välityspalvelinotsakkeisiin ei luoteta). Ylitys → HTTP 429.
- **Syöte- ja historiarajat**: `rytkoset_theme_chat_prepare_messages()` säilyttää vain `user`/`assistant`-roolit, sanitoi sisällön (`sanitize_textarea_field`), katkaisee jokaisen viestin merkkirajaan ja leikkaa historian viimeisimpiin viesteihin **ennen** API-kutsua.

## System-prompt

`rytkoset_theme_chat_get_system_prompt()` kokoaa promptin, joka ohjeistaa assistentin: vastaa **vain suomeksi**, pysy yhdistyksen aiheissa, käytä faktoihin vain promptissa annettuja lähteitä, **älä keksi tietoa** ja ohjaa epävarmoissa sähköpostiin (`rytkoset_theme_get_contact_email()`). Se myös kieltää täydentämästä puuttuvia kohtia yleisellä tiedolla, WordPress-oletuksilla tai arvauksilla tulevista suunnitelmista, henkilöistä, julkaisujen saatavuudesta, tuotteiden ostettavuudesta, käyttöoikeuksista tai yksittäisten tilausten tilasta.

Promptin tietolähteet:

1. **Pysyvä sivustokonteksti** (`rytkoset_theme_chat_get_stable_site_context()`): sivuston peruspolut ja vakioidut toimintalogiikat, joita mallin ei pidä päätellä. Mukana ovat mm. `/kauppa/`, `/oma-tili/tilaukset/`, `/foorumi/`, `/blogi/`, `/digilehdet/`, some-linkit, ehdollinen maksun jatkaminen, foorumin käytössäolo, blogitekstien vastaanotto ylläpidon kautta, digilehtien HTML-muoto, sukukirjan kirjastolainaus, julkaistu `Rytkösten sukulainen nro 9` -tuote sekä hallituksen sivu ja puheenjohtaja.
2. **Customizerin Tietopohja/FAQ-kenttä** (#414): ylläpitäjän vapaamuotoinen tietopohja vakiintuneille yhdistys- ja toimintaohjeille.
3. **Automaattinen ajantasainen tietolohko** (#459): tulevat tapahtumat ja julkaistut jäsenyystuotteet sivuston omista lähteistä.

Promptin voi korvata tai laajentaa suodattimella `rytkoset_theme_chat_system_prompt` (argumentit: `$prompt`, `$contact_email`). Pysyvää sivustokontekstia voi muokata suodattimella `rytkoset_theme_chat_stable_site_context`.

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
- "Onko sukuseuralla some-tilejä?" -> pitää tunnistaa sivuston some-linkit.
- "Saanko digilehden PDF:nä?" -> ei saa luvata PDF-latausta, ellei tietopohjaan ole lisätty erillistä ohjetta.
- "Miten voin yrittää epäonnistunutta maksua uudelleen?" -> pitää käyttää ehdollista muotoa: vain jos tilauksella näkyy **Maksa / yritä uudelleen** -painike.

## Ajantasainen tietolohko (#459)

FAQ:n lisäksi system-promptiin liitetään **automaattisesti koottu** tietolohko, joka luetaan suoraan samoista lähteistä kuin sivuston omat näkymät — sitä ei ylläpidetä käsin, eikä se vanhene:

- **Tulevat tapahtumat** (`rytkoset_event`, julkaistut, tapahtumapäivä tänään tai myöhemmin; tapahtumapäivä on voimassa päivän loppuun): nimi, päivämäärä, kellonaika, paikka, hinta, ilmoittautumisen takaraja samasta lähteestä kuin tapahtumasivun yhteenvetokortilla (maksuttomat: oma takaraja tai tapahtumapäivä; linkitetyt maksulliset tuotteet: tuotteen takaraja), #450-lisävalinnat (esim. bussin lähtöpaikat + määräkentän nimi) ja tapahtuman osoite. Maksulliset tapahtumat ohjataan tapahtumasivulle. Enintään 5 tapahtumaa aikajärjestyksessä. Jos tulevia tapahtumia ei ole, lohko toteaa sen eksplisiittisesti, ettei malli keksi tapahtumia.
- **Jäsenyystuotteet** (`_rytkoset_membership_product = yes`, vain julkaistut): nimi, hinta, jäsenyystyyppi ja "jäsenyys voimassa X asti" -päivä. Osio jää kokonaan pois, jos WooCommerce ei ole aktiivinen (fail-safe).

Lohkon perään lisätään ohje, ettei malli arvioi vapaita paikkoja tai ilmoittautumistilannetta vaan ohjaa tapahtumasivulle. Toteutus: `rytkoset_theme_chat_get_live_context()` + apufunktiot (`..._get_upcoming_event_ids()`, `..._format_event_context()`, `..._get_membership_context()`) `inc/chat.php`:ssä; ei välimuistia (kevyet kyselyt ajetaan vain chat-pyynnön yhteydessä, rate limit rajaa volyymin).

Suodattimet:

| Suodatin | Oletus | Vaikutus |
|---|---|---|
| `rytkoset_theme_chat_live_context_enabled` | `true` | Koko lohkon voi kytkeä pois |
| `rytkoset_theme_chat_live_context_max_events` | 5 | Tapahtumien enimmäismäärä |
| `rytkoset_theme_chat_live_context_max_length` | 4000 | Lohkon merkkiraja (katkaisu) |

Työnjako FAQ:n kanssa: **rakenteinen, muuttuva tieto** (päivämäärät, hinnat, lähtöpaikat) tulee tästä lohkosta automaattisesti — sitä ei tarvitse eikä kannata kopioida FAQ:hun. FAQ:hun kirjoitetaan vain vakaat faktat ja toimintaohjeet (maksaminen, käytännöt, historia).

Maksuohjeissa ei pidä luvata, että kaikki epäonnistuneet tai keskeneräiset
Mollie-tilaukset voi aina vaihtaa itse toiseen maksutapaan. Käytä muotoa:
"Avaa Oma tili -> Tilaukset. Jos tilauksen kohdalla näkyy Maksa / yritä
uudelleen -painike, voit jatkaa maksua ja valita kassalla toisen maksutavan.
Jos painiketta ei näy, ota yhteyttä sähköpostitse." Toteutus ja tarkempi
rajaus on dokumentoitu tiedostossa `docs/woocommerce-mollie-payments.md`.

## Palveluntarjoajan vaihto (Mistral ↔ Azure Sweden Central)

Integraatio on tarkoituksella tehty vaihdettavaksi: chatti kutsuu geneeristä **chat-completions-rajapintaa** (`POST {endpoint}` + `Authorization: Bearer {key}` + `{model, messages, max_tokens, temperature}` → `choices[0].message.content`), ja kaikki kolme parametria luetaan `wp-config.php`-vakioista. Palveluntarjoajan vaihto on siis konfiguraatiomuutos, **ei koodimuutos**, kunhan uusi tarjoaja toteuttaa saman rajapintamuodon:

1. Hanki uuden tarjoajan API-avain ja chat-completions-päätepisteen koko URL (esim. Mistral-malli Azure AI:n Sweden Central -alueella).
2. Päivitä `RYTKOSET_CHAT_API_KEY`, `RYTKOSET_CHAT_API_ENDPOINT` ja tarvittaessa `RYTKOSET_CHAT_API_MODEL` kohdeympäristön `wp-config.php`:hen.
3. Aja alla oleva curl-testi ja varmista suomenkielinen vastaus.

Tarkista vaihdon yhteydessä tarjoajan dokumentaatiosta erityisesti: (a) hyväksyykö päätepiste `Authorization: Bearer` -otsakkeen — koodi lähettää vain sen, joten esim. pelkkää `api-key`-otsaketta vaativa päätepiste vaatisi pienen muutoksen `inc/chat.php`:n `wp_remote_post()`-kutsuun; (b) mallin nimi kyseisessä palvelussa; (c) että data käsitellään EU:ssa (GDPR — päivitä myös tietosuojaseloste, ks. alla).

## Tietosuoja (GDPR)

Chatin tietosuojaominaisuudet, jotka selosteen ja dokumentaation väitteet perustuvat koodiin:

- Viestit välitetään Mistralin **EU-päätepisteeseen** palvelimen kautta; API-avain ja kävijän IP eivät koskaan välity Mistralille (Mistral näkee vain palvelimen IP:n).
- Keskusteluja **ei tallenneta** WordPressiin eikä selaimeen: historia elää vain sivun JS-muistissa ja katoaa sivun sulkeutuessa. Ei evästeitä eikä web storagea → ei suostumusbanneritarvetta.
- Rate limit käsittelee kävijän IP-osoitetta lyhytaikaisesti palvelimella (transient, MD5-tiivisteenä avaimessa, enintään rajoitusikkunan ajan). IP:tä ei yhdistetä keskustelun sisältöön.
- Widgetin disclaimer kehottaa olemaan syöttämättä arkaluonteisia tietoja, ja system-prompt ohjeistaa mallia olemaan pyytämättä niitä.

### Tekstiehdotus tietosuojaselosteeseen

Selosteteksti on sivun sisältöä (ei koodia), joten alla oleva on **ehdotus ylläpidolle** lisättäväksi tietosuojaseloste-sivulle. Täydennä yhteysosoite ja tarkista Mistral-linkin ajantasaisuus:

> **Tekoälyavusteinen tukichatti**
>
> Sivustolla on tekoälyavusteinen tukichatti, joka vastaa sukuseuraa ja sivuston käyttöä koskeviin kysymyksiin. Vastaukset tuottaa Mistral AI:n kielimalli. Chattiin kirjoitetut viestit lähetetään käsiteltäviksi Mistral AI:lle (Mistral AI SAS, Ranska), ja ne käsitellään Euroopan unionin alueella. Viestejä käytetään vain vastauksen tuottamiseen.
>
> Sivusto ei tallenna chat-keskusteluja: keskustelu säilyy vain selaimesi muistissa ja tyhjenee, kun suljet sivun. Chatti ei käytä evästeitä. Väärinkäytön estämiseksi sivusto käsittelee kävijän IP-osoitetta lyhytaikaisesti viestimäärän rajoittamista varten; IP-osoitetta ei yhdistetä keskustelujen sisältöön eikä luovuteta chat-palvelun tarjoajalle.
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

Yksikkötestit (`tests/ChatProxyTest.php`) kattavat puhtaat helperit: rate limit -päätös, viestien valmistelu/katkaisu, vastauksen poiminta ja system-prompt. Verkko- ja REST-kytkentä varmistetaan yllä olevalla manuaalisella curl-testillä.

## Käyttöliittymä (chat-widget, #413)

Kelluva chat-painike + paneeli (`inc/chat.php` renderöi kuoren `wp_footer`-koukussa, `assets/js/chat.js` + `assets/css/chat.css`). Rakentuu yllä kuvatun REST-reitin päälle.

- **Näkyy vain kun backend on konfiguroitu ja chatti on kytketty päälle.** `rytkoset_theme_chat_widget_is_enabled()` palauttaa `true` vasta kun `RYTKOSET_CHAT_API_KEY` + `RYTKOSET_CHAT_API_ENDPOINT` on asetettu **ja** Customizerin Tukichatti-kytkin on päällä (#414) — muuten widgetiä ei renderöidä eikä assetteja ladata. Näyttämisen voi pakottaa/estää suodattimella `rytkoset_theme_chat_widget_enabled`.
- **Keksitön:** keskusteluhistoria elää **vain JS-muistissa**. Ei `localStorage`/`sessionStorage`/keksejä → keksibanneria ei tarvita. Sivun lataus nollaa keskustelun (hyväksytty MVP-rajaus).
- **Turvallinen renderöinti:** mallin vastaus lisätään DOMiin `textContent`illä (ei `innerHTML`) → ei XSS-riskiä. Rivinvaihdot säilyvät CSS:n `white-space: pre-wrap`illä.
- **Saavutettavuus (WCAG 2.1 AA):** paneeli `role="dialog"`, viestiloki `role="log" aria-live="polite"` (uudet vastaukset ilmoitetaan ruudunlukijalle), näkyvä fokus, näppäimistökäyttö (Enter lähettää, Shift+Enter rivinvaihto, **Esc** sulkee ja palauttaa fokuksen painikkeeseen). Fokusansaa ei ole (paneeli ei ole modaali) — MVP-rajaus.
- **Tumma teema:** widget käyttää vain design-tokeneita, jotka adaptoivat `:root[data-theme="dark"]`-teemaan.
- **Disclaimer** näkyy paneelin yläosassa: "Tekoälyavustaja. Älä syötä arkaluonteisia tietoja; varmista tärkeät asiat sähköpostitse."
- **Syöteraja** (`maxlength`) jaetaan backendin kanssa: `rytkoset_theme_chat_get_max_input_length()`.

### Sivuvälimuisti-caveat

Widgetin nonce (`wp_create_nonce('wp_rest')`) upotetaan sivun HTML:ään `wp_add_inline_script`illä. Jos sivustolla on **täyssivuvälimuisti**, kirjautumattoman kävijän nonce vanhenee ~12–24 h kuluttua ja REST-kutsu palauttaa "Istunto on vanhentunut" (HTTP 403). Tällöin ohita chat-sivujen välimuisti tai pidä TTL noncen elinikää lyhyempänä.

### Manuaalinen selaintestaus

Avaa chat kelluvasta painikkeesta, lähetä kysymys → "kirjoittaa…"-tila → suomenkielinen vastaus. Tarkista DevToolsin **Application**-välilehdeltä, ettei keksejä tai web storagea synny. Testaa mobiilileveys (390 px), tumma teema, näppäimistö (Tab/Esc), ruudunlukija (`aria-live`) ja `<script>`-syöte (renderöityy tekstinä, ei suoritu).
