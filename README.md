# Rytkösten sukuseura – uusi verkkosivusto

Tämä repo sisältää Rytkösten sukuseuran uuden verkkosivuston kehitystyön.
Tavoitteena on päivittää vanha Joomla 3 -pohjainen sivusto moderniksi
WordPress + WooCommerce -ratkaisuksi, jossa on:

- sukuseuran esittely ja ajankohtaiset
- verkkokauppa (sukulehdet, sukukirjat, muut tuotteet)
- jäsenyys (perhe, yksityinen, ainaisjäsenyys)
- digitaaliset lehdet (verkkoversiot Rytkösten sukulainen -lehdestä)
- keskustelufoorumi
- uutiskirjeet (AcyMailing)
- valokuvagalleria, myös videot

## Teknologiat

- WordPress (uusin versio)
- Oma teema / lapsiteema (GeneratePress/Astra tms.)
- WooCommerce
- Custom plugin:
  - jäsenyyslogiikka (custom user role + WooCommerce-integraatio)
  - digilehti-sisältötyypit (CPT: issue + article)
- AcyMailing (uutiskirjeet)
- GitHub Projects (backlog & Kanban)
- GitHub Actions (CI/CD) – *tulossa myöhemmin*

## Projektin rakenne (suunniteltu)

```text
/theme
  rytkoset-theme/          # Sukuseuran teema

/plugins
  rytkoset-membership/     # Jäsenyyslogiikka + roolit
  rytkoset-digilehdet/     # Digilehdet (issue + article CPT)

docs/
  project-overview.md
  architecture.md
  membership-system.md
  digilehdet.md
