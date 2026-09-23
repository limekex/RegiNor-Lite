# Kalender og deling av kurs – M4.2 og M4.3

Status: **M4.2a/b og M4.3 implementert og prøvd lokalt 20. september 2026**. Faktiske kalenderapper, SoMe-forhåndsvisninger, Avada/WPML og visuell tilgjengelighet er fortsatt åpne leveranseporter. Funksjonene bygger på M4.1 og krever ikke LetsReg-webhooks eller bekreftede kjøp. [Roadmap](ROADMAP.md) angir prioritet og gjenstående lanseringskontroller.

## Mål og avgrensning

Deltakeren skal enkelt kunne legge kurskveldene i sin egen kalender og dele en bestemt kursprofil med andre. Kalenderen henter faktiske økter fra RegiNor, inkludert flytting og avlysning. Dette innebærer ingen tilgang til brukerens private kalender, ingen WordPress-konto og ingen bekreftelse på påmelding hos LetsReg.

Første leveranse gjelder **ett lokalt kurs med alle dets faktiske kurskvelder**. Abonnement på alle kurs i en periode, personlig utvalg av flere kurs, toveis synkronisering, OAuth og automatiske kalenderinvitasjoner etter kjøp er egne senere behov. Å abonnere på et kurs skal aldri automatisk abonnere på neste kursperiode.

Eksisterende TEC-eksport gjelder én heldagsoppføring gjennom hele kursperioden. Den beholdes som egen funksjon og skal ikke brukes som kalendergrunnlag for de enkelte undervisningskveldene.

## M4.2 – Legg kursdatoene i egen kalender

### Brukerflyt

På kursprofilen, ved oversikten over faktiske kursdatoer, vises én rolig inngang: **«Legg til i kalender»**. Den åpner en enkel seksjon med to valg. «Meld meg på» beholder sin plass og prioritet.

| Valg | Forklaring ved handlingen | Resultat |
| --- | --- | --- |
| **Abonner på kursdatoene** | «Kalenderen din kan hente endringer i tider og steder automatisk. Oppdateringer kan ta tid.» | Åpne kalenderappen der dette støttes, eller kopier abonnementslenken og følg en kort veiledning. |
| **Last ned kursdatoene (.ics)** | «Legg alle kurskveldene til én gang. Denne kopien oppdateres ikke automatisk.» | En kalenderfil for import til en kalender brukeren selv velger. |

Under valgene: «Å legge kurset i kalenderen melder deg ikke på. Påmelding skjer hos LetsReg.» Ved avlyst kurs skal status være tydelig også her. Det gis ikke inntrykk av at en filnedlasting eller et klikk har fullført import/abonnement.

- Valg av **Apple Kalender**, **Google Kalender**, **Outlook** eller **Annen kalender** viser bare den korte veiledningen som trengs. Ikke vis alle tekniske fremgangsmåter samtidig.
- Abonnementets HTTPS-lenke er alltid tilgjengelig for kopiering. `webcal:` kan tilbys som ekstra appåpning der det er prøvd; den underliggende feeden bruker HTTPS.
- Google-veiledningen må ta høyde for at tillegg fra URL gjøres i nettleser på datamaskin; en mobilknapp må ikke love at abonnement opprettes direkte i appen. [Google: abonnement fra URL](https://support.google.com/calendar/answer/37100?hl=en).
- Apple-veiledningen viser hvordan abonnement kan legges i iCloud for visning på brukerens enheter. [Apple: kalenderabonnement](https://support.apple.com/en-ie/102301).
- Import er en kopi; abonnement er periodisk henting. Ingen lovnad om sanntid eller bestemt oppdateringsintervall. [Microsoft: import og abonnement](https://support.microsoft.com/en-us/outlook/import-or-subscribe-to-a-calendar-in-outlook-com-or-outlook-on-the-web).
- Nedlastingen og lesbar veiledning fungerer uten JavaScript. Kopiering har synlig reservefelt ved manglende støtte eller avvist tilgang. Alle handlinger har tekst, tastaturtilgang og tydelig tilbakemelding.

### M4.2a – Felles kalendergrunnlag og nedlasting

1. Bygg én kalenderprojeksjon fra de samme faktiske øktene som kursdetalj og schema. En kursprofil gir én kalenderfil med én hendelse per økt. Hele kurset tas med, også gjennomførte kvelder dersom profilen fortsatt er offentlig; ikke beregn antallet fra dagens dato.
2. Ta med kursnavn, faktisk start/slutt, sal, sted/adresse, eventuell flytte-/avlysningsinformasjon og lenke til gjeldende kursprofil. Hold beskrivelsen kort og uten markedsførings-/personidentifikatorer. Pris og ledige plasser inngår ikke som kalenderdata som kan bli utdaterte.
3. Kursfrie dager gir ingen hendelser. Forskjøvet oppstart, lengre økter, endret ukedag og annet sted følger faktiske økter, aldri en rekonstruert normaluke.
4. Kalenderidentitet etableres allerede her, slik at abonnement kan bruke samme hendelser senere. Stabil `UID` per lokal økt; kopi av en kursperiode får nye identiteter. Bruk persistente identiteter som ikke avhenger av navn, slug eller tidspunkt.
5. Lever UTF-8 iCalendar med `VCALENDAR`, `VERSION:2.0`, `PRODID` og `VEVENT`. Bruk `UID`, `DTSTAMP`, `DTSTART`, `DTEND`, `SUMMARY`, `DESCRIPTION`, `LOCATION`, `URL` og relevant status/revisjon. Korrekt tekstescaping, CRLF og linjefolding uten å dele UTF-8-tegn. Dette følger [RFC 5545](https://www.rfc-editor.org/rfc/rfc5545).
6. Første versjon bruker eksplisitte start-/sluttidspunkt i UTC, beregnet fra øktenes lagrede tidssone. Dermed skal lokal kursstart fortsatt være korrekt over sommertid. Ingen flytende klokkeslett eller generell gjentakelsesregel som overser opphold/flytting.
7. Samme serializer brukes for nedlasting og senere feed. Nedlasting har `text/calendar; charset=utf-8`, trygt filnavn og `Content-Disposition: attachment`. Kalenderen sender ikke møteinvitasjoner, deltakerlister eller påtvungne varsler.

### M4.2b – Abonnement og endringshåndtering

- Egen stabil HTTPS-feed per kurs og valgt tilgjengelig språk. Implementert adresse: `/?rnl_calendar=<kalender-id>&rnl_calendar_language=<sprak>`. Nedlasting legger til `rnl_calendar_download=1`. Query-endepunktet er valgt fremfor den tidligere foreslåtte `/reginor-kalender/`-ruten: det virker med både enkle og pene permalenker og overtar ingen eksisterende WordPress-seksjon. Kalender-ID er en vedvarende, tilfeldig kursidentitet, **ikke** en besøks-ID eller hemmelig tilgangsnøkkel. Rutekollisjoner må kontrolleres før endelig registrering.
- Feed-adressen og øktens UID skal overleve navne-/slugendring. Språkvariantene bruker samme øktidentitet; abonnementsnavnet viser kurs og periode. Kursansvarlig får ingen ekstra påkrevde kalenderfelt.
- Flyttet dato, tid eller sal oppdaterer samme UID. Kalenderrevisjon, `LAST-MODIFIED` og relevant `SEQUENCE` oppdateres ved endring i kalenderinnhold, ikke bare fordi noen henter feeden. Endret kapasitet skal ikke skape kalenderendringer.
- Avlyste kvelder beholdes med samme UID og `STATUS:CANCELLED` mens kurset er offentlig. Hvis hele kurset avlyses, markeres det i alle øktene. En egen erstatningskveld har ny UID; den avlyste kvelden beholdes som avlyst.
- Hvis omplanlegging fjerner en tidligere publisert økt, må minimal kalenderhistorikk bevare nok informasjon til en avlyst oppføring med gammel UID. Kun fravær fra en senere feed skal ikke brukes som garanti for at alle klienter fjerner hendelsen.
- Historikken oppdateres atomisk fra bekreftede kursendringer og inneholder bare nødvendig kalenderinnhold, ikke kopier av hele kurs-/brukerhistorikken. Allerede gjennomførte økter forsvinner ikke bare fordi datoen har passert.
- Implementasjonen må håndtere identitet/revisjon fra nedlastingsfasen, før abonnement slås på. Nettleseren skal ikke konstruere eller lagre kalenderens autoritative datoer.

### Synlighet, avslutning og drift

**Gjeldende offentlig synlighetspolicy beholdes.** En feed eller fil kan bare leses når kurset er offentlig etter samme policy som kursprofilen. Tilfeldig kalender-ID gir ingen særtilgang.

| Tilstand | Forventet respons |
| --- | --- |
| Offentlig kurs, også fullt eller med stengt påmelding | Fil/feed med faktiske økter. Påmeldingsstatus endrer ikke undervisningsplanen. |
| Offentlig kurs med flytting/avlysning | Oppdatert økt eller avlyst oppføring med bevart UID. |
| Kurset er ferdig, men fortsatt innenfor synlighetsvindu | Samme kalender med gjennomførte/avlyste økter, ingen automatisk ny periode. |
| Kladd, skjult, utløpt vindu, slettet eller ukjent kurs | Generisk 404 uten kursnavn, datoer eller omdirigering til skjult profil. |
| Tidligere tilgjengelig kurs blir offentlig igjen | Samme kalenderidentitet og siste publiserte revisjon. |

Kalenderklienter kan beholde tidligere importerte/hentede data. Å skjule kurset trekker ikke tilbake kopier som allerede er lagt i en privat kalender. Abonnementet viser derfor at oppdateringer er tilgjengelige **så lenge kurset er offentlig**. Dersom virksomheten ønsker kalenderoppdateringer etter at profilen er skjult, krever det en egen eksplisitt tilgangs-/arkivbeslutning; det bygges ikke inn som et stille unntak her.

Feed og fil bruker eksisterende `no-store`-policy og unntas fra fullside-/CDN-cache. Ingen LetsReg-kall ved uthenting. Eventuell senere støtte for betingede HTTP-svar må alltid kontrollere synlighet først, slik at 304 ikke omgår utløp. Oppfriskningshint er ikke garanti for klientens oppdatering. Produksjonsfeeden må kunne nås av eksterne kalendertjenester; localhost og innloggingsbeskyttet staging kan ikke brukes som bevis for Google-/Outlook-abonnement.

### Akseptansekriterier

- [x] K01: Filens antall/datoer/tider/steder samsvarer med kursets faktiske økter, inkludert tidligere kvelder, senere oppstart, pauser og sommertid.
- [x] K02: Samme kurs/økt beholder UID over nedlasting, gjenhenting, språk- og slugendring. Kopiert periode får nye UID-er.
- [ ] K03: Flytting oppdaterer samme hendelse; avlysning, erstatningskveld og fjernet publisert økt har dokumentert oppførsel uten duplikater i støttede klienter.
- [ ] K04: Kladd/skjult/utløpt kurs gir 404 både direkte og via gamle lenker, med varm cache. Ingen nye kalenderdata eksponeres etter skjuling.
- [x] K05: Norsk tekst, lange linjer og spesialtegn validerer i en uavhengig ICS-parser; tekst kan ikke injisere ekstra kalenderfelt.
- [ ] K06: Filimport prøvd i Apple Kalender, Google Kalender og Outlook. Gjentatt manuell import kan lage kopier; faktisk klientoppførsel dokumenteres og abonnement anbefales ved behov for oppdatering.
- [ ] K07: Abonnement prøvd fra offentlig tilgjengelig testfeed i alle tre klientfamilier. Dokumenter første innlesing, senere endring/avlysning, observert forsinkelse og hva som skjer når kurset skjules. UID alene er ikke et klienttestbevis.
- [ ] K08: Mobil, tastatur, skjermleser, uten JavaScript og avvist kopiering prøvd. Deltakeren forstår forskjellen på nedlasting, abonnement og påmelding.
- [x] K09: Kurslåsen brukes ved generering og historikk. Samtidige HTTP-uthentinger, kopi og tilbakeføring ved publiseringsfeil er prøvd; eksisterende lagringsprøver dekker versjonskonflikt. Gjenhenting uten innholdsendring lager ikke ny revisjon.
- [ ] K10: Drift dokumenterer HTTPS, rutekollisjoner, cacheunntak, språk, avvik og avslutning av abonnement. Ingen personbasert måling av feed-uthentinger.

## M4.3 – Delingsknapper på hver kursprofil

### Brukerflyt og kanaler

En diskret **«Del kurset»**-seksjon på hver offentlig kursprofil, etter hovedinformasjonen. Knapper har ikon og forståelig tekst; ikon alene er ikke nødvendig her. Deling skal ikke konkurrere med påmelding eller forflytte eksisterende innhold.

Implementerte handlinger:

| Handling | Oppførsel |
| --- | --- |
| **Del …** | Enhetens delingsmeny via Web Share API der den støttes. Aktiveres kun ved brukerhandling. |
| **Kopier lenke** | Kopier kursets rene canonical-adresse; bekreft først etter vellykket kopiering. Synlig, markerbar lenke ved feil eller uten JavaScript. |
| **Facebook** | Lenke til Facebooks `sharer/sharer.php` med offentlig kursadresse. Faktisk dialog/forhåndsvisning må fortsatt prøves fra offentlig staging. |
| **WhatsApp** | Støttet delingslenke med kort kursnavn og offentlig kursadresse; brukeren velger mottaker selv. |

Web Share krever nettleserstøtte, sikker kontekst og en brukerhandling. Reservevalg skal alltid finnes. [W3C: Web Share](https://www.w3.org/TR/web-share/).

Instagram, Messenger og andre apper kan finnes i enhetens egen delingsmeny, men hvilke mål som tilbys bestemmes av enheten. Egne knapper som lover innlegg/story eller direkte deling til en bestemt app inngår først når en støttet plattformkontrakt er verifisert. Det legges ikke til udokumenterte appadresser. Facebook-/WhatsApp-knappene bruker vanlige eksterne lenker uten popup-skript og må fortsatt prøves i nettleser og app. WhatsApps format er kontrollert mot [leverandørens veiledning](https://faq.whatsapp.com/5913398998672934). Metas dokumentasjon lot seg ikke hente (429/utilgjengelig); Facebook-knappen er derfor ikke plattformgodkjent. Ingen melding eller innlegg er sendt under utviklingen.

### Lenke, forhåndsvisning og personvern

- Del **den lokale kursprofilen**, ikke LetsReg, periodeoversikten eller en lenke til et tilfeldig kalenderkort. Den som mottar lenken skal kunne lese om kurset og velge påmelding.
- Bruk M4.1s gjeldende canonical for valgt språk. Fjern innkommende filter-, UTM-, `_gl`-, annonse-, besøks- og brukerparametre fra delingspayloaden. Den besøkendes egen kampanjekilde i samtykket statistikk endres ikke av dette.
- Open Graph-tittel og -beskrivelse følger kursprofilens metadata i M4.1. Bildet bruker kurs → periode → felles standardbilde. Ingen separate metadatafelt for knappene. Plattformen bestemmer til slutt hvordan forhåndsvisningen vises og når dens cache oppdateres.
- Ingen tredjeparts-SDK, iframe, sporingspixel eller nettverkskontakt til delingsplattformen før brukeren velger en handling. Eksterne vinduer åpnes sikkert og varsles i knappens tilgjengelige navn.
- Skjulte kurs har ingen delingsknapper eller private delingsmetadata. Tidligere delte lenker følger vanlig 404-/redirect-policy.
- Kursprofilens tekster, knappenavn og tilbakemeldinger er I18n/WPML-klare. Avbrutt delingsdialog er et normalt avbrudd, ikke en feilmelding.
- Deling krever ikke analysesamtykke. Eventuell måling bruker eksisterende Complianz-regler og kan aldri være nødvendig for at knappen skal fungere.

### Måling og gjennomføringsgrenser

**Valgfri senere måleutvidelse, ikke aktivert i denne leveransen:** separate hendelser er `course_share_click`, `calendar_download_click` og `calendar_subscribe_click`, med lokal kurs-ID og avgrenset kanal/handling. Disse finnes ikke i dagens hendelsesskjema og krever egne endringer i validering, rapportering, GTM-kontrakt og samtykketester før de aktiveres.

Et klikk er bare en valgt handling. Ikke tell åpnet/lukket delingsdialog som publisert SoMe-innlegg, eller nedlastet fil som bekreftet import/abonnement. Feedens automatiske gjenhentinger skal ikke registreres som besøk eller konverteringer. Ingen mottakerdata, private kalendernavn eller full utgående URL lagres i målingen. Bekreftet påmelding/salg forblir M5b.

### Akseptansekriterier

- [x] D01: Hver offentlig kursprofil har deling av riktig kurs og språk, med oppdatert canonical etter slugendring.
- [x] D02: Delingspayloaden inneholder ikke besøks-, annonse-, samtykke- eller innkommende kampanjeparametre.
- [x] D03: Native deling, avbrudd, manglende støtte og kopieringsfeil har DOM-prøvd reservevalg. Lenker/felt rendres uten JavaScript. Plattformknappene bruker vanlige lenker i ny fane, ikke skriptstyrte popup-vinduer.
- [ ] D04: Facebook/WhatsApp prøves på mobil og desktop med faktisk offentlig testprofil; bilde/tittel/beskrivelse kontrolleres. Ingen uverifisert Instagram-/Messenger-lovnad.
- [ ] D05: Tastatur, skjermleser, fokusretur, 320/390 px og zoom fungerer uten at påmeldingshandlingen blir mindre tydelig.
- [x] D06: Ingen kontakt til delingsplattform før handling, og ingen analysehendelse uten relevant samtykke. Eksisterende LetsReg-klikk og kampanjetilknytning beholdes.
- [x] D07: Skjult/utløpt kurs og gamle adresser følger M4.1-policy; ingen delingsvisning omgår offentlig tilgang.

## Implementasjon, drift og testbevis

- Kursdetaljen har «Legg til i kalender» ved datolisten og «Del kurset» etter hovedinformasjonen. Nedlasting og lesbare lenker/veiledninger fungerer uten JavaScript; native deling vises bare med nettleserstøtte. Ingen `webcal:`-snarvei tilbys før faktisk klientprøve.
- `CourseCalendar` lagrer kalender-ID og minimal økthistorikk i private metadata. Ved publisering og direkte endring av påmeldingsstatus oppdateres tilgjengelige språk innen samme transaksjon som kursene. Vanlig fullt-/stengtstatus endrer ikke kalenderen; avlysning gjør det. Feil ruller også publiseringen tilbake. Ved første offentlige visning/henting av eksisterende kurs opprettes grunnlaget under kurslåsen; senere henting avstemmer endret offentlig innhold atomisk. Ingen observerte endringer gir ingen ny revisjon. Kalenderhistorikk før funksjonen ble installert rekonstrueres ikke fra privat revisjonslogg.
- Flytting bevarer UID. Fjernede, tidligere offentlig lagrede økter beholdes som avlyst. Kopiering av kursperiode arver ingen kalender-ID/historikk. Språkvarianter deler økt-ID-er; nytt språk har standardhistorikken som reserve for tidligere fjernede økter.
- `CalendarEndpoint` håndterer GET/HEAD før temaet, med `no-store`, `noindex`, `nosniff` og generisk 404 for utilgjengelige mål. Andre metoder gir 405, midlertidig lagrings-/låsefeil 503. Ingen LetsReg-kall, analytics eller betinget 304 i kalenderendepunktet.
- Utrulling må bruke HTTPS, sørge for at kalenderlenken er eksternt tilgjengelig og unnta forespørsler med `rnl_calendar` fra sidecache/CDN. Ta med de nye private kalenderfeltene i vanlig WordPress-backup. Bevar disse ved gjenoppretting for å unngå nye UID-er. Test 404 etter skjuling også med varm CDN-cache.
- Ingen nye statistikkhendelser er innført. Eksisterende LetsReg-klikk, kampanjeparametre og samtykkestyring beholdes. Delingslenker bruker ren lokal kursadresse; eksterne delingslenker undertrykker referrer og sender ingen annonse-/besøksparametre.

Lokale prøver:

- `test:calendar`: **43 kontroller**, stabile ID-er, uendret gjenhenting, UTC/sommertid, varighet, slugendring, rollefri anonym tilgang, flytting, fjernet økt, avlysning, kopi, språk, utløp og tilbakeføring ved kalenderfeil under publisering.
- `test:calendar-http`: **26 kontroller**, ekte anonym HTTP, samtidige uthentinger, nedlastingsheadere, HEAD/405, flytting, slugendring, avlysning og 404 med betingede cache-headere. Filene leses også med den uavhengige Python-pakken `icalendar` 6.3.2.
- `ICalendarTest`: **2 tester / 28 assertions**, UTF-8-linjefolding, tekstescaping/injeksjonsvern, UTC, revisjoner og fravær av invitasjoner/varsler.
- `course-tools.test.cjs`: **3 atferdstester**, kopiering etter bekreftet suksess, avvist/manglende utklippstavle, ren native payload, avbrudd, feil og fokusretur.

Parseren er bare et testverktøy og følger ikke pluginpakken. Installer den isolert med `python3 -m venv /tmp/rnl-calendar-check` og `/tmp/rnl-calendar-check/bin/pip install -r tests/requirements-calendar.txt`. Kjør HTTP-prøven med `RNL_CALENDAR_PYTHON=/tmp/rnl-calendar-check/bin/python npm run test:calendar-http`. CI har samme parserprøve, men GitHub-kjøring etter push er ikke bekreftet.

**Gjenstår:** K03/K06/K07 krever faktisk import og abonnement i Apple Kalender, Google Kalender og Outlook fra offentlig testadresse. K04/K10 krever faktisk CDN/HTTPS/drift; K08/D05 krever visuell/menneskelig tilgjengelighetsprøve. D04 og reelt WPML-/Avada-samspill gjenstår. Nettleserverktøyet feilet ved oppstart med `Cannot redefine property: process`, så lokal visuell kontroll er ikke godkjent. Syntaktisk gyldig ICS er ikke bevis for klientenes oppdateringsforsinkelse eller avlysningsvisning. Ingen kalenderkontoer eller SoMe-kontoer er endret.
