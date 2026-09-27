# Forslag: Salsakurs for nybegynnere i Oslo

Redaksjonelt utkast, 27. september 2026. Ikke publisert. Kursdatoer, klokkeslett, pris og tilgjengelighet skal komme fra RegiNor, ikke kopieres inn i løpende tekst.

## Grunnlag og prioritering

RegiNors innloggede [statistikkrapport](https://www.salsanor.no/wp-admin/admin.php?page=rnl-analytics) ble lest 27. september. Rapporten dekker målte besøksøkter med samtykke de siste 30 dagene. Rapporten viste 76 besøksøkter, 18 med kursoversikt, 8 med kursdetaljer og 4 med LetsReg-klikk. Alle fire LetsReg-klikk i utsnittet var knyttet til testkampanjen `rnl_test / qa / gtm_oppsett`. Google/cpc hadde 16 målte besøksøkter, men ingen registrerte kursdetaljer eller LetsReg-klikk. Dette er ikke bevis for at annonsene ikke gir påmeldinger: gamle kurs, samtykke, blokkering og manglende trinn kan påvirke målingen. Ingen salg er knyttet til enkeltbesøk. Det finnes foreløpig ikke et robust datagrunnlag for å rangere tekster eller kurs etter konvertering.

Redaksjonell anbefaling: vis konkrete kurs tidlig, gjør forskjellen mellom intro og grunnkurs tydelig, og svar kort på usikkerhet om partner og forkunnskaper. Dette er hypoteser å prøve, støttet av temaene SalsaNor allerede skriver om, ikke en påstått effektmåling.

Eksisterende [nybegynnerside](https://www.salsanor.no/niva/nybegynner/) beskriver grunntrinn, rytme, føring og følging uten krav om erfaring. [Salsa Intro](https://www.salsanor.no/niva/salsa-intro/) presenteres som en smakebit før et lengre kurs. De gamle sidene har også historiske kursopplysninger; disse brukes ikke som grunnlag for nye datoer eller priser.

## Sideinnhold i anbefalt rekkefølge

### 1. Første skjerm

**Salsakurs for nybegynnere i Oslo**

Lyst til å lære salsa? Start med grunntrinnene hos SalsaNor. Du trenger ingen danseerfaring og kan komme uten partner. Vi øver på rytme, samspill og enkle figurer, steg for steg.

**Hovedknapp:** Finn ditt nybegynnerkurs → `#nybegynnerkurs`

**Sekundær tekstlenke:** Vil du prøve først? Se Salsa Intro → `#salsa-intro`

Bruk ett ekte, godkjent bilde fra et nybegynnerkurs med plass til tittelen. Hold første skjerm kompakt; la starten på kursutvalget bli synlig uten lang scrolling.

### 2. Finn kurset som passer deg

**Lær salsa over flere kurskvelder**

På grunnkurset bygger du ferdighetene litt etter litt. Du får tid til å øve på grunntrinn, rytme og dans i par. Velg kurset som passer hverdagen din, og se tid, sted, pris og instruktør på kurskortet.

**[Dynamiske nybegynnerkort fra RegiNor]**

Plasser disse ved ankeret `nybegynnerkurs`. Vis dag, tid, antall kvelder, sted/sal, nivå, instruktør, pris og status. Ingen filtre eller visningsvelger. Bruk kursets vanlige detaljer/påmeldingshandlinger, slik at eksisterende måling følger med.

### 3. Vil du prøve først?

**Få en smakebit med Salsa Intro**

Nysgjerrig på salsa, men vil prøve før du velger et lengre kurs? På Salsa Intro får du en introduksjon til dansen. Se de aktuelle introkursene nedenfor for innhold, varighet og pris.

**[Dynamiske, fremhevede introkurs fra RegiNor]**

Anker: `salsa-intro`. Ikke kall intro gratis eller lov en bestemt rabatt uten at dette gjelder akkurat kurset som vises. Hvis ingen introkurs er publisert, fjern introhenvisningen i toppområdet og vis en kort beskjed om at nye datoer kommer.

### 4. Litt usikker før første kveld?

**Må jeg ha med en partner?**

Nei. Du kan melde deg på alene. På nybegynnerkurset får du øve med ulike dansepartnere. Les mer om [å starte uten dansepartner](https://www.salsanor.no/2025/04/24/salsa-for-single-derfor-trenger-du-ingen-partner-som-nybegynner/).

**Hva om jeg ikke kan trinnene eller finner rytmen?**

Du kommer for å lære. Vi begynner med grunnlaget, og rytme og trinn er en del av det vi øver på sammen.

**Hva bør jeg ha på meg?**

Velg klær du kan bevege deg i, og ta med innesko. Se også praktisk informasjon på det valgte kurset.

De to siste svarene bygger på [tipsene før første salsakurs](https://www.salsanor.no/2025/03/24/10-ting-jeg-skulle-onske-jeg-visste-for-mitt-forste-salsakurs/). Bruk eksisterende FAQ-innlegg som lenkemål når riktig innlegg er valgt i administrasjonen. Ikke lag nye, antatte FAQ-adresser.

### 5. Bli litt bedre kjent med kursopplevelsen

To rolige artikkelkort med bilde, kort ingress og tydelig lenke:

- **Før ditt første salsakurs** – praktiske tips om klær, sko og hva du møter første kveld. Lenke til artikkelen med ti nybegynnertips ovenfor.
- **Du trenger ingen dansepartner for å starte** – hva det betyr å komme alene og øve med andre. Lenke til partnerartikkelen ovenfor.

Plasser artiklene etter kursutvalget. De skal hjelpe dem som trenger mer informasjon, uten å skyve kursene nedover. Avada kan vise disse manuelt valgte innleggene; RegiNors nivåarkiv har eget oppsett for relaterte artikler og FAQ, men kurskortkoden alene viser ikke dette tilleggsinnholdet.

### 6. Avslutning

**Klar for å ta de første stegene?**

Finn en kurskveld som passer deg. Du ser praktiske detaljer før du går videre til påmelding hos LetsReg.

**Knapp:** Se nybegynnerkursene → `#nybegynnerkurs`

## Oppsett i Avada og RegiNor

Legg kortkodene i hvert sitt Text Block-element. Eksemplene forutsetter at de norske nivånavnene faktisk er «Nybegynner» og «Intro»; bytt til korrekte nivå-ID-er eller kildenavn fra **Kursinnhold og ressurser → Kursnivåer** før bruk. Nivåene er ikke de gamle ACF-taksonomiene.

```text
[reginor_courses styles="salsa" levels="nybegynner" exclude_levels="intro" default_view="list" allowed_views="list" show_header="0" show_filters="0" show_view_switch="0"]

[reginor_courses styles="salsa" levels="intro" featured="only" default_view="list" allowed_views="list" show_header="0" show_filters="0" show_view_switch="0"]
```

Fra 0.1.34 kan dansestil kombineres med nivå: `styles="salsa"` begrenser begge utvalg til kurs der hovedspråkets dansestil er «Salsa». Velg riktig eksisterende stil under **RegiNor Lite → Kortkodegenerator** hvis kilden bruker et annet navn, for eksempel «Cubansk salsa». Generatoren lager kortkoden med aktuelle valg. Kontroller fortsatt at nivå og forkunnskaper passer målgruppen; Rueda-kurs skal ikke komme med i en annonse for grunnkurs i salsa.

Begge kortkodene følger pluginens vanlige periodevalg, publisering og synlighetsvinduer. De viser ikke alle fremtidige perioder samlet. Kontroller at riktig kampanjeperiode velges, særlig ved overgang mellom to kursrunder. Skjuling av tom introseksjon er en redaksjonell oppgave i denne versjonen.

Forslag til metatittel: **Salsakurs for nybegynnere i Oslo | SalsaNor**

Forslag til metabeskrivelse: **Lær salsa fra grunnen av i Oslo. Du trenger ingen erfaring eller partner. Se nybegynnerkurs og Salsa Intro med tid, sted og pris.**

Avklar én foretrukket adresse og canonical i Yoast så kampanjesiden og nivåarkivet ikke konkurrerer med samme identiske tekst. Ikke endre eksisterende nivåadresser som del av dette forslaget.

## Måleplan

Bruk landingssiden som annonsens direkte mål. Gi kampanjene tydelige navn: for eksempel `utm_source=google&utm_medium=cpc&utm_campaign=salsa_nybegynner_host3_2026&utm_content=uten_partner`. Bruk `meta / paid_social` for betalte Meta-annonser og en egen `utm_content` per budskap. Ikke legg nye UTM-parametere på interne lenker fra landingssiden.

Skill QA-trafikk fra ordinære kampanjer i analysen. Mål samtykkede landinger, kursoversikt, kursdetaljer og LetsReg-klikk. Se på absolutte antall før prosenter når datagrunnlaget er lite. LetsReg-klikk er interesse, ikke kjøp. Ordresum/deltakertall i hybridrapporten er aggregert utvikling, ikke attribuerte salg.

Første innholdseksperiment: «Kom uten partner» mot «Prøv Salsa Intro». Hold kursutvalg og øvrig sideoppsett likt, og vurder først når begge variantene har reell trafikk utover testøkter. Ingen vinner eller lønnsomhet kan fastslås fra dagens utsnitt.

Før annonsering: kontroller mobil, riktig kursutvalg og periode, tomme utvalg, CTA-ankre, samtykke og faktisk hendelsesflyt til GTM. Dette er en publiseringskontroll som gjenstår; forslaget er ikke lagt ut på nettsiden.
