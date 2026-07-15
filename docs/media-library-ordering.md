# Mediakirjaston ja albumien kuvajärjestys

Albumien ylläpidossa käytetään WordPressin omaa mediakirjastoa ja **Real Media
Library** -kansioita. Kansioita käytetään vain adminin kuvien ryhmittelyyn:
teeman julkinen albumi, kuvien järjestys ja PhotoSwipe eivät riipu Real Media
Libraryn omista rajapinnoista.

Uusien albumien kuvat lisätään ensisijaisesti Gutenberg-editorin
**Galleria-lohkona**. Teema tukee edelleen vanhojen albumien ACF
`gallery_images` -kenttää, mutta uusi albumi ei tarvitse ACF Prota.

## Oletusjärjestys

Teema asettaa adminin mediakirjaston ja median valintamodaalin oletusjärjestykseksi nousevan tiedostonimijärjestyksen. Tämä auttaa valitsemaan albumin kuvat oikeassa järjestyksessä.

Julkinen albumisivu järjestää sekä Galleria-lohkon että vanhan ACF-gallerian
kuvat automaattisesti WordPressin liitteen otsikon (`post_title`) mukaan
nousevasti ennen renderöintiä. WordPress asettaa otsikon tiedostonimen
perusteella latauksen yhteydessä. Lightbox (PhotoSwipe) toimii molemmilla
kuvalähteillä automaattisesti.

Käytännössä tämä toimii oikein, kun kuvat on nimetty nollatäytetyillä juoksevilla nimillä, esimerkiksi:

- `IMG_0001.jpg`
- `IMG_0002.jpg`
- `IMG_0003.jpg`

## Mitä tämä tarkoittaa ylläpidossa

- Julkisen albumin järjestys määräytyy tiedostonimen mukaan, ei valintajärjestyksestä, Real Media Library -kansiosta tai Galleria-lohkon sisäisestä järjestyksestä.
- Mediakirjaston haku, päivämääräsuodatus ja tiedostotyyppisuodatus säilyvät käytössä.
- Jos ylläpitäjä valitsee mediakirjastossa erikseen jonkin muun sarakejärjestyksen, WordPressin oma valinta voi ohittaa adminin oletusjärjestyksen kyseisessä näkymässä.

## Ylläpitomalli

1. Nimeä albumikuvat ennen latausta nollatäytettyyn järjestykseen.
2. Lataa kuvat oikeaan Real Media Library -kansioon.
3. Lisää albumin sisältöeditoriin **Galleria-lohko** ja valitse kuvat
   mediakirjastosta. Real Media Libraryn kansiosuodatus helpottaa valintaa.
4. Tarkista julkinen albumisivu ennen julkaisua.

Jos yksittäinen albumi tarvitsee tiedostonimistä poikkeavan tarinallisen järjestyksen, sille kannattaa tehdä erillinen jatkotiketti. Nykyinen MVP pitää albumien järjestyksen toistettavana ilman maksullisia lisäosia.
