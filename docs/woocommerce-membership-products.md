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
- Kassalle on lisätty ohjeteksti tilanteisiin, joissa korissa on jäsenmaksutuote:
  - kaikkien jäsenten nimet tulee kirjoittaa `Lisätietoja`-kenttään
  - tiedot voidaan kirjata jäsenrekisteriin

## Tekniset huomiot

- Jäsenmaksutuotteet on merkitty omalla tuotemetadata-lipulla:
  - `_rytkoset_member_names_required = yes`
- Metadata on käytössä vain vuosijäsenmaksutuotteilla.
- Teema näyttää kassaohjeen vain silloin, kun korissa on tuote, jolla tuo metadata on käytössä.
- `Ainaisjäsenmaksu` ei näytä vuosijäsenmaksujen lisätieto-ohjetta kassalla.
- Kassaohjeen renderöinti tehdään teemassa, koska WooCommerce Block Checkout ei näyttänyt luotettavasti normaalia sivusisältöä nykyisessä teemassa.

## Testattu nyt

- Molemmat tuotteet ovat olemassa WooCommercessa oikeilla hinnoilla.
- Molemmat tuotteet ovat virtuaalisia ja myydään yksittäin.
- `Ainaisjäsenmaksu` on olemassa WooCommercessa hinnalla 100 EUR.
- `Ainaisjäsenmaksu` on virtuaalinen ja myydään yksittäin.
- Vuosijäsenmaksutuote voidaan lisätä ostoskoriin.
- `Kassa`-sivu latautuu jäsenmaksutuotteen kanssa.
- Kassasivulle syötetään teeman kautta jäsenmaksuohje oikeassa sessiossa.
- `Ainaisjäsenmaksu` voidaan lisätä ostoskoriin ja `Kassa`-sivu latautuu oikein.
- `Ainaisjäsenmaksu` ei aktivoi vuosijäsenmaksujen kassaohjetta.

## Jätetään seuraaviin tiketteihin

- Jäsenyyden uusintalogiikka
- Mahdollinen jäsenkategoria tai oma tuoteryhmä jäsenmaksuille
- Jäsenrekisteri-integraatio
- Automaattiset merkinnät tai käsittelysäännöt tilauksille
- Mahdollinen erillinen kuittaus- tai sähköpostiviesti jäsenmaksuille
