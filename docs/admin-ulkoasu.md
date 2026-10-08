# Hallintapaneelin ulkoasu (wp-admin)

Hallintapaneelin ulkoasu tuo wp-adminin samaan ilmeeseen julkisen sivuston kanssa ja helpottaa harvoin kirjautuvien vapaaehtoisten työtä. Suunnitelma on Claude Design -projektissa "Rytköset" (`Admin redesign.html`). Työ etenee viipaleina epicin [#690](https://github.com/Alpine78/rytkoset-wp/issues/690) alla.

## Tiedostot

- `wp-content/themes/rytkoset-theme/inc/admin-appearance.php` lataa tyylin `admin_enqueue_scripts`-koukussa WordPressin oman `colors`-tyylin jälkeen.
- `wp-content/themes/rytkoset-theme/assets/css/admin.css` sisältää kaikki tyylit. Arvot ovat `--ra-*`-muuttujissa tiedoston alussa. Sama tiedosto ladataan myös mukauttajaan (`customize_controls_enqueue_scripts`).
- `wp-content/themes/rytkoset-theme/assets/css/editor-style.css` antaa lohkoeditorin sisällölle julkisen sivun perustekstin (`system-ui`-fonttipino, tekstiväri, riviväli, linkkiväri). Se lisätään editorin asetuksiin `block_editor_settings_all`-suotimella eikä `add_theme_support( 'editor-styles' )` -kutsulla, koska tuki vaihtaisi Ulkoasu-valikon Lohkomallit-kohdan koko sivustoeditoriksi. Julkinen sivu ei käytä Manropea eikä Newsreaderia leipätekstissä, joten editori ei käytä niitäkään. Hätäkatkaisin poistaa myös editor-tyylin.

Tyyli koskee kaikkia hallinnan käyttäjiä. Ylä- ja sivupalkin, sijaintimerkin, laskureiden ja painikkeiden värit pysyvät samoina, vaikka käyttäjä olisi valinnut profiilissaan toisen väriasetelman. Väriasetelma voi silti vaikuttaa yksittäisiin kohtiin, joita tyyli ei kata. Väriasetelman valinnan poisto kuuluu viipaleeseen #699.

## Viipaleet

| Viipale | Tiketti | Sisältö | Tila |
| ------- | ------- | ------- | ---- |
| 1 | #691 | Tokenit, Manrope, ylä- ja sivupalkki, painikkeet, ilmoitukset, metaboxit, lohkoeditorin brändiväri | Tehty |
| 2 | #692 | Listataulukot, suodatinsirut, lomakekentät, WooCommercen tilamerkit | Tehty |
| 3a | #693 | Kojelaudan asettelu ja vaimennus | Tehty |
| 3b | #694 | Kojelaudan omat widgetit ja roolikohtainen näkyvyys | Odottaa |
| 4a | #695 | Oma näkymä: Tapahtumat > Osallistujat (tilamerkit, yhteenvetokortit, mobiilin korttirivit) | Tehty |
| 4b–4d | #696–#698 | Omat näkymät: Viestintä ja Palaute, Verkkojäsenyydet, Digilehdet | Tehty |
| 5 | #699 | Valikon ryhmittely ja roolit | Vaatii hallituksen päätöksen |
| 6 | #700 | Viimeistely: Newsreader-sivuotsikot, mediaruudukko, editor-tyylit, mukauttaja | Tehty |

## Hätäkatkaisin

Jos jokin hallinnan näkymä hajoaa tyylin takia, tyylin saa pois lisäämällä `wp-config.php`:hen:

```php
define( 'RYTKOSET_DISABLE_ADMIN_APPEARANCE', true );
```

Vakio ohittaa myös suotimen. Koodista tyylin saa pois suotimella `rytkoset_theme_enable_admin_appearance` (palauta `false`).

## Tarkistuslista WordPress- ja lisäosapäivitysten jälkeen

`admin.css`:ssä hauraat kohdat on merkitty kommentilla `FRAGILE`. Tarkista ainakin nämä:

1. **Lohkoeditorin Tallenna-painike on brändisininen** (#0f4c81), ei WordPressin sininen (#3858e9). WordPress 7:n editori kirjoittaa brändimuuttujansa inline-tyylinä ja valitsee värin kovakoodatusta listasta värimallin nimen perusteella. Tyyli ylikirjoittaa muuttujat `!important`-säännöllä elementille, jonka `style`-attribuutissa on `--wp-admin-theme-color`. Jos painike muuttuu taas WordPressin siniseksi, valitsin ei enää osu.
2. **Tapahtuman ja digilehden muokkauksen sivupalkissa ei ole vaakavieritystä.** Teeman omien metaboxien valintalistat on rajattu laatikon levyisiksi.
3. **Sivuvalikon sijaintimerkki** (keltainen palkki) ja keltaiset laskurit näkyvät myös aktiivisessa kohdassa ja hoverissa. Valikon taittopainikkeen hover- ja focus-tausta on sininen myös muissa väriasetelmissa.
4. **Näppäimistöfokus** näkyy keltaisena ylä- ja sivupalkissa ja sinisenä muualla.
5. **WooCommercen tilamerkit** (Käsittelyssä, Valmistunut, Odottaa maksua…) ja varastotekstit käyttävät teeman tilavärejä. Ne perustuvat WooCommercen luokkiin `mark.order-status.status-*`, `mark.instock` ja `mark.outofstock`.
6. **Listanäkymien rivitoiminnot** (Muokkaa, Pikamuokkaus, Siirrä roskakoriin) näkyvät ilman hoveria.
7. **Kojelauta** on yli 1500 px:n näytöllä kahdessa sarakkeessa, eikä tyhjiä "Raahaa laatikot tähän" -alueita näy, paitsi raahauksen aikana. Säännöt toistavat WordPressin `dashboard.css`:n 800–1499 px:n sääntöjä ja nojaavat body-luokkaan `is-dragging-metaboxes`. Jos käyttäjä valitsee sarakemäärän Näyttöasetuksista, sitä ei ohiteta.
8. **Kojelaudan vaimennetut laatikot** tunnistetaan id:llä (WordPress, WooCommerce, Rank Math). Jos pluginin id muuttuu, laatikko vain lakkaa olemasta vaimennettu.

## Tunnetut rajaukset

- Pluginien näkymien (WooCommerce, AcyMailing, Rank Math, bbPress) asettelua ei suunnitella uudelleen. Yleiset säännöt koskevat kuitenkin myös niitä: fontti (Manrope), kappaleiden ja metaboxien tekstikoko 14 px, metaboxien otsikot ja rivitys, siirtonuolten näkyvyys, painikkeet ja ilmoitukset. Tarkista pluginien näkymät päivitysten jälkeen.
- Painikkeet ovat 40 px kuten WordPress 7:n kentät. Listojen työkalurivin haku- ja massatoimintopainikkeet (`.button-compact`) ja kentät ovat työpöydällä 32 px. Alle 782 px:n näytöllä kaikki painikkeet ovat 44 px. Kenttien korkeutta ei muuteta, vain reunan väri, pyöristys ja tilat.
- WooCommercen tilausten hakupainike ei käytä compact-luokkaa, joten se on 40 px ja hakukenttä 32 px. Se on WooCommercen oma merkintä, eikä sitä tasata.
- Tummaa tilaa ei ole, koska pluginit kovakoodaavat vaaleat taustat.
- Profiilissa, osallistujalistassa, tilauksissa (783 px) ja AcyMailingissa (390 px) on sivun vaakavieritystä myös ilman tätä tyyliä. Ne korjataan omissa tiketeissään.
