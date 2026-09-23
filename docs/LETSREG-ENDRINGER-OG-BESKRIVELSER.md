# LetsReg-endringer og gjenbruk av beskrivelser

Implementert lokalt 21. september 2026, testutgave 0.1.1. LetsReg er kilde for påmelding; lokalt redaksjonelt innhold overskrives ikke av bakgrunnskontroller.

## Endringskontroll

Koblede kurs kontrolleres av eksisterende LetsReg-kø, også når påmeldingsstatus styres manuelt og statistikk er avslått. Kontrollene deler kontoens lås, tokenflyt, begrensning på tre arrangementer per runde og feilventetid. Arrangementer med fersk kontroll hentes normalt ikke igjen før ti minutter har gått. Faktisk intervall avhenger av cron, antall arrangementer og API-tilgang. Offentlige sidevisninger henter ikke API-data.

API-et dokumenterer `Event.id` og nullable `Event.lastUpdate`. Sammen med affiliate-/arrangør-ID brukes disse til identitet og endringskontroll. Vi sammenligner også normalisert innhold: navn, beskrivelse, offentlig URL, kurs-/påmeldingsdatoer, priskategorier/priser og arrangementets aktiv-/publisert-/avlyststatus. Deltakerantall, ledighet og ordresum inngår ikke i innholdssammenligningen. Endret tidsstempel alene kan likevel gi varsel; API-et avklarer ikke hvilke operasjoner som oppdaterer det.

Kilde: [LetsReg OpenAPI 2.2.8](https://integrate.deltager.no/swagger/v1/swagger.json), kontrollert 21. september 2026. Tidsstempel er et signal, ikke garanti for at alle relevante endringer varsles av leverandøren.

Ved import eller første kobling beholdes det gjennomgåtte kildegrunnlaget privat på kurset. Nyere kontroller erstatter ikke dette grunnlaget før brukeren har vurdert endringene. Kobling til et annet arrangement oppretter nytt grunnlag. For eldre koblinger uten tidligere grunnlag vises **første sammenligning**, ikke en påstand om hva som historisk har endret seg.

## Brukerreise

1. **Kurs i perioden** viser «Endret hos LetsReg – se forskjellene» når nytt kildegrunnlag avviker. Varslingen skjer i administrasjonen, ikke via e-post.
2. På kurssiden viser **Endringer hos LetsReg** tidligere gjennomgåtte verdier og verdiene fra siste kontroll. **Kontroller LetsReg nå** henter nytt grunnlag gjennom eksisterende tilgangskontroll.
3. For endret beskrivelse vises også teksten som brukes lokalt og antall kurs som deler den. Velg **Godkjenn tekst – oppdater eksisterende** eller **Godkjenn tekst – opprett separat beskrivelse**.
4. **Gjennomgått – behold lokalt** anerkjenner kildeendringen uten å endre lokale felt. Nye endringer varsles igjen.

Fra 0.1.2 kan en [felles beskrivelsesmal](LETSREG-BESKRIVELSESMAL.md) fordele godkjent tekst på kursbeskrivelse, nivåforklaring, dansestil og partnerinformasjon. Uten mal oppdateres bare kursbeskrivelsen. Prisvilkår foreslås separat i kursoppsettet og kan fra 0.1.10 godkjennes direkte etter før/etter-sammenligning på kurssiden, også mens kurset er publisert. Priser, kursnavn, timeplan og publiseringsstatus beholdes. Andre kildeendringer forblir til gjennomgang og håndteres gjennom det eksisterende kursoppsettet. Oppdatering av en delt beskrivelse påvirker alle kurs som bruker den, også publiserte kurs; konsekvensen vises før godkjenning. Separat beskrivelse bytter bare beskrivelsestilknytningen for valgt kurs.

Utdatert kildegrunnlag, feilet kontroll, endret konto eller endret kildehash blokkerer godkjenning. Kurs-/beskrivelsesversjoner kontrolleres igjen ved lagring. Det utføres ingen nettverkskall inne i kursenes lagringstransaksjon.

## Import uten gjentatte beskrivelser

Importvalget heter nå **Hent og gjenbruk fra LetsReg**. Før bekreftelse vises hvilken av følgende handlinger som foreslås:

| Situasjon | Handling |
| --- | --- |
| Identisk normalisert beskrivelse med samme navn, dansestil, nivåforklaring, partnerinformasjon og målgruppe | Gjenbruk eksisterende beskrivelses-ID uten ny versjon. Samme arrangør/affiliate kreves. Dette gjelder også identiske beskrivelser fra ulike arrangementer. |
| Samme arrangement, mindre tekstendring, ingen lokale redigeringer | Vis før/etter og foreslå oppdatering av samme ID ved bekreftet import. Ingen bakgrunnsoverskriving. |
| Vesentlig avvik | Stopp vanlig importbekreftelse til brukeren har valgt oppdatering eller separat beskrivelse og forhåndsvist på nytt. |
| Lokale redigeringer etter import | Blokker overskriving. Behold eksisterende, flett manuelt som administrator, eller opprett separat. |
| Flere avvikende beskrivelser knyttet til samme arrangement | Ikke gjett hvilket objekt som skal erstattes. Be brukeren velge en konkret eksisterende beskrivelse eller opprette separat. |
| Eldre koblet beskrivelse uten importhistorikk | Identisk innhold kan gjenbrukes. Avvik kan ikke automatisk anses som trygge oppdateringer. |

«Vesentlig» er en konservativ lokal vurderingsregel: endret navn, dansestil, nivåforklaring eller partnerinformasjon, eller under 65 % overlapp mellom de unike ordene i beskrivelsestekstene. Det er et signal om manuell vurdering, ikke en semantisk klassifisering. Lignende navn eller tekst brukes aldri til å slå sammen ulike arrangementer; gjenbruk på tvers krever identisk normalisert innhold.

**Opprett separat hvis teksten er forskjellig** lager heller ikke en ny kopi av identisk innhold. Eksisterende valg **Bruk eksisterende lokal beskrivelse** beholdes. Eksisterende duplikater ryddes ikke automatisk og ACF-data berøres ikke.

Bulkimport bruker samme regler. Hvis to gjennomgåtte nye beskrivelser er identiske, kan den andre gjenbruke den første inne i samme transaksjon. Endringer i målbeskrivelse eller deling siden forhåndsvisning krever ny gjennomgang; en feil ruller tilbake hele bulkimporten, også oppdateringer av eksisterende beskrivelser.

## Tilgang og lagring

- Kursansvarlig kan kontrollere kilden og godkjenne oppdatering av dokumentert importerte beskrivelser med sin eksisterende koblings-/importrettighet. Oppdatering krever også tilgang til alle lokale kurs som deler beskrivelsen. Dette gir ingen generell ressursredigering eller API-administrasjon.
- Administrator kan gjennomgå en eldre beskrivelse uten importhistorikk. Registrerte lokale redigeringer beskyttes også for administrator ved kildeoppdatering.
- Privat metadata på beskrivelsen lagrer arrangementsopprinnelser og sist importerte lokale felt. Privat metadata på kurset lagrer sist gjennomgåtte kildefelt og hvem som gjennomgikk dem. Nyere kildegrunnlag er kortvarig og kontobundet.
- Tekster lagres gjennom eksisterende versjonshistorikk og WPML-flyt. Godkjente endringer oppdaterer kalendergrunnlaget for publiserte kurs. Ingen nye personopplysninger eller LetsReg-skriveoperasjoner innføres.

## Test og gjenstående prøve

`npm run test:letsreg-changes` prøver varsling, delte beskrivelser, gjenbruk, oppdatering, store avvik, eksplisitt separat beskrivelse, lokale redigeringer, eldre koblinger, kontroller med manuelt styrt status, kildefeil, noncer, rettigheter og samtidige ressursendringer med syntetiske API-svar. Eksisterende import-/bulk-/tilbakeføringstester og øvrige relevante regresjonstester kommer i tillegg.

Faktisk stagingprøve gjenstår: endre en avtalt testbeskrivelse hos LetsReg, kontroller varsel og før/etter-visning, prøv å beholde lokal tekst og godkjenne oppdatering, og kontroller delte kurs, språk, cache og kalenderabonnement. Ingen ekte LetsReg-arrangementer eller beskrivelser er endret i denne implementeringen.

## Publiserte kurs – oppfølging 22. september

«Godkjenn tekst – oppdater eksisterende» og «Opprett separat beskrivelse» kan brukes mens kurs og periode er publisert. Endringen vises straks, uten ny publiseringsrunde. Varsel, før/etter og vern av lokale redigeringer beholdes. En feilet kildekontroll gir fortsatt ikke tillatelse til å godkjenne et gammelt kildegrunnlag, selv om siste kjente påmeldingsstatus beholdes.

Administratorens tekstskjema er nå også synlig på publiserte kurs, med kursbeskrivelse, nivå/forkunnskaper, dansestil, partnerinformasjon og kursets egne prisvilkår. Bare disse tekstfeltene kan lagres; versjoner og rettigheter kontrolleres, delte beskrivelser varsles, obligatoriske publiserte tekster kan ikke tømmes, og kalendertekster oppdateres. Timeplan, periodeversjon, påmelding og publiseringsstatus beholdes. Kursansvarlig kan fortsatt godkjenne importerte LetsReg-tekster etter gjeldende rettigheter.

## Rik tekst og eldre prisvilkår – 0.1.10

Tidligere kildegodkjenning oppdaterte bare feltene på den delte beskrivelsen, ikke prisvilkårene som ligger på hvert kurs. Dermed kunne tidligere importert ren tekst bli stående, selv etter oppdatering av de andre beskrivelsene. Prisvilkår vises nå som et eget godkjenningsvalg med lokal tekst og foreslått tekst. Dette virker også når kilden allerede er markert som gjennomgått. Versjoner, rettigheter, kontotilhørighet, kildehash og fersk kontroll kreves fortsatt. Handlingen godkjenner ikke andre kildeendringer.

Backend har WordPress-editor med Visuell/HTML for beskrivelse, nivå/forkunnskaper, partnerinformasjon og prisvilkår. Dansestil/navn og nivåutvalgets korte forklaring forblir ren tekst. Administratorens tekstskjema på publiserte kurs kan lagre alle fire rike tekstfelt; prisvilkår gjelder bare det åpne kurset. Vanlige skjemaer, API-forslag og bulkimport synkroniserer editor og underliggende felt før forhåndsvisning/lagring.
