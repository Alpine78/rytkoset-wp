# Tapahtumakohtainen osallistujalista adminissa

Tämä dokumentti kuvaa tikettien `#72` (näkymä) ja `#73` (CSV-vienti) toteutukset.

## Tavoite

Ylläpitäjä ja tapahtumajärjestäjä näkevät kaikki tapahtuman osallistujat yhdestä admin-näkymästä riippumatta siitä, onko kyseessä ilmainen tapahtuma (lomakepohjainen ilmoittautuminen) vai maksullinen tapahtuma (WooCommerce-tilaus).

## Sijainti

`Tapahtumat > Osallistujat`

## Mitä näkymä näyttää

### Tapahtumavalitsin

Sivun yläosassa on dropdown-valitsin, josta voi valita yksittäisen tapahtuman tai nähdä kaikkien tapahtumien osallistujat kerralla. Tapahtumat on järjestetty tapahtumapäivän mukaan lähimmästä tulevasta vanhimpaan menneeseen, ja päivämäärättömät tapahtumat ovat listan lopussa.

Oletuksena valittu: `Kaikki tapahtumat`.

### Statussuodatin

Näkymässä voi suodattaa osallistujia statuksen perusteella:

- Kaikki tilat
- Odottaa vahvistusta (`pending`)
- Vahvistettu (`confirmed`)
- Peruutettu (`cancelled`)
- Maksettu (WooCommerce-tilaus)

### Yhteenveto

Suodattimien yläpuolella näkyy osallistujamäärä eriteltynä lähteen mukaan: kuinka moni on ilmoittautunut lomakkeella ja kuinka moni WooCommercen kautta.

### Taulukko

Jokaiselta osallistujalta näkyy:

| Sarake | Selite |
| --- | --- |
| Nimi | Osallistujan nimi |
| Osallistujatyyppi | Tampere 2026 -variaatiosta tuleva osallistujatyyppi, esimerkiksi `Aikuinen` tai `Lapsi 3-12 vuotta` |
| Perjantain buffet | Osallistuuko henkilö perjantain buffet-illalliselle |
| Sähköposti | Osallistujan sähköposti (ilmaisessa) tai yhteyshenkilön sähköposti (maksullisessa) |
| Puhelin | Puhelinnumero |
| Ruokavalio | Ruokarajoitteet tai allergiat |
| Status | Osallistumisen tila |
| Lähde | `Maksuton` tai `Maksullinen` |
| Tapahtuma | Tapahtuman nimi linkkinä (vain "Kaikki tapahtumat" -näkymässä) |
| Pvm | Ilmoittautumisen tai tilauksen luontipäivä |
| Muokkaa | Linkki osallistumisen muokkaamiseen (`event_registration`-postille tai WooCommerce-tilaukselle) |

Jos tapahtumalla ei ole yhtään osallistujaa valituilla suodattimilla, taulukko näyttää "Ei osallistujia tällä suodatuksella".

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
| Lähde | `Maksuton` tai `Maksullinen` |
| Status | Luettava tilaselite |
| Ilmoittautunut | Ilmoittautumispäivä |
| Yhteyshenkilö | Tilaajan nimi (maksullisissa) |
| Yhteyshenkilön sähköposti | Tilaajan sähköposti (maksullisissa) |
| Tilausnumero | WooCommerce-tilausnumero (maksullisissa) |

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
participant_type – Tampere 2026 -osallistujatyyppi
friday_buffet – true/false perjantain buffet-illalliselle
email         – sähköposti
phone         – puhelinnumero
diet          – ruokarajoitteet
notes         – lisätiedot
status        – pending / confirmed / cancelled / processing / completed / ...
status_label  – luettava tilaselite
source        – "free" tai "paid"
created       – Unix-aikaleima
contact_name  – tilaajan nimi (maksullisessa)
contact_email – tilaajan sähköposti (maksullisessa)
edit_url      – suoralinkki adminiin
order_id      – WooCommerce-tilauksen ID (maksullisessa)
order_number  – WooCommerce-tilausnumero (maksullisessa)
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

## Liittyvät toiminnot

- **Massaviestintä:** sähköpostin lähettäminen osallistujille on toteutettu erillisellä sivulla `Tapahtumat > Viestintä`. Katso [event-participants-messaging.md](event-participants-messaging.md).
- **Tampere 2026 -osallistujat:** näkyvät tällä sivulla osana yhtenäistä listaa, kun tapahtumasuodattimesta valitaan Tampere 2026 -tapahtuma. Vanha `WooCommerce > Tampere 2026 osallistujat` -pikalinkkisivu poistettiin tiketissä `#194`, koska sama tieto on saatavilla rajatuilla oikeuksilla (`edit_others_event_registrations`) tästä yhtenäisestä näkymästä.

## Rajaus tässä vaiheessa

- Ei muita massatoimintoja osallistujille tällä sivulla (viestintä on omalla sivullaan)
- Ei ilmoittautumisten tilamuutosta suoraan listanäkymästä (tehdään `event_registration`-postilomakkeella)
