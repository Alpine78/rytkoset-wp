# Commit-viestien formaatti (Conventional Commits)

Käytämme automaattista changelogin generointia.  
Siksi commit-viestien tulee noudattaa muotoa:

### Perusformaatti
<type>(optional scope): <description>

### Sallitut type-kentät
- feat — uusi ominaisuus
- fix — bugikorjaus
- docs — dokumentaatio
- style — ulkoasun muutos ilman logiikkaa
- refactor — koodirakenne, ei uusia ominaisuuksia
- perf — suorituskykyparannus
- test — testikoodit
- chore — ylläpitotehtävät

### Esimerkkejä
feat(events): lisää yksittäisen tapahtuman template  
fix(woo): korjaa membership-tilauksen tallennus  
docs: päivitä README staging-ohjeilla  
