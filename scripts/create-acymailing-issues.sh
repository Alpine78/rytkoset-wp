#!/usr/bin/env bash
set -euo pipefail

REPO="Alpine78/rytkoset-wp"
ASSIGNEE="Alpine78"

# Helper issue creation that returns issue number
create_issue() {
  local TITLE="$1"
  local LABELS="$2"
  local BODY="$3"

  ISSUE_URL=$(gh issue create \
    --repo "$REPO" \
    --assignee "$ASSIGNEE" \
    --title "$TITLE" \
    --label "$LABELS" \
    --body "$BODY")

  # Palauta issue-numero URL:ista
  echo "$ISSUE_URL" | sed 's/.*#\([0-9]\+\).*/\1/'
}

echo "Luodaan EPIC ja sub-issuet repoihin $REPO ..."

# ---------------------------
# 1) EPIC
# ---------------------------
EPIC_URL=$(gh issue create \
  --repo "$REPO" \
  --assignee "$ASSIGNEE" \
  --title "EPIC — Uutiskirjeet & AcyMailing (Essential)" \
  --label epic \
  --label email \
  --label newsletter \
  --label "plugin: acymailing" \
  --body "## 🎯 Tavoite
Uutiskirjeet toteutetaan AcyMailing Essential -lisenssillä. Tämä EPIC kokoaa kaikki tehtävät yhteen.

## Sub-issuet
Luodaan automaattisesti skriptillä.
")

EPIC_NUMBER=$(echo "$EPIC_URL" | sed 's/.*#\([0-9]\+\).*/\1/')
echo "EPIC luotu: #$EPIC_NUMBER"

# ---------------------------
# 2) Sub-issuet
# ---------------------------

ISSUE1=$(create_issue \
  "AcyMailing-lisenssin uusiminen ja aktivointi (dev + prod)" \
  "task,plugin: acymailing,email,newsletter" \
  "Varmista lisenssin aktivointi dev- ja prod-ympäristöissä."
)

ISSUE2=$(create_issue \
  "AcyMailing-pluginin puhdas asennus (dev + prod)" \
  "task,plugin: acymailing,environment: dev,environment: prod" \
  "Asenna plugin puhtaasti deviin ja prodiin. Testaa näkymät."
)

ISSUE3=$(create_issue \
  "SMTP- ja lähetysrajojen asetukset (18 mailia/h)" \
  "task,email,configuration" \
  "Aseta throttle 18/h ja testaa bounce/virhelogit."
)

ISSUE4=$(create_issue \
  "Rytköset-uutiskirjepohja" \
  "design,frontend: layout,newsletter,email" \
  "Suunnittele ja toteuta uusi uutiskirjepohja."
)

ISSUE5=$(create_issue \
  "Mailing-listojen luonti ja segmentointi" \
  "task,newsletter,data" \
  "Luo listat + segmentointi ja import devistä prodin."
)

ISSUE6=$(create_issue \
  "Automaatiot: kuittiviestit ja lomakevahvistukset" \
  "task,automation,newsletter,email,frontend: forms" \
  "Luo tervetulo-, kuitti- ja tapahtumaviestit."
)

ISSUE7=$(create_issue \
  "Testilähetykset devissä" \
  "testing,newsletter,email,environment: dev" \
  "Lähetä testiviestit hallitukselle."
)

ISSUE8=$(create_issue \
  "Tuotantoonvienti ja ensimmäinen lähetys" \
  "task,newsletter,email,environment: prod" \
  "Vie listat ja tee yksi oikea lähetys."
)

ISSUE9=$(create_issue \
  "Dokumentaatio: uutiskirjejärjestelmä" \
  "documentation,enhancement,newsletter" \
  "Kirjoita ohjeet uutiskirjeen lähettämiseen ja ylläpitoon."
)

# ---------------------------
# Tuloste lopuksi
# ---------------------------

echo
echo "Luodut issuet:"
echo " EPIC: #$EPIC_NUMBER"
echo " 1)  #$ISSUE1"
echo " 2)  #$ISSUE2"
echo " 3)  #$ISSUE3"
echo " 4)  #$ISSUE4"
echo " 5)  #$ISSUE5"
echo " 6)  #$ISSUE6"
echo " 7)  #$ISSUE7"
echo " 8)  #$ISSUE8"
echo " 9)  #$ISSUE9"

echo
echo "Lisää nämä EPICin bodyn checklistiksi:"
echo "- [ ] #$ISSUE1"
echo "- [ ] #$ISSUE2"
echo "- [ ] #$ISSUE3"
echo "- [ ] #$ISSUE4"
echo "- [ ] #$ISSUE5"
echo "- [ ] #$ISSUE6"
echo "- [ ] #$ISSUE7"
echo "- [ ] #$ISSUE8"
echo "- [ ] #$ISSUE9"
