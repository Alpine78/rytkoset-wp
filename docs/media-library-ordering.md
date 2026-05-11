# Mediakirjaston ja albumien kuvajärjestys

Albumien ylläpidossa käytetään WordPressin omaa mediakirjastoa ja FileBird-kansioita. Kuvat lisätään albumiin **Gutenberg-sisältöeditorin Gallery-lohkona**. Maksullisia lisäosia (FileBird Pro, ACF Pro) ei tarvita.

## Oletusjärjestys

Teema asettaa adminin mediakirjaston ja median valintamodaalin oletusjärjestykseksi nousevan tiedostonimijärjestyksen. Tämä auttaa valitsemaan albumin kuvat oikeassa järjestyksessä.

Julkinen albumisivu järjestää Gallery-lohkon kuvat automaattisesti WordPressin liitteen otsikon (`post_title`) mukaan nousevasti ennen renderöintiä. WordPress asettaa otsikon tiedostonimen perusteella latauksen yhteydessä. Lightbox (PhotoSwipe) toimii Gallery-lohkon kuvilla automaattisesti.

Käytännössä tämä toimii oikein, kun kuvat on nimetty nollatäytetyillä juoksevilla nimillä, esimerkiksi:

- `IMG_0001.jpg`
- `IMG_0002.jpg`
- `IMG_0003.jpg`

## Mitä tämä tarkoittaa ylläpidossa

- Julkisen albumin järjestys määräytyy tiedostonimen mukaan, ei valintajärjestyksestä tai Gallery-lohkon sisäisestä järjestyksestä.
- Mediakirjaston haku, päivämääräsuodatus ja tiedostotyyppisuodatus säilyvät käytössä.
- Jos ylläpitäjä valitsee mediakirjastossa erikseen jonkin muun sarakejärjestyksen, WordPressin oma valinta voi ohittaa adminin oletusjärjestyksen kyseisessä näkymässä.

## Ylläpitomalli

1. Nimeä albumikuvat ennen latausta nollatäytettyyn järjestykseen.
2. Lataa kuvat oikeaan FileBird-kansioon.
3. Lisää albumin sisältöeditoriin **Gallery-lohko** ja valitse kuvat mediakirjastosta (FileBird-kansiosuodatus helpottaa valintaa).
4. Tarkista julkinen albumisivu ennen julkaisua.

Jos yksittäinen albumi tarvitsee tiedostonimistä poikkeavan tarinallisen järjestyksen, sille kannattaa tehdä erillinen jatkotiketti. Nykyinen MVP pitää albumien järjestyksen toistettavana ilman maksullisia lisäosia.
