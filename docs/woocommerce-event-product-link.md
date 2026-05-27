# WooCommerce: tapahtuman linkitys maksutuotteeseen

Tämä dokumentti kuvaa tiketin `#138` toteutuksen.

## Tavoite

Tapahtumalle voidaan valita yksi WooCommerce-tuote, jolloin tapahtumasivulle näytetään selkeä painike tuotteen ostosivulle.

Tämä pitää tapahtuman sisällön ja WooCommerce-tuotteen erillään:

- tapahtumasivu kertoo tapahtumasta
- WooCommerce-tuote hoitaa ostamisen

## Ylläpito

1. Avaa WordPress-adminissa `Tapahtumat`.
2. Luo tai avaa tapahtuma.
3. Etsi sivupalkista laatikko `Maksutuote`.
4. Valitse kentästä `WooCommerce-tuote`.
5. Tallenna tapahtuma.

Jos tapahtumalle ei valita tuotetta, tapahtumasivulle ei tule maksupainiketta.

## Julkinen näkymä

Kun tapahtumalle on valittu maksutuote, tapahtumasivulla näkyy painike:

`Ilmoittaudu ja maksa`

Painike vie WooCommerce-tuotteen julkiselle tuotesivulle. Se ei lisää tuotetta automaattisesti ostoskoriin eikä ohjaa suoraan kassalle.

Jos linkitetty tuote ei ole ostettavissa, tapahtumasivulla ei näytetä aktiivista maksupainiketta. Esimerkiksi Tampere 2026 -tuotteen ilmoittautumisen määräpäivä luetaan suoraan tuotteelta, jolloin tapahtumasivu voi näyttää viestin `Ilmoittautuminen on päättynyt.` ilman että sama deadline tallennetaan tapahtumalle erikseen.

## Rajaus

Tässä vaiheessa toteutetaan vain linkitys yhteen WooCommerce-tuotteeseen.

Ei toteuteta vielä:

- automaattista ostoskoriin lisäämistä
- suoraa kassalle ohjausta
- usean tuotteen valintaa samalle tapahtumalle
- tapahtumakohtaista kapasiteettilogiikkaa
- lippuja tai QR-koodeja

Tampere 2026 -tuotteen määräpäivä ja kapasiteetti hallitaan edelleen WooCommerce-tuotteen omilla asetuksilla. Tapahtuma lukee näitä tietoja vain julkisen tapahtumasivun näyttöä varten.
