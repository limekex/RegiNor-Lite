# Gjennomgang av M0–M4

Gjennomført 20. september 2026 mot arbeidskopien. M0–M4 har et fungerende lokalt grunnlag, men er **ikke samlet godkjent for produksjon**. Konkrete frontendfeil er rettet, kontrollverktøyet er utvidet og utdaterte roadmap-påstander er korrigert. [M4.1 med permalenker/SEO](PERMALENKER-OG-METADATA.md) er fortsatt en separat planlagt leveranse.

## Status per milepæl

| Milepæl | Kontrollert og lukket lokalt | Gjenstår før godkjenning |
| --- | --- | --- |
| M0 | Bootstrap, lint, domenetester, låsefiler, CI-definisjon og installasjonspakke | GitHub-kjøring av levert revisjon. CI- og pluginfilene er ennå ikke sporet i Git i denne arbeidskopien; en tidligere grønn kjøring ville derfor ikke verifisert disse endringene. |
| M1 | Lesende miljørapport, private registreringer og test av samspill med installert TEC | Faktiske Avada/Core/Builder-, WPML-, SEO- og cacheversjoner, rutekollisjoner i staging, profilreferanser og innbygging. |
| M2 | Kalender, lokale tider/DST, opphold, kollisjoner, versjoner, historikk, øktidentitet og kopiering | Ingen ny lokal funksjonsmangel identifisert i gjennomgangen. Faktiske opplysninger for neste periode må kvalitetssikres. |
| M3 | Periode-/kursarbeidsflyt, publiseringsfeil, noncer/rettigheter, versjonskonflikter, adminmenyer og oversettelseskontrakt | Faktisk WPML, visuell/tastatur-/skjermleserprøve og A15 med kursansvarlig. |
| M4 | Offentlig katalog, anonym HTTP, canonical/schema, synlighet, cacheheadere, farger, kart og lokal TEC-integrasjon | Avada/WPML/SEO/CDN-staging, eksterne schema-/delingskontroller og manuell tilgjengelighets-/brukerprøve. |

Avkrysninger betyr implementert og prøvd innenfor oppgitt miljø, ikke at alle akseptansekriterier er godkjent. M5-kapasitet og webhooks er ikke del av denne gjennomgangen.

## Hull rettet i kode

### Påmelding viste feil åpningsdato

Statuskontrollen brukte kursets og periodens samlede salgsvindu, mens teksten på kursdetaljen bare brukte periodens åpning. Et kurs som åpnet senere kunne derfor annonsere feil dato.

Teksten bruker nå samme `SalesWindow::bounds()` som statusberegningen. Bare en fremtidig åpning i et gyldig vindu vises med dato. En redaksjonell «åpner senere»-status får ikke en oppdiktet dato fra et allerede passert periodetidspunkt. Testene dekker senere kursåpning, passert åpning og et tomt salgsvindu.

### Nøkkelfakta og leserekkefølge på kursdetaljen

CSS plasserte allerede påmeldingsboksen først på mobil, men HTML-/tastaturrekkefølgen hadde beskrivelse og alle kurskveldene først. Faktaboksen manglet også faktisk oppstartsdato.

Fakta og påmelding kommer nå først i HTML, med faktisk første kursdato. Desktop beholder to kolonner. Testen kontrollerer at oppstart, pris-/påmeldingsboks og hovedhandling kommer før lang beskrivelse. Dette er automatisk delbevis for A2/A3, ikke en full skjermleser- eller visuell godkjenning.

### Innbygde oversikter fikk cachekontroll for sent

Den valgte hovedsiden fikk tidlig `no-store`, men shortcoden på en annen side forsøkte først å sette headere under rendering. Da kan temaet allerede ha sendt responsen.

En tidlig kontroll oppdager nå direkte shortcode, kursblokk og synkroniserte blokker i det lagrede innholdet på andre enkeltsider. Referanser kontrolleres med syklusvern og dybdegrense. HTTP-prøven kontrollerer synlig kursinnhold og `no-store` på både ekstra shortcode-side og side med en nestet synkronisert kursblokk.

Innbygging fra temaets malfiler eller Avadas globale oppsett kan ikke alltid oppdages fra sidens innhold. Disse sidene må fortsatt identifiseres og unntas fra fullsidecache/CDN i staging. Et cachelag som svarer før WordPress kjører, må konfigureres separat.

### Miljørapporten manglet konkrete oppsettskontroller

`npm run inspect:wordpress` rapporterer nå også databaseversjon, miljøtype, cacheflagg, forventet form på RegiNors private innholdstyper, eierskap til shortcode, blokkregistrering, status/innbygging for valgt kursside og registrerte instruktørtyper. Ingen kursinnhold, passord eller generelle opsjonsdumper tas med.

Struktursamsvar alene beviser ikke at ingen annen plugin kan kollidere med rutene. Rapporten er et grunnlag for stagingkontrollen, ikke automatisk godkjenning.

## Faktisk lokalmiljø

Lesende uttrekk fra Docker 20. september 2026:

| Del | Observert |
| --- | --- |
| WordPress / PHP | 7.1 / 8.2.29 |
| Database | 12.3.3 rapportert av databaseforbindelsen |
| Tema | Twenty Twenty-Five 1.5, uten child theme |
| RegiNor Lite | 0.1.0, aktiv |
| The Events Calendar | 6.17.5, aktiv |
| Avada, ACF, WPML og SEO-plugin | Ikke installert i dette lokalmiljøet |
| Språk / nettstedstidssone | `nb_NO` / `+00:00`; kursprøvene bruker eksplisitt `Europe/Oslo` |
| Permalinker | `/%year%/%monthnum%/%day%/%postname%/` |
| Persistent objektcache | Ikke aktiv i uttrekket |
| RegiNor-oppsett | Alle fem innholdstyper har forventet privat struktur. Shortcode og blokk er registrert. Valgt kursside er publisert, uten passord, med innbygging. Ingen instruktørtyper er konfigurert. |

Dette bekrefter ikke produksjonsmiljøet. Hele rårapporten holdes utenfor repoet; ingen produksjonsinnstillinger er endret.

## Testbevis fra denne gjennomgangen

Alle WordPress-/HTTP-prøver bruker syntetiske objekter og rydder opp etter seg. De ble kjørt sekvensielt mot samme miljø. Kartets eksterne søk er simulert, og ingen LetsReg-innlogging eller leverandørdata ble hentet.

| Kontroll | Resultat |
| --- | --- |
| `composer check` | 156 tester / 282 assertions, lint og Composer-validering bestått |
| `test:interface` | Alle ni JS-/DOM-testskript bestått |
| `test:wordpress` | Plugin-/tilgangssjekk bestått |
| `test:storage` | 58 kontroller bestått |
| `test:workflow` | 86 kontroller bestått |
| `test:admin` | 91 kontroller bestått |
| `test:admin-menu` | 52 HTTP-kontroller bestått |
| `test:http` | 40 kontroller bestått |
| `test:public` etter rettinger | 50 kontroller bestått |
| `test:public-http` etter rettinger | 41 kontroller bestått |
| `test:appearance` | 46 kontroller bestått |
| `test:maps` | 242 kontroller bestått |
| `test:tec` / `test:tec-http` | 35 / 15 kontroller bestått |

WP 6.8 og hele PHP-matrisen ble ikke kjørt på nytt i denne gjennomgangen. Tidligere sprintbevis beholdes som historikk. Nytt forsøk på nettlesertilkobling feilet med `Cannot redefine property: process`; ingen nye skjermbilder eller visuelle godkjenninger foreligger.

## Sporbarhet til akseptansekriteriene

| Kriterier | Bevis og avgrensning |
| --- | --- |
| A4/A5/A7/A8, R9/R11 | Domenetester og lagring/arbeidsflyt: kalender, DST, konflikter, unntak og identitet. Lokalt kontrollert. |
| A9/A13, R1–R3/R10 | Arbeidsflyt, lagring, admin og HTTP: kopiering, versjoner og avgrenset tilgang. Faktisk rolletildeling i staging gjenstår. |
| A2/A3/A6/A10/A11, R4–R8 | Offentlig modell og anonyme HTTP-prøver gir delbevis for innhold, tid/status og skjuling. A6s samlede kalender-/språkvisning og brukerens faktiske reise må inngå i stagingprøven. |
| A12, I7 | Lokale schema-/canonical-kontroller bestått; eksterne validatorer og faktisk SEO-plugin gjenstår. |
| U1–U4/U6/U7 | Renderer-, DOM-, utseende- og kalenderprøver gir automatisk delbevis. Visuell plassering og forståelighet må kontrolleres manuelt. |
| A1, U5/U8 | Mobil, zoom, tastatur og skjermleser er fortsatt åpne manuelle kriterier. HTML-rekkefølgen er rettet, men dette godkjenner ikke hele kriteriet. |
| A15 | Må gjennomføres med kursansvarlig; utviklerstyrt HTTP-prøve er ikke en brukertest. |
| R12, I6/I10 | Lokalt prøvd synlighet, utløp og cacheheadere, inkludert TEC. Faktisk CDN/Avada og nettleseropplevelse gjenstår. |
| I1–I5/I8/I9/I11/I12 | Lokale registrerings-/rolle-/TEC-prøver og fravær av ACF gir delbevis. Ingen ACF-avhengighet var nødvendig for testene; gamle produksjonsruter og faktisk tilbakeføring må fortsatt prøves i staging. |
| A14 | Utgår etter beslutningen om ingen migrering av gamle kurs. Ny LetsReg-import er en separat arbeidsflyt under M5.2. |

## Gjenstående kontroller med krav til bevis

| ID | Milepæl / ansvar | Handling og nødvendig bevis |
| --- | --- | --- |
| G01 | M0 / utvikler | Kjør GitHub CI etter at revisjonen er levert dit. Noter commit-ID, kjøringslenke og resultat for hele WP-/PHP-matrisen. Lokal grønn test er ikke et CI-resultat. |
| G02 | M1 / drift + utvikler | Kjør lesende kartlegging på staging med faktisk tema og plugins. Noter versjoner og innbyggings-/profilvalg. Kontroller registreringer, eksisterende ACF-/TEC-ruter og valgt ny kursinngang uten å endre gammel kursløsning. |
| G03 | M1/M3 / kursansvarlig + administrator | Kontroller reelle kursbeskrivelser, nivå, pris per person/par, tillegg, partnerpraksis og LetsReg-lenker. Administrator må klargjøre delte beskrivelser/saler; kursansvarlig kan deretter gjennomføre periodeflyten. |
| G04 | M3/M4 / utvikler | Prøv WPML String Translation og språkbytte med faktisk plugin. Dokumenter oversatt tekst, riktig kursside og uendrede ID-er/økter/priser; kontroller canonical/cache per språk. |
| G05 | M4 / drift + utvikler | Unnta hovedside, alle innbyggingssider og sitemap fra CDN/fullsidecache. Besøk anonymt før/ved/etter synlighets- og salgsgrensene med varm cache; lagre headere og observer at skjult kurs/påmeldingsknapp ikke gjenbrukes. |
| G06 | M4 / utvikler | Prøv Avada-palett, alpha, kursfarger, saler, drop-in og TEC-kategori/bilde i faktisk oppsett. Sammenlign HTML/schema/canonical med SEO-plugin og eksterne validatorer; noter eventuelle duplikater. |
| G07 | M3/M4 / UI-kontroll | Prøv 320/390 px, 200 % tekst/zoom, tastatur, skjermleser, fokus ved retur, popover og redusert bevegelse. Lag skjermbilder og konkrete funn. Automatisk DOM-prøve lukker ikke denne raden. |
| G08 | M3/M4 / prosjekteier + testdeltakere | Gjennomfør A15 og deltakerreisen med 1–2 kursansvarlige og 4–6 deltakere. Observer uten veiledning, noter stopp/feiltolkninger og rett funn før godkjenning. |

G02–G08 kan ikke erstattes med antakelser om et annet miljø. M0–M4 markeres ikke som fullført før de relevante kontrollene er dokumentert. Ingen av disse radene krever migrering av gamle kurs eller utvikling av live LetsReg-kapasitet.
