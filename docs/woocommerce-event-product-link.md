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

## Kuka saa linkittää minkä tuotteen (#745)

Linkitetyn tuotteen ostajat näkyvät tapahtuman osallistujalistassa, CSV-viennissä, järjestäjäilmoituksissa ja viestintätyökalussa. Siksi linkitys on rajattu:

- **Ilmoittautumistuotteen** saa linkittää jokainen, joka saa muokata tapahtumaa, myös Event Organizer. Ilmoittautumistuote on tuote, jonka varastoasetuksissa on tapahtumailmoittautuminen käytössä (`_rytkoset_registration_mode`), sekä vanha Tampere 2026 -tuote (vanha tila tai SKU). Katso [maksullisen tapahtuman yleisohje](woocommerce-event-registration.md).
- **Muun tuotteen** saa linkittää vain käyttäjä, jolla on oikeus muokata kyseistä tuotetta (ylläpitäjä tai kauppapäällikkö). Tällaisen tuotteen osallistujarivit muodostetaan tilauksen laskutustiedoista.
- Event Organizerin tuotevalikossa näkyvät vain ilmoittautumistuotteet. Jos tapahtumaan on jo linkitetty muu tuote, hän näkee tuotteen nimen ilman valikkoa, eikä tapahtuman tallennus muuta tai poista linkkiä.
- Jos WooCommerce ei ole käytössä, tapahtuman tallennus jättää linkin ennalleen.

Rajaus koskee linkin asettamista. Se ei muuta jo tallennettuja linkityksiä, eikä se seuraa tuotteen myöhempiä muutoksia: jos ilmoittautumistuotteelta poistetaan ilmoittautumistila tai tuote otetaan muuhun käyttöön, poista linkki tapahtumasta samalla.

Kehittäjälle: `rytkoset_theme_save_event_product_link()` on suojatun,
rekisteröimättömän `_rytkoset_event_product_id`-metan kirjoittaja.
`rytkoset_theme_user_can_link_event_product()` tarkistaa ilmoittautumistuotteen
tunnistuksen tai kyseisen tuote-ID:n `edit_post`-oikeuden. Ilmoittautumistuotteen
tunnistus periytyy variaatiolle emolta.
Lukittu linkki ohitetaan tallennuksessa ennen kentän lukemista; puuttuvaan
tuotteeseen osoittavaa ID:tä ei lukita. Lukuun ei lisätä nykyiseen käyttäjään
sidottua rajausta: ylläpitäjän tavallinen tuotelinkki on tuettu, ja esimerkiksi
järjestäjäilmoitus voi lähteä ilman kirjautunutta käyttäjää.

### Kertatarkistus käyttöönoton yhteydessä

Koska vanhoja linkityksiä ei muuteta, tarkista dev- ja tuotantoympäristössä kerran, ettei mihinkään tapahtumaan ole linkitetty tuotetta, joka ei kuulu sille:

```bash
wp post list --post_type=rytkoset_event --post_status=any \
  --meta_key=_rytkoset_event_product_id --fields=ID,post_title,post_status,post_author
wp post list --post_type=rytkoset_event --post_status=trash \
  --meta_key=_rytkoset_event_product_id --fields=ID,post_title,post_status,post_author
wp post meta get <tapahtuman ID> _rytkoset_event_product_id
```

Tila `any` ei sisällä roskakoria, joten roskakorissa olevat tapahtumat listataan erikseen: myös niiden linkitys on voimassa. Käy lista läpi ja varmista jokaisen tuotteen kohdalla, että se on kyseisen tapahtuman maksutuote. Jos löytyy asiaton linkitys, poista se ylläpitäjänä ja tarkista lisäksi **Tapahtumat > Viestintä** -näkymän lähetysjono: jonoon jo lisätty viesti säilyttää vastaanottajansa, vaikka linkki poistetaan.

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
