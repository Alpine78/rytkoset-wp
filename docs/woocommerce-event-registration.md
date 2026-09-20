# Maksullisen tapahtuman osallistujailmoittautuminen

Uuden maksullisen tapahtuman osallistujatiedot otetaan käyttöön tuotteen ja tapahtuman ylläpitoasetuksilla (#642). Ohje yhdistää aiemmat viisi Tampere 2026 -tuote-, kassa-, hallinta-, ilmoitus- ja bussikyytiopasta. Osallistujien sähköpostit kuuluvat erilliseen tikettiin #685.

## Käyttöönotto

1. Luo WooCommerceen tavallinen tuote tai variaatiotuote. Aseta nimi, hinta ja tarvittaessa osallistujatyypit variaatioiksi. Aseta tuote virtuaaliseksi, jos siihen ei liity toimitusta.
2. Valitse **Tuotetiedot → Varasto → Tapahtuman osallistujat**. Asetus on uusilla tuotteilla oletuksena pois päältä. Variaatiot käyttävät päätuotteen asetuksia.
3. Aseta **Ilmoittautumisen määräpäivä**. Tyhjä kenttä tarkoittaa uudelle tapahtumatuotteelle, ettei määräpäivää ole. Päivä on voimassa loppuun saakka sivuston aikavyöhykkeessä. Virheellinen päivämäärä näyttää virheen ja säilyttää aiemman arvon.
4. Aseta **Osallistujia enintään / tilaus** (1–10, oletus 10). Raja koskee päätuotteen kaikkien variaatioiden yhteistä määrää. Lisäksi yhden tilauksen kaikkien tapahtumatuotteiden yhteinen yläraja on 10. Raja tarkistetaan palvelimella myös Store API -kassalla.
5. Aseta tapahtuman paikkamäärä WooCommercen varastonhallinnalla ja estä jälkitoimitukset. Tilauskohtainen osallistujaraja ei korvaa varastosaldoa.
6. Kirjoita halutessasi **Osallistujan kyllä/ei-lisävalinta**, esimerkiksi `Osallistun yhteiselle illalliselle` (enintään 200 merkkiä). Tyhjä kenttä piilottaa valinnan. Valinta ei muuta hintaa. Älä käytä sitä terveystietojen keräämiseen: vastaus kuuluu tilausvahvistuksiin ja järjestäjäviestiin.
7. Luo tapahtuma kohdassa **Tapahtumat**, aseta päivämäärä ja maksullisuus sekä linkitä maksutuote. Katso [tuotelinkitysohje](woocommerce-event-product-link.md).

Tavallinen tuote ilman osallistujakytkintä ei saa osallistujakenttiä. Samassa ostoskorissa olevat muut tuotteet eivät lisää osallistujarivien määrää.

## Kassa ja ylläpito

Yksi tuotekappale vastaa yhtä osallistujaa. Kassalla kysytään jokaisen osallistujan nimi ja vapaaehtoiset ruokarajoitteet tai allergiat. Ostajan yhteystiedot tulevat WooCommercen tavallisista laskutuskentistä.

Kassan otsikko on **Tapahtuman osallistujat**. Jokaisella osallistujalla on numeroitu kortti, jonka otsakkeessa näkyvät tuotteen osallistujatyyppi tai nimi sekä yksikköhinta. Määrää muutetaan ostoskorissa. Nimikenttä on pakollinen, ruokarajoite ja mahdollinen lisävalinta vapaaehtoisia. Rasti tarkoittaa kyllä, tyhjä rasti ei. Kassakenttien automaattitäyttö on rajattu pois.

Lisävalinnan teksti tallennetaan tilausriville ostohetkellä. Tuotteen kysymyksen muuttaminen tai poistaminen ei muuta vanhan vastauksen merkitystä. Ennen lisävalinnan käyttöönottoa tehdylle tilaukselle ei muodosteta jälkikäteen ei-vastausta. Asiakkaan tilausnäkymässä ja WooCommercen HTML- ja tekstisähköposteissa erillinen **Osallistujien lisävalinnat** -yhteenveto näyttää nimen, kysymyksen ja vastauksen.

Osallistujalistan ja CSV:n **Maksullisen tapahtuman lisävalinta** -sarake näyttää esimerkiksi `Osallistun yhteiselle illalliselle: Kyllä`. Vanha buffet-tieto näkyy samassa sarakkeessa muodossa `Perjantain buffet: Kyllä/Ei`; ilman kysymystä solu jää tyhjäksi. CSV:n sarakkeen paikka säilyy, mutta otsikko ja solun sisältö muuttuvat: päivitä mahdolliset sarakkeen nimeen tai pelkkään kyllä/ei-arvoon perustuvat jatkokäsittelyt.

Osallistujat löytyvät tilauksen **Tapahtuman osallistujat** -laatikosta ja **Tapahtumat → Osallistujat** -näkymästä. Eri tapahtumien tuotteita sisältävässä tilauksessa kukin tapahtuma saa vain omat osallistujarivinsä. Sama rajaus koskee järjestäjäilmoitusta.

Osallistujakytkimen poistaminen tuotteelta lopettaa uusien osallistujatietojen keruun. Jo tallennetun uuden tilauksen osallistujat säilyvät luettavina, koska tilausriville tallennetaan ostohetken ilmoittautumistapa. Käytä määräpäivää tai tuotteen saatavuutta ilmoittautumisen sulkemiseen: kytkimen poistaminen ei sulje tavallisen tuotteen myyntiä.

Järjestäjäviestien vastaanottajat asetetaan tapahtumalle. Katso [järjestäjäilmoitukset](#järjestäjäilmoitukset). Osallistujien omia sähköpostiosoitteita ei tässä kerätä. WooCommerce-tietojen kohdennettu Privacy Tools -integraatio on avoimen #261:n tehtävä; maksuttomien ilmoittautumisten anonymisointi ei käsittele näitä tilauksia.

## Ilmoittautumisen sulkeminen ja jälkihoito

Määräpäivän jälkeen ostopolku ilmoittaa **Ilmoittautuminen on päättynyt.** Kapasiteetin täyttyessä viesti on **Ilmoittautuminen on täynnä.** Tarkistus koskee myös ostoskorissa jo olevaa tuotetta. Varasto hoidetaan WooCommercen normaalilla varastologiikalla; peruutuksen tai hyvityksen yhteydessä tarkista palautunut saldo ja korjaa tarvittaessa käsin. Erillistä palautusautomatiikkaa ei lisätä.

Ilmoittautumisen päätyttyä aseta kataloginäkyvyydeksi **Piilotettu**, pidä tuote julkaistuna ja linkitettynä tapahtumaan sekä päivitä tapahtuman kuvaus. Piilottaminen yksin ei sulje ostamista suoralla linkillä. Tapahtuma-arkiston avoimuusmerkki seuraa tuotteen ostettavuutta. Etusivun ajankohtaisnosto on datalähtöinen (#657).

## Yhteensopivuus ja tekniikka

- Moduuli: `inc/woocommerce-event-registration.php`; kassaskripti: `assets/js/event-checkout-participants.js`.
- Uuden tuotteen tila: `_rytkoset_registration_mode = event_participants`. Vanhat `tampere_2026`-tuotteet ja SKU `tampere-2026-osallistumismaksu` tunnistetaan edelleen.
- Tuotemetat `_rytkoset_registration_deadline`, `_rytkoset_registration_max_participants` ja `_rytkoset_registration_choice_label` siirtyvät tuotesynkronoinnissa.
- Kassakentät rekisteröidään ilman riippuvuutta vanhan tuotteen olemassaolosta. Näkyvyys ja pakollisuus perustuvat Store API:n `rytkoset_event_registration`-laajennukseen. `legacy_buffet_indices` rajaa vanhan buffet-kentän ja `choice_indices` uuden lisävalinnan oikeisiin osallistujariveihin myös sekakorissa. `participants` sisältää korttiotsakkeiden tyypin, hinnan ja kysymyksen (`choice_label`). JS-asetukset ovat `window.rytkosetEventParticipants`-oliossa. PHP ja JS julkaistaan yhdessä; ennen julkaisua avattu kassa ladataan uudelleen.
- Vanhat `rytkoset/participant_N_name|diet|friday_buffet`-tunnisteet ja `_wc_other/`-tilausmetat säilyvät. Uusi valinta tallentuu `rytkoset/participant_N_choice`-kenttään samassa osallistujarivimallissa (N = 1–10). Palvelin poistaa uusilta Store API -tilauksilta piilotettujen valintojen ja ylimääräisten osallistujien metat. Vanhat tilaukset eivät vaadi migraatiota. #261:n tietoinventaarion tulee kattaa myös uusi `choice` sekä tilausriville tallennettu kysymysteksti; automaattista export-/eraser-kattavuutta ei tässä lisätä.
- Uusien tilausten tuoteriveille tallennetaan `_rytkoset_registration_mode`, `_rytkoset_participant_type` ja `_rytkoset_registration_choice_label`. Vanhoille tilauksille käytetään edelleen tuotetunnistusta ilman migraatiota.
- Vanhan tuotteen määräpäivän varapolku `2026-07-30` koskee vain vanhaa tilaa. Tampereen historiallinen tuote on virtuaalinen variaatiotuote (aikuinen 49 €, lapsi 3–12 vuotta 24,50 €); lauantain 29.8.2026 osallistumismaksu ja paikan päällä maksettava perjantain buffet ovat erillisiä. Ilmoittautumisaikaa jatkettiin ylläpidon tuotemeta-arvolla 14.8.2026 asti. Näitä arvoja ei käytetä uusien tapahtumien oletuksina.

## Järjestäjäilmoitukset

Tapahtumaan linkitetyn maksutuotteen tilaus lähettää järjestäjille tapahtumakohtaisen tekstisähköpostin.

### Lähetyslogiikka

- Ilmoitus lähetetään WooCommerce-tilauksista, joissa on jonkin tapahtuman `Maksutuote`-kenttään linkitetty tuote tai sen variaatio.
- Ilmoitus lähetetään, kun tilaus saavuttaa ensimmäisen aktiivisen tilan: `on-hold`, `processing` tai `completed`.
- Ilmoitus lähetetään vain kerran per tapahtuma per tilaus.
- Lähetyksen deduplikointi tehdään order-metalla `_rytkoset_event_organizer_notification_sent_at_{event_id}`.
- Käytetty vastaanottajalista tallennetaan order-metana avaimeen `_rytkoset_event_organizer_notification_recipients_{event_id}`.
- Jos samassa tilauksessa on usean eri tapahtuman maksutuotteita, jokaiselle tapahtumalle lähetetään oma ilmoitus sen omille vastaanottajille.

### Vastaanottajat

- Vastaanottajat hallitaan tapahtuman muokkausnäkymässä metaboxissa `Järjestäjäilmoitukset`.
- Kentän nimi on `Järjestäjäilmoitusten vastaanottajat`.
- Tapahtuman meta-avain on `_rytkoset_event_organizer_notification_recipients`.
- Osoitteet voidaan syöttää pilkuilla tai rivinvaihdoilla eroteltuna.
- Tallennuksessa säilytetään vain kelvolliset uniikit sähköpostiosoitteet.
- Jos vastaanottajia ei ole asetettu, sähköpostia ei lähetetä ja tilaukselle lisätään private order note.

### Sähköpostin sisältö

Ilmoitusviesti sisältää:

- tapahtuman nimen
- tapahtuman päivämäärän
- tapahtuman paikan
- tilausnumeron
- tilauksen päivämäärän
- tilan
- maksutavan
- yhteyshenkilön nimen
- yhteyshenkilön sähköpostin
- yhteyshenkilön puhelinnumeron
- osallistujien nimet, osallistujatyypin sekä mahdollisen lisävalinnan kysymyksen ja vastauksen
- ilmoittautumisyhteenvedon (ks. alla)
- linkin tilaukseen adminissa, jos sellainen voidaan muodostaa
- linkin `Tapahtumat > Osallistujat` -näkymään

Osallistujakentät käyttöön ottaneiden tuotteiden osallistujat puretaan kassakentistä, myös vanhalla tuotteella. Ilman osallistujakenttiä käytetään tilaajan yhteystietoja ja tapahtumatuotteen ostettua määrää.

#### Tietominimointi (#643)

Viestistä on tarkoituksella jätetty pois tiedot, jotka ovat arkaluonteisia tai jotka toistuisivat turhaan:

- **ruokarajoitteet ja allergiat** — terveyteen liittyvää tietoa ei lähetetä sähköpostitse, vaan se luetaan tilausnäkymästä tai `Tapahtumat > Osallistujat` -sivulta
- **osallistujakohtaiset sähköpostit ja puhelinnumerot** — tilaajan yhteystiedot ovat jo viestissä kerran omassa lohkossaan
- **asiakkaan vapaa lisätietokenttä** — voi sisältää mitä tahansa, myös terveystietoa, joten se jätetään tilaukselle

Viestin lopussa on aina kaksi linkkiä, joista tarkat tiedot löytyvät: `tilaus:` ja `osallistujalista:`.

Sama minimointi koskee WooCommercen omaa **ylläpidon "Uusi tilaus" -sähköpostia**: `rytkoset_theme_hide_paid_event_diet_fields_from_admin_email()` piilottaa `rytkoset/participant_N_diet` -kentät, kun WooCommerce merkitsee viestin `sent_to_admin`-kontekstiin. Lisäksi tapahtumatilauksen vapaa tilauslisätieto (`customer_note`) piilotetaan ylläpidon HTML- ja tekstisähköpostien tilaustaulukosta. Tämä kenttä tulostuu WooCommercessa erillään osallistujakentistä, joten se suodatetaan erikseen vain viestin renderöinnin ajaksi. Rajaus koskee osallistujakenttiä käyttäviä tilauksia ja tapahtumiin linkitettyjä tilauksia; muiden tilausten lisätiedot näkyvät edelleen. Asiakkaan oma tilausvahvistus, kiitos-sivu ja tilausnäkymä säilyvät ennallaan.

#### Ilmoittautumisyhteenveto (#643)

Jokainen ilmoitus sisältää tilannekuvan, jotta järjestäjän ei tarvitse avata ylläpitoa nähdäkseen kokonaistilanteen:

```
Ilmoittautumistilanne:
Tämän ilmoittautumisen henkilömäärä: 2
Ilmoittautuneita yhteensä: 11
Paikkoja jäljellä: 18
Ilmoittautuminen päättyy: 14.8.2026 (4 päivää jäljellä)
```

- **Ilmoittautuneita yhteensä** laskee maksuttomat ilmoittautumiset ja maksullisten tilausten ostetut kappalemäärät yhteen. Perutut, hyvitetyt ja epäonnistuneet tilaukset sekä perutut maksuttomat ilmoittautumiset jätetään pois, samoin keskenjääneet checkout-luonnokset. Luku voi siksi olla pienempi kuin `Tapahtumat > Osallistujat` -listan rivimäärä, joka näyttää myös kuolleet tilaukset.
- **Paikkoja jäljellä** näytetään vain, jos linkitetyssä tuotteessa on varastonhallinta päällä.
- **Ilmoittautuminen päättyy** lukee saman määräpäivän kuin tapahtumasivu (maksullisilla tuotteelta, maksuttomilla tapahtuman omasta kentästä).
- Rivi jätetään pois, jos arvoa ei voida selvittää. Arvausta ei koskaan tulosteta.

Yhteenveto lasketaan moduulissa `inc/event-registration-summary.php`. Maksullinen laskenta **ei** käytä `rytkoset_theme_get_event_participants()`-funktiota, koska se lataisi kaikki tilaukset kesken tilauksen tilasiirtymän (#376). Tilalla on yksi indeksoitu kysely `woocommerce_order_items`-tauluihin (HPOS ja vanha tallennus tuettu). Paikallisesti mitattuna (n. 200 tilausta) kevyt laskenta vei 1,3 ms ja vanha osallistujahaku 53,4 ms.

Laskennan voi ohittaa suodattimella `rytkoset_theme_pre_event_paid_registration_count` (ei-null paluuarvo ohittaa kyselyn kokonaan).

### Audit trail

- Onnistuneesta lähetyksestä lisätään private order note.
- Epäonnistuneesta lähetyksestä lisätään private order note.
- Puuttuvista vastaanottajista lisätään private order note.
- Onnistuneen lähetyksen ajankohta ja käytetty vastaanottajalista tallennetaan tilauksen metaan tapahtumakohtaisesti.

### Maksuttomat tapahtumat

Sama tapahtuman `Järjestäjäilmoitukset`-vastaanottajakenttä laukaisee ilmoituksen myös maksuttoman lomakkeen ilmoittautumisista (#638), joten järjestäjät hallitaan yhdestä paikasta molemmille poluille. Polut ovat toisensa poissulkevia: maksuton lomake näkyy vain, kun tapahtuma on maksuton eikä siihen ole linkitetty maksutuotetta, ja tämän dokumentin tilausilmoitus vaatii nimenomaan maksutuotteen. Sama ilmoittautuminen ei siis voi tuottaa kahta ilmoitusta.

Maksuttoman polun ilmoitus on dokumentoitu tiedostossa `docs/events.md`. Keskeisin ero: maksuttomalla polulla ei ole WooCommerce-tilausta eikä siten order note -audit trailia, joten lähetyksestä ei jää lokimerkintää.

### Debug local/dev-ympäristössä

- Jos `wp_mail` epäonnistuu local- tai dev-ympäristössä, tilaukselle lisätään private order noteen myös debug-esikatselu.
- Debug-notessa näkyvät tapahtuma, virhesyy, sähköpostin aiherivi ja viestin sisältö.
- Tarkoitus on helpottaa lokaalissa kehityksessä sen varmistamista, mitä sähköpostia järjestelmä yritti lähettää.

## Kuljetuksen ilmoittautuminen ja maksu jälkikäteen

Bussikyyti käyttää #450:n yleistä maksutonta ilmoittautumista. Maksu peritään erikseen vasta matkan varmistuttua.

### Tavoite

- Kerätä bussikyytiin lähtijät (nimi, sähköposti, **lähtöpaikka**, matkustajamäärä) **ilman maksua**.
- Hallitus näkee yhdellä silmäyksellä lähtijämäärän ja lähtöpaikkajakauman → voi todeta täyttyykö vähimmäismäärä (esim. 20).
- Kun matka varmistuu, maksu peritään **manuaalisilla WooCommerce-tilauksilla** (WooCommercen maksulinkki, nykyinen maksunvälittäjä Paytrail).

### Vaihe 1 — Bussikyytitapahtuman luonti (ylläpito)

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

### Vaihe 2 — Ilmoittautuminen (kävijä)

Tapahtumasivun lomakkeella kysytään:

- **Nimi** ja **sähköposti** (pakollisia)
- **Lähtöpaikka** (pudotusvalikko tapahtuman lähtöpaikoista, pakollinen). Bussikyydissä valikossa on myös vaihtoehto **Muu paikka reitin varrella** — jos kävijä valitsee sen, hän kirjoittaa tarkemman paikan Lisätieto-kenttään. Osallistujat-listassa ja CSV:ssä nämä näkyvät lähtöpaikkana "Muu paikka reitin varrella", ja tarkka paikka löytyy lisätiedoista
- **Matkustajien määrä** (oletus 1; perhe voi varata useamman paikan yhdellä lomakkeella)
- tietosuojasuostumus

Lomake kertoo selvästi, että ilmoittautuminen on tässä vaiheessa **maksuton** ja maksu peritään vasta kun kyyti varmistuu. Ilmoittautunut saa kuittaussähköpostin, jossa näkyy myös lähtöpaikka ja matkustajamäärä.

Tekninen huomio: matkustajamäärän yläraja on oletuksena 10 (suodatin `rytkoset_theme_event_registration_max_quantity`).

### Vaihe 3 — Lähtijöiden seuranta (ylläpito)

**Tapahtumat → Osallistujat**, valitse bussikyytitapahtuma:

- Lista näyttää bussikyytitapahtumalle omat sarakkeet **Lähtöpaikka** ja **Matkustajia**.
- **Bussikyydin yhteenveto** -laatikko näyttää matkustajat yhteensä (peruutetut pois lukien) sekä lähtöpaikkajakauman matkustajamäärinä.
- **Vie CSV** sisältää bussikyytitapahtumalle lähtöpaikka- ja matkustajamääräsarakkeet.

Vähimmäismäärän (esim. 20) toteutuminen todetaan **käsin** listalta — järjestelmä ei estä ilmoittautumista eikä laukaise automatiikkaa.

### Vaihe 4 — Vahvistus ja maksu (ylläpito, operatiivinen)

Kun lähtijöitä on riittävästi ja matka toteutuu:

1. **Ilmoita lähtijöille.** Käytä **Tapahtumat → Viestintä** -toimintoa ja lähetä bussikyytiläisille viesti, että matka toteutuu ja maksulinkki tulee erikseen.
2. **Peri maksu WooCommercella.** Luo kullekin ilmoittautuneelle (tai perheelle matkustajamäärän mukaan) WooCommerce-tilaus:
   - **WooCommerce → Tilaukset → Lisää tilaus.**
   - Lisää asiakas ja **bussipaikka-tuote** rivituotteena (matkustajamäärä = kappalemäärä), jätä tila **Odottaa maksua**.
   - Lähetä asiakkaalle tilauksen maksulinkki (*Lähetä tilaustiedot asiakkaalle* / Customer invoice). Asiakas jatkaa maksua nykyisellä Paytrail-maksutavalla.
3. Jos matka **ei** toteudu, ilmoita lähtijöille eikä maksuja peritä (yhtään tilausta ei ole vielä luotu).

> Tätä varten kannattaa pitää yksi yksinkertainen **bussipaikka-tuote** WooCommercessa (yksi hinta, ei variaatioita — lähtöpaikka tulee ilmoittautumisesta). Tuote voidaan **piilottaa kaupasta** (Catalog visibility), koska sitä ei myydä suoraan vaan käytetään vain manuaalisten tilausten rivituotteena.

### Vanha maksullinen tuote

Vanha maksullinen variaatiotuote (SKU `tampere-2026-bussikyyti`, `inc/woocommerce-bus-transport.php`, tiketti `#442`) on tällä mallilla **korvattu**:

- Piilota vanha tuote kaupasta ja säilytä sen määräpäivä suljettuna. Katalogipiilotus yksin ei estä ostamista suoralla linkillä.
- Voit säilyttää yksinkertaisen bussipaikka-tuotteen vaiheen 4 manuaalisia tilauksia varten.
- Koodimoduuli `inc/woocommerce-bus-transport.php` (määräpäivä-/kapasiteettiportti SKU:n perusteella) jää toistaiseksi paikalleen, mutta se ei ole enää ilmoittautumisen reitti. #642:ssa moduuli säilytetään ostosuojana: sen poistaminen voisi avata vielä julkaistun vanhan tuotteen myyntiin. Poisto tehdään erikseen, kun vanhan tuotteen myynnistä poistuminen on varmistettu kaikissa ympäristöissä. Uusi kuljetus ei tarvitse tätä moduulia.

### Tekninen toteutus

| Osa | Sijainti |
| --- | --- |
| Yleinen valintalista + määräkenttä + ruokavaliovalinta tapahtumalle ("Ilmoittautumisen lisävalinta" -laatikko; metat `_rytkoset_event_choice_enabled`, `_rytkoset_event_choice_options`, `_rytkoset_event_choice_field_label`, `_rytkoset_event_collect_quantity`/`_rytkoset_event_quantity_field_label`, `_rytkoset_event_collect_diet`), getterit ja valinnan resolveri. Meta-avaimet ja funktioiden nimet ovat geneerisiä (ei bussikohtaisia) | `inc/events.php` |
| Lomakkeen valinta + määräkenttä, validointi ja tallennus (metat `_rytkoset_registration_choice`, `_rytkoset_registration_quantity`), kuittaussähköposti tapahtuman nimillä | `inc/event-registrations.php` |
| Osallistujat-näkymän sarakkeet + yhteenveto + CSV-sarakkeet (tapahtuman nimillä) | `inc/event-participants-admin.php` |
| Maksuttoman ilmoittautumisen lähtöpaikka ja matkustajamäärä GDPR-vientiin; nykyinen anonymisointi säilyttää nämä arvot | `inc/event-registration-privacy.php` |

### Jätetään tietoisesti pois

- Automaattinen vähimmäismäärän/perumisen logiikka (todetaan ja hoidetaan käsin).
- Maksun integrointi suoraan ilmoittautumiseen (peritään erikseen vasta varmistuksen jälkeen).
- Bussipaikka-tuotteen ja tapahtuman tekninen linkitys (maksu hoidetaan manuaalisilla tilauksilla).

## Ostoskori tilauksen jälkeen

Onnistuneen tilauksen jälkeen ostoskorin pitää tyhjentyä myös kirjautuneella asiakkaalla. #642:n hyväksymistestissä löytyi WooCommercen kassapyyntöjen kilpailutilanne: ennen tilausvahvistusta alkanut taustapäivitys saattoi valmistua sen jälkeen ja tallentaa vanhan korin takaisin istuntoon. Teema estää vanhentuneen kassapäivityksen istuntotallennuksen. Uutta ostoskoria ei tyhjennetä tämän tarkistuksen yhteydessä. Samalla poistetaan Checkout Blockin istuntoon tallentama asiakkaan tilausmuistiinpano, jotta edellisen tilauksen tekstiä ei esitäytetä seuraavassa tilauksessa.

Paikallisessa toistokokeessa viivästetty taustapäivitys palautti ennen korjausta kaksi tuotetta valmistuneen tilauksen jälkeen. Korjauksen jälkeen sama kahdeksan sekunnin viivästyskoe jätti korin tyhjäksi. Myös heti oston jälkeen lisätty uusi tuote säilyi myöhäisen pyynnön valmistuessa. Erillisessä selaintestissä ensimmäisen 0 € tilauksen muistiinpano ei enää näkynyt uuden tuotteen kassalla. Lint ja 1 094 PHPUnit-testiä läpäisty (2 846 assertiota; ennestään tunnettu deprecation säilyy). Dev-julkaisutestissä tarkistetaan vielä korikuvakkeesta avattu ostoskori ja uuden tuotteen lisääminen oston jälkeen. Korjaus ei poista takautuvasti ennen korjausta jo palautuneita ostoskoreja; vanhan testikorin voi tyhjentää kerran ostoskorisivulla.

## Validointi ja julkaisutarkistus

Paikalliset tarkistukset 20.9.2026:

- PHPCS läpi. PHPUnit: 1 085 testiä / 2 819 assertiota läpi; ennestään tunnettu PHPUnit-deprecation säilyy.
- WooCommerce 11.1 / Docker: oikea Store API -tilaus kahdella osallistujalla, lisävalinnan kyllä- ja ei-vastaukset, kysymyksen tilausrivikohtainen tallennus sekä osallistujalista myös tuotteen asetusten muuttamisen jälkeen. HTML- ja tekstisähköpostit näyttävät alkuperäisen kysymyksen.
- Chromium: kassavalinnan label, rastin tilan säilyminen, näppäimistöfokus sekä 320/390/1 440 px vaaleassa ja tummassa teemassa. Ei vaakavieritystä.
- Oikea CSV-vientikäsittelijä näyttää kysymyksen sekä kyllä- ja ei-vastaukset. Tilausvahvistus näyttää lisävalinnat kerran. Sekakorin valinnaton, uusi valinnallinen ja vanha buffet-tuote näyttävät vain oikean osallistujarivin valinnan.
- Vaiheessa 2 tarkistettiin lisäksi osallistujarajan ylitys, valinnattoman tuotteen kassa ja CSV-vienti sekä vanhan ja uuden tuotteen yhteinen kassa.

Dev-hyväksyntä on vielä tekemättä. Paikalliset tilaukset ovat nollahintaisia ja sähköpostien lähettäminen estetään; ne eivät todenna ulkoista maksupalvelua. Ennen tiketin sulkemista tarkista `dev.rytkoset.net`-ympäristössä:

1. Luo uusi tapahtuma ja maksutuote ylläpidosta, aseta osallistujat, lisävalinta, määräpäivä, varasto ja järjestäjäosoitteet sekä linkitä tuote.
2. Osta yksi ja useampi osallistuja eri variaatioilla. Tarkista nimi, ruokarajoite, kyllä/ei sekä muiden korituotteiden vaikutuksettomuus osallistujamäärään.
3. Tarkista tilausvahvistus, asiakkaan ja ylläpidon sähköpostit, tilauksen osallistujalaatikko, tapahtuman osallistujalista ja CSV. Järjestäjäviesti ei sisällä ruokarajoitteita eikä vapaata tilauslisätietoa.
4. Poista lisävalinta käytöstä ja muuta kysymystä. Uusi kassa seuraa asetusta, vanha tilaus säilyttää alkuperäisen vastauksen. Tarkista vanhan tuotteen buffet-historia.
5. Tarkista tilausraja, täysi varasto ja umpeutunut määräpäivä sekä mobiili, tumma teema ja näkyvä näppäimistöfokus.
