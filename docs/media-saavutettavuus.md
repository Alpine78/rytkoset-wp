# Median saavutettavuus — ylläpitäjän ohje

Lyhyt ohje siitä, miten kuvien ja videoiden lisääminen säilyttää sivuston
saavutettavuustason (WCAG 2.1 AA).

## Kuvien alt-tekstit

WordPressin mediakirjastossa jokaiselle kuvalle voi täyttää **vaihtoehtoisen tekstin**
(*"Alt-teksti"*). Sen lukee ruudunlukija sokeille käyttäjille, ja se näkyy myös, jos
kuva ei lataudu.

### Mitä kirjoittaa?

- **Kuvaile, mitä kuvassa näkyy** — lyhyesti (yleensä 5–15 sanaa).
- Älä aloita "Kuva …" tai "Valokuva …" — ruudunlukija sanoo sen jo.
- Jos kuvassa on ihmisiä, mainitse keitä ja mitä he tekevät, jos tiedossa.
- **Koristeellinen kuva ilman tietosisältöä:** jätä alt-teksti tyhjäksi (theme
  tulkitsee sen koristeeksi automaattisesti).

### Esimerkkejä (sukuseuran kuvat)

- ✅ "Sukukokouksen osallistujat kokoontuneet ryhmäkuvaan kesäteatterin lavalla, 2024"
- ✅ "Vanha mustavalkokuva nuoresta Viljo Rytkösestä puvun kanssa"
- ❌ "Kuva sukukokouksesta" *(liian yleinen — kuvaile mitä siellä tapahtuu)*
- ❌ "IMG_2024_07_12.jpg" *(tiedostonimi ei kuvaa kuvaa)*

### Mitä theme tekee, jos alt jää tyhjäksi?

Galleriakuville teema käyttää tämän fallback-järjestyksen
([`rytkoset_theme_get_gallery_image_alt()`](../wp-content/themes/rytkoset-theme/functions.php)):

1. Kutsuvan näkymän eksplisiittinen alt-arvo, jos sellainen annetaan (vanhan
   ACF-gallerian kuvarivi)
2. Mediakirjaston `_wp_attachment_image_alt` -kenttä
3. Kuvan **kuvateksti** (caption), jos täytetty
4. Muutoin tyhjä → koristekuva

Tämä on **varajärjestelmä, ei korvaava ratkaisu**. Hyvä alt-teksti pitää aina
kirjoittaa mediakirjastoon, jotta ruudunlukija saa kuvauksen kuvasta — ei vain
sen yhteydestä.

## Videot

Albumin upotetuille YouTube-videoille teema lisää automaattisesti `title`-attribuutin
muodossa "Video albumissa {albumin nimi}" tai "Video N/M albumissa {albumin nimi}",
jos videoita on useita.

Mahdolliset tarkennukset:
- **Tekstitys (CC):** YouTubessa ladatut videot kannattaa tekstittää YouTube Studiossa.
  Automaattitekstitykset eivät täytä WCAG 1.2.2 -kriteeriä — manuaalinen tekstitys
  on suositeltava ratkaisu.
- **Audiokuvaus / transkriptio:** vain harvoin käytössä; vaaditaan vain, jos videossa
  on merkittävää visuaalista sisältöä, jota puhe ei selitä.

## Galleria näppäimistöllä

PhotoSwipe (kuvan suurennusnäkymä) toimii näppäimistöllä ilman erillisiä toimia:

| Näppäin | Toiminto |
| --- | --- |
| **Enter** kuvaruudukossa | Avaa kuvan suuressa näkymässä |
| **← / →** | Edellinen / seuraava kuva |
| **Esc** | Sulkee suurennuksen |
| **Tab / Shift+Tab** | Siirtyy työkalupalkin painikkeisiin (mm. *Kopioi linkki*, *Sulje*) |

## Albumin osiosuodatin (#677)

Kun albumissa on vähintään kaksi valittavaa osiota (ks.
[`docs/media-library-ordering.md`](media-library-ordering.md) osioiden
tekemisestä), albumin yläosaan ilmestyy "Kaikki / osio / Videot" -valinta.
Suodattimen saavutettavuutta tukevat seuraavat ratkaisut:

- Jokainen valinta on oma `<button>`-elementti, joten se on tavoitettavissa
  Tab-näppäimellä ja aktivoitavissa Enterillä/välilyönnillä ilman erillistä
  koodia.
- Valittu osio merkitään `aria-pressed="true"`-attribuutilla **ja** pienellä
  valintamerkillä painikkeen tekstin edessä — tila ei siis välity pelkällä
  värillä.
- Näkyvän tilan vaihtuminen ilmoitetaan ruudunlukijalle `aria-live="polite"`
  -alueella (esim. "Näytetään: Lauantain juhla, 85 kuvaa."), joten muutos
  huomataan myös ilman näköä.
- Piilotetut osiot saavat HTML:n `hidden`-attribuutin, joten niiden linkit ja
  kuvat poistuvat kokonaan Tab-järjestyksestä eivätkä jää "haamuiksi" ruudulle.
- Suodatin vaatii JavaScriptin toimiakseen. Ilman sitä albumi näyttää kaiken
  sisältönsä kuten ennen suodattimen käyttöönottoakin — mitään ei siis jää
  saavuttamattomiin.

### Toteutuksen tarkistus 12.9.2026

Paikallisessa Docker-WordPressissä tarkistettu Playwrightilla kahden gallerian
ja kahden videon testialbumi sekä aiempi yhden gallerian albumi:

- Valinnat ja lukumäärät, johdannon näkyminen, `aria-pressed` ja live-ilmoitus.
- Enter-aktivointi, fokuksen säilyminen sekä näkyvä fokus 390 px leveydellä
  vaaleassa ja tummassa teemassa; ei vaakasuuntaista ylivuotoa.
- `?nayta=` ja muiden URL-osien säilyminen, tuntemattoman valinnan palautuminen
  Kaikki-tilaan sekä piilotetun kuvan PhotoSwipe-jakolinkki.
- Samassa albumissa toistuva kuva avautuu näkyvästä galleriasta; lightboxin
  kuvasarja pysyy kyseisen Galleria-lohkon sisällä.
- Videon piilotus palauttaa iframe-lähteen; ilman JavaScriptiä kaikki sisältö
  näkyy ja suodatin pysyy piilossa.

Lohkomallin rekisteröinti ja H2 + Galleria -rakenne tarkistettu WordPressin
lohkomallirekisteristä. Varsinainen ruudunlukijatesti ja julkaisemisen jälkeinen
`dev.rytkoset.net`-tarkistus jäävät tehtäviksi; muutoksia ei julkaistu tässä
paikallisessa tarkistuksessa.
