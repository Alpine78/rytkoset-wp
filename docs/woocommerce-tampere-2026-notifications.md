# WooCommerce: maksullisten tapahtumien järjestäjäilmoitukset

Tämä dokumentti kuvaa alun perin Tampere 2026 -ilmoituksia varten tehtyä toiminnallisuutta. Tiketissä `#269` se muutettiin yleiseksi maksullisten tapahtumien järjestäjäilmoitukseksi.

## Tavoite

Kun WooCommerce-tilaus sisältää tapahtumaan linkitetyn maksutuotteen, tapahtuman järjestäjille lähtee yksi plain text -sähköposti ilman, että tilausta täytyy avata käsin WooCommerce-adminissa.

## Lähetyslogiikka

- Ilmoitus lähetetään WooCommerce-tilauksista, joissa on jonkin tapahtuman `Maksutuote`-kenttään linkitetty tuote tai sen variaatio.
- Ilmoitus lähetetään, kun tilaus saavuttaa ensimmäisen aktiivisen tilan: `on-hold`, `processing` tai `completed`.
- Ilmoitus lähetetään vain kerran per tapahtuma per tilaus.
- Lähetyksen deduplikointi tehdään order-metalla `_rytkoset_event_organizer_notification_sent_at_{event_id}`.
- Käytetty vastaanottajalista tallennetaan order-metana avaimeen `_rytkoset_event_organizer_notification_recipients_{event_id}`.
- Jos samassa tilauksessa on usean eri tapahtuman maksutuotteita, jokaiselle tapahtumalle lähetetään oma ilmoitus sen omille vastaanottajille.

## Vastaanottajat

- Vastaanottajat hallitaan tapahtuman muokkausnäkymässä metaboxissa `Järjestäjäilmoitukset`.
- Kentän nimi on `Järjestäjäilmoitusten vastaanottajat`.
- Tapahtuman meta-avain on `_rytkoset_event_organizer_notification_recipients`.
- Osoitteet voidaan syöttää pilkuilla tai rivinvaihdoilla eroteltuna.
- Tallennuksessa säilytetään vain kelvolliset uniikit sähköpostiosoitteet.
- Jos vastaanottajia ei ole asetettu, sähköpostia ei lähetetä ja tilaukselle lisätään private order note.
- Vanhaa `Asetukset > Yleiset` -sivun Tampere 2026 -vastaanottajakenttää ei enää näytetä eikä käytetä.

## Sähköpostin sisältö

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
- osallistujien nimet, osallistujatyypin ja perjantain buffet -valinnan
- ilmoittautumisyhteenvedon (ks. alla)
- linkin tilaukseen adminissa, jos sellainen voidaan muodostaa
- linkin `Tapahtumat > Osallistujat` -näkymään

Tampere 2026 -tilauksissa osallistujat puretaan edelleen checkoutin osallistujakentistä. Muissa maksullisissa tapahtumissa osallistujat muodostetaan tilaajan yhteystiedoista ja tapahtumatuotteen ostetusta määrästä.

### Tietominimointi (#643)

Viestistä on tarkoituksella jätetty pois tiedot, jotka ovat arkaluonteisia tai jotka toistuisivat turhaan:

- **ruokarajoitteet ja allergiat** — terveyteen liittyvää tietoa ei lähetetä sähköpostitse, vaan se luetaan tilausnäkymästä tai `Tapahtumat > Osallistujat` -sivulta
- **osallistujakohtaiset sähköpostit ja puhelinnumerot** — tilaajan yhteystiedot ovat jo viestissä kerran omassa lohkossaan
- **asiakkaan vapaa lisätietokenttä** — voi sisältää mitä tahansa, myös terveystietoa, joten se jätetään tilaukselle

Viestin lopussa on aina kaksi linkkiä, joista tarkat tiedot löytyvät: `tilaus:` ja `osallistujalista:`.

Sama minimointi koskee WooCommercen omaa **ylläpidon "Uusi tilaus" -sähköpostia**: `rytkoset_theme_hide_tampere_2026_diet_fields_from_admin_email()` piilottaa `rytkoset/participant_N_diet` -kentät, kun WooCommerce merkitsee viestin `sent_to_admin`-kontekstiin. Lisäksi tapahtumatilauksen vapaa tilauslisätieto (`customer_note`) piilotetaan ylläpidon HTML- ja tekstisähköpostien tilaustaulukosta. Tämä kenttä tulostuu WooCommercessa erillään osallistujakentistä, joten se suodatetaan erikseen vain viestin renderöinnin ajaksi. Rajaus koskee Tampere 2026 -tilauksia ja muihin tapahtumiin linkitettyjä tilauksia; muiden tilausten lisätiedot näkyvät edelleen. Asiakkaan oma tilausvahvistus, kiitos-sivu ja tilausnäkymä säilyvät ennallaan.

### Ilmoittautumisyhteenveto (#643)

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

## Audit trail

- Onnistuneesta lähetyksestä lisätään private order note.
- Epäonnistuneesta lähetyksestä lisätään private order note.
- Puuttuvista vastaanottajista lisätään private order note.
- Onnistuneen lähetyksen ajankohta ja käytetty vastaanottajalista tallennetaan tilauksen metaan tapahtumakohtaisesti.

## Maksuttomat tapahtumat

Sama tapahtuman `Järjestäjäilmoitukset`-vastaanottajakenttä laukaisee ilmoituksen myös maksuttoman lomakkeen ilmoittautumisista (#638), joten järjestäjät hallitaan yhdestä paikasta molemmille poluille. Polut ovat toisensa poissulkevia: maksuton lomake näkyy vain, kun tapahtuma on maksuton eikä siihen ole linkitetty maksutuotetta, ja tämän dokumentin tilausilmoitus vaatii nimenomaan maksutuotteen. Sama ilmoittautuminen ei siis voi tuottaa kahta ilmoitusta.

Maksuttoman polun ilmoitus on dokumentoitu tiedostossa `docs/events.md`. Keskeisin ero: maksuttomalla polulla ei ole WooCommerce-tilausta eikä siten order note -audit trailia, joten lähetyksestä ei jää lokimerkintää.

## Debug local/dev-ympäristössä

- Jos `wp_mail` epäonnistuu local- tai dev-ympäristössä, tilaukselle lisätään private order noteen myös debug-esikatselu.
- Debug-notessa näkyvät tapahtuma, virhesyy, sähköpostin aiherivi ja viestin sisältö.
- Tarkoitus on helpottaa lokaalissa kehityksessä sen varmistamista, mitä sähköpostia järjestelmä yritti lähettää.
