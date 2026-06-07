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
  - jäsenen tai jäsenten nimet ja sähköpostiosoitteet kirjoitetaan tilauksen muistiinpanoon
- Kassalle on lisätty ohjeteksti kaikille jäsenmaksutuotteille, myös ainaisjäsenmaksulle:
  - tilaaja valitsee `Lisää muistiinpano tilaukseesi`
  - jäsenen tai jäsenten nimet ja sähköpostiosoitteet kirjoitetaan muistiinpanoon
  - tiedot voidaan kirjata jäsenrekisteriin

## Tekniset huomiot

- Jäsenmaksutuotteet merkitään omalla tuotemetadata-lipulla:
  - `_rytkoset_membership_product = yes`
- Jäsenmaksun tyyppi tallennetaan tuotemetadataan:
  - `_rytkoset_membership_type = annual_individual`
  - `_rytkoset_membership_type = annual_family`
  - `_rytkoset_membership_type = lifetime`
- Vuosijäsenmaksujen jäsenkausi tallennetaan tuotemetadataan:
  - `_rytkoset_membership_period = 2023-2026`
- Kassalla näytettävä nimiohje määräytyy tuotemetadata-lipulla:
  - `_rytkoset_member_names_required = yes`
- Nimiohjeen metadata on käytössä vain vuosijäsenmaksutuotteilla.
- Teema näyttää kassaohjeen silloin, kun korissa on jäsenmaksutuotteeksi merkitty tuote.
- Checkout Blockin tilausmuistiinpano käyttää WooCommercen omaa valintatekstiä `Lisää muistiinpano tilaukseesi`.
- `Ainaisjäsenmaksu` näyttää saman jäsenrekisteriohjeen kuin vuosijäsenmaksut.
- Kassaohjeen renderöinti tehdään teemassa, koska WooCommerce Block Checkout ei näyttänyt luotettavasti normaalia sivusisältöä nykyisessä teemassa.
- WooCommercen samaa virheilmoitusta ei lisätä sessioon kahdesti. Kun yksittäin myytävä jäsenmaksutuote on jo ostoskorissa, uudesta lisäysyrityksestä näytetään vain yksi selkeä virheilmoitus.

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
- asiakkaan kirjoittamat lisätiedot
- ylläpidon käsittelyohjeen

Vuosijäsenmaksuissa lisätietokentästä poimitaan jäsenen tai perheenjäsenten nimet manuaalista jäsenrekisteriin vientiä varten. Jos lisätietokenttä on tyhjä, tilausnäkymä näyttää ylläpidolle huomion.

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
