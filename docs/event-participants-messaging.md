# Massaviestintä tapahtuman osallistujille

Tämä dokumentti kuvaa tiketin `#74` toteutuksen.

## Tavoite

Ylläpitäjä ja tapahtumajärjestäjä voivat lähettää sähköpostiviestin tapahtuman osallistujille suoraan WordPressin administa — vähentää manuaalista sähköpostityötä esim. muistutuksissa, lisäohjeissa ja aikataulumuutoksissa.

## Sijainti

`Tapahtumat > Viestintä`

## Mitä sivu sisältää

### Suodattimet

Sivun yläosassa on samat suodattimet kuin osallistujalistalla:

- **Tapahtuma:** yksittäinen tapahtuma tai `Kaikki tapahtumat`
- **Status:** kaikki, jokin yksittäinen rekisteröintistatus (`pending`/`confirmed`/`cancelled`) tai `Maksulliset` (WooCommerce-tilaukset)

Suodattimien jälkeen näkyy vastaanottajamäärä:

> *"Viesti lähetetään 23 vastaanottajalle (osoitteita puuttuu 2)."*

Osoitteita puuttuvat osallistujat ohitetaan automaattisesti. Vastaanottajat deduplikoidaan sähköpostiosoitteen perusteella (sama yhteyshenkilö Tampere 2026 -tilauksessa lasketaan yhdeksi vastaanottajaksi).

### Viestilomake

| Kenttä | Selite |
| --- | --- |
| Aihe | Sähköpostin otsikkorivi (pakollinen, max 200 merkkiä) |
| Viesti | Viestin runko tekstimuotoisena (pakollinen) |

Lomake hyväksyy kaksi placeholderia, jotka korvataan jokaisen vastaanottajan kohdalla:

- `{nimi}` → osallistujan nimi
- `{tapahtuma}` → tapahtuman otsikko

Esim. *"Hei {nimi}, tervetuloa tapahtumaan {tapahtuma}!"* lähetetään yksilöllisesti jokaiselle.

### Lähetys

Lähetä-painike on muodossa "Lähetä viesti X vastaanottajalle" ja näyttää tarkistuksen ennen lähetystä. Jos vastaanottajia on 0, painike on disabloitu.

Lähetys tapahtuu synkronisesti: jokaiselle vastaanottajalle tehdään oma `wp_mail()`-kutsu. Tämä mahdollistaa personoinnin sekä per-vastaanottaja-onnistumislaskennan, mutta saattaa olla hidas erittäin suurilla (>100) listoilla. Jos PHP-pyyntö timeoutaa, lähetys voidaan myöhemmin siirtää jonotettuun cron-tehtävään.

Lähettäjäksi tulee WordPressin oletusosoite (admin_email). Vastauksia varten viestiin lisätään `Reply-To`-otsake, joka on lähettävän käyttäjän sähköpostiosoite.

### Lähetysloki

Sivun alaosassa näkyy taulukko viimeisestä 20 lähetyksestä:

| Sarake | Sisältö |
| --- | --- |
| Aika | Lähetyksen aikaleima |
| Lähettäjä | Lähetyksen tehnyt käyttäjä |
| Tapahtuma | Tapahtuman otsikko (tai "Kaikki tapahtumat") |
| Aihe | Sähköpostin aihe |
| Lähetetty | Onnistuneiden lähetysten määrä |
| Epäonnistunut | `wp_mail()`-tason epäonnistumiset |
| Ohitettu | Osallistujat, joilta puuttui osoite |

Lokia tallennetaan max 50 viimeisintä merkintää WordPressin option-taulukkoon avaimella `rytkoset_event_messaging_log`. Vanhin merkintä poistuu, kun uusi tulee tilalle (FIFO).

## Oikeudet

Sivu, lähetyshandler ja lokin näkyminen on rajattu käyttäjille, joilla on `edit_others_event_registrations`-oikeus. Tämä kuuluu sekä `administrator`- että `event_organizer`-roolille. Lähetyshandleri tarkistaa oikeuden uudelleen `admin_post`-käsittelijässä ja vahvistaa nonce-merkin ennen lähetystä.

## Tekninen toteutus

Toteutus on tiedostossa `wp-content/themes/rytkoset-theme/inc/event-participants-messaging.php`.

Pääfunktiot:

- `rytkoset_theme_get_event_messaging_recipients($event_id, $status_filter)` — deduplikoitu vastaanottajalista + ohitettujen määrä
- `rytkoset_theme_personalize_event_message($body, $name, $event_title)` — placeholder-korvaus
- `rytkoset_theme_register_event_messaging_admin_page()` — rekisteröi adminisivun
- `rytkoset_theme_render_event_messaging_admin_page()` — renderöi sivun
- `rytkoset_theme_send_event_participants_message()` — `admin_post`-handleri, joka lähettää viestit ja kirjaa lokin
- `rytkoset_theme_append_event_messaging_log($entry)` / `rytkoset_theme_get_event_messaging_log($limit)` — lokin tallennus ja haku

Vastaanottajien haku hyödyntää [`event-participants-admin.php`](../wp-content/themes/rytkoset-theme/inc/event-participants-admin.php):n olemassa olevia funktioita `rytkoset_theme_get_event_participants()` ja `rytkoset_theme_get_all_events_participants()`.

## Rajaus tässä vaiheessa

- Vain sähköposti (ei SMS)
- Vain tekstimuotoinen viesti (ei HTML-mallia, ei liitteitä)
- Ei viestipohjien tallennusta
- Ei queue/rate-limit-mekanismia — pitkät listat voivat hidastua
- Ei unsubscribe-linkkejä
- Loki ei näytä per-vastaanottaja-tasoa (vain aggregoidut laskurit)
- Ei AcyMailing-integraatiota (jätetty seuraavaan iteraatioon)
