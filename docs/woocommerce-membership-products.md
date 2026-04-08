# WooCommerce: jasenmaksutuotteet

Tama dokumentti kuvaa jasenmaksutuotteiden nykytilan paikallisessa Docker-ymparistossa.

## Tehty nyt

- Luotu kaksi WooCommerce-tuotetta:
  - `Vuosijasenmaksu - Yksityishenkilo`, 30 EUR
  - `Vuosijasenmaksu - Perhe`, 35 EUR
- Luotu yksi erillinen WooCommerce-tuote:
  - `Ainaisjasenmaksu`, 100 EUR
- Molemmat tuotteet on toteutettu:
  - `Simple product`
  - `Virtual`
  - `Sold individually`
- `Ainaisjasenmaksu` on toteutettu:
  - `Simple product`
  - `Virtual`
  - `Sold individually`
- Molemmat tuotteet on sijoitettu kategoriaan `Muut tuotteet`.
- Myos `Ainaisjasenmaksu` on sijoitettu kategoriaan `Muut tuotteet`.
- Tuotteille on lisatty selkeat nimet, hinnat ja kuvaukset.
- Vuosijasenmaksujen tuotekuvauksissa kerrotaan:
  - jasenyys on voimassa sukukokousten valisen ajan
  - nykyinen kausi on `2023 - 2026`
- Kassalle on lisatty ohjeteksti tilanteisiin, joissa korissa on jasenmaksutuote:
  - kaikkien jasenten nimet tulee kirjoittaa `Lisatietoja`-kenttaan
  - tiedot voidaan kirjata jasenrekisteriin

## Tekniset huomiot

- Jasenmaksutuotteet on merkitty omalla tuotemetadata-lipulla:
  - `_rytkoset_member_names_required = yes`
- Metadata on kaytossa vain vuosijasenmaksutuotteilla.
- Teema nayttaa kassaoheen vain silloin, kun korissa on tuote, jolla tuo metadata on kaytossa.
- `Ainaisjasenmaksu` ei nayta vuosijasenmaksujen lisatieto-ohjetta kassalla.
- Kassaoheen renderointi tehdaan teemassa, koska WooCommerce Block Checkout ei nayttanyt luotettavasti normaalia sivusisaltoa nykyisessa teemassa.

## Testattu nyt

- Molemmat tuotteet ovat olemassa WooCommercessa oikeilla hinnoilla.
- Molemmat tuotteet ovat virtuaalisia ja myydaan yksittain.
- `Ainaisjasenmaksu` on olemassa WooCommercessa hinnalla 100 EUR.
- `Ainaisjasenmaksu` on virtuaalinen ja myydaan yksittain.
- Vuosijasenmaksutuote voidaan lisata ostoskoriin.
- `Kassa`-sivu latautuu jasenmaksutuotteen kanssa.
- Kassasivulle syotetaan teeman kautta jasenmaksuohje oikeassa sessiossa.
- `Ainaisjasenmaksu` voidaan lisata ostoskoriin ja `Kassa`-sivu latautuu oikein.
- `Ainaisjasenmaksu` ei aktivoi vuosijasenmaksujen kassaohjetta.

## Jatetaan seuraaviin tiketteihin

- Jasenyyden uusintalogiikka
- Mahdollinen jasenkategoria tai oma tuoteryhma jasenmaksuille
- Jasenrekisteri-integraatio
- Automaattiset merkinnat tai kasittelysaannot tilauksille
- Mahdollinen erillinen kuittaus- tai sahkopostiviesti jasenmaksuille
