# Maksu- ja toimitusehdot

Tämä dokumentti on versionhallittu lähdekopio verkkokaupan **Maksu- ja
toimitusehdot**-sivusta. Sivu itse on WordPress-sisältöä (ei teemakoodia),
joten sitä ei voi editoida gitissä — tämä tiedosto toimii muutoshistoriana ja
pohjana, joka kopioidaan WP-adminiin julkaisun yhteydessä.

> **Luonnos — tarkistettava ennen julkaisua.** Ei korvaa juristin arviota.

## Tausta ja lakiperusteet (ei julkaistavaa osaa)

Tarkistettu kuluttajansuojalain (38/1978) 6 luvun (koti- ja etämyynti) mukaan
Finlexin konsolidoidusta lakitekstistä. Keskeiset havainnot edelliseen
julkaistuun versioon verrattuna:

- **KSL 6:5 §** — ehto, joka poikkeaa 6 luvusta kuluttajan vahingoksi, on
  **mitätön**. Ympäripyöreä "tapauskohtaisesti"-muotoilu ilman lakiperustetta
  ei ole turvallinen tapa rajata peruuttamisoikeutta.
- **Tapahtumamaksut**: KSL 6:16 §:n 11-kohta poistaa peruuttamisoikeuden
  vapaa-ajanpalvelusta, kun sopimus edellyttää suoritusta **määrättynä
  ajankohtana** — osuu suoraan tapahtumailmoittautumisiin. Tämä on nimetty
  tekstissä.
- **Digitaaliset tuotteet (digilehdet)**: KSL 6:15 § 2 mom ja 6:24 § 2 mom
  edellyttävät kuluttajan **nimenomaista ennakkosuostumusta ja hyväksyntää
  peruuttamisoikeuden menettämisestä** kassalla, ennen kuin sähköinen
  toimitus kesken peruuttamisajan poistaa oikeuden. Teeman koodista
  (`inc/`) ei löytynyt tällaista suostumusmekanismia checkoutissa. **Päätös
  (#476): suostumus-valintaruutu toteutetaan kassalle** — seurantana on
  oma, pienempi koodiketiketti. Kunnes se on tehty, teksti **ei** väitä
  digilehtien olevan poikkeuksen piirissä: ne noudattavat oletusarvoista
  14 vrk:n peruuttamisoikeutta, ja alla oleva "ellei... vahvisteta
  suostumustasi" -muotoilu on kirjoitettu niin, että se pysyy paikkansa
  pitävänä myös ennen ko. koodimuutosta (poikkeus ei koskaan laukea, koska
  suostumusaskelta ei vielä kysytä) ja aktivoituu automaattisesti sen
  valmistuttua. *(Sivuhuomio: KSL 6:16 § 9-kohta poistaa
  peruuttamisoikeuden "yksittäisen aikakausjulkaisun toimittamisesta" —
  voisi periaatteessa koskea yksittäistä digilehteä, mutta soveltuvuus
  verkkosisältöön on epäselvä ja tarkistettava juristilta ennen kuin siihen
  nojataan.)*
- **Jäsenmaksut**: mikään KSL 6:16 §:n poikkeuslista ei suoraan mainitse
  jäsenmaksua, eikä koodissa ole 15/16 §:n edellyttämää
  suostumusmekanismia. Syvempi avoin kysymys — onko yhdistyksen jäsenmaksu
  ylipäätään KSL 6 luvun soveltamisalaan kuuluva kulutushyödykesopimus vai
  yhdistyslain piiriin kuuluva jäsenyysasia — jää auki; teksti pysyy siksi
  varovaisena ("voi olla rajoitettu... arvioidaan tapauskohtaisesti"),
  samassa hengessä kuin nykyinen live-teksti.
- Toteutunut itsepalvelu-peruutus (14 vrk, `/oma-tili/peruuta-tilaus/`) on
  kuvattu [`docs/woocommerce-peruutus.md`](woocommerce-peruutus.md):ssä —
  tämän sivun teksti on yhdenmukaistettu sen kanssa.
- Lisätty puuttuvat ennakkotiedot (KSL 6:9 §:n 3–4 kohta), jotka eivät
  näkyneet aiemmassa versiossa: puhelinnumero 040 592 2842 ja osoite
  Tyrmynniementie 71, 74595 Runni — sama osoite kuin rekisterinpitäjän
  osoite [`docs/tietosuoja.md`](tietosuoja.md):ssä.

## Julkaisu WordPress-adminissa

1. Etsi sivu (todennäköisesti *Sivut*-listalta hakusanalla "Maksu- ja
   toimitusehdot" tai kaupan asetuksista linkitetty sivu).
2. Korvaa sisältö alla olevalla, täydennetyllä tekstillä.
3. Tarkista linkitys peruutussivulle (`/oma-tili/peruuta-tilaus/`).
4. Julkaise.

---

# Maksu- ja toimitusehdot

## Myyjä

**Rytkösten sukuseura ry**
Sähköposti: info@rytkoset.net
Puhelin: 040 592 2842
Y-tunnus: 2081792-3
Osoite: Tyrmynniementie 71, 74595 Runni

## Tuotteet ja hinnat

Verkkokaupassa voidaan myydä jäsenmaksuja, tapahtumamaksuja, digitaalisia
tuotteita sekä fyysisiä tuotteita, kuten lehtiä ja t-paitoja.

Hinnat ilmoitetaan tuotteen yhteydessä. Mahdolliset toimituskulut näytetään
kassalla ennen maksamista.

## Maksutavat

Maksut välittää Mollie. Käytettävissä olevat maksutavat näkyvät kassalla.
Tilaus käsitellään, kun maksu on vastaanotettu tai vahvistettu.

## Toimitus

Fyysiset tuotteet toimitetaan asiakkaan antamaan osoitteeseen tai muulla
tuotteen yhteydessä ilmoitetulla tavalla.

Toimitusaika riippuu tuotteesta ja toimitustavasta. Jos toimituksessa on
viivettä, sukuseura pyrkii ilmoittamaan siitä asiakkaalle.

## Digitaaliset tuotteet

Digitaaliset tuotteet (esim. digilehdet) toimitetaan tai avataan käyttöön
tuotteen yhteydessä ilmoitetulla tavalla.

Digitaalisiin tuotteisiin sovelletaan samaa 14 vuorokauden
peruuttamisoikeutta kuin muihinkin verkkokaupan tuotteisiin (ks. alla
"Peruuttaminen ja palautukset"), ellei tuotteen yhteydessä erikseen pyydetä
ja vahvisteta suostumustasi sisällön välittömään toimittamiseen ennen
peruuttamisajan päättymistä.

## Jäsenmaksut

Jäsenmaksu koskee valittua jäsenyystyyppiä. Jäsenmaksu ei ole fyysinen
tuote. Jäsenmaksun peruutus- ja palautuskäytäntö on kuvattu alla kohdassa
"Peruuttaminen ja palautukset".

## Tapahtumamaksut

Tapahtumamaksu koskee valittua tapahtumaa ja osallistujamäärää. Koska
tapahtuma järjestetään määrättynä ajankohtana, tapahtumamaksuun ei
kuluttajansuojalain (38/1978, 6:16 § 11 kohta) mukaan sovelleta
peruuttamisoikeutta. Tapahtuman mahdolliset erityisehdot (esim. ilmoittautumisen
määräaika) ilmoitetaan tapahtuman tai tuotteen yhteydessä.

## Peruuttaminen ja palautukset

Voit peruuttaa tilauksen itsepalveluna kohdassa *Oma tili → Tilaukset*
14 vuorokauden kuluessa tilauksen tekemisestä. Maksamaton tilaus
peruuntuu heti; maksettu tilaus käsitellään manuaalisesti ja saat
vahvistuksen peruutuspyynnön vastaanottamisesta.

Fyysisillä tuotteilla on kuluttajansuojalain mukainen palautusoikeus.
Palautettavan tuotteen tulee olla käyttämätön ja myyntikuntoinen. Asiakas
vastaa palautuksen järjestämisestä, ellei palautus johdu virheestä
tuotteessa tai toimituksessa.

Jäsenmaksujen peruutusoikeus voi olla rajoitettu, jos jäsenyyteen liittyvä
palvelu on jo alkanut; tämä arvioidaan tapauskohtaisesti peruutuspyynnön
yhteydessä. Tapahtumamaksuihin ei sovelleta peruuttamisoikeutta yllä
kuvatun mukaisesti. Digitaalisten tuotteiden peruuttamisoikeus on kuvattu
edellä kohdassa "Digitaaliset tuotteet".

## Virheet ja reklamaatiot

Jos tuotteessa, maksussa tai toimituksessa on virhe, ota yhteyttä
mahdollisimman pian.

## Yhteydenotot

Maksuihin, toimituksiin, jäsenyyteen ja tapahtumiin liittyvissä
kysymyksissä ota yhteyttä:

info@rytkoset.net
