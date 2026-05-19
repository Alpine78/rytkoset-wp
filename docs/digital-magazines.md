# Digilehdet

Digilehdet ovat WordPressissä HTML-sisältöä. Niitä ei julkaista PDF-tiedostoina, WooCommerce-tuotteina tai ostoon sidottuina sisältöinä tässä MVP-vaiheessa.

## Sisältömalli

Digilehti käyttää yhtä hierarkkista sisältötyyppiä:

- Post type: `digital_magazine`
- Julkinen arkisto: `/digilehdet/`
- Lehti: yläkohde, jolla ei ole vanhempaa
- Juttu: saman sisältötyypin alakohde, jonka vanhemmaksi valitaan lehti

URL-rakenne:

- lehti: `/digilehdet/{lehden-polku}/`
- juttu: `/digilehdet/{lehden-polku}/{jutun-polku}/`

Sisällysluetteloa ei tallenneta erilliseen kenttään. Lehden sivu muodostaa sisällysluettelon automaattisesti julkaistuista lapsijutuista.

## Lehden luominen

1. Avaa WordPress-adminissa `Digilehdet`.
2. Valitse `Lisää uusi`.
3. Kirjoita lehden nimi otsikoksi.
4. Jätä vanhempi tyhjäksi.
5. Kirjoita lehden johdanto tai kuvaus editoriin.
6. Lisää halutessasi ote ja artikkelikuva.
7. Julkaise lehti.

## Jutun lisääminen lehteen

1. Avaa `Digilehdet > Lisää uusi`.
2. Kirjoita jutun otsikko.
3. Valitse sivupalkissa vanhemmaksi oikea lehti.
4. Kirjoita jutun varsinainen sisältö editoriin.
5. Aseta `Järjestys` / `Menu order` -kenttään numero, jos haluat määrittää sisällysluettelon järjestyksen.
6. Julkaise juttu.

Suositeltu järjestysnumerointi on esimerkiksi `10`, `20`, `30`. Silloin väliin voi lisätä myöhemmin uusia juttuja ilman kaikkien numeroiden muuttamista.

## Julkinen näkymä

Lehden sivulla näkyy:

- lehden otsikko
- lehden kuvaus tai johdanto
- automaattinen sisällysluettelo

Jutun sivulla näkyy:

- linkki takaisin lehteen
- jutun otsikko ja sisältö
- edellinen ja seuraava juttu samassa lehdessä, jos niitä on

Ensimmäisellä jutulla ei näytetä edellinen-linkkiä. Viimeisellä jutulla ei näytetä seuraava-linkkiä.

## Testaus

Perustestissä varmista:

- `/digilehdet/` näyttää vain lehdet, ei yksittäisiä juttuja
- lehden sivu näyttää jutut sisällysluettelossa oikeassa järjestyksessä
- jutun sivu näyttää takaisin-linkin lehteen
- jutun edellinen/seuraava-navigaatio toimii
- mobiilinäkymässä sisällysluettelo ja lukunäkymä pysyvät luettavina
