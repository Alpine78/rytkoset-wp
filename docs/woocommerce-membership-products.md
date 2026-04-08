# WooCommerce: jasenmaksutuotteet

Tama dokumentti kuvaa ensimmaisen jasenmaksu-slicen paikallisessa Docker-ymparistossa.

## Tehty nyt

- Luotu kaksi WooCommerce-tuotetta:
  - `Vuosijasenmaksu - Yksityishenkilo`, 30 EUR
  - `Vuosijasenmaksu - Perhe`, 35 EUR
- Molemmat tuotteet on toteutettu:
  - `Simple product`
  - `Virtual`
  - `Sold individually`
- Molemmat tuotteet on sijoitettu kategoriaan `Muut tuotteet`.
- Tuotteille on lisatty selkeat nimet, hinnat ja kuvaukset.
- Tuotekuvauksissa kerrotaan:
  - jasenyys on voimassa sukukokousten valisen ajan
  - nykyinen kausi on `2023 - 2026`
- Kassalle on lisatty ohjeteksti tilanteisiin, joissa korissa on jasenmaksutuote:
  - kaikkien jasenten nimet tulee kirjoittaa `Lisatietoja`-kenttaan
  - tiedot voidaan kirjata jasenrekisteriin

## Tekniset huomiot

- Jasenmaksutuotteet on merkitty omalla tuotemetadata-lipulla:
  - `_rytkoset_member_names_required = yes`
- Teema nayttaa kassaoheen vain silloin, kun korissa on tuote, jolla tuo metadata on kaytossa.
- Kassaoheen renderointi tehdaan teemassa, koska WooCommerce Block Checkout ei nayttanyt luotettavasti normaalia sivusisaltoa nykyisessa teemassa.

## Testattu nyt

- Molemmat tuotteet ovat olemassa WooCommercessa oikeilla hinnoilla.
- Molemmat tuotteet ovat virtuaalisia ja myydaan yksittain.
- Tuote voidaan lisata ostoskoriin.
- `Kassa`-sivu latautuu jasenmaksutuotteen kanssa.
- Kassasivulle syotetaan teeman kautta jasenmaksuohje oikeassa sessiossa.

## Jatetaan seuraaviin tiketteihin

- `Ainaisjasenmaksu`, 100 EUR
- Jasenyyden uusintalogiikka
- Mahdollinen jasenkategoria tai oma tuoteryhma jasenmaksuille
- Jasenrekisteri-integraatio
- Automaattiset merkinnat tai kasittelysaannot tilauksille
- Mahdollinen erillinen kuittaus- tai sahkopostiviesti jasenmaksuille
