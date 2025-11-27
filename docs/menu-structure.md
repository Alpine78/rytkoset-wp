# Ensisijaisen valikon päivitys (Appearance → Menus)

Tämän ohjeen avulla päivität teeman päävalikon ("Primary menu") vaatimusten mukaiseen rakenteeseen ja varmistat, että sama rakenne näkyy sekä desktop- että mobiilinavigaatiossa.

## 1) Avaa päävalikko
1. Kirjaudu WordPressin ylläpitoon.
2. Siirry kohtaan **Appearance → Menus**.
3. Valitse **Primary menu** (tai vastaava päävalikko), ja varmista, että valinta **Display location → Primary menu** on päällä.

## 2) Rakenna valikko vaatimusten mukaan
Lisää alla oleva perusrakenne ja täydennä linkit sivuihin/tuotekategorioihin, jotka vastaavat projektin vaatimuslistaa.

- **Etusivu** – linkitä etusivulle (`/`).
- **Sukuseura** (alavalikko)
  - *Sukuseuran esittely* (esim. `/sukuseura/sukuseura`)
  - *Jäsenyys* (esim. `/sukuseura/jaesenyys`)
  - *Tapahtumat* (esim. `/sukuseura/tapahtumat`)
- **Valokuvat** (alavalikko)
  - *Galleria/albumit* (esim. `/valokuvat` tai albumikohtaiset sivut)
  - *Sukukokousvuodet* (lisää jokaiselle vuodelle oma lapsikohde tarpeen mukaan)
- **Kauppa** (alavalikko)
  - *Jäsenmaksut* (WooCommerce-tuote tai kategoria)
  - *Tuotteet* (pääkategoria tai erilliset tuoteryhmät)
- **Blogi / Ajankohtaista** – linkitä uutis- tai blogilistaukseen (esim. `/kategoriat/ajankohtaista`).
- **Yhteystiedot** – linkitä yhteydenotto-/hallitus-sivuun.

> Täydennä mahdolliset lisäkohteet vaatimuslistan perusteella. Yllä olevat alavalikot ovat minimissään käytössä Sukuseura-, Valokuvat- ja Kauppa-osioissa.

### Lapsikohteiden luominen
1. Lisää uusi menu item **Add menu items** -paneelista.
2. Vedä kohde hieman sisennettynä sen yläpuolisen valikkokohteen alle → WordPress luo alavalikon.
3. Toista, kunnes kaikki alavalikot on rakennettu.

## 3) Tallenna ja julkaise
1. Napsauta **Save Menu**.
2. Varmista, että valikko on edelleen julkaistu **Primary menu** -sijaintiin.

## 4) Testaa näkyminen desktop- ja mobiilinäkymissä
- **Desktop:** avaa sivusto selaimessa, tarkista, että päävalikko näkyy headerissa ja että alavalikot avautuvat hoverilla/fokuksella.
- **Mobiili:** avaa sivusto kapealla selaimella tai devtoolsin mobiilitilassa, avaa **Valikko**-painike ja varmista, että samat kohteet ja alavalikot ovat näkyvissä ja avautuvat.
- Jos valikko ei näy mobiilissa, tarkista, että Primary-valikkoon on liitetty sisältöä; mobiilivalikko käyttää samaa `primary`-sijaintia kuin desktop (`header.php`).

## 5) Dokumentoi tehdyt muutokset
Kirjaa muutoksesi projektin muistiinpanoihin tai tikettiin (esim. mitä kohteita lisättiin/muutettiin), jotta muut näkevät, miten valikko vastaa vaatimuslistaa.
