# Uutiskirjeet ja AcyMailing

Tämä dokumentti kuvaa uutiskirjeen tilauspaikkojen MVP-toteutuksen tiketille `#266`.

## Käyttötarkoitus

Uutiskirjeen tilaus kerätään AcyMailingilla. Teema ei tallenna tilaajia omaan tietokantaan eikä käsittele lomakkeen lähetystä itse.

Ensimmäinen julkaistu tilauspaikka on sivuston footer, koska se näkyy koko sivustolla eikä vaadi erillisiä sivukohtaisia sisältömuutoksia.

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
- näyttää kirjautuneelle käyttäjälle lomakkeen sijaan tekstin `Olet jo uutiskirjeen tilaaja.`, jos käyttäjä on jo aktiivisena tilaajana lomakkeen kohdelistalla
- näyttää kirjautuneelle käyttäjälle pelkän tilauspainikkeen, jos käyttäjä ei vielä ole kohdelistan tilaaja; painike käyttää kirjautuneen käyttäjän sähköpostiosoitetta piilotettuna kenttänä
- vaihtaa kirjautuneen käyttäjän tilauspainikkeen onnistuneen AcyMailing-vastauksen jälkeen heti tekstiksi `Olet jo uutiskirjeen tilaaja.`, jotta käyttäjän ei tarvitse päivittää sivua nähdäkseen tilan
- poistaa footerissa renderöidystä AcyMailing-lomakkeesta lomakkeen omat sisäiset `<style>`-tagit, jotta AcyMailingin shortcode-preview-tyylit eivät tee footeriin erillistä valkoista laatikkoa
- muotoilee lomakkeen scoped-tyyleillä tiedostossa `assets/css/footer.css`

AcyMailingin plugin-koodissa automaattiset popup/header/footer-lomakkeet tarkistetaan Enterprise-tasoa vasten, mutta shortcode rekisteröidään erikseen WordPress-shortcodena. Siksi teema ei riipu Enterprise-footer-ominaisuudesta.

Kirjautuneen käyttäjän tilaustila tarkistetaan AcyMailingin tauluista `wp_acym_user` ja `wp_acym_user_has_list`. Tarkistus käyttää footer-shortcoden lomake-ID:tä ja lomakkeen listavalintoja, joten listan numeerista ID:tä ei kovakoodata teemaan.

## Testaus

Testaa käyttöönoton jälkeen:

- footer näyttää lomakkeen desktopissa ja mobiilissa
- lomake toimii näppäimistöllä ja focus-tila näkyy
- tumma teema näyttää kentät ja painikkeet luettavasti
- kirjautumaton käyttäjä voi tilata uutiskirjeen, jos AcyMailing-asetukset sallivat sen
- kirjautunut käyttäjä, joka ei ole kohdelistan tilaaja, voi tilata uutiskirjeen ilman sähköpostiosoitteen uudelleenkirjoittamista
- kirjautuneen käyttäjän tilauspainike näyttää lähetyksen aikana tilan `Tilataan...` ja onnistumisen jälkeen tekstin `Olet jo uutiskirjeen tilaaja.`
- kirjautunut käyttäjä, joka on jo kohdelistan tilaaja, näkee tekstin `Olet jo uutiskirjeen tilaaja.` lomakkeen sijaan
- testiosoite ilmestyy AcyMailingin tilaajiin listalle `Rytkoset.net GDPR`

Jos frontendissä näkyy virhe `You are not allowed to modify this user`, testaa lomake kirjautumattomana tai käytä kirjautuneen WordPress-käyttäjän omaa sähköpostiosoitetta. AcyMailing voi estää tilanteen, jossa kirjautunut käyttäjä yrittää tilata tai muokata eri sähköpostiosoitteen tilausta.

## Rajaukset

Tämä MVP ei sisällä:

- etusivun erillistä uutiskirjenostoa
- tapahtumailmoittautumisen erillistä uutiskirjevalintaa
- WooCommerce checkout -tilausvalintaa
- AcyMailing-automaatioita tai kuittiviestejä
- uutiskirjeen lähetyspohjaa tai SMTP-asetuksia

Paikallisessa plugin-koodissa ei ole mukana erillistä AcyMailing WooCommerce -integraatiolisäosaa. Siksi checkout-opt-in rajataan jatkotiketiksi, ellei lisäosa oteta myöhemmin käyttöön.
