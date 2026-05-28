# Uutiskirjeet ja AcyMailing

Tämä dokumentti kuvaa uutiskirjeen tilauspaikkojen MVP-toteutuksen tiketille `#266`.

## Käyttötarkoitus

Uutiskirjeen tilaus kerätään AcyMailingilla. Teema ei tallenna tilaajia omaan tietokantaan eikä käsittele lomakkeen lähetystä itse.

Ensimmäinen julkaistu tilauspaikka on sivuston footerin yläpuolinen pre-footer-kaista, joka näkyy koko sivustolla eikä vaadi erillisiä sivukohtaisia sisältömuutoksia. Tiketissä `#278` footer jaettiin pre-footeriin (uutiskirjekaista) ja slim footeriin (brändi, navigaatio, yhteystiedot, some). Etusivulla pre-footer on näyttävä iso lohko (`template-parts/pre-footer-large.php`), muilla sivuilla yksirivinen kompakti kaista (`template-parts/pre-footer-compact.php`). Molemmat käyttävät samaa AcyMailing-lomaketta.

Jatkoslicessä `#276` samaa AcyMailing-kohdelistaa käytetään myös vapaaehtoisissa opt-in-valinnoissa rekisteröitymisen, maksuttoman tapahtumailmoittautumisen ja WooCommerce-kassan yhteydessä.

## Lista

Uudet yleiset uutiskirjetilaukset ohjataan olemassa olevalle AcyMailing-listalle:

- `Rytkoset.net GDPR`

Uutta listaa ei luoda tätä MVP:tä varten. AcyMailingissa voi näkyä myös muita listoja, kuten hallituksen lista tai oletuksena syntynyt `Newsletters`, mutta niitä ei käytetä footerin yleiseen uutiskirjetilaukseen.

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
- näyttää opt-in-checkboxin vain, jos footer-shortcode ja sen AcyMailing-kohdelista ovat käytettävissä
- piilottaa opt-in-checkboxin kirjautuneelta käyttäjältä, joka on jo kohdelistan aktiivinen tilaaja
- poistaa footerissa renderöidystä AcyMailing-lomakkeesta lomakkeen omat sisäiset `<style>`-tagit, jotta AcyMailingin shortcode-preview-tyylit eivät tee footeriin erillistä valkoista laatikkoa
- muotoilee lomakkeen scoped-tyyleillä tiedostossa `assets/css/footer.css`

AcyMailingin plugin-koodissa automaattiset popup/header/footer-lomakkeet tarkistetaan Enterprise-tasoa vasten, mutta shortcode rekisteröidään erikseen WordPress-shortcodena. Siksi teema ei riipu Enterprise-footer-ominaisuudesta.

Kirjautuneen käyttäjän tilaustila tarkistetaan AcyMailingin tauluista `wp_acym_user` ja `wp_acym_user_has_list`. Tarkistus käyttää footer-shortcoden lomake-ID:tä ja lomakkeen listavalintoja, joten listan numeerista ID:tä ei kovakoodata teemaan.

## Opt-in-paikat

Uutiskirjeen vapaaehtoinen opt-in on käytössä näissä työnkuluissa:

- WordPress-rekisteröityminen: checkbox `Tilaa uutiskirje`, ei oletuksena valittu. Valittu opt-in käsitellään `user_register`-hookissa.
- Maksuton tapahtumailmoittautuminen: checkbox näkyy lomakkeella, jos käyttäjä ei ole kirjautuneena jo tilaaja. Tilaus käsitellään vasta onnistuneen ilmoittautumisen tallennuksen jälkeen.
- WooCommerce Checkout Block: checkbox `Tilaa uutiskirje` näkyy kaikille tilauksille, paitsi kirjautuneelle jo tilaajana olevalle käyttäjälle. Tilaus käsitellään Store API -checkoutin order processed -hookissa.

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
- rekisteröitymisen opt-in lisää uuden käyttäjän sähköpostin uutiskirjelistalle
- maksuttoman tapahtumailmoittautumisen opt-in lisää ilmoittautujan sähköpostin uutiskirjelistalle eikä estä ilmoittautumista, jos AcyMailing-tilaus epäonnistuu
- WooCommerce-kassan opt-in lisää billing email -osoitteen uutiskirjelistalle eikä estä tilausta, jos AcyMailing-tilaus epäonnistuu
- kirjautunut jo tilaaja ei näe tapahtuma- tai kassalomakkeiden opt-in-checkboxia
- testiosoite ilmestyy AcyMailingin tilaajiin listalle `Rytkoset.net GDPR`

Jos frontendissä näkyy virhe `You are not allowed to modify this user`, testaa lomake kirjautumattomana tai käytä kirjautuneen WordPress-käyttäjän omaa sähköpostiosoitetta. AcyMailing voi estää tilanteen, jossa kirjautunut käyttäjä yrittää tilata tai muokata eri sähköpostiosoitteen tilausta.

## Rajaukset

Tämä MVP ei sisällä:

- AcyMailing-automaatioita tai kuittiviestejä
- uutiskirjeen lähetyspohjaa tai SMTP-asetuksia

Paikallisessa plugin-koodissa ei ole mukana erillistä AcyMailing WooCommerce -integraatiolisäosaa. Checkout-opt-in toteutetaan siksi teeman kevyellä WooCommerce Blocks -hookilla.
