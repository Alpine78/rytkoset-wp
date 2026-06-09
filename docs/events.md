# Tapahtumat

Tämä dokumentti kokoaa tapahtumakokonaisuuden nykyisen toteutuksen, käyttöönoton ja ylläpidon toimintamallin.

## Nykyinen rajaus

Tapahtumakokonaisuus on tässä vaiheessa kevyt MVP:

- tapahtumat ovat WordPressin oma `event`-sisältötyyppi
- tapahtuman perustiedot tallennetaan post metaan
- tapahtuman julkinen sisältö kirjoitetaan WordPress-editorissa
- ilmaisten tapahtumien ilmoittautumisille on oma ei-julkinen `event_registration`-sisältötyyppi
- maksuttomien tapahtumien sivulla voidaan näyttää ilmoittautumislomake, jonka tiedot tallentuvat ilmoittautumisiksi
- maksuttoman tapahtumailmoittautumisen jälkeen ilmoittautujalle lähetetään kevyt kuittisähköposti
- maksullisen tapahtuman ilmoittautuminen ja maksaminen ohjataan WooCommerce-tuotteelle
- osallistujat näkee tapahtumakohtaisesti `Tapahtumat > Osallistujat` -näkymästä, joka yhdistää ilmaiset ja maksulliset ilmoittautumiset
- Tampere 2026 -osallistujien hallintaan on lisäksi oma WooCommerce-pikalinkkinäkymä

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

| Kenttä ylläpidossa | Meta-avain                   | Muoto / arvot                        | Käyttö                                    |
| ------------------ | ---------------------------- | ------------------------------------ | ----------------------------------------- |
| Tapahtumapäivä     | `_rytkoset_event_date`       | `YYYY-MM-DD`, esim. `2026-08-29`     | Arkiston järjestys ja julkinen päivämäärä |
| Alkamisaika        | `_rytkoset_event_start_time` | `HH:MM`, esim. `11:30`               | Julkinen tapahtumatieto                   |
| Päättymisaika      | `_rytkoset_event_end_time`   | `HH:MM`, esim. `18:00`               | Julkinen tapahtumatieto, valinnainen      |
| Paikka             | `_rytkoset_event_location`   | vapaa teksti                         | Julkinen tapahtumatieto                   |
| Maksullisuus       | `_rytkoset_event_fee_type`   | `free`, `paid` tai tyhjä             | Julkinen hintatieto                       |
| Hintateksti        | `_rytkoset_event_price_text` | vapaa teksti, esim. `49 € / henkilö` | Julkinen hintatieto                       |
| Ilmoittautumisen määräpäivä | `_rytkoset_event_registration_deadline` | `YYYY-MM-DD` | Maksuttoman tapahtuman lomakkeen sulkeminen |
| Maksutuote         | `_rytkoset_event_product_id` | WooCommerce-tuotteen ID              | Linkki ilmoittautumis-/maksutuotteeseen   |
| Järjestäjäilmoitusten vastaanottajat | `_rytkoset_event_organizer_notification_recipients` | sähköpostiosoitteet, yksi per rivi | Maksullisen tapahtuman tilausilmoitusten vastaanottajat |

Tallennuksessa tarkistetaan nonce, käyttäjän `edit_post`-oikeus ja kenttäkohtaiset muodot. Tyhjä kenttä poistaa vastaavan metatiedon.

### Ilmoittautumisten metakentät

Ilmoittautumisen tiedot tallennetaan WordPressin post metaan:

| Kenttä ylläpidossa           | Meta-avain                              | Muoto / arvot                       | Käyttö                                                        |
| ---------------------------- | --------------------------------------- | ----------------------------------- | ------------------------------------------------------------- |
| Tapahtuma                    | `_rytkoset_registration_event_id`       | `event`-postauksen ID               | Viittaus tapahtumaan                                          |
| Osallistujan nimi            | `_rytkoset_registration_name`           | vapaa teksti                        | Osallistujalista ja admin-otsikko                             |
| Sähköposti                   | `_rytkoset_registration_email`          | sähköpostiosoite                    | Yhteydenpito ja myöhempi vahvistus                            |
| Ruokarajoitteet ja allergiat | `_rytkoset_registration_diet`           | vapaa teksti                        | Käytännön järjestelyt                                         |
| Lisätieto                    | `_rytkoset_registration_notes`          | vapaa teksti                        | Ylläpidon lisätiedot                                          |
| Tila                         | `_rytkoset_registration_status`         | `pending`, `confirmed`, `cancelled` | Ilmoittautumisen käsittelytila                                |
| GDPR-hyväksyntä              | `_rytkoset_registration_gdpr_consent`   | Unix-aikaleima                      | Tallennetaan, kun käyttäjä hyväksyy tietosuojakäytännön (#38) |
| Anonymisointiaika            | `_rytkoset_registration_anonymized_at`  | MySQL-aikaleima                     | Tallennetaan, kun henkilötiedot anonymisoidaan (#250)         |

Ilmoittautumisen otsikko muodostetaan automaattisesti muodossa `Osallistujan nimi - Tapahtuman nimi`, jotta admin-lista pysyy luettavana.

### Julkinen näkyminen

Yksittäisellä tapahtumasivulla näytetään:

- tapahtuman artikkelikuva ja otsikko
- editoriin kirjoitettu sisältö
- maksuttoman tapahtuman ilmoittautumislomake, jos tapahtuma on merkitty maksuttomaksi, siihen ei ole linkitetty maksutuotetta ja ilmoittautumisen määräpäivää ei ole ohitettu — lomake sisältää GDPR-tietosuojatekstin ja pakollisen hyväksyntächeckboxin (#38); onnistumisen jälkeen lomake korvataan vahvistusosiolla, joka näyttää tapahtuman tiedot (#32), ja ilmoittautujalle lähetetään tekstimuotoinen kuittisähköposti (#107)
- sivupalkin yhteenvetokortti, jos tapahtumalla on perustietoja tai maksutuote
- jakopainikkeet

Yhteenvetokortissa näytetään täytetyt perustiedot:

- päivämäärä
- kellonaika tai aikaväli
- paikka
- hinta
- ilmoittautumisen määräpäivä, jos sellainen voidaan päätellä tapahtumalta tai linkitetyltä tuotteelta ja tapahtumapäivä ei ole vielä mennyt; määräpäivän jälkeen mutta ennen tapahtumaa tekstinä on `Ilmoittautuminen päättyi`
- `Ilmoittaudu ja maksa` -painike, jos tapahtumaan on linkitetty maksutuote

Jos linkitetyn WooCommerce-tuotteen ilmoittautuminen on päättynyt tai tuote ei muuten ole ostettavissa, tapahtumasivu näyttää tilaviestinä syyn eikä tarjoa aktiivista maksupainiketta.

**Roskapostisuoja (maksuton lomake):** lomakkeessa on piilotettu honeypot-kenttä ja kevyt IP-kohtainen lähetysrajoitus (oletus 5 lähetystä / 10 min samasta IP-osoitteesta). Rajan ylittävä lähetys hylätään ennen tallennusta ja kuittisähköpostia, jottei lomakkeen toistolla voi synnyttää rajatonta määrää kuittiviestejä tai ilmoittautumistietueita. Tavallinen yksittäinen ilmoittautuminen ei osu rajaan. Rajan ja aikaikkunan voi säätää suodattimilla `rytkoset_theme_event_registration_rate_limit` ja `rytkoset_theme_event_registration_rate_limit_window`. Jos järjestäjä testaa lomaketta toistuvasti samasta verkosta ja saa viestin "Liian monta ilmoittautumisyritystä", kyse on tästä rajasta — odota aikaikkunan verran. (Käänteisen proxyn takana raja kohdistuu proxyn IP:hen; ks. `docs/tietoturva.md`.)

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
8. Jos maksuton tapahtuma käyttää lomakeilmoittautumista, täytä sivupalkin `Tapahtumapäivä`-laatikosta `Maksuttoman ilmoittautumisen määräpäivä`.
9. Jos tapahtumaan liittyy ilmoittautuminen tai maksu, valitse sivupalkin `Maksutuote`-laatikosta oikea WooCommerce-tuote.
10. Julkaise tai päivitä tapahtuma.
11. Tarkista julkinen tapahtumasivu ja tapahtuma-arkisto.

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

Julkinen ilmoittautumislomake näkyy maksuttomissa tapahtumissa, jos tapahtumaan ei ole linkitetty WooCommerce-maksutuotetta ja ilmoittautumisen määräpäivä ei ole ohitettu. Jos määräpäivä on tyhjä, lomake sulkeutuu tapahtumapäivän jälkeen. Lomake tarkistaa honeypot-kentän, noncen, tapahtuman, nimen, sähköpostiosoitteen ja GDPR-hyväksynnän ennen tallennusta. Sama sähköpostiosoite voi luoda vain yhden aktiivisen (`pending` tai `confirmed`) ilmoittautumisen samaan tapahtumaan; `cancelled`-tilainen ilmoittautuminen sallii uuden ilmoittautumisen. Uudet ilmoittautumiset tallentuvat aluksi tilaan `pending`, jotta ylläpitäjä voi käsitellä ne adminissa. Onnistuneen maksuttoman ilmoittautumisen jälkeen ilmoittautujalle lähetetään `wp_mail()`-pohjainen tekstimuotoinen kuittisähköposti, jossa kerrotaan ilmoittautumisen vastaanotosta ja näytetään tapahtuman perustiedot.

Maksuttomat `event_registration`-ilmoittautumiset ovat mukana WordPressin Privacy Tools -viennissä ja poistopyynnössä sähköpostiosoitteen perusteella. Poistopyyntö anonymisoi ilmoittautumisen: nimi korvataan arvolla `Anonymisoitu osallistuja`, sähköposti, ruokarajoitteet ja lisätiedot poistetaan, mutta tapahtumaviittaus ja status säilytetään raportointia varten. Yksittäisen tapahtuman maksuttomat ilmoittautumiset voi anonymisoida myös adminissa kohdassa `Tapahtumat > Osallistujat`, kun tapahtuma on valittuna.

Ilmoittautumiset kulkevat WooCommercen kautta silloin, kun tapahtumaan on linkitetty maksutuote:

1. ylläpitäjä luo WooCommerce-tuotteen
2. ylläpitäjä linkittää tuotteen tapahtumaan `Maksutuote`-kentällä
3. tapahtumasivulle tulee `Ilmoittaudu ja maksa` -painike
4. käyttäjä siirtyy WooCommerce-tuotesivulle ja ostaa tuotteen
5. ilmoittautumistiedot tallentuvat WooCommerce-tilaukselle

Maksullisen tapahtuman ilmoittautumisen määräpäivä luetaan linkitetyltä WooCommerce-tuotteelta, kun tuotteella on tapahtumailmoittautumisen oma deadline-logiikka. Tapahtumaan ei tallenneta samaa deadlinea erikseen, jotta tuotteen ostettavuus ja tapahtumasivun viesti eivät eriydy.

Maksullisen tapahtuman järjestäjäilmoitukset lähetetään tapahtumalle asetetuille vastaanottajille, kun linkitetyn maksutuotteen tilaus saavuttaa tilan `on-hold`, `processing` tai `completed`. Ilmoitus lähetetään vain kerran per tapahtuma per tilaus. Jos vastaanottajia ei ole asetettu, sähköpostia ei lähetetä ja tilaukselle kirjataan private order note.

Tapahtuman ja WooCommerce-tuotteen välinen linkitys on dokumentoitu tarkemmin tiedostossa `docs/woocommerce-event-product-link.md`.

### Event Organizer -rooli

Tapahtumien käytännön hallintaa varten sivustolla on rajattu `Event Organizer` -rooli.

Rooli saa:

- luoda, muokata, julkaista ja poistaa tapahtumia
- hallita kaikkia ilmaisten tapahtumien ilmoittautumisia kohdassa `Tapahtumat > Ilmoittautumiset`
- muuttaa ilmoittautumisen tilaa, esimerkiksi `pending`, `confirmed` tai `cancelled`
- lisätä tapahtuman artikkelikuvan mediakirjastosta
- linkittää tapahtumaan olemassa olevan WooCommerce-maksutuotteen
- määrittää tapahtumakohtaiset järjestäjäilmoitusten vastaanottajat

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
- maksullisten tapahtumien järjestäjäilmoitukset: `docs/woocommerce-tampere-2026-notifications.md`

Tampere 2026 -osallistujat näkyvät yhteisessä osallistujalistassa (katso alla). Vanha `WooCommerce > Tampere 2026 osallistujat` -pikalinkkisivu poistettiin tiketissä `#194`, kun sama tieto on saatavilla rajatuilla oikeuksilla yhteisestä näkymästä.

### Yleinen osallistujanäkymä

Kaikkien tapahtumien osallistujat näkee yhdistettynä näkymässä:

- `Tapahtumat > Osallistujat`

Näkymässä voi valita yksittäisen tapahtuman tai katsella kaikkien tapahtumien osallistujia kerralla. Näkymä yhdistää ilmaisten tapahtumien lomakeilmoittautumiset ja maksullisten tapahtumien WooCommerce-tilaukset (mukaan lukien Tampere 2026 -tilausten osallistujat). Suodatus statuksen mukaan on tuettu, ja näkymästä on CSV-vienti samoilla suodattimilla.

Tarkempi kuvaus on tiedostossa `docs/event-participants-admin.md`. Saman valikon alta löytyy myös `Tapahtumat > Viestintä`, jolla voi lisätä sähköpostiviestin valitun tapahtuman osallistujille WP-Cron-lähetysjonoon (`docs/event-participants-messaging.md`). Jono lähettää enintään 18 `wp_mail()`-yritystä rullaavan 60 minuutin aikana.

AcyMailingia ei käytetä #107:n kuittisähköposteihin eikä nykyiseen tapahtumaviestintään. #264:n ratkaisuna tapahtumaviestintä pysyy WordPressin `Tapahtumat > Viestintä` -näkymässä ja lähetysnopeus hallitaan kevyellä cron-jonolla.

### Mitä ylläpitäjä tekee ilmoittautumisille

1. Avaa `Tapahtumat > Osallistujat`.
2. Valitse tapahtuma dropdownista tai katso kaikki tapahtumat kerralla.
3. Suodata tarvittaessa statuksen mukaan.
4. Avaa osallistuja muokkauslinkistä, jos tietoja pitää tarkistaa tai statusta pitää muuttaa.

Jos tilaus perutaan tai hyvitetään, tarkista WooCommerce-tuotteen varastosaldo. Kapasiteetti perustuu WooCommercen varastoon, ei tapahtuman omaan laskuriin.

## Käyttöönoton muistilista

Kun uusi maksullinen tapahtuma otetaan käyttöön:

1. Luo tai tarkista tapahtuman WordPress-sivu kohdassa `Tapahtumat`.
2. Luo WooCommerce-tuote, jos ilmoittautuminen tai maksu tarvitaan.
3. Aseta tuotteelle hinta, varasto ja muut myyntiasetukset.
4. Linkitä tuote tapahtuman `Maksutuote`-kentässä.
5. Lisää tapahtuman `Järjestäjäilmoitukset`-kenttään vastaanottajat.
6. Testaa julkiselta tapahtumasivulta, että painike vie oikealle tuotteelle.
7. Testaa ostoskori ja kassa.
8. Tarkista, että ilmoittautumistiedot näkyvät WooCommerce-tilauksella ja mahdollisessa tapahtumakohtaisessa osallistujanäkymässä.
9. Tarkista, että tilaukselle kirjautuu järjestäjäilmoituksen private order note.

## Mitä on tehty

Tässä vaiheessa on toteutettu:

- `event`-sisältötyyppi
- `event_registration`-sisältötyyppi ilmaisten tapahtumien osallistujille
- maksuttoman tapahtuman julkinen ilmoittautumislomake
- maksuttoman tapahtuman ilmoittautumisen validointi ja frontend-tallennus
- maksuttoman tapahtumailmoittautumisen kuittisähköposti ilmoittautujalle
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
- maksullisten tapahtumien tapahtumakohtaiset järjestäjäilmoitukset
- rajattu `Event Organizer` -rooli tapahtumien ja ilmoittautumisten hallintaan
- yhdistetty `Tapahtumat > Osallistujat` -näkymä ilmaisten ja maksullisten tapahtumien osallistujille

## Jätetään myöhempään vaiheeseen

Tässä vaiheessa ei toteuteta:

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

## Saavutettavuus

Tapahtumaosio on testattu WCAG 2.1 AA -vaatimuksia vasten tiketissä #75. Seuraavat asiat on tarkistettu ja korjattu:

- Otsikkohierarkia (h1→h2, ei hyppyjä) ✓
- Section-alueet aria-labelledby-tunnisteilla ✓
- Kuvien alt-tekstit ✓
- Lomakekenttien eksplisiittiset label/for-parit ✓
- GDPR-checkboxin eksplisiittinen id/for-assosiaatio ja aria-required ✓
- Checkbox-elementin `:focus-visible`-tyyli ✓
- Tekstin muted-väri eksplisiittisenä muuttujana (`--color-text-mute`) opacity-hämärryksen sijaan ✓
- Redirect-URL sisältää fragmenttiankurin (`#element-id`) — selain skrollaa automaattisesti lomakkeelle tai vahvistusosioon ✓
- `prefers-reduced-motion` -media query koko teemalle ✓

Lomakkeen palvelinpuolen virheviestit ovat yleisiä ilmoituksia lomakkeen yläpuolella (`role="alert"`). HTML5 native validation hoitaa kenttäkohtaiset virheet ennen lähetystä.
