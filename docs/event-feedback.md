# Tapahtumakohtainen palautekysely

Tämä dokumentti kuvaa tiketin `#666` toteutuksen: tapahtuman jälkeinen,
anonyymi palautekysely osallistujille. Ilmoittautuminen, osallistujalista ja
massaviestintä on kuvattu tiedostoissa
[events.md](events.md), [event-participants-admin.md](event-participants-admin.md)
ja [event-participants-messaging.md](event-participants-messaging.md).

## Tavoite

Tapahtuman jälkeen ylläpitäjä voi pyytää lyhyen, anonyymin palautteen
aktiivisilta osallistujilta tulevien tapahtumien kehittämiseksi. Palaute ei
liity uutiskirjeeseen tai markkinointiin, eikä sitä kytketä kehenkään
yksittäiseen osallistujaan.

## Sijainti

- Tapahtuman muokkausnäkymä: **Palautekysely**-laatikko (asetukset)
- `Tapahtumat > Viestintä`: **Palautekysely**-osio (lähetys, ks.
  [event-participants-messaging.md](event-participants-messaging.md#palautekysely-osio-666))
- `Tapahtumat > Palaute` (tuloskooste)
- Julkinen lomake: `/palaute/{tapahtuma-id}/`

## Tapahtuman asetukset

Tapahtuman muokkausnäkymän **Palautekysely**-laatikossa:

| Kenttä | Selite |
| --- | --- |
| Palautekyselyn tila | **Ei palautekyselyä** (oletus kaikille tapahtumille), **Lähetä käsin** tai **Lähetä automaattisesti** |
| Automaattinen lähetysaika | Pakollinen vain automaattitilassa. Ajan pitää olla tapahtumapäivän jälkeen — jos ei, tila tallennetaan hiljaisesti `Lähetä käsin`-arvoon |
| Palautteen määräpäivä | Valinnainen. Tyhjänä kysely pysyy avoinna toistaiseksi |
| Johdantoteksti | Valinnainen, max 500 merkkiä. Näytetään lomakkeella ja palautepyynnön viestin alussa |
| Ilmoita järjestäjille uusista vastauksista | Valinnainen rasti, oletuksena pois. Ks. "Järjestäjäilmoitus" alla |

Jos palautepyyntö on jo lisätty lähetysjonoon (käsin tai automaattisesti),
laatikko näyttää sen ajankohdan eikä asetusten muokkaaminen sen jälkeen
koskaan poista tätä tilaa huomaamatta — jonotusaikaleima
(`_rytkoset_event_feedback_queued_at`) on oma meta-kenttänsä, jota asetusten
tallennusfunktio ei koskaan kirjoita.

Tampere 2026 -sukujuhlalle käytetään **Lähetä käsin** -tilaa, jos
palautekysely otetaan sille käyttöön (ylläpidon oma asetusvalinta, ei
koodissa pakotettu — kysely on tapahtumakohtaisesti geneerinen kaikille
tapahtumille).

## Julkinen lomake

Osoite on `/palaute/{tapahtuma-id}/`. Lomake on avoinna vain, kun:

1. tapahtuman palautekyselyn tila ei ole **Ei palautekyselyä**,
2. tapahtumapäivä on ohi, ja
3. mahdollista määräpäivää ei ole ohitettu.

Muulloin sivu näyttää selkeän tilaviestin lomakkeen sijaan. Linkki toimii
riippumatta siitä, onko palautepyyntöä vielä lähetetty — jaettu linkki
avautuu heti kun yllä olevat ehdot täyttyvät.

Lomake sisältää:

1. **Kokonaisarvio tapahtumasta**, asteikko 1–5 (pakollinen, natiivi
   radiogroup)
2. **Mikä onnistui hyvin?** (valinnainen, max 500 merkkiä)
3. **Mitä voisimme parantaa?** (valinnainen, max 500 merkkiä)
4. **Toiveita tuleviin tapahtumiin** (valinnainen, max 500 merkkiä)

Turvallisuus: honeypot-kenttä tarkistetaan ennen noncea, nonce vaaditaan,
oma IP-perusteinen lähetysrajoitin (oletus 5 lähetystä / 10 min, suotimet
`rytkoset_theme_event_feedback_rate_limit` / `..._rate_limit_window`, ei jaeta
`inc/event-registrations.php`:n rekisteröinnin rajoittimen kanssa). Onnistunut
lähetys uudelleenohjaa (PRG), joten sivun päivitys ei lähetä vastausta
uudelleen. Reitti lähettää `noindex, nofollow`-ohjeet (`wp_robots` + Rank
Math -suodin).

**Tietoinen rajoitus:** koska vastaajaa ei tunnisteta eikä linkissä ole
henkilökohtaista tunnistetta (tiketin vaatimus), osoite on paljas, arvattava
tapahtuma-ID ilman salaisuutta. Kuka tahansa tapahtuma-ID:n tietävä voi
vastata kyselyn ollessa avoinna. Tämä on tietoinen valinta, ei puute — katso
myös `docs/tietosuoja.md`.

## Järjestäjäilmoitus uudesta vastauksesta

Palautekysely-laatikon rasti **"Ilmoita järjestäjille uusista vastauksista"**
(oletuksena pois) lähettää jokaisesta onnistuneesta lomakelähetyksestä
tekstimuotoisen sähköpostin tapahtuman **omille** järjestäjäilmoitusten
vastaanottajille — samaan `_rytkoset_event_organizer_notification_recipients`
-kenttään, jota myös maksullisten tilausten ja maksuttomien ilmoittautumisten
järjestäjäilmoitukset käyttävät (`inc/events.php`). Ei erillistä
osoitelistaa: yksi kenttä hallitsee kaikkia tapahtuman järjestäjäilmoituksia.

- Tyhjä vastaanottajakenttä tarkoittaa, ettei ilmoitusta lähetetä, vaikka rasti
  olisi päällä — sama sääntö kuin muillakin järjestäjäilmoituksilla, ei
  `admin_email`-varaosoitetta.
- Viesti sisältää kokonaisarvion, täytetyt vapaatekstivastaukset (tyhjät
  kysymykset jätetään pois) ja linkin `Tapahtumat > Palaute` -koosteeseen.
  Koska vastaus on anonyymi, viestissä ei ole eikä voi olla osallistujan
  nimeä tai sähköpostia.
- Lähetys on riippumaton lomakkeen kiitossivun näyttämisestä: epäonnistunut
  tai ohitettu ilmoitus ei koskaan estä kävijän onnistunutta lähetystä.
- Ei kuristusta per tapahtuma — jokainen läpäissyt lähetys (honeypot + nonce +
  IP-rajoitin jo suodattaneet) lähettää oman viestinsä. Julkisen lomakkeen
  oma IP-rajoitin (5/10 min) on ainoa yläraja tälle.

## Anonyymi vastaus

Vastaus tallennetaan omaan, ei-julkiseen `event_feedback`
-sisältötyyppiin — nimi on tarkoituksella lyhyt: `wp_posts.post_type`-sarake
on `varchar(20)`, ja alkuperäinen `rytkoset_event_feedback_response` (33
merkkiä) sai `wp_insert_post()`:n epäonnistumaan hiljaisesti tuotannon
tapaisessa MySQL-ympäristössä paikallisessa Docker-testauksessa havaitulla
tavalla. Meta-avaimet: `_rytkoset_feedback_event_id`,
`_rytkoset_feedback_rating`, `_rytkoset_feedback_well`,
`_rytkoset_feedback_improve`, `_rytkoset_feedback_wishes`. **Ei koskaan**
nimeä, sähköpostia, käyttäjä-, ilmoittautumis- tai tilaustunnistetta, eikä
IP-osoitetta tallenneta postiin (IP käsitellään vain hetkellisesti
lähetysrajoittimen transientissa). `post_author` pakotetaan aina `0`:aan.

MVP hyväksyy mahdollisen useamman vastauksen samalta henkilöltä; täydellinen
duplikaattien esto vaatisi tunnisteen, evästeen tai kirjautumisen, mikä
heikentäisi anonymiteettiä suhteettomasti.

## Lähettäminen

Palautepyyntö lähtee nykyisen `Tapahtumat > Viestintä` -lähetysjonon kautta —
katso [event-participants-messaging.md](event-participants-messaging.md).
Kumpikin tila käyttää samaa vastaanottajajoukkoa: `#665`:n
`rytkoset_theme_filter_active_event_participants()`, joka rajaa pois perutut
maksuttomat ilmoittautumiset sekä perutut, hyvitetyt ja epäonnistuneet
tilaukset. Yhden ilmoittautumisen/tilauksen vastaanottajarivit deduplikoidaan
sähköpostiosoitteen mukaan ennen jonotusta.

- **Lähetä käsin:** `Tapahtumat > Viestintä` -sivun Palautekysely-osiossa
  näytetään vastaanottajaerittely (osallistujarivit / yksilölliset osoitteet
  / ilman osoitetta jäävät) ja "Lisää palautepyyntö jonoon" -painike. Painike
  on ainoa tapa jonottaa palautepyyntö käsin-tilassa; se ei koskaan jonotu
  itsestään.
- **Lähetä automaattisesti:** WP-Cron-pyyhkäisy (`rytkoset_process_event_feedback_auto_queue`,
  sama viiden minuutin aikataulu kuin viestijonon prosessorilla) tarkistaa
  automaattitilaiset tapahtumat, joiden lähetysaika on ohitettu ja joita ei
  ole vielä jonotettu, ja jonottaa palautepyynnön samalla polulla kuin käsin-
  painike. Vastaanottajajoukko ratkaistaan vasta jonotushetkellä, joten
  tapahtuman viimeisimmätkin peruutukset huomioidaan.
- **Idempotenssi:** `_rytkoset_event_feedback_queued_at` toimii
  jonotusmerkintänä (kysely NOT EXISTS -haussa), ja automaattipyyhkäisy
  käyttää samaa transienttilukkoa kuin viestijonon prosessori
  (`rytkoset_theme_process_event_messaging_queue()`), jotta kaksi
  samanaikaista cron-ajoa ei voi jonottaa samaa palautepyyntöä kahdesti.
- Jos automaattitilaisella, erääntyneellä tapahtumalla ei ole yhtään
  kelvollista vastaanottajaa, se merkitään silti jonotetuksi, jotta pyyhkäisy
  ei yritä sitä uudelleen loputtomiin.

Kiinteä viestimalli: aihe on `Palautetta tapahtumasta: {tapahtuma}`, runko
tapahtuman johdantoteksti + kiinteä kutsu + `{palautelinkki}`. Yleisen
viestilomakkeen vapaatekstiin voi myös itse lisätä `{palautelinkki}`, jos
haluaa muotoilla oman viestin.

## Tulokset

`Tapahtumat > Palaute` näyttää valitulle tapahtumalle:

- vastausmäärän
- kokonaisarvion keskiarvon (pyöristetty yhteen desimaaliin; "–" kun
  vastauksia ei ole, ei nollalla jakoa)
- vapaatekstivastaukset (arvio + kolme tekstiä per rivi)

Jokaisella rivillä on **Muokkaa**-toiminto, joka avaa inline-lomakkeen
kolmelle vapaatekstikentälle (arviota ei voi muokata). Käytetään, jos joku on
vahingossa kirjoittanut tunnistettavia tietoja vapaaseen tekstiin — ks.
"Tietosuoja ja säilytys" alla.

Ei CSV-vientiä, kaavioita, PDF:ää, AI-yhteenvetoa eikä tapahtumien välistä
analytiikkaa (tiketin rajaus).

## Oikeudet

`Tapahtumat > Palaute` sekä `Tapahtumat > Viestintä`:n Palautekysely-osio ja
sen lähetys- ja muokkaustoiminnot on rajattu `edit_others_event_registrations`
-oikeudella — sama oikeus kuin osallistujatyökaluilla
(`administrator` ja `event_organizer`). Kaikki kirjoittavat toiminnot
tarkistavat noncen.

## Tietosuoja ja säilytys

Vastaus on anonyymi jo tallennushetkellä, joten sillä ei ole samaa
säilytystarvetta kuin henkilötiedolla. **Assosiaatio on päättänyt, ettei
raakoja vapaatekstivastauksia poisteta automaattisella aikataulun mukaisella
ajolla** — tämä poikkeaa tiketin alkuperäisestä ehdotuksesta ("päätetään ja
toteutetaan määräaika, jonka jälkeen vapaat tekstit poistetaan"). Perustelu:
data on anonyymia, ja palautetta halutaan voida käydä läpi vielä tulevien
sukujuhlien (esim. 2029) suunnittelussa.

Koska vapaa tekstikenttä voi silti sisältää vahingossa syötettyä
tunnistettavaa tietoa, ylläpidolla on `Tapahtumat > Palaute` -sivulla
**Muokkaa**-toiminto, jolla yksittäisen vastauksen kolme vapaatekstikenttää
voi editoida tai tyhjentää (`rytkoset_theme_update_event_feedback_response_text()`).
Numeerinen arvio ei ole koskaan muokattavissa tätä kautta, joten
keskiarvokooste pysyy luotettavana.

Muuta:

- Palautetta ei lähetetä AcyMailingiin, WooCommerceen eikä AI-tukichatille.
- Palautepyyntö ei itsessään lisää ketään uutiskirjeelle.
- Julkisen lomakkeen arvattavuus (ei henkilökohtaista tunnistetta) on
  tietoinen kompromissi anonymiteetin vuoksi — ks. "Julkinen lomake" yllä.
- Oikeusperuste ja tietosuojaselosteen teksti: ks. `docs/tietosuoja.md`.

## Tekninen toteutus

Toteutus on tiedostossa
`wp-content/themes/rytkoset-theme/inc/event-feedback.php`.

Pääfunktiot:

- `rytkoset_theme_get_event_feedback_mode($event_id)` / `..._get_event_feedback_send_at_raw()` / `..._get_event_feedback_deadline_raw()` / `..._get_event_feedback_intro()` — asetusten getterit
- `rytkoset_theme_event_feedback_send_at_is_after_event($event_id, $send_at_raw)` — validoi automaattitilan lähetysajan
- `rytkoset_theme_event_feedback_survey_is_open($event_id)` — lomakkeen avoin-tila
- `rytkoset_theme_register_event_feedback_response_cpt()` — rekisteröi `event_feedback`-CPT:n, jakaa `event_registration`:n `capability_type`:n (`inc/event-roles.php`)
- `rytkoset_theme_render_event_feedback_page()` — julkisen `/palaute/{id}/`-reitin renderöinti + `template_redirect`-lähetyskäsittely
- `rytkoset_theme_handle_event_feedback_submission($event_id)` — validointi, sanitointi, tallennus, PRG-uudelleenohjaus
- `rytkoset_theme_event_feedback_notifies_organizers($event_id)` / `..._send_event_feedback_organizer_notification()` — järjestäjäilmoituksen opt-in ja lähetys, kutsutaan onnistuneen tallennuksen jälkeen ennen uudelleenohjausta
- `rytkoset_theme_get_event_feedback_recipients($event_id)` — käyttää `rytkoset_theme_get_event_participants()` + `rytkoset_theme_filter_active_event_participants()` + `rytkoset_theme_get_event_messaging_recipients()` (kaikki `event-participants-admin.php`/`event-participants-messaging.php`), lisää osallistujarivien kokonaismäärän esikatselua varten
- `rytkoset_theme_render_event_feedback_queue_section($event_id)` / `..._send_event_feedback_request()` — käsin-jonotuksen osio ja `admin_post`-handleri
- `rytkoset_theme_process_event_feedback_auto_queue()` — WP-Cron-pyyhkäisy, transienttilukolla
- `rytkoset_theme_get_event_feedback_responses($event_id)` / `..._get_event_feedback_average_rating()` — admin-koosteen data
- `rytkoset_theme_update_event_feedback_response_text()` / `..._handle_event_feedback_response_edit()` — ylläpidon muokkaustoiminto

## Rajaus tässä vaiheessa

- Ei yleistä kysely- tai lomakerakentajaa; neljä kysymystä on kiinteä.
- Ei henkilökohtaista vastauslinkkiä, vastaajan tunnistamista eikä pakotettua
  yhden vastauksen sääntöä.
- Ei osallistujakohtaisen sähköpostin keräämistä WooCommerce-tilauksille tai
  usean henkilön maksuttomille ilmoittautumisille — sama vastaanottajajoukko
  kuin yleisessä viestinnässä.
- Ei tulosten CSV-/PDF-vientiä, visualisointeja eikä AI-yhteenvetoa.
- Ei palautteeseen perustuvaa markkinointiprofilointia.
- Ei automaattista vapaatekstin poistoa — ks. "Tietosuoja ja säilytys".
