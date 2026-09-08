# Tapahtumakohtainen osallistujalista adminissa

Tämä dokumentti kuvaa tikettien `#72` (näkymä), `#73` (CSV-vienti) ja `#665`
(osallistujan käsin lisääminen ja peruminen) toteutukset.

## Tavoite

Ylläpitäjä ja tapahtumajärjestäjä näkevät kaikki tapahtuman osallistujat yhdestä admin-näkymästä riippumatta siitä, onko kyseessä ilmainen tapahtuma (lomakepohjainen ilmoittautuminen) vai maksullinen tapahtuma (WooCommerce-tilaus).

## Sijainti

`Tapahtumat > Osallistujat`

## Mitä näkymä näyttää

### Tapahtumavalitsin

Sivun yläosassa on dropdown-valitsin, josta voi valita yksittäisen tapahtuman tai nähdä kaikkien tapahtumien osallistujat kerralla. Tapahtumat on järjestetty tapahtumapäivän mukaan lähimmästä tulevasta vanhimpaan menneeseen, ja päivämäärättömät tapahtumat ovat listan lopussa.

Oletuksena valittu: `Kaikki tapahtumat`.

### Lisää osallistuja

Sivun otsikon vieressä on **Lisää osallistuja** -painike. Se avaa vakiomuotoisen
`Lisää uusi ilmoittautuminen` -näkymän. Kun tapahtumavalitsimesta on valittu
yksittäinen tapahtuma, tapahtuma esivalitaan (`?rytkoset_event_id=<id>`), jolloin
tapahtumakohtaiset lisävalinta- ja määräkentät näkyvät heti ilman kaksivaiheista
tallennusta.

Näkymä on tarkoitettu sivuston ulkopuolella (puhelimitse, sähköpostitse,
järjestäjän kautta) ilmoittautuneen lisäämiseen. Se ei luo WooCommerce-tilausta
eikä uutta osallistujarekisteriä, eikä tallennus lähetä automaattista kuittia tai
järjestäjäilmoitusta. Kentät on kuvattu [events.md](events.md):n kohdassa
"Osallistujan käsin lisääminen".

### Statussuodatin

Näkymässä voi suodattaa osallistujia statuksen perusteella:

- Kaikki tilat
- Odottaa vahvistusta (`pending`)
- Vahvistettu (`confirmed`)
- Peruutettu (`cancelled`)
- Maksettu (WooCommerce-tilaus)

### Yhteenveto

Suodattimien yläpuolella näkyy osallistujamäärä eriteltynä lähteen mukaan: kuinka moni on ilmoittautunut lomakkeella ja kuinka moni WooCommercen kautta.

Kun valitulla tapahtumalla on tapahtumakohtainen lisävalinta, näkymä näyttää
lisäksi valinnan otsikolla nimetyn yhteenvedon. Peruutetut ilmoittautumiset
ohitetaan. Jos määräkenttä on käytössä, yhteenveto laskee tallennetut määrät;
muuten se laskee ilmoittautumiset. Yhteenvedossa näkyy kokonaismäärä ja erittely
valinnan mukaan.

### Taulukko

Jokaiselta osallistujalta näkyy:

| Sarake | Selite |
| --- | --- |
| Nimi | Osallistujan nimi |
| Tapahtumakohtainen lisävalinta | Näkyy vain, kun yksittäiselle tapahtumalle on määritetty lisävalinta; otsikkona käytetään tapahtuman kenttäotsikkoa |
| Tapahtumakohtainen määrä | Näkyy vain, kun yksittäiselle tapahtumalle on määritetty määräkenttä; otsikkona käytetään tapahtuman kenttäotsikkoa |
| Osallistujatyyppi | Tampere 2026 -variaatiosta tuleva osallistujatyyppi, esimerkiksi `Aikuinen` tai `Lapsi 3-12 vuotta` |
| Perjantain buffet | Osallistuuko henkilö perjantain buffet-illalliselle |
| Sähköposti | Osallistujan sähköposti (ilmaisessa) tai yhteyshenkilön sähköposti (maksullisessa) |
| Puhelin | Puhelinnumero |
| Ruokavalio / huomiot | Ruokarajoitteet, allergiat ja lisätiedot |
| Status | Osallistumisen tila |
| Lähde | `Verkkolomake` tai `Käsin lisätty` maksuttomille, `Maksullinen` WooCommerce-riveille. Lähde ilman tallennettua metaa (ennen `#665` luodut rivit) näytetään muodossa `Verkkolomake`. |
| Tapahtuma | Tapahtuman nimi linkkinä (vain "Kaikki tapahtumat" -näkymässä) |
| Ilmoittautunut | Ilmoittautumisen tai tilauksen luontipäivä |
| Toiminnot | `Muokkaa`-linkki (`event_registration`-postille tai WooCommerce-tilaukselle) sekä `event_registration`-riveillä **Peru osallistuminen** / **Palauta ilmoittautuminen** -painike |

Jos tapahtumalla ei ole yhtään osallistujaa valituilla suodattimilla, taulukko näyttää "Ei osallistujia valitulla suodatuksella".

### Osallistumisen peruminen ja palauttaminen

`event_registration`-riveillä (sekä verkkolomake- että käsin lisätyt) on
Toiminnot-sarakkeessa vahvistusta vaativa toiminto:

- **Peru osallistuminen** vaihtaa tilaksi `cancelled` (**Peruttu**). Tietuetta ei
  poisteta eikä siirretä roskakoriin, joten muutos on korjattavissa ja
  ilmoittautumishistoria säilyy.
- **Palauta ilmoittautuminen** näkyy `cancelled`-riveillä ja vaihtaa tilan
  takaisin `confirmed`-arvoon (**Vahvistettu**).

Peruttu henkilö poistuu aktiivisesta osallistujamäärän yhteenvedosta,
tapahtumaviestien vastaanottajista ja tulevan palautepyynnön kohderyhmästä.
Hänet löytää edelleen **Peruttu**-suodattimella. Peruminen ei ole
tietosuoja-asetuksen mukainen poistopyyntö; henkilötiedot anonymisoidaan
normaalin säilytys- ja poistopolun mukaisesti.

Toiminto vaatii `edit_others_event_registrations`-oikeuden, oman noncen ja
selainvahvistuksen. Maksullisten WooCommerce-ilmoittautumisten peruutus,
hyvitys ja moniosallistujatilauksen yksittäisen henkilön poisto käsitellään
WooCommercen omalla tilaus-/hyvityspolulla, eikä niitä tehdä tästä
toiminnosta.

### CSV-vienti

Suodattimien vieressä on **Vie CSV** -painike, joka lataa osallistujat CSV-tiedostona. Vienti kunnioittaa nykyisiä suodattimia: jos tapahtuma- tai statussuodatin on käytössä, vientiin tulee vain niitä vastaavat rivit. Tiedoston nimi sisältää tapahtuman slugin (tai `kaikki-tapahtumat`) ja mahdollisen statussuodatimen.

Tiedosto on UTF-8-koodattu BOM-merkillä, joten Excel ja LibreOffice tunnistavat ääkköset suoraan. Erottimena on puolipiste (`;`), joka soveltuu suomalaiseen Excel-asetukseen.

Sarakkeet:

| Sarake | Sisältö |
| --- | --- |
| Tapahtuma | Tapahtuman otsikko |
| Nimi | Osallistujan nimi |
| Osallistujatyyppi | Tampere 2026 -osallistujilla tuotteen variaatio |
| Perjantain buffet | `Kyllä` tai `Ei` |
| Sähköposti | Osallistujan sähköposti |
| Puhelin | Puhelinnumero |
| Ruokavalio / huomiot | Ruokarajoitteet ja lisätiedot yhdistettynä |
| Lähde | `Verkkolomake` / `Käsin lisätty` / `Maksullinen` |
| Status | Luettava tilaselite |
| Ilmoittautunut | Ilmoittautumispäivä |
| Yhteyshenkilö | Tilaajan nimi (maksullisissa) |
| Yhteyshenkilön sähköposti | Tilaajan sähköposti (maksullisissa) |
| Tilausnumero | WooCommerce-tilausnumero (maksullisissa) |

Kun vienti on rajattu yksittäiseen tapahtumaan, CSV:n loppuun lisätään
tapahtuman asetusten mukaan lisävalinta- ja/tai määräsarake samoilla otsikoilla
kuin admin-taulukossa. `Kaikki tapahtumat` -viennissä näitä dynaamisia
sarakkeita ei lisätä, koska tapahtumien kenttäotsikot voivat poiketa toisistaan.

### GDPR-anonymisointi

Kun yksittäinen tapahtuma on valittuna, sivulla näkyy **GDPR: maksuttomien ilmoittautumisten anonymisointi** -toiminto. Se anonymisoi valitun tapahtuman maksuttomat `event_registration`-ilmoittautumiset kaikista statuksista (`pending`, `confirmed`, `cancelled`).

Anonymisointi poistaa osallistujan nimen, sähköpostiosoitteen, ruokarajoitteet ja lisätiedot. Ilmoittautumisrivi, tapahtumaviittaus ja status säilyvät raportointia varten. Toiminto vaatii erillisen checkbox-vahvistuksen ja nonce-tarkistuksen.

Toiminto ei koske WooCommerce-tilauksia, Tampere 2026 -osallistujakenttiä tai maksullisten tapahtumien tilaajatietoja.

## Lähteet ja normalisointi

Näkymä yhdistää kaksi eri lähdettä yhtenäiseen rivi­rakenteeseen:

### Maksuttomat ilmoittautumiset

Lähde: `event_registration` -sisältötyyppi. Meta-avain `_rytkoset_registration_event_id` kytkee ilmoittautumisen tapahtumaan. Status tulee meta-avaimesta `_rytkoset_registration_status`.

### Maksulliset ilmoittautumiset

Lähde: WooCommerce-tilaukset, joissa on tapahtuman meta-avaimeen `_rytkoset_event_product_id` tallennettu tuote. Status seuraa tilauksen WooCommerce-statusta.

- **Tampere 2026**: käytetään olemassa olevaa moniosallistujarakennetta — tilauksen checkout-kentistä puretaan jokainen osallistuja erikseen omaksi riviksi. Osallistujatyyppi tulee tilauksen variaatiosta ja perjantain buffet-valinta checkout-kentästä.
- **Muut maksulliset tapahtumat**: yksi rivi per tilaus, tiedot tilauksen laskutustiedoista.

### Yhtenäinen rivirakenne

```
name          – osallistujan nimi
choice        – maksuttoman ilmoittautumisen tapahtumakohtainen lisävalinta
quantity      – maksuttoman ilmoittautumisen tapahtumakohtainen määrä
participant_type – Tampere 2026 -osallistujatyyppi
friday_buffet – true/false perjantain buffet-illalliselle
email         – sähköposti
phone         – puhelinnumero
diet          – ruokarajoitteet
notes         – lisätiedot
status        – pending / confirmed / cancelled / processing / completed / ...
status_label  – luettava tilaselite
source        – "free" tai "paid" (maksuton/maksullinen -haaran erottelu)
origin        – "web_form" / "manual" (maksuton) tai "paid"
origin_label  – näkyvä lähde: "Verkkolomake" / "Käsin lisätty" / "Maksullinen"
registration_id – event_registration-postin ID (0 maksullisilla riveillä)
created       – Unix-aikaleima
contact_name  – tilaajan nimi (maksullisessa)
contact_email – tilaajan sähköposti (maksullisessa)
edit_url      – suoralinkki adminiin
order_id      – WooCommerce-tilauksen ID (maksullisessa)
order_number  – WooCommerce-tilausnumero (maksullisessa)
order_status  – WooCommerce-tilauksen status (maksullisessa; "" maksuttomilla)
event_id      – tapahtuman post ID
event_title   – tapahtuman otsikko
```

Tämä rakenne on käytössä myös CSV-viennissä.

## Oikeudet

Sivu ja CSV-vienti ovat näkyvissä käyttäjillä, joilla on `edit_others_event_registrations`-oikeus. Tämä kuuluu sekä `administrator`- että `event_organizer`-roolille. Vienti tarkistaa oikeuden uudelleen `admin_post`-käsittelijässä ja vahvistaa nonce-merkin ennen tiedoston luontia.

## Tekninen toteutus

Toteutus on tiedostossa `wp-content/themes/rytkoset-theme/inc/event-participants-admin.php`.

Pääfunktiot:

- `rytkoset_theme_get_event_free_participants($event_id, $status_filter)` — hakee ilmaiset osallistujat ja normalisoi rivit
- `rytkoset_theme_get_event_paid_participants($event_id)` — hakee maksulliset osallistujat WooCommercesta
- `rytkoset_theme_get_event_participants($event_id, $status_filter)` — yhdistää molemmat lähteet
- `rytkoset_theme_get_all_events_participants($status_filter)` — kerää osallistujat kaikista tapahtumista
- `rytkoset_theme_register_event_participants_admin_page()` — rekisteröi adminisivun
- `rytkoset_theme_render_event_participants_admin_page()` — renderöi sivun
- `rytkoset_theme_render_event_participants_export_form()` — renderöi CSV-vientipainikkeen
- `rytkoset_theme_export_event_participants_csv()` — `admin_post`-handleri, joka tuottaa CSV-tiedoston
- `rytkoset_theme_render_event_participants_anonymization_form()` — renderöi tapahtumakohtaisen anonymisointilomakkeen
- `rytkoset_theme_handle_event_free_registrations_anonymization()` — `admin_post`-handleri, joka anonymisoi valitun tapahtuman maksuttomat ilmoittautumiset
- `rytkoset_theme_get_event_participants_add_url()` / `rytkoset_theme_render_event_participants_add_button()` — "Lisää osallistuja" -painike ja sen kohde-URL (esivalittu tapahtuma)
- `rytkoset_theme_render_event_registration_cancel_action()` — Toiminnot-sarakkeen Peru/Palauta-lomake yhdelle riville
- `rytkoset_theme_handle_event_registration_cancel_toggle()` — `admin_post`-handleri (`rytkoset_toggle_event_registration_status`), joka vaihtaa tilan `cancelled` ⇄ `confirmed`
- `rytkoset_theme_render_event_registration_toggle_notice()` — Peru/Palauta-toiminnon palaute
- `rytkoset_theme_get_event_feedback_inactive_order_statuses()` — WooCommerce-statukset (`cancelled` / `refunded` / `failed`), joita ei koskaan oteta viestinnän tai palautepyynnön vastaanottajiksi
- `rytkoset_theme_filter_active_event_participants()` — rajaa rivit aktiiviseen osallistujajoukkoon viestintää ja palautepyyntöä varten (perutut maksuttomat + perutut/hyvitetyt/epäonnistuneet tilaukset pois)

Tilanvaihdon (nimen sync mukaan lukien) tekee `event-registrations.php`:n
`rytkoset_theme_set_event_registration_status()`.

GDPR-exporter, eraser ja yhteinen anonymisointihelper ovat tiedostossa `wp-content/themes/rytkoset-theme/inc/event-registration-privacy.php`.

## Liittyvät toiminnot

- **Massaviestintä:** sähköpostin lähettäminen osallistujille on toteutettu erillisellä sivulla `Tapahtumat > Viestintä`. Katso [event-participants-messaging.md](event-participants-messaging.md).
- **Tampere 2026 -osallistujat:** näkyvät tällä sivulla osana yhtenäistä listaa, kun tapahtumasuodattimesta valitaan Tampere 2026 -tapahtuma. Vanha `WooCommerce > Tampere 2026 osallistujat` -pikalinkkisivu poistettiin tiketissä `#194`, koska sama tieto on saatavilla rajatuilla oikeuksilla (`edit_others_event_registrations`) tästä yhtenäisestä näkymästä.

## Rajaus tässä vaiheessa

- Ei muita massatoimintoja osallistujille tällä sivulla kuin maksuttomien ilmoittautumisten anonymisointi (viestintä on omalla sivullaan)
- Listanäkymästä voi perua ja palauttaa vain `event_registration`-osallistumisia (`cancelled` ⇄ `confirmed`); muut tilamuutokset tehdään `event_registration`-postilomakkeella
- Ei käsin luotua WooCommerce-tilausta tai maksumerkintää, ei osallistujien massatuontia
- Ei WooCommerce-maksun peruutusta, hyvitystä tai moniosallistujatilauksen yksittäisen henkilön peruutusta tästä näkymästä
- Ei WooCommerce-tilausten tai Tampere 2026 -osallistujakenttien anonymisointia tästä näkymästä
