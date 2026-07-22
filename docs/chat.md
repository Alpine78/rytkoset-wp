# AI-tukichatti: backend-proxy, Mistral-integraatio ja ylläpito

AI-tukichatti tarvitsee palvelinpuolen välityskerroksen, jotta Mistralin API-avain **ei koskaan** päädy selaimeen. Kokonaisuus on toteutettu useassa siivussa (EPIC 11 / #411): backend-proxy ja kulusuojat (**#412**), chat-widget (**#413**), Customizer-asetukset, dokumentaatio ja GDPR (**#414**), automaattisesti koottu ajantasainen tietolohko tapahtumille ja jäsenyystuotteille (**#459**), sen laajennus muuhun verkkokaupan tuotekatalogiin (**#471**) sekä kulusuojien osumien ja peruskäyttölukujen näkyminen wp-adminissa (**#472**).

Toteutus: `inc/chat.php`. Teeman koodia, ei ulkoisia kirjastoja. Tämä on teeman **ensimmäinen REST-päätepiste**.

## EU:n tekoälyasetuksen luokittelu ja tekoälylukutaito (#470)

Arvioitu 15.7.2026 asetuksen (EU) 2024/1689 perusteella:

- **Riskiluokka:** rajoitettu riski, korkea varmuus. Chatti keskustelee luonnollisten henkilöiden kanssa ja tuottaa tekstiä, mutta se ei kuulu liitteen III korkean riskin käyttötarkoituksiin. Se ei käytä biometriaa, profiloi käyttäjiä, tunnista tunteita tai tee henkilöitä koskevia päätöksiä.
- **Rooli (työoletus, päivitetty 21.7.2026):** yhdistys on **tarjoaja ja käyttöönottaja** (3 artiklan 3 ja 11 kohta). Yhdistys rakensi välityskerroksen, system-promptin, `lue_sivu`-työkalun ja widgetin Mistralin GPAI-mallin päälle ja ajaa niitä omissa nimissään, joten pelkkä käyttöönottaja-rooli ei ole varovainen oletus. Roolin lopullinen vahvistus juristin kanssa tehdään tiketissä #564; 50 artiklan 1 ja 2 kohdan toteutus tehdään joka tapauksessa.
- **50 artiklan 1 kohta:** käyttäjälle kerrotaan ennen viestin lähettämistä, että kyseessä on tekoälyavustaja. Paneelissa näkyy teksti: "Tekoälyavustaja. Älä syötä arkaluonteisia tietoja; varmista tärkeät asiat sähköpostitse." 50 artiklan 5 kohdan "selkeästi ja erottuvasti" -vaatimuksen vuoksi teksti on normaalilla tekstivärillä (ei muted-sävyllä) ja sana "Tekoälyavustaja" on korostettu (`<strong>`) (#600).
- **50 artiklan 2 kohta:** toteutettu tekninen minimi #600:ssa — katso alla oleva päivätty toteutettavuusperustelu.
- **50 artiklan 4 kohta:** chatti vastaa yksittäisen käyttäjän kysymykseen eikä julkaise vastauksia yleisölle yleistä etua koskevana sisältönä. Jos chatin tekstiä myöhemmin julkaistaan verkkosivun sisältönä, ihmisen toimituksellinen tarkistus ja tarvittava AI-merkintä arvioidaan erikseen.
- **Aikataulu:** 50 artiklan velvoitteita sovelletaan 2.8.2026 alkaen. Tekoälylukutaitoa koskevaa 4 artiklaa on sovellettu 2.2.2025 alkaen.

### 50 artiklan 2 kohdan toteutus ja toteutettavuusperustelu (kirjattu 21.7.2026, #600)

**Toteutettu koneellisesti luettava merkintä:**

1. REST-vastauksessa kenttä `ai_generated: true` jokaisessa onnistuneessa chat-vastauksessa (`rytkoset_theme_chat_build_response_body()`, `inc/chat.php`). #604:n deterministinen lähdevastaus säilyttää saman konservatiivisen merkinnän, jotta REST-/DOM-sopimus ei haarautuisi vastaustavan mukaan.
2. DOM:ssa attribuutti `data-ai-generated="true"` jokaisessa assistentin viestielementissä (`appendMessage()`, `assets/js/chat.js`) — kattaa sekä live-vastaukset että `sessionStorage`-palautuksen. Palvelimella renderöity tervetuloviesti on ylläpitäjän kirjoittamaa tekstiä, ei mallin tuottamaa, joten sitä ei tarkoituksella merkitä.

**Toteutettavuusperustelu (50 artiklan 2 kohdan sanamuoto):** merkintöjen on oltava tehokkaita, yhteentoimivia, tunnistettavia ja luotettavia "siinä määrin kuin se on teknisesti mahdollista, ottaen huomioon eri sisältötyyppien erityispiirteet ja rajoitukset, toteuttamiskustannukset ja yleisesti tunnustettu viimeisin kehitys". Tähän nojaten toteutus rajataan yllä kuvattuun tekniseen minimiin (JSON-kenttä + data-attribuutti), koska:

- Chatin tuotos on kolmannen osapuolen GPAI-mallin tuottamaa **paljasta tekstiä**. Tekstin robusti vesileimaus edellyttää mallin token-tason näytteistykseen (logit-jakaumaan) puuttumista, mikä on mahdollista vain mallin tarjoajalle — ei API-asiakkaalle, joka vastaanottaa valmiin tekstin.
- Yleisesti tunnustettu alkuperämerkintästandardi C2PA kattaa käytännössä mediatiedostot (kuva, ääni, video), ei API:sta palautuvaa paljasta tekstivastausta. Yhdistyksen sovellettavissa olevaa robustia, yhteentoimivaa tekstivesileimaa ei ole.
- Yhdistys on pieni yleishyödyllinen toimija; erillisen vesileimainfrastruktuurin rakentaminen ei ole oikeasuhtaista toteuttamiskustannuksiin nähden.

**Mistralin oma merkintä (tarkistettu 21.7.2026):** Mistralin virallinen API-referenssi ([docs.mistral.ai/api/endpoint/chat](https://docs.mistral.ai/api/endpoint/chat)) ei dokumentoi vesileima- tai alkuperämerkintäparametria pyyntöön eikä merkintäkenttää tai -otsaketta vastaukseen (pyyntöparametrit ja vastauskentät käyty läpi; ei `watermark`-, provenance- tai C2PA-mainintoja). Eräät kolmannen osapuolen sivustot väittävät Mistralilla olevan vesileimaominaisuuksia, mutta väitteelle ei löytynyt tukea virallisesta dokumentaatiosta — asiaa ei oleteta ilman lähdettä. Jos Mistral myöhemmin lisää vastauksiinsa oman merkinnän, se välittyy tämän toteutuksen rinnalla eikä korvaa sitä.

**Tarkistuspäivä:** arvioi tämä perustelu ja toteutuksen riittävyys uudelleen, kun (a) komissio julkaisee 50 artiklan 7 kohdan mukaiset lopulliset käytännesäännöt merkinnästä (toinen luonnos julkaistu keväällä 2026, lopullinen odotettavissa ennen 2.8.2026) tai (b) Digital Omnibus (poliittinen yhteisymmärrys 7.5.2026) julkaistaan virallisessa lehdessä — se siirtäisi ennen 2.8.2026 käyttöön otettujen järjestelmien merkintävelvoitteen 2.2.2027:ään, mutta sitä ei sovelleta ennen julkaisua. Tarkista tilanne viimeistään **2.8.2026**.

### Ylläpitäjän vähimmäisperehdytys

Jokaisen chatin asetuksia, tietopohjaa, mallia tai tuotantokäyttöä hoitavan on ennen tehtävää:

1. luettava tämä dokumentti ja [`docs/tietosuoja.md`](tietosuoja.md)
2. ymmärrettävä, että kielimalli voi antaa väärän tai keksityn vastauksen myös silloin, kun vastaus vaikuttaa varmalta
3. pidettävä FAQ:ssa ja testikysymyksissä vain julkista tietoa, ei henkilötietoja tai jäsenille rajattua sisältöä
4. testattava olennaiset sisältö-, malli- ja promptimuutokset dev-ympäristössä alla olevilla savutesteillä
5. seurattava Dashboard-widgetin virhe- ja käyttötilastoja sekä kytkettävä chatti pois päältä, jos se vuotaa rajattua tietoa tai antaa toistuvasti haitallisia vastauksia
6. ohjattava henkilökohtaiset, maksamiseen liittyvät ja muut merkittävät asiat ihmisen käsiteltäväksi.

Perehdytyksen suorittajat, päivämäärä, käytetty materiaali ja vastuuhenkilön hyväksyntä kirjataan yhdistyksen sisäiseen koulutusmuistioon; henkilönimiä ei tallenneta julkiseen repoon. Aiempi tehtävään soveltuva työkokemus, koulutus ja sertifioinnit voidaan ottaa huomioon osaamisnäyttönä. Niitä täydennetään vain tämän chatin toimintaa, henkilötietoja, rajoituksia ja ihmisen valvontaa koskevilla puuttuvilla tiedoilla. Pelkkä tämän ohjeen olemassaolo ei osoita, että riittävä osaaminen on arvioitu.

Lähde: [asetus (EU) 2024/1689, erityisesti 3, 4 ja 50 artikla](https://eur-lex.europa.eu/legal-content/FI/TXT/?uri=CELEX:32024R1689). Luokittelu ja rooliarvio ovat tarkistettavia ensiarvioita, eivät sitova oikeudellinen kannanotto.

50 artiklan 2 kohdan tekninen toteutus tehtiin tiketissä [#600](https://github.com/Alpine78/rytkoset-wp/issues/600) (katso yllä oleva toteutettavuusperustelu). Tuotantoroolin vahvistaminen juristin kanssa ja perehdytyksen todentaminen jäävät jatkotikettiin [#564](https://github.com/Alpine78/rytkoset-wp/issues/564).

## Päätepiste

```
POST /wp-json/rytkoset/v1/chat
```

- Julkinen reitti (`permission_callback => __return_true`).
- Suojana `wp_rest`-nonce (`X-WP-Nonce`-otsake), IP-pohjainen rate limit sekä syöte-, historia- ja token-rajat.
- Pyynnön runko (JSON): `messages`-taulukko, jossa kukin alkio `{ "role": "user" | "assistant", "content": "…" }`.
- Vastaus (JSON): `{ "reply": "…", "ai_generated": true }`. `ai_generated` on tekoälyasetuksen 50 artiklan 2 kohdan koneellisesti luettava merkintä tekoälyn tuottamasta sisällöstä (#600). Virhetilanteessa WordPressin REST-virhemuoto (`code`, `message`, `data.status`).

## Konfiguraatio (`wp-config.php`-vakiot)

Avainta **ei kirjata repoon**. Aseta kohdeympäristön `wp-config.php`:hen:

```php
define( 'RYTKOSET_CHAT_API_KEY', 'sk-...' );                              // Pakollinen. Mistralin API-avain.
define( 'RYTKOSET_CHAT_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions' ); // Pakollinen. Mistralin EU-endpoint (koko URL).
define( 'RYTKOSET_CHAT_API_MODEL', 'mistral-small-latest' );             // Valinnainen. Oletus: mistral-small-latest.
define( 'RYTKOSET_CHAT_PROMPT_CACHE_KEY', 'rytkoset-chat-dev-v1' );       // Valinnainen dev-kokeilu. Puuttuva/tyhjä = pois käytöstä.
```

Jos `RYTKOSET_CHAT_API_KEY` tai `RYTKOSET_CHAT_API_ENDPOINT` puuttuu, reitti palauttaa **hallitun virheen** (HTTP 503) — ei PHP-fatalia. Avain luetaan vain palvelimella eikä sitä koskaan tulosteta vasteeseen tai lokiin.

`RYTKOSET_CHAT_PROMPT_CACHE_KEY` on oletuksena pois käytöstä. Kun vakio sisältää arvon ja endpointin isäntä on täsmälleen `api.mistral.ai`, backend lisää Mistralin payloadiin `prompt_cache_key`-kentän. Kenttää ei lähetetä Azurelle tai muille palveluntarjoajille. Käytä ympäristöä ja kokeiluversiota kuvaavaa vakaata arvoa, kuten `rytkoset-chat-dev-v1` — älä johda avainta käyttäjästä, IP-osoitteesta, viestistä tai muusta henkilötiedosta. Avaimen arvoa ei tallenneta käyttötilastoihin eikä näytetä Dashboard-widgetissä.

## Kulusuojat

Kaikki oletukset ovat suodatettavia:

| Suoja                                               | Oletus                  | Suodatin                                   |
| --------------------------------------------------- | ----------------------- | ------------------------------------------ |
| Rate limit (viestiä / IP / ikkuna)                  | 20                      | `rytkoset_theme_chat_rate_limit`           |
| Rate limit -ikkuna                                  | `HOUR_IN_SECONDS` (1 h) | `rytkoset_theme_chat_rate_window`          |
| Yksittäisen viestin merkkiraja                      | 1000                    | `rytkoset_theme_chat_max_input_length`     |
| Historian pituus (viimeisimmät viestit)             | 8                       | `rytkoset_theme_chat_max_history`          |
| Vastauksen `max_tokens`                             | 800                     | `rytkoset_theme_chat_max_tokens`           |
| Mallin `temperature` (0–1)                          | 0.2                     | `rytkoset_theme_chat_temperature`          |
| Mallin `frequency_penalty` (0–2)                    | 0.3                     | `rytkoset_theme_chat_frequency_penalty`    |
| Käyttäjälle hyväksyttävän vastauksen merkkiraja     | 3000                    | kiinteä turvaraja                          |
| Työkalukierrokset / käyttäjäviesti (#501)           | 2 (kova yläraja 3)      | `rytkoset_theme_chat_page_tool_max_rounds` |
| Työkalun palauttaman sivusisällön merkkiraja (#501) | 5000                    | `rytkoset_theme_chat_page_tool_max_length` |
| Nimetyn lähteen palvelinhaun sisältöraja            | 60 (kova yläraja 100)   | `rytkoset_theme_chat_prefetch_max_pages`   |
| Varmennetun lähdeotteen merkkiraja                  | 3000 (800–5000)         | `rytkoset_theme_chat_prefetch_max_length`  |

- **Rate limit**: kiinteä ikkuna transientilla (`rytkoset_chat_rl_<md5(ip)>`), IP luetaan vain `REMOTE_ADDR`:sta (välityspalvelinotsakkeisiin ei luoteta). Ylitys → HTTP 429. Huom: raja on IP-kohtainen, ei käyttäjäkohtainen — saman verkon (esim. sama WiFi tai operaattorin NAT) kävijät jakavat saman laskurin.
- **Syöte- ja historiarajat**: `rytkoset_theme_chat_prepare_messages()` säilyttää vain `user`/`assistant`-roolit, sanitoi sisällön (`sanitize_textarea_field`), katkaisee jokaisen viestin merkkirajaan ja leikkaa historian viimeisimpiin viesteihin **ennen** API-kutsua. Rikkinäisen mallivastauksen tunnistava validaattori pudottaa epäkelvon `assistant`-rivin historiasta ennen kuin historia lähetetään takaisin Mistralille (#604); käyttäjän omiin riveihin sisältövalidaattoria ei sovelleta. Widget poistaa kuitenkin juuri lähetetyn käyttäjärivin omasta tulevasta historiastaan, jos REST-pyyntö epäonnistuu, jotta seuraava onnistunut vastaus ei vastaa samalla aiempaan epäonnistuneeseen kysymykseen. Kysymys ja virheilmoitus jäävät nykyisen näkymän lokiin, mutta eivät seuraavaan REST-payloadiin tai `sessionStorage`en.
- **Temperature**: matala oletus (0.2), koska tukichatin vastaukset ovat faktavastauksia — satunnaisuus lisäisi vain epäjohdonmukaisuutta. Arvo rajataan välille 0–1.
- **Frequency penalty**: Mistralin dokumentaatio esittää rangaistusparametrit nimenomaan keinona välttää toistosilmukat, joihin malli voi pitkässä kontekstissa tai pitkässä vastauksessa jäädä. Rangaistus kertyy toistuvaa tokenia kohden, joten matalakin arvo voimistuu nopeasti heti kun ilmaus alkaa toistua. Oletus on tarkoituksella varovainen (0.3): suomenkielinen tukivastaus toistaa luonnostaan aihesanoja kuten "sukuseura" tai "jäsenyys", ja liian voimakas rangaistus vääristää sanamuotoja. Arvo rajataan välille 0–2 — negatiivinen rangaistus *lisäisi* toistoa, mitä ei koskaan haluta. Jos toistosilmukka toistuu vielä oletusarvolla, nosta suodattimella asteittain (0.5 → 1.0) ja todenna sama kysymys uudelleen.

> **Tausta (#507):** dev-ympäristössä havaittiin toistuva silmukka, jossa kysymys "Mikä on sukuseuran tilikausi?" session ensimmäisenä viestinä tuotti saman virheellisen väitteen kymmeniä kertoja peräkkäin, Markdown-lihavointia vastoin promptin ohjetta sekä mallin omia erikoismerkkijonoja (`<end_of_thinking>`, pitkiä `]]]]`- ja `000000`-jaksoja), kunnes `max_tokens` katkaisi vastauksen kesken sanan. Vika oli deterministinen (3/3 samalla syötteellä) kahdella eri koodiversiolla — myös ennen kuin `tool_choice: "any"` -pakotus ja käsite-erotteleva promptirivi olivat olemassa, joten kumpikaan niistä ei ollut syy. Dashboard-widgetin laskurit osoittivat epäonnistuneessa ajossa yhden API-kutsun ja **nolla** sivunlukua, kun sama pakotus toimi moitteettomasti kysymyksellä "Kuka on Marja-Liisa Patrikainen?" (kaksi API-kutsua, yksi sivunluku, oikea vastaus). Matala `temperature` tekee samplauksesta lähes deterministisen, joten sama syöte päätyi joka kerta samaan toistoattraktoriin. `frequency_penalty` osuu suoraan tähän mekanismiin. Dev-todennus 21.7.2026 oletusarvolla 0.3: silmukka ei enää toistunut ja vastaus oli oikein sekä jatketussa että tuoreessa istunnossa, joten arvoa ei tarvinnut nostaa.

### Tilikausivastauksen tuotantoturva (#604)

#507:n jälkeen sama kysymys epäonnistui tuotannossa puhtaassa istunnossa 3/3 kertaa noin 20,8 sekunnin aikakatkaisuun, vaikka tavallinen kontrollikysymys onnistui noin 2,7 sekunnissa. [Mistralin API-dokumentaation](https://docs.mistral.ai/api) `frequency_penalty` säilyy arvolla 0.3 toistosilmukan lievennyksenä, mutta se ei takaa lähteen lukemista eikä faktan oikeellisuutta. Siksi tilikausikysymys ei enää riipu Mistralista:

- Uusimman käyttäjäviestin `tilikausi`-sanan tavalliset suomen taivutusmuodot ohjataan ennen Mistral-kutsua `rytkoset_theme_chat_get_fiscal_year_source_reply()`-polulle.
- Polku hakee WordPressistä sivun täsmällisellä hierarkkisella polulla `sukuseura/saannot`, käyttää samaa julkaisu-, sivutyyppi-, salasana- ja jäsenrajausta kuin `lue_sivu`, poimii tekstiksi riisutusta sisällöstä kohdan 10 seuraavaan numeroituun otsikkoon asti ja palauttaa lähdetekstin sekä sivun permalinkin. Päivämääriä ei ole koodissa.
- Jos sivua, sen julkisuutta, kohtaa 10 tai permalinkkiä ei voida varmentaa, pyyntö palauttaa nykyisen turvallisen HTTP 502 -virheen. Mallilta ei pyydetä korvaavaa vastausta.
- Pakotettu `tool_choice: "any"` on muiden sääntö-/henkilökysymysten todellinen invariantti [Mistralin function calling -ohjeen](https://docs.mistral.ai/studio-api/conversations/function-calling) mukaisesti: ensimmäisellä kierroksella hyväksytään vain kelvollinen `lue_sivu`-kutsu, jolla on positiivinen `sivu_id`. Plain-text-vastaus, väärä työkalu tai virheelliset argumentit hylätään ilman retryä.
- Hallitus-, puheenjohtaja-, henkilö- ja teosnimikysymykset saavat ennen Mistralia palvelimen varmentaman lähdeotteen. Hallitussivu ratkaistaan täsmällisellä polulla `sukuseura/sukuseuran-hallitus`; muut nimet haetaan enintään 60 julkaistusta julkisesta sisällöstä. Teosnimen pitkät sanat osallistuvat pisteytykseen, jotta pelkkä yleinen ensimmäinen sana ei tee osumasta moniselitteistä. Sukukokouspaikan pitää esiintyä samalla rivillä/kappaleessa sukukokouskäsitteen kanssa; tällöin haku kattaa myös julkaistut salasanattomat `rytkoset_event`-tapahtumat ja voi antaa saman kokouksen tapahtuma- ja kuljetussivun yhdessä. Sivujen julkaisu-, salasana- ja jäsenrajaus säilyy; tapahtumista hyväksytään vain julkaistut ja salasanattomat. Ote rajataan yhteensä 3000 merkkiin ja Mistralille tehdään yksi tavallinen tekstikutsu asetuksella `tool_choice: "none"`. Jos nimettyyn faktakysymykseen ei löydy varmennettua osumaa tai osuma jää sallitun määrän jälkeen moniselitteiseksi, backend palauttaa turvallisen HTTP 200 -vastauksen "en löytänyt varmennettua vastausta chatin käytettävissä olevista julkisista lähteistä" ilman Mistral-kutsua. Vastausta ei kirjata Mistral-/yhteysvirheeksi.
- Kysymyksen muotoilu ei saa kaataa hallituskysymystä. Isolla alkukirjaimella alkava kysymys- tai käskysana (`Ketkä`, `Luettele`, `Listaa`, `Näytä`, `Voiko`, `Saako`) ohitetaan nimitermeissä, joten `Luettele hallituksen jäsenet.` toimii samoin kuin `Keitä on hallituksessa?`. Virkkeen alussa oleva oikea erisnimi säilyy hakuterminä (`Tampereen sukukokous, milloin se pidetään?`). Kun hallitus- tai puheenjohtajakysymyksessä ei ole sivulta varmennettua henkilönimeä, lähteeksi annetaan koko varmennettu hallitussivu yksittäisen riviotteen sijaan; näin myös kirjoitusvirheellinen kysymys saa oikean lähteen sen sijaan että vastaus kaatuisi lähteettömäksi.
- `kuka tahansa` ja `kuka vain` ovat indefiniittipronomineja eivätkä ohjaa henkilöhakuun, joten esimerkiksi `Voiko kuka tahansa liittyä sukuseuran jäseneksi?` vastataan tavallista reittiä sääntösivun perusteella. Vastaavasti `kirjasto`-alkuiset sanat eivät enää käynnistä teosnimihakua, joten `Saako sitä kirjastosta?` ei kaadu lähteettömäksi.
- Mallin lopullinen teksti hyväksytään vain, jos `finish_reason` on `stop`, teksti on enintään 3000 merkkiä eikä sisällä HTML-jäänteitä, ajatus-/erikoistokeneita, pitkiä merkki- tai roskamotiivijaksoja tai vähintään kolmesti toistuvaa samaa kahdeksan sanan jaksoa. Hylkäys palauttaa saman turvallisen 502:n ilman automaattista uusintaa.
- Virhetilastoon tallennetaan vain staattinen henkilötiedoton tyyppi: `direct_source_missing`, `forced_tool_missing`, `invalid_finish_reason` tai `invalid_reply`. Viestisisältöä tai `finish_reason`-arvoa ei kirjata.

Kytke tuotannon chatti Customizerista pois päältä ja pidä se pois, kunnes #604:n dev- ja tuotantosavutestit ovat valmiit. Malli, 20 sekunnin timeout, `frequency_penalty: 0.3` ja REST-vastauksen rakenne säilyvät tässä korjauksessa ennallaan.

### Dev-ympäristön löysemmät rajat

Pitkää testisessiota varten rate limitin ja keskustelumuistin voi ylikirjoittaa ympäristökohtaisesti `wp-config.php`-vakioilla (sama tiedosto, jossa chat-vakiot jo ovat — ei repossa, joten ei voi vuotaa tuotantoon):

```php
define( 'RYTKOSET_CHAT_RATE_LIMIT', 200 );  // dev: enemmän viestejä / IP / h (oletus 20)
define( 'RYTKOSET_CHAT_MAX_HISTORY', 30 );  // dev: pidempi keskustelumuisti (oletus 8 viestiä)
```

> Huom: `add_filter()`-kutsut **eivät** toimi `wp-config.php`:ssä (WordPress lataa sen ennen `plugin.php`:tä) — siksi ylikirjoitus on toteutettu vakioina, samaan tapaan kuin `RYTKOSET_CHAT_API_KEY`. Suodattimet (`rytkoset_theme_chat_rate_limit`, `..._max_history`) ajetaan vakion päälle ja sopivat teeman/mu-pluginin koodiin. Älä löysää tuotannon rajoja ilman erillistä päätöstä — ne ovat kulusuojia.

### Mistralin prompt-välimuistin dev-kokeilu (#567)

Mistralin dokumentaation mukaan sama `prompt_cache_key` kasvattaa yhteisen promptin alun välimuistiosuman todennäköisyyttä, mutta ei takaa osumaa. Välimuistista käytetyt syötetokenit näkyvät vastauksen `usage.prompt_tokens_details.cached_tokens`-kentässä ja kaikki syötetokenit `usage.prompt_tokens`-kentässä. Välimuistitokenit laskutetaan 10 prosentilla normaalista syötetokenien hinnasta.

Koodi säilyttää saman avaimen myös `lue_sivu`-työkalun sisäisillä jatkokierroksilla: työkalusilmukka muuttaa vain saman payloadin viesti- ja `tool_choice`-kenttiä. System-promptia ei siirretä Mistralin beta-Prompts-palveluun. Dev-koe 21.7.2026 osoitti mitattavan hyödyn ilman havaittua vastauslaadun tai työkalukäytön heikkenemistä. **Tuotantopäätös: ota käyttöön**, kun tämä toteutus viedään tuotantoon, omalla vakaalla henkilötiedottomalla avaimella kuten `rytkoset-chat-prod-v1`.

Dev-koe:

1. Aseta vain devin `wp-config.php`:hen `define( 'RYTKOSET_CHAT_PROMPT_CACHE_KEY', 'rytkoset-chat-dev-v1' );`. Arvon pitää pysyä samana koko kokeen ajan.
2. Nollaa vain kokeen henkilötiedoton tokenikoonti: `wp option delete rytkoset_chat_stat_prompt_cache` (paikallisessa Dockerissa komennon alkuun `docker compose run --rm wpcli`).
3. Lähetä vähintään yksi tavallinen peruskysymys toistuvasti samalla tai yhteisen alkuosan säilyttävällä keskustelulla. Savutestaa erikseen yksi kysymys, joka käyttää `lue_sivu`-työkalua.
4. Tarkista wp-adminin **Ohjausnäkymä → Tukichatti** -widgetistä mitattujen Mistral API-kutsujen määrä, syötetokenien kokonaismäärä, välimuistista käytetyt tokenit ja osumakutsujen määrä. Työkalukysymys voi kasvattaa API-kutsujen määrää useammin kuin viestimäärää, koska jokainen sisäinen kierros mitataan erikseen.
5. Kirjaa tikettiin tavallisen kysymyksen ja työkalukysymyksen toimivuus, `cached_tokens / prompt_tokens` sekä arvioitu syötekustannussäästö: `cached_tokens / prompt_tokens × 90 %`. Jos `cached_tokens` pysyy nollassa riittävän monen samanalkuisen pyynnön jälkeen, kirjaa käytetty malli, avain, pyyntömäärä ja se, muuttuuko system-promptin alkupää kokeen aikana.
6. Ota tuotantoon vain, jos osumia syntyy ja vastaukset sekä virheenkäsittely pysyvät ennallaan. Käytä tuotannolle omaa henkilötiedotonta arvoa; muuten jätä vakio määrittelemättä.

**Toteutunut dev-tulos 21.7.2026:** kokeen lopussa 9/12 Mistral API -kutsusta oli välimuistiosumia ja 68 336 / 93 989 syötetokenia tuli välimuistista (72,7 %; arvioitu syötekustannussäästö 65,4 %). Viimeisessä kutsussa välimuistista tuli 7 424 / 8 180 tokenia (90,8 %; arvioitu säästö 81,7 %). Viimeisessä laadullisessa testissä kaksi käyttäjäviestiä tuotti kolme API-kutsua ja kaksi `lue_sivu`-hakua; kaikki kolme API-kutsua olivat välimuistiosumia (22 160 / 23 087 tokenia eli 96,0 % välimuistista). Tavallinen kysymys palautti oikeat kaupan ja tilausten URLit ilman turhaa sivunlukua. `lue_sivu`-vastaus tunnisti Teuvo Rönkön pastoriksi ja Ylä-Savon Tyrmyn sukuhaaraa koskeneen selvityksen tekijäksi julkisen Sukututkimus-sivun mukaisesti. Rate limit- ja virhelaskurit eivät kasvaneet. Tulos täyttää #567:n dev-validoinnin ja puoltaa tuotantokäyttöä.

Koonti hyväksyy vain ei-negatiiviset kokonaisluvut ja ohittaa puuttuvan tai ristiriitaisen usage-rakenteen. Se tallentaa `wp_options`-tauluun vain tokenimäärät, osumakutsujen määrän ja viimeisimmän mittausajan — ei viestejä, IP-osoitteita eikä välimuistiavainta.

## System-prompt

`rytkoset_theme_chat_get_system_prompt()` kokoaa promptin, joka ohjeistaa assistentin: vastaa **vain suomeksi**, pysy yhdistyksen aiheissa, käytä faktoihin vain promptissa annettuja lähteitä, **älä keksi tietoa** ja ohjaa epävarmoissa sähköpostiin (`rytkoset_theme_get_contact_email()`). Se myös kieltää täydentämästä puuttuvia kohtia yleisellä tiedolla, WordPress-oletuksilla tai arvauksilla tulevista suunnitelmista, henkilöistä, julkaisujen saatavuudesta, tuotteiden ostettavuudesta, käyttöoikeuksista tai yksittäisten tilausten tilasta. Erillinen ohje kieltää nimenomaan **numeromuotoisten faktojen** (vuosiluvut, päivämäärät, hinnat, lukumäärät) arvaamisen tai päättelyn, kun tarkkaa lukua ei löydy lähteistä — nämä ovat kielimallien yleisin ja huomaamattomin hallusinaatiotyyppi, koska väärä luku ei itsessään paljasta epävarmuutta (#480: tuotannossa havaittu konkreettinen tapaus, jossa sama lähteetön kysymys tuotti kerran asianmukaisen kieltäytymisen ja kerran keksityn vuosiluvun). Lisäksi prompt sisältää tulostyylisäännöt: vastaus pelkkänä tekstinä ilman markdownia, ja sivuston linkit täysinä paljaina osoitteina (`home_url()`-pohja) — widget muuttaa ne turvallisesti klikattaviksi.

Promptin tietolähteet:

1. **Pysyvä sivustokonteksti** (`rytkoset_theme_chat_get_stable_site_context()`): sivuston peruspolut ja vakioidut toimintalogiikat, joita mallin ei pidä päätellä. Mukana ovat mm. `/kauppa/`, `/oma-tili/tilaukset/`, `/tapahtumat/`, `/albumit/`, `/foorumi/`, `/blogi/`, `/digilehdet/`, some-linkit, ehdollinen maksun jatkaminen, foorumin käytössäolo, blogitekstien vastaanotto ylläpidon kautta, digilehtien HTML-muoto, sukukirjan kirjastolainaus, julkaistu `Rytkösten sukulainen nro 9` -tuote sekä hallituksen sivu ja koko hallituslista. Lisäksi kaksi devin savutestin jälkeen lisättyä riviä: **maksutapoja ei saa luetella muistista** vaan Maksu- ja toimitusehdot -sivulta (malli listasi yleisluontoisesti myös osamaksun, jota ei ole käytössä), ja **chatti kuvaa oman tietojenkäsittelynsä** — viestit välitetään EU-alueen tekoälypalveluun, vastauksen pohjaksi lähetetään sivuston julkaistua sisältöä, keskusteluja ei tallenneta palvelimelle, evästeitä ei käytetä ja IP-osoitetta käsitellään lyhytaikaisesti viestimäärän rajoittamiseksi. Malli vastasi aiemmin kysymykseen `Onko tämä turvallinen chatti?` väittämällä, ettei chatti käsittele henkilötietoja lainkaan; se on ristiriidassa yhdistyksen oman tietosuojaselosteen kanssa, joten rivi kieltää sekä tuon väitteen että yleisen turvallisuustakuun.
2. **Sivustokartta** (`rytkoset_theme_chat_get_sitemap_context()`): automaattisesti WordPressistä koottu lista sivuston julkaistuista sivuista (otsikko + permalink), julkaistuista tapahtumista (merkintä `(tapahtuma)`) ja albumeista (merkintä `(albumi)`) sekä tapahtuma- ja albumiarkistoista. Järjestys on tarkoituksellinen, koska lohkon 6000 merkin raja katkaisee lopun: tapahtumat ensin (niitä on vähän ja ne ovat ainoa lähde ohjelmakysymyksiin), sitten sivut ydintietona, viimeisenä albumit. Jos albumirivit katkeavat, palvelimen lähde-esihaku löytää albumin silti — se ei käytä sivustokarttaa. Jos lohko katkeaa niin, että sivujakin putoaa, nosta `..._sitemap_max_length`-arvoa tai laske `..._sitemap_max_pages`- / `..._sitemap_max_albums`-arvoa. Prompt kieltää viittaamasta muihin kuin lähteissä annettuihin osoitteisiin — tuotannossa havaittiin, että ilman sivukarttaa malli keksi osoitteen `/kuvat/`, jota ei ole olemassa. Uusi julkaistu sivu näkyy sivukartassa heti ilman koodimuutosta. Kun sivun lukutyökalu (#501) on käytössä, sivuriveillä on lisäksi `(sivu-id: N)` -merkintä ja julkisten sivujen otsikoista/erisnimistä koottu lyhyt `aiheita:`-hakuvihje, jolla malli voi valita oikean sivun `lue_sivu`-työkalulle. Hakuvihje ei ole faktavastauksen lähde.
3. **Customizerin Tietopohja/FAQ-kenttä** (#414): ylläpitäjän vapaamuotoinen tietopohja vakiintuneille yhdistys- ja toimintaohjeille.
4. **Automaattinen ajantasainen tietolohko** (#459, #471): tulevat tapahtumat, julkaistut jäsenyystuotteet ja muut verkkokaupan tuotteet sivuston omista lähteistä.

Promptti ohjeistaa jättämään URL-osoitteen jälkeen aina välilyönnin tai rivinvaihdon. Widget rajaa lisäksi oman sivuston URL-kandidaatin polun ensimmäiseen merkkiin, joka ei kuulu permalinkkien tavalliseen merkistöön (`a–z`, numerot, `/`, `-`, `.`, `_`). Näin esimerkiksi polun perään ilman välilyöntiä liimautuva `/Lisätietoa` jää linkin ulkopuolelle, mutta lauseen loppupiste, sulkeet, rivinvaihto sekä kelvollisen polun kyselymerkkijono tai fragmentti säilyvät tekstissä oikein (#579).

Promptin voi korvata tai laajentaa suodattimella `rytkoset_theme_chat_system_prompt` (argumentit: `$prompt`, `$contact_email`). Pysyvää sivustokontekstia voi muokata suodattimella `rytkoset_theme_chat_stable_site_context`. Sivustokartan suodattimet: `rytkoset_theme_chat_sitemap_enabled` (`true`; koko lohkon voi kytkeä pois), `..._sitemap_max_pages` (60), `..._sitemap_max_events` (20; `0` jättää tapahtumat listaamatta), `..._sitemap_max_albums` (15; `0` jättää albumit listaamatta) ja `..._sitemap_max_length` (6000 merkkiä).

Prompt sallii lisäksi ehdottamaan **sivuston omaa hakua** (`{home_url}/?s=hakusana`) viimeisenä keinona, kun vastausta ei löydy lähteistä eikä sivun lukutyökalulla — ei sen sijaan, että sopiva sivu luettaisiin työkalulla. Sama hakulinkki liitetään deterministiseen lähteettömään vakiovastaukseen (`rytkoset_theme_chat_get_named_source_fallback_reply()`), jossa malli ei ole mukana lainkaan: hakusanaksi tulevat kysymyksestä poimitut nimitermit (`rytkoset_theme_chat_get_site_search_url()` URL-koodaa ne, ja tyhjä hakusana jättää linkin pois). WordPressin oma haku kattaa myös sisältöä, jota chat ei käytä lähteenä, joten se on aito jatkoaskel umpikujassa.

Prompt kieltää lisäksi kopioimasta aiemman vastauksen kieltäytymismuotoilua uuteen kysymykseen ja aloittamasta vastausta kieltäytymisellä silloin, kun vastaus löytyy lähteestä. Molemmat lisättiin devin savutestin perusteella: yksi deterministinen lähteetön vakiovastaus keskusteluhistoriassa sai mallin toistamaan saman sanamuodon myös kysymyksiin, joita backend ei ohjannut kyseiselle polulle, ja yksi vastaus sisälsi sekä kieltäytymisen että oikean lähdetiedon.

## Customizer-asetukset (#414)

**Ulkoasu → Mukauta → Tukichatti** (`customize.php`, osio `rytkoset_theme_chat`). Kolme kenttää, jotka ei-tekninen ylläpitäjä voi muokata ilman koodimuutoksia:

| Kenttä                                                    | Setting                               | Vaikutus                                                                                                                |
| --------------------------------------------------------- | ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Näytä tukichatti sivustolla (checkbox, oletus **päällä**) | `rytkoset_theme_chat_enabled`         | Pois → widget piilotetaan **ja** REST-reitti palauttaa hallitun virheen (HTTP 503). Suora rajapintakutsukin siis estyy. |
| Tervetuloviesti (textarea)                                | `rytkoset_theme_chat_welcome_message` | Chatin avausviesti. Tyhjä = teeman oletusteksti.                                                                        |
| Tietopohja / usein kysytyt kysymykset (textarea)          | `rytkoset_theme_chat_faq`             | Liitetään system-promptiin jokaiseen API-kutsuun. Muutos näkyy chatin vastauksissa heti julkaisun jälkeen.              |

Getterit: `rytkoset_theme_chat_admin_enabled()`, `rytkoset_theme_chat_get_welcome_message()`, `rytkoset_theme_chat_get_faq_text()`. Sanitointi: checkbox → bool, tekstikentät → `sanitize_textarea_field`.

Huom: chatti näkyy sivustolla vain kun **molemmat** ehdot täyttyvät — API-avain on asetettu `wp-config.php`:hen **ja** Customizer-kytkin on päällä.

### Tietopohjan (FAQ) ylläpito

FAQ-teksti täydentää pysyvää sivustokontekstia, automaattista tapahtuma-/tuotelohkoa ja julkisten sivujen `lue_sivu`-työkalua. Malli ei hae tietoa internetistä; julkaistuja julkisia sivuja voidaan lukea vain alla kuvatulla rajatulla työkalulla. Kirjoitusohjeet:

- **Rakenne:** otsikot ISOLLA omilla riveillään, faktat luettelomerkkeinä (`- `). Selkeä rakenne auttaa mallia poimimaan oikean kohdan.
- **Sisältö:** vakiintuneet faktat ja toimintaohjeet — jäsenyystyypit ja -hinnat, maksaminen nykyisellä Paytrail-maksutavalla, maksun jatkaminen vain ehdollisesti ("jos tilauksella näkyy Maksa / yritä uudelleen -painike"), tilauksen peruutus, tapahtumiin ilmoittautuminen, kirjautumisongelmat, historian tiivistelmä, yhteystiedot. Poista Mollien ulkomaanmaksu- ja RF-viiteohjeet Customizerin FAQ:sta kokeilujakson ajaksi.
- **Pituus:** teksti lähetetään Mistralille **jokaisen viestin mukana**, joten pidä se tiiviinä (nyrkkisääntö: alle ~5 000 merkkiä). Pitkä teksti kasvattaa kuluja ja heikentää vastausten tarkkuutta.
- **Ajantasaisuus:** päivitä FAQ, kun vain siellä ylläpidetty fakta muuttuu. Chatti saa julkaistut tapahtuma- ja tuotetiedot automaattisesta kontekstista, voi lukea rajattuja julkisia sivuja työkalulla ja lukee tilikauden suoraan Säännöt-sivulta, joten näitä tietoja ei pidä kopioida FAQ:hun. Nopeasti muuttuvien tietojen osalta parempi tapa on viitata ylläpidettyyn sivuun kuin kopioida yksityiskohdat FAQ:hun.
- **Rajaukset:** älä laita FAQ:hun henkilötietoja äläkä mitään, mikä ei saa näkyä julkisesti — FAQ:n sisältö voi päätyä chatin vastauksiin kenelle tahansa kävijälle.

Testaa muutokset dev-ympäristössä kysymällä chatilta muutettuja kohtia ennen tuotantoon vientiä.

### Tuotantovalmiuden savutestit

Ennen chatin vientiä tuotantoon testaa devissä ainakin kysymykset, joissa malli on helposti taipuvainen arvaamaan:

- "Mikä on sukuseuran tilikausi?" -> vastauksen pitää tulla jokaisessa vähintään kymmenessä puhtaassa istunnossa Säännöt-sivun kohdan 10 tekstistä, sisältää oikea Säännöt-linkki eikä se saa kasvattaa Mistralin API- tai sivunlukulaskuria. Testaa myös taivutusmuoto ja seurantamuoto "Entä tilikausi?".
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

Tuotantoon viennin jälkeen pidä chatti edelleen pois päältä ja tee viisi puhdasta tilikausikyselyä sekä yksi kontrollikysymys suoraan REST-reitille tai ylläpitäjän rajatussa testissä. Kytke widget uudelleen päälle vasta, kun kaikki kuusi vastausta, lähdelinkki, vasteajat ja virhelaskurin muuttumattomuus on varmennettu.

## Ajantasainen tietolohko (#459, #471)

FAQ:n lisäksi system-promptiin liitetään **automaattisesti koottu** tietolohko, joka luetaan suoraan samoista lähteistä kuin sivuston omat näkymät — sitä ei ylläpidetä käsin, eikä se vanhene:

- **Tulevat tapahtumat** (`rytkoset_event`, julkaistut, tapahtumapäivä tänään tai myöhemmin; tapahtumapäivä on voimassa päivän loppuun): nimi, päivämäärä, kellonaika, paikka, hinta, ilmoittautumisen takaraja samasta lähteestä kuin tapahtumasivun yhteenvetokortilla (maksuttomat: oma takaraja tai tapahtumapäivä; linkitetyt maksulliset tuotteet: tuotteen takaraja), #450-lisävalinnat (esim. bussin lähtöpaikat + määräkentän nimi) ja tapahtuman osoite. Maksulliset tapahtumat ohjataan tapahtumasivulle. Enintään 5 tapahtumaa aikajärjestyksessä. Jos tulevia tapahtumia ei ole, lohko toteaa sen eksplisiittisesti, ettei malli keksi tapahtumia.
- **Jäsenyystuotteet** (`_rytkoset_membership_product = yes`, vain julkaistut): nimi, hinta, jäsenyystyyppi ja "jäsenyys voimassa X asti" -päivä. Osio jää kokonaan pois, jos WooCommerce ei ole aktiivinen (fail-safe).
- **Muut verkkokaupan tuotteet** (#471, esim. sukulehdet, t-paidat): kaikki muut julkaistut ja hinnalliset WooCommerce-tuotteet — nimi, hinta, permalink — `menu_order`/nimi-järjestyksessä (sama järjestys kuin kaupan oletuslajittelu). Jäsenyystuotteet jätetään pois, koska niillä on jo oma osionsa yllä — ei duplikointia. Enintään 20 tuotetta. Osio jää kokonaan pois, jos julkaistuja ei-jäsenyystuotteita ei löydy.

Lohkon perään lisätään ohje, ettei malli arvioi vapaita paikkoja, ilmoittautumistilannetta tai tuotteiden varastotilannetta vaan ohjaa tapahtuman tai tuotteen sivulle. Toteutus: `rytkoset_theme_chat_get_live_context()` + apufunktiot (`..._get_upcoming_event_ids()`, `..._format_event_context()`, `..._get_membership_context()`, `..._get_shop_products_context()`) `inc/chat.php`:ssä; ei välimuistia (kevyet kyselyt ajetaan vain chat-pyynnön yhteydessä, rate limit rajaa volyymin).

Suodattimet:

| Suodatin                                        | Oletus | Vaikutus                                            |
| ----------------------------------------------- | ------ | --------------------------------------------------- |
| `rytkoset_theme_chat_live_context_enabled`      | `true` | Koko lohkon voi kytkeä pois                         |
| `rytkoset_theme_chat_live_context_max_events`   | 5      | Tapahtumien enimmäismäärä                           |
| `rytkoset_theme_chat_live_context_max_products` | 20     | Muiden verkkokaupan tuotteiden enimmäismäärä (#471) |
| `rytkoset_theme_chat_live_context_max_length`   | 4000   | Lohkon merkkiraja (katkaisu)                        |

Työnjako FAQ:n kanssa: **rakenteinen, muuttuva tieto** (päivämäärät, hinnat, lähtöpaikat) tulee tästä lohkosta automaattisesti — sitä ei tarvitse eikä kannata kopioida FAQ:hun. FAQ:hun kirjoitetaan vain vakaat faktat ja toimintaohjeet (maksaminen, käytännöt, historia). Uuden tuotteen lisääminen kauppaan riittää — chatti kertoo siitä ilman FAQ- tai koodimuutosta heti kun tuote on julkaistu ja sillä on hinta.

## Sivun lukutyökalu (#501)

Chatin tietopohja laajenee promptin lähteiden yli **function calling** -työkalulla: malli saa `lue_sivu`-työkalun, jolla se voi hakea sivustokartassa listatun **julkaistun julkisen sivun tai tapahtuman** tekstisisällön, kun vastausta ei löydy promptin lähteistä. Tietopohja on siis sivusto itse — uusi julkaistu sivu tai tapahtuma on heti chatin luettavissa ilman FAQ- tai koodimuutosta.

Tapahtumarungot ovat mukana, koska tapahtuman ohjelma, aikataulu, tarjoilut ja käytännön ohjeet ovat vain tapahtuman omalla sivulla: live-konteksti antaa tapahtumasta vain rakenteisen tiivistelmän (päivä, kello, paikka, maksu, ilmoittautumisen takaraja), joten esimerkiksi `Mitä ohjelmaa juhlissa on?` tai `Milloin buffet on?` ei ollut aiemmin vastattavissa millään lähteellä.

Albumikuvaukset (`gallery_album`) ovat mukana samasta syystä: menneen tilaisuuden ohjelma, ajankohta ja siellä esiintyneet henkilöt on kuvattu vain albumin omassa tekstissä. Devin savutestissä kuusi vastausta kaatui tähän (`Kuka on Sanna Björkman?`, `Kuka on Pasi Rytkönen?`, `Kuka on Ahti Rytkönen?`, `Milloin Iisalmen sukukokous pidettiin?`, `Onko siitä valokuvia?`, `Milloin sukuseuran 60-vuotisjuhla oli?`). Tietosuojan kannalta kyse ei ole uudesta tietokategoriasta: vakiokonteksti välittää jo joka pyynnössä hallituksen kymmenen jäsenen nimet, roolit ja paikkakunnat, ja albumitieto on samaa julkaistua sisältöä pienempänä määränä (vain osuva ote tai yksi pyydetty albumi). `docs/tietosuoja.md` kertoo tämän nyt myös kävijälle.

Blogikirjoitukset (`post`) eivät edelleenkään ole luettavia — blogi näkyy vain sivustokartan kautta.

**Albumien videot:** albumin YouTube-videot ovat ACF-kentässä `gallery_videos` (`video_url` + valinnainen pikkukuva), eivät `post_content`-sisällössä, eikä WordPress tiedä videoiden YouTube-otsikoita missään vaiheessa — albumisivun iframe-otsikko rakennetaan albumin omasta otsikosta (`Video 1/3 albumissa X`). Chat ei siis voi tietää videoiden sisällöstä mitään, ja prompt kieltää nimenomaisesti kuvailemasta niitä. Jos videon sisällön halutaan löytyvän chatista tai hausta, siitä on kirjoitettava kuvaus albumin omaan tekstiin.

**Toiminta:**

1. Sivustokartan sivuriveille lisätään `(sivu-id: N)` -merkintä ja julkisten sivujen otsikoista/erisnimistä koottu lyhyt `aiheita:`-hakuvihje. Vihjeessä priorisoidaan ammatti-, arvo- tai tekijyyssanan yhteydessä mainitut henkilönimet sekä yksittäiset erisnimet, jotta pitkän julkaisuluettelon lopussa oleva henkilö tai lyhyt harvinainen nimimuoto ei putoa 360 merkin rajan ulkopuolelle. Laajennettu budjetti pitää myös pitkän Säännöt-sivun myöhemmät otsikot, kuten `10. Tilikausi ja tilintarkastus`, sivuvalinnan näkyvissä. Vihje kootaan kokonaisista termeistä eikä sitä katkaista kesken sanan. System-prompt ohjeistaa kutsumaan työkalua **ennen kieltäytymistä**, kun vastaus ei ole jo annetuissa lähteissä — mutta ei silloin, kun vastaus löytyy promptista. Hakuvihjeet auttavat valitsemaan oikean sivun, mutta niistä ei saa muodostaa faktavastausta ilman sivun lukemista työkalulla. Peruskysymykset vastataan edelleen **yhdellä API-kutsulla** täsmälleen kuten ennen. Sanamuoto on tarkoituksella matalakynnyksinen: ensimmäinen devissä testattu versio ehdollisti työkalun käytön sanalla "todennäköisesti", ja promptin arvauskieltojen (#480 ym.) rinnalla `mistral-medium-latest` jätti työkalun kokonaan käyttämättä (17 viestiä, 0 työkalukutsua) ja jopa väitti, ettei sivulla näkyvää nimeä mainita sivustolla. Nykyinen ohje sanoo eksplisiittisesti, että työkalulla haettu sisältö on sallittu lähde, työkalun kokeileminen ei ole kiellettyä arvaamista, ja ettei sivuston sisällöstä saa esittää kielteistä väitettä ("ei mainita sivustolla") tarkistamatta asiaa työkalulla.
2. Hallitus-, puheenjohtaja-, henkilö-, teosnimi- ja paikkasidonnaiset sukukokouskysymykset ratkaistaan ensin yllä kuvatulla palvelinhaulla. Kun varmennettu julkinen lähde löytyy, sen rajattu ote annetaan yhdelle tavalliselle Mistral-kutsulle eikä epävakaata function calling -alkukierrosta tarvita. Nimetty mutta lähteetön kysymys vastataan turvallisesti ilman mallia; tämä koskee myös selvää sivuston ulkopuolista nimettyä faktakysymystä, kuten valtion presidenttiä. Pakotettu `tool_choice: "any"` säilyy pronominiseurannoissa, tekijyyskysymyksissä ja sääntöjen täsmällisissä käsitteissä, joiden lähde pitää yhä lukea työkalulla. Tällaisia sääntökäsitteitä ovat esimerkiksi `toimintakausi`, `tilintarkastus` ja `nimenkirjoitus`; myös `Entä …?` pakottaa tuoreen sivunluvun. Sama pakotus koskee tietosuoja- ja kauppaehtokäsitteitä (`tietosuoja`, `rekisteriseloste`, `henkilötiedot`, `evästeet`, `uutiskirje`, `maksutavat`, `toimitusehdot`, `peruuttamisoikeus`) sekä rekisteröidyn omia tietoja koskevia muotoja, joissa on omistusliite (`tietojani`, `tietojeni`, `tietoni`). Nämä lisättiin devin savutestin perusteella: `Missä maissa tietojani käsitellään?` ja `Voinko peruuttaa uutiskirjeen tilaamisen?` vastattiin `En tiedä`, vaikka vastaus on Tietosuojaseloste-sivulla, joka on sivustokartassa hakuvihjeineen — malli ei vain kutsunut työkalua. Omistusliite on tarkoituksellinen ehto, jotta tavallinen `Mitä tietoa sivustolla on tapahtumista?` ei pakota lukua. Tavalliset tukikysymykset, kuten `Kuka voi liittyä jäseneksi?`, eivät pakota työkalua pelkän kysymyssanan vuoksi.
3. Sivun sisältö luetaan raa'asta `post_content`ista ja riisutaan tekstiksi (`rytkoset_theme_chat_extract_page_text()`): block-kommentit, HTML ja shortcodet poistetaan — shortcodeja tai dynaamisia blokkeja **ei suoriteta** (MVP-rajaus; whitelistattu renderöinti mahdollinen jatkoaskel).

**#604:n poikkeukset:** suora tilikausikysymys ratkaistaan deterministisesti Säännöt-sivun kohdasta 10 ilman Mistralia. Varmennettuun lähteeseen osuvat nimetyt henkilö-, teos- ja sukukokouskysymykset käyttävät palvelimen lähdeotetta ja yhtä tavallista Mistral-kutsua; lähteetön nimetty faktakysymys saa deterministisen turvallisen vastauksen ilman mallia. Muissa pakotetuissa kysymyksissä `tool_choice: "any"` hyväksytään vain, jos ensimmäinen vastaus sisältää ainakin yhden rakenteellisesti kelvollisen `lue_sivu`-kutsun ja positiivisen `sivu_id`:n.

**Vastausvalidointi (#604):** työkalusilmukan jälkeisen lopullisen mallivastauksen `finish_reason`-arvon pitää olla täsmälleen `stop`. Teksti hylätään myös, jos se ylittää 3000 merkkiä, sisältää HTML-/ajatus-/erikoistokenijäänteitä, pitkiä merkki- tai roskamotiivijaksoja tai saman kahdeksan sanan jakson vähintään kolmesti. Hylättyä vastausta ei yritetä uudelleen eikä sitä lisätä historiaan; myös epäonnistuneen pyynnön käyttäjävuoro poistetaan tulevasta selainhistoriasta. Käyttäjä saa nykyisen geneerisen HTTP 502 -virheen.

Seurantakysymyksissä system-prompt ohjaa yhdistämään pronominin tai muun viittauksen viimeisimpään yksiselitteiseen keskustelukontekstiin ja käyttämään aiemman kysymyksen nimeä sivun valintaan. Epäselvä viittaus pitää täsmentää käyttäjältä. Henkilön ammatti-, tehtävä- ja roolivastauksissa käytetään sivulta luettua nimenomaista nimikettä ilman yleistämistä tai päättelyä (#507).

**Vuotosuoja:** `lue_sivu`-työkalu palauttaa vain julkaistuja, salasanattomia `page`-, `rytkoset_event`- ja `gallery_album`-sisältöjä (`rytkoset_theme_chat_get_page_tool_post_types()`), ja kaikki kulkevat saman `rytkoset_theme_chat_get_public_source_post()`-vartijan läpi kuin palvelimen lähde-esihaku: sivuille täysi jäsensivutarkistus, tapahtumille ja albumeille julkaisu- ja salasanatarkistus. Muut sisältötyypit, kuten blogikirjoitukset, eivät kelpaa.

**Lähdeviitteen sitovuus:** palvelimen lähdelohko vaatii käyttämään vastauksessa täsmälleen lohkossa annettua osoitetta — ei sivustokartan muuta osoitetta, vaikka aihe vaikuttaisi liittyvän toiseen sivuun — ja toistamaan otteen päivämäärät, vuosiluvut ja paikat sellaisenaan ilman yleistyksiä kuten "muun muassa". Vaatimus lisättiin devin savutestin jälkeen: kysymykseen `Onko Pielavedellä pidetty sukujuhlia?` esihaku antoi oikean Toimintakertomus-lähteen tekstillä "Hallitus valittiin sukukokouksessa Pielavedellä 27.7.2019", mutta malli vastasi "muun muassa Pielavedellä" ja viittasi osoitteeseen `/sukuseura/`, jolla ei esiinny sanaa Pielavesi lainkaan. Väite oli sisällöltään tosi mutta lähdeviite väärä, mikä on käyttäjälle harhaanjohtavampaa kuin kieltäytyminen.

**Esihaun porrastus:** nimilähdehaku etsii ensin sivuista (sukukokouskysymyksessä tapahtumista ja sivuista) ja vasta sitten albumeista. Jokainen porras saa oman `..._prefetch_max_pages`-budjettinsa. Porras hyväksytään vain, jos se tuottaa yksiselitteisen lähteen (`rytkoset_theme_chat_prefetch_candidates_are_usable()`: henkilö- ja teoskysymyksessä täsmälleen yksi, sukukokouksessa enintään kaksi); moniselitteinen porras hylätään ja haku jatkuu seuraavaan. Tämä pitää sivut ensisijaisina — albumissa mainittu nimi ei tee sivulta löytyvästä henkilöstä moniselitteistä — mutta antaa albumin varmentaa nimen, jota pelkkä sukunimiosuma ei sivuilta ratkaise. Jäsenille rajatut sivut (#392, `_rytkoset_members_only`) suodatetaan **ehdottomasti ja katsojasta riippumatta**. Kaikki sivun epäämissyyt käyttävät samaa geneeristä käsittelyä, eikä lähteetön fallback kerro, onko rajattua sisältöä olemassa.

**Kulusuojat:** työkalukierroksia enintään 2 per käyttäjäviesti oletuksena (suodatin `rytkoset_theme_chat_page_tool_max_rounds`, kova yläraja 3), sivusisältö katkaistaan 5000 merkkiin (`..._page_tool_max_length`), sivunluku suoritetaan enintään kolmelle työkalukutsulle per kierros ja jokainen suoritettu kutsu kirjataan käyttötilastoihin (#472). Rate limit kuluu kerran per käyttäjäviesti — sisäiset jatkokutsut eivät kierrä sitä. Huomaa, että työkalullinen kysymys kestää yleensä kaksi tai kolme API-kierrosta ja maksaa enemmän tokeneita.

**Pois kytkeminen:** suodatin `rytkoset_theme_chat_page_tool_enabled` (`false` → API-payload, sivustokartta ja system-prompt ovat täsmälleen entisellään, ei `tools`-kenttää). Mallihuomio: dev ja tuotanto käyttävät `mistral-medium-latest`-mallia (`RYTKOSET_CHAT_API_MODEL`; koodin oletusfallback on `mistral-small-latest`). Mistralin dokumentaation mukaan vahvin function calling -tuki on `mistral-large-latest`-mallilla — jos käytössä oleva malli käyttää työkalua huonosti (turhia kutsuja tai ei kutsu lainkaan), mallin voi vaihtaa ympäristökohtaisesti samalla vakiolla.

Maksuohjeissa ei pidä luvata, että kaikki epäonnistuneet tai keskeneräiset
tilaukset voi aina vaihtaa itse toiseen maksutapaan. Käytä muotoa:
"Avaa Oma tili -> Tilaukset. Jos tilauksen kohdalla näkyy Maksa / yritä
uudelleen -painike, voit jatkaa maksua ja valita kassalla toisen maksutavan.
Jos painiketta ei näy, ota yhteyttä sähköpostitse." Toteutus ja tarkempi
rajaus on dokumentoitu tiedostossa `docs/woocommerce-paytrail.md`.

## Käyttötilastot ylläpitäjälle (#472)

Ennen tätä kokonaisuutta kulusuojien osumat eivät näkyneet ylläpitäjälle mitenkään tuotannossa: `rytkoset_theme_chat_log_error()` kirjoittaa lokiin vain `WP_DEBUG`-tilassa, eikä rate limit -osumia kirjattu mihinkään. Kevyet koontilaskurit näyttävät suoraan wp-adminissa, käytetäänkö chattia, osuuko joku rate limitiin, toimiiko Mistral-yhteys ja tuottaako prompt-välimuistikoe mitattavia osumia — ilman palvelimen lokien tarkistamista.

**Näkyvyys:** WordPressin Dashboard-widget **"Tukichatti"** (`rytkoset_theme_chat_register_dashboard_widget()`, koukku `wp_dashboard_setup`), näkyy vain `manage_options`-käyttäjille. Widget näyttää chatin tilan, prompt-välimuistin ympäristökohtaisen päällä/pois-tilan, lähetettyjen viestien, rate limit -osumien ja sivunlukujen määrät, Mistral-/yhteysvirheet sekä prompt-välimuistin syötetokenien ja osumien koonnin.

**Tallennus:** erilliset `wp_options`-rivit (`autoload = false`) päivitetään olemassa olevissa päätöspisteissä — ei erillistä seurantajärjestelmää:

| Option                            | Sisältö                                                                                                    | Päivityskohta                                                                                                                                                                                                                                                                                                      |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `rytkoset_chat_stat_messages`     | `count`, `last_at`                                                                                         | `rytkoset_theme_chat_handle_request()`:n onnistunut paluu (`rytkoset_theme_chat_record_message_sent_stat()`)                                                                                                                                                                                                       |
| `rytkoset_chat_stat_rate_limit`   | `count`, `last_at`                                                                                         | `rytkoset_theme_chat_register_rate_limit_hit()` palauttaa `true` (`rytkoset_theme_chat_record_rate_limit_hit_stat()`)                                                                                                                                                                                              |
| `rytkoset_chat_stat_error`        | `count`, `last_at`, `last_type`                                                                            | Verkkovirhe, ei-2xx-HTTP-vastaus, tyhjä/odottamaton vastaus sekä #604:n varmennetun lähteen, pakotetun työkalukutsun, `finish_reason`-arvon ja sisältövalidaattorin hylkäykset — `log_error()` säilyy WP_DEBUG-lokitusta varten, `rytkoset_theme_chat_record_error_stat( $type )` on erillinen, rinnakkainen kutsu |
| `rytkoset_chat_stat_tool_calls`   | `count`, `last_at`                                                                                         | Jokainen **suoritettu** `lue_sivu`-työkalukutsu (#501, `rytkoset_theme_chat_record_tool_call_stat()`) — kierroskaton ylittäneitä, ohitettuja kutsuja ei lasketa; viestimäärälaskuri kasvaa edelleen vain kerran per käyttäjäpyyntö                                                                                 |
| `rytkoset_chat_stat_prompt_cache` | `api_calls`, `cache_hit_calls`, `prompt_tokens`, `cached_tokens`, viimeisimmän kutsun tokenit ja `last_at` | Jokainen onnistunut suora Mistral API -kutsu, jonka vastauksessa on ehjä `usage.prompt_tokens_details.cached_tokens`-rakenne; myös työkalun sisäiset jatkokierrokset lasketaan                                                                                                                                     |

`last_type`-arvo on lyhyt, staattinen tunniste (`network`, `http_<koodi>`, `empty_reply`, `direct_source_missing`, `forced_tool_missing`, `invalid_finish_reason` tai `invalid_reply`) — ei koskaan dynaamista virhesanomaa, viestisisältöä tai mallin palauttamaa syyarvoa. `rytkoset_theme_chat_get_error_type_label()` muotoilee sen ihmisluettavaksi widgetissä.

**Ei henkilötietoa:** laskurit eivät koskaan sisällä raakaa IP-osoitetta, viestisisältöä eivätkä `prompt_cache_key`-arvoa — vain lukumäärät, tokenimäärät, aikaleimat ja lyhyt virhetyypin tunniste, sama periaate kuin nykyisessä rate limit -transientissa (joka tallentaa vain MD5-tiivisteen). Koska data ei yksilöi ketään, se ei ole GDPR:n tarkoittamaa henkilötietoa eikä `docs/tietosuoja.md`-tietosuojaselosteen sisältöä tarvinnut tämän vuoksi muuttaa (ks. selosteen "AI-tukichatti"-kohta).

**Puhtaat apufunktiot** (testattu `tests/ChatUsageStatsTest.php`:ssä): `rytkoset_theme_chat_bump_stat()` / `..._bump_error_stat()` (laskurin kasvatus, ei kosketa `wp_options`-tauluun), `rytkoset_theme_chat_get_usage_stats()` (yhteenveto widgetiä varten) ja `rytkoset_theme_chat_get_error_type_label()`. Itse Dashboard-widgetin rekisteröinti ja renderöinti ovat ohutta admin-liimakoodia, joka on tarkoituksella jätetty yksikkötestien ulkopuolelle (ks. `CLAUDE.md`:n testausohje render-raskaille admin-näkymille). Widgetin aiemmat rivit todennettiin #472:ssa manuaalisesti wp-adminissa; uuden prompt-välimuistirivin dev-todennus kuuluu yllä kuvattuun #567-kokeeseen.

## Palveluntarjoajan vaihto (Mistral ↔ Azure Sweden Central)

Integraatio on tarkoituksella tehty vaihdettavaksi: chatti kutsuu geneeristä **chat-completions-rajapintaa** (`POST {endpoint}` + `Authorization: Bearer {key}` + `{model, messages, max_tokens, temperature, frequency_penalty}` → `choices[0].message.content`), ja kaikki kolme parametria luetaan `wp-config.php`-vakioista. Mistral-kohtainen `prompt_cache_key` lisätään vain, kun endpointin isäntä on täsmälleen `api.mistral.ai`; se jää automaattisesti pois muiden tarjoajien payloadista. Palveluntarjoajan vaihto on siis konfiguraatiomuutos, **ei koodimuutos**, kunhan uusi tarjoaja toteuttaa saman rajapintamuodon:

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
12. **Tilikausilähde (#604), nimetty lähdehaku ja sivun lukutyökalu (#501)**: varmista tilikausipolku ja aiemmat henkilötestit. Kysy lisäksi `Voinko ostaa kirjan Rytkösiä sukupolvesta toiseen?` → vastauksen pitää käyttää Sukututkimus-sivun loppuunmyyty-tietoa. `Milloin Tampereen sukukokous pidettiin?` saa käyttää tulevan tapahtuman ja kuljetuksen sivuja mutta ei hallituksen paikkarivejä. `Milloin Runnin sukukokous pidettiin?`, `Milloin Helsingin sukukokous pidetään?`, `Milloin Rovaniemellä on pidetty sukukokous?` ja `Kuka on Ruotsin presidentti?` palauttavat nykyisellä sisällöllä turvallisen lähteettömän HTTP 200 -vastauksen ilman Mistral- tai sivunlukukutsua. Lisää testisivulle paikka ja sukukokous eri kappaleisiin → se ei saa kelvata osumaksi. Merkitse sivu vain jäsenille tai tapahtuma luonnokseksi/salasanalliseksi → sisältö ei saa päätyä lähdeotteeseen. Pronominikysymyksen kelvoton pakotettu työkaluvastaus palauttaa edelleen turvallisen 502:n. Kysy hallituskokoonpanoa vielä muodoissa `Ketkä kuuluvat hallitukseen?`, `Luettele hallituksen jäsenet.` ja `Näytä hallituksen kokoonpano.` → kaikki antavat saman hallitussivun listan. Kysy `Voiko kuka tahansa liittyä sukuseuran jäseneksi?` ja `Saako sukukirjaa kirjastosta?` → kumpikaan ei saa palauttaa lähteetöntä vakiovastausta.
13. **Käyttötilastot (#472)**: lähetä chatiin muutama viesti → wp-adminin Ohjausnäkymän **Tukichatti**-widget näyttää lähetettyjen viestien määrän kasvavan ja viimeisimmän ajankohdan päivittyvän. Täytä rate limit (esim. `RYTKOSET_CHAT_RATE_LIMIT` väliaikaisesti pieneksi devissä) → rate limit -osumien laskuri kasvaa. Aiheuta upstream-virhe (esim. väliaikaisesti virheellinen `RYTKOSET_CHAT_API_ENDPOINT`) → virhelaskuri kasvaa ja widget näyttää viimeisimmän virhetyypin. Poista `RYTKOSET_CHAT_API_KEY` → widgetin tila-rivi kertoo "API-avain puuttuu". Tarkista, ettei widgetissä näy IP-osoitteita eikä viestien sisältöä.
14. **Prompt-välimuisti (#567)**: suorita edellä kuvattu dev-koe ensin ilman `RYTKOSET_CHAT_PROMPT_CACHE_KEY`-vakiota ja sitten vakaalla dev-avaimella. Varmista Dashboard-widgetistä `prompt_tokens`, `cached_tokens` ja osumakutsut sekä tarkista tavallinen vastaus ja `lue_sivu`-vastaus. Vaihda endpoint väliaikaisesti ei-Mistral-testiosoitteeksi vain payloadin tarkasteluun → `prompt_cache_key` ei saa olla mukana. Palauta oikea endpoint heti testin jälkeen.

Yksikkötestit (`tests/ChatProxyTest.php`) kattavat yleiset chat-helperit ja vastausvalidoinnin. `tests/ChatPageToolTest.php` kattaa sivun lukutyökalun, tilikausilähteen ja nimetyn palvelinhaun: henkilöt, teosnimen lisäsanat, sukukokouksen paikan ja aiheen saman rivin vaatimus, kahden tapahtumalähteen yhdistelmä sekä salasanallisen/luonnostapahtuman hylkäys. `tests/ChatRequestHandlerTest.php` varmistaa yhden kutsun lähdepolun, lähteettömän nimetyn kysymyksen mallittoman 200-vastauksen ja pakotettujen pronomini-/sääntökysymysten fail-closed-invariantin. Muut chat-testit kattavat ajantasaisen tietolohkon, sivustokartan, tilastot ja selainhistorian suodatuksen.

## Käyttöliittymä (chat-widget, #413)

Kelluva chat-painike + paneeli (`inc/chat.php` renderöi kuoren `wp_footer`-koukussa, `assets/js/chat.js` + `assets/css/chat.css`). Rakentuu yllä kuvatun REST-reitin päälle.

- **Näkyy vain kun backend on konfiguroitu ja chatti on kytketty päälle.** `rytkoset_theme_chat_widget_is_enabled()` palauttaa `true` vasta kun `RYTKOSET_CHAT_API_KEY` + `RYTKOSET_CHAT_API_ENDPOINT` on asetettu **ja** Customizerin Tukichatti-kytkin on päällä (#414) — muuten widgetiä ei renderöidä eikä assetteja ladata. Näyttämisen voi pakottaa/estää suodattimella `rytkoset_theme_chat_widget_enabled`.
- **Keksitön:** keskusteluhistoria + paneelin auki-tila säilyvät **välilehtikohtaisessa `sessionStorage`ssa** (#498; versioitu avain `rytkosetChat.v2`, historiakatto 40 viestiä), joten keskustelu jatkuu sivulatausten yli ja tyhjenee välilehden sulkeutuessa. #604 vaihtoi avaimen v1:stä v2:een ilman historian migraatiota, jotta ennen korjausta tallennettu rikkinäinen mallivastaus ei palaudu keskustelukontekstiin; vanha `rytkosetChat.v1` poistetaan kohdennetusti eikä muuta `sessionStorage`-dataa tyhjennetä. Epäonnistunut REST-pyyntö poistaa lisäksi juuri lähetetyn käyttäjävuoron muistissa olevasta ja tallennetusta historiasta ennen seuraavaa lähetystä. Kertamuutoksena myös avoinna ollut paneelitila nollautuu. Ei `localStorage`a eikä keksejä → keksibanneria ei tarvita (välttämätön, kävijän itse pyytämän toiminnon tallennus). Palautetut viestit renderöidään samaa turvallista polkua kuin livenä. Jos selain estää storagen tai se on täynnä, chatti toimii muistinvaraisesti kuten ennen (sivulataus nollaa keskustelun).
- **Turvallinen renderöinti:** mallin vastaus lisätään DOMiin ilman `innerHTML`:ää (tekstisolmut) → ei XSS-riskiä. Rivinvaihdot säilyvät CSS:n `white-space: pre-wrap`illä. Vastauksessa olevat **paljaat oman sivuston https-osoitteet** (nykyinen isäntä, `rytkoset.net` ja sen alidomainit) muutetaan klikattaviksi linkeiksi turvallisesti (`createElement('a')`, `new URL()`-validointi, protokolla vain http/https, `rel="noopener noreferrer"` muille kuin oman hostin linkeille). Oman hostin linkit avautuvat **samassa välilehdessä**, jolloin keskustelu jatkuu `sessionStorage`sta (#498); muut sallitut hostit (esim. dev↔tuotanto-alidomainit) avautuvat uuteen välilehteen, koska `sessionStorage` ei siirry uuteen välilehteen eikä toiselle originille. Muut osoitteet ja mahdollinen markdown-syntaksi jäävät pelkäksi tekstiksi. System-prompt ohjeistaa mallia vastaamaan ilman markdownia ja kirjoittamaan sivuston linkit täysinä osoitteina (`home_url()`-pohja, toimii oikein dev- ja tuotantoympäristössä).
- **Saavutettavuus (WCAG 2.1 AA):** paneeli `role="dialog"`, viestiloki `role="log" aria-live="polite"` (uudet vastaukset ilmoitetaan ruudunlukijalle), näkyvä fokus, näppäimistökäyttö (Enter lähettää, Shift+Enter rivinvaihto, **Esc** sulkee ja palauttaa fokuksen painikkeeseen). Fokusansaa ei ole (paneeli ei ole modaali) — MVP-rajaus.
- **Tumma teema:** widget käyttää vain design-tokeneita, jotka adaptoivat `:root[data-theme="dark"]`-teemaan.
- **Mobiili (≤ 640px):** paneeli täyttää lähes koko ruudun (`chat.css`). iOS Safarissa kiinnitetyn (`position: fixed`) elementin sijainti lasketaan layout-viewportista, joka voi poiketa näkyvästä viewportista sekä virtuaalinäppäimistön ollessa auki että sivun mahdollisen vaakavierityksen takia — ilman korjausta paneeli voi jäädä osittain ruudun ulkopuolelle tai näppäimistön peittoon. `chat.js` pinnaa auki olevan paneelin `window.visualViewport`-rajapinnalla tarkasti näkyvään viewporttiin (`resize`/`scroll`-kuuntelu + `matchMedia`-rajan ylityksen käsittely), inline `left`/`top`/`width`/`height` korvaa CSS:n oletusarvot avattaessa ja puretaan suljettaessa (`chat.css`:n `@media (max-width: 640px)` toimii fallbackina selaimille ilman `visualViewport`-tukea).
- **Disclaimer** näkyy paneelin yläosassa: "Tekoälyavustaja. Älä syötä arkaluonteisia tietoja; varmista tärkeät asiat sähköpostitse."
- **Syöteraja** (`maxlength`) jaetaan backendin kanssa: `rytkoset_theme_chat_get_max_input_length()`.

### Sivuvälimuisti-caveat

Widgetin nonce (`wp_create_nonce('wp_rest')`) upotetaan sivun HTML:ään `wp_add_inline_script`illä. Jos sivustolla on **täyssivuvälimuisti**, kirjautumattoman kävijän nonce vanhenee ~12–24 h kuluttua ja REST-kutsu palauttaa "Istunto on vanhentunut" (HTTP 403). Tällöin ohita chat-sivujen välimuisti tai pidä TTL noncen elinikää lyhyempänä.

### Manuaalinen selaintestaus

Avaa chat kelluvasta painikkeesta, lähetä kysymys → "kirjoittaa…"-tila → suomenkielinen vastaus. Tarkista DevToolsin **Application**-välilehdeltä, ettei chat luo keksejä tai `localStorage`-merkintöjä ja että sen aktiivinen `sessionStorage`-avain on `rytkosetChat.v2`. Esitäytä ennen sivulatausta `rytkosetChat.v1` väärällä vastauksella ja erillinen sentinel-avain → v1-viestiä ei renderöidä eikä lähetetä REST-payloadissa, v1-avain poistuu mutta sentinel ja WordPressin mahdolliset omat storage-avaimet säilyvät. Testaa mobiilileveys (390 px), tumma teema, näppäimistö (Tab/Esc), ruudunlukija (`aria-live`) ja `<script>`-syöte (renderöityy tekstinä, ei suoritu).

**Istunnon säilyminen (#498/#604):** käy uusi v2-keskustelu, lataa sivu uudelleen tai siirry chatin antamasta oman sivuston linkistä toiselle sivulle → keskustelu ja auki ollut paneeli palautuvat (fokus ei siirry syötekenttään palautuksessa). Sulje välilehti ja avaa sivu uudessa välilehdessä → keskustelu on tyhjä. Estä storage (esim. Firefoxin `dom.storage.enabled=false`) → chatti toimii muistinvaraisesti ilman JS-virheitä.

**Epäonnistuneen vuoron rollback:** aiheuta devissä yksi REST-virhe, pidä paneeli auki ja lähetä sen jälkeen eri kysymys. Jälkimmäisen vastauksen pitää vastata vain uusimpaan kysymykseen. Tarkista Network-pyynnöstä ja `rytkosetChat.v2`-arvosta, ettei epäonnistunut käyttäjäviesti ole uuden pyynnön `messages`-taulukossa eikä tallennetussa historiassa; ensimmäinen kysymys ja virhe saavat silti jäädä nykyisen näkymän lokiin.

**Oikealla mobiililaitteella (ei vain DevToolsin emulaattorilla — `visualViewport`-käyttäytyminen eroaa):** avaa paneeli, avaa virtuaalinäppäimistö koskettamalla syötekenttää → koko paneeli (viestiloki + syötekenttä + lähetyspainike) pysyy näkyvässä osassa ruutua näppäimistön yläpuolella, ei leikkaudu eikä jää näppäimistön alle. Yritä vetää sivua sivuttain paneelin ollessa auki → paneeli ei siirry pois paikaltaan.
