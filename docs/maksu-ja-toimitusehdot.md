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
- **Tapahtumamaksut**: KSL 6:16 §:n 1 momentin 11 kohta poistaa
  peruuttamisoikeuden vapaa-ajanpalvelusta, kun sopimus edellyttää suoritusta
  **määrättynä ajankohtana** — osuu suoraan tapahtumailmoittautumisiin. Tämä on
  nimetty tekstissä.
- **Digitaaliset tuotteet (digilehdet)**: KSL 6:15 § 2 mom ja 6:24 § 2 mom
  edellyttävät kuluttajan **nimenomaista ennakkosuostumusta ja hyväksyntää
  peruuttamisoikeuden menettämisestä** kassalla, ennen kuin sähköinen
  toimitus kesken peruuttamisajan poistaa oikeuden. Teeman koodista
  ei aiemmin löytynyt tällaista suostumusmekanismia checkoutissa (#476). **Nyt
  toteutettu (#477):** WooCommerce-kassalle lisättiin pakollinen
  suostumus-valintaruutu, joka näkyy vain kun ostoskorissa on digilehteen
  linkitetty tuote — ks. `inc/woocommerce-digital-magazine.php` ja
  [`docs/digital-magazines.md`](digital-magazines.md):n "Peruuttamisoikeuden
  menettämisen suostumus kassalla" -osio. Alla oleva "ellei... vahvisteta
  suostumustasi" -muotoilu kuvaa siis nyt toteutunutta käyttäytymistä: ilman
  rastia digilehti noudattaa oletusarvoista 14 vrk:n peruuttamisoikeutta,
  rastilla peruuttamisoikeus päättyy heti lukuoikeuden myöntämiseen (kuten
  #477:n perässä mainittu itsepalveluperuutuksen kytkentä toteaa, tätä ei
  vielä tarkisteta automaattisesti peruutuspainikkeen puolella — ylläpitäjä
  tarkistaa suostumuksen tilaukselta manuaalisen käsittelyn yhteydessä).
  *(Sivuhuomio: KSL 6:16 §:n 1 momentin 9 kohta poistaa peruuttamisoikeuden
  "yksittäisen sanoma- tai aikakauslehden tai aikakausjulkaisun
  toimittamisesta" — voisi periaatteessa koskea yksittäistä digilehteä, mutta
  soveltuvuus verkkosisältöön on epäselvä ja tarkistettava juristilta ennen
  kuin siihen nojataan.)*
- **Jäsenmaksut**: mikään KSL 6:16 §:n poikkeuslista ei suoraan mainitse
  jäsenmaksua. Julkaistava teksti, tilausvahvistuksen peruuttamisohje (#573)
  ja itsepalveluperuutuksen huomautukset kohtelevat jäsenmaksua siksi
  yhdenmukaisesti tavallisen 14 päivän peruuttamisoikeuden piiriin kuuluvana
  etämyyntituotteena. Tämä on KSL 6:5 §:n kannalta turvallisin linja, koska
  ehdot eivät voi poiketa 6 luvusta kuluttajan vahingoksi. Syvempi avoin
  kysymys — onko yhdistyksen jäsenmaksu ylipäätään KSL 6 luvun soveltamisalaan
  kuuluva kulutushyödykesopimus vai yhdistyslain piiriin kuuluva jäsenyysasia
  — jää edelleen juristin vahvistettavaksi; jos jäsenmaksu rajataan 6 luvun
  ulkopuolelle, linjaus voidaan kiristää.
- Toteutunut itsepalvelu-peruutus (`/oma-tili/peruuta-tilaus/`) on kuvattu
  [`docs/woocommerce-peruutus.md`](woocommerce-peruutus.md):ssä. Fyysisen
  tuotteen 14 vuorokauden määräaika alkaa vastaanottamisesta; koska sivusto
  ei tallenna vastaanottopäivää, ylläpitäjä tarkistaa määräajan manuaalisesti.
- Lisätty puuttuvat ennakkotiedot (KSL 6:9 §:n 1 momentin 3 ja 4 kohta),
  jotka eivät näkyneet aiemmassa versiossa: puhelinnumero 040 592 2842 ja
  osoite Tyrmynniementie 71, 74595 Runni — sama osoite kuin rekisterinpitäjän
  osoite [`docs/tietosuoja.md`](tietosuoja.md):ssä.
- **Juridisten tekstien tarkistus 19.7.2026 (#571)**, viittaukset varmennettu
  Finlexin konsolidoidusta lakitekstistä: "käyttämätön ja myyntikuntoinen"
  -palautusehto korvattu KSL 6:18 §:n mukaisella arvonalennusmallilla, koska
  ehdoton kuntovaatimus poikkeaisi 6 luvusta kuluttajan vahingoksi ja olisi
  KSL 6:5 §:n nojalla mitätön (kuluttaja saa tutkia tuotteen kuten
  myymälässä; laajemmasta käytöstä seuraa arvonalennusvastuu, ei
  peruuttamisoikeuden menetys). Lisätty hyvityksen palautuksen 14 päivän
  määräaika ja pidätysoikeus (KSL 6:17 §:n 3 momentti) sekä digituotteen
  peruuttamisajan alkamishetki sopimuksen tekemisestä (KSL 6:14 §).
  Julkaistavan tekstin KSL-viittaus kirjoitettu auki muotoon "6 luvun 16 §:n
  1 momentin 11 kohta". Digisisällön suostumus näkyy tilausvahvistuksessa ja
  vahvistussähköpostissa (#491:n kenttäsuodattimet), mikä toteuttaa KSL
  6:13 §:n 3 momentin vahvistusvaatimuksen. Peruuttamislomakkeen ja -ohjeen
  (KSL 6:9 §:n 3 momentti, OM:n asetus) tarjoaminen jäi tässä vaiheessa
  #573:ssa toteutettavaksi.
- **Virhevastuu ja riidanratkaisu 20.7.2026 (#574)**: julkaistavaan tekstiin
  lisätty yleisluonteinen maininta myyjän lakisääteisestä virhevastuusta sekä
  tieto siitä, etteivät ehdot rajoita kuluttajan lakisääteisiä oikeuksia.
  Riidanratkaisun etenemisjärjestys ja linkit on tarkistettu Kilpailu- ja
  kuluttajaviraston kuluttajaneuvonnan sekä kuluttajariitalautakunnan
  ajantasaisilta verkkosivuilta.
- **Peruuttamisohje ja -lomake 20.7.2026 (#573)**: julkaistavaan tekstiin
  lisätty [oikeusministeriön asetuksen 110/2014](https://www.finlex.fi/api/media/statute/94081/mainPdf/main.pdf)
  liitteiden I ja II nykyisten,
  [asetuksella 754/2022](https://www.finlex.fi/api/media/statute/14014/mainPdf/main.pdf)
  muutettujen mallien mukainen täytetty peruuttamisohje ja -lomake.
  Tuoteryhmärajaukset vastaavat #571:n ehtoja: määrättynä
  ajankohtana suoritettavat tapahtumapalvelut sekä nimenomaisen suostumuksen,
  hyväksynnän ja vahvistuksen jälkeen toimitettu digisisältö rajataan ulos.
  Sama pysyvä sisältö lisätään WooCommercen tilausvahvistussähköposteihin
  niissä tilauksissa, joissa vähintään yhdellä tuotteella on
  peruuttamisoikeus.

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

Maksut välittää Paytrail. Käytettävissä olevat maksutavat näkyvät kassalla.
Tilaus käsitellään, kun maksu on vastaanotettu tai vahvistettu.

## Maksupalvelutarjoaja

Maksunvälityspalvelun toteuttajana ja maksupalveluntarjoajana toimii Paytrail
Oyj (2122839-7) yhteistyössä suomalaisten pankkien ja luottolaitosten kanssa.
Paytrail Oyj näkyy maksun saajana tiliotteella tai korttilaskulla ja välittää
maksun kauppiaalle. Paytrail Oyj:llä on maksulaitoksen toimilupa.
Reklamaatiotapauksissa pyydämme ottamaan ensisijaisesti yhteyttä tuotteen
toimittajaan.

Paytrail Oyj, y-tunnus: 2122839-7

Innova 2

Lutakonaukio 7

40100 Jyväskylä

Puhelin: 0207 181830

[paytrail.com/kuluttaja/tietoa-maksamisesta](https://www.paytrail.com/kuluttaja/tietoa-maksamisesta)

## Toimitus

Fyysiset tuotteet toimitetaan asiakkaan antamaan osoitteeseen tai muulla
tuotteen yhteydessä ilmoitetulla tavalla.

Postitettavat tuotteet käsitellään 1–3 arkipäivässä. Postin arvioitu
kuljetusaika lähettämisestä on 2–5 arkipäivää. Jos toimituksessa on viivettä,
sukuseura pyrkii ilmoittamaan siitä asiakkaalle.

## Digitaaliset tuotteet

Digitaaliset tuotteet (esim. digilehdet) toimitetaan tai avataan käyttöön
tuotteen yhteydessä ilmoitetulla tavalla.

Digitaalisiin tuotteisiin sovelletaan 14 vuorokauden peruuttamisoikeutta,
joka lasketaan sopimuksen tekemisestä (ks. alla "Peruuttaminen ja
palautukset"), ellei tuotteen yhteydessä erikseen pyydetä ja vahvisteta
suostumustasi sisällön välittömään toimittamiseen ennen peruuttamisajan
päättymistä.

## Jäsenmaksut

Jäsenmaksu koskee valittua jäsenyystyyppiä. Jäsenmaksu ei ole fyysinen
tuote. Jäsenmaksun peruutus- ja palautuskäytäntö on kuvattu alla kohdassa
"Peruuttaminen ja palautukset".

## Tapahtumamaksut

Tapahtumamaksu koskee valittua tapahtumaa ja osallistujamäärää. Koska
tapahtuma järjestetään määrättynä ajankohtana, tapahtumamaksuun ei
kuluttajansuojalain (38/1978) 6 luvun 16 §:n 1 momentin 11 kohdan mukaan
sovelleta peruuttamisoikeutta. Tapahtuman mahdolliset erityisehdot (esim. ilmoittautumisen
määräaika) ilmoitetaan tapahtuman tai tuotteen yhteydessä.

## Peruuttaminen ja palautukset

Voit tehdä peruutus- tai palautuspyynnön itsepalveluna. Jos teit tilauksen
käyttäjätilillä, toiminto löytyy kohdasta *Oma tili → Tilaukset*. Ilman
käyttäjätiliä tehdyn tilauksen henkilökohtainen **Peruuta tilaus** -linkki on
tilausvahvistuksessa. Tarkista tilauksen ja peruuttajan tiedot erillisellä
vahvistussivulla ja lähetä ilmoitus selkeästi merkityllä vahvistuspainikkeella.
Saat viipymättä sähköpostitse vahvistuksen, joka sisältää ilmoituksen sisällön
sekä lähetyspäivän ja -ajan. Maksamaton tilaus peruuntuu heti, jos tilaus ei
sisällä peruuttamisoikeuden ulkopuolisia tuotteita; muut pyynnöt käsitellään
manuaalisesti.

Fyysisen tuotteen palautuksesta on ilmoitettava 14 vuorokauden kuluessa
tuotteen vastaanottamisesta. Palautus tehdään postitse. Tuotteen saa avata
ja tutkia sen luonteen, ominaisuuksien ja toimivuuden toteamiseksi samaan
tapaan kuin myymälässä. Jos tuotetta on käytetty tätä laajemmin,
hyvityksestä voidaan vähentää tuotteen arvon alentumista vastaava määrä.
Asiakas järjestää palautuksen ja maksaa palautuspostin, ellei palautus
johdu virheestä tuotteessa tai toimituksessa. Sivusto ei tallenna
vastaanottopäivää, joten ylläpitäjä tarkistaa vastaanottopäivän ja
määräajan manuaalisesti.

Hyvitys palautetaan aina alkuperäiselle maksutavalle viivytyksettä ja
viimeistään 14 päivän kuluttua peruuttamisilmoituksen saapumisesta.
Fyysisen tuotteen palautuksessa hyvityksen maksamista voidaan kuitenkin
odottaa, kunnes tuote on vastaanotettu takaisin tai olet osoittanut
lähettäneesi sen.

Jäsenmaksuihin sovelletaan samaa 14 päivän peruuttamisoikeutta kuin muihinkin
etämyynnissä myytäviin tuotteisiin. Tapahtumamaksuihin ei sovelleta
peruuttamisoikeutta yllä kuvatun mukaisesti. Digitaalisten tuotteiden
peruuttamisoikeus on kuvattu edellä kohdassa "Digitaaliset tuotteet".

## Peruuttamisohje

Tämä ohje ja jäljempänä oleva lomake koskevat tuotteita, joihin sovelletaan
peruuttamisoikeutta.

### Peruuttamisoikeus

Teillä on oikeus peruuttaa tämä sopimus 14 päivän kuluessa syytä
ilmoittamatta.

Peruuttamisen määräaika päättyy 14 päivän kuluttua sopimuksen tekemisestä,
kun kyseessä on palvelu tai sähköisesti toimitettava digitaalinen sisältö.
Tavaran kaupassa määräaika päättyy 14 päivän kuluttua siitä, kun tavara,
viimeinen tavaraerä tai säännöllisesti toimitettavien tavaroiden ensimmäinen
tavaraerä on vastaanotettu.

Peruuttamisoikeuden käyttämiseksi teidän on ilmoitettava meille päätöksestänne
peruuttaa sopimus yksiselitteisellä tavalla, esimerkiksi kirjeellä postitse
tai sähköpostilla:

- **Rytkösten sukuseura ry**
- Tyrmynniementie 71, 74595 Runni
- Puhelin: 040 592 2842
- Sähköposti: info@rytkoset.net

Voitte käyttää jäljempänä olevaa peruuttamislomaketta, mutta sen käyttö ei
ole pakollista. Voitte tehdä yksiselitteisen peruuttamisilmoituksen myös
verkkosivustollamme. Jos teitte tilauksen käyttäjätilillä, toiminto löytyy
kohdasta *Oma tili → Tilaukset*. Ilman käyttäjätiliä tehdyn tilauksen
henkilökohtainen **Peruuta tilaus** -linkki on tilausvahvistuksessa. Jos
käytätte verkkosivuston peruuttamistoimintoa, ilmoitamme teille viipymättä
sähköpostitse peruuttamisilmoituksen saapumisesta sekä ilmoituksen lähetyspäivän
ja -ajan.

Peruuttamisen määräajan noudattamiseksi riittää, että lähetätte ilmoituksenne
peruuttamisoikeuden käytöstä ennen peruuttamisajan päättymistä.

### Peruuttamisen vaikutukset

Jos peruutatte tämän sopimuksen, palautamme teille kaikki teiltä saamamme
suoritukset, myös toimituskustannukset (paitsi lisäkustannuksia siitä, että
olette valinnut tarjoamastamme edullisimmasta vakiotoimitustavasta poikkeavan
toimitustavan), viivytyksettä ja joka tapauksessa viimeistään 14 päivän
kuluttua peruuttamisilmoituksen saatuamme. Suoritamme palautuksen sillä
maksutavalla, jota olette käyttänyt alkuperäisessä liiketoimessa, ellette ole
nimenomaisesti suostunut muuhun, ja joka tapauksessa siten, että teille ei
aiheudu suoritusten palauttamisesta kustannuksia.

Voimme pidättyä maksujen palautuksesta, kunnes olemme saaneet tavaran takaisin
tai kunnes olette osoittanut lähettäneenne tavaran takaisin.

Teidän on lähetettävä tavarat takaisin osoitteeseen Rytkösten sukuseura ry,
Tyrmynniementie 71, 74595 Runni viivytyksettä ja viimeistään 14 päivän
kuluttua peruuttamisilmoituksen tekemisestä. Määräaikaa on noudatettu, jos
lähetätte tavarat takaisin ennen kyseisen 14 päivän määräajan päättymistä.

Teidän on vastattava tavaroiden palauttamisesta johtuvista välittömistä
kustannuksista.

Olette vastuussa vain sellaisesta tavaroiden arvon alentumisesta, joka on
seurausta muusta kuin tavaroiden luonteen, ominaisuuksien ja toimivuuden
toteamiseksi tarvittavasta käsittelystä.

Jos olette pyytänyt palvelun suorittamista ennen peruuttamisajan päättymistä,
teidän on maksettava meille peruuttamisilmoituksen tekemiseen mennessä
sopimuksen täyttämiseksi tehdystä suorituksesta kohtuullinen korvaus.

### Tuotteet, joita peruuttamisoikeus ei koske

Peruuttamisoikeutta ei sovelleta tapahtumamaksuihin, kun vapaa-ajanpalvelu
sovitaan suoritettavaksi määrättynä ajankohtana.

Sähköisesti toimitettavan digitaalisen sisällön peruuttamisoikeus päättyy,
kun toimittaminen on aloitettu peruuttamisaikana kuluttajan nimenomaisella
ennakkosuostumuksella, kuluttaja on hyväksynyt peruuttamisoikeuden puuttumisen
ja elinkeinonharjoittaja on toimittanut tästä vahvistuksen.

## Peruuttamislomakkeen malli

*(Täyttäkää ja palauttakaa tämä lomake vain siinä tapauksessa, että haluatte
peruuttaa sopimuksen.)*

- Vastaanottaja: Rytkösten sukuseura ry, Tyrmynniementie 71, 74595 Runni,
  info@rytkoset.net
- Ilmoitan/Ilmoitamme (\*), että haluan/haluamme (\*) peruuttaa
  tekemäni/tekemämme (\*) sopimuksen, joka koskee seuraavien tavaroiden
  toimittamista (\*) / seuraavan palvelun suorittamista (\*):
- Tavarat tai palvelu: ________________________________________________
- Tilauspäivä (\*) / Vastaanottopäivä (\*): _____________________________
- Kuluttajan nimi (\*) / Kuluttajien nimet (\*): _________________________
- Kuluttajan osoite (\*) / Kuluttajien osoitteet (\*): ___________________
- Kuluttajan allekirjoitus (\*) / Kuluttajien allekirjoitukset (\*) (vain
  jos lomake täytetään paperimuodossa):
- Allekirjoitus: ______________________________________________________
- Päiväys: ____________________________________________________________

(\*) Tarpeeton yliviivataan.

## Virheet ja reklamaatiot

Jos tavarassa, digitaalisessa sisällössä tai palvelussa, maksussa tai
toimituksessa on virhe, ilmoita siitä mahdollisimman pian osoitteeseen
info@rytkoset.net.

Myyjä vastaa tavaroiden sekä digitaalisten sisältöjen ja palvelujen virheistä
kuluttajansuojalain virhevastuuta koskevien säännösten mukaisesti. Näillä
ehdoilla ei rajoiteta kuluttajan lakisääteiseen virhevastuuseen perustuvia
oikeuksia.

## Erimielisyyksien ratkaiseminen

Jos kauppasopimusta koskevaa erimielisyyttä ei saada ratkaistuksi osapuolten
välisillä neuvotteluilla, kuluttaja voi ottaa yhteyttä Kilpailu- ja
kuluttajaviraston kuluttajaneuvontaan:
[kkv.fi/kuluttajaneuvonta](https://www.kkv.fi/kuluttaja-asiat/kuluttajaneuvonta/).

Jos erimielisyys ei ratkea kuluttajaneuvonnan avulla, kuluttaja voi saattaa
asian kuluttajariitalautakunnan ratkaistavaksi:
[kuluttajariita.fi](https://www.kuluttajariita.fi/). Kuluttajariitalautakunta
voi jättää asian käsittelemättä, jos kuluttaja ei ole ensin ollut yhteydessä
kuluttajaneuvontaan.

## Yhteydenotot

Maksuihin, toimituksiin, jäsenyyteen ja tapahtumiin liittyvissä
kysymyksissä ota yhteyttä:

info@rytkoset.net
