# WooCommerce: Tampere 2026 bussikyyti-lisätuote

Tämä dokumentti kuvaa tiketin `#442` toteutusmallin: yhteiskuljetus Savosta Tampereen sukujuhliin ja takaisin.

## Tavoite

Tarjota Tampereen sukujuhliin erillinen bussikyyti Savosta ja takaisin (meno-paluu), alustava hinta **45 € / matkustaja**. **Meno perjantaina `28.8.2026`, paluu lauantaina `29.8.2026` juhlien jälkeen.** Matka toteutuu vain, jos lähtijöitä on riittävästi.

## Päätökset

- **Useita noutopisteitä Savossa** → matkustaja valitsee lähtöpaikan ostaessaan.
- **Bussin voi ostaa ilman osallistumismaksutuotetta** → erillinen, itsenäisesti ostettava tuote.
- **Ilmoittautumisen määräpäiväsulku pakollinen**, oletus `2026-07-30` (sama kuin osallistumismaksulla).
- **Matkan toteutuminen** (riittävä lähtijämäärä) ja mahdollinen hyvitys hoidetaan **operatiivisesti** — WooCommercessa ei ole tälle natiivia kynnystä.

## Tuotemalli

- Tuotteen nimi: `Bussikyyti Tampereen sukujuhliin (Savo–Tampere–Savo)`
- SKU: `tampere-2026-bussikyyti`
- Tuotetyyppi: **Variable product** (sama malli kuin osallistumismaksussa)
- Virtuaalituote: `Kyllä` (ei toimitusta)
- Ladattava: `Ei`
- Attribuutti **`Lähtöpaikka`**, jonka termit ovat noutopisteet → kukin noutopiste on variaatio. Kaikki variaatiot hinnaltaan `45 €`.

### Kapasiteetti (koko bussi, jaettu noutopisteiden kesken)

Kapasiteetti on koko bussin paikkamäärä, jaettu kaikkien noutopisteiden kesken. Tämä tehdään WooCommercen **parent-tason** varastonhallinnalla — ei variaatiokohtaisesti:

- parent-tuotteella `Manage stock = yes`
- `Stock quantity = bussin paikkamäärä`
- `Backorders = no`
- variaatioilla **ei** omaa varastoa (perivät jaetun parent-saldon)

Näin esim. `2 × Kuopio + 1 × Iisalmi` vähentää samasta 50 paikan saldosta yhteensä 3.

## Myyntilogiikka

- Yksi variaation kappale = yksi matkustaja kyseisestä noutopisteestä.
- Samaan tilaukseen voi ostaa matkustajia eri noutopisteistä (kuten osallistumismaksun aikuinen/lapsi).
- Tuote liitetään `Tampere 2026 osallistumismaksu` -tuotteen **Cross-sells**-kenttään (Product data → Linked Products), jolloin bussi ehdotetaan ostoskorissa. Bussin voi silti ostaa myös erikseen.
- Maksutapana nykyinen maksupolku (Mollie / tilisiirto).

## Määräpäivä ja kapasiteettisulku

Bussituotteelle on oma kenttä tuotteen **inventory**-osiossa:

- `Ilmoittautumisen määräpäivä` (oletus `2026-07-30`)

Logiikka (`inc/woocommerce-bus-transport.php`) uudelleenkäyttää osallistumismaksun määräpäivä-/kapasiteettiportin viestit ja päivän normalisoinnin, mutta bussi tunnistetaan **omalla SKU:lla**:

- määräajan jälkeen tuotetta ei voi ostaa → `Ilmoittautuminen on päättynyt.`
- kapasiteetin täytyttyä → `Ilmoittautuminen on täynnä.`
- määräpäivä tulkitaan päivän loppuun asti paikallisessa aikavyöhykkeessä (osto sulkeutuu seuraavan päivän alusta)
- sama estologiikka pysäyttää etenemisen myös ostoskorissa ja kassalla

### Tärkeä rajaus: ei osallistujakenttiä

Bussituote **ei** täsmää osallistumismaksutuotteeseen (`rytkoset_theme_is_tampere_2026_registration_product()`), joten sille **ei** aktivoidu osallistujakohtaisia kassakenttiä (nimi / ruokarajoite / buffet) eikä osallistuja-admin-saraketta. Noutopiste tulee variaatiosta ja matkustajamäärä kappalemäärästä.

## Matkan toteutuminen (operatiivinen)

WooCommercessa ei ole "toteutuu jos vähintään X lähtijää" -kynnystä, joten:

- kerro tuotekuvauksessa minimimäärä ja että `45 €` palautetaan, jos matka ei toteudu
- katso lähtijämäärä määräpäivän (`30.7.2026`) jälkeen tuotteen varastosaldosta / tilauksista
- jos lähtijöitä on liian vähän, peru ja hyvitä kyseiset tilaukset käsin (sekä Mollie että tilisiirto tukevat hyvitystä)

## Tuotekuvauksen minimitiedot

- reitti ja että hinta on meno-paluu
- aikataulu: meno perjantaina `28.8.2026`, paluu lauantaina `29.8.2026` juhlien jälkeen
- hinta `45 € / matkustaja`
- valitse lähtöpaikka ja lisää ostoskoriin yksi paikka per matkustaja
- matka toteutuu, jos lähtijöitä on riittävästi; muuten maksu palautetaan
- ilmoittautumisen määräpäivä `30.7.2026`

## Testaus

- Tuote näkyy kaupassa ja on ostettavissa myös ilman osallistumismaksutuotetta
- Lähtöpaikan voi valita ja eri noutopisteistä voi ostaa paikkoja samaan tilaukseen
- Ostoskorin summa muuttuu oikein (45 € × matkustajat)
- Koko bussin kapasiteetti on jaettu noutopisteiden kesken (yksi yhteinen varastosaldo)
- Määräpäivän jälkeen tuotetta ei voi ostaa ja näkyy `Ilmoittautuminen on päättynyt.`
- Kapasiteetin täytyttyä näkyy `Ilmoittautuminen on täynnä.`
- Bussituote **ei** näytä Tampere 2026 -osallistujakenttiä kassalla eikä osallistujasaraketta adminissa
- Tuote ehdotetaan ostoskorissa osallistumismaksun cross-sellinä

## Jätetään myöhempään / tietoisesti pois

- automaattinen minimimäärän / perumisen logiikka (manuaalinen hyvitys)
- matkustajakohtaiset lisäkentät (nimi/allergiat) bussille
- erillinen matkustajaraportti (paikkamäärä näkyy varastosaldosta ja tilauksista)

## Paikallinen testituote

Paikalliseen ympäristöön luotiin testituote 3 esimerkkinoutopisteellä (`Kuopio`, `Iisalmi`, `Varkaus`) ja 50 paikan jaetulla varastolla. **Noutopisteet ovat paikkamerkkejä** — korvaa ne todellisilla noutopisteillä WooCommerce-adminissa (Product data → Attributes → `Lähtöpaikka`, ja luo variaatiot kullekin).
