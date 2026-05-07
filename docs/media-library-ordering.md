# Mediakirjaston kuvien järjestys

Albumien ylläpidossa käytetään WordPressin omaa mediakirjastoa ja ACF:n galleriakenttää. Maksullista FileBird Pro -järjestelyä ei käytetä.

## Oletusjärjestys

Teema asettaa adminin mediakirjaston oletusjärjestykseksi tiedostonimestä syntyvän liitteen otsikon nousevassa järjestyksessä.

Käytännössä tämä toimii oikein, kun kuvat on nimetty nollatäytetyillä juoksevilla nimillä, esimerkiksi:

- `IMG_0001`
- `IMG_0002`
- `IMG_0003`

Sama oletusjärjestys asetetaan myös median valintamodaaliin, jota käytetään albumin kuvien valinnassa.

## Mitä tämä ei muuta

- Julkisen albumin kuvajärjestystä ei muuteta automaattisesti.
- ACF:n galleriakenttään jo tallennettu kuvajärjestys säilyy ennallaan.
- Mediakirjaston haku, päivämääräsuodatus ja tiedostotyyppisuodatus säilyvät käytössä.
- Jos ylläpitäjä valitsee mediakirjastossa erikseen jonkin muun sarakejärjestyksen, WordPressin oma valinta ohittaa teeman oletuksen.

## Ylläpitomalli

1. Nimeä albumikuvat ennen latausta nollatäytettyyn järjestykseen.
2. Lataa kuvat oikeaan FileBird-kansioon.
3. Lisää kuvat albumin ACF-galleriakenttään mediakirjastosta.
4. Tarkista albumin kuvajärjestys ennen julkaisua.

Jos kuvia pitää järjestää käsin julkisessa albumissa, tee järjestely albumin omassa galleriakentässä. Mediakirjaston oletusjärjestys auttaa valintaa, mutta ei korvaa albumikohtaista tarkistusta.
