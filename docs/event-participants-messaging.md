# Massaviestintä tapahtuman osallistujille

Tämä dokumentti kuvaa tikettien `#74` ja `#264` toteutuksen, `#665`:n
lisäyksen aktiivisesta vastaanottajajoukosta sekä `#666`:n
`{palautelinkki}`-placeholderin ja "Palautekysely"-jonotusosion. Itse
palautekyselyn asetukset, julkinen lomake ja tuloskooste on kuvattu
tiedostossa [event-feedback.md](event-feedback.md).

## Tavoite

Ylläpitäjä ja tapahtumajärjestäjä voivat lisätä sähköpostiviestin tapahtuman osallistujille WordPressin administa. Viestit lähtevät taustalla WP-Cron-jonosta, jotta noin 18 sähköpostin tuntirajaa ei ylitetä.

## Sijainti

`Tapahtumat > Viestintä`

## Mitä sivu sisältää

### Suodattimet

Sivun yläosassa on samat suodattimet kuin osallistujalistalla:

- **Tapahtuma:** yksittäinen tapahtuma tai `Kaikki tapahtumat`
- **Status:** kaikki, jokin yksittäinen rekisteröintistatus (`pending`/`confirmed`/`cancelled`) tai `Maksulliset` (WooCommerce-tilaukset)

Suodattimien jälkeen näkyy vastaanottajamäärä:

> *"Viesti lisätään jonoon 23 vastaanottajalle (osoitteita puuttuu 2)."*

Osoitteita puuttuvat osallistujat ohitetaan automaattisesti. Vastaanottajat deduplikoidaan sähköpostiosoitteen perusteella (sama yhteyshenkilö Tampere 2026 -tilauksessa lasketaan yhdeksi vastaanottajaksi).

**Aktiivinen vastaanottajajoukko (`#665`):** vastaanottajalistasta rajataan aina
pois ne WooCommerce-tilaukset, joiden status on `cancelled`, `refunded` tai
`failed`, sekä perutut (`cancelled`) maksuttomat ilmoittautumiset — myös silloin,
kun statussuodattimena on `Kaikki`. Perutut maksuttomat ilmoittautumiset
säilyvät vastaanottajissa vain, jos statussuodattimeksi valitaan nimenomaan
`Peruutettu`, jolloin viesti voidaan tietoisesti lähettää perutuille
osallistujille. Sama rajaus on tulevan tapahtumakohtaisen palautepyynnön
(`#666`) vastaanottajajoukon turvallinen pohja. Rajattavat tilaukset voi muokata
suotimella `rytkoset_theme_event_feedback_inactive_order_statuses`.

### Viestilomake

| Kenttä | Selite |
| --- | --- |
| Aihe | Sähköpostin otsikkorivi (pakollinen, max 200 merkkiä) |
| Viesti | Viestin runko tekstimuotoisena (pakollinen) |

Lomake hyväksyy kolme placeholderia:

- `{nimi}` → osallistujan nimi (korvataan jokaisen vastaanottajan kohdalla erikseen)
- `{tapahtuma}` → tapahtuman otsikko (korvataan jokaisen vastaanottajan kohdalla erikseen)
- `{palautelinkki}` → valitun tapahtuman julkisen palautelomakkeen osoite (`#666`). Ratkaistaan
  kerran per jonotyö tapahtuman ID:stä, ei per vastaanottaja, koska linkillä ei ole
  henkilökohtaista tunnistetta. Toimii vain kun jono on jonotettu yhdelle yksittäiselle
  tapahtumalle — `Kaikki tapahtumat` -lähetyksellä placeholder korvautuu tyhjällä.

Esim. *"Hei {nimi}, tervetuloa tapahtumaan {tapahtuma}!"* lähetetään yksilöllisesti jokaiselle.

### Lähetys ja jono

Lähetyspainike on muodossa "Lisää jonoon X vastaanottajalle" ja näyttää tarkistuksen ennen jonotusta. Jos vastaanottajia on 0, painike on disabloitu.

Admin-lomake ei lähetä viestejä heti. Se tallentaa vastaanottajat, aiheen, viestin, lähettäjän ja `Reply-To`-osoitteen lähetysjonoon. WP-Cron käsittelee jonon vanhimmasta viestistä alkaen ja tekee jokaiselle vastaanottajalle oman `wp_mail()`-kutsun.

Jono noudattaa rullaavaa tuntirajaa: `rytkoset_event_messaging_send_attempts`-option perusteella lasketaan viimeisen 60 minuutin `wp_mail()`-yritykset, ja uusi cron-ajo lähettää vain sen verran, että 18 yrityksen raja ei ylity. Sekä onnistuneet että epäonnistuneet `wp_mail()`-kutsut lasketaan yrityksiksi.

Epäonnistuneet vastaanottajakohtaiset lähetykset merkitään tässä MVP:ssä lopullisesti epäonnistuneiksi. Kun jonotyöllä ei ole enää odottavia vastaanottajia, työ poistuu jonosta ja siitä kirjoitetaan koontirivi lähetyslokiin.

WP-Cron käynnistyy normaalisti sivulatausten yhteydessä. Tuotannossa lähetyksen tasaisuus paranee, jos palvelimella kutsutaan WordPressin `wp-cron.php`-tiedostoa oikealla cron-ajolla.

Lähettäjäksi tulee WordPressin oletusosoite (admin_email). Vastauksia varten viestiin lisätään `Reply-To`-otsake, joka on lähettävän käyttäjän sähköpostiosoite.

### Lähetysjonon tila

Sivulla näkyy lähetysjonon taulukko ennen lokia:

| Sarake | Sisältö |
| --- | --- |
| Luotu | Jonotyön luontiaika |
| Lähettäjä | Jonotyön luonut käyttäjä |
| Tapahtuma | Tapahtuman otsikko (tai "Kaikki tapahtumat") |
| Aihe | Sähköpostin aihe |
| Tila | `Jonossa` tai `Käsittelyssä` |
| Jonossa | Odottavien vastaanottajien määrä |
| Lähetetty | Onnistuneiden lähetysten määrä |
| Epäonnistunut | Epäonnistuneiden lähetysten määrä |
| Ohitettu | Osallistujat, joilta puuttui osoite |
| Viimeksi lähetetty | Viimeisin vastaanottajakohtainen lähetysaika |

### Lähetysloki

Sivun alaosassa näkyy taulukko viimeisestä 20 valmistuneesta jonotyöstä:

| Sarake | Sisältö |
| --- | --- |
| Aika | Jonotyön valmistumisaika |
| Lähettäjä | Lähetyksen tehnyt käyttäjä |
| Tapahtuma | Tapahtuman otsikko (tai "Kaikki tapahtumat") |
| Aihe | Sähköpostin aihe |
| Lähetetty | Onnistuneiden lähetysten määrä |
| Epäonnistunut | `wp_mail()`-tason epäonnistumiset |
| Ohitettu | Osallistujat, joilta puuttui osoite |

Lokia tallennetaan max 50 viimeisintä merkintää WordPressin option-taulukkoon avaimella `rytkoset_event_messaging_log`. Vanhin merkintä poistuu, kun uusi tulee tilalle (FIFO).

### Palautekysely-osio (#666)

Kun yksittäinen tapahtuma on valittuna (ei `Kaikki tapahtumat`), tapahtuman
palautekysely ei ole `Ei palautekyselyä` -tilassa ja tapahtuma on ohi, viesti-
lomakkeen yläpuolelle ilmestyy oma "Palautekysely"-osio. Se näyttää
vastaanottajaerittelyn (osallistujarivit / yksilölliset osoitteet / ilman
osoitetta jäävät) ja "Lisää palautepyyntö jonoon" -painikkeen, joka lähettää
kiinteän aihe-/runkomallin (tapahtuman johdantoteksti + `{palautelinkki}`)
samaan jonoon kuin yllä oleva yleinen viestilomake. Painike katoaa, kun
palautepyyntö on jo jonotettu tälle tapahtumalle (`_rytkoset_event_feedback_queued_at`).

## Oikeudet

Sivu, lähetyshandler ja lokin näkyminen on rajattu käyttäjille, joilla on `edit_others_event_registrations`-oikeus. Tämä kuuluu sekä `administrator`- että `event_organizer`-roolille. Lähetyshandleri tarkistaa oikeuden uudelleen `admin_post`-käsittelijässä ja vahvistaa nonce-merkin ennen lähetystä.

## Tekninen toteutus

Toteutus on tiedostossa `wp-content/themes/rytkoset-theme/inc/event-participants-messaging.php`.

Pääfunktiot:

- `rytkoset_theme_get_event_messaging_recipients($event_id, $status_filter)` — deduplikoitu vastaanottajalista + ohitettujen määrä
- `rytkoset_theme_personalize_event_message($body, $name, $event_title, $feedback_link = '')` — placeholder-korvaus, `#666`:n `{palautelinkki}` mukaan lukien
- `rytkoset_theme_register_event_messaging_admin_page()` — rekisteröi adminisivun
- `rytkoset_theme_render_event_messaging_admin_page()` — renderöi sivun
- `rytkoset_theme_send_event_participants_message()` — `admin_post`-handleri, joka validoi lomakkeen ja lisää työn jonoon
- `rytkoset_theme_enqueue_event_messaging_job($args)` — luo jonotyön non-autoloaded `rytkoset_event_messaging_queue`-optioniin
- `rytkoset_theme_process_event_messaging_queue()` — WP-Cron-prosessori, joka purkaa jonoa 18 viestiä / rullaava 60 minuuttia -rajalla
- `rytkoset_theme_get_event_messaging_send_attempts()` — lukee viimeisen tunnin lähetysyritykset `rytkoset_event_messaging_send_attempts`-optiosta
- `rytkoset_theme_append_event_messaging_log($entry)` / `rytkoset_theme_get_event_messaging_log($limit)` — lokin tallennus ja haku

Vastaanottajien haku hyödyntää [`event-participants-admin.php`](../wp-content/themes/rytkoset-theme/inc/event-participants-admin.php):n olemassa olevia funktioita `rytkoset_theme_get_event_participants()` ja `rytkoset_theme_get_all_events_participants()`. Rivit ajetaan `rytkoset_theme_filter_active_event_participants()`-funktion läpi ennen vastaanottajien muodostamista.

## Rajaus tässä vaiheessa

- Vain sähköposti (ei SMS)
- Vain tekstimuotoinen viesti (ei HTML-mallia, ei liitteitä)
- Ei viestipohjien tallennusta
- Ei unsubscribe-linkkejä
- Loki ei näytä per-vastaanottaja-tasoa (vain aggregoidut laskurit)
- Ei AcyMailing-integraatiota tässä ratkaisussa
