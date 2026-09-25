# Roadmap – RegiNor Lite

Oppdatert **25. september 2026**. Grunnlag: spesifikasjon v1.2 og prosjekteiers avklaringer. RegiNor tas i bruk fra neste nye kursperiode uten migrering av gamle ACF-kurs. Avada og The Events Calendar videreføres.

Avkrysset betyr levert i repoet, ikke godkjent i produksjon. Ingen lanseringsfrist er avtalt. Backend og frontend skal være intuitive for personer med lav digital kompetanse og ha et elegant uttrykk; se [brukeropplevelse](BRUKEROPPLEVELSE.md). Leveransehistorikk og lokale testbevis finnes i [endringsloggen](../CHANGELOG.md).

## Oppfølging – 0.1.24

- [x] Kurs med én kveld bruker entallsdag og «Dato» i kort/kursprofil, og viser faktisk dato i kalenderkortet. Dagsoverskriften er i entall når alle viste kurs den dagen har én kveld; gjentakende kurs beholder flertall og oppstart.
- [x] Kalenderens tidsakse avgrenses til første/siste viste kurs på hver dag. Saler samme dag beholder felles klokke og faktiske opphold. Filtre oppdaterer tidsrommet, og korte dagskolonner strekkes ikke til nabodagens høyde.
- [x] Lokalt: offentlig 119 / HTTP 83, admin 117, interface/DOM og Composer 230/565 består. Regresjonsprøvene dekker bl.a. enkeltkveld kl. 13 på lørdag og kveldskurs på andre dager. POT og ZIP med 126 runtime-filer kontrollert.
- [ ] Visuell prøve i faktisk Avada/staging med ulike dagstider og enkeltkvelder.

[Releasenotat og testbevis](releases/0.1.24.md).

## Oppfølging – 0.1.23

- [x] En egen første kursdato før perioden krever aktiv avkryssing på kurset. Server og skjema håndhever dette, også ved import; tillatelsen nullstilles ved kopiering til ny periode.
- [x] Forhåndsvisning/publiseringskontroll viser unntaket. Andre kurs, periodens datoer og synlighets-/salgsvinduer beholdes. Eksisterende økt-, kollisjons-, sluttdato- og historikkvern gjelder fortsatt.
- [x] Kursvisning, schema og kalenderfil bruker faktiske datoer; ukeskalenderen viser tidligere oppstart. TECs samleoppføring beholder periodens oppgitte datoer.
- [x] Lokalt: admin 117, lagring 65, arbeidsflyt 92, HTTP 43, offentlig 99 / HTTP 83, import 117, kalender 43, WordPress, interface/DOM og Composer 230/565 består. Importprøven bruker syntetiske API-svar. POT og ZIP med 126 runtime-filer kontrollert.
- [ ] Prøv godkjent introkurs før perioden i faktisk Avada/staging, inkludert synlighets- og salgsvindu.

[Releasenotat og testbevis](releases/0.1.23.md).

## Beskrivelsesmal for introkurs – 25. september 2026

- [x] [Introkursbeskrivelse i enkel HTML](SALSA-INTROKURS-LETSREG.md), med eksisterende tekstmarkører og veiledning for én økt på 150 minutter. Ingen ny kurstype eller automatisk timeplantolkning fra brødtekst.
- [x] Eksisterende maltester (6 / 34 assertions) og 11 direkte kontroller av HTML-eksemplet og nivånavntreff består lokalt. Ingen runtime-endring.
- [ ] Bekreft arrangementsår, sal, pris/partnerordning og videre kurstilbud; prøv teksten gjennom LetsRegs editor/API og kontroller importert kladd med én økt.

## Oppfølging – 0.1.22

- [x] Erklærer WP Consent API-støtte gjennom den dokumenterte kroken for RegiNors egen pluginfil. Nettstedhelse skal dermed gjenkjenne støtten.
- [x] API-avslag håndheves direkte for statistikk og markedsføring i nettleser og server. Complianz kreves fortsatt; tilbaketrekking og sletting av måleøkten beholdes.
- [x] Lokalt: journey 55, analytics 36, WordPress-oppstart og Composer 230/565 består. Prøvene bruker syntetisk samtykke og bekrefter også at andre utvidelser ikke får RegiNors erklæring. POT og ZIP med 126 runtime-filer kontrollert.
- [ ] Kontroller Nettstedhelse og reelle samtykkevalg/tilbaketrekking med Complianz/GTM etter installasjon. Erklæringen er ikke en full revisjon av nettstedets øvrige utvidelser.

[Releasenotat og lokale testbevis](releases/0.1.22.md).

## Oppfølging – 0.1.21

- [x] Kampanjesider: inkluder/utelat kursnivåer og velg bare fremhevede kurs per kortkode. «Kategorier» betyr kursnivåer, avklart med prosjekteier. Utvalget avgrenses på serveren og gjelder kort, kalender, filtre og schema.
- [x] Egne brytere for innledning/overskrifter, filtre og visningsvalg; startvisning og tillatte visninger. Flere innbygginger kan ha ulike valg på samme side, med egne filtre/ankre og bevarte kampanjeparametere. Tilsvarende valg i WordPress-blokken og eksempler under Nettsidevisning.
- [x] Prosjekteier bekrefter at midlertidig lagringssporing er slått av etter vellykket kladdprøve.
- [x] Lokalt: offentlig 99 / HTTP 83, admin 91, nivåer 37, WordPress, interface/DOM/blokk/fokus, journey og Composer 230/565 består. POT og ZIP med 126 runtime-filer kontrollert.
- [ ] Visuell kontroll av kampanjelandingsside med to kortkoder i faktisk Avada/staging. Automatisk cron-testing avventes fortsatt.

[Brukerveiledning](KORTKODER-OG-KAMPANJESIDER.md) · [Releasenotat og lokale testbevis](releases/0.1.21.md).

## Oppfølging – 0.1.20

- [x] Første produksjonsspor avgrenser tregheten til sekvensielle kursstatusoppdateringer, 10–32 sekunder per kurs. Kurslåsen tas på under ett millisekund; ingen commit er bekreftet i loggutdraget.
- [x] Lokal ytelsesfeil gjenskapt og rettet: standard objektcache beholdes gjennom transaksjonen, og lokale cacheverdier ryddes ved transaksjonsgrensen. Syntetisk lagringshook går fra 100 til 2 SQL-kall for 100 gjentatte lesinger, med fortsatt korrekt rollback og alle hooks aktive.
- [x] Diagnostikken viser antall SQL-kall og cachemodus uten rå SQL. Ekstern/ikke-standard objektcache beholder tidligere policy; ingen delt cache eller sidecache tømmes.
- [x] Lokalt: cache 15, diagnostikk, arbeidsflyt 92 med/uten sporing, lagring 65, WordPress, HTTP 43, TEC 102, offentlig 99 / HTTP 58, DOM og Composer 230/565. POT og ZIP med 125 runtime-filer kontrollert. [Avgrensninger](releases/0.1.20.md#verifikasjon).
- [x] Prosjekteier bekrefter LiteSpeed Object Cache av, med deaktivert Redis-/Memcached-utvidelse. Ny runtime-sporing skal bekrefte den faktiske implementasjonen.
- [x] **Prosjekteier bekrefter 25. september at «Til kladd» fungerer etter 0.1.20 og går «forholdsvis raskt».** Den konkrete blokkerende brukerreisen er dermed bekreftet løst i denne prøven. Eksakt tidsmåling og nytt spor er ikke mottatt.
- [ ] Republisering og etterfølgende TEC-synkronisering må bekreftes separat i faktisk miljø. Automatisk cron-testing avventes fortsatt; den vellykkede kladdprøven godkjenner ikke hele driftsoppsettet.

> Punktene nedenfor beskriver tidligere utgaver og feilsøking. «Til kladd» er senere bekreftet løst med 0.1.20 som beskrevet over; øvrige driftskontroller beholdes åpne.

## Oppfølging – 0.1.19

- [x] Valgfri lagringsdiagnostikk med operasjons-ID, nettsteds-ID, databaseforbindelse og tidsbruk per trinn. Av som standard; ingen rå SQL, kursinnhold eller innsendte felter. Avgrenses til nettsted 19 under kontrollert forsøk.
- [x] Tidlige oversettelser av cron-navn rettet, med egen regresjonsprøve. Intervallene er uendret.
- [x] Lokalt: diagnostikkprøve med 92 arbeidsflytkontroller, arbeidsflyt uten sporing 92, lagring 65, HTTP 43, WordPress, oversettelser og Composer 230/565. POT og ZIP med 125 runtime-filer kontrollert. [Testomfang og avgrensninger](releases/0.1.19.md#verifikasjon).
- [ ] Produksjonsfeilen er fortsatt åpen: siste logg har 37 låsetidsavbrudd ved Avadas cache og to PHP-stopp etter 120 sekunder. Knytt neste lagringsspor til aktiv ventende/blokkerende forbindelse hos host. Automatisk cron-testing avventes.

## Driftsfunn etter 0.1.18 – nettsted 19

- [x] Ny terminalprøve bekrefter nettsted 19 og manuell kjøring med eksplisitt PHP-CLI og `flock`: én kalenderhendelse returnerte på 0,131 sekunder uten fatalfeil. Første utskrift hadde to kjøringer på 4,928 og 2,146 sekunder; dette beviser ikke dupliserte TEC-oppføringer.
- [x] Administrasjonens køpanel bekrefter registrert start/avslutning kl. 23:43:51 CEST og neste periodiske jobb kl. 23:48:51, uten den gamle generelle køfeilen. Første nye «Til kladd»-forsøk feilet fortsatt; ny logg bekrefter fortsatt 120-sekunders tidsavbrudd. Videre automatisk cron-prøve avventes etter prosjekteiers beskjed. PHP-innstillingene er vurdert i [feilsøkingsnotatet](FEILSOKING-KLADD-OG-TEC.md#køstatus-kl-234351-cest-og-php-innstillinger).
- [x] RegiNors varsel om tidlige oversettelser gjenskapt og rettet lokalt. Egen oppstartstest, WordPress, admin 91, kapasitet 39, TEC 102 og Composer 225/518 består. Rettelsen følger 0.1.19; produksjonsstakken er ikke endret her.
- [x] Køoversikt og ny logg vurdert: periodisk kalenderjobb finnes, men flere RegiNor-jobber er kraftig forsinket og besøksutløst cron er slått av. Gammel kømelding og faktisk køinnhold er skilt i [feilsøkingsnotatet](FEILSOKING-KLADD-OG-TEC.md).
- [x] Servercron-kommando og konkret oppstartsfeil bekreftet fra cPanel/logg: WP-CLI starter med PHP-CGI og avbryter før hendelseskjøring. [Rettet kommando og avgrenset prøve](HOSTING-DATABASEKONTROLL.md#bekreftet-oppstartsfeil-i-cpanel-cron) er dokumentert.
- [x] Prosjekteiers terminalprøve bekrefter `/usr/local/bin/php` som PHP 8.3.33 (cli). OPcache-advarsel om dobbel lasting gjenstår separat.
- [ ] Bekreft periodens TEC-status og «Til kladd» etter den vellykkede manuelle kjøringen. Kontroller gamle planlagte publiseringer før hele cron-køen gjenopptas, og verifiser automatisk fremdrift over flere intervaller. Terminaltesten alene bekrefter ikke at cPanel-jobben er endret eller at kalenderoppføringen ble lagret riktig.
- [ ] Finn eieren av den aktive databaseblokkeringen. Ny logg viser ti låsetidsavbrudd og PHP-stopp kl. 22:32:42 CEST. Cron-problemet er ikke i seg selv bevis for årsaken til «Til kladd»-feilen.

## Oppfølging – 0.1.18

- [x] Konkret WordPress-cron-feilkode, jobbnavn og administrativ lesestatus. Skiller kalenderkøen fra Action Scheduler og viser nettsteds-ID i flernettstedsoppsett.
- [x] Bekreftet konkurrerende kølegging gir ikke falsk feil; tidligere køfeil ryddes når samme jobb faktisk finnes. Feil fra kalenderarbeidet beholdes.
- [x] Lokalt: TEC 102, lagring 65, admin 91, adminmeny HTTP 56, WordPress og Composer 225 tester / 518 assertions; POT og ZIP kontrollert.
- [ ] **Driftsblokkering fortsatt åpen:** Loggen etter 0.1.17 viser 32 låsetidsavbrudd rundt Avada-cache og PHP-timeout etter 120 sekunder. Identifiser aktiv blokkerende transaksjon med host, se [lesende kontroll](HOSTING-DATABASEKONTROLL.md). Gammel feilet TEC/Shepherd-jobb er ikke bevist som årsak.

## Oppfølging – 0.1.17

- [x] TEC-skriving flyttet fra sidelasting/skjemasvar/avslutning til bakgrunnsjobb, med deduplisering og periodisk kontroll. Samme oppføring gjenbrukes; kladd holdes skjult før jobben fullføres.
- [x] Skiller avvist lagring fra reell versjonskonflikt. Meldinger identifiserer endret kurs/versjon og kontrollkode. Tom TEC-datovelgerinnstilling håndteres i reserveoppdateringen.
- [x] Lokalt: TEC 91, arbeidsflyt 92, lagring 65, admin 91, HTTP 43 / TEC HTTP 15 / adminmeny 56, offentlig 99 / HTTP 58, WordPress, DOM og Composer 225 tester / 518 assertions.
- [ ] Bekreft kladd/republisering og kjørende bakgrunnsjobb på faktisk nettsted. Ny prøve etter 0.1.16 viser fortsatt 120 sekunders tidsavbrudd og databaseventing; original blokkerende operasjon er ikke identifisert. Sikkerhetsutvidelser er ikke bevist som årsak.

## Oppfølging – 0.1.16

- [x] TEC har egen lås under oppdatering. Felles kurslås brukes bare til å lese et konsistent kildegrunnlag; kalenderarbeid venter ikke på andre skrivere.
- [x] Databasefeil skilles fra opptatt lås. Språk-/oppryddingsfeil frigjør låser, og kladd beskyttes også når en eldre kalenderkopi fortsatt er publisert.
- [x] Lokalt: TEC 82, arbeidsflyt 90, lagring 61, admin 91, HTTP 43 / TEC HTTP 15, offentlig 99 / HTTP 58, WordPress, DOM, journey 46 og Composer 225 tester / 518 assertions.
- [ ] Bekreft «Til kladd» for Høst 3 2026 i faktisk miljø etter oppgradering. Loggen viser feil i databaseforbindelsen og WPML-låseventing; den opprinnelige årsaken er ikke bevist. Se [feilsøkingsnotat](FEILSOKING-KLADD-OG-TEC.md).

## Oppfølging – 0.1.15

- [x] RegiNor avventer samlet Complianz-/WP Consent API-oppdatering før nye hendelser, og stopper måling umiddelbart ved tilbaketrekking.
- [x] Ingen ekstra reisestart ved mellomtilstanden i «Avslå alle»; slettingsforespørsler avbrytes ikke av senere kategoriendringer.
- [x] Regresjonstest feilet før retting; 46 journey-tester og Composer-kontrollen passerer etter retting.
- [ ] Verifiser 0.1.15 på salsanor.no med A–D og GA4 DebugView. GTM er integrert, og RegiNor-workspace er konfigurert, men upublisert.

## Oppfølging – 0.1.14

- [x] Lagrede arrangementsfelt leses tilbake etter TEC-oppdatering; et upålitelig retursvar avgjør ikke alene resultatet.
- [x] Ved manglende oppdatering prøves TECs direkte oppdaterings-API på samme eide arrangements-ID. Vern, synlighetsregler og dublettsikring beholdes.
- [x] Ved feil i begge metoder viser status arrangements-ID, avvikende felt og ufølsomme resultatkoder.
- [ ] Bekreft «Høst 3 2026» i faktisk Pro-stage. Skjermbildet fra 0.1.13 bekrefter at feilen skjer i lagringsfasen; eksakt leverandørfeil er ikke fastslått.

- [x] Lokalt: TEC 64, TEC HTTP 15 (med fremprovosert ORM-feil), admin 91, lagring 61, offentlig 99 / HTTP 58, WordPress, DOM og Composer 225 tester / 518 assertions.

## Oppfølging – 0.1.13

- [x] TECs forekomst-ID-er normaliseres til stabil WordPress-ID ved kobling, lagringskontroll, lenker, REST og kalendernedlasting. Eldre kobling med forekomst-ID repareres ved synkronisering.
- [x] Kalenderstatus skiller kladd, synlighetsdatoer, manglende kursside/kurs/sluttdato, databaselås og feil i opprettelse/lagring/kategori/bilde.
- [ ] Bekreft i faktisk TEC Pro-stage at «Høst 3 2026» opprettes og lenker riktig. Offentlig kursrekke er lest; søk etter «Kursperiode» i offentlig TEC REST ga ingen treff. Produksjonsårsaken er ikke bekreftet.

- [x] Lokalt: TEC 64, TEC HTTP 15, admin 91, offentlig 99 / HTTP 58, lagring 61, WordPress-smoketest, DOM og Composer 225 tester / 518 assertions. Pro-ID-er er simulert via dokumentert filter; faktisk Pro-test gjenstår.

## Oppfølging – 0.1.12

- [x] Tydeliggjort at kalenderdeling inkluderer alle publiserte, synlige fremtidige perioder. Eksisterende synlighets- og kladdvern beholdes.
- [x] Lenker fra TECs arrangementsoversikt, kalenderoppsett og periodens kalenderstatus til riktig kursrekke. Skjulte perioder får bare administrasjonslenke.

- [x] Lokal verifikasjon: TEC 44, TEC HTTP 15, admin 91, arbeidsflyt 86, HTTP 43, administrasjonsmeny 56, WordPress-smoketest og Composer 225 tester / 518 assertions. Faktisk staging gjenstår.

## Oppfølging – 0.1.11

- [x] Velg dagsfarger for kalender, kortliste eller begge. Eksisterende oppsett beholder kalender som standard; alpha, palettkobling og fargeprioritet beholdes.
- [x] Sentrerte saloverskrifter med sal og sted på separate linjer; tydelig salnavn på kort og kursprofil.
- [x] Lokal funksjons- og nettleserkontroll av valgene og typografien. Faktisk Avada-staging gjenstår.

## Oppfølging – 0.1.10

- [x] Visuell/HTML-editor for de fire rike tekstfeltene, med synkronisering i vanlig lagring, enkelt-/bulkimport og forslag.
- [x] Egen før/etter og godkjenning av prisvilkår uten kladd, også for tidligere gjennomgåtte LetsReg-kilder. Manuell redigering på publiserte kurs og vern gjennom rettigheter/versjoner/kildekontroll.
- [x] Gjennomgående lokal test av avsnitt, lenker, tekstformatering og lister fra syntetisk API via import/lagring til alle fire seksjoner i frontend.
- [ ] Installer og godkjenn nye prisvilkår på faktisk nettsted. Eldre ren tekst får ikke tilbake formatering uten ny henting/godkjenning eller manuell redigering.

## Vurdering og anbefalt neste arbeid

**Grunnfunksjonene i M0–M4 og M4.1 er implementert lokalt. Det største hullet før innføring er bekreftelse i faktisk miljø og med brukere.** M5 har nå reell lesetilgang, automatisk status og kategoriers øvre kapasitetsgrenser. Eksakte salgbare pooltall, produksjonsdrift og bekreftede konverteringer er fortsatt åpne. M4.2a/b og M4.3 er også implementert lokalt; faktiske kalenderklienter og SoMe-/visuell staging gjenstår.

| Prioritet | Gjenstående arbeid | Bevis før godkjenning |
| --- | --- | --- |
| 1 – Innføringsgrunnlag | M0–M4/M4.1: GitHub CI, faktisk Avada/TEC/WPML/SEO, cache/CDN, riktige kursopplysninger og tilgjengelighet | [G01–G08](M0-M4-GJENNOMGANG.md#gjenstående-kontroller-med-krav-til-bevis), faktiske delingsforhåndsvisninger og integrasjonstester |
| 2 – Klientprøver | M4.2a/b og M4.3 er levert lokalt: prøv filimport, abonnement, flytting/avlysning og delingsdialoger | Faktiske klientbevis og gjenstående K-/D-kriterier etter [egen kontrakt](KALENDER-OG-KURSDELING.md) |
| Parallelt – Integrasjon | M5: verifiser kapasitetsbetydning, token/kvoter, servercron, varsling og cacheutløp. M5a: faktisk Complianz/GTM/GA4 | [Kategoriavgrensning](M5-KATEGORIKAPASITET.md), leverandørprøve og dokumentert stagingdrift |
| Delvis på vent – M5b | Bekreftet besøks-/salgskobling mangler leverandørstøtte; arrangementshistorikk og eksperimentelle tidssammenfall er implementert lokalt | [Supportsak #134895094 og kontrakthull](M5B-KONTRAKTGJENNOMGANG.md#leverandørsvar-besøkskobling-satt-på-vent); ingen bekreftet kampanjeattribusjon |
| Deretter – M6 | Første reelle kursperiode, brukerprøve, utrulling og tilbakeføring | Godkjent staging og driftsansvar; portene for funksjonene som faktisk slås på må være bestått |

Prioritetene er en anbefalt arbeidsrekkefølge, ikke tidsestimater. Miljøkartlegging og leverandøravklaringer kan gå samtidig med utvikling. M5b er ikke en forutsetning for kalender, deling eller ordinær påmelding via LetsReg. Live API er valgfritt ved første innføring; en funksjon som ikke er godkjent, må forbli avslått.

## Faser og sprinter

| Fase | Milepæl / sprint | Resultat | Status |
| --- | --- | --- | --- |
| Fundament | M0 + domenedel av M2 / sprint 1 | Verktøy, kalenderforslag, kollisjoner, synlighet og periodevalg | Implementert og testet lokalt |
| Miljø og ny kursmodell | M1 + lagringsdelen av M2 / sprint 2 | Egen privat lagring, stabile økter, historikk og omplanlegging | Levert lokalt; faktisk Avada/TEC-kartlegging gjenstår |
| Kursarbeidsflate | Resten av M2 + M3 / sprint 3 | Opprette neste periode, grupper, opphold, forhåndsvisning og publisering med avgrenset rolle | Implementert og testet lokalt; visuell prøve og A15-brukertest gjenstår |
| Offentlig kursreise | M4 / sprint 4 | Avada-innbygging, kursliste, detalj, ukeskalender og schema | Implementert lokalt; visuell/menneskelig prøve og faktisk Avada/TEC-staging gjenstår |
| Permalenker og delingsmetadata | M4.1 / egen leveransebolk | Lesbare kursadresser, Open Graph, SEO, Event-schema og sted | Implementert lokalt; staging gjenstår |
| Kalender i egne apper | M4.2a/M4.2b / nye leveransebolker | Nedlasting av faktiske kurskvelder og abonnement med endringer | Implementert og prøvd lokalt; klient-/stagingprøver gjenstår |
| Deling av kursprofil | M4.3 / ny leveransebolk | Enhetens deling, kopier lenke og SoMe-knapper | Implementert og prøvd lokalt; klient-/stagingprøver gjenstår |
| Kapasitetsvisning | M5 / sprint 5 og oppfølging | Kurskobling/import, automatisk status og kategoriers øvre kapasitetsgrenser | Implementert og avgrenset faktisk lesetest utført; poolsemantikk og produksjonsdrift gjenstår |
| Innføring | M6 / sprint 6 | Første reelle nye kursperiode, staging, brukertest og ny kursinngang | Avventer relevante leveranseporter, faktisk staging og brukerprøve |

Sprintene er leveransebolker, ikke tidsestimater. M1 kan gjennomføres ved siden av domenearbeidet. Produksjonstilpasning av Avada/TEC krever faktiske versjoner og grensesnitt, men ingen ACF-felteksport eller gammel kursrunde er nødvendig for å starte den nye modellen.

## M0 – Prosjektgrunnlag

- [x] Installerbar plugin, namespace/text domain og avgrenset klasselaster.
- [x] wp-env, låsefiler, PHP-lint, PHPUnit, WordPress-smoketest, CI og ZIP-pakking.
- [x] Lesende administratorstatus. Egne administrator-capabilities er lagt til i sprint 2; ingen kursdata opprettes automatisk.
- [ ] Bekreft CI på GitHub etter push.

**Kontrollstatus:** lokal bootstrap, Composer/DOM og ZIP er kontrollert. GitHub-kjøring for denne arbeidskopien gjenstår; se G01 i [gjennomgangen](M0-M4-GJENNOMGANG.md).

## M1 – Avgrenset kompatibilitetskartlegging

- [x] Avklart Avada/TEC-samspill, ACF-uavhengig kursmodul og ingen migrering.
- [x] Dokumenterte integrasjonspunkter og lesende strukturverktøy er tilgjengelige.
- [x] Lokalmiljø kartlagt med WP 7.1, PHP 8.2.29, Twenty Twenty-Five og TEC 6.17.5. Lesende rapport inkluderer database, registreringer, innbygging og profiltyper. Dette erstatter ikke stagingkartleggingen.
- [ ] Bekreft WP/PHP, Avada/Core/Builder, child theme, ACF/TEC-utgaver, SEO, cache/CDN og staging.
- [ ] Kontroller at nye rnl_-registreringer og URL-er ikke kolliderer med eksisterende kurs eller TEC.
- [ ] Kartlegg Avada-innbygging og eventuelle referanser til eksisterende instruktør-/stedsprofiler. Ikke importer gamle kursposter.
- [ ] Bekreft nivåforklaringer, prisgrunnlag, partnerpraksis og LetsReg-lenker for den nye perioden før publisering.
- [x] Valgt TEC-kobling for hele kursperioden med lenke til RegiNors kursoversikt; synlighet og datoer følger RegiNor. Samlet schema i faktisk staging gjenstår under M4.

**Leveranseport:** miljø og grensesnitt er avklart før staging/produksjonstilpasning. Ingen import, feltmapping for gamle kurs eller overtakelse av ACF-kursregistreringer inngår.

## M2 – Ny kursmodell og domeneregler

- [x] Kalenderforslag med ISO-ukedager, lokale tider, UTC per dato og periode-/gruppeopphold.
- [x] Avgrenset søkehorisont, absolutt sluttdato og forståelige feil for ugyldige/uklare tidspunkt.
- [x] Kollisjonskontroll av leverte faktiske tidsintervaller, saler og instruktører.
- [x] Domenepolicy med injiserbar klokke, halvåpent synlighetsvindu og deterministisk periodevalg.
- [x] PHPUnit-tester for kalender, DST, opphold, kollisjoner, grenser, pauseuker, kommende og tomtilstand.
- [x] Privat WordPress-modell for beskrivelse, periode, gruppe, økt, opphold, sted/sal og konfigurerte profilreferanser.
- [x] Privat nivåregister med stabil ID, navn/forklaring, rekkefølge og deaktivering av nye valg. Nivåreferanse per enkeltkurs, uavhengig av delt kursbeskrivelse.
- [x] Metadataregistrering, repository, atomisk versjon/historikk, kladdvalidering og administrator-capabilities uten ACF.
- [x] Persistente økt-/oppholds-ID-er; differanse ved omplanlegging med bevaring av flytting, avlysning og påbegynte økter.
- [x] Repository for konflikter på tvers av perioder og avledede første/siste ikke-avlyste faktiske økter.
- [x] Varsling når økter faller utenfor synlighetsvindu; ingen automatisk utvidelse av vinduet.
- [x] Publiseringsvalidering, uavhengig effektiv salgsstatus, samtidighetskontroll på tvers av perioder og eksplisitt erstatningskveld i sprint 3.

**Leveranseport:** domenelogikk, lagring, publiseringsvalidering og editorintegrasjon er levert i sprint 1–3, med test av A8s identitetsbevaring. Offentlige kanaler og lokal TEC er siden prøvd under M4; faktisk Avada/TEC-staging gjenstår. Se [sprint 2](SPRINT-02.md), [datakontrakt](DATAKONTRAKT.md) og [gjeldende testbevis](M0-M4-GJENNOMGANG.md).

## M3 – Kursadministrasjon og tilgang

- [x] Versjonert rolleoppsett for rnl_course_manager med egne capabilities og begrensede oppslag.
- [x] RegiNor Lite → Kursperioder: opprett en ny periode og grupper, angi tider/saler/instruktører/priser/lenker.
- [x] Opphold, konkrete datoer, kollisjoner, synlighets-/salgsgrenser og forhåndsvisning før publisering.
- [x] Optimistisk versjonskontroll, historikk og logg over kritiske endringer.
- [x] Kopier senere en RegiNor-periode som ny kladd med nye ID-er og nullstilte vinduer/koblinger. Første periode opprettes fra bunnen av.
- [x] Automatiske tester av direkte admin/REST, rettigheter for generisk skriving/sletting, papirkurv og samarbeid. Rå CPT-REST er lukket. Senere kart-/LetsReg-funksjoner bruker egne avgrensede AJAX-ruter; samlet kursimport har egen kontrollert handling.
- [x] Periodetabell med nyeste først, status/dager/sted/datoer og egne sider for Periode → Kurs → Publiser.
- [x] Kursinnhold og ressurser erstatter menyen Beskrivelser og steder. Administrator vedlikeholder nivåutvalg; kursansvarlig velger nivå under Undervisning.
- [x] Instruktør valgfritt; undervisning utenfor synlighetsvindu og sen påmelding krever ikke særskilt godkjenning.
- [x] Progressive kursfrie dager, samlet inngang til øktendringer, feltkort/hjelp og løpende validering. Valgfri undervisningsslutt håndheves på server; synlighet/salg er uavhengig. Se [testbevis og avgrensning](BRUKERFLYT-OG-SPRAK.md).
- [x] PHP-/JavaScript-I18n, POT og WPML-kontrakt for visningstekster og oversatt kursside.
- [ ] Prøv faktisk WPML String Translation, språkvelger, cache og SEO per språk i staging.
- [ ] Visuell nettleser-/tastaturprøve og A15-brukertest med faktisk kursansvarlig.

**Leveranseport:** kode og automatiske tester for A9/A13 og R1–R3/R10 er levert i [sprint 3](SPRINT-03.md). HTTP-testen gjennomfører hovedflyten med bare kursansvarligrollen. Menneskelig A15-brukertest og visuell kvalitetssikring gjenstår; kriteriene er ikke formelt godkjent.

## M4 – Offentlig kursreise og Avada/TEC

- [x] Kartlagt kalenderrepresentasjon av kursperioder i TEC. [Valgt visning, bruk og teknisk kontrakt](TEC-KURSPERIODER.md).
- [x] Avklart én TEC-oppføring fra periodestart til periodeslutt med lenke til alle kurs. Valgfri kobling er implementert mot lokal TEC 6.17.5; staging gjenstår.
- [x] Felles TEC-arrangementskategori og standard fremhevet bilde under Nettsidevisning, med mediebibliotek og oppdatering av eksisterende kalenderoppføringer.

- [x] Fremheving uten tekstetikett i frontend; stjerne i administrasjonen.

- [x] Drop-in-banner som overlegg uten å forskyve innhold i kursliste, kalender eller detaljer.

- [x] 8 px innvendig luft rundt kurskortenes sider i salkolonnene.
- [x] Valgfrie fargeoverstyringer per ukedag og sal, kompakte periodehandlinger og kursmerker for fremheving/drop-in.
- [x] Valgfritt kartpunkt per kurssted med adressesøk/kartvelger. Kursprofilen viser kart automatisk i egen hovedkolonneboks med adresse/sal, og sidekolonnens adresse lenker dit. Kartressursene støtter både permalenker og query-adresser. [Kartleveranse og begrensninger](KARTVELGER.md). Kartfliser/markør og anker visuelt prøvd lokalt; faktisk Avada-/cache-/samtykkeoppsett gjenstår.

- [x] Felles Utseende-side med bakgrunner, palettkobling, alpha og standardvisning; kursfarge/fremheving og drop-in/pris i kursoppsettet. Se [leveranse og avgrensninger](UTSEENDE-OG-DROPIN.md).
- [ ] Visuell prøve av fargeprøver/popover og Avada-paletten, inkludert ekstra farger, gjennomsiktighet og kontrast.

- [x] Felles PHP-renderer i dynamisk blokk og shortcode; administrator velger eksisterende kursside. Ingen produksjonsinnbygging er gjort.
- [x] Kort/detalj med nivå, faktiske datoer, pris, sted og LetsReg-handling uten krav om JavaScript. Kursprofilen har dansestil-/nivåpiller ved tittelen og egne hovedkolonnebokser for beskrivelse, nivå/forkunnskaper, partnerinformasjon og prisvilkår. Kort/kalender viser kort nivåpill uten lang nivåforklaring eller prisvilkår.
- [x] Tidskolonnen tåler temaets etterfølgende avsnittsformatering (`wpautop`): klokkeslett forblir selvstendige rutenettelementer, uten linjebryting. Lokalt testet; ny pakke må kontrolleres i faktisk Avada etter installasjon.
- [x] Kursdetalj viser fakta, faktisk oppstart og påmelding før lang tekst i HTML-/tastaturrekkefølgen. Åpningsdato følger samme effektive salgsvindu som statuskontrollen.
- [x] Periode-/nivå-/dagvalg og tilbakeføring av valg via URL og fokus via progressiv forbedring. Manuell fokusprøve gjenstår.
- [x] Brukerdefinerte kursnivåer erstatter målgruppesnarveiene. Felles nivåfilter i liste/kalender, nivånavn på kurs og i schema, med filtervalg bevart ved retur. Ingen automatisk gjetting av nivå for eksisterende kurs.
- [x] Ukeskalender med dag/sal og fast tidsmønster uten datoer/ukevelger; avvik peker til faktiske økter. Mobil bruker lesbar dagliste.
- [x] Samme publiseringspolicy i leverte offentlige flater; skjuling i core søk/REST/feeds/sitemap kontrollert lokalt.
- [x] Lesetidspolicy uten cron-avhengighet, no-store på dynamiske responser og utløp av åpne sider med JavaScript.
- [x] Tidlig no-store også på andre enkeltsider med shortcode/kursblokk, inkludert nestede synkroniserte blokker. Anonym HTTP-prøve dekker begge innbyggingsmåter.
- [x] 0.1.3: fjernet kunstig ettminuttsutløp ved ukjent LetsReg-status, tydelig utløpsmelding, unik oppdateringsadresse og eksplisitt LiteSpeed/CDN no-cache. [Funn og oppsett](CACHE-OG-KURSOPPDATERING.md).
- [x] Lokale påmeldingsvalg: Egen påmeldingslenke og Kun drop-in uten LetsReg-kobling, med kursstyrt pris per kveld og statusbytte uten avpublisering. [Bruk](LOKAL-PAMELDING-OG-DROPIN.md).
- [ ] Bekreft cache-/CDN-unntak på faktisk kursinngang før innføring.
- [x] Schema fra faktiske økter og canonical med stabil gruppe-ID, kontrollert i lokal WordPress.
- [ ] Kontroller schema-validatorer og fravær av motstridende Avada/TEC/SEO-merking i staging.
- [ ] Mobil 320/390 px, 200 % tekst/zoom, tastatur, skjermleser og redusert bevegelse.

**Leveranseport:** kode/testbevis i [sprint 4](SPRINT-04.md). Visuell, menneskelig og faktisk integrasjonsprøve gjenstår før godkjenning av A1–A3/A6/A10–A12, U1–U8, R4–R8/R11–R12 og relevante I-kriterier i [samspillsplanen](SAMSPILL-AVADA-ACF-TEC.md).

Gjenstående arbeid for M0–M4 er konkretisert med ansvar og bevis i [G01–G08](M0-M4-GJENNOMGANG.md#gjenstående-kontroller-med-krav-til-bevis). Ingen staging-/brukerkriterier er krysset av på grunnlag av lokale automatiske tester alene.

Oppfølging 22. september: kursoversikten har mindre eksternt ikon, forkortede ukedager og kun skjermlesertittel for merker. Kort/kalender henviser til kurssiden for kategoriers kapasitetsdetaljer. Den åpne administrasjonsoversikten starter nå den avgrensede LetsReg-køen automatisk. Påmeldte og rapportert ledig vises separat i backend. Faktisk kapasitetssemantikk/pooler og servercron i produksjon er fortsatt åpne kontroller.

Oppfølging 22. september (0.1.9): statusmerker er forkortet og identiske på kort, kalender og kursprofil. Eksisterende riktekstflyt er kontrollert: enkel HTML bevares i kursbeskrivelse, nivåforklaring, partnerinformasjon og prisvilkår. Backend viser HTML-kode i vanlige tekstbokser; visuell teksteditor er ikke implementert.

## M4.1 – Permalenker, SEO og delingsmetadata

Oppfølging 20. september: eksisterende kurs får navnebaserte slugs automatisk før første offentlige visning. ID-baserte reserveadresser videresendes; administratorbesøk er ikke nødvendig.

Bestilt 20. september 2026. [Detaljert kontrakt og akseptansekriterier](PERMALENKER-OG-METADATA.md). Bygger videre på M4s offentlige visninger og faktiske økter; endrer ikke LetsRegs påmeldingsadresser.

- [x] `/kursrekke/` viser standard kursoversikt fra samme kortkode, med filtre, kort-/kalendervisning og felles synlighetskontroll.
- [x] Offentlige adresser `/kursrekke/rekkenavn/` for perioden og `/kursrekke/rekkenavn/kursslug` for et bestemt kurs i perioden.
- [x] Stabile, entydige slugs, vern mot rutekollisjoner og 301-videresending fra tidligere RegiNor-adresser og endrede slugs. Gamle ACF-adresser beholdes.
- [x] Samme synlighetskontroll for side, metadata, redirect og sitemap; skjulte kurs skal ikke eksponeres gjennom nye ruter.
- [x] Serverrenderte sidetitler, metabeskrivelser, canonical, robots og oppdaterte sitemap-lenker. Gode standarder fra kursinnhold og valgfrie overstyringer i kurs-/periodeoppsett.
- [x] Open Graph og metadata for sosiale delinger med tittel, beskrivelse, offentlig URL og bilde med definert reservevalg.
- [x] Event-schema fra faktiske kursøkter, med tidssone, sted, avlysning/flytting og verifiserte pris-/påmeldingsopplysninger; samordnet med eksisterende Course/CourseInstance-schema.
- [x] Lokasjonsmetadata fra kurssted: navn, adresse og verifisert kartpunkt som Place/PostalAddress/GeoCoordinates når tilgjengelig.
- [x] WPML-grunnlag, prøvd med stubber: språktilpassede adresser, metadata, canonical og hreflang uten kopiering av driftsdata.
- [ ] Samspill med Avada, TEC og nettstedets faktiske SEO-plugin, uten motstridende canonical-, Open Graph- eller schema-utdata.
- [x] Automatiske rute-/metadata-/synlighetstester (55 WordPress- og 62 HTTP-kontroller, inkludert standardoversikten på `/kursrekke/`).
- [ ] Stagingkontroll av søk, delingsforhåndsvisning, schema og eksisterende lenker.

**Leveranseport:** alle akseptansekriteriene i kontrakten er prøvd, og metadata er kontrollert i faktisk Avada/WPML/TEC-/SEO-oppsett. Implementeringsrekkefølge: adresser og identitet → SEO/deling → Event/sted → integrasjonsprøve. Ingen dato er avtalt.

## M4.2 – Kalendernedlasting og abonnement

Implementert og prøvd lokalt 20. september 2026; **klient-/staginggodkjenning gjenstår**. [Brukerflyt, teknisk kontrakt og K01–K10](KALENDER-OG-KURSDELING.md). Gjelder ett kurs med alle faktiske kurskvelder. TECs eksisterende heldagsoppføring for hele perioden er en annen eksport.

### M4.2a – Legg kursdatoene til én gang

- [x] «Legg til i kalender» på kursprofilen med nedlasting av `.ics`, kort forklaring og veiledning for Apple Kalender, Google Kalender og Outlook.
- [x] Felles kalenderprojeksjon fra faktiske økter, inkludert tidligere datoer, forskjøvet oppstart, opphold, sommertid, sal og sted.
- [x] Persistente kalender-/øktidentiteter og revisjon fra første fil, felles serializer for nedlasting og senere abonnement. Kopiering gir nye identiteter.
- [x] Ingen automatisk påmelding eller oppdatering av en nedlastet kopi; påmeldingshandlingen beholder prioritet.
- [x] Uavhengig ICS-parser, HTTP-tilgang/utløp, I18n-grunnlag og DOM-reservevalg prøvd lokalt.
- [ ] Visuell tilgjengelighet og faktisk filimport i Apple Kalender, Google Kalender og Outlook prøvd.

### M4.2b – Hold kursdatoene oppdatert

- [x] «Abonner på kursdatoene», stabil HTTPS-feed per kurs/språk, kopierbar adresse og enkel klientveiledning.
- [x] Flytting oppdaterer samme UID; avlysning og fjernede publiserte økter bevares som avlyste hendelser. Minimal kalenderhistorikk oppdateres atomisk.
- [x] Feed følger samme synlighet som kursprofilen, uten LetsReg-kall ved henting. Skjult/utløpt kurs gir ingen nye kalenderdata; tidligere private kopier kan ikke trekkes tilbake.
- [x] Ingen løfte om sanntid eller automatisk overgang til neste kursperiode. Ingen tilgang til brukerens private kalender eller personbasert måling av feed-henting.
- [ ] Faktiske abonnementer, flytting, avlysning og avslutning prøvd mot offentlig tilgjengelig testfeed i Apple Kalender, Google Kalender og Outlook.

**Leveranseport:** M4.2a kan leveres før abonnement. Hele K01–K10 må prøves før M4.2 samlet godkjennes; faktisk klientoppførsel og forsinkelse dokumenteres. Abonnement på hele perioder, personlige kursutvalg, toveis synkronisering og kalenderinvitasjoner etter kjøp er utenfor første leveranse.

## M4.3 – Delingsknapper på kursprofilen

Implementert og prøvd lokalt 20. september 2026; **klient-/staginggodkjenning gjenstår**. Bygger på M4.1s adresser og metadata. [Krav og D01–D07](KALENDER-OG-KURSDELING.md).

- [x] Diskret «Del kurset» med enhetens delingsmeny, «Kopier lenke», Facebook og WhatsApp. WhatsApps lenkeformat er dokumentert; faktisk Facebook-dialog og begge plattformprøver gjenstår.
- [x] Del gjeldende kursprofil på valgt språk med ren canonical og eksisterende Open Graph-bilde/tittel/beskrivelse; ingen innkommende besøks-/annonseparametre i delt lenke.
- [x] Ingen tredjeparts-SDK eller kontakt før handling. Forståelige reservevalg uten Web Share, JavaScript eller kopiering; avbrutt deling er normalt.
- [ ] I18n, mobil, tastatur, skjermleser, fokus og faktisk delingsforhåndsvisning prøvd uten å svekke påmeldingshandlingen.
- [x] Eksisterende LetsReg-sporing og samtykke beholdes. Ingen ny sporing i kalender-/delingshandlinger eller feed-henting.
- [ ] Valgfri senere måleutvidelse: kalender-/delingshendelser med egen kontrakt, rapportering og samtykketester. Klikk skal ikke telles som bekreftet deling, abonnement eller kjøp.

**Leveranseport:** D01–D07, faktiske plattformprøver og samme offentlige tilgangspolicy som M4.1. Instagram/Messenger kan tilbys gjennom enhetens delingsmeny; egne direkte knapper krever verifisert støtte. M5b er ingen avhengighet.

## M5 – Kapasitetsadapter

Oppfølging 22. september (0.1.8): siste gyldige status/kapasitet overlever utløpt kontroll, API-feil og cachetømming. Salgsvindu og nye gyldige leverandørendringer styrer automatisk status; historiske tall merkes med kontrolltid og felles disclaimer. Parkategorier vises samlet i hele par, ubegrenset som ∞ og fullt med venteliste som egen badge. Felles cachepolicy omfatter frontend/innbygginger/REST/AJAX. Tekstgodkjenning og administrators tekstredigering krever ikke avpublisering. Faktisk Cloudflare/LiteSpeed-oppsett, cachepurge og servercron må fortsatt kontrolleres ved utrulling.

Oppfølging 22. september: prosjekteier avklarer plassgrense 0 = ubegrenset. Implementert på arrangementsnivå med `maxAllowedRegistrations` og `registeredParticipants`: positiv grense minus påmeldte, aldri negativ ledighet. Ukjent grense er ikke ubegrenset. Eget maksimum per kategori mangler i prisendepunktet; prosjekteier har i etterfølgende avklaring bekreftet null som ubegrenset kategori.

Prosjekteier har bekreftet vellykket manuell token-/arrangørkontroll mot SalsaNor Oslo. M5.2 har nå lokal kurskobling og import av nye kursutkast, med felles navnesøk og eksplisitt kategorivalg. Importen er prøvd med syntetiske svar; visuell brukertest og faktisk importprøve gjenstår. Avgrenset M5.3-polling og valgfri automatisk påmeldingsstatus er nå implementert. Kategoritall er også levert i frontend/backend og prøvd med en avgrenset faktisk lesekontroll. Betydningen av salgbare plasser/pooler, driftskvoter og gjenstående tokenmodell i M5.1 samt M5.4-webhooks gjenstår. Se [arbeidsplan, API-grunnlag og dokumentasjonen vi trenger](LETSREG-API-M5.md).

- [x] Leverandøruavhengig lesetransport med fast HTTPS-opprinnelse, TLS-kontroll, ingen redirects, tids-/størrelsesgrenser og separate tilgangs-/retryfeil. Brukes av manuelle kontroller og valgfri automatisk statuskontroll.
- [x] M5.1: sammenlignet Betait-integrasjonen med offisiell OpenAPI 2.2.8. Dokumentert password-tokenflyt, ny Swagger-tokenadresse og affiliate-prefiks i brukernavnet. Se [API-kontrakten](LETSREG-API-KONTRAKT.md).
- [x] M5.1: faktisk tokeninnlogging og kontroll av valgt arrangør/affiliate bekreftet gjennom prosjekteiers rapporterte resultat «SalsaNor Oslo», med siste vellykkede kontroll 18.09.2026 kl. 22:47:04 som vist i administrasjonen. Senere avgrenset faktisk kontroll hentet to lokale arrangementer med ett tokenkall per runde; se [kapasitetsprøven](M5-KATEGORIKAPASITET.md). Dette er ikke en produksjonsgodkjenning.
- [ ] M5.1: avklar minste leserettigheter, testmiljø og tokenlevetid/fornyelse, samt leverandørens anbefalte tokenadresse for serverdrift.
- [x] M5.1: serverstyrt hemmelighetsoppsett, dokumentert password-tokenutveksling og administratorens manuelle tilkoblingskontroll mot valgt arrangør-/affiliate-ID. [Oppsett og testbevis](LETSREG-TILKOBLING.md).
- [x] M5.1 lokalt: egen Git-ignorert JSON-fil, montert utenfor Dockers webrot og lest uten shell-tolkning i WordPress/WP-CLI. Oppsett via `npm run env:configure`.
- [x] M5.1 første polling: ett token i minnet per avgrenset bakgrunnsrunde med inntil tre arrangementer, med konto-/utløpskontroll ved gjenbruk. Ingen persistent token-cache. Endret legitimasjon ugyldiggjør observasjoner.
- [ ] M5.1 drift: verifiser tokenlevetid, leverandørkvoter og eventuell gjenbruk/fornyelse etter kontrakten.
- [x] M5.2 søk: kompakt nedtrekksliste med oppstartsdato, én bryter for oppstart innenfor kursperioden og progressiv henting av flere treff.
- [x] M5.2 kursoppsett: ny seksjonsrekkefølge, én valgfri startdato, kontrollerbare LetsReg-forslag til undervisning og påmeldingsdatoer, samt fra-pris per person fra valgte kategorier. Ingen automatisk overskriving av kursoppsett. Se [brukerflyt og begrensninger](LETSREG-TILKOBLING.md#kursoppsett-og-utfyllingsforslag).
- [x] M5.2 fase 1: opprett kurs lokalt, søk etter arrangement hos LetsReg, og velg kategorier med rolle og påmeldingsform. Søk og kategorivalg under «Pris og påmelding» i kurseditoren, direkte versjonert koblingslagring også på publiserte kurs, og autofyll fra API-ets offentlige URL med eksplisitt valg ved lenkebytte; kopiering av periode nullstiller koblingen. Testet lokalt med syntetiske svar.
- [x] Delegert kursimport: kursansvarlig kan importere enkeltkurs og opptil 20 kurs samlet til en periode i kladd, med nye eller eksisterende beskrivelser. Egen importrettighet i rolleversjon 3, objektkontroll og kontrollert lagring; ingen utvidet tilgang til felles ressurser eller deltaker-/ordredata.
- [x] Delegert LetsReg-oppslag: kursansvarlig kan søke, velge kategorier og lagre lokal kurskobling med egen rettighet og objekttilgang. API-/arrangøroppsett forblir administratorstyrt. Kontrollgrunnlag bindes til brukeren som hentet det; ingen generelle API- eller deltakeroppslag.
- [x] Backendstil gjennomgått: manglende knapp-/sideklasser rettet, eget adminstilark, tydeligere overskriftshierarki, feltkort og tabellutforming. [Funn og visuelle kontrollpunkter](BACKEND-STILGJENNOMGANG.md).
- [x] M5.2 fase 2: enkelt- og bulkimport av opptil 20 LetsReg-arrangementer til en periode i kladd. Valg mellom ny beskrivelse fra API-et og eksisterende lokal beskrivelse; manuell sal og gjennomgang av undervisning/kategorier. Importliste med redigering og samlet opprettelse av kurs, beskrivelser, økter og koblinger. Signerte kildegrunnlag, duplikatvern, utløp, versjonskontroll og full tilbakeføring er testet lokalt. Se [importflyten](LETSREG-TILKOBLING.md#importer-et-nytt-kursutkast).
- [x] M5.2 innholdsoppfølging: kontobundet kontroll av arrangements-ID, `lastUpdate` og normaliserte kildefelt. Varsel i periodeoversikt og før/etter-visning på kurset; eksplisitt behold/oppdater/separat beskrivelse. Ingen redaksjonell bakgrunnsoverskriving.
- [x] M5.2 beskrivelsesgjenbruk: identiske importbeskrivelser gjenbrukes, gjennomgåtte endringer oppdaterer samme ID, og lokale redigeringer/store avvik/tvetydige treff krever valg. Delt bruk, versjonskontroll og samlet tilbakeføring er ivaretatt. [Regler og test](LETSREG-ENDRINGER-OG-BESKRIVELSER.md).
- [x] M5.2 beskrivelsesmal: fem tekstmarkører fordeler kursbeskrivelse, dansestil, nivå/forkunnskaper, partnerinformasjon og prisvilkår ved import. H-tagger er ikke nødvendige. Valgfri praktisk informasjon utelates; ugyldig mal beholder originaltekst og forklares. Feltvis kildegodkjenning og eksisterende vern videreføres. [Mal og Rueda-eksempel](LETSREG-BESKRIVELSESMAL.md).
- [x] 0.1.3: behold begrenset rik formatering og lenker gjennom import, lagring, feltfordeling og frontend; rens på inn-/utgang og behold lokale redigeringsvern.
- [ ] Prøv kildeendring, varsel, lokal overstyring, beskrivelsesgjenbruk og språk/cache i faktisk staging med et avtalt LetsReg-arrangement.
- [x] M5.2 kategorivisning: rapportert antall i administrasjonen og øvre grense per kategori i frontend. Salgsvindu, arrangement, partner, ukjent/feil/utløp og manuell status håndheves. Ingen kategori-/rollesummer.
- [ ] M5.2: verifiser kapasitetspooler, delte grenser og komplett kapasitetssvar før eksakte salgbare rolle-/pooltall aktiveres.
- [x] M5.2 grunnlag: manuell lesekontroll av ett arrangement og priskategori-ID-er under LetsReg-tilkobling, med arrangør-/affiliatekontroll, full responsvalidering og minimal privat forhåndsvisning. Navne-/kategorikobling og avgrenset statusberegning og kategoriers øvre kapasitetsgrenser er levert; eksakte rolle-/pooltall er fortsatt ikke aktivert.
- [x] M5.2 faktisk lesetest: prosjekteiers skjermbilde viser Rueda Øvet 1 (613037), med fire aktive priskategorier (1401566–1401569). [Testbevis](LETSREG-TILKOBLING.md#verifikasjon-og-neste-port). Delte kapasitetsgrenser og betydningen av ledighet er fortsatt uavklart.
- [x] M5.2 påmeldingsmodell for dette oppsettet: prosjekteier bekrefter pris per deltaker også ved parpåmelding; partner legges til som egen deltaker. Rolle (fører/følger) og påmeldingsform (enkelt/par) holdes adskilt. Ingen dobling av parkategorier eller summering av mulig delt kapasitet.
- [x] M5.3 første statusleveranse: periodisk arrangementskontroll under egen kontolås, maksimal alder, kontobinding, vedvarende feilventetid/Retry-After og tilgangsstopp ved 401/403. Valg per kurs; ingen nettverk under global kurslås.
- [x] Kursoversikt: AJAX-meny for automatisk/manuell status på publiserte kurs uten å endre periode, timeplan eller publisering. Statusvisning oppdateres hvert minutt fra lokal observasjon.
- [ ] M5.3 drift: verifiser kvoter, poolmodell, eventuell paginering, servercron og driftsvarsling mot leverandøren.
- [x] M5.4: funnet offisielle endepunkter og modeller for webhook-abonnementer; ingen abonnement opprettet.
- [ ] M5.4: bekreft mottakssignatur, leverings-ID, payload og retry; implementer mottak/deduplisering med polling som avstemming.

- [x] Privat demonstrasjonsadapter med ukjent, fersk, fullt, rollefordelt, utløpt og feil. Demotall brukes aldri i offentlige visninger; automatisk LetsReg-modus har separat kategorivisning.
- [x] Skill siste forsøk, siste suksess og utløp; offentlige kategoritall vises som øvre grenser, uten ubekreftet InStock.
- [x] Administrativt prøveoppsett og rettighets-/ratebegrenset «Kontroller nå» for kursansvarlig.
- [ ] Verifiser organisasjon, autentisering, rolle-/poolkapasitet, reservasjoner, datotolkning og kvoter før produksjonsgodkjenning av automatisk status.
- [x] Polling, varig kø, atomiske snapshots og retry for demonstrasjonen. Separat serverstyrt kjøring er dokumentert, ikke satt opp i produksjon.
- [ ] Verifiser live kø-/poolmodell, kvoter og eventuell webhook-kontrakt før produksjonsgodkjenning.
- [x] Demonstrasjonsdata vises aldri offentlig. Faktiske kategoriers øvre grenser utløper i åpne visninger og skjules ved ukjent/feil/manuell status; kontrollert lokalt.
- [ ] Verifiser faktisk CDN/cache og utløp av offentlige kategoritall, også i allerede åpne faner.

**Leveranseport:** demonstrasjon, lesetransport, kurskobling/import, automatisk status og kategoriers øvre grenser er implementert og prøvd lokalt. Faktisk tilgang og avgrenset lesing av arrangementer/kategorier er bekreftet; se [testbevis og begrensninger](M5-KATEGORIKAPASITET.md). Dette godkjenner ikke eksakte salgbare rolle-/pooltall eller alle L1–L10. Produksjonsdrift krever gjenstående kontrakt-, kvote-, cron- og cachekontroller. Webhooks er ikke implementert. Live API er valgfritt ved første innføring.

## M5a/M5b – Kampanjer og bekreftede konverteringer

Menyretting: Statistikk registreres etter hovedmenyen slik at WordPress bygger korrekt lenke og side-hook. Ny `test:admin-menu` prøver faktisk innlogging og HTTP-ruting; direkte kall til render-metoden alene oppdaget ikke denne feilen.

- [x] M5a: UTM/kampanjer, samtykket besøksøkt, side-/kursvisning og LetsReg-klikk; egen rapport under Statistikk.
- [x] Complianz-styring, standard av, separate statistikk-/markedsføringssamtykker, tilbaketrekking og 30-dagers opprydding. Egne GTM-hendelser uten ny Google-tag.
- [x] Lokale perioder med 12 kurs hver: tre kurs per sal, to saler mandag og tirsdag; ett forskjøvet kurs per periode. Idempotent opprettelse og tydelig oppstartsmerking i kalenderen.
- [ ] Verifiser faktisk Complianz/GTM/GA4-container, samtykke, cron og måling i staging.
- [x] M5b: gjennomgått offentlig API 2.2.8 og lokal kode; [dokumenterte ordrefelt og konkrete kontrakthull](M5B-KONTRAKTGJENNOMGANG.md). Ingen mottaker/konverteringssporing aktivert.
- [x] M5b: [offentlig finsøk](M5B-OFFENTLIG-RESEARCH.md) bekrefter tokenflyt, webhook-omfang og mulige refusjonskilder. Egne arrangørpiksler er omtalt, men referanseoverføring og callback-sikkerhet er fortsatt uverifisert.
- [x] M5b: vurdert brukerlevert `participant.registered`-eksempel med konvolutt, ordre-/kategori-/arrangements-ID og betalingsdato. [Eksempelvurderingen](M5B-KONTRAKTGJENNOMGANG.md#brukerlevert-webhook-eksempel) skiller mulig API-avstemming fra ubekreftet betaling og manglende besøksreferanse. Ingen rå deltakerdata lagret.
- [ ] M5b: administratorstyrt lesekontroll av webhook-katalogen og separat ordre-/refusjonsprøve på avtalt testarrangement. Vurder API-avstemming først, uten avhengighet av webhook-mottak; ikke likestill registrering/offline-test med betaling.
- [x] M5b: dokumentert prosjekteiers supportsvar i sak #134895094. LetsReg oppgir at etterspurt arrangørspesifikk sporing ikke tilbys i dag; ingen leveransedato oppgitt. [Konsekvens og avgrensning](M5B-KONTRAKTGJENNOMGANG.md#leverandørsvar-besøkskobling-satt-på-vent).
- [x] M5a/M5b observasjon: valgfri 30-dagers historikk over `Event.ordersTotalSum` og `registeredParticipants`, gjenbrukt fra autentiserte arrangementskontroller. Dagstabell med samtykkede klikk/kampanjer, eget aktiveringsvalg under Statistikk og manuell oppdatering. Ingen ordre-/deltakerregistre hentes.
- [x] Eksperimentell tidsvurdering med valgbart vindu, utgangspunkt, manglende felt, korreksjoner, datagap, konto-/kursbinding og delte arrangementer. Beregnes på nytt fra beholdte klikk; ingen kalibrerte prosenter, bekreftede salg eller kjøpshendelser til GA4/Ads. [Oppsett, kilde og begrensninger](KLIKK-OG-LETSREG-UTVIKLING.md).
- [ ] Verifiser arrangementstotalenes valuta, betalings-/refusjonsbetydning og oppdateringsforsinkelse med kjente testhendelser. Kontroller faktisk cron/kvoter og rapportens forståelighet i staging. Lokale syntetiske tester bekrefter kodeflyten, ikke økonomisk semantikk.
- [ ] M5b – **på vent:** dokumentert støtte for besøksreferanse eller arrangørspesifikk kjøpsmåling før besøkskobling implementeres. API-/webhook-autentisering og betalingssemantikk må uansett verifiseres separat.
- [ ] **På vent:** koble bekreftede salg idempotent til samtykket besøksøkt, med refusjoner og ukjent kilde. Kapasitetsendringer teller ikke som kjøp.

**Leveranseport:** [målekontrakt, GTM-oppsett og testdata](MARKEDSFORING-OG-ATTRIBUSJON.md). Lokal førsteversjon av M5a og separat arrangementshistorikk/tidsvurdering er levert; ingen live konverteringskobling er aktivert. Rapportert ordresum er ikke verifisert betalt omsetning.

## M6 – Innføring fra neste nye kursperiode

- [x] Første lokale testutgave **0.1.0**: [releasenotat](releases/0.1.0.md), versjonert ZIP og SHA-256-kontrollsum. Miljø-/brukergodkjenning og utrulling gjenstår.
- [x] Beskrivelsesmal levert som lokal testutgave **0.1.2** med [releasenotat](releases/0.1.2.md), ZIP og kontrollsum. Faktisk editor/API-roundtrip gjenstår.
- [x] Oppdatert lokal testutgave **0.1.1**: [releasenotat](releases/0.1.1.md), versjonert ZIP og SHA-256-kontrollsum med endringskontroll mot LetsReg og gjenbruk av beskrivelser. Live endringskontroll og visuell staging-prøve gjenstår.
- [ ] Opprett den første reelle nye perioden direkte i RegiNor med bekreftede opplysninger.
- [ ] Test hele arbeidsflyten i staging med 1–2 kursansvarlige og 4–6 deltakere.
- [ ] Bytt kursinngangen til RegiNor ved innføring; bevar gamle ACF-kursposter, registreringer og URL-er.
- [ ] Verifiser Avada/TEC, canonical, sitemap, cache og at gamle kurs ikke påvirkes.
- [ ] Prøv tilbakeføring av ny visning uten å slette nye RegiNor-data.
- [ ] Samle testbevis for relevante A-, U-, R- og I-kriterier, og L ved live integrasjon. A14 om kursmigrering utgår etter brukeravklaringen.
- [ ] Godkjenn relevante K-/D-kriterier dersom kalender/delingsknapper inngår i utrullingen. M5b er ikke en lanseringsforutsetning.
- [ ] Dokumenter driftsansvar, oppdateringer, sikkerhetskopi og utrulling.

**Leveranseport:** første nye periode er prøvd og godkjent i staging, med fungerende tilbakeføring. Ingen gammel kursrunde importeres. Datoen 3.–4. oktober i spesifikasjonen er et mulig behov, ikke en avtalt frist.

## Etter MVP

Automatisk nybegynnerside og eventuelle senere kalenderutvidelser som periodeabonnement/personlige kursutvalg. Kalendernedlasting og abonnement er nå konkretisert i M4.2, delingsknapper i M4.3. Behovsstyrt måling ligger i M5a/M5b etter prosjekteiers bestilling. Handlekurv, betaling, billetter, deltakerregister og egen venteliste inngår ikke i dagens leveranse. Mulige utvidelser for deltakerlister og oppmøte er beskrevet nedenfor som et separat fremtidig omfang. Import/migrering av gamle kurs er tatt ut av leveransen.

### Mulige fremtidige features – deltakerdrift og videre API-bruk

Registrert etter prosjekteiers ønske 21. september 2026. **Dette er kandidater til senere utvikling, ikke bestilte sprinter eller krav før staging/innføring. F08 har siden fått et implementert grunnlag i M5.2; øvrige kandidater er ikke implementert.** Prioriteringen er et forslag. En eventuell deltaker-/oppmøtemodul utvider dagens avgrensning uten deltakerregister og må spesifiseres før implementering.

| ID | Mulig feature | Verdi og foreslått omfang | API-grunnlag / avklaring |
| --- | --- | --- | --- |
| F01 | Deltakerliste per kurs | Mobilvennlig liste med navn, fører/følger, enkelt-/parpåmelding, søk, sist oppdatert, nye/endret påmelding og utskriftsvisning. Kontakt- og økonomiopplysninger får separate rettigheter. | `GET /events/{eventId}/orders/orderdetails` og ordrelinjemodellen gir et grunnlag. Verifiser kontotilgang, komplett liste, gratis/ubetalt, avmelding/refusjon og kobling til lokale kurs/kategorier. Datofiltrene gjelder betalingsdato, ikke kursdato. |
| F02 | Instruktør- og innsjekkroller | «Mine kurs i dag» med tilgang bare til tildelte kurs, kurssteder eller vakter. Ingen generell tilgang til WordPress-backend eller API-oppsett. | Lokal rolle- og tildelingsmodell. Rettighet og konkret kurs/økt kontrolleres på server ved hvert oppslag og hver endring. |
| F03 | Oppmøte per undervisningskveld | Navnesøk → «Møtt» → angre. Antall påmeldte/møtt og historikk over hvem som korrigerte oppmøtet. | Lokal registrering knyttet til stabil deltaker-/ordrelinjereferanse og faktisk lokal kursøkt. Én påmelding kan gi oppmøte på flere kurskvelder. Ingen offentlig innsjekkrute funnet i det undersøkte integrasjons-API-et. |
| F04 | Oppmøterapporter og fører-/følgerbalanse | Oversikt per kurskveld og over perioden; skille mellom påmeldt rollebalanse og faktisk oppmøte. | Bygger på F01–F03 og eksisterende kategorikobling. Delte arrangementer, par og avmeldinger må ikke gi dobbelttelling. |
| F05 | Ventelisteoversikt | Kursansvarlig ser etterspørsel per kurs/kategori og kan følge opp i LetsReg. | `GET /events/{eventId}/waitinglist` er dokumentert. Automatisk invitasjon/flytting fra venteliste er ikke dokumentert i det undersøkte API-et og inngår ikke i første forslag. |
| F06 | Kursgenerator: opprette kurs hos LetsReg fra lokalt oppsett | Generer enkeltkurs eller en hel kursperiode fra standardbeskrivelser, dansestil, nivå, sal/sted, timeplan og prismaler. Bygg LetsReg-beskrivelse og arrangementsoppsett, forhåndsvis og opprett hos LetsReg med automatisk lokal kobling. Senere støtte for kontrollerte oppdateringer. Se konkretisering nedenfor. | Oppretting/endring av arrangement og priser er omtalt i tidligere API-kartlegging. Eksakte skrivefelt, skriverettigheter, kladd/publisering, rabatter og kategorimodell må verifiseres før implementering. |
| F07 | Økonomisk avstemming | Kontroll av ordre, registreringer og oppgjør per arrangement, uten å utlede kampanjetilhørighet. | Ordre-/oppgjørsendepunkter finnes; oppgjørsoversikten krever utvidet API-rolle. Betalingsstatus, refusjoner, valuta og beløpsgrunnlag må verifiseres. Separat fra dagens rapporterte ordresum og fra M5bs besøkskobling. |
| F08 | Videreutvikling av avviksvarsler (grunnlag nå levert i M5.2) | Forklare ulikt navn, dato, klokkeslett eller pris, med lenke til relevant kurs og kontrolltidspunkt. | Sammenligne lokale opplysninger med dokumenterte arrangements-/prisfelt. Tillatte lokale avvik må kunne skilles fra feil; ingen lydløs overskriving. |
| F09 | QR-innsjekk og eventuell offlinebruk | Raskere innsjekk ved døren; mulig kø ved ustabil forbindelse. Senere utvidelse av F03. | LetsReg har egen innsjekkapp, men offentlig kontrakt for billettkoder og lesing/skriving av innsjekk er ikke funnet. Avklar kompatibilitet før LetsReg-billetter brukes. Offline krever egne regler for tilgang, lokal lagring, synkronisering og samtidige/doble innsjekkinger. |

**Foreslått rekkefølge etter innføring:** F01 + F02 → F03 → F04. F09 vurderes etter at mobilflyten med navnesøk er prøvd. F05–F08 prioriteres separat etter behov og verifisert API-tilgang.

### F06 – Kursgenerator og opprettelse hos LetsReg

**Fremtidig feature, presisert etter prosjekteiers ønske 21. september 2026. Ikke implementert eller del av release 0.1.2.** Målet er å sette opp kurs én gang i RegiNor og bruke dette som grunnlag for arrangementene hos LetsReg. Gjelder både ett kurs og flere kurs i samme periode.

Foreslått brukerreise:

1. Velg kursperiode og standardbeskrivelser. Velg dansestil, nivå/forkunnskaper, partnerinformasjon og eventuelle lokale teksttilpasninger per kurs.
2. Fordel kursene på ukedag, klokkeslett, sal og kurssted. Bruk periodens datoer, kursfrie dager og antall kvelder; håndter forskjøvet oppstart og varighet per kurs. Beregn faktiske økter og kontroller sal-/tidskollisjoner før overføring. Instruktør kan velges, men er valgfritt.
3. Velg prismal, fører-/følgerkategorier, enkelt-/parpåmelding, kapasitet, salgsperiode og prisvilkår. Parpris behandles som pris per deltaker. Rabatter, delte kapasitetsgrenser og andre LetsReg-spesifikke innstillinger inngår bare når API-støtten er verifisert.
4. Generer navn og samlet LetsReg-beskrivelse fra de lokale feltene med [felles beskrivelsesmal](LETSREG-BESKRIVELSESMAL.md). Praktisk informasjon kan bygges fra sted, instruktører og egne standardtekster. Vis ferdig tekst og arrangements-/prisoppsett per kurs, med mulighet for justering før sending.
5. Bekreft «Opprett hos LetsReg». Vis resultat per kurs, lagre arrangements-ID, priskategori-ID-er og offentlig påmeldingslenke, og koble dem til riktig lokalt kurs. Utkast skal foretrekkes dersom LetsReg støtter det; offentlig publisering og åpning av salg må ha en tydelig, separat handling.

Foreslåtte utviklingsfaser og leveransekrav:

- [ ] **F06.1 – Lokal generator:** gjenbrukbare oppsetts-/prismaler, generering av lokale kursutkast og forhåndsvisning av LetsReg-tekst uten eksterne skrivekall. Endring av en mal skal ikke endre allerede opprettede kurs automatisk.
- [ ] **F06.2 – Opprett ett arrangement:** bekreft skrivekontrakt og kontotilgang på avtalt testarrangement, inkludert påkrevde felt, kategorier, kladd/publisering, tidszoner og salgsdatoer. Sal beholdes som lokal planleggingsressurs; eventuell overføring som sted eller tekst må defineres eksplisitt.
- [ ] **F06.3 – Opprett en hel periode:** kø med fremdrift og resultat per kurs, leverandørens kvoter og trygg gjenopptakelse. Et tidsavbrudd etter mulig opprettelse må avklares mot LetsReg før nytt forsøk, slik at samme kurs ikke opprettes to ganger. Delvis fullført overføring må kunne repareres uten å gjenta vellykkede kurs; lokale transaksjoner kan ikke rulle tilbake eksterne opprettelser.
- [ ] **F06.4 – Kontrollert oppdatering:** sammenlign sist overførte verdier med lokale og eksterne endringer. Vis forskjell og avklar hvilket system som eier hvert felt. Ingen lydløs overskriving av endringer gjort direkte hos LetsReg; påmelding og betaling eies fortsatt av LetsReg.
- [ ] **Tilgang og sporbarhet:** administrator etablerer skrivetilgang. Egen rettighet for å opprette eksterne arrangementer kan tildeles kursansvarlig, med objektkontroll og historikk over hvem som sendte hva og resultatet. Dagens søk-/importrettigheter gir ikke automatisk ekstern skrivetilgang.
- [ ] **Godkjenning før bruk:** prøv enkeltkurs og periode, duplikatvern, delvis feil, gjenopptakelse, kategorikobling, endringer begge steder og publiserings-/salgsstatus. Verifiser at generert beskrivelsesmal kan leses tilbake uten å miste eller flytte innhold til feil lokale felt.

Foreslått ansvarsdeling og tilgang ved en eventuell oppmøtemodul:

- **LetsReg** er kilde for påmelding, betaling og registreringsstatus. **RegiNor** er kilde for undervisningsplan og lokalt registrert oppmøte.
- **Instruktør:** deltakerliste og oppmøte på egne tildelte kurs. **Innsjekkpersonell:** tilsvarende tilgang begrenset til tildelt dato/vakt/kurssted.
- **Kursansvarlig:** oversikt og korrigering innenfor sitt avtalte kursansvar. **Administrator:** API-oppsett og tildeling av rettigheter. Dette endrer ikke dagens roller før funksjonene eventuelt implementeres.
- Hent og vis bare opplysninger arbeidsoppgaven trenger. Avklar lagringstid, sletting, eksportrettigheter og endringslogg som del av modulens datakontrakt. Deltakeropplysninger skal ikke brukes til å gjette annonsekilde eller besøksidentitet.
- Prøv først med syntetiske data og deretter et avtalt testkurs: flere undervisningskvelder, delte arrangementer, etternølere, avmelding/refusjon, samtidige innsjekkinger og tilbakekalt tilgang. Oppmøteregistrering skal ikke innebære ny påmelding eller betalingsbekreftelse.

**Kildegrunnlag for kandidatene:** offentlig [LetsReg OpenAPI 2.2.8](https://integrate.deltager.no/swagger/v1/swagger.json), kontrollert 21. september 2026, og [LetsRegs egen appbeskrivelse](https://play.google.com/store/apps/details?id=com.letsreg.app). Dokumentert API-funksjon er ikke bevis for kontotilgang eller gjennomført integrasjonstest. Synkronisering av lokal innsjekk tilbake til LetsReg er fortsatt uavklart.
