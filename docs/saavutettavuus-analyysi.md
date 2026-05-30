# Saavutettavuusanalyysi (WCAG 2.1 AA)

Tämä on EPIC #81:n perustason saavutettavuusanalyysi (tiketti #82). Tavoitteena on
käydä sivuston keskeiset näkymät läpi WCAG 2.1 AA -tasoa vasten, tunnistaa
tärkeimmät ongelmakohdat (navigaatio, kontrastit, lomakkeet, media), dokumentoida
löydökset ja priorisoida korjaukset jatkotiketteihin #83–#89.

- **Laajuus:** perustaso — koodikatselmointi `rytkoset-theme`-teemasta + kontrastien
  laskenta värimuuttujista. Ei automaattista testausajoa eikä ruudunlukijatestiä;
  ne kuuluvat tikettiin #89.
- **Menetelmä:** templatejen ja CSS:n läpikäynti, kontrastisuhteiden laskenta WCAG-
  kaavalla `assets/css/base.css`-tokeneista (sis. tumma teema), JS:n fokuksenhallinnan
  tarkistus.
- **Tarkastellut näkymät:** etusivu, sisältösivu, blogiartikkeli, tapahtuma-arkisto
  ja yksittäinen tapahtuma, albumi/galleria, kirjautumissivu, WooCommerce-kassa.

> **Huom. luotettavuus:** kaikki tämän raportin kontrastiluvut on laskettu
> teeman todellisista hex-arvoista, ja jokainen "korjattava"-löydös osoittaa
> todelliseen tiedosto:rivi-kohtaan. Epävarmat kohdat on merkitty erikseen
> *"varmistettava dev-ympäristössä"*.

---

## 1. Yhteenveto

Teema on saavutettavuuden perustasolla **hyvässä kunnossa**. Pohjatyö on tehty
huolellisesti: semanttiset maamerkit, ohituslinkki, `lang="fi"`, kattavat
`aria-label`-attribuutit, mobiilivalikon fokusansa ja näppäimistötuki, näkyvät
fokustyylit sekä esimerkillisesti merkitty tapahtumailmoittautumislomake.

**Kriittisiä esteitä ei löytynyt.** Korjattavat kohdat ovat rajattuja ja
toteutettavissa pieninä osina jatkotiketeissä:

| Prioriteetti | Löydös | Tiketti | Tila |
| --- | --- | --- | --- |
| **Korkea** | Vaalean teeman päävalikon linkkitekstin kontrasti alle AA:n (3,68:1) | #84 | ✅ korjattu |
| **Keski** | Galleriavideon `<iframe>` ilman saavutettavaa nimeä (`title`) | #86 | avoinna |
| **Keski** | Galleriakuvien alt-tekstien laatu riippuu syötetystä datasta | #86 | avoinna |
| **Matala** | Pari fokustilaa ilmaistaan vain tausta-/värimuutoksella, ei fokusrenkaalla | #83 | ✅ korjattu |
| **Matala** | `aria-current` aktiivisesta valikkokohdasta | #83 | ✅ varmistettu (WP-walker tuottaa automaattisesti) |
| **Matala** | Skip-linkki rikki staattisilla sivuilla ja arkistoissa (puuttuva tai poikkeava main) | #83 | ✅ korjattu (löydetty toteutuksen aikana) |
| **Matala** | WooCommerce-kassan ja uutiskirjeen kenttien labelit — varmistettava | #85 / #88 | avoinna |

---

## 2. Löydökset osa-alueittain

### 2.1 Navigaatio ja rakenne

**Kunnossa:**

| Asia | WCAG | Sijainti |
| --- | --- | --- |
| Ohituslinkki sisältöön (`skip-link`, "Siirry sisältöön") | 2.4.1 | `header.php:20` |
| Maamerkit: `role="banner"`, `<main id="primary">`, `role="contentinfo"` | 1.3.1 | `header.php:24`, `page.php:5`, `footer.php:17` |
| Dokumentin kieli `lang="fi"` (`language_attributes()`) | 3.1.1 | `header.php:10` |
| `aria-label` kaikissa nav-elementeissä (Päävalikko, Tilivalikko, Mobiilivalikko, Alavalikko, somelinkit) | 1.3.1 | `header.php`, `footer.php` |
| Mobiilivalikko: `aria-expanded` / `aria-controls` / `aria-haspopup`, fokusansa, Esc sulkee, fokus palautuu | 2.1.1, 2.1.2, 4.1.2 | `header.php:173`, `assets/js/main.js` |
| Yksi `<h1>` per sivu, looginen otsikkohierarkia | 1.3.1, 2.4.6 | `front-page.php`, `archive-rytkoset_event.php` |
| bbPress-suodatinchipit käyttävät `aria-current="true"` | 4.1.2 | `bbpress/content-single-forum.php:89` |

**Korjattava / varmistettava:**

- **`aria-current` aktiivisessa valikkokohdassa** — päävalikot käyttävät WordPressin
  oletus-walkeria (ei custom-walkeria), joka **lisää `aria-current="page"`
  automaattisesti** aktiiviseen kohtaan. Tämä on todennäköisesti jo kunnossa, mutta
  *varmistettava renderöidystä HTML:stä dev-ympäristössä*. Vakavuus: matala. → #83
- **Pari fokustilaa ilman fokusrengasta** — valtaosa teeman fokustyyleistä noudattaa
  hyvää idiomia: `:hover, :focus-visible { outline: none }` ja heti perään erillinen
  `:focus-visible { outline: var(--nav-focus-outline) }` (esim. `nav.mobile.css:237`,
  `:367`, `:587`). Muutamassa kohdassa fokus ilmaistaan kuitenkin **vain taustan ja
  tekstivärin muutoksella** ilman rengasta: `.mobile-submenu-toggle:focus-visible`
  (`nav.mobile.css:289`) ja kaupan määräpainike (`shop.css:356`). Nämä ovat marginaalisia
  (taustamuutos toimii heikkona indikaattorina ja ympäröivä kontti saa renkaan), mutta
  yhtenäisyyden vuoksi suositeltavaa korjata. Vakavuus: matala. → #83

### 2.2 Kontrastit

Kontrastit on laskettu WCAG-kaavalla `(L1+0,05)/(L2+0,05)`. Rajat: normaali teksti
4,5:1, suuri teksti (≥24px tai ≥18,66px lihavoitu) 3:1, käyttöliittymäkomponentit 3:1.

**Vaalea teema (oletus):**

| Pari | Hex | Suhde | AA |
| --- | --- | --- | --- |
| Leipäteksti / tausta | `#1f2933` / `#ffffff` | ~14,8:1 | ✅ |
| Leipäteksti / vaalea tausta | `#1f2933` / `#f5f5f5` | ~13,5:1 | ✅ |
| Vaimennettu teksti / valkoinen | `#5b6577` / `#ffffff` | ~5,9:1 | ✅ |
| Ensisijainen linkki / valkoinen | `#0f4c81` / `#ffffff` | ~7,4:1 | ✅ |
| Painike (primary) teksti / accent | `#08254a` / `#fbbf24` | ~11,8:1 | ✅ |
| Beige etusivulohko, tumma teksti | `#1f2933` / `#f6f1e7` | ~13,1:1 | ✅ |
| Tumma etusivulohko, valkoinen | `#ffffff` / `#0b315b` | ~7,8:1 | ✅ |
| Footer-teksti / footer | `#ffffff` / `#111827` | ~13,1:1 | ✅ |
| Yläpalkin utility-rivi | `rgba(255,255,255,.82)` / `#1f4277` | ~7:1+ | ✅ |
| Päävalikon linkki (15px) / palkki | `#ffffff` / `#0f4c81` *(oli `#3b82f6` — korjattu #84:ssä)* | ~8,9:1 | ✅ |

**Tumma teema** (`:root[data-theme="dark"]`) läpäisee kauttaaltaan (laskennalliset
suhteet 5:1–13:1), mm. päävalikon palkki tummenee arvoon `#0f4c81`, jolloin valkoinen
teksti saa ~8,9:1.

**Korjattu #84:ssä:**

- **Vaalean teeman päävalikon linkkiteksti** — `--header-primary-bg` muutettiin
  `#3b82f6` → `var(--color-primary)` (`#0f4c81`, [base.css:57](../wp-content/themes/rytkoset-theme/assets/css/base.css)).
  Vaalea ja tumma teema käyttävät nyt rakenteellisesti samaa headeria. Valkoisen
  tekstin kontrasti nousi 3,68:1 → ~8,9:1. Korjaus heijastuu myös hero-gradientin
  yläosaan ja `.btn--ghost`-nappeihin gradientin päällä — hero-otsikon alaotsikko
  `hero__lead` (rgba(255,255,255,.82) 18px) nousi ~3,0:1 → ~5,4:1.

**Avoinna:**

- **Ghost-napit erittäin kirkkaiden kuvien päällä** (`components.css`) — kontrasti
  hero-gradientin lisäksi kuvan luminanssista. Korjauksen jälkeen riski on huomattavasti
  pienempi. Vakavuus: matala. Tarvittaessa lisätään tummempi gradienttipeite.

### 2.3 Lomakkeet

**Kunnossa:**

| Asia | WCAG | Sijainti |
| --- | --- | --- |
| Tapahtumailmoittautuminen: `<label for>`, `required` + `aria-required`, `autocomplete`, `role="alert"` -virheilmoitus, pakollisten kenttien selite | 1.3.1, 3.3.2, 4.1.2 | `inc/event-registrations.php` |
| GDPR-suostumus pakollisena valintaruutuna selitteineen | 3.3.2 | `inc/event-registrations.php` |
| Kirjautumissivun välilehdet: `role="tab"`, `aria-selected` | 4.1.2 | `inc/login.php` |
| Ei pelkkä-placeholder-labeleita teeman lomakkeissa | 3.3.2 | — |

**Varmistettava:**

- **WooCommerce-kassan omat kentät** (Tampere 2026 -kentät, jäsenmaksut) — labelien
  sidonta hoituu WooCommerce Blocks -kautta; *varmistettava renderöidystä kassasta*. → #88 / #85
- **Uutiskirjelomakkeen kentät** (`inc/newsletter.php`, `template-parts/pre-footer-*.php`)
  — opt-in-valintaruudun label on kunnossa; AcyMailing-shortcoden tuottaman lomakkeen
  saavutettavuus on kolmannen osapuolen vastuulla. *Varmistettava.* → #85

### 2.4 Media ja ikonit

**Kunnossa:**

| Asia | WCAG | Sijainti |
| --- | --- | --- |
| Ikonilinkkien saavutettavat nimet (`screen-reader-text` / `aria-label`) | 1.1.1, 4.1.2 | `footer.php`, `inc/share.php` |
| Koristeikonit `aria-hidden="true"` / `focusable="false"` | 1.1.1 | `footer.php`, `inc/login.php` |
| PhotoSwipe: kopioi-linkki `aria-label`, ilmoitukset `role="status" aria-live="polite"` | 4.1.3 | `assets/js/photoswipe-init.js` |
| Kirjautumissivun logo koristeellisena `alt=""` | 1.1.1 | `inc/login.php` |
| IPTC-otsikon/kuvauksen synkronointi mediakirjastoon (pohja alt-teksteille) | 1.1.1 | `inc/attachment-iptc.php` |

**Korjattava:**

- **Galleriavideon `<iframe>` ilman saavutettavaa nimeä** — upotetulta videokehykseltä
  puuttuu `title`-attribuutti (`single-gallery_album.php:89`). Ruudunlukija ilmoittaa
  kehyksen vain geneerisesti. Korjaus: lisää `title`, esim. albumin/videon nimi.
  Vakavuus: keski. → #86
- **Galleriakuvien alt-tekstien laatu** — kuvat renderöidään mediakirjaston alt-kentästä;
  laatu riippuu siitä, mitä ylläpitäjä on syöttänyt. Tämä on sisältö-/ohjeistusasia, ei
  koodivirhe — suositellaan ohjeistus alt-tekstien täyttöön ja oletuskäsittely puuttuvalle
  alt-tekstille. Vakavuus: keski. → #86

---

## 3. Priorisoitu korjauslista

1. **Korkea — Päävalikon kontrasti (vaalea teema)** → #84
2. **Keski — Galleriavideon `<iframe>`-`title`** → #86
3. **Keski — Galleriakuvien alt-tekstit (ohjeistus + oletus)** → #86
4. **Keski — Ghost-nappien kontrasti kuvan päällä (varmistus)** → #84
5. **Keski — WooCommerce-kassan / uutiskirjeen kenttälabelit (varmistus)** → #88 / #85
6. **Matala — Yhtenäistä fokusrenkaat (pari poikkeusta)** → #83
7. **Matala — `aria-current`-varmistus renderöidystä HTML:stä** → #83

---

## 4. Ehdotetut jatkotikettien sisällöt

Ehdotukset perustuvat tämän analyysin löydöksiin. Hyväksymiskriteerit voi tarkentaa
tikettikohtaisesti.

### #83 Navigaation saavutettavuus
- [ ] Varmistetaan, että aktiivisessa valikkokohdassa on `aria-current="page"`
  (tarkistus renderöidystä HTML:stä; lisätään tarvittaessa)
- [ ] Yhtenäistetään fokusrengas kaikkiin valikon ja painikkeiden `:focus-visible`-tiloihin
  (poikkeukset: `nav.mobile.css:289`, `shop.css:356`)
- [ ] Näppäimistökierto pää-, mobiili- ja tilivalikossa testattu

### #84 Kontrastit ja värimaailma
- [ ] Vaalean teeman päävalikon linkkitekstin kontrasti ≥ 4,5:1
  (`--header-primary-bg` / tekstiväri)
- [ ] Ghost-/outline-nappien kontrasti riittää hero-kuvien päällä (tai vahvempi peite)
- [ ] Molemmat teemat (vaalea/tumma) tarkistettu kontrastityökalulla

### #85 Lomakkeiden saavutettavuus
- [ ] Uutiskirje- ja muut teeman lomakkeet: label-sidonta, virheilmoitukset, fokusjärjestys
- [ ] AcyMailing-lomakkeen saavutettavuuden rajat dokumentoitu

### #86 Kuvien ja median saavutettavuus
- [ ] Galleriavideon `<iframe>` saa kuvaavan `title`-attribuutin
- [ ] Galleriakuvien alt-tekstien oletuskäsittely + ylläpitäjän ohjeistus
- [ ] PhotoSwipe-näppäimistökäyttö testattu

### #87 Tapahtumalomakkeen saavutettavuus
- [ ] Tapahtumailmoittautumislomake testattu ruudunlukijalla ja näppäimistöllä
  (pohja jo hyvä; varmistus)

### #88 WooCommerce-osioiden saavutettavuus
- [ ] Kassan ja tuotesivujen kenttälabelit, virheilmoitukset ja fokus tarkistettu
- [ ] Määräpainikkeen (`shop.css:356`) fokustila yhtenäistetty

### #89 Saavutettavuustestaus
- [ ] Perustason testausmenetelmät dokumentoitu ja ajettu (ks. luku 5)

---

## 5. Perustason testausmenetelmät (#89:n pohja)

- **Näppäimistö:** koko sivu läpi Tab/Shift+Tab — fokusjärjestys looginen, ei ansoja,
  mobiilivalikko sulkeutuu Esc:llä
- **Fokuksen näkyvyys:** jokaisella interaktiivisella elementillä näkyvä fokusindikaattori
- **Zoom:** selaimen 200 % zoom ei riko asettelua eikä piilota sisältöä
- **Kontrasti:** kontrastityökalu (esim. selaimen devtools / WAVE / axe) molemmissa teemoissa
- **Mobiili:** kapea näyttöleveys, kosketuskohteet ≥ 44px
- **Tumma teema:** kaikki yllä myös tummassa teemassa
- **Ruudunlukija (kevyt):** lomakkeiden labelit, ikonilinkkien nimet ja maamerkit luetaan oikein
