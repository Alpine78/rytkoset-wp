# AI-tukichatti: backend-proxy ja Mistral-integraatio

AI-tukichatti tarvitsee palvelinpuolen välityskerroksen, jotta Mistralin API-avain **ei koskaan** päädy selaimeen. Tämä toiminto (#412, EPIC 11 / #411) toteuttaa **vain backendin**: REST-reitin, system-promptin, Mistral-välityksen ja kulusuojat. Käyttöliittymä (chat-widget) ja vastauksen escapetys kuuluvat tikettiin **#413**.

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

`rytkoset_theme_chat_get_system_prompt()` kokoaa promptin, joka ohjeistaa assistentin: vastaa **vain suomeksi**, pysy yhdistyksen aiheissa, **älä keksi tietoa** ja ohjaa epävarmoissa sähköpostiin (`rytkoset_theme_get_contact_email()`). Promptin voi korvata tai laajentaa (esim. tarkempi FAQ-tietämys) suodattimella `rytkoset_theme_chat_system_prompt` (argumentit: `$prompt`, `$contact_email`).

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

Yksikkötestit (`tests/ChatProxyTest.php`) kattavat puhtaat helperit: rate limit -päätös, viestien valmistelu/katkaisu, vastauksen poiminta ja system-prompt. Verkko- ja REST-kytkentä varmistetaan yllä olevalla manuaalisella curl-testillä.

## Käyttöliittymä (chat-widget, #413)

Kelluva chat-painike + paneeli (`inc/chat.php` renderöi kuoren `wp_footer`-koukussa, `assets/js/chat.js` + `assets/css/chat.css`). Rakentuu yllä kuvatun REST-reitin päälle.

- **Näkyy vain kun backend on konfiguroitu.** `rytkoset_theme_chat_widget_is_enabled()` palauttaa `true` vasta kun `RYTKOSET_CHAT_API_KEY` + `RYTKOSET_CHAT_API_ENDPOINT` on asetettu — ilman avainta widgetiä ei renderöidä eikä assetteja ladata. Näyttämisen voi pakottaa/estää suodattimella `rytkoset_theme_chat_widget_enabled`.
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
