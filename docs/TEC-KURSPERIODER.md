# Kursperioder i The Events Calendar

Implementert lokalt 18. september 2026. Prosjekteiers avklaring: **én kalenderoppføring fra kursperiodens startdato til sluttdato, med lenke til alle kursene**. Dette erstatter det opprinnelige forslaget om bare en oppstartsoppføring.

## Bruk

Åpne **Kursperioder → velg periode → 1. Periode** og kryss av **Vis hele kursperioden i arrangementskalenderen**. Lagre og bruk vanlig publiseringsflyt. En publisert periode tas tilbake til kladd før oppsettet endres. Valget er avslått på eksisterende perioder og foreslås ikke automatisk for nye.

Når perioden er publisert og offentlig synlig, opprettes ett heldagsarrangement med tittel **Kursperiode: [periodenavn]**. Det strekker seg fra periodens startdato til og med sluttdato. Hvis sluttdato ikke er satt, brukes siste faktiske kurskveld. Arrangementsteksten forklarer at undervisningen skjer på faste kursdager, og at kursoversikten viser tidspunkt, opphold og påmelding.

**Fremtidige kursperioder vises også før oppstart.** Alle publiserte perioder med kalenderdeling og et åpent synlighetsvindu får hver sin oppføring, ikke bare den nærmeste eller standardvalgte perioden. «Vis som kommende» styrer velgeren i kursoversikten, ikke kalenderdelingen. En fremtidig «Synlig fra»-dato holder oppføringen skjult frem til denne datoen; kladder forblir skjult. Salgsstart behøver ikke være passert.

Klikk i kalenderen går til RegiNors oversikt med denne perioden valgt. Det samme gjelder arrangementets vanlige permalink og lenken i TECs REST-svar. Direkte besøk på TEC-detaljen videresendes til kursoversikten. Kalendernedlasting bruker hele datospennet, med ICS-sluttdato satt til dagen etter siste inkluderende periodedag, slik heldagsformatet krever.

Dette er ikke et abonnement på undervisningskveldene. Nedlasting og abonnement på **ett kurs med faktiske økter** er implementert lokalt separat i [M4.2 – Kalender og deling av kurs](KALENDER-OG-KURSDELING.md). Den leveransen skal ikke erstatte eller utvide TEC-oppføringen med en hendelse per kurskveld.

Periodesiden viser status for kalenderdelingen og en direkte lenke til kursrekken når den er offentlig tilgjengelig. Under **Nettsidevisning → Arrangementskalender** finnes en oversikt over perioder med kalenderdeling, status og lenker til periodeoppsett og offentlig kursrekke. I **TECs arrangementsoversikt** har tilknyttede oppføringer de samme lenkene. Skjulte perioder har bare administrasjonslenke. Hvis TEC mangler, beholdes valget og siden forklarer at pluginen må aktiveres. Kalenderoppføringen kan ikke redigeres eller slettes direkte gjennom WordPress/TECs vanlige redigeringsrettigheter: endringer gjøres i RegiNor. Eksisterende, uavhengige TEC-arrangementer berøres ikke.

## Synkronisering og synlighet

### Felles kategori og bilde

Under **RegiNor Lite → Nettsidevisning → Arrangementskalender** kan administrator velge én fast arrangementskategori fra TECs eksisterende taksonomi `tribe_events_cat`, samt et valgfritt standard fremhevet bilde fra WordPress-mediebiblioteket. En lenke åpner TECs kategoriadministrasjon dersom kategorien må opprettes først. Ingen kategori eller bildefil opprettes automatisk.

Valgene lagres separat fra kurssidevalget og gjelder alle RegiNor-koblede kalenderoppføringer. Lagre legger oppdateringen i bakgrunnskø. Allerede synlige oppføringer oppdateres uten å opprette nye arrangementer. Skjulte oppføringer får gjeldende valg når de blir synlige igjen. «Ingen fast kategori» og «Fjern bilde» fjerner det tidligere valget fra oppføringene ved neste bakgrunnskontroll. Slettede kategorier og mediebilder behandles som tomme valg ved neste synkronisering. Uavhengige TEC-arrangementer endres ikke.

Bildet settes som arrangementets vanlige WordPress-fremhevede bilde, slik at TEC/temaets bildevisninger kan bruke det. Kalenderens valgte visning avgjør hvor bildet vises; det påtvinges ikke alle månedskalenderruter. Se [TECs bildefunksjon](https://docs.theeventscalendar.com/reference/functions/tribe_event_featured_image/). Det er ingen ekstra bildeoverstyring per periode i denne leveransen.

### Oppdatering

- Opprettelse og oppdatering bruker TECs ORM. Stabil kobling mellom periode-ID og arrangement-ID hindrer duplikater. Koblingen kan gjenfinnes fra arrangementet hvis lagringen ble avbrutt. [TECs API for opprettelse](https://docs.theeventscalendar.com/apis/orm/create/events/).
- Fra **0.1.17** skjer TEC-skriving bare i bakgrunnsjobben, ikke i sidelasting, innsendt skjema eller avslutningshook. RegiNor-endringer legger én deduplisert jobb i kø, tidligst fem sekunder senere. En periodisk kontroll hvert femte minutt håndterer også tidsvinduer og nye forsøk. Tidspunktene avhenger av at WordPress-cron faktisk kjører.
- Kalenderarbeidet bruker egen lås. Kurslagringens lås beskytter bare lesing av kildegrunnlaget; opptatte låser gir plass til annet arbeid. En nyere kladdstatus gjelder ved offentlig lesing selv om kalenderkopien venter på oppdatering.
- Ved kladd, avkrysset kalenderdeling, avlysning, slettet periode eller utløpt synlighetsvindu skjules egne TEC-oppføringer fra offentlig lesing; bakgrunnsjobben setter også kalenderkopien til kladd. Oppføringen og dens ID beholdes og gjenbrukes ved republisering. Ingen kalenderfiler som besøkende allerede har lastet ned kan tilbakekalles.
- Offentlige spørringer og direkte REST-oppslag har tilleggskontroll mot RegiNors synlighet. En foreldet publisert kopi skal ikke bli offentlig hvis en synkronisering feiler.
- Kalenderens HTML-cache omgås når den inneholder RegiNor-oppføringer. Kalender-/REST-svar får `no-store`. Ekstern sidecache/CDN og Avadas eventuelle egen caching må fortsatt kontrolleres i staging.
- Feil ved opprettelse/oppdatering rapporteres på perioden; kursdataene beholdes. Neste bakgrunnsjobb prøver igjen. Kø som har ventet over ti minutter, manglende registrert fullføring etter tre minutter og mislykket kølegging får egne kontrollkoder. Åpning av synlighetsvindu kan vises først etter neste vellykkede jobb; lukking og kladd håndheves også uten kalenderlagring.
- Kopiering lager en ny periode uten gammel arrangement-ID. Kalenderdeling kan følge med som valg, men kopien er kladd med nullstilte synlighetsvinduer og vises derfor ikke før ordinær publisering.

## Språk og avgrensning

Nye tekster bruker pluginens text domain. Lagret kalenderinnhold bygges i nettstedets standard språk-/locale-kontekst, slik at besøk på et annet språk ikke skriver om den felles kalenderoppføringen. Lenken til RegiNor løses i visningskonteksten. Faktisk WPML/Avada-installasjon må fortsatt prøves; det opprettes ikke automatiske WPML-kopier av TEC-arrangementet.

Det opprettes ingen TEC-billetter, ingen kjøps-/kapasitetskobling og ingen separate arrangementer per kurskveld. Dette er en representasjon av hele kursperioden etter prosjekteiers valg. Kurs- og påmeldingsdetaljer eies fortsatt av RegiNor/LetsReg. Kalenderrepresentasjonen bruker ikke Pro-gjentakelser eller Series. Steder i TEC dupliseres ikke automatisk; alle kurs og deres steder finnes via periodelenken.

## Lokal verifikasjon

The Events Calendar **6.17.5** er installert og aktivert bare i lokalt wp-env for integrasjonsprøvene. `test:tec` bruker syntetiske perioder og kontrollerer heldag, start/slutt, lenker, oppdatering uten duplikater, redigeringsvern, avpublisering, fravalg, automatisk beregnet sluttdato, tidsvinduer og kopiering.

`test:tec-http` prøver faktisk månedskalender, REST, videresending og ICS-nedlasting, inkludert fravær etter utløpt synlighet. 44 integrasjonskontroller og 15 HTTP-kontroller besto lokalt 23. september 2026. Utvidelsen dekker flere fremtidige perioder samtidig og administrasjonslenker uten å eksponere skjulte kursrekker. Integrasjonsprøven dekker også kategori/bilde ved opprettelse, bytte, fravalg og sletting, validering, rettigheter og TECs bildefunksjon. Testene rydder egne data og gjenoppretter kursside- og kalendervalg. CI installerer samme TEC-versjon for disse prøvene.

Faktisk Avada/TEC Pro/WPML, ekstern cache og visuell mobil-/tastaturprøve er ikke bekreftet. Nettleserverktøyet har ikke latt seg starte. Den lokale standardtemakalenderen er kontrollert via HTTP, ikke visuelt godkjent.

`test:admin-menu` besto 25 HTTP-kontroller med TEC aktivert, inkludert innlasting av bildevelgerens ressurser, lagring/videresending av kalendervalg, ugyldig nonce/bilde og avvist tilgang for kursansvarlig. Selve mediedialogen og bildevisningen i Avada er ikke visuelt kontrollert.

## Oppfølging av TEC Pro – 23. september 2026

Prosjekteier melder at fremtidig «Høst 3 2026» ikke opprettes i TEC på stage/prod med TEC Pro og siste pluginutgaver. Den offentlige kursrekken var tilgjengelig ved lesekontroll, mens offentlig TEC REST-søk etter «Kursperiode» ga null treff. Dette beviser ikke om oppføringen mangler helt, er kladd eller er filtrert bort; administrasjonsstatus og faktisk Pro-test gjenstår.

TEC beskriver at Pro/custom tables kan gi forekomst-ID-er også for enkeltarrangementer. Vår tidligere kobling sammenlignet slike ID-er direkte med WordPress-ID-er fra databasen. Versjon 0.1.13 normaliserer dem gjennom leverandørens filter, og verifiserer lagringsresultater mot samme stabile arrangementsidentitet. Eldre lagret kobling normaliseres ved neste synkronisering. Det opprettes fortsatt ett enkelt heldagsarrangement, ingen Pro-gjentakelse eller Series.

Kilder: [TECs hendelser og forekomster](https://docs.theeventscalendar.com/apis/custom-tables/events/), [filter for stabil post-ID](https://docs.theeventscalendar.com/reference/hooks/tec_events_custom_tables_v1_normalize_occurrence_id/).

Kalenderstatus skiller nå mellom publisering, synlighetsvindu med konkret åpningsdato, manglende offentlig kursside, manglende offentlig kurs, ugyldig sluttdato, databaselås, opprettelse, lagring og kategori/bilde. Kontrollene omgår ikke synlighet. Rå unntak og serverdata vises ikke til brukeren.

ID-kontrakten er lokalt prøvd med syntetiske forekomst-ID-er mot normaliseringsfilteret og faktisk TEC uten Pro. Dette er ikke en verifikasjon av den installerte Pro-versjonen i stage/prod.

### Lagringsfeil etter 0.1.13

Prosjekteiers skjermbilde bekrefter nå melding fra lagringsfasen for «Høst 3 – 2026». Denne meldingen kommer etter at perioden er kvalifisert for kalenderdeling og en arrangements-ID er tilgjengelig. Den viser ikke om ORM lot være å skrive, kastet et unntak eller bare returnerte feil resultatformat.

TEC oppgir selv problemet BTRIA-2310 i [ORM-oppdateringsdokumentasjonen](https://docs.theeventscalendar.com/apis/orm/update/). Versjon 0.1.14 leser derfor tilbake alle ønskede felt før en oppdatering godkjennes. Hvis de fortsatt avviker, prøves `Tribe__Events__API::updateEvent()` på samme eide arrangements-ID. Dette er TECs eldre API og brukes også av deres [REST-oppdatering](https://docs.theeventscalendar.com/reference/classes/tribe__events__rest__v1__endpoints__single_event/); ORM beholdes som førstevalg. Ingen direkte SQL-/metadataskriving brukes til å omgå TECs datohåndtering.

Det samme gjelder avpublisering. Ingen ekstra oppføring opprettes ved mislykket oppdatering. En mislykket reserveoppdatering gir et varsel med arrangements-ID, navn på avvikende felt og resultatkoder, uten rå unntak, innhold eller serverhemmeligheter. Kladd/synlighet og eierskapsvern beholdes.

Dette er lokalt testet ved feilinjeksjon i faktisk TEC. Den eksakte feilårsaken i Pro-stage er fortsatt ikke verifisert.

## Drift av bakgrunnsjobben – 0.1.17

Jobbene heter `rnl_tec_sync_soon` og `rnl_tec_sync_periodic`. De fjernes ved deaktivering. Administrator må sikre fungerende WP-Cron eller servercron, særlig hvis `DISABLE_WP_CRON` brukes. RegiNor endrer ikke hostingens cron-oppsett. Se [WordPress om cron](https://developer.wordpress.org/plugins/cron/) og [serverbasert kjøring](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/).

Det opprettes **én TEC-oppføring per kursperiode**, ingen oppføring per enkeltkurs. Lokal tilbakeføring til kladd oppdaterer status og historikk på eksisterende kurs etter tur i én transaksjon. Dette oppretter ikke kursene på nytt. WordPress-lagringshooks kjøres fortsatt, slik at andre utvidelser kan påvirke varigheten. Bakgrunnsflyttingen av TEC er ikke en garanti mot andre database- eller hookproblemer.

Lokal HTTP-test avdekket også at reserveoppdateringen feilet når TECs datovelgerformat var tomt. RegiNor bruker nå standardformatet i dette tilfellet, uten å endre TEC-innstillingen. Dette forklarer en lokal feil, ikke nødvendigvis tidsavbruddet i produksjon.

## Kødiagnose – 0.1.18

`RNL-TEC-SCHEDULE` er en feilkode, ikke en cron-hook. RegiNor-jobbene ligger i WordPress-cron, ikke TECs Action Scheduler. Under kalenderoppsettet finnes en sammenleggbar teknisk status med faktisk køtid for de to jobbene, siste start/avslutning, nettsteds-ID og informasjon om besøksutløst cron. I flernettstedsoppsett må riktig nettstedsadresse velges også i WP-CLI.

En køfeil viser nå om WordPress ikke fikk lagret cron-optionen, et filter avviste planleggingen, eller en annen feil oppstod. Eksisterende jobb etter en konkurrerende planlegging godtas, men ikke en ubekreftet duplikatmelding alene. Dette løser ikke aktive databaseblokkeringer. Se [hostingkontrollen](HOSTING-DATABASEKONTROLL.md).
