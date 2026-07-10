# WooCommerce: jäsenmaksutuotteet

Tämä dokumentti kuvaa jäsenmaksutuotteiden nykytilan paikallisessa Docker-ympäristössä.

## Tehty nyt

- Luotu kaksi WooCommerce-tuotetta:
  - `Vuosijäsenmaksu - Yksityishenkilö`, 30 EUR
  - `Vuosijäsenmaksu - Perhe`, 35 EUR
- Luotu yksi erillinen WooCommerce-tuote:
  - `Ainaisjäsenmaksu`, 100 EUR
- Molemmat tuotteet on toteutettu:
  - `Simple product`
  - `Virtual`
  - `Sold individually`
- `Ainaisjäsenmaksu` on toteutettu:
  - `Simple product`
  - `Virtual`
  - `Sold individually`
- Molemmat tuotteet on sijoitettu kategoriaan `Muut tuotteet`.
- Myös `Ainaisjäsenmaksu` on sijoitettu kategoriaan `Muut tuotteet`.
- Tuotteille on lisätty selkeät nimet, hinnat ja kuvaukset.
- Vuosijäsenmaksujen tuotekuvauksissa kerrotaan:
  - jäsenyys on voimassa sukukokousten välisen ajan
  - nykyinen kausi on `2023 - 2026`
  - jäsenen tai jäsenten nimet ja sähköpostiosoitteet syötetään kassan rakenteisiin kenttiin
- Kassalle näytetään rakenteiset kentät jäsenten nimille ja sähköposteille, kun korissa on nimet vaativa jäsenmaksutuote (`_rytkoset_member_names_required = yes`):
  - yksityis- ja ainaisjäsenmaksu: yksi nimi + sähköposti (molemmat pakollisia)
  - perhejäsenmaksu (`annual_family`): useampi nimi + sähköposti -rivi; ensimmäinen rivi pakollinen, lisärivien sähköpostit valinnaisia
  - tiedot tallentuvat tilauksen tietoihin rakenteisessa muodossa ja voidaan kirjata jäsenrekisteriin

## Tekniset huomiot

- Jäsenmaksutuotteet merkitään omalla tuotemetadata-lipulla:
  - `_rytkoset_membership_product = yes`
- Jäsenmaksun tyyppi tallennetaan tuotemetadataan:
  - `_rytkoset_membership_type = annual_individual`
  - `_rytkoset_membership_type = annual_family`
  - `_rytkoset_membership_type = lifetime`
- Vuosijäsenmaksujen jäsenkausi tallennetaan tuotemetadataan:
  - `_rytkoset_membership_period = 2023-2026`
- Vuosi-/perhejäsenmaksun voimassaolopäivä (esim. seuraavan sukukokouksen päivä) tallennetaan tuotemetadataan ISO-muodossa:
  - `_rytkoset_membership_expiry_date = 2029-08-15`
  - Asetetaan tuotteen **Jäsenyys voimassa asti** -kentästä. Automaattinen jäsenyyspäivitys (#302) käyttää tätä päivää käyttäjän jäsenyyden voimassaoloksi.
- Rakenteisten jäsenkenttien näkyminen kassalla määräytyy tuotemetadata-lipulla:
  - `_rytkoset_member_names_required = yes`
- Lippu on käytössä sekä vuosijäsenmaksuilla että ainaisjäsenmaksulla.
- Teema näyttää rakenteiset kentät silloin, kun korissa on nimet vaativa jäsenmaksutuote; kenttien käyttöä selittää niiden yläpuolelle injektoitu **Jäsentiedot**-osio-otsikko (ks. alla), ei erillinen ylätiedote — aiempi erillinen kassaohje-banneri poistettiin #520:n viimeistelyssä päällekkäisenä, kun otsikko lisättiin suoraan kenttien yhteyteen.
- WooCommercen samaa virheilmoitusta ei lisätä sessioon kahdesti. Kun yksittäin myytävä jäsenmaksutuote on jo ostoskorissa, uudesta lisäysyrityksestä näytetään vain yksi selkeä virheilmoitus.

### Rakenteiset jäsenkentät kassalla

Toteutus seuraa Tampere 2026 -kenttien mallia (`inc/woocommerce-tampere-2026.php`, `docs/woocommerce-tampere-2026-checkout-fields.md`):

- Kentät rekisteröidään WooCommerce Blocks -kassan lisäkenttärajapinnalla (`woocommerce_register_additional_checkout_field`, `location = order`): `rytkoset/member_X_name` ja `rytkoset/member_X_email` (X = 1–6).
- Teema julkaisee Checkout Blockille näytettävien rivien määrän Store API:n `cart.extensions.rytkoset_membership.member_row_count` -kentässä: 1 yksityis-/ainaisjäsenmaksulle, käyttäjän lisäämä määrä 1–6 perhejäsenmaksulle ja 0, kun korissa ei ole nimet vaativaa jäsenmaksua.
- Perhejäsenmaksu alkaa yhdestä rivistä. **+ Lisää jäsen** lisää uuden rivin ilman sivulatausta ja rivien 2–6 roskakoripainike poistaa rivin; riviä 1 ei voi poistaa. Poisto tiivistää myöhemmät arvot järjestykseen ja tyhjentää viimeisen rivin, jotta poistettu tieto ei tallennu tilaukselle.
- Rivimäärä tallennetaan WooCommerce-sessioon `extensionCartUpdate`-päivityksellä. Palvelin clampaa arvon aina tuotetyypin sallimaan väliin; `rytkoset_theme_membership_max_member_rows`-suodattimella voi muuttaa oletusmaksimia 6.
- Rivit, joiden indeksi ylittää `member_row_count`-arvon, piilotetaan ja niiden validointi ohitetaan ehdollisella JSON-skeemalla.
- Rivin 1 nimi ja sähköposti ovat pakollisia; lisärivit ovat valinnaisia. Sähköpostin muoto tarkistetaan `validate_callback`-funktiolla (`is_email`); tyhjät valinnaiset rivit ohitetaan.
- Kentät tallentuvat tilauksen lisäkentiksi order-metana (`_wc_other/rytkoset/member_X_name`, `_wc_other/rytkoset/member_X_email`).
- Tyhjien jäsenrivien lisäkenttämetat poistetaan uusilta Store API -tilauksilta, eikä tyhjiä rivejä näytetä tilausvahvistuksessa, sähköposteissa tai WooCommerce-adminissa.
- Kenttien autocomplete on rajattu pois, jotta selaimen autofill ei kirjoita arvoja vääriin riveihin.
- Dynaamisten kontrollien näppäimistö- ja ruudunlukijakäyttö on huomioitu: poistopainikkeilla on rivikohtaiset `aria-label`-tekstit, muutoksista ilmoitetaan live-alueella ja fokus siirtyy lisäyksen jälkeen uuden rivin nimeen sekä poiston jälkeen seuraavaan järkevään kontrolliin.
- Jäsenrivien yläpuolelle injektoidaan **Jäsentiedot**-osio-otsikko ja lyhyt ohjeteksti (`assets/js/membership-checkout-rows.js`); teksti vaihtuu perhe- ja yksilö-/ainaisjäsenmaksun välillä. Skripti latautuu aina, kun korissa on nimet vaativa jäsenmaksu — lisäys-/poistokontrollit renderöityvät vain perhejäsenmaksulla.
- Jäsen- ja muut tuotekohtaiset lisätiedot sekä mahdollinen tilausmuistiinpano näytetään ennen maksutapoja. Maksutavat ovat viimeinen muokattava osio ennen ehtoja ja teeman keltaista, pyöristettyä **Lähetä tilaus** -painiketta.

### Jäsen 1 -kenttien esitäyttö kirjautuneelle käyttäjälle (#521)

Kun kirjautunut käyttäjä ostaa nimet vaativan jäsenmaksutuotteen, kassalla ehdotetaan jäsenriville 1 oletuksena käyttäjän omia tietoja:

- Nimi: profiilin etu- ja sukunimi; jos ne puuttuvat, näyttönimi (`rytkoset_theme_get_membership_member_prefill_name()`).
- Sähköposti: tilin `user_email` (`rytkoset_theme_get_membership_member_prefill_email()`).
- Koskee kaikkia jäsenmaksutyyppejä, joilla `_rytkoset_member_names_required = yes`. Perhejäsenyydessä vain rivi 1 esitäytetään; rivit 2–6 jäävät tyhjiksi.
- Vierasostajille ei tehdä esitäyttöä (skripti ja arvot eivät edes lataudu sivulle).

Toteutus on rajattu checkout-JS (`assets/js/membership-checkout-prefill.js`), joka täyttää checkout-datastoren (`wc/store/checkout`, `setAdditionalFields`) kautta vain tyhjinä pysyvät jäsen 1 -kentät. Täyttöä yritetään uudelleen lyhyen käynnistysikkunan ajan (15 s), koska Checkout Block voi mountin jälkeen tehdä asynkronisen refreshin, joka nollaa lisäkentät ja pyyhkisi kertatäytön; heti kun käyttäjä itse koskee jäsen 1 -kenttään, täyttö lopetetaan pysyvästi. Esitäyttö on ehdotus, ei lukitus: käyttäjän muokkaamia tai kassan draft-tilaukselle jo tallentuneita arvoja ei koskaan ylikirjoiteta (tyhjennetty kenttä pysyy tyhjänä), ja tilaukselle tallentuu se arvo, jonka käyttäjä lopulta hyväksyy. Huomaa, että tuoreen draft-tilauksen hydraatiossa storen `additionalFields`-objektissa ei välttämättä ole member-avaimia lainkaan, joten skripti käyttää renderöityä kenttää (ei avaimen olemassaoloa) merkkinä kentän käytöstä. WooCommercen palvelinpuolen oletusarvosuodatin (`woocommerce_get_default_value_for_*`) todettiin toteutuksessa epäluotettavaksi Block Checkoutin hydraatiosykleissä (ensikäynnillä arvo ei näy; asiakasobjektin oletus yliajaa draft-tilaukselle tallennetun muokkauksen), joten sitä ei käytetä.

## Jäsenmaksutilausten käsittelymalli

Jäsenmaksutilaukset käsitellään toistaiseksi manuaalisesti WooCommerce-adminin tietojen perusteella.

WooCommerce Orders -listaan lisätään sarake:

- `Jäsenmaksu`

Sarake näyttää jäsenmaksutilauksille jäsenmaksun tyypin ja vuosijäsenmaksuilla myös jäsenkauden, esimerkiksi:

- `Vuosijäsen: Yksityishenkilö, 2023-2026`
- `Vuosijäsen: Perhe, 2023-2026`
- `Ainaisjäsen`

Yksittäisen tilauksen admin-näkymään lisätään `Jäsenmaksu`-laatikko. Se näyttää:

- jäsenmaksun tyypin
- jäsenkauden
- tilauksen tilan
- yhteyshenkilön nimen, sähköpostin ja puhelinnumeron
- syötetyt jäsenet (nimi + sähköposti) rakenteisena listana
- asiakkaan kirjoittamat lisätiedot
- ylläpidon käsittelyohjeen

Jäsenet poimitaan suoraan rakenteisista jäsenkentistä manuaalista jäsenrekisteriin vientiä varten. Jos jäsenkentät ovat tyhjät eikä lisätietokentässä ole tietoja, tilausnäkymä näyttää ylläpidolle huomion. Vanhat tilaukset, joissa jäsenet on kirjattu vapaaseen lisätietokenttään (tilauksen muistiinpanoon), näkyvät adminissa edelleen kuten ennen.

## Uuden jäsenkauden käyttöönotto

Sukukokouksen jälkeen uusi jäsenkausi, esimerkiksi `2026-2029`, tehdään uusina WooCommerce-tuotteina.

Vanhoja `2023-2026` tuotteita ei muokata uuteen kauteen, koska niitä tarvitaan tilaushistoriaa varten.

Suositeltu toimintamalli:

1. Kloonaa vanhat vuosijäsenmaksutuotteet.
2. Vaihda tuotteiden nimet ja kuvaukset uudelle kaudelle.
3. Anna uusille tuotteille uudet SKU:t:
   - `JASEN-2026-2029-YKSITYINEN`
   - `JASEN-2026-2029-PERHE`
4. Aseta jäsenmaksumetadatat:
   - `_rytkoset_membership_product = yes`
   - `_rytkoset_membership_type = annual_individual` tai `annual_family`
   - `_rytkoset_membership_period = 2026-2029`
   - **Jäsenyys voimassa asti** = uuden kauden sukukokouksen päivä (käytetään automaattisessa jäsenyyspäivityksessä #302)
   - `_rytkoset_member_names_required = yes`
5. Piilota vanhat `2023-2026` tuotteet kaupasta, kun niitä ei enää myydä.
6. Päivitä jäsenyyssivun linkit uusiin tuotteisiin.
7. Tee testitilaus ennen julkaisua.

## Testattu nyt

- Molemmat tuotteet ovat olemassa WooCommercessa oikeilla hinnoilla.
- Molemmat tuotteet ovat virtuaalisia ja myydään yksittäin.
- `Ainaisjäsenmaksu` on olemassa WooCommercessa hinnalla 100 EUR.
- `Ainaisjäsenmaksu` on virtuaalinen ja myydään yksittäin.
- Vuosijäsenmaksutuote voidaan lisätä ostoskoriin.
- `Kassa`-sivu latautuu jäsenmaksutuotteen kanssa.
- Kassasivulle syötetään teeman kautta jäsenmaksuohje oikeassa sessiossa.
- `Ainaisjäsenmaksu` voidaan lisätä ostoskoriin ja `Kassa`-sivu latautuu oikein.
- `Ainaisjäsenmaksu` aktivoi saman jäsenrekisteriohjeen kuin vuosijäsenmaksut.
- Yksittäin myytävän jäsenmaksutuotteen uusi lisäysyritys ei tuota kahta samaa ostoskori-ilmoitusta.
- Jäsenmaksutuotteet tunnistetaan adminissa jäsenmaksumetadatan perusteella.
- WooCommerce Orders -lista näyttää jäsenmaksutilauksille `Jäsenmaksu`-sarakkeen arvon.
- Jäsenmaksutilauksen admin-näkymässä näkyy käsittelyyn tarkoitettu `Jäsenmaksu`-laatikko.

## Jätetään seuraaviin tiketteihin

- Jäsenyyden uusintalogiikka
- Jäsenrekisteri-integraatio
- Automaattiset jäsenyyden voimassaolomerkinnät
- WordPress-käyttäjään sidottu jäsenyyden tila
- Cron-pohjainen jäsenyyden vanheneminen
- Mahdollinen erillinen kuittaus- tai sähköpostiviesti jäsenmaksuille
