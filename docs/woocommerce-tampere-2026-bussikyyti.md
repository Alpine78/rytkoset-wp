# Bussikyyti: ilmoittautuminen ja maksu jälkikäteen

Tämä dokumentti kuvaa tiketin `#450` toimintamallin: bussikyydin (esim. Savo–Tampere–Savo) **ilmoittautumisten keräys ilman maksua**, ja maksu vasta kun kyydin toteutuminen on varmistunut.

> **Mallinmuutos.** Aiemmin bussikyyti oli **maksullinen** WooCommerce-variaatiotuote, jossa maksu perittiin heti ostaessa (tiketti `#442`, `inc/woocommerce-bus-transport.php`). Hallitus päätti, että kyytiläiset kerätään ensin ja maksu peritään vasta kun matka toteutuu (vähintään 20 lähtijää). Bussikyyti mallinnetaan nyt **maksuttomana tapahtumana**, jolloin käytössä on koko olemassa oleva ilmoittautumisinfra (lomake, Osallistujat-näkymä, CSV-vienti, joukkoviestintä, GDPR-työkalut). Vanha maksullinen tuotemalli on korvattu — ks. [Vanha maksullinen tuote](#vanha-maksullinen-tuote).

## Tavoite

- Kerätä bussikyytiin lähtijät (nimi, sähköposti, **lähtöpaikka**, matkustajamäärä) **ilman maksua**.
- Hallitus näkee yhdellä silmäyksellä lähtijämäärän ja lähtöpaikkajakauman → voi todeta täyttyykö vähimmäismäärä (esim. 20).
- Kun matka varmistuu, maksu peritään **manuaalisilla WooCommerce-tilauksilla** (Mollie-maksulinkki).

## Vaihe 1 — Bussikyytitapahtuman luonti (ylläpito)

1. **Tapahtumat → Lisää uusi.** Anna otsikko (esim. *Bussikyyti Tampereen sukujuhliin*), kuvaus ja ajankohta.
2. **Tapahtuman tiedot** -laatikko: aseta **Maksullisuus = Maksuton**. (Bussikyyti kerätään maksuttomalla lomakkeella; varsinainen maksu hoidetaan myöhemmin erikseen.) Poista tarvittaessa valinta **Kysy ruokavaliorajoitteet ja allergiat** — bussikyydissä ei ole tarjoiluita. Kirjoita **Hintateksti**-kenttään ehdollinen maksuselite, esim. `45 € (maksetaan myöhemmin, jos ilmoittautuneita on vähintään 20)` — teksti näkyy tapahtumasivun HINTA-rivillä "Maksuton"-tekstin sijaan (#464).
3. **Ilmoittautumisen lisävalinta** -laatikko:
   - Rastita **Lisää valintalista ilmoittautumislomakkeelle**.
   - **Kentän nimi**: `Lähtöpaikka`.
   - **Vaihtoehdot**: yksi lähtöpaikka riviä kohti (esim. `Iisalmi` / `Lapinlahti` / `Kuopio`). Lisää tarvittaessa viimeiseksi riviksi esim. `Muu paikka reitin varrella (kerro lisätiedoissa)` — vastaaja täydentää tarkemman paikan Lisätieto-kenttään.
   - Rastita **Kysy määrä** ja anna määräkentän nimeksi `Matkustajien määrä`.
4. **Tapahtumapäivä**-laatikko: halutessasi aseta **Maksuttoman ilmoittautumisen määräpäivä** — lomake sulkeutuu sen jälkeen (tyhjänä lomake sulkeutuu tapahtumapäivän jälkeen).
5. Julkaise tapahtuma. Tapahtumasivulle ilmestyy bussikyydin ilmoittautumislomake.

> **Yleiskäyttöinen:** sama "Ilmoittautumisen lisävalinta" -laatikko toimii missä tahansa tapahtumassa. Voit nimetä kentän vapaasti (esim. "Kuljetustapa", "Ryhmä") ja määräkenttä on oma valintansa — ota käyttöön vain tarvittavat. Bussikyyti on vain yksi käyttötapa.

> **Älä** liitä tapahtumaan maksutuotetta (Maksutuote-laatikko). Jos tapahtumaan on linkitetty WooCommerce-tuote, maksuton ilmoittautumislomake ei näy.

## Vaihe 2 — Ilmoittautuminen (kävijä)

Tapahtumasivun lomakkeella kysytään:

- **Nimi** ja **sähköposti** (pakollisia)
- **Lähtöpaikka** (pudotusvalikko tapahtuman lähtöpaikoista, pakollinen). Bussikyydissä valikossa on myös vaihtoehto **Muu paikka reitin varrella** — jos kävijä valitsee sen, hän kirjoittaa tarkemman paikan Lisätieto-kenttään. Osallistujat-listassa ja CSV:ssä nämä näkyvät lähtöpaikkana "Muu paikka reitin varrella", ja tarkka paikka löytyy lisätiedoista
- **Matkustajien määrä** (oletus 1; perhe voi varata useamman paikan yhdellä lomakkeella)
- tietosuojasuostumus

Lomake kertoo selvästi, että ilmoittautuminen on tässä vaiheessa **maksuton** ja maksu peritään vasta kun kyyti varmistuu. Ilmoittautunut saa kuittaussähköpostin, jossa näkyy myös lähtöpaikka ja matkustajamäärä.

Tekninen huomio: matkustajamäärän yläraja on oletuksena 10 (suodatin `rytkoset_theme_event_registration_max_quantity`).

## Vaihe 3 — Lähtijöiden seuranta (ylläpito)

**Tapahtumat → Osallistujat**, valitse bussikyytitapahtuma:

- Lista näyttää bussikyytitapahtumalle omat sarakkeet **Lähtöpaikka** ja **Matkustajia**.
- **Bussikyydin yhteenveto** -laatikko näyttää matkustajat yhteensä (peruutetut pois lukien) sekä lähtöpaikkajakauman matkustajamäärinä.
- **Vie CSV** sisältää bussikyytitapahtumalle lähtöpaikka- ja matkustajamääräsarakkeet.

Vähimmäismäärän (esim. 20) toteutuminen todetaan **käsin** listalta — järjestelmä ei estä ilmoittautumista eikä laukaise automatiikkaa.

## Vaihe 4 — Vahvistus ja maksu (ylläpito, operatiivinen)

Kun lähtijöitä on riittävästi ja matka toteutuu:

1. **Ilmoita lähtijöille.** Käytä **Tapahtumat → Viestintä** -toimintoa ja lähetä bussikyytiläisille viesti, että matka toteutuu ja maksulinkki tulee erikseen.
2. **Peri maksu WooCommercella.** Luo kullekin ilmoittautuneelle (tai perheelle matkustajamäärän mukaan) WooCommerce-tilaus:
   - **WooCommerce → Tilaukset → Lisää tilaus.**
   - Lisää asiakas ja **bussipaikka-tuote** rivituotteena (matkustajamäärä = kappalemäärä), jätä tila **Odottaa maksua**.
   - Lähetä asiakkaalle tilauksen maksulinkki (*Lähetä tilaustiedot asiakkaalle* / Customer invoice). Maksu hoituu Molliella tavalliseen tapaan.
3. Jos matka **ei** toteudu, ilmoita lähtijöille eikä maksuja peritä (yhtään tilausta ei ole vielä luotu).

> Tätä varten kannattaa pitää yksi yksinkertainen **bussipaikka-tuote** WooCommercessa (yksi hinta, ei variaatioita — lähtöpaikka tulee ilmoittautumisesta). Tuote voidaan **piilottaa kaupasta** (Catalog visibility), koska sitä ei myydä suoraan vaan käytetään vain manuaalisten tilausten rivituotteena.

## Vanha maksullinen tuote

Vanha maksullinen variaatiotuote (SKU `tampere-2026-bussikyyti`, `inc/woocommerce-bus-transport.php`, tiketti `#442`) on tällä mallilla **korvattu**:

- Piilota vanha tuote kaupasta (Catalog visibility = *Piilotettu*) tai poista se, jos siitä ei ole tehty tilauksia.
- Voit säilyttää yksinkertaisen bussipaikka-tuotteen vaiheen 4 manuaalisia tilauksia varten.
- Koodimoduuli `inc/woocommerce-bus-transport.php` (määräpäivä-/kapasiteettiportti SKU:n perusteella) jää toistaiseksi paikalleen, mutta se ei ole enää ilmoittautumisen reitti. Sen voi poistaa erikseen, kun vanhasta tuotteesta on luovuttu.

## Tekninen toteutus

| Osa | Sijainti |
| --- | --- |
| Yleinen valintalista + määräkenttä + ruokavaliovalinta tapahtumalle ("Ilmoittautumisen lisävalinta" -laatikko; metat `_rytkoset_event_choice_enabled`, `_rytkoset_event_choice_options`, `_rytkoset_event_choice_field_label`, `_rytkoset_event_collect_quantity`/`_rytkoset_event_quantity_field_label`, `_rytkoset_event_collect_diet`), getterit ja valinnan resolveri. Meta-avaimet ja funktioiden nimet ovat geneerisiä (ei bussikohtaisia) | `inc/events.php` |
| Lomakkeen valinta + määräkenttä, validointi ja tallennus (metat `_rytkoset_registration_choice`, `_rytkoset_registration_quantity`), kuittaussähköposti tapahtuman nimillä | `inc/event-registrations.php` |
| Osallistujat-näkymän sarakkeet + yhteenveto + CSV-sarakkeet (tapahtuman nimillä) | `inc/event-participants-admin.php` |
| Lähtöpaikka + matkustajamäärä GDPR-vientiin (anonymisointi säilyttää ne — eivät henkilötietoja) | `inc/event-registration-privacy.php` |

## Jätetään tietoisesti pois

- Automaattinen vähimmäismäärän/perumisen logiikka (todetaan ja hoidetaan käsin).
- Maksun integrointi suoraan ilmoittautumiseen (peritään erikseen vasta varmistuksen jälkeen).
- Bussipaikka-tuotteen ja tapahtuman tekninen linkitys (maksu hoidetaan manuaalisilla tilauksilla).
