# WooCommerce: Tampere 2026 järjestäjäilmoitukset

Tämä dokumentti kuvaa tiketin `#150` toteutusmallin.

## Tavoite

Kun Tampere 2026 -ilmoittautumistilaus siirtyy tilaan `on-hold`, juhlajärjestelytoimikunnalle lähtee yksi plain text -sähköposti ilman, että tilausta täytyy avata käsin WooCommerce-adminissa.

## Lähetyslogiikka

- Ilmoitus lähetetään vain Tampere 2026 -tilauksista.
- Ilmoitus lähetetään, kun tilaus siirtyy tilaan `on-hold`.
- Ilmoitus lähetetään vain kerran per tilaus.
- Lähetyksen deduplikointi tehdään order-metalla `_rytkoset_tampere_2026_notification_sent_at`.
- Käytetty vastaanottajalista tallennetaan order-metana avaimeen `_rytkoset_tampere_2026_notification_recipients`.

## Vastaanottajat

- Vastaanottajat hallitaan WordPress-adminissa kohdassa `Asetukset > Yleiset`.
- Asetuksen nimi on `Tampere 2026 järjestäjäilmoitusten vastaanottajat`.
- Osoitteet voidaan syöttää pilkuilla tai rivinvaihdoilla eroteltuna.
- Tallennuksessa säilytetään vain kelvolliset uniikit sähköpostiosoitteet.
- Jos vastaanottajia ei ole asetettu, sähköpostia ei lähetetä ja tilaukselle lisätään private order note.

## Sähköpostin sisältö

Ilmoitusviesti sisältää vähintään:

- tilausnumeron
- tilauksen päivämäärän
- tilan
- maksutavan
- yhteyshenkilön nimen
- sähköpostin
- puhelinnumeron
- osallistujien nimet
- ruokarajoitteet / allergiat, jos niitä on annettu
- asiakkaan lisätiedot, jos niitä on annettu
- linkin tilaukseen adminissa, jos sellainen voidaan muodostaa

## Audit trail

- Onnistuneesta lähetyksestä lisätään private order note.
- Epäonnistuneesta lähetyksestä lisätään private order note.
- Puuttuvista vastaanottajista lisätään private order note.

## Debug local/dev-ympäristössä

- Jos `wp_mail` epäonnistuu local- tai dev-ympäristössä, tilaukselle lisätään private order noteen myös debug-esikatselu.
- Debug-notessa näkyvät virhesyy, sähköpostin aiherivi ja viestin sisältö.
- Tarkoitus on helpottaa lokaalissa kehityksessä sen varmistamista, mitä sähköpostia järjestelmää oli yrittämässä lähettää.
