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

Ilmoitusviesti sisältää vähintään:

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
- osallistujat
- asiakkaan lisätiedot, jos niitä on annettu
- linkin tilaukseen adminissa, jos sellainen voidaan muodostaa

Tampere 2026 -tilauksissa osallistujat puretaan edelleen checkoutin osallistujakentistä. Muissa maksullisissa tapahtumissa osallistujat muodostetaan tilaajan yhteystiedoista ja tapahtumatuotteen ostetusta määrästä.

## Audit trail

- Onnistuneesta lähetyksestä lisätään private order note.
- Epäonnistuneesta lähetyksestä lisätään private order note.
- Puuttuvista vastaanottajista lisätään private order note.
- Onnistuneen lähetyksen ajankohta ja käytetty vastaanottajalista tallennetaan tilauksen metaan tapahtumakohtaisesti.

## Debug local/dev-ympäristössä

- Jos `wp_mail` epäonnistuu local- tai dev-ympäristössä, tilaukselle lisätään private order noteen myös debug-esikatselu.
- Debug-notessa näkyvät tapahtuma, virhesyy, sähköpostin aiherivi ja viestin sisältö.
- Tarkoitus on helpottaa lokaalissa kehityksessä sen varmistamista, mitä sähköpostia järjestelmä yritti lähettää.
