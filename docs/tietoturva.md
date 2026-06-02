# Tietoturva

Tämä dokumentti kokoaa Rytkösten sukuseuran sivuston tietoturvakovennukset: mitä on toteutettu teemakoodissa ja mitkä asiat hoidetaan palvelin- ja ylläpitotasolla. Liittyy tikettiin #336.

Sivustolla käsitellään henkilötietoja (tapahtumailmoittautumiset, jäsenyydet, WooCommerce-tilaukset), joten tietoturva ja [tietosuoja](tietosuoja.md) kulkevat käsi kädessä.

## Mitä kuuluu mihinkin

Repossa on versioituna vain `wp-content/`. WordPress-core, liitännäiset, palvelimen `.htaccess` ja palvelinkonfiguraatio ovat repon ulkopuolella. Siksi tietoturva jakautuu kahteen osaan:

- **Teemakoodi** (`inc/security.php`) — alla "Toteutetut kovennukset".
- **Palvelin / ylläpito** — alla "Palvelintason checklist". Näitä ei voi toteuttaa teemassa.

## Toteutetut kovennukset (teemakoodi)

Kaikki alla oleva on moduulissa [`inc/security.php`](../wp-content/themes/rytkoset-theme/inc/security.php). Koko kovennuspaketin voi poistaa käytöstä suodattimella `rytkoset_theme_enable_security_hardening`.

### Käyttäjien luetteloinnin esto

Estää bottiverkkoja keräämästä kirjautumisnimiä brute-force-hyökkäyksiä varten:

- **REST API:** `/wp/v2/users` ja `/wp/v2/users/<id>` poistetaan kirjautumattomilta (`rest_endpoints`-suodatin). Kirjautuneet käyttäjät säilyttävät pääsyn, joten wp-admin ja blokkieditorin tekijävalinta toimivat normaalisti.
- **`?author=N`:** numerokysely ohjataan etusivulle (301) ennen kuin WordPress paljastaa kirjautumisnimen `/author/<slug>/`-osoitteena. `/author/<slug>/`-arkistot säilyvät ennallaan.

### XML-RPC estetty

`xmlrpc.php` on yleinen brute-force- ja DDoS-vahvistuskohde, eikä sivusto käytä sitä (ei Jetpackia, mobiilisovellusta eikä pingbackejä):

- `xmlrpc_enabled` palautetaan epätodeksi → kaikki todennusta vaativat metodit (mm. `system.multicall`) estetään.
- `pingback.ping`-metodit poistetaan → sivustoa ei voi käyttää DDoS-heijastimena.
- `X-Pingback`-otsake riisutaan vastauksista.

Erikseen kytkettävissä suodattimella `rytkoset_theme_disable_xmlrpc`.

### Selaintason tietoturvaotsakkeet

Lähetetään etusivun pyynnöille (`send_headers`); wp-admin ohitetaan. Muokattavissa suodattimella `rytkoset_theme_security_headers`.

| Otsake | Arvo | Suoja |
| --- | --- | --- |
| `X-Content-Type-Options` | `nosniff` | Estää MIME-tyypin arvauksen |
| `X-Frame-Options` | `SAMEORIGIN` | Clickjacking-suoja |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Rajoittaa referrer-tiedon vuotamista |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` | Estää käyttämättömät selainominaisuudet |

> **HSTS** (`Strict-Transport-Security`) ja **Content-Security-Policy** on jätetty tarkoituksella pois teemasta. HSTS kuuluu HTTPS-/palvelintasolle, ja tiukka CSP rikkoisi helposti wp-adminin, WooCommercen ja Mollien hostatun maksu-iframen. Nämä hoidetaan palvelintasolla, ks. alla.

### Testaus dev-ympäristössä

Kirjautuneena ulos:

- `https://dev.rytkoset.net/wp-json/wp/v2/users` → `rest_no_route` (404), ei käyttäjälistaa.
- `https://dev.rytkoset.net/?author=1` → ohjaus etusivulle.
- `https://dev.rytkoset.net/xmlrpc.php` (POST `system.listMethods`) → ei `pingback.ping`-metodia; todennetut metodit palauttavat virheen.
- `curl -I https://dev.rytkoset.net/` → neljä tietoturvaotsaketta näkyvät; `X-Pingback` puuttuu.

Kirjautuneena: wp-admin ja blokkieditori toimivat normaalisti.

## Palvelintason checklist (ylläpito)

Nämä eivät ole teemakoodia. Tee ja ylläpidä palvelimella / WordPress-adminissa.

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
- [ ] **Content-Security-Policy** — vaatii huolellisen määrittelyn wp-adminin, WooCommercen ja Mollie-iframen vuoksi; rakennetaan asteittain (report-only ensin).
- [ ] **PHP-tiedostojen suoritus estetty** `wp-content/uploads/`-kansiossa.

### wp-config.php

- [ ] `DISALLOW_FILE_EDIT` päällä (estää teema-/plugin-editorin wp-adminissa).
- [ ] Suolakkeet (`AUTH_KEY` ym.) kunnossa ja uniikit.
- [ ] Tiedosto-oikeudet tarkistettu.

## Yhteys tietosuojaan

Tietoturva on edellytys tietosuojalle. Kun arvioit tämän dokumentin kohtia, tarkista yhteensopivuus [tietosuoja.md](tietosuoja.md):n kanssa erityisesti henkilötietojen (ilmoittautumiset, jäsenyydet, tilaukset) suojaamisen osalta.
