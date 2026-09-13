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

## Albumin osiot ja suodatus (#677)

Kun albumissa on useampi erillinen tilaisuus (esim. perjantain buffet-illallinen
ja lauantain juhla) tai kuvien lisäksi YouTube-videoita, kävijä voi julkisella
albumisivulla valita yläreunan **"Kaikki / osio / Videot"** -valinnalla, mitä
albumista näytetään. Tämä ei vaadi mitään uutta kenttää — teema päättelee
osiot suoraan editorissa kirjoitetusta sisällöstä (`assets/js/album-filter.js`),
eikä vanhoja, yhden gallerian albumeja tarvitse muuttaa mitenkään.

### Näin teet albumin, jossa on useampi osio

1. Kirjoita ensin **otsikko (H2)** tilaisuudelle, esim. "Perjantain
   buffet-illallinen". Käytä lohkovalikon pikamallia **"Albumin osio"** — se
   lisää valmiiksi otsikon ja tyhjän Galleria-lohkon, joten rakenteen ei
   tarvitse muistaa ulkoa.
2. Lisää otsikon **heti perään** kyseisen tilaisuuden **Galleria-lohko** ja
   valitse siihen vain sen tilaisuuden kuvat.
3. Toista sama seuraavalle tilaisuudelle: uusi H2-otsikko + oma Galleria-lohko.
4. Jos albumissa on myös YouTube-videoita (ACF:n "Videot"-kenttä), ne
   muodostavat automaattisesti oman **Videot**-valintansa — niitä ei tarvitse
   otsikoida erikseen.

Otsikon teksti on suoraan se, mitä kävijä näkee valintanapissa (esim.
"Perjantain buffet-illallinen (32)", missä luku on osion kuvamäärä).

### Milloin suodatin näkyy

Suodatin ilmestyy vain, jos albumista löytyy **vähintään kaksi** valittavaa
kokonaisuutta (esim. kaksi otsikoitua osiota, tai yksi osio + videot). Yhden
gallerian albumi ilman videoita näkyy siis täsmälleen
ennallaan, eikä tyhjää tai turhaa suodatinta tule näkyviin.

Otsikoimaton sisältö (esim. johdantoteksti tai galleria ennen ensimmäistä
otsikkoa) näkyy aina, riippumatta suodattimen valinnasta.

### Jaettava linkki tiettyyn osioon

Valinta tallentuu osoitteeseen kyselyparametrina, esim.
`?nayta=lauantain-juhla`, joten yhden tilaisuuden kuvat voi jakaa suoraan
linkkinä. Jos otsikon tekstiä muutetaan jälkikäteen, vanha jaettu linkki ei
enää osu osioon (näyttää tällöin kaiken albumin sisällön) — tämä kannattaa
huomioida, jos linkkiä on jo jaettu esim. sähköpostissa.

Samannimiset otsikot saavat linkeissä juoksevan päätteen (`-2`, `-3`).
Nimet `kaikki`, `videot` ja `kuvat` ovat varattuja: samannimisen H2-osion
linkki saa myös päätteen. Aksentit poistetaan (esim. ä → a, é → e).
Muut kyselyparametrit ja osoitteen `#`-osa säilyvät valintaa vaihdettaessa.
Jos `#kuva=ID` viittaa piilotettuun kuvaan, näkymäksi vaihtuu Kaikki.
Jos sama kuva on myös valitussa osiossa, valinta säilyy ja kuva avautuu siitä.

Pelkkää tekstiä sisältävät H2-osiot näkyvät aina. Lukumäärä sisältää osion
kuvat ja upotukset; videon toisto pysäytetään, kun sen osio piilotetaan.

Kun **Kaikki** on valittuna, kuvan suurennusnäkymän nuolilla voi selata albumin
valokuvat läpi osioiden rajojen yli siinä järjestyksessä kuin kuvat näkyvät
sivulla. Yhden osion valinnassa selaus pysyy kyseisen osion kuvissa. Jos sama
kuva on sijoitettu useaan osioon, se näkyy myös Kaikki-selauksessa jokaisessa
esiintymiskohdassa. Yksittäisen kuvan `#kuva=ID`-jakolinkki avaa ensimmäisen
näkyvän esiintymän, koska liitteen ID on sama kaikissa kohdissa. Videot eivät
kuulu kuvien suurennusnäkymään.

### Rajaukset

- Suodatin ei vielä osaa liittää yksittäistä videota tiettyyn tilaisuuteen —
  ACF:n Videot-kentän videot muodostavat yhden yhteisen "Videot"-valinnan.
  Editorin H2-osion sisään lisätty upotus kuuluu kyseiseen osioon.
