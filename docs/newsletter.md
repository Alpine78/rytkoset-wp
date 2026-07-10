# Uutiskirjeet ja AcyMailing

Tämä dokumentti kuvaa uutiskirjeen tilauspaikkojen MVP-toteutuksen sekä ylläpitäjän perusprosessin uutiskirjeen laatimiseen, lähettämiseen ja listojen hallintaan.

## Käyttötarkoitus

Uutiskirjeen tilaus kerätään AcyMailingilla. Teema ei tallenna tilaajia omaan tietokantaan eikä käsittele lomakkeen lähetystä itse.

Ensimmäinen julkaistu tilauspaikka on sivuston footerin yläpuolinen pre-footer-kaista, joka näkyy koko sivustolla eikä vaadi erillisiä sivukohtaisia sisältömuutoksia. Tiketissä `#278` footer jaettiin pre-footeriin (uutiskirjekaista) ja slim footeriin (brändi, navigaatio, yhteystiedot, some). Etusivulla pre-footer on näyttävä iso lohko (`template-parts/pre-footer-large.php`), muilla sivuilla yksirivinen kompakti kaista (`template-parts/pre-footer-compact.php`). Molemmat käyttävät samaa AcyMailing-lomaketta.

Jatkoslicessä `#276` samaa AcyMailing-kohdelistaa käytetään myös vapaaehtoisissa opt-in-valinnoissa rekisteröitymisen, maksuttoman tapahtumailmoittautumisen ja WooCommerce-kassan yhteydessä.

## Lista

Uudet yleiset uutiskirjetilaukset ohjataan olemassa olevalle AcyMailing-listalle:

- `Rytkoset.net GDPR`

Uutta listaa ei luoda tätä MVP:tä varten. AcyMailingissa voi näkyä myös muita listoja, kuten hallituksen lista tai oletuksena syntynyt `Newsletters`, mutta niitä ei käytetä footerin yleiseen uutiskirjetilaukseen.

## Uutiskirjeen laatiminen ja lähettäminen

Tee varsinainen uutiskirjelähetys aina tuotannon WordPressissä (`rytkoset.net`). Kehitysympäristöä (`dev.rytkoset.net`) voi käyttää ulkoasun kokeiluun, mutta siellä ei pidä aktivoida tuotannon automaattista lähetystä tai käyttää oikeaa vastaanottajalistaa.

### 1. Luo kampanja

1. Avaa WordPress-adminissa `AcyMailing > Kampanjat` (`Campaigns`).
2. Luo uusi tavallinen kertaluonteinen kampanja tai kopioi aiempi, toimivaksi todettu uutiskirje.
3. Anna kampanjalle ylläpidossa tunnistettava nimi ja vastaanottajalle näkyvä aihe.
4. Tarkista lähettäjän nimeksi `Rytkösten sukuseura ry` ja käytä hyväksyttyä sukuseuran sähköpostiosoitetta.
5. Valitse vastaanottajalistaksi `Rytkoset.net GDPR`, ellei kyse ole erikseen sovitusta sisäisestä viestistä.

Älä lähetä yleistä uutiskirjettä AcyMailingin oletuslistalle `Newsletters` tai hallituksen sisäiselle listalle.

### 2. Laadi sisältö

Pidä viesti lyhyenä ja helposti silmäiltävänä:

- selkeä otsikko ja esikatseluteksti
- lyhyt tervehdys
- tärkein asia ensimmäisenä
- yksi selkeä toimintakehotus tai ensisijainen linkki
- linkit sivustolle mieluummin kuin pitkät tekstikokonaisuudet sähköpostissa
- tieto siitä, miksi vastaanottaja saa viestin
- toimiva tilauksen peruutuslinkki

Käytä olemassa olevaa hyväksyttyä uutiskirjepohjaa. Älä poista AcyMailingin dynaamisia tilauksen peruutus- tai selainversion linkkejä. Essential-lisenssillä `Built with AcyMailing` -brändäyksen voi poistaa kohdasta `AcyMailing > Asetukset > Sähköpostiasetukset > Sähköpostieditori`.

### 3. Tarkista ennen lähetystä

1. Avaa kampanjan esikatselu.
2. Lähetä AcyMailingin `Lähetä testi` -toiminnolla viesti vähintään yhdelle ylläpitäjän omalle osoitteelle.
3. Tarkista testiviesti sekä puhelimella että tietokoneella.
4. Avaa kaikki linkit ja tarkista, että ne johtavat tuotantoon eivätkä `dev.rytkoset.net`-osoitteeseen.
5. Tarkista lähettäjä, vastausosoite, aihe, esikatseluteksti, kohdelista ja vastaanottajien määrä.
6. Varmista, että peruutuslinkki ja selainversiolinkki näkyvät viestin lopussa.

Testilähetys ei lisää kampanjaa varsinaiseen lähetysjonoon eikä korvaa kohdelistan tarkistusta.

### 4. Lähetä kampanja

1. Valitse lähetys heti tai ajastettuna.
2. Hyväksy kampanjan vastaanottajalista ja lisää viesti AcyMailingin lähetysjonoon.
3. Älä yritä pakottaa kaikkia viestejä lähtemään kerralla. Tuotannon automaattinen lähetysprosessi purkaa jonoa asetetulla nopeudella.
4. Tarkista lähetyksen käynnistyttyä `AcyMailing > Asetukset > Jonoprosessi`: `Last Cron` päivittyy ja raportissa näkyy käsiteltyjen viestien määrä.
5. Tarkista lähetyksen valmistuttua kampanjan tilastoista onnistuneet ja epäonnistuneet lähetykset sekä mahdolliset peruutukset ja palautuneet viestit.

Jos lähetysjono ei etene seuraavan automaattisen cron-ajon jälkeen, älä luo kampanjaa uudelleen. Tarkista ensin cron, jono ja raportti, jotta sama viesti ei lähde vastaanottajille kahdesti.

## Tilaajien ja listojen hallinta

### Tilaajan tarkistaminen

1. Avaa `AcyMailing > Tilaajat` (`Subscribers`).
2. Hae henkilö sähköpostiosoitteella.
3. Avaa tilaaja ja tarkista, mille listoille osoite kuuluu sekä onko tilaus aktiivinen, vahvistamaton, peruttu tai estetty.

Sama sähköpostiosoite voi näkyä AcyMailingissa vain yhtenä tilaajana, vaikka se liittyisi useaan listaan.

### Tilaajan lisääminen

Suositeltu tapa on, että henkilö tilaa uutiskirjeen itse sivuston lomakkeella tai vapaaehtoisella opt-in-valinnalla. Lisää osoite ylläpidosta vain, jos henkilön suostumus on tiedossa:

1. Avaa `AcyMailing > Tilaajat`.
2. Luo uusi tilaaja tai avaa olemassa oleva.
3. Syötä ja tarkista sähköpostiosoite.
4. Liitä tilaaja listalle `Rytkoset.net GDPR`.
5. Tallenna.

Älä lisää jäsenrekisterin, tapahtuman tai verkkokaupan osoitteita uutiskirjelistalle ilman erillistä uutiskirjesuostumusta.

### Tilauksen poistaminen

Vastaanottajan kannattaa ensisijaisesti käyttää uutiskirjeen omaa `Peruuta tilaus` -linkkiä. Ylläpitäjä voi tarvittaessa avata tilaajan ja poistaa hänen tilauksensa listalta `Rytkoset.net GDPR`.

Älä poista koko tilaajatietuetta vain siksi, että henkilö peruu yhden listan tilauksen. Älä myöskään aktivoi peruttua tai estettyä tilausta uudelleen ilman uutta suostumusta.

### Listojen perushallinta

Listat löytyvät kohdasta `AcyMailing > Listat` (`Lists`):

- `Rytkoset.net GDPR` on yleisen uutiskirjeen tuotantolista.
- Hallituksen tai muun rajatun ryhmän listaa käytetään vain kyseisen ryhmän viestintään.
- Oletuslistaa `Newsletters` ei käytetä yleisen uutiskirjeen lähetyksiin.
- Älä nimeä, poista tai yhdistä `Rytkoset.net GDPR` -listaa ilman, että footer-lomakkeen ja teeman opt-inien toiminta tarkistetaan uudelleen.

Ennen CSV-tuontia ota varmuuskopio, varmista suostumuksen peruste ja tee ensin pieni koetuonti. Älä tuo WordPress-käyttäjiä tai vanhaa jäsenrekisteriä kokonaisuutena uutiskirjelistalle.

## Lähetysasetukset ja rajat

Tuotannon perusasetukset:

- AcyMailing Essential -lisenssi on kytketty vain tuotantoon.
- Automaattiset tehtävät ja AcyMailingin oma web-cron ovat tuotannossa aktiivisia.
- Kehitysympäristön automaattiset tehtävät pidetään deaktivoituina.
- Jonoprosessi lähettää yhden 18 viestin erän tunnissa eli enintään noin 18 sähköpostia tunnissa.
- Viestien välinen odotus voi olla `0` sekuntia, koska tuntiraja tehdään eräkoon ja ajovälin avulla.
- Lähetys on sallittu ympäri vuorokauden, ellei ylläpito erikseen rajaa lähetysaikaa.
- Epäonnistuneita lähetyksiä yritetään enintään asetettu määrä; toistuvasti epäonnistuvia osoitteita ei pidä palauttaa aktiivisiksi ilman syyn selvitystä.
- `Send a report`: vähintään `Only if an error occurs`.
- `Save the report`: `Only if AcyMailing executes an action`.

Esimerkiksi 180 vastaanottajan kampanja kestää noin 10 tuntia nopeudella 18 viestiä tunnissa. Kampanja kannattaa siksi lisätä jonoon riittävän aikaisin. AcyMailingin lisäksi myös muut sivuston sähköpostit käyttävät samaa palveluntarjoajan lähetyskapasiteettia, joten suurta uutiskirjelähetystä ei kannata ajoittaa samaan aikaan tapahtumien massaviestinnän kanssa.

Tuotannossa cronin toiminta tunnistetaan kohdasta `AcyMailing > Asetukset > Jonoprosessi`:

- `Last Run Time` päivittyy
- `Triggered from the IP` on AcyMailingin palvelun IP `178.23.155.178`
- raportissa näkyy käsiteltyjen, onnistuneiden ja epäonnistuneiden viestien määrä

Erillistä cPanel-cronia ei tarvita niin kauan kuin AcyMailingin oma web-cron toimii ja lähetysjono etenee.

## Footer-lomakkeen käyttöönotto

1. Avaa WordPress-adminissa `AcyMailing > Subscription forms`.
2. Luo tai tarkista `Shortcode`-tyyppinen lomake.
3. Aseta lomakkeen listaksi `Rytkoset.net GDPR`.
4. Pidä lomake lyhyenä:
   - sähköpostiosoite
   - tilauspainike
   - tietosuojalinkki tai suostumusteksti AcyMailingin lomakeasetuksista
5. Jos AcyMailing-lomakkeessa on source-asetus, käytä arvoa `footer`.
6. Kopioi lomakkeen shortcode muodossa `[acymailing_form_shortcode id="..."]`.
7. Avaa `Ulkoasu > Mukauta > Uutiskirje`.
8. Liitä shortcode kenttään `AcyMailing-lomakkeen shortcode`.
9. Tallenna ja testaa footer.

Älä valitse AcyMailingin omaa `Footer`-lomaketyyppiä tässä MVP:ssä. Se on AcyMailingin automaattinen footer-display ja paikallisen plugin-koodin perusteella Enterprise-ominaisuus. Teeman toteutus käyttää sen sijaan Essentialissa käytettävissä olevaa shortcode-lomaketta ja sijoittaa sen itse sivuston footeriin.

Footer ei näytä uutiskirjeblokkia, jos shortcode on tyhjä, AcyMailing ei ole aktiivinen tai shortcode ei renderöi lomaketta.

## Suositellut AcyMailing-asetukset

Footerin uutiskirjelomake on tarkoitettu myös kävijöille, joilla ei ole WordPress-käyttäjätunnusta. AcyMailing luo tai päivittää AcyMailing-tilaajan, mutta ei luo WordPress-käyttäjää.

Suositeltu asetuspolku on `AcyMailing > Configuration > Subscription`:

- `Allow non-logged in users`: päällä
- `Auto-generate User's name`: päällä, jos lomakkeella kysytään vain sähköpostiosoite
- `Require confirmation`: projektin päätös
- `Allow subscriber data modifications without authentication`: `Only their subscription`

Jos `Require confirmation` on päällä, tilaaja saa vahvistusviestin ennen kuin tilaus vahvistuu. Jos se on pois päältä, sähköpostiosoite voidaan lisätä listalle heti lomakkeen lähetyksestä. Vahvistuksen poistaminen tekee tilauksesta helpomman, mutta lisää väärien tai toisen henkilön sähköpostiosoitteiden tilaamisen riskiä.

Tietosuostumus tai tietosuojalinkki kannattaa pitää lomakkeella erillään sähköpostivahvistuksesta. Se ei ole tekninen kaksoisvahvistus, vaan kertoo mihin tilaaja antaa suostumuksen.

## Lomakkeen kieli

Footerissa näkyvät AcyMailing-lomakkeen omat tekstit, kuten `Email`, `I agree with the Privacy policy` ja `Privacy policy`, tulevat AcyMailingin käännösavaimista eivätkä teemasta.

Suomenkielinen lomake otetaan käyttöön AcyMailingin asetuksista:

1. Varmista WordPressissä, että `Asetukset > Yleinen > Sivuston kieli` on `Suomi`.
2. Avaa `AcyMailing > Configuration > Languages`.
3. Lisää tai avaa `Suomi` / `fi-FI`.
4. Jos AcyMailing ehdottaa uusimman kielitiedoston lataamista, lataa se ja tallenna kieli.
5. Päivitä footer ja testaa lomake kirjautumattomana.

Jos tekstit jäävät englanniksi, tarkista AcyMailingin `fi-FI`-kielestä ainakin nämä avaimet:

```ini
ACYM_EMAIL="Sähköposti"
ACYM_PRIVACY_POLICY="tietosuojaselosteen"
ACYM_I_AGREE_PRIVACY="Hyväksyn %s"
```

Lomakkeen `Display only for those languages: Suomi` rajaa lomakkeen näkymistä kielen perusteella, mutta se ei yksin käännä lomakkeen tekstejä.

Englannin kieltä ei kannata poistaa AcyMailingista, vaikka sivusto on vain suomeksi. AcyMailingin plugin-koodissa `en-US` on oletus- ja fallback-kieli, jota käytetään, jos nykyisen kielen käännöstä ei löydy. Suomenkielisyys kannattaa tehdä `fi-FI`-käännöksillä tai `fi-FI` custom translations -kentällä.

## Toteutus teemassa

Toteutus on tiedostossa `wp-content/themes/rytkoset-theme/inc/newsletter.php`.

Teema:

- lisää Customizer-asetuksen `rytkoset_theme_newsletter_shortcode`
- hyväksyy footerissa vain AcyMailingin `acymailing_form_shortcode`-shortcoden
- renderöi lomakkeen footerissa vain, kun AcyMailing-shortcode on käytettävissä
- piilottaa koko pre-footer-kaistan kirjautuneelta käyttäjältä, joka on jo aktiivisena tilaajana lomakkeen kohdelistalla (`rytkoset_theme_get_footer_newsletter_form()` palauttaa tyhjän merkkijonon, jolloin partial ei renderöi mitään)
- näyttää kirjautuneelle käyttäjälle pelkän tilauspainikkeen, jos käyttäjä ei vielä ole kohdelistan tilaaja; painike käyttää kirjautuneen käyttäjän sähköpostiosoitetta piilotettuna kenttänä
- vaihtaa kirjautuneen käyttäjän tilauspainikkeen onnistuneen AcyMailing-vastauksen jälkeen heti tekstiksi `Olet jo uutiskirjeen tilaaja.`, jotta käyttäjän ei tarvitse päivittää sivua nähdäkseen tilan
- tarjoaa yhteisen AcyMailing-helperin, jolla rekisteröityminen, maksuton tapahtumailmoittautuminen ja WooCommerce-kassa voivat tilata sähköpostiosoitteen samalle kohdelistalle
- tarjoaa My Account -näkymälle perumishelperin, joka hakee AcyMailing-tilaajan kirjautuneen käyttäjän sähköpostilla ja peruu vain yleisen uutiskirjelistan tilauksen AcyMailingin `unsubscribe()`-API:lla
- näyttää opt-in-checkboxin vain, jos footer-shortcode ja sen AcyMailing-kohdelista ovat käytettävissä
- piilottaa opt-in-checkboxin kirjautuneelta käyttäjältä, joka on jo kohdelistan aktiivinen tilaaja
- poistaa footerissa renderöidystä AcyMailing-lomakkeesta lomakkeen omat sisäiset `<style>`-tagit, jotta AcyMailingin shortcode-preview-tyylit eivät tee footeriin erillistä valkoista laatikkoa
- muotoilee lomakkeen scoped-tyyleillä tiedostossa `assets/css/footer.css`

AcyMailingin plugin-koodissa automaattiset popup/header/footer-lomakkeet tarkistetaan Enterprise-tasoa vasten, mutta shortcode rekisteröidään erikseen WordPress-shortcodena. Siksi teema ei riipu Enterprise-footer-ominaisuudesta.

Kirjautuneen käyttäjän tilaustila tarkistetaan AcyMailingin tauluista `wp_acym_user` ja `wp_acym_user_has_list`. Tarkistus käyttää footer-shortcoden lomake-ID:tä ja lomakkeen listavalintoja, joten listan numeerista ID:tä ei kovakoodata teemaan.

## Oma tili -välilehti

WooCommercen Oma tili -sivulla on kirjautuneelle käyttäjälle `Uutiskirje`-välilehti (`/oma-tili/uutiskirje/`). Endpointin avain on `rytkoset_newsletter` ja julkinen slug `uutiskirje`.

Välilehti näyttää vain kirjautuneen käyttäjän oman WordPress-tilin sähköpostiosoitteen uutiskirjetilan. Näkymässä ei ole avointa sähköpostikenttää, eikä sillä voi tilata tai perua toisen osoitteen tilausta.

Toiminnot:

- jos käyttäjä ei ole yleisen uutiskirjelistan aktiivinen tilaaja, hän näkee `Tilaa uutiskirje` -painikkeen
- jos käyttäjä on aktiivinen tilaaja, hän näkee `Peru tilaus` -painikkeen
- molemmat toiminnot lähetetään POST-pyyntönä WordPress-noncella
- tilaus käyttää samaa `rytkoset_theme_subscribe_email_to_newsletter()`-helperiä kuin muut opt-in-paikat
- peruminen ei poista AcyMailing-tilaajatietuetta, vaan merkitsee vain footer-shortcoden kohdelistan tilauksen perutuksi AcyMailingin omalla `unsubscribe()`-mekanismilla
- onnistumis- ja virheviestit näytetään WooCommercen noticeina

Endpoint rekisteröidään teemassa WooCommercen `woocommerce_get_query_vars` -suodattimella. Rewrite-säännöt flushataan versionoidulla option-vartijalla, joka tarkistaa myös tallennetut rewrite-säännöt, jotta olemassa olevat asennukset eivät jää 404-tilaan.

## Opt-in-paikat

Uutiskirjeen vapaaehtoinen opt-in on käytössä näissä työnkuluissa:

- WordPress-rekisteröityminen: checkbox `Tilaa uutiskirje`, ei oletuksena valittu. Valittu opt-in käsitellään `user_register`-hookissa.
- Maksuton tapahtumailmoittautuminen: checkbox näkyy lomakkeella, jos käyttäjä ei ole kirjautuneena jo tilaaja. Tilaus käsitellään vasta onnistuneen ilmoittautumisen tallennuksen jälkeen.
- WooCommerce Checkout Block: checkbox `Tilaa sukuseuran uutiskirje tähän osoitteeseen` näkyy Yhteystiedot-osiossa sähköpostikentän alla (`location => 'contact'`, #520) kaikille tilauksille, paitsi kirjautuneelle jo tilaajana olevalle käyttäjälle. Arvo tallentuu edelleen samaan `_wc_other/rytkoset/newsletter_opt_in` -tilausmetaan, ja tilaus käsitellään Store API -checkoutin order processed -hookissa.

Kirjautumattomille käyttäjille opt-in näytetään, koska tilaustilaa ei voi päätellä luotettavasti ennen sähköpostiosoitteen syöttämistä. Jos syötetty sähköposti on jo tilaaja, integraatio käsittelee tilanteen onnistumisena eikä näytä käyttäjälle virhettä.

AcyMailingin `Require confirmation` -asetus määrää, vaatiiko uusi tilaus sähköpostivahvistuksen. Teema ei ohita AcyMailingin double opt-in -asetusta.

## Testaus

Testaa käyttöönoton jälkeen:

- etusivun iso pre-footer ja alasivujen kompakti kaista näyttävät lomakkeen desktopissa ja mobiilissa
- slim footer (brändi, navigaatio, yhteystiedot, some) näkyy kaikilla sivuilla
- lomake toimii näppäimistöllä ja focus-tila näkyy
- tumma teema näyttää kentät ja painikkeet luettavasti
- kirjautumaton käyttäjä voi tilata uutiskirjeen, jos AcyMailing-asetukset sallivat sen
- kirjautunut käyttäjä, joka ei ole kohdelistan tilaaja, voi tilata uutiskirjeen ilman sähköpostiosoitteen uudelleenkirjoittamista
- kirjautuneen käyttäjän tilauspainike näyttää lähetyksen aikana tilan `Tilataan...` ja onnistumisen jälkeen tekstin `Olet jo uutiskirjeen tilaaja.`
- kirjautunut käyttäjä, joka on jo kohdelistan tilaaja, ei näe pre-footer-kaistaa lainkaan (vain slim footer)
- Oma tili -> Uutiskirje näyttää kirjautuneen käyttäjän tilaustilan ja toimii ilman 404-virhettä
- Oma tili -> Uutiskirje: ei tilaaja voi tilata uutiskirjeen yhdellä painikkeella ja sivun uusi lataus näyttää tilan muuttuneen
- Oma tili -> Uutiskirje: tilaaja voi perua uutiskirjeen yleisen listatilauksen yhdellä painikkeella ja sivun uusi lataus näyttää tilan muuttuneen
- Oma tili -> Uutiskirje: POST ilman oikeaa noncea ei muuta tilausta
- rekisteröitymisen opt-in lisää uuden käyttäjän sähköpostin uutiskirjelistalle
- maksuttoman tapahtumailmoittautumisen opt-in lisää ilmoittautujan sähköpostin uutiskirjelistalle eikä estä ilmoittautumista, jos AcyMailing-tilaus epäonnistuu
- WooCommerce-kassan opt-in lisää billing email -osoitteen uutiskirjelistalle eikä estä tilausta, jos AcyMailing-tilaus epäonnistuu
- kirjautunut jo tilaaja ei näe tapahtuma- tai kassalomakkeiden opt-in-checkboxia
- testiosoite ilmestyy AcyMailingin tilaajiin listalle `Rytkoset.net GDPR`

Jos frontendissä näkyy virhe `You are not allowed to modify this user`, testaa lomake kirjautumattomana tai käytä kirjautuneen WordPress-käyttäjän omaa sähköpostiosoitetta. AcyMailing voi estää tilanteen, jossa kirjautunut käyttäjä yrittää tilata tai muokata eri sähköpostiosoitteen tilausta.

## Tuotantoonvienti ja ensimmäinen lähetys

Tämä checklist kattaa tiketin `#109`: tuotannon listojen ja asetusten tarkistus, ensimmäisen oikean lähetyksen suunnittelu, hallittu lähetys ja havaintojen kirjaaminen.

Ennen lähetystä tarkista tuotannossa:

- AcyMailing-lisenssi on kytketty ja automaattinen lähetysprosessi on aktiivinen.
- AcyMailingin oma web-cron-palvelu toimii: `Last Cron` päivittyy AcyMailingin palvelun IP:stä ja testikampanja lähtee jonosta automaattisesti.
- Kohdelista on `Rytkoset.net GDPR`; älä lähetä oletuslistalle `Newsletters` tai hallituksen sisäiselle listalle, ellei lähetyksen tarkoitus erikseen sitä vaadi.
- Footerin shortcode-lomake käyttää samaa kohdelistaa kuin opt-in-paikat.
- Tilaajien määrä ja vastaanottajalista on silmäilty ennen lähetystä; poista selvästi virheelliset tai testiosoitteet.
- Lähettäjän nimi, lähettäjän osoite ja vastausosoite ovat sukuseuran hyväksymät.
- Uutiskirjeen kieli, otsikko, esikatseluteksti, linkit ja tietosuojalinkki on tarkistettu.
- Testilähetys on lähetetty vähintään yhdelle ylläpitäjän omalle osoitteelle ja tarkistettu mobiilissa sekä desktopissa.
- Lähetysnopeus huomioi palvelun noin 18 sähköpostin tuntirajan; jos vastaanottajia on paljon, käytä AcyMailingin jonoa/automaattista lähetysprosessia äläkä pakota koko lähetystä kerralla.

### cPanel-cron

AcyMailingin oma web-cron-palvelu on ensisijainen ratkaisu tässä tuotantokäytössä. cPanel-cronia ei tarvita uutiskirjejonon käsittelyyn, jos AcyMailingin oma web-cron päivittyy ja testilähetys lähtee jonosta automaattisesti.

AcyMailingin oma web-cron-palvelu on käytössä, jos AcyMailingin `Last Cron` -kohdassa näkyy viimeisin ajokerta ja `Triggered from the IP` on AcyMailingin palvelun IP, esimerkiksi `178.23.155.178`. Silloin AcyMailingin palvelin triggeröi sivustoa, eikä cPanel-cron ole välttämättä tarpeellinen ensimmäistä lähetystä varten.

Tuotannossa 9.6.2026 lisenssin siirron jälkeen testikampanja lähti AcyMailingin web-cronin kautta: 3 viestiä käsiteltiin, 3 onnistui ja 0 epäonnistui. Tämä vahvistaa, että automaattinen lähetysprosessi toimii ilman erillistä cPanel-cronia.

Jos vanhassa cPanel-cronissa on Joomla-ajan URL, esimerkiksi `option=com_acym`, sen voi poistaa sen jälkeen, kun AcyMailingin web-cron on todettu toimivaksi. Älä korvaa sitä uudella cPanel-cronilla, ellei AcyMailingin oma web-cron lakkaa toimimasta tai lähetysjono ei etene.

Jos cPanel-cron tarvitaan myöhemmin fallbackiksi:

1. Avaa AcyMailingin asetuksista CRON-/Queue process -kohta.
2. Kopioi AcyMailingin näyttämä tuotannon CRON-URL. Älä kirjoita osoitetta käsin äläkä tallenna sitä repoon, koska osoite voi sisältää sivustokohtaisen avaimen.
3. Testaa URL avaamalla se selaimessa kirjautuneena ylläpitäjänä. AcyMailingin pitää näyttää onnistunut cron-triggeröinti tai päivittää viimeisin ajokerta.
4. Avaa cPanelissa `Cron Jobs`.
5. Valitse ajoväli AcyMailingin asetuksen mukaan. Käytä mieluummin samaa tai harvempaa väliä kuin AcyMailingin lähetysprosessin oma väli.
6. Lisää komento muodossa:

```bash
wget -q -O /dev/null "https://example.com/ACYMAILING_CRON_URL" >/dev/null 2>&1
```

Jos palvelimella ei ole `wget`-komentoa, käytä vastaavaa `curl`-komentoa:

```bash
curl -fsS "https://example.com/ACYMAILING_CRON_URL" >/dev/null 2>&1
```

Pidä URL lainausmerkeissä, koska CRON-URL voi sisältää `&`-merkkejä. Ensimmäisen ajon jälkeen tarkista AcyMailingista viimeisin cron-ajo ja jonon eteneminen. Jos cPanel lähettää cron-outputin sähköpostiin, hiljennys `>/dev/null 2>&1` estää turhat ilmoitukset vasta, kun toiminta on todettu.

Varsinainen ylläpitäjän lähetysprosessi on kuvattu tämän dokumentin kohdassa [Uutiskirjeen laatiminen ja lähettäminen](#uutiskirjeen-laatiminen-ja-lähettäminen).

Kirjaa ensimmäisen lähetyksen jälkeen jatkoa varten:

- lähetyksen päivämäärä ja kellonaika
- kohdelista
- vastaanottajien määrä
- lähetyksen aihe
- havaittu tekninen ongelma, jos sellainen tuli vastaan
- jatkotoimenpide, jos bounceja, virheellisiä osoitteita tai palautetta tuli

## Rajaukset

Tämä MVP ei sisällä:

- AcyMailing-automaatioita tai kuittiviestejä
- uutiskirjeen lähetyspohjaa tai SMTP-asetuksia

Paikallisessa plugin-koodissa ei ole mukana erillistä AcyMailing WooCommerce -integraatiolisäosaa. Checkout-opt-in toteutetaan siksi teeman kevyellä WooCommerce Blocks -hookilla.
