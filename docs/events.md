# Tapahtumat

Tämä dokumentti kokoaa tapahtumakokonaisuuden nykyisen toteutuksen, käyttöönoton ja ylläpidon toimintamallin.

## Nykyinen rajaus

Tapahtumakokonaisuus on tässä vaiheessa kevyt MVP:

- tapahtumat ovat WordPressin oma `event`-sisältötyyppi
- tapahtuman perustiedot tallennetaan post metaan
- tapahtuman julkinen sisältö kirjoitetaan WordPress-editorissa
- ilmaisten tapahtumien ilmoittautumisille on oma ei-julkinen `event_registration`-sisältötyyppi
- maksuttomien tapahtumien sivulla voidaan näyttää ilmoittautumislomake, jonka tiedot tallentuvat ilmoittautumisiksi
- maksullisen tapahtuman ilmoittautuminen ja maksaminen ohjataan WooCommerce-tuotteelle
- Tampere 2026 -osallistujien hallinta tehdään WooCommerce-tilausten ja erillisen osallistujalista-adminin kautta

Tapahtuma ei siis vielä ole erillinen täysi ilmoittautumisjärjestelmä. WordPress-tapahtuma kertoo tapahtumasta. Ilmaisten tapahtumien oma ilmoittautumisrakenne, lomakkeen käyttöliittymä sekä perustason validointi ja tallennus ovat valmiina. WooCommerce hoitaa ostamisen sekä ilmoittautumistiedot silloin, kun tapahtumaan on linkitetty maksutuote.

## Tekninen perusrakenne

Tapahtumat rekisteröidään teemassa tiedostossa `wp-content/themes/rytkoset-theme/inc/events.php`.

### Sisältötyyppi

- Post type: `event`
- Julkinen arkisto: `/tapahtumat/`
- URL-rakenne: `/tapahtumat/{tapahtuman-polku}/`
- REST-tuki on käytössä, jotta tapahtumia voi muokata lohkoeditorilla.
- Tuetut WordPress-ominaisuudet:
  - otsikko
  - sisältö
  - ote
  - artikkelikuva
  - custom fields

Tapahtumien yksittäinen näkymä tulee tiedostosta `single-event.php` ja arkisto tiedostosta `archive-event.php`.

### Ilmoittautumisten sisältötyyppi

Ilmaisten tapahtumien ilmoittautumisia varten teemassa on ei-julkinen sisältötyyppi:

- Post type: `event_registration`
- Admin-nimi: `Ilmoittautumiset`
- Näkyy WordPress-adminissa `Tapahtumat`-valikon alla
- Ei julkista arkistoa, yksittäissivua, hakunäkyvyyttä tai REST-näkymää

Yksi `event_registration` vastaa yhtä osallistujaa. Tämä pitää osallistujalistat ja myöhemmän CSV-viennin suoraviivaisina.

### Metakentät

Tapahtuman lisätiedot tallennetaan WordPressin post metaan:

| Kenttä ylläpidossa | Meta-avain | Muoto / arvot | Käyttö |
| --- | --- | --- | --- |
| Tapahtumapäivä | `_rytkoset_event_date` | `YYYY-MM-DD`, esim. `2026-08-29` | Arkiston järjestys ja julkinen päivämäärä |
| Alkamisaika | `_rytkoset_event_start_time` | `HH:MM`, esim. `11:30` | Julkinen tapahtumatieto |
| Päättymisaika | `_rytkoset_event_end_time` | `HH:MM`, esim. `18:00` | Julkinen tapahtumatieto, valinnainen |
| Paikka | `_rytkoset_event_location` | vapaa teksti | Julkinen tapahtumatieto |
| Maksullisuus | `_rytkoset_event_fee_type` | `free`, `paid` tai tyhjä | Julkinen hintatieto |
| Hintateksti | `_rytkoset_event_price_text` | vapaa teksti, esim. `49 € / henkilö` | Julkinen hintatieto |
| Maksutuote | `_rytkoset_event_product_id` | WooCommerce-tuotteen ID | Linkki ilmoittautumis-/maksutuotteeseen |

Tallennuksessa tarkistetaan nonce, käyttäjän `edit_post`-oikeus ja kenttäkohtaiset muodot. Tyhjä kenttä poistaa vastaavan metatiedon.

### Ilmoittautumisten metakentät

Ilmoittautumisen tiedot tallennetaan WordPressin post metaan:

| Kenttä ylläpidossa | Meta-avain | Muoto / arvot | Käyttö |
| --- | --- | --- | --- |
| Tapahtuma | `_rytkoset_registration_event_id` | `event`-postauksen ID | Viittaus tapahtumaan |
| Osallistujan nimi | `_rytkoset_registration_name` | vapaa teksti | Osallistujalista ja admin-otsikko |
| Sähköposti | `_rytkoset_registration_email` | sähköpostiosoite | Yhteydenpito ja myöhempi vahvistus |
| Ruokarajoitteet ja allergiat | `_rytkoset_registration_diet` | vapaa teksti | Käytännön järjestelyt |
| Lisätieto | `_rytkoset_registration_notes` | vapaa teksti | Ylläpidon lisätiedot |
| Tila | `_rytkoset_registration_status` | `pending`, `confirmed`, `cancelled` | Ilmoittautumisen käsittelytila |

Ilmoittautumisen otsikko muodostetaan automaattisesti muodossa `Osallistujan nimi - Tapahtuman nimi`, jotta admin-lista pysyy luettavana.

### Julkinen näkyminen

Yksittäisellä tapahtumasivulla näytetään:

- tapahtuman artikkelikuva ja otsikko
- editoriin kirjoitettu sisältö
- maksuttoman tapahtuman ilmoittautumislomake, jos tapahtuma on merkitty maksuttomaksi eikä siihen ole linkitetty maksutuotetta
- sivupalkin yhteenvetokortti, jos tapahtumalla on perustietoja tai maksutuote
- jakopainikkeet

Yhteenvetokortissa näytetään täytetyt perustiedot:

- päivämäärä
- kellonaika tai aikaväli
- paikka
- hinta
- `Ilmoittaudu ja maksa` -painike, jos tapahtumaan on linkitetty maksutuote

Tapahtuma-arkistossa `/tapahtumat/` tapahtumat jaetaan kolmeen osioon:

1. Tulevat tapahtumat
2. Menneet tapahtumat
3. Päivämäärättömät tapahtumat

Tulevat tapahtumat näytetään lähimmästä tulevasta tapahtumasta alkaen. Menneet tapahtumat näytetään uusimmasta vanhimpaan. Päivämäärätön tapahtuma jää näkyviin, mutta se siirtyy päivämäärättömien tapahtumien osioon.

## Uuden tapahtuman luominen ylläpidossa

1. Avaa WordPress-adminissa `Tapahtumat`.
2. Valitse `Lisää uusi`.
3. Kirjoita tapahtuman otsikko.
4. Kirjoita varsinainen tapahtumakuvaus editoriin.
5. Lisää tarvittaessa ote ja artikkelikuva.
6. Täytä sivupalkin `Tapahtumapäivä`-kenttä.
7. Täytä sivupalkin `Tapahtuman tiedot` -laatikosta tarvittavat kentät:
   - alkamisaika
   - päättymisaika
   - paikka
   - maksullisuus
   - hintateksti
8. Jos tapahtumaan liittyy ilmoittautuminen tai maksu, valitse sivupalkin `Maksutuote`-laatikosta oikea WooCommerce-tuote.
9. Julkaise tai päivitä tapahtuma.
10. Tarkista julkinen tapahtumasivu ja tapahtuma-arkisto.

### Suositeltu minimitieto

Jokaiselle tapahtumalle kannattaa täyttää vähintään:

- otsikko
- kuvaus
- tapahtumapäivä
- paikka, jos tiedossa

Maksulliselle tapahtumalle kannattaa lisäksi täyttää:

- maksullisuus: `Maksullinen`
- hintateksti, esimerkiksi `49 € / henkilö`
- maksutuote, jos ilmoittautuminen tehdään WooCommercen kautta

## Ilmoittautumisten hallinta

### Yleinen malli

Ilmaisten tapahtumien ilmoittautumiset tallennetaan `event_registration`-sisältötyyppiin. Ylläpitäjä voi luoda ja muokata ilmoittautumisia käsin WordPress-adminissa kohdassa `Tapahtumat > Ilmoittautumiset`.

Julkinen ilmoittautumislomake näkyy maksuttomissa tapahtumissa, jos tapahtumaan ei ole linkitetty WooCommerce-maksutuotetta. Lomake tarkistaa noncen, tapahtuman, nimen ja sähköpostiosoitteen ennen tallennusta. Uudet ilmoittautumiset tallentuvat aluksi tilaan `pending`, jotta ylläpitäjä voi käsitellä ne adminissa.

Ilmoittautumiset kulkevat WooCommercen kautta silloin, kun tapahtumaan on linkitetty maksutuote:

1. ylläpitäjä luo WooCommerce-tuotteen
2. ylläpitäjä linkittää tuotteen tapahtumaan `Maksutuote`-kentällä
3. tapahtumasivulle tulee `Ilmoittaudu ja maksa` -painike
4. käyttäjä siirtyy WooCommerce-tuotesivulle ja ostaa tuotteen
5. ilmoittautumistiedot tallentuvat WooCommerce-tilaukselle

Tapahtuman ja WooCommerce-tuotteen välinen linkitys on dokumentoitu tarkemmin tiedostossa `docs/woocommerce-event-product-link.md`.

### Event Organizer -rooli

Tapahtumien käytännön hallintaa varten sivustolla on rajattu `Event Organizer` -rooli.

Rooli saa:

- luoda, muokata, julkaista ja poistaa tapahtumia
- hallita kaikkia ilmaisten tapahtumien ilmoittautumisia kohdassa `Tapahtumat > Ilmoittautumiset`
- muuttaa ilmoittautumisen tilaa, esimerkiksi `pending`, `confirmed` tai `cancelled`
- lisätä tapahtuman artikkelikuvan mediakirjastosta
- linkittää tapahtumaan olemassa olevan WooCommerce-maksutuotteen

Rooli ei saa:

- hallita WooCommerce-tuotteita, tilauksia, maksutapoja tai asetuksia
- avata WooCommerce-hallintanäkymiä
- muuttaa sivuston yleisiä asetuksia, teeman asetuksia tai käyttäjärooleja

Tämä rooli on tarkoitettu tapahtumien järjestäjille, joille ei haluta antaa täysiä ylläpitäjän oikeuksia. Maksutuotteet luo ja ylläpitää edelleen varsinainen ylläpitäjä.

### Tampere 2026

Tampere 2026 -tapahtuman ilmoittautuminen on toteutettu WooCommercen päälle erillisinä MVP-osina:

- osallistumismaksutuote: `docs/woocommerce-tampere-2026-product.md`
- checkoutin osallistujakentät: `docs/woocommerce-tampere-2026-checkout-fields.md`
- määräpäivä ja kapasiteetti: `docs/woocommerce-tampere-2026-management.md`
- osallistujalista adminissa: `docs/woocommerce-tampere-2026-participants-admin.md`
- osallistujien CSV-vienti: `docs/woocommerce-tampere-2026-participants-csv-export.md`
- järjestäjäilmoitukset: `docs/woocommerce-tampere-2026-notifications.md`

Ylläpidon kannalta tärkein näkymä on:

- `WooCommerce > Tampere 2026 osallistujat`

Siellä osallistujat näkyvät riveinä. Näkymä käyttää WooCommerce-tilauksille tallennettuja osallistujatietoja eikä luo erillistä tapahtumarekisteritaulua.

### Mitä ylläpitäjä tekee ilmoittautumisille

1. Avaa `WooCommerce > Tampere 2026 osallistujat`.
2. Tarkista osallistujat, yhteyshenkilöt ja tilauksen tila.
3. Avaa tarvittaessa tilaus linkistä, jos maksun tai asiakkaan tietoja pitää tarkistaa.
4. Suodata osallistujia tilauksen statuksen mukaan, jos haluat erottaa aktiiviset ja perutut ilmoittautumiset.
5. Vie osallistujat CSV-tiedostoon, jos osallistujalista tarvitaan taulukkolaskentaan.

Jos tilaus perutaan tai hyvitetään, tarkista WooCommerce-tuotteen varastosaldo. Kapasiteetti perustuu WooCommercen varastoon, ei tapahtuman omaan laskuriin.

## Käyttöönoton muistilista

Kun uusi maksullinen tapahtuma otetaan käyttöön:

1. Luo tai tarkista tapahtuman WordPress-sivu kohdassa `Tapahtumat`.
2. Luo WooCommerce-tuote, jos ilmoittautuminen tai maksu tarvitaan.
3. Aseta tuotteelle hinta, varasto ja muut myyntiasetukset.
4. Linkitä tuote tapahtuman `Maksutuote`-kentässä.
5. Testaa julkiselta tapahtumasivulta, että painike vie oikealle tuotteelle.
6. Testaa ostoskori ja kassa.
7. Tarkista, että ilmoittautumistiedot näkyvät WooCommerce-tilauksella ja mahdollisessa tapahtumakohtaisessa osallistujanäkymässä.

## Mitä on tehty

Tässä vaiheessa on toteutettu:

- `event`-sisältötyyppi
- `event_registration`-sisältötyyppi ilmaisten tapahtumien osallistujille
- maksuttoman tapahtuman julkinen ilmoittautumislomake
- maksuttoman tapahtuman ilmoittautumisen validointi ja frontend-tallennus
- tapahtuman yksittäinen sivupohja
- tapahtuma-arkisto, jossa on tulevat, menneet ja päivämäärättömät tapahtumat
- tapahtumapäivän metakenttä
- tapahtuman perustietojen metakentät
- tapahtuman maksutuotelinkitys yhteen WooCommerce-tuotteeseen
- julkinen `Ilmoittaudu ja maksa` -painike linkitetylle tuotteelle
- tapahtumalistan admin-sarake tapahtumapäivälle
- tapahtumapäivän mukaan järjestettävä admin-sarake
- Tampere 2026 -ilmoittautumisen WooCommerce-pohjainen MVP
- Tampere 2026 -osallistujalista adminissa
- Tampere 2026 -osallistujien CSV-vienti
- Tampere 2026 -järjestäjäilmoitukset
- rajattu `Event Organizer` -rooli tapahtumien ja ilmoittautumisten hallintaan

## Jätetään myöhempään vaiheeseen

Tässä vaiheessa ei toteuteta:

- yleistä osallistujaraporttia kaikille tapahtumille
- erillistä osallistujien tietokantataulua
- osallistujien massatoimintoja tapahtumanäkymästä
- usean maksutuotteen linkitystä samaan tapahtumaan
- automaattista ostoskoriin lisäämistä tapahtumasivulta
- suoraa kassalle ohjausta tapahtumasivulta
- tapahtumakohtaista kapasiteettilogiikkaa WordPress-tapahtumalle
- tapahtumakohtaisia lippuja tai QR-koodeja
- toistuvia tapahtumia
- erillistä päättymispäivää
- karttalinkkiä tai karttaupotusta
- numeerista hintamallia tapahtuman metakenttiin
- automaattista WooCommerce-tuotteen luontia tai muuttamista tapahtumasta

## Ensimmäiset tapahtumat

Nykyisille tapahtumille kannattaa asettaa vähintään nämä päivämäärät:

- Rytkösten sukuseuran Etelä-Suomen tapaaminen: `2025-10-07`
- Rytkösten sukukokous Tampereella: `2026-08-29`

Tampereen sukukokoukselle voidaan lisäksi asettaa:

- Alkamisaika: `11:30`
- Päättymisaika: `18:00`
- Paikka: `Hotelli Rosendahl, Pyynikintie 13, Tampere`
- Maksullisuus: `Maksullinen`
- Hintateksti: `49 € / henkilö`
