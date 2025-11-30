#!/usr/bin/env bash
# Luo EPIC + 9 sub-issuea uutiskirje / AcyMailing -kokonaisuudelle.
# Edellyttää: gh CLI, kirjautuneena (gh auth login).

set -euo pipefail

REPO="Alpine78/rytkoset-wp"
ASSIGNEE="Alpine78"

echo "Luodaan EPIC ja sub-issuet repoihin $REPO ..."

# 1) EPIC
EPIC_NUMBER=$(
  gh issue create \
    --repo "$REPO" \
    --title "EPIC — Uutiskirjeet & AcyMailing (Essential)" \
    --assignee "$ASSIGNEE" \
    --label epic,email,newsletter,"plugin: acymailing" \
    --body '
## 🎯 Tavoite
Uutiskirjeiden hallinta toteutetaan AcyMailing Essential -lisenssillä.
Tämä epic varmistaa, että koko viestintä toimii modernisti, luotettavasti ja helposti ylläpidettävästi.

## Sub-issuet
Luodaan automaattisesti tämän epicin alle.
' \
    --json number --jq .number
)

echo "EPIC luotu: #$EPIC_NUMBER"

# Helperi sub-issueiden luontiin
create_issue () {
  local TITLE="$1"
  local LABELS="$2"
  local BODY="$3"

  gh issue create \
    --repo "$REPO" \
    --title "$TITLE" \
    --assignee "$ASSIGNEE" \
    --label "$LABELS" \
    --body "$BODY" \
    --json number --jq .number
}

# 1) Lisenssin uusiminen & aktivointi
ISSUE1=$(create_issue \
  "AcyMailing-lisenssin uusiminen ja aktivointi (dev + prod)" \
  "task,plugin: acymailing,email,newsletter" \
  "### Kuvaus
- Varmista, että Essential-lisenssi on uusittu (tehty).
- Aktivoi lisenssi **devissä** ja **prodissa**.
- Tarkista AcyMailing → Configuration → License, että lisenssi on aktiivinen molemmissa ympäristöissä.

### Hyväksymiskriteerit
- [ ] Lisenssi näkyy aktiivisena devissä.
- [ ] Lisenssi näkyy aktiivisena prodissa."
)

# 2) Pluginin puhdas asennus
ISSUE2=$(create_issue \
  "AcyMailing-pluginin puhdas asennus (dev + prod)" \
  "task,plugin: acymailing,environment: dev,environment: prod" \
  "### Kuvaus
- Poista vanhat AcyMailing-/Joomla-jäämät jos tarpeen.
- Asenna uusin AcyMailing dev-ympäristöön.
- Varmista, että sama versio on tuotannossa.
- Testaa perusnäkymät (listat, viestit, queue) ja cron / automaatiot.

### Hyväksymiskriteerit
- [ ] Devissä AcyMailing toimii ilman virheilmoituksia.
- [ ] Prodissa AcyMailing toimii ilman virheilmoituksia."
)

# 3) SMTP / throttling / bounce
ISSUE3=$(create_issue \
  "SMTP- ja lähetysrajojen asetukset (webhotelli, 18 mailia/h)" \
  "task,email,configuration" \
  "### Kuvaus
- Aseta lähetysrajoitukset AcyMailingiin:
  - Max **18 sähköpostia / tunti**.
  - Lähetysväli noin **1 viesti / 200 s**.
- Testaa lokit ja virhetilanteet.
- Ota bounce-hallinta käyttöön, jos palveluntarjoaja sallii.

### Hyväksymiskriteerit
- [ ] Lähekkäysnopeus vastaa webhotellin rajoja.
- [ ] Virhelogit pysyvät puhtaina testiajon jälkeen.
- [ ] Bouncet näkyvät hallitusti AcyMailingissa."
)

# 4) Uutiskirjepohja
ISSUE4=$(create_issue \
  "Rytköset-uutiskirjepohjan suunnittelu ja toteutus" \
  "design,frontend: layout,newsletter,email" \
  "### Kuvaus
- Luo uusi uutiskirjepohja Rytkösten visuaalisen ilmeen mukaan:
  - Logo, värit, typografia.
  - Mobiiliresponsiivinen layout.
- Footer:
  - Sukuseuran yhteystiedot.
  - Linkki tietosuojaselosteeseen.
  - Pakollinen unsubscribe-linkki.
  - Mahdolliset some-linkit.

### Hyväksymiskriteerit
- [ ] Pohja renderöityy hyvin sekä desktopilla että mobiilissa.
- [ ] Footer sisältää vaaditut tiedot (yhteystiedot + poistumislinkki)."
)

# 5) Mailing-listat & segmentointi
ISSUE5=$(create_issue \
  "Mailing-listojen luonti ja segmentointi" \
  "task,newsletter,data" \
  "### Kuvaus
- Luo vähintään seuraavat listat:
  - **Jäsenet**
  - **Hallitus**
  - **Uutiskirjetilaajat** (ei-jäsenet)
- Tee segmentointi (aktiiviset / ei-aktiiviset) jos mahdollista.
- Importoi nykyinen CSV-lista:
  - Ensin dev-ympäristöön.
  - Sitten tuotantoon, kun rakenne on testattu.

### Hyväksymiskriteerit
- [ ] Kaikilla segmenteillä on järkevä nimi ja kuvaus.
- [ ] Testi-import devissä onnistuu ilman virheitä.
- [ ] Prodissa listat ja segmentit vastaavat devin rakennetta."
)

# 6) Automaatiot
ISSUE6=$(create_issue \
  "Automaatiot: kuittiviestit ja lomakevahvistukset" \
  "task,automation,newsletter,email,frontend: forms" \
  "### Kuvaus
- Luo seuraavat automaatiot (vähintään):
  - Tervetuloviesti uusille uutiskirjetilaajille / jäsenille.
  - Tapahtumailmoittautumisen vahvistusviesti (Event Organizer).
  - Jäsenmaksun maksukuittiviesti (jos prosessi valmis).
- Valinnainen:
  - Muistutusviesti jäsenyyden vanhentuessa.

### Hyväksymiskriteerit
- [ ] Kaikki automaatiot on testattu devissä.
- [ ] Vahvistusviestit laukeavat oikeista triggereistä."
)

# 7) Testilähetykset devissä
ISSUE7=$(create_issue \
  "Testilähetykset dev-ympäristössä (hallitus / testiryhmä)" \
  "testing,newsletter,email,environment: dev" \
  "### Kuvaus
- Luo testikampanja, joka lähetetään hallituksen osoitteisiin.
- Tarkista:
  - Mobiilityylit ja responsiivisuus.
  - Kaikki linkit toimivat (sivusto, some, unsubscribe).
  - GDPR-/tietosuojateksti on mukana.
- Varmista, että throttling toimii odotetusti.

### Hyväksymiskriteerit
- [ ] Testiryhmä on vastaanottanut viestit ilman virheilmoituksia.
- [ ] Layout toimii yleisimmissä sähköpostiklienteissä (Gmail, Outlook, mobiili)."
)

# 8) Prod-vienti
ISSUE8=$(create_issue \
  "Uutiskirjejärjestelmän tuotantoon vienti + ensimmäinen oikea lähetys" \
  "task,newsletter,email,environment: prod" \
  "### Kuvaus
- Aktivoi lisenssi myös tuotantoympäristössä (jos ei vielä).
- Vie listat CSV:nä dev → prod.
- Tee ensimmäinen oikea (tai puoli-oikea) lähetys hallitukselle tai pienelle ryhmälle.
- Kerää palaute ja tee tarvittavat säädöt.

### Hyväksymiskriteerit
- [ ] Ensimmäinen tuotantolähetys onnistuu ilman kriittisiä virheitä.
- [ ] Mahdolliset säätötarpeet on kirjattu erillisiksi issuiksi."
)

# 9) Dokumentaatio
ISSUE9=$(create_issue \
  "Dokumentaatio: Uutiskirjeiden lähetys ja AcyMailingin käyttö" \
  "documentation,enhancement,newsletter" \
  "### Kuvaus
Lisää dokumentaatio (wiki / docs-kansio / README):

- Kuinka luodaan uusi uutiskirje?
- Kuinka valitaan ja segmentoidaan listat?
- Kuinka automaatiot toimivat (triggerit, ajastus)?
- Miten toimitaan, jos lähetys epäonnistuu (perus troubleshooting)?

### Hyväksymiskriteerit
- [ ] Dokumentaatio löytyy yhdestä selkeästä paikasta.
- [ ] Hallituksen jäsen pystyy ohjeen avulla lähettämään uutiskirjeen ilman kehittäjän apua."
)

echo
echo "Luodut issuet:"
echo "EPIC: #$EPIC_NUMBER"
echo " 1) #$ISSUE1"
echo " 2) #$ISSUE2"
echo " 3) #$ISSUE3"
echo " 4) #$ISSUE4"
echo " 5) #$ISSUE5"
echo " 6) #$ISSUE6"
echo " 7) #$ISSUE7"
echo " 8) #$ISSUE8"
echo " 9) #$ISSUE9"

echo
echo "Muokkaa EPICin runkoa ja lisää checklist esim:"
echo
echo "- [ ] #$ISSUE1 AcyMailing-lisenssin uusiminen ja aktivointi"
echo "- [ ] #$ISSUE2 Pluginin puhdas asennus (dev + prod)"
echo "- [ ] #$ISSUE3 SMTP / throttling / bounce"
echo "- [ ] #$ISSUE4 Uutiskirjepohja"
echo "- [ ] #$ISSUE5 Mailing-listat ja segmentointi"
echo "- [ ] #$ISSUE6 Automaatiot"
echo "- [ ] #$ISSUE7 Testilähetykset devissä"
echo "- [ ] #$ISSUE8 Tuotantoon vienti"
echo "- [ ] #$ISSUE9 Dokumentaatio"
