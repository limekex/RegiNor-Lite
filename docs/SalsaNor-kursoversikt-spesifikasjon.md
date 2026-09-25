# RegiNor Lite – kursoversikt og kursadministrasjon for SalsaNor

Beslutningsgrunnlag og implementeringsspesifikasjon, versjon 1.2 – 17. september 2026.

**Gjeldende presisering fra prosjekteier, 17. september 2026:** RegiNor Lite tas i bruk **fra neste nye kursperiode, uten migrering av eksisterende kurs eller ACF-data**. Nye perioder opprettes direkte i RegiNor med egen kursmodell, lagring, validering og administrasjon uten runtime-avhengighet til ACF. Gamle kurs og URL-er beholdes i dagens løsning. Avada og The Events Calendar videreføres. Denne beslutningen erstatter dokumentets tidligere krav om kursimport, ACF-feltmapping, overtakelse av gamle kursregistreringer og migreringskriteriet A14; kapittel 12 brukes bare for relevante deler om staging, testing og tilbakeføring av ny visning. Referanser til `MigrationRunner` betyr ikke at en kursimport skal bygges. Kartlegging gjelder kompatibilitet, navnerom, maler, cache, roller og eventuelle referanser til eksisterende instruktører/steder. Se [gjeldende roadmap](ROADMAP.md) og [samspillsplan](SAMSPILL-AVADA-ACF-TEC.md).

**Ny kontroll-/UI-/språkavklaring fra prosjekteier, 18. september 2026:** Instruktør er valgfritt. Kurskvelder utenfor synlighetsvinduet og påmelding etter kursstart gir bare informasjon, uten blokkerende kontroll eller særskilt godkjenning. Faktisk offentlig synlighet og salg følger fortsatt sine vinduer. «Gruppe» heter «Kurs» i grensesnittet. Perioder vises i tabell med nyeste først, grønn aktiv/gul neste, dager igjen/til start, navn/sted/datoer og handlinger. Hver periode har egne oppgavesider og kurstabell. Hele pluginen klargjøres for I18n/WPML. Dette erstatter tidligere motstridende kontrollkrav i blant annet kapittel 19. Se [brukerflyt og språk](BRUKERFLYT-OG-SPRAK.md).

**Tillegg fra prosjekteier, 18. september 2026:** Både backend og frontend skal være svært brukervennlig og intuitiv for personer med lite eller ingen digital kompetanse, og samtidig elegante og visuelt tiltalende. Dette er et styrende krav for alle sprinter. Se [brukeropplevelse og akseptanseprøver](BRUKEROPPLEVELSE.md); oppfyllelse skal bekreftes med målgruppen før lansering.

## 1. Anbefaling og avgrensning

Bygg en liten WordPress-plugin som organiserer **kursrunde → kursgrupper → kursdatoer**, med gjenbrukbare kursbeskrivelser. La LetsReg fortsette å håndtere påmelding og betaling. Gi deltakeren en enkel kursliste og kursansvarlig en samlet arbeidsflate for hver runde.

Et helt nytt nettsted er ikke en forutsetning. Kursoversikten, kursdetaljene og inngangen fra forsiden kan bygges om innenfor eksisterende nettsted. Om temaets mobilmeny eller sidebygger krever større inngrep, må avgjøres etter inspeksjon av WordPress-installasjonen.

Gjenbruk domenemodellen og gode prinsipper fra salsanor-tickets. Ikke porter hele Next.js-applikasjonen til WordPress. Første versjon skal ikke inneholde handlekurv, kundekonto, betalingsintegrasjon, QR-billetter, deltakerregister eller egen venteliste.

**Grunnlag og begrensninger:** GitHub-koden er lest på `main`, commit `cd27526c15260a4bd4eda0d3a777da5c0c12d292`. Nettsiden på salsanor.no kunne ikke leses gjennom nettverktøyet; det rapporterte begrenset tilgang/robots.txt. Dette dokumentet inneholder derfor ikke en verifisert visuell mobilrevisjon, ytelsesmåling eller kartlegging av nettstedets nåværende schema. Nettsidevurderingen bygger på opplysningene og tilbakemeldingen brukeren har gitt. Blokkeringen her dokumenterer ikke at Googlebot er blokkert.

Alle navn, priser, datoer og timeplaner brukt i skisseeksempler er demonstrasjonsdata, ikke SalsaNors bekreftede kurstilbud.

## 2. Hva som bør endres i brukerreisen

Den rapporterte vanskeligheten tyder på at siden ber deltakeren forstå for mange deler av organiseringen før vedkommende kan velge kurs. Det er en arbeidshypotese som skal prøves med faktiske brukere.

| Opplysning fra bruker/kursgruppe | Vurdering | Foreslått endring |
| --- | --- | --- |
| Rom, nivå og tidsluker er taksonomier | Nivå passer som klassifisering; tid trenger faktiske verdier og datoer | Behold nivå som strukturert kategori, lagre tid og kalender som data |
| Vanskelig å finne kurs på mobil | En samlet timeplan kan kreve oversettelse mellom rom, dag, nivå og påmelding | Standardvisning med lesbare kurskort i én kolonne |
| Førstegangsbesøkende trenger tydelig inngang | «Hvilket kurs passer meg?» må besvares tidlig | Synlig valg «Jeg er nybegynner» og direkte inngang til nybegynnerkurs |
| Forsidebilder beveger seg | Bevegelse kan konkurrere med hovedhandlingen | Foreslå ett rolig bilde og tydelig «Finn kurs i Oslo»; vurder faktisk forside før bygging |
| Flytteinformasjon, drop-in og praktiske knapper tar plass | Utdatert innhold bør ryddes; praktisk informasjon trenger riktig plass | Fjern foreldet flyttenotis; flytt relevant transport- og stedsinformasjon til kursdetaljer |
| «Kom i gang med salsa» vurderes flyttet | Godt introduksjonsinnhold bør støtte påmelding | Kort inngang på forsiden og permanent nybegynnerside som viser aktuelle kurs automatisk |

Ikke fjern adresse fra kursvalget. Deltakeren må fortsatt se hvor kurset foregår. Det er separate knapper og lange forklaringer som kan flyttes ned. Drop-in bør bare vises dersom det faktisk tilbys i den aktuelle runden, med egen tydelig kontekst.

### Foreslått offentlig flyt

1. Forside: «Finn kurs i Oslo» som hovedhandling. Sekundær inngang: «Ny på salsa? Start her».
2. `/kurs-i-oslo/`: gjeldende synlige kursperiode, ellers neste synlige kommende periode. Tilgjengelige fremtidige perioder kan velges etter kapittel 19. Vis kurskort eller valgt ukeskalender. Valget «Jeg er nybegynner» filtrerer listen; «Vis alle kurs» er alltid tilgjengelig.
3. Kursdetalj: nivå, datoer, klokkeslett, antall kurskvelder, adresse, pris, instruktører og påmeldingshandling.
4. LetsReg: registrering og betaling. Lenken forteller tydelig at deltakeren går videre til LetsReg.

Ingen obligatorisk veiviser eller innlogging for å se kurs. En kjent deltaker skal kunne gå rett til sin gruppe. En ny deltaker skal slippe å forstå «paralleller» og interne kurskoder.

### Kurskort, i denne rekkefølgen

- Tittel og forklarende nivå: «Salsa – nybegynner» / «For deg som ikke har danset salsa før».
- Ukedag og klokkeslett, skrevet med ord og vanlig tidsformat.
- Første kursdato og antall kurskvelder; vis siste dato på detaljsiden.
- Kurssted og pris for hele rekken, med nødvendige prisvilkår.
- Én tydelig hovedhandling: «Se kurset og meld deg på».

Rom/sal er nyttig på detaljsiden og i timeplanen, men skal ikke være første filter for en ny deltaker. Start med nivå og eventuelt dag. Dansestil kan legges til når utvalget faktisk krever det. Unngå søkefelt og mange filtre for en liten kursliste.

Kursoversikten skal ha valgene «Kursliste» og «Ukeskalender». Kursansvarlig kan velge standardvisning per innbygging; kursliste anbefales for førstegangsbesøkende. Ukeskalenderen har overordnet dagkolonne, saler som underkolonner og klokkeslett langs venstresiden. På mobil vises dagene under hverandre med salene ved siden av hverandre innenfor hver dag. Se kapittel 17 for periodens faste timeplan, tilgjengelighet og akseptansekriterier.

## 3. Hva GitHub-prosjektet faktisk gir oss

Kildegrunnlaget er kodeinspeksjon, ikke en kjørt eller testet installasjon.

| Funn i repository | Bruk i minivarianten |
| --- | --- |
| `CoursePeriod`: start/slutt, salgsåpning/-stenging, sted og `Europe/Oslo` | Kursrunde med standardverdier og påmeldingsvindu |
| `CourseTrack`: ukedag, start/slutt, nivå, rom, pris og relasjoner | Én konkret kursgruppe med egen side og egen påmeldingslenke |
| `TrackSession`: `startsAt` og `endsAt` | Konkrete kursøkter, som gir korrekte datoer og kollisjonskontroll |
| `PeriodBreak`: tilknytning til periode og/eller gruppe | Opphold på runde- eller gruppenivå |
| Mange-til-mange-relasjon `Instructors` | Flere instruktører per gruppe; gjenbruk eksisterende WordPress-profiler |
| `course-view-toggle.tsx` og `week-calendar-view.tsx` | Inspirasjon til alternative visninger, med annen mobilprioritering |
| `PARTNER`-preset i kursmalene | Gjenbruk språk om fører/følger ved behov; valg og kapasitet håndteres i LetsReg |
| SEO-hjelpere og `CourseInstance` på gruppesiden | Gjenbruk prinsippet om entydige sider og strukturerte data, med egen korrekt implementering |

Konkrete ting som bør forbedres ved gjenbruk:

1. Visningsvelgeren starter i kalender. Kalenderen har `min-w-[600px]` og horisontal scrolling. Tekstetikettene i visningsvelgeren skjules under `sm`. Dette bør ikke overføres til den beskrevne målgruppen.
2. Periodesiden merker hele perioden som `Course`, selv om gruppene kan være forskjellige kurs. I minivarianten skal en kursrunde være en samling, mens «Salsa nybegynner» er et kurs med ett eller flere tilbud.
3. Periodesidens tilbud har hardkodet `InStock`. Minivarianten skal ikke påstå ledig kapasitet bare fordi en påmeldingslenke finnes.
4. Gruppens schema bruker periodens start/slutt og viser ikke opphold i tidsplanen. Bruk gruppens faktiske datoer og unntak.
5. Ukedager valideres som 1–7, men `schemaOrgDay()` forventer 0–6. Søndag (`7`) faller tilbake til mandag. Bruk én dokumentert ukedagskonvensjon og test alle syv dager.
6. SEO-hjelperen har `reginor.events` som fast domene. URL-er, organisasjon og breadcrumbs skal utledes fra denne WordPress-installasjonen.

Dette er konkrete referansefunn, ikke en fullstendig sikkerhets- eller kvalitetsrevisjon av tickets-prosjektet.

Kilder: [datamodell](https://github.com/limekex/salsanor-tickets/blob/cd27526c15260a4bd4eda0d3a777da5c0c12d292/packages/database/prisma/schema.prisma), [visningsvelger](https://github.com/limekex/salsanor-tickets/blob/cd27526c15260a4bd4eda0d3a777da5c0c12d292/apps/web/src/components/course-view-toggle.tsx), [kalender](https://github.com/limekex/salsanor-tickets/blob/cd27526c15260a4bd4eda0d3a777da5c0c12d292/apps/web/src/components/week-calendar-view.tsx), [SEO-hjelper](https://github.com/limekex/salsanor-tickets/blob/cd27526c15260a4bd4eda0d3a777da5c0c12d292/apps/web/src/lib/seo.tsx). Gruppens og periodens sider ligger i `apps/web/src/app/(site)/courses/[periodId]/`.

## 4. Valg av løsning

| Alternativ | Fordel | Ulempe | Vurdering |
| --- | --- | --- | --- |
| Rydde dagens layout og videreføre alle tidsluketaksonomier | Rask første forbedring | Fortsatt manuell kalender, tung rundehåndtering og svak datamodell | Midlertidig tiltak |
| Liten, egen WordPress-plugin | Passer eksisterende nettside, profiler og redaktørarbeid | Krever vedlikehold av avgrenset egen kode | Anbefalt |
| Generell arrangementsplugin | Kan gi kalender og redaktørfunksjoner | Må undersøkes for hele kursrekker, unntak, grupper og ekstern påmelding | Relevant hvis dere ønsker standardprodukt; ingen bestemt plugin er evaluert her |
| Integrere full tickets-plattform | Samlet fremtidig påmelding og kapasitet | Vesentlig større migrasjon og driftsomfang | Eget senere prosjekt |

## 5. Datamodell for WordPress

Pluginnavn: **RegiNor Lite**. Pluginmappe, slug og text domain: `reginor-lite`. PHP-namespace: `RegiNor\Lite`; prefiks for nye felt, hooks og rettigheter: `rnl_`. Tidligere foreslått `snk_` skal ikke brukes i ny kode. Eksisterende produksjonsnøkler skal bevares eller migreres eksplisitt, aldri bare omdøpes. Følgende er målmodell; eksakte eksisterende CPT- og feltnavn må først kartlegges.

| Objekt | Rolle og nødvendige felt |
| --- | --- |
| Kursbeskrivelse | Gjenbrukbar tittel, dansestil, nivå, forkunnskaper, beskrivelse, bilde, partnerinformasjon og lenke til nivåhjelp |
| Kursrunde | Navn, tidssone, foreslått startdato, standard antall kvelder, standard sted/pris, påmeldingsvindu, synlig fra/til, vis som kommende, felles opphold og publiseringsstatus |
| Kursgruppe | Referanse til kurs og runde; offentlig tittel, ukedag 1–7, lokal start/slutt, første dato, antall kvelder, sted/sal, instruktør-ID-er, prisvilkår, påmeldingslenke og redaksjonell påmeldingsstatus |
| Kursøkt | Stabil ID, gruppereferanse, opprinnelig dato, faktisk lokal dato/tid, beregnet UTC-tid, tidssone, status og eventuelle overstyringer av sal/instruktører |
| Kurssted og sal | Gjenbrukbar stedspost med navn og adresse; sal med unik ID og tilhørighet til sted |
| Nivå | Eksisterende taksonomi med rekkefølge, synlig navn og forklaring på forkunnskaper |
| Instruktør | Referanse til eksisterende instruktør-CPT; ingen duplisering av profiler |

### Lagring

Anbefalt MVP: tre hoved-CPT-er for kursbeskrivelse, runde og gruppe. Gjenbruk eksisterende kurs-CPT dersom den representerer varige kursbeskrivelser. Hvis dagens innlegg representerer konkrete gjennomføringer, behold identiteten som grupper og opprett egne gjenbrukbare beskrivelser. Denne betydningen må avgjøres fra faktiske data før migrering.

Kursøkter lagres som en registrert, validert array av objekter i gruppens metadata, med stabile økt-ID-er. Med dette volumet er egne SQL-tabeller unødvendige i første versjon. En datatilgangsklasse skal skjule lagringsformen, slik at økter senere kan flyttes til tabell. Påmeldingsdata skal ikke lagres i denne modellen.

Steder/saler kan bruke eksisterende termer med termmetadata, forutsatt entydige ID-er og sted–sal-relasjon. Nivå og dansestil kan fortsatt være taksonomier. Dato og klokkeslett skal ikke være taksonomier.

Runder og redaksjonelle hjelpedata trenger ikke offentlige arkivsider. Kursgrupper skal ha egne offentlige sider. Pluginen eier funksjonalitet og data; temaet styrer den overordnede profilen. Dette samsvarer med WordPress' anbefaling om å legge innholdstyper i en plugin. [WordPress: registering custom post types](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/).

### Feltkontrakt og arv

- Beløp lagres som heltall i øre og valuta `NOK`. Visningspris må angi «per person», «per par» eller annet korrekt grunnlag og hva som kommer i tillegg.
- Lokal dato er `YYYY-MM-DD`, lokal tid er `HH:mm`, tidssone er `Europe/Oslo` som standard. Ukedag følger ISO: mandag 1 til søndag 7.
- Rundens standardverdier kopieres til gruppen ved opprettelse. Senere endring av en standard endrer ikke allerede publiserte grupper uten eksplisitt masseoppdatering og forhåndsvisning.
- Antall kurskvelder gjelder faktiske planlagte undervisningsøkter. Antall kalenderuker er en beregnet konsekvens.
- Publiseringsstatus, hendelsesstatus og billettstatus er forskjellige felt. «Publisert», «avlyst» og «fullt» betyr forskjellige ting.
- En påmeldingsstatus kan være «åpner senere», «påmelding tilgjengelig», «fullt», «venteliste», «stengt» eller «avlyst». Kapasitetstall er utelatt når de ikke kan bekreftes.
- Lenke lagres per gruppe, også når flere grupper bruker samme LetsReg-arrangement. Et felt angir om lenken gjelder valgt gruppe eller en felles runde.
- Registrer metadata med type, sanitering, tilgangskontroll og REST-skjema. Sikre historikk for hele gruppeoppsettet, inkludert økter; ikke anta at vanlig posthistorikk automatisk dekker metadata i installert WordPress-versjon.

## 6. Kalenderregler og validering

Planleggingsmotoren skal være felles for administrasjon, offentlig visning, schema og eventuell kalenderfil.

**Avklaring 25. september 2026, implementert i 0.1.23:** Et kurs, for eksempel et introkurs med én økt, kan starte før kursperiodens oppgitte start. Kursansvarlig må både angi «En annen første kursdato» og aktivt krysse av «Tillat kursstart før kursperioden». Dato alene er ikke godkjenning, heller ikke ved LetsReg-import. Godkjenningen lagres på kurset i eksisterende historikk og nullstilles ved kopiering til ny periode. Kursets nedre datogrense utvides til den godkjente første datoen; periodens oppgitte datoer og andre kurs endres ikke. Ukedag, kursfrie dager, øktantall, kollisjoner, historikkvern og sluttdato håndheves fortsatt. Synlighets- og salgsvalg er uavhengige og endres ikke. Forhåndsvisning/publiseringskontroll viser unntaket som informasjon. Faktiske økter brukes fortsatt til kursdatoer, offentlig periodesortering, schema og kalenderfiler. TECs samleoppføring beholder kursperiodens oppgitte tidsrom.

1. Finn første valgte ukedag på eller etter ønsket startdato, eller bruk gruppens eksplisitte første kursdato.
2. Generer ukentlige lokale datoer til ønsket antall undervisningsøkter er nådd. Hopp over opphold for runden og gruppen. Bruk en avgrenset søkehorisont og gi forståelig feil dersom oppsettet ikke kan fullføres.
3. Vis beregnet sluttdato før lagring. Hvis et opphold skyver siste kveld, skal dette være synlig. En eventuell absolutt sluttdato skal stoppe publisering dersom antallet ikke får plass.
4. Flytting av én kveld bevarer øktens ID og opprinnelige dato. En avlysning skal ha status og forklaring. Kursansvarlig velger om det skal legges til erstatningskveld.
5. Beregn UTC-offset per dato. Ikke legg til 168 timer i UTC for å lage neste lokale kurskveld; det kan flytte klokkeslettet ved sommertidsskifte.
6. Ved regenerering: vis forskjellene og behold manuelt flyttede/avlyste økter. Ikke overskriv dem lydløst. Avsluttede økter endres ikke av en vanlig fremtidig omplanlegging.
7. Kontroller rom- og instruktørkollisjoner mot faktiske økter i alle relevante aktive runder. To tidsintervaller kolliderer når `a.start < b.end && b.start < a.end`. Tilgrensende økter er tillatt; eventuell riggetid er en eksplisitt regel.

Publisering blokkeres ved manglende nivåforklaring, sted/adresse, ugyldig prisgrunnlag, tom/ugyldig påmeldingslenke for åpen påmelding, feil tidsrekkefølge eller uavklart kollisjon. Salgsåpning må være før salgsstenging. Stenging etter kursstart kan tillates når sen påmelding er et bevisst valg.

Ikke flytt datoer automatisk ut fra generelle helligdager. Kursansvarlig registrerer og bekrefter faktiske opphold. Opphold som ikke treffer noen økt skal ikke redusere antall kvelder.

## 7. Administrasjon for kursgruppa

Ett menyvalg i WordPress: **RegiNor Lite → Kursperioder**. Åpning av en runde viser dens kursgrupper og en enkel timeplan med dag, klokkeslett og sal.

Normal arbeidsflyt:

1. «Kopier forrige kursrunde» eller «Opprett kursrunde».
2. Fyll inn navn, startdato, standard antall kurskvelder, synlighetsvindu og påmeldingsvindu. Velg om perioden skal kunne vises som kommende.
3. Legg til eller juster grupper fra eksisterende kursbeskrivelser.
4. Velg instruktører og sal, registrer opphold, pris og LetsReg-lenker.
5. Se alle faktiske datoer og eventuelle kollisjoner. Forhåndsvis mobilvisningen.
6. Publiser runden når valideringen er bestått.

Tabell/listeskjema skal være fullt brukbart med tastatur. Dra-og-slipp er ikke et MVP-krav. En timeplan er en ekstra oversikt, ikke den eneste måten å endre tidspunkt på.

Kopiering skal opprette ny runde og nye gruppe-/økt-ID-er som kladd. Gjenbruk beskrivelser og profiler, men nullstill LetsReg-lenker, manuell billettstatus, bekreftelser, salgsdatoer og synlighetsvinduer; «Vis som kommende» starter avslått. Tidligere opphold og særflyttinger kopieres ikke automatisk. Det skal være umulig å publisere en kopiert runde med skjulte gamle påmeldingslenker.

Publiseringskontrollen gjelder både enkeltsider, lister, REST, sitemap og schema: en gruppe er offentlig bare når både gruppen og runden tillater det. Kladd skal aldri bli tilgjengelig gjennom en direkte URL eller et ubeskyttet API.

Bruk egne rettigheter for redigering og publisering av kurs. Rollen «Kursansvarlig» får kun rettigheter til kursperiodeoppsett, grupper, økter, opphold og deres publisering. Tilgang til gjenbrukbart innhold er avgrenset til å velge eksisterende oppføringer. Full rettighetsmatrise og krav finnes i kapittel 18. Alle skriveendepunkter skal kontrollere rettigheter på server, nonce der relevant og referanser til riktig runde. Samtidige endringer skal oppdages med versjonskontroll og gi beskjed før noe overskrives. [WordPress: custom REST endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/).

## 8. LetsReg: tydelig overlevering og én sannhetskilde

WordPress er kilde for presentasjon og publisert timeplan. LetsReg er kilde for registrering, betaling, faktisk kapasitet og eventuell venteliste. Pris og dato må kontrolleres mot LetsReg før publisering; automatisk kapasitetssynkronisering er nå spesifisert i kapittel 16. API, kapasitetsfelter og webhook-abonnementer er dokumentert. Kontotilgang, kapasitetssemantikk og kontrakten for webhook-leveranser må verifiseres før tilkobling.

Knapp på detaljsiden: **«Meld deg på hos LetsReg»**. Normal navigasjon i samme fane, slik at tilbakeknappen virker som forventet.

Hvis LetsReg-lenken åpner en felles runde uten forhåndsvalg, vis «Velg [nøyaktig kursnavn] i påmeldingen». Bekreft faktisk støtte for gruppelenker før kodeagenten bruker URL-parametere. Ikke konstruer udokumenterte dyplenker.

En redaksjonell status «påmelding tilgjengelig» betyr ikke bekreftet ledig plass. Ikke vis «3 plasser igjen» eller `InStock` uten tilstrekkelig datagrunnlag. Ved manuelt registrert «fullt» vises alternativer; ventelisteknapp krever bekreftet ventelistelenke. Eventuell fører-/følgerstatus må uttrykkes presist, eksempelvis «Venteliste for følgere». Det skal ikke være en ekstra deltakerregistrering på WordPress før LetsReg.

Måling i MVP: klikk til LetsReg med kurs-/gruppe-ID, uten navn, e-post eller andre personopplysninger. Et utgående klikk skal ikke rapporteres som en gjennomført påmelding. Bekreftede konverteringer krever verifisert integrasjon og avklart definisjon av fullført påmelding; kapasitetssynk alene beviser ikke konvertering fra nettsiden.

## 9. SEO og strukturerte data

Avklaring 21. september 2026: `/kursrekke/` er inngangen til standard kursoversikt og viser samme innhold som `[reginor_courses]`, med gjeldende visningsvalg, filtre og synlighetsregler. Perioder ligger under `/kursrekke/periodenavn/` og enkeltkurs under `/kursrekke/periodenavn/kursnavn/`. Eksisterende kursside og gamle ACF-adresser beholdes.

En CPT er ikke i seg selv SEO-uvennlig. Utfordringen er innholdsmodell, URL-er, faktiske datoer, internlenker, serverrendring og presist samsvar mellom synlig innhold og strukturerte data.

| Side | Innhold og foreslått merking |
| --- | --- |
| `/kurs-i-oslo/` | Stabil, indekserbar oversikt; `CollectionPage`, `ItemList`, breadcrumbs |
| Permanent kursbeskrivelse | Eksempel: salsa nybegynner; `Course` med reelle forkunnskaper og tilbyder |
| Konkret gruppe i en runde | Egen stabil URL; `CourseInstance`, relatert til kursbeskrivelsen, med faktisk kalender og tilbud |
| Enkeltstående workshop/prøvekveld | Egen side; `Event` eller passende undertype med faktisk start/slutt og sted |

Kursbeskrivelse og gjennomføring er forskjellige ting. `CourseInstance` representerer et tilbud på et bestemt tidspunkt/sted og er en undertype av `Event`. Koble gjennomføringen til riktig `Course`; ikke kall en blandet kursrunde for ett kurs. [Schema.org: CourseInstance](https://schema.org/CourseInstance).

Bruk `courseSchedule` med `Schedule` for ukentlig undervisning, med tidsrom, `byDay`, `repeatFrequency`, `scheduleTimezone` og relevante unntak. Ved uregelmessige flyttinger må merking bygges fra faktiske økter, eventuelt med flere tidsplaner/underhendelser; en opprinnelig ukerytme alene må ikke beskrive feil datoer. [courseSchedule](https://schema.org/courseSchedule), [Schedule](https://schema.org/Schedule).

For `CourseInstance` er anbefalt MVP-projeksjon første faktiske økt som `startDate`, siste faktiske økt som `endDate`, samt `courseSchedule` og synlig liste over datoer. Bruk ikke `eventSchedule` som et ekstra synonym: Schema.org sier uttrykkelig at `Event` med `eventSchedule` ikke skal ha separate `startDate`/`endDate` på samme objekt. [eventSchedule](https://schema.org/eventSchedule).

Googles arrangementsfunksjon krever en egen side for hvert arrangement. Et samlet kurs som selges som én rekke skal ikke fremstilles som seks separat kjøpbare billetter. Selvstendig kjøpbare kvelder trenger egen modell og eventuelt egne sider. Google oppgir ikke Norge i regionlisten for den særskilte arrangementsopplevelsen; bedre merking gir derfor ingen garanti om slike søkeresultater. [Google: Event structured data](https://developers.google.com/search/docs/appearance/structured-data/event).

Googles dokumentasjon beskriver dessuten kurslisteutvidelsen som tilgjengelig på engelsk. Bruk derfor korrekt kursmerking for forståelighet og datakvalitet, uten å love et norsk kurskarusellresultat. [Google: Course list](https://developers.google.com/search/docs/appearance/structured-data/course).

Tekniske krav:

- Serverrender kurskort, detaljer og reelle lenker. Påmelding og lesing skal fungere uten JavaScript.
- Hver gruppe har varig canonical-URL, entydig tittel og unik beskrivelse. Ikke flytt gamle gruppers URL til en ny gjennomføring.
- Behold `/kurs-i-oslo/`. Gjør eksakt mapping av gamle kurssider og redirects der innhold faktisk erstattes. Ikke masseomdiriger alle gamle kurs til forsiden.
- Gamle gruppesider merkes «Avsluttet» og lenker til kommende relevante kurs. Indeksering av tynne historiske sider vurderes etter innhold; ingen automatisk masse-noindex uten kartlegging.
- Vanlige filterkombinasjoner er ikke nye SEO-landingssider. Bruk canonical mot riktig oversikt og unngå indeksering av meningsløse kombinasjoner. Nybegynnerinnhold med varig verdi kan ha egen side.
- Bruk riktig tilbyder, adresse, synlig prisgrunnlag, salgslenke og bilde. Obligatorisk medlemskap eller tilleggskostnad må fremgå. Ikke anta at medlemspliktige arrangementer kvalifiserer for Googles arrangementsvisning.
- Avlysning oppdaterer både synlig tekst og hendelsesstatus; «fullt» er tilbudsstatus, ikke avlysning. Flytting beholder identitet og tidligere dato der det er relevant.
- Samordne med installert SEO-plugin slik at det ikke produseres motstridende `Course`, `Event`, canonical eller breadcrumbs.
- JSON-LD serialiseres sikkert, blant annet med beskyttelse mot avsluttende script-tag i redaksjonell tekst. Dynamiske verdier skal aldri settes inn som ukontrollert HTML.
- Test med Schema Markup Validator og relevante Google-verktøy. Godkjent syntaks skal rapporteres separat fra kvalifisering og faktisk synlighet.

## 10. Tilgjengelighet og mobil

Prosjektmål: WCAG 2.2 AA i de leverte flatene, bekreftet med både automatisk og manuell kontroll. Dette er et kvalitetsmål, ikke en juridisk vurdering av hele nettstedet.

- Lesbar standardtekst rundt 18 px og tydelige mellomtitler; ingen nedskalering av en desktop-timeplan.
- Sikt mot minst 44 × 44 CSS-piksler for hovedhandlinger. Dette er et valgt brukbarhetsmål; WCAG 2.2 AA-kriteriet for minimumsmål bruker 24 × 24 med vilkår/unntak. [W3C: target size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).
- Tydelige tekstetiketter på knapper, filtre og status. Ikke ikon som eneste forklaring.
- Test fra 320 px bredde, med tekstforstørring og 200 % zoom, synlig fokus, tastatur og skjermleser.
- Ingen viktig informasjon bare i hover, farge eller animasjon. Respekter redusert bevegelse.
- Åpne/lukk-funksjoner bruker semantiske kontroller. Filtrering annonserer antall treff og bevarer valg ved retur fra detaljsiden.
- Sticky påmeldingsknapp kan vurderes etter testing; den må ikke skjule tekst, fokuserte felt eller samtykkebanner.

## 11. Teknisk leveranse til kodeagenten

Implementer som avgrenset WordPress-plugin med moduler for datatilgang, validering, kalenderberegning, publisering, offentlig rendering, admin, schema og migrering. Ingen avhengighet til Supabase eller Vercel i MVP. Ny frontend rendres i PHP, med begrenset JavaScript for filtrering og administrasjon. Unngå en separat SPA for kurslisten.

Lever en dynamisk blokk og en shortcode for innbygging i eksisterende sidebygger. Begge skal bruke samme renderer. Eksakte integrasjonspunkter velges etter kartlegging av aktivt tema og editor. Norsk bokmål i alle synlige tekster og feilmeldinger; engelsk er greit i interne identifikatorer.

**Avklaring 25. september 2026 – kampanjesider:** Kortkoden skal kunne vise eller utelate bestemte kursnivåer og vise bare fremhevede kurs. Hver innbygging på samme side har sitt eget utvalg, startvisning og valg for å skjule innledning, filtre og visningsbytte. Utvalget håndheves på serveren og kan ikke utvides med URL-filtre. Blokk og kortkode bruker samme renderer. Implementasjonen og attributtene er beskrevet i [kortkodeveiledningen](KORTKODER-OG-KAMPANJESIDER.md); visuell Avada-kontroll er separat fra lokale automatiske prøver.

Foreslåtte interne tjenester: `ScheduleGenerator`, `ConflictValidator`, `PublicationService`, `CourseRepository`, `SchemaPresenter` og `MigrationRunner`. De skal ha én felles, normalisert representasjon av en gruppe og dens faktiske økter.

REST brukes bare der admin eller forbedret filtrering trenger det. Offentlig lesing skal bare returnere publiserte felt. Skriveoperasjoner skal ha rettighetskontroll, full servervalidering og eksplisitt versjon. Returner forståelige feltfeil og bruk konfliktrespons ved utdatert redigering.

Cache skal ugyldiggjøres når grupper, runder, nivåer, steder eller instruktører endres. Åpning/stenging av påmelding og overgang til «avsluttet» skal fungere også ved lav trafikk; ikke baser korrekt status utelukkende på et WP-Cron-kall. Beregn effektiv status ved lesing og sørg for at sidecache ikke varer forbi neste statusendring.

### Kartlegging før implementering

Kodeagenten skal først dokumentere nåværende CPT-er, taksonomier, felt/relasjoner, URL-er, maler, tema/sidebygger, WordPress-/PHP-versjon, SEO-plugin og cache. Ta med én reell eksportert kursrunde og de faktiske LetsReg-lenkene. Verifiser om samme kursnavn finnes i flere grupper og hvordan nivåer/rom brukes.

Denne kartleggingen avgjør adapteren til eksisterende data. Den skal ikke brukes som anledning til å utvide produktomfanget. Behold eksisterende instruktør-ID-er og innholdsidentiteter når de kan gjenbrukes.

## 12. Migrering og innføring

1. Lag sikkerhetskopi og arbeid i staging.
2. Lag en lesbar migreringsrapport med gammel ID/URL, ny rolle/ID, konverterte felt og uløste verdier. Start med tørrkjøring.
3. Ikke gjett datoer eller nivåer fra tvetydig fritekst. Marker til kontroll. Behold originale tidsluketermer til mappingen er bekreftet.
4. Migrer én representativ runde med to dager, to saler og flere nivåer. Verifiser lenker og instruktørrelasjoner.
5. Prøv administrasjonen med en faktisk kursansvarlig. Rett forståelsesproblemer før full migrering.
6. Aktiver ny offentlig visning, kontroller canonical/redirects/sitemap og gjennomgå mobilflyten helt til LetsReg.
7. Behold en enkel tilbakeføring til gammel visning i innføringsperioden. En gjentatt migreringskjøring skal oppdatere samme migrerte objekter, ikke lage dubletter.

Hvis nyhetsbrevet skal ut 3.–4. oktober 2026, er en kontrollert opprydding av forsiden og kursinngangen en fornuftig første leveranse. Full plugin og migrering bør bare lanseres før dette dersom akseptansekriteriene er bestått. Tidsbruk må estimeres etter WordPress-kartleggingen.

## 13. Prototype og brukertest

Anbefalt første prototype skal prøve to oppgaver: finne riktig kurs på mobil og opprette neste runde uten utviklerhjelp. Den kan lages uavhengig av WordPress for å avklare språk og arbeidsflyt.

Prototypen i Nettsteder viser en klikkbar kursliste, kursdetaljer og en forenklet administrasjon med redigering, opphold, datoberegning og kollisjonsvarsel. Den bruker demonstrasjonsdata. Ingen reell påmelding, lagring i WordPress, publisering av kurs eller synkronisering skjer. Selve demonstrasjonsnettstedet publiseres privat i Nettsteder. Administrasjonen og LetsReg-hendelsene er simulert; endringer nullstilles ved ny innlasting. Knappen for rundekopiering demonstrerer en kladd, men oppretter ikke et varig separat kursregister. Skissen implementerer ikke hele spesifikasjonen.

Prototypens JavaScript-syntaks og interaksjonslogikk er kontrollert i et simulert dokumentmiljø: nivå/dag-filter, kursdatoer, forskyvning ved opphold, romkollisjon, escaping av kursnavn, påmelding/avmelding, duplikat, rolleavhengig ledighet, fullt kurs, utdatert bilde og API-feil. Lokale stil- og scriptreferanser er kontrollert. En faktisk nettleser var ikke tilgjengelig for renderkontroll. Responsiv visning, tastaturflyt og skjermleser må derfor fortsatt prøves i nettleser og med brukere. Dette er ikke en test av WordPress-løsningen eller LetsRegs API. En valgfri WebMCP-funksjon er kontrollert med simulert dokumentgrensesnitt, men ikke i en nettleser med faktisk støtte.

Neste test bør gjøres med 4–6 personer fra målgruppen, valgt etter digital trygghet, og 1–2 kursansvarlige. Alder alene skal ikke brukes som forklaring på hva brukerne mestrer.

| Oppgave | Observer |
| --- | --- |
| «Du har aldri danset salsa. Finn et kurs som passer.» | Finner personen nybegynnerinngangen og forstår nivået? |
| «Finn et kurs på en bestemt dag.» | Kan vedkommende sammenligne tider uten hjelp? |
| «Hva koster hele kurset, og hvor mange ganger møtes dere?» | Er prisgrunnlag og antall kvelder forstått? |
| «Gå videre til påmelding.» | Forstår personen overgangen til LetsReg og riktig kursvalg der? |
| «Kopier en runde og legg inn en kursfri uke.» | Oppdager kursansvarlig nye sluttdatoer og krav om nye lenker? |

Mål: minst fire av fem deltakere finner riktig kurs og påmeldingshandling uten veiledning. Kursansvarlig skal kunne gjøre den normale rundekopieringen selv. Målet er et foreslått akseptansekriterium, ikke et allerede oppnådd resultat.

## 14. Akseptansekriterier og avgrensede tester

| ID | Bestått når |
| --- | --- |
| A1 | Kursoversikt og detalj fungerer på 320/390 px uten horisontal scrolling av hovedinnholdet |
| A2 | Tittel, forståelig nivå, dag/tid, startdato, antall kvelder, sted og prisgrunnlag vises før lang omtale |
| A3 | Brukeren kan lese kurs og følge påmeldingslenke uten JavaScript |
| A4 | Seks mandagsøkter fra 12.10.2026 gir 12., 19., 26.10. og 2., 9., 16.11.; opphold 26.10.–1.11. flytter siste økt til 23.11. |
| A5 | Kl. 18.00 forblir lokal kl. 18.00 over overgangen 25.10.2026; UTC-offset beregnes per dato |
| A6 | Søndag blir søndag i kalender, filtre og schema; alle ukedager er testet |
| A7 | Overlapp i samme sal eller for samme instruktør fanges; samtidig undervisning i to ulike saler med ulike instruktører tillates |
| A8 | Flyttet/avlyst økt overlever vanlig regenerering, med stabil ID og synlig status |
| A9 | Rundekopiering gir nye ID-er, kladd og tomme påmeldingslenker; publisering blokkeres til nødvendige data er fylt |
| A10 | Kladd vises ikke i direkte offentlig URL, REST, oversikt, sitemap eller schema |
| A11 | Fullt, stengt, åpner senere og avlyst gir korrekt tekst/handling; ingen falske kapasitetstall |
| A12 | Schema og synlig side bruker samme datoer, priser, sted og URL; ingen ukontrollerte duplikater fra SEO-plugin |
| A13 | Uautorisert skriving avvises, fritekst kan ikke injisere script, og samtidig redigering overskriver ikke lydløst |
| A14 | Tørrkjøring er uten datamutasjon; gjentatt migrering lager ikke dubletter; gamle URL-er håndteres etter dokumentert mapping |
| A15 | En kursansvarlig klarer oppretting, korrigering, forhåndsvisning og publisering uten hjelp fra utvikler |

Automatiser kalenderlogikken, kollisjonene, statusberegningen og tilgangsgrensene. Bruk manuell kontroll for språk, mobilflyt, tastatur og faktisk LetsReg-overgang. Denne spesifikasjonen har ikke kjørt disse testene mot produksjonsnettstedet.

## 15. Prioritert leveranseplan

**MVP:** avgrenset kursansvarligrolle, kursperioder med enkelt-/flerdagers opphold og styrte synlighetsvinduer, ukeskalender med dag-/salgruppering og valg av standardvisning, integrasjonsadapter med demonstrasjonsdata, status/ferskhet og administrativ kontroll, samt kartlegging, datamodell, kalender/opphold, kursrundeadministrasjon, kopiering, validering, kursliste, detaljsider, LetsReg-lenker, korrekt schema, migrering og nødvendig tilgjengelighet.

**Neste trinn:** kalendernedlasting, automatisk visning av aktuelle kurs på nybegynnersiden, forbedret måling basert på reelle behov.

**Presisering 20. september 2026:** Kalendernedlasting og abonnement på faktiske kurskvelder er formalisert i M4.2, og delingsknapper per kursprofil i M4.3. Se [kalender-/delingskontrakten](KALENDER-OG-KURSDELING.md) for omfang, synlighet, endringer og akseptansekriterier, og [gjeldende roadmap](ROADMAP.md) for status og prioritet. M4.2/M4.3 er siden implementert og prøvd lokalt; faktiske klient-/plattformprøver gjenstår. M4.1s delingsmetadata og TECs periodeeksport er separate funksjoner.

**Integrasjonsleveranse:** koble til LetsRegs dokumenterte API, aktiver polling og eventuelt webhooks etter kapittel 16. Dette kan leveres samtidig med MVP når tilgang og datakontrakt er verifisert. Offentlig visning av tall skal være avslått fram til valideringen er bestått.

**Senere vurdering:** gjenbruk av tickets-plattformen som fremtidig påmeldingsmotor. Unngå to uavhengige redigerbare kursregistre.

For å ferdigstille den nettstedsspesifikke revisjonen trengs mobilvisning/skjermbilder av forside, kursoversikt og én kursdetalj, samt eksport eller kode for dagens CPT/felt. Faktisk partnerpraksis, prisvilkår, eventuell medlemsplikt og nivåbetegnelser må bekreftes før innhold publiseres.


## 16. LetsReg API, webhooks og oppdatert kapasitet

### 16.1 Verifisert API-kontrakt og gjenstående avklaringer

**Kildetillegg 20. september 2026:** [API Integration](https://help.letsreg.com/nb/articles/13598282-api-integration) bekrefter også legacy-token med separat `affid`. For webhook-abonnement på ett arrangement angis `eventId` og `organizerId=0`; for arrangøromfang angis `organizerId` og `eventId=0`. Dette avklarer nullverdienes beskrevne omfang, men ikke utelatte felt, callback-sikkerhet eller referanseoverføring. Se [finsøket og gjenstående verifikasjon](M5B-OFFENTLIG-RESEARCH.md).

Kilde: [Deltager Integration API – Swagger](https://integrate.deltager.no/index.html), [OpenAPI-skjema](https://integrate.deltager.no/swagger/v1/swagger.json), lest i nettleser 17. september 2026. Oppgitt versjon er **2.2.8**, OpenAPI 3.0. Dette erstatter forrige versjons formulering om uverifisert webhook-støtte: **både API, kapasitetsfelter og administrasjon av webhook-abonnementer er dokumentert**. Ingen autentiserte kall, registrering av abonnementer eller endringer hos LetsReg er utført.

| Dokumentert operasjon | Bruk i pluginen |
| --- | --- |
| `GET /organizers` | Finn organisasjoner kontoen har tilgang til |
| `GET /organizers/{organizerId}/events` | Finn arrangementer for mapping; uten datointervall returneres kommende/pågående arrangementer |
| `GET /events/{eventId}` | Hent arrangementets kapasitet, status, salgsdatoer og påmeldingsadresse |
| `GET /events/{eventId}/prices` | Hent priskategorier med kategori-ID og kapasitet |
| `GET /webhooks` | Hent tilgjengelige webhook-typer; ikke hardkod oppdiktede hendelsesnavn |
| `GET /webhooks/my` | Kontroller eksisterende abonnementer for kontoen |
| `POST /webhooks` | Opprett et eksplisitt konfigurert abonnement |
| `GET /webhooks/{id}` | Les ett abonnement |
| `DELETE /webhooks/{id}` | Fjern bare et abonnement pluginen selv eier, ved eksplisitt frakobling |

**Autentisering:** Swagger beskriver OAuth2 password-flow med tokenadresse `https://integrate.deltager.no/swagger/token`. Brukernavnet beskrives som `affid:username`, der prefikset velger affiliate-kontekst. Dette er den dokumenterte Swagger-flyten, ikke bevis for at brukerens vanlige konto er egnet som produksjonsintegrasjon. Avklar støttet tjenestekonto/produksjonsautentisering, nødvendige rettigheter, tokenlevetid og fornyelse med LetsReg. Hemmeligheter skal bare konfigureres sikkert på serveren; ikke be om passord i chat eller lagre dem i prototypen.

**Dokumenterte kapasitets- og statusfelter:**

| Leverandørfelt | Planlagt bruk og begrensning |
| --- | --- |
| `Event.availableRegistrations` | Primær kandidat for ledige plasser på arrangementsnivå; bekreft hvordan reservasjoner og ubegrenset kapasitet representeres |
| `Event.registeredParticipants`, `maxAllowedRegistrations` | Kontrollinformasjon; ikke beregn ledighet selv når kildefeltet er tilgjengelig |
| `Event.hasWaitinglist` | Dokumentert ventelistestøtte; en egen ventelistelenke må fortsatt bekreftes |
| `Event.active`, `published`, `isArchived`, `isCancelled` | Underlag for offentlig status; avklar samlet salgslogikk |
| `Event.registrationStartDate`, `registrationEndDate` | Åpnings-/stengingsvindu, nullable; avklar tidssone for mottatte verdier |
| `Event.eventUrl` | Kilde til påmeldingslenke; valider faktisk mål og riktig gruppe |
| `Event.lastUpdate` | Kildetidspunkt, nullable; ikke anta at alle kapasitetsendringer oppdaterer dette |
| `EventPriceCategory.id`, `externalId`, `name` | Map kategorier til gruppe/rolle med stabile ID-er, ikke navn alene |
| `EventPriceCategory.available`, `registered` | Kapasitet per priskategori; avklar delte grenser, parbilletter og forhold til arrangementsgrensen |
| `EventPriceCategory.active`, `availableFrom`, `availableTill`, `price`, `vat` | Kategoristatus, salgsperiode og prisgrunnlag; datoer kan være null |

`Event` inneholder også `prices`. Bekreft i test om dette gir et komplett, konsistent bilde sammen med arrangementskapasiteten, eller om eget priskall er nødvendig. Skjemaet oppgir felter, men gir ikke en full forklaring av salgbart antall under samtidige reservasjoner. Ukjente eller negative spesialverdier skal ikke automatisk bli «0».

**Webhook-kontrakt:** `WebHook` beskriver `id`, `title`, `hookName`, `description`. `WebHookRequest` krever `hookUrl`, `organizerId`, `webHookType`; `affiliateId` og `eventId` finnes som valgfrie felter i skjemaet. Avklar om utelatt felt og verdien 0 betyr ulike omfang. `WebHookSubscription` returnerer blant annet UUID `id`, `dateAdded`, `callbackUrl`, organisasjons-/arrangementsreferanser og `webHookType`. Abonnementets UUID er ikke en leverings-ID og kan ikke brukes til å deduplisere alle varsler.

`GET /webhooks/my` beskrives som en liste, mens skjemaet for 200-svaret peker på ett `WebHookSubscription`-objekt. Denne kontraktforskjellen må prøves mot et reelt svar før klienten fastsettes.

**Følgende er fremdeles ikke avklart av det leste skjemaet:** tilgjengelige `hookName`-/`webHookType`-verdier og dekning for booking/avmelding/endring, callback-payload, signatur eller annen avsenderautentisering, leverings-ID, retries, rekkefølge, kvoter og garantier for oppdateringstid. Egne `/webhooks/linkmobility/...`-ruter gjelder SMS-leveringsrapporter inn til LetsReg; de er ikke dokumentasjon av bookingvarsler til SalsaNor.

**Før produksjon:** verifiser tilgang og korrekt organisasjon; hent webhook-katalogen; få callback-kontrakten; prøv faktisk påmelding, avmelding, reservasjon, refusjon og fører/følger/par i testarrangement. Først da aktiveres eksakte offentlige kapasitetstall og webhook-mottak. Polling kan leveres først dersom kapasiteten er validert, mens webhook-detaljene avklares.

### 16.2 Anbefalt arkitektur og avgrensning

**WordPress eier kursinnhold og publisert timeplan. LetsReg eier påmelding, salgsstatus og kapasitet.** Pluginen henter en lokal, tidsstemplet kopi til visning. Besøkende skal ikke vente på et API-kall når de åpner kursoversikten. LetsReg gjør den endelige kapasitetskontrollen ved bestilling; nettsiden lover aldri at et vist antall reserverer en plass.

Bruk et lite adapterlag med funksjonene «hent kapasitetsbilde» og, dersom dokumentert, «valider og normaliser varsel». Forretningsdata skal behandles lesende. Oppretting/fjerning av pluginens egne webhook-abonnementer er en avgrenset konfigurasjonsoperasjon via de dokumenterte endepunktene. Pluginen skal ikke opprette påmeldinger, flytte deltakere eller lagre et eget deltakerregister. Ved avvik i pris eller dato får kursansvarlig et varsel; ikke overskriv redaksjonelt innhold lydløst.

| Tilgjengelig leverandørstøtte | Løsning |
| --- | --- |
| Dokumentert salgbart antall og autentiserte webhooks | Varsel utløser API-kontroll, supplert med periodisk kontroll |
| Dokumentert salgbart antall, ingen webhooks | Periodisk API-kontroll og manuell «Kontroller nå» |
| Bare booking-/deltakerdata | Beregn bare dersom alle reservasjoner, statuser og kapasitetsregler er dokumentert og testet; ellers ingen tall |
| Manglende tilgang eller ufullstendig datagrunnlag | «Sjekk ledige plasser hos LetsReg» og verifisert påmeldingslenke |

Ingen skjermskraping. Ikke beregn «maks minus antall betalte» uten dokumentert støtte for at dette faktisk tilsvarer salgbare plasser.

### 16.3 Mapping og rolleavhengig kapasitet

Hver kursgruppe får en eksplisitt kobling til LetsRegs stabile identifikatorer. Koblingen kan omfatte organisasjon, arrangement, produkt/priskategori og kapasitetspool, avhengig av faktisk API-modell. Identifiser aldri kurs ved å sammenligne navn alene. Flere grupper kan dele et arrangement, men må ikke arve arrangementets samlede ledighet som sin egen.

Støtt separat status for fører, følger og eventuelt par. En parbillett kan kreve én plass i hver rolle og totalt to deltakerplasser. Delte kapasitetspooler skal begrense kategoriantall; summering av kategorier kan ellers doble ledigheten. Disse reglene må komme fra leverandørens dokumenterte modell.

`0` betyr bekreftet ingen plasser. `null` betyr ukjent. Ukjent blir aldri automatisk «fullt». Venteliste er en egen bekreftet egenskap og lenke; fullt kurs beviser ikke at venteliste tilbys. Ved rundekopiering nullstilles alle eksterne koblinger, kapasitetstall og bekreftelsestidspunkter.

### 16.4 Behandling av hendelser

1. Hent et første komplett kapasitetsbilde før offentlig aktivering.
2. Motta webhook på serveren dersom støttet. Valider autentisering/signatur og eventuell tidsgrense akkurat som LetsReg dokumenterer. Ikke anta HMAC eller et bestemt headernavn.
3. Kontroller at organisasjon og eksterne ID-er er tilknyttet denne installasjonen. Dedupliser med leverandørens stabile leverings-ID, eller dokumentert tilsvarende mekanisme.
4. Legg en minimal jobb i en varig kø før vellykket mottak bekreftes. Køfeil skal ikke kvitteres som vellykket behandling. Samle tett ankomne hendelser til én oppdatering per gruppe/pool.
5. Hent autoritativ kapasitet fra API-et. **Et varsel endrer ikke kapasiteten med +1 eller −1.** Duplikater, rekkefølgefeil og tapte varsler skal ikke gi drivende tellere.
6. Valider komplett svar, alle nødvendige sider, tilhørighet og kapasitetsgrenser. Lagre ett atomisk øyeblikksbilde. En ufullstendig uthenting erstatter aldri et komplett bilde.
7. Bruk køserialisering/lås per kapasitetspool og kildeversjon når tilgjengelig, slik at en eldre kontroll ikke overskriver en nyere.
8. Ugyldig autentisering avvises. Hvis leverandøren ikke tilbyr tilstrekkelig verifiserbare varsler, bruk polling. En usignert melding skal aldri regnes som sannheten om antall plasser.

Webhook-mottak alene oppdaterer ikke tidspunktet «sist kontrollert». Det gjør bare en vellykket API-kontroll av relevant kapasitet. Periodisk kontroll reparerer tapte varsler og skal beholdes også med webhooks.

### 16.5 Ferskhet, offentlig tekst og cache

Foreslått startkonfigurasjon: kontroll hvert **5. minutt** for aktive kurs med åpen påmelding, og en grense på **15 minutter** for å vise eksakte tall. Dette er produktkrav som må tilpasses dokumenterte kvoter og realistisk oppdateringstid hos LetsReg, ikke en lovnad fra leverandøren. Et varsel skal prioritere ny kontroll i køen. Sett et målbart reaksjonsmål først etter måling mot det faktiske API-et.

| Datatilstand | Offentlig visning |
| --- | --- |
| Ferskt, rolleuavhengig antall 8 | «8 ledige plasser» + kontrolltid |
| Ferskt, bare førerplasser ledige | «3 plasser for førere»; følgerstatus oppgis særskilt |
| Bekreftet fullt med venteliste | «Fullt – venteliste» og korrekt handling |
| Fullt uten bekreftet venteliste | «Fullt» og alternative kurs |
| Ukjent, utløpt eller mislykket kontroll | «Sjekk ledige plasser hos LetsReg»; ingen eksakte tall |
| Avlyst eller påmelding stengt | Tydelig status; ingen vanlig påmeldingsknapp |

Ved API-feil beholdes siste gode bilde internt for feilsøking, men prototypens konservative regel skjuler tall straks kontrollen feiler. Feil må ikke «friske opp» gamle tall. Skillet mellom `last_attempt_at`, `last_success_at` og eventuell kildeoppdateringstid skal være eksplisitt.

Sidecache/CDN og strukturert data må utløpe senest ved bildets utløpstid og tømmes ved endret status. En allerede åpen side må også fjerne utløpte tall. Produksjonsversjonen skal ha lesbart grunninnhold og påmeldingslenke uten JavaScript. Ingen bakgrunnsmekanisme skal gjøre at cachet HTML lover ledighet utover ferskhetsgrensen.

Schema skal følge samme kontrollerte status som synlig innhold. Utelat ubekreftet `availability`; ikke sett `InStock` på en hel kursrunde fordi én kategori har plass. Separate offers kan brukes for dokumenterte billettkategorier når dette er korrekt for den valgte schema-modellen. Kapasitetsintegrasjon gir ikke i seg selv rett til arrangementsvisning i Google.

### 16.6 Intern datakontrakt og administrasjon

Foreslått normalisert lagring per kursgruppe/pool:

- Mapping: leverandør, organisasjons-ID, arrangements-ID, kategori-/produkt-ID-er, pool-ID og verifisert påmeldings-/ventelistelenke.
- Kapasitetsbilde: total og salgbart antall når kjent, per-rolle antall, salgsstatus, ventelistestøtte og valgfri kildeversjon.
- Kontroll: `last_attempt_at`, `last_success_at`, `source_updated_at` når tilgjengelig, `expires_at`, siste feilkode og intern revisjon.
- Kapabiliteter: støtter eksakte antall, roller, webhooks og kildeversjon. Ukjente kapabiliteter er avslått.

Administratorens integrasjonsside viser tilkobling, koblede kurs, siste vellykkede kontroll, feil og «Kontroller nå». Kursansvarlig ser kun aggregert status for egne arbeidsoppgaver og kan be om ny kontroll av konfigurerte koblinger; hemmeligheter, abonnementer og teknisk konfigurasjon er administratoroppgaver. Ved feil: forklar hva deltakeren ser, for eksempel «Tall er skjult. Påmeldingslenken fungerer fortsatt». Gi tydelig handling for utløpt tilgang eller feil mapping. Kontrollknappen skal bruke samme kø og kvotebegrensning som automatikken.

Manuelle overstyringer er unntak med begrunnelse, bruker, tidspunkt og utløp. De skal ikke framstå som API-bekreftelse eller skape falske kontrolltidspunkter. En lokal redaksjonell avlysning har forrang for offentlig handling. Offentlig detaljert antall skal normalt bare komme fra verifisert API-data.

### 16.7 Drift og sikkerhet

Bruk en varig jobbkø, eksempelvis Action Scheduler i WordPress, med faktisk serverstyrt kjøring/cron. Ikke baser oppdateringsløftet på at noen besøker nettsiden og utløser WP-Cron. Bruk eksponentiell retry med jitter, respekter `Retry-After` og kvoter, og varsle kursansvarlig ved vedvarende feil. Ha oversikt over fastlåste jobber og mulighet for kontrollert gjenkjøring.

Nøkler lagres kun på serveren i egnet hemmelighetskonfigurasjon. Ikke legg dem i offentlig JavaScript, WordPress REST-svar eller kildekontroll. Begrens administrasjon og manuelle kontroller med capabilities og nonce. Valider inngående størrelser og identifikatorer, og ratebegrens mottak. Logger skal inneholde tekniske ID-er og feilkoder, ikke navn, e-post, betalingsinformasjon eller rå deltakerpayload. Behold bare nødvendige hendelsesmetadata med definert slettefrist; persondata er ikke nødvendig for kapasitetsvisning.

### 16.8 Akseptansekriterier for integrasjonen

| ID | Bestått når |
| --- | --- |
| L1 | Tilgang og datakontrakt er verifisert mot SalsaNors faktiske organisasjon og et testarrangement |
| L2 | Gjentatt eller forsinket hendelse endrer ikke tellere feil; kontroll henter aktuell kildeverdi |
| L3 | Tapt webhook repareres av periodisk kontroll, uten manuell korrigering |
| L4 | Førere, følgere, par og delte kapasitetspooler gir korrekt salgbart antall uten dobbelttelling |
| L5 | Reservasjon, betaling, faktura, avmelding, overføring og refusjon gir dokumentert korrekt effekt |
| L6 | Ukjent, API-feil og utløpt bilde skjuler eksakte tall i side, kort, cache og schema |
| L7 | 401/403 gir tilgangsvarsel; 429 og midlertidig feil gir kontrollert retry uten forespørselsstorm |
| L8 | Køfeil, delvis paginering og eldre parallelle svar kan ikke gi et falskt ferskt bilde |
| L9 | Feil organisasjon, ukjent mapping og ugyldig varsel avvises; ingen hemmeligheter/persondata lekker |
| L10 | Fullt, stengt, avlyst og venteliste gir riktig tekst og lenke; LetsReg gjør endelig bestillingskontroll |

### 16.9 Hva prototypen demonstrerer

[Åpne den private prototypen i Nettsteder](https://salsanor-kursdemo.hosalmaas-8514.chatgpt.site).

Nettsteder-prototypen viser ferske plasser, fullt kurs, fører-/følgerforskjell, utdatert informasjon og API-feil. Under «Kursansvarlig → LetsReg og kapasitet» kan man simulere påmelding, avmelding, duplikat og ny kontroll. Dette demonstrerer produktatferd, ikke en virkelig webhook-mottaker, API-klient, sikkerhetsmodell eller varig kø. Ferskhet og integrasjonstilstand er forenklet til én felles demosituasjon; produksjonen trenger kontroll per gruppe/pool.

Pris, dato, sted, instruktør og kapasitet er eksempeldata. Påmeldingsknappen sender ingen bestilling. Prototypen er ikke en installert WordPress-plugin. Bruk den til å teste forståelsen av kursvalg, administrasjon og kapasitetsstatus før bygging mot ekte systemer.


## 17. Ukeskalender med dag og sal

### 17.1 Omfang og visningsvalg

Dette er en utvidelse av presentasjonen, ikke en ny kursmodell. Kalenderen viser gruppenes faste ukemønster for valgt kursperiode. Faktiske kursøkter brukes til datoer, opphold, avvik og schema. Den skal ikke ha et eget redigerbart sett med tider. Ingen ekstra Event-/CourseInstance-objekter eller indekserbare duplikatsider opprettes bare fordi visningen byttes.

Innbyggingen får `default_view: list | week` og `allowed_views`. Standard skal kunne settes til ukeskalender av kursansvarlig. Besøkende kan bytte visning med tekstmerkede knapper; filtre og valgt runde beholdes. Kursliste er anbefalt standard på siden for helt nye deltakere. Visningsvalget krever ingen konto.

### 17.2 Oppbygning

- Øverste overskriftsrad: ukedager uten dato, for eksempel «Mandager» og «Onsdager». Når alle viste kurs på dagen har én kurskveld, brukes entall, for eksempel «Lørdag».
- Andre overskriftsrad: saler under hver dag, for eksempel «Sal 1» og «Sal 2». Rom identifiseres med stabil ID og stedstilhørighet. Dersom flere steder brukes, vises sted også.
- Venstre akse: lokale klokkeslett i Europe/Oslo, fra dagens første viste kursstart til dagens siste viste kursslutt. Saler innenfor samme dag deler tidsakse; andre dagers tider gir ikke tomme tidsrader. Kurs plasseres etter fast start/slutt; samtidige kurs i ulike saler samme dag skal kunne sammenlignes på samme høyde. Faktiske opphold mellom kursene beholdes.
- Kursfelt: navn, nivå når det ikke framgår tydelig av navn, start–slutt, instruktør og kort kapasitet/status fra samme kilde som kurskortet. Klikk åpner samme kursdetalj.
- Definert rekkefølge for saler, kronologisk rekkefølge for dager. Tom sal beholdes der dette støtter sammenligning; tomt felt merkes ikke som ledig undervisningskapasitet eller påmeldingsplass.
- Kollisjoner må aldri skjule eller dekke et annet kurs. Administrasjonen varsler; offentlig publisering følger eksisterende kollisjonskontroll.

### 17.3 Fast timeplan for hele kursperioden

Vis ingen ukenumre eller ukevelger i kalendervisningen. Dagsoverskrifter er «Mandager», «Tirsdager» osv., med entall når dagen bare viser kurs med én kveld. Vis valgt kursperiodes navn over timeplanen. «Ukeskalender» kan beholdes som visningsnavn; den viser en normal kursuke gjennom perioden, ikke en bestemt kalenderuke.

**Avklaring 25. september 2026, implementert i 0.1.24:** Kurs med én faktisk, ikke-avlyst økt bruker entallsdag og «Dato» i kurskort og kursprofil. Kalenderkortet viser alltid den faktiske datoen som «Dato: …», også når den ligger før eller etter ordinær periodestart. Kurs over flere kvelder beholder «Oppstart» og flertallsdag. Dersom samme kalenderdag viser både enkeltkvelder og kurs over flere kvelder, beholder dagsoverskriften flertall; enkeltkvelden har fortsatt sin egen dato. Gjentakende kurs som starter tidligere/senere enn normalt, beholder datomerknaden om oppstart. Dette presiserer det opprinnelige ønsket om en kalender uten datoer. Tidsaksen avgrenses per dag etter gjeldende kursutvalg og filtre, og korte dagskolonner strekkes ikke til høyden på nabodagen.

Hver kursgruppe vises én gang på sin faste ukedag, start/slutt og sal, også når perioden inneholder kursfrie uker. Opphold skal ikke fjerne gruppen fra denne oversikten. Vis en kort merknad som «Perioden har kursfrie dager – se kursdatoene» uten konkrete datoer i selve kalenderen. Kursdetaljen viser hele den faktiske datolisten og oppholdene.

Individuelt flyttet tidspunkt/sal eller en avlyst kveld gir merknaden «Enkelte kurskvelder er endret – se kursdatoene». Endres gruppens faste timeplan for resten av perioden, må den nye faste regelen og avviksinformasjonen skilles tydelig. En gruppe som er avlyst i sin helhet skal merkes «Avlyst» og ha korrekt handling; den skal ikke framstå som et normalt tilgjengelig kurs.

Kalenderen skal aldri brukes som kilde til schema-datoer eller kollisjonsberegning. Disse bruker faktiske økter. Påmeldingsstatus gjelder hele kursgruppen og hentes fra samme kontrollerte kilde som i kurslisten.

### 17.4 Mobil og tilgjengelighet

På brede skjermer vises dagene side om side, hver med salunderkolonner. På smale skjermer legges dagblokkene under hverandre; innenfor hver dag beholdes de to salene side om side med en smal tidskolonne. Bruk lesbar skrift og la kurstitler bryte over flere linjer. Ved flere rom eller 200 % tekstforstørrelse må løsningen tilby en lesbar dag-/salliste i stedet for å presse teksten sammen. Hele siden skal ikke kreve sidelengs scrolling.

Bruk semantiske dag-/saloverskrifter og tydelige navn på kurslenkene, tastaturnavigasjon og synlig fokus. Ved retur fra kursdetalj beholdes visning, filtre og valgt kursperiode; fokus går tilbake til det synlige kurselementet. Skjulte alternative mobil-/desktop-elementer skal ikke være tilgjengelige for skjermleser eller tastatur. Produksjonen skal serverrendre kursinnhold, og tilby liste og fungerende påmeldingslenke uten JavaScript.

### 17.5 Akseptansekriterier og prototype

| ID | Bestått når |
| --- | --- |
| U1 | Ukedager er overordnede kolonner med saler under hver; entallsdag når alle viste kurs har én kveld, og dato på enkeltkurskort; ingen ukevelger |
| U2 | Samtidige kurs samme dag står på samme tidsrad; hver dags tidsakse avgrenses til dagens viste kurs, med riktige start-/sluttider, varigheter og opphold |
| U3 | Valgt kursperiode, nivå og dag bruker samme datagrunnlag som listen; bytte visning mister ikke valgene |
| U4 | Opphold endrer ikke fast timeplan; avvik merkes og konkrete datoer vises på kursdetaljen |
| U5 | Ved 320/390 px og 200 % tekstforstørrelse er innhold og handlinger lesbare uten sidescroll av hovedsiden |
| U6 | Standardvisning kan settes administrativt; deltakerens eget valg overstyrer standard innenfor besøket |
| U7 | Kursdetalj, kapasitet og schema er identiske uansett inngangsvisning; utdaterte tall skjules også i kalenderen |
| U8 | Tastatur og skjermleser gir forståelig dag, sal, kurs og tidspunkt; retur gjenoppretter synlig fokus |

Prototypen demonstrerer visningsbytte, valg av standard under «Kursansvarlig», fast timeplan uten datoer og ukevelger, to saler per dag, filtre, opphold og kursdetaljer. Dagsoverskriftene er «Mandager» og «Onsdager». Kalenderens faste mønster er kontrollert i simulert dokumentmiljø. To dager/to saler er demorammene; varige innstillinger, vilkårlig antall rom, individuell flytting/avlysning og produksjonens serverrendering er fortsatt implementeringskrav. JavaScript og kalenderlogikk kontrolleres lokalt; visuell nettleser- og tilgjengelighetstest av denne statiske prototypen er ikke gjennomført.


## 18. RegiNor Lite: egen rolle for kursansvarlige

### 18.1 Rolle og ansvarsområde

Opprett en egen WordPress-rolle **Kursansvarlig**, med intern nøkkel `rnl_course_manager`. Rollen skal kunne styre kursrekkeoppsett selvstendig, inkludert publisering etter validering. Den skal ikke bygge på redaktørrollen. Administrator tildeler rollen til navngitte brukere.

I dette dokumentet betyr «kursperiode», «kursrunde» og brukerens overordnede «kursrekke» samme beholder for parallellene. Bruk **Kursperiode** konsekvent i administrasjonen. En kursgruppe er ett konkret tilbud, for eksempel salsa nybegynner mandag kl. 18, og har sine egne kursøkter.

Kursansvarlige samarbeider som utgangspunkt om alle pluginens perioder, også dem en annen kursansvarlig har opprettet. Samtidig redigering håndteres med eksisterende versjonskontroll. Begrensning per avdeling eller tildelt periode inngår ikke i MVP.

| Handling | Kursansvarlig | Administrator |
| --- | --- | --- |
| Opprette, kopiere og redigere kursperioder | Ja | Ja |
| Opprette/endre grupper og deres tider, økter og opphold | Ja | Ja |
| Velge eksisterende kursbeskrivelse, nivå, sal og instruktør | Ja, begrenset oppslag | Ja |
| Angi gruppens offentlige tittel, prisvilkår og godkjente påmeldingslenke | Ja, innenfor oppsettet | Ja |
| Angi synlighetsvindu, påmeldingsvindu og kommende-visning | Ja | Ja |
| Forhåndsvise, publisere, avpublisere og arkivere kursoppsett | Ja | Ja |
| Slette egen eller andres kladd til papirkurv og gjenopprette den | Ja | Ja |
| Permanent slette eller masseslette historiske/publiserte perioder | Nei | Ja, eksplisitt handling |
| Opprette/endre gjenbrukbare kursbeskrivelser, nivåer, saler eller instruktørprofiler | Nei | Ja eller eksisterende separat redaksjonell rolle |
| Se aggregert kapasitet og be om kontroll av eksisterende kobling | Ja | Ja |
| Søke etter LetsReg-arrangement og velge priskategorier til kurs | Ja, hos administratorens konfigurerte arrangør og med rettighet til kurset | Ja |
| Importere nye kurs og beskrivelser fra LetsReg, enkeltvis eller samlet | Ja, i redigerbar periode i kladd | Ja |
| Endre API-tilgang, hemmeligheter, webhook-abonnementer eller globale innstillinger | Nei | Ja |
| Redigere sider, innlegg, menyer, mediebibliotek, tema, plugins eller brukere | Nei | Etter administratorens vanlige rettigheter |
| Lese deltakerregistre, ordre, betalings- eller personopplysninger | Nei | Utenfor RegiNor Lite MVP |

Kursansvarlig får en egen startside med kursperioder og normal tilgang til egen profil/utlogging. Andre administrasjonsmenyer skjules for enkelhet, men sikkerheten skal håndheves på serveren også ved direkte URL og API-kall. Globale standarder og sideinnbygging styres av administrator. Kursansvarlig kan velge kursperiodens visningsstandard innenfor dette oppsettet.

### 18.2 Implementeringskrav for rettigheter

Bruk separate capabilities for perioder og grupper, eksempelvis `rnl_edit_periods`, `rnl_publish_periods`, `rnl_edit_others_periods` og tilsvarende for grupper. Konkrete objektoperasjoner skal bruke WordPress' meta-capability-mapping med objekt-ID. Økt- og oppholdsoperasjoner krever rettighet til den tilhørende gruppen/perioden. Definer egne administratorrettigheter for innstillinger og integrasjon. Registrer eksplisitt capability-mapping og `map_meta_cap` for CPT-ene; ikke la dem falle tilbake til generelle innleggsrettigheter. [WordPress: register_post_type](https://developer.wordpress.org/reference/functions/register_post_type/).

Rollen får `read` og de avgrensede kursrettighetene; ikke `edit_posts`, `edit_pages`, `upload_files`, `manage_options`, brukeradministrasjon eller plugin-/temarettigheter. Rettigheter skal kontrolleres med capabilities, ikke bare sammenligning av rollenavn. Opprett/oppgrader rollens rettigheter med en versjonert migrering. Deaktivering skal ikke slette kursdata eller omfordele brukere. [WordPress: roller og rettigheter](https://developer.wordpress.org/plugins/users/roles-and-capabilities/).

LetsReg-oppslaget har egen rettighet `rnl_link_letsreg`, innført for kursansvarlig i rolleversjon 2. Administrator konfigurerer API-tilgang og arrangør. Kursansvarlig søker og velger arrangement/kategorier direkte i kursoppsettet, uten tilgang til driftssiden. Konto-/eierkontroll, felles forespørselsgrense, nonce og objektrettigheter gjelder også her. Kurskoblingen kan lagres med kontrollert API-lenke på publiserte kurs uten ny publisering. Ingen ordre-/deltakeroppslag eller skriveoperasjoner hos LetsReg inngår.

Kursimport har egen rettighet `rnl_import_letsreg`, tildelt kursansvarlig i rolleversjon 3. Den krever også LetsReg-oppslag og rett til å opprette kurs. Enkeltimport og bulkimport (opptil 20 kurs) kan opprette nye kursbeskrivelser fra det kontrollerte importgrunnlaget. Dette gir ikke generell adgang til å opprette eller redigere felles beskrivelser, nivåer eller steder. Eksisterende beskrivelser gjenbrukes uten å overskrives. Import krever tilgang til valgt periode, forhåndsvisning og bekreftelse; den oppretter bare kladder.

Implementer den samme kontrollen i admin, REST `permission_callback`, AJAX, massehandlinger, import-/eksportfunksjoner og registrerte metafelt. Nonce er ikke en erstatning for tilgangskontroll. Valg av eksisterende instruktør/rom skal bruke en avgrenset oppslagstjeneste med kun ID, navn og relevant offentlig informasjon; dette skal ikke åpne hele profilen eller dens private felter. Globale taksonomier skal ikke bli redigerbare gjennom en gruppeeditor.

WordPress-rettigheter kan være summen av flere roller og direkte brukerrettigheter. En bruker som samtidig er administrator blir ikke begrenset ved å få denne rollen i tillegg. For en konto med kun kursoppsett må administrator tildele denne rollen uten andre privilegerte roller eller direkte rettigheter. Kontroller dette ved oppsett; pluginen skal ikke lydløst fjerne eksisterende rettigheter fra brukere.

Loggfør hvem som endret publisering, vinduer og timeplan, med tidspunkt og objekt-ID. Ikke loggfør hemmeligheter eller deltakerdata.

## 19. Kursperioder, opphold og synlighetsvinduer

### 19.1 Tre uavhengige tidsforløp

| Tidsforløp | Betydning |
| --- | --- |
| Undervisningsperiode | Første til siste faktiske kursøkt; opphold kan forskyve siste økt |
| Synlighetsvindu | Når publisert periode og dens grupper er offentlig tilgjengelige |
| Påmeldingsvindu | Når påmeldingshandlingen er åpen; LetsRegs status kan begrense dette ytterligere |

En periode kan være synlig før salget åpner, med «Påmelding åpner …». En pågående periode kan fortsatt være synlig etter at påmeldingen er stengt. Et åpent salg hos LetsReg skal ikke overstyre at perioden er kladd eller utenfor synlighetsvinduet.

### 19.2 Felt og validering

Per periode: `visible_from`, `visible_until`, `show_as_upcoming`, publiseringsstatus og eksisterende salgs-/kursdatoer. Tidspunkter angis i Europe/Oslo og lagres entydig med UTC-beregning. Offentlig gyldighet bruker intervallet **fra og med start, til men ikke med slutt**. Administrator/kursansvarlig ser de lokale tidene.

Før publisering må begge synlighetstidspunkter være satt og slutt være etter start. Foreslå synlig fra nå og synlig til midnatt etter siste kursdag, men krev at verdiene bekreftes. Publisering med fremtidig `visible_from` betyr «Publisert – vises fra …», ikke at innholdet allerede er offentlig. Ved endret opphold/sluttdato skal systemet varsle hvis siste økt faller utenfor synlighetsvinduet, og tilby å flytte vinduets slutt. Endringen skjer ikke lydløst. Tidlig skjuling krever et eksplisitt bekreftet valg.

`show_as_upcoming` bestemmer om en fremtidig periode vises i periodevelgeren før undervisningen starter. Den overstyrer aldri kladd eller synlighetsvindu. Begge kan være oppfylt selv om påmeldingen ennå ikke er åpen. Feltet gjelder fremtidig oversiktsvisning; en publisert periode innenfor vinduet kan fortsatt leses via direkte lenke når `show_as_upcoming` er avslått. Bruk kladd eller senere `visible_from` dersom innholdet faktisk skal holdes skjult.

### 19.3 Enkeltstående og sammenhengende opphold

Et opphold har stabil ID, offentlig forklaring, omfang (hele perioden eller én gruppe) og lokal fra-/til-dato, inklusive begge datoer. Ett datofelt kan brukes for én fridag; internt lagres lik start/slutt. Flere og overlappende opphold behandles som union av datoer, slik at samme kveld aldri trekkes fra to ganger.

Normal regel: behold antall undervisningskvelder og finn neste gyldige ukedag. Seks mandager med én fri mandag gir fremdeles seks økter og én uke senere slutt. En fri tirsdag påvirker ikke mandagsgruppen. Ved absolutt sluttdato som ikke gir plass til alle øktene blokkeres publisering til kursansvarlig velger nytt antall eller ny sluttdato.

Forhåndsvis konkrete datoer før lagring. Hvis et nytt opphold treffer en manuelt flyttet økt, må kursansvarlig avklare konflikten; den skal verken forsvinne eller gjenopprettes automatisk. Behold historiske/avlyste øktidentiteter. Fjerning av et opphold utløser en ny, eksplisitt forhåndsvisning av konsekvensene.

### 19.4 Valg av gjeldende og kommende periode

Beregn først offentlig tilgjengelige perioder: publisert, innenfor synlighetsvinduet og med minst én offentlig gruppe. Deretter:

1. **Gjeldende:** undervisningen har startet og siste faktiske økt er ikke avsluttet. Dette inkluderer kursfrie dager og uker mellom øktene.
2. Hvis flere perioder pågår, velges den med seneste første økt som standard; ved likhet brukes stabil ID som siste sorteringsnøkkel. Alle gjeldende perioder forblir valgbare med tydelige navn/datoer.
3. **Kommende:** første økt er i fremtiden, `show_as_upcoming` er på, og offentlig tilgjengelighet er oppfylt. Sorter etter første økt stigende og deretter stabil ID.
4. Hvis ingen gjeldende periode finnes, vis nærmeste kommende som hovedvisning. Hvis heller ingen kommende er tilgjengelig, vis «Nye kurs er ikke publisert ennå». Ikke vis gamle kurs som om de var nye.
5. En bruker kan velge en annen tilgjengelig periode og få dens kursliste/ukeskalender. Behold dette valget ved navigasjon til kursdetalj og tilbake. Delbare periodevalg bruker stabil ID/slug og samme servervalidering.

Periodevelgeren merker gruppene «Pågående» og «Kommende». Vis bare faktisk tilgjengelige valg; ikke vis en tom knapp for fremtidige kurs. Hvis en valgt periode blir utilgjengelig, vis en forståelig melding og tilby gjeldende/neste tilgjengelige periode. Ingen tilbakefall til en kladd.

En avsluttet periode som fortsatt er innenfor synlighetsvinduet kan leses via sin direkte lenke med tydelig «Avsluttet», men velges ikke som gjeldende eller kommende. Et offentlig historikkarkiv er ikke del av MVP. Avlyste perioder som fortsatt er offentlige beholder avlysningsinformasjonen, men skal ikke foreslås som et tilgjengelig kommende kurstilbud.

### 19.5 Felles offentlig kontroll og cache

Bruk én funksjon for offentlig tilgang på tvers av periodevelger, liste, ukeskalender, direkte gruppe-/periode-URL, søk, REST, feeds, sitemap og JSON-LD. Kladd, privat, papirkurv og perioder utenfor vinduet skal ikke eksponeres gjennom alternative kanaler. Offentlige direkteforespørsler til skjult innhold returnerer 404; bare autentiserte brukere med riktig rettighet kan forhåndsvise. En eksplisitt parameter for periode-ID skal ikke omgå kontrollen.

Når synlighetsvinduet utløper skjules også detaljsidene. Et fremtidig ønske om varige offentlige arkivsider må få et eget arkivkrav; det er ikke en implisitt unntaksregel fra vinduet. Eksisterende indekserte URL-er må vurderes i migreringsplanen før denne regelen tas i bruk.

Tidsgrensene evalueres ved lesing med en testbar klokke; løsningen skal ikke være avhengig av at en publiseringsjobb har kjørt. Planlagte jobber tømmer cache ved grensene, og TTL begrenses av neste synlighets-/salgs-/kapasitetsgrense. En gammel cache skal ikke gjøre skjulte perioder tilgjengelige. Forhåndsvisning må aldri lagres i offentlig cache.

### 19.6 Akseptansekriterier

| ID | Bestått når |
| --- | --- |
| R1 | Kursansvarlig kan opprette, kopiere, planlegge og publisere perioder uten generelle WordPress-redaktørrettigheter |
| R2 | Direkte admin-/REST-/AJAX-kall til sider, profiler, globale felt, brukere og integrasjonsinnstillinger avvises |
| R3 | Kursansvarlige kan samarbeide om periodeoppsett; samtidig redigering gir konfliktmelding |
| R4 | Publisert periode er skjult like før `visible_from`, synlig ved start og skjult ved `visible_until` i alle offentlige kanaler |
| R5 | Påmeldingsstatus og synlighetsstatus kan endres uavhengig; fremtidig synlig kurs kan ha stengt/ikke åpnet påmelding |
| R6 | Fremtidig kladd eller periode utenfor vinduet avsløres ikke i velger, direkte URL, REST eller schema |
| R7 | Nåværende periode består gjennom oppholdsuker; tilgjengelige kommende perioder kan velges uten å erstatte den |
| R8 | Ingen gjeldende periode gir nærmeste tilgjengelige kommende, ellers en tydelig tomtilstand |
| R9 | Enkeltfridag, flerukers opphold, overlapp og gruppeunntak gir korrekt antall økter og sluttdato |
| R10 | Kopiering oppretter kladd med nullstilte synlighets-/salgsdatoer og eksterne koblinger |
| R11 | Overlappende perioder, sommertid og eksakte grenseklokkeslett gir deterministisk resultat |
| R12 | Utløpt cache og uteblitt cron kan ikke eksponere skjult innhold; autorisert forhåndsvisning er privat |

Disse kapitlene er krav til WordPress-pluginen. Nettsteder-prototypen demonstrerer ennå ikke reell rollebegrensning, flere uavhengige perioder eller automatiske synlighetsvinduer. Ingen WordPress-roller eller produksjonsdata er endret i denne spesifikasjonsoppdateringen.
