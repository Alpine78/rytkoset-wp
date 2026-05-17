# Tapahtumakohtainen osallistujalista adminissa

Tämä dokumentti kuvaa tiketin `#72` toteutuksen.

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
| Sähköposti | Osallistujan sähköposti (ilmaisessa) tai yhteyshenkilön sähköposti (maksullisessa) |
| Puhelin | Puhelinnumero |
| Ruokavalio | Ruokarajoitteet tai allergiat |
| Status | Osallistumisen tila |
| Lähde | `Maksuton` tai `Maksullinen` |
| Tapahtuma | Tapahtuman nimi linkkinä (vain "Kaikki tapahtumat" -näkymässä) |
| Pvm | Ilmoittautumisen tai tilauksen luontipäivä |
| Muokkaa | Linkki osallistumisen muokkaamiseen (`event_registration`-postille tai WooCommerce-tilaukselle) |

Jos tapahtumalla ei ole yhtään osallistujaa valituilla suodattimilla, taulukko näyttää "Ei osallistujia tällä suodatuksella".

## Lähteet ja normalisointi

Näkymä yhdistää kaksi eri lähdettä yhtenäiseen rivi­rakenteeseen:

### Maksuttomat ilmoittautumiset

Lähde: `event_registration` -sisältötyyppi. Meta-avain `_rytkoset_registration_event_id` kytkee ilmoittautumisen tapahtumaan. Status tulee meta-avaimesta `_rytkoset_registration_status`.

### Maksulliset ilmoittautumiset

Lähde: WooCommerce-tilaukset, joissa on tapahtuman meta-avaimeen `_rytkoset_event_product_id` tallennettu tuote. Status seuraa tilauksen WooCommerce-statusta.

- **Tampere 2026**: käytetään olemassa olevaa moniosallistujarakennetta — tilauksen checkout-kentistä puretaan jokainen osallistuja erikseen omaksi riviksi.
- **Muut maksulliset tapahtumat**: yksi rivi per tilaus, tiedot tilauksen laskutustiedoista.

### Yhtenäinen rivirakenne

```
name          – osallistujan nimi
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

Tämä rakenne on suunniteltu CSV-vientiä (`#12`) varten.

## Oikeudet

Sivu on näkyvissä käyttäjillä, joilla on `edit_others_event_registrations`-oikeus. Tämä kuuluu sekä `administrator`- että `event_organizer`-roolille.

## Tekninen toteutus

Toteutus on tiedostossa `wp-content/themes/rytkoset-theme/inc/event-participants-admin.php`.

Pääfunktiot:

- `rytkoset_theme_get_event_free_participants($event_id, $status_filter)` — hakee ilmaiset osallistujat ja normalisoi rivit
- `rytkoset_theme_get_event_paid_participants($event_id)` — hakee maksulliset osallistujat WooCommercesta
- `rytkoset_theme_get_event_participants($event_id, $status_filter)` — yhdistää molemmat lähteet
- `rytkoset_theme_get_all_events_participants($status_filter)` — kerää osallistujat kaikista tapahtumista
- `rytkoset_theme_register_event_participants_admin_page()` — rekisteröi adminisivun
- `rytkoset_theme_render_event_participants_admin_page()` — renderöi sivun

## Rajaus tässä vaiheessa

- Ei CSV-vientiä (tulossa tiketissä `#12`)
- Ei massatoimintoja osallistujille
- Ei ilmoittautumisten tilamuutosta suoraan listanäkymästä (tehdään `event_registration`-postilomakkeella)
