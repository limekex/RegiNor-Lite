# M5 – Fra demonstrasjon til verifisert LetsReg-tilkobling

Oppdatert 19. september 2026 etter gjennomgang av prosjekteiers Betait-repo og leverandørens OpenAPI. Prosjekteier ønsker å lukke hull i M5, med API-autentisering som første steg. Dette dokumentet skiller forberedelser i kode fra verifisering mot den faktiske SalsaNor-kontoen.

Oppfølging 20. september: avgrenset bakgrunnspolling og valgfri automatisk påmeldingsstatus er implementert, med manuell overstyring direkte på publiserte kurs. [Gjeldende brukerflyt, driftsgrenser og testavgrensning](PAMELDINGSSTATUS.md).

Oppfølging M5.2/M5.3: kategoriantall vises i backend og frontend som dokumenterte øvre grenser fra valgte kategorier og arrangementet. [Regler, parpåmelding, ferskhet og åpne leverandørkriterier](M5-KATEGORIKAPASITET.md).

## Avklart og uavklart

Prosjekteier har oppgitt [Swagger](https://integrate.deltager.no/index.html) og eksisterende Betait-integrasjon. OpenAPI 2.2.8 beskriver OAuth2 password flow med tokenadresse `https://integrate.deltager.no/swagger/token` og brukernavn på formen `affid:username`. Betait bruker legacy-adressen og separat `affid`-felt. Se [kontrollert kontrakt og forskjellene](LETSREG-API-KONTRAKT.md).

API-base, tokenflyt, arrangøroppslag, kapasitetsfelt og webhook-abonnementer er nå dokumentert. Prosjekteier har rapportert vellykket faktisk token-/arrangørkontroll mot SalsaNor Oslo, med vist tidspunkt 18.09.2026 kl. 22:47:04. Tokenlevetid/refresh, rolle-/poolsemantikk og innkommende webhook-autentisering er fortsatt ikke verifisert. RegiNor har også en første arrangements-/priskategorikontroll. Lokalt Docker-oppsett bruker en separat JSON-fil utenfor webroten. Se [serveroppsett og testbevis](LETSREG-TILKOBLING.md).

## Første kodeleveranse

`Infrastructure/ReadOnlyApiTransport.php` er et leverandøruavhengig fundament for lesekall. Klassen brukes nå av administratorens manuelle arrangørkontroll. Den er ikke koblet til demonstrasjonskøen eller offentlig kapasitet. Nettverkskall skjer ved eksplisitt kontroll eller bakgrunnskjøring for kurs med automatisk status og gyldig serveroppsett.

- Én eksplisitt HTTPS-opprinnelse per klient, oppgitt av en fremtidig, dokumentert adapter. Ingen vertsadresse fra kurslenker eller innkommende varsler.
- Relative stier og headerverdier valideres før sending. WordPress' sikre HTTP-klient kontrollerer adresser; TLS-verifikasjon er på, og videresending følges ikke. [WordPress: wp_safe_remote_get](https://developer.wordpress.org/reference/functions/wp_safe_remote_get/).
- Ti sekunders tidsgrense, svargrense 256 KiB og ingen automatisk retry inne i HTTP-kallet. Grensene er foreløpige prosjektvalg og må kontrolleres mot leverandørens kontrakt.
- `401` og `403` får separate feilkoder og er ikke automatisk retrybare. `429` og `5xx` gir retrybar feil; gyldig `Retry-After` som sekunder eller HTTP-dato bevares. Manglende/ugyldig `Retry-After` gir ingen leverandørfrist og må håndteres med køens backoff.
- HTTP-feil og rå feilsvar returneres ikke videre. Ingen nettverkskall tillates mens RegiNors globale kurslås holdes.
- Bare komplette, størrelsesbegrensede JSON-svar aksepteres på transportnivå; `206`, HTML-innlogging og ugyldig JSON avvises. Store JSON-ID-er beholder presisjonen som tekst.

Et gyldig JSON-svar er **ikke** en bekreftet organisasjon, ferdig paginering eller bekreftet kapasitet. En konkret adapter må kontrollere disse forholdene og normalisere tillatte felter før lagring. Vellykket rådata skal ikke loggføres eller lagres direkte. Transportlaget lagrer ingen hemmeligheter. LetsRegConnection leverer Bearer-headeren etter en separat token-POST. Tokenet brukes kun i minnet under én manuell kontroll eller bakgrunnsjobb; levetid og eventuell automatisk fornyelse må fortsatt verifiseres. Nettstedets andre plugins og HTTP-debugging må også kontrolleres før ekte nøkler tas i bruk.

## Gjennomføringsrekkefølge

| Steg | Konkret leveranse og ferdigkriterium | Status |
| --- | --- | --- |
| M5.1 Tilgang og autentisering | Dokumentert API-versjon, test-/produksjonsadresse, kontotilgang, autentiseringsmetode, nøkkelrotasjon/tokenlevetid og minste lesetilgang. Autentiser et ufarlig oppslag og bekreft SalsaNors organisasjons-ID mot svaret. | Manuell token-/arrangørkontroll implementert og testet med syntetiske svar. Prosjekteier har bekreftet faktisk tilgang til SalsaNor Oslo. Minste rettigheter, avtalt testmiljø og tokenmodell for automatisk polling gjenstår. |
| M5.2 Kurskobling og datakontrakt | Velg riktig arrangement/produkt/pool via stabile ID-er og dokumentert kontotilhørighet. Verifiser fører/følger/par, delte pooler, midlertidige reservasjoner, faktura og refusjon. Ett helt kontrollert testbilde. | Prosjekteier har vist faktisk henting av arrangement 613037 med fire aktive priskategorier, og bekreftet at parpris gjelder per deltaker med separat partnerregistrering. Lokal kurskobling og enkelt- og bulkimport av nye kursutkast er implementert, med felles navnesøk og eksplisitt rolle/påmeldingsform. Import og kobling er prøvd med syntetiske svar. Faktisk importprøve og API-feltenes kapasitetssemantikk gjenstår. |
| M5.3 Kontrollkø og offentlig aktivering | Ta kortvarig jobb-/poollås, hent uten global kurslås, og lagre bare hvis jobbversjon/konfigurasjon fortsatt stemmer. Full paginering, kvoter, vedvarende konto-/poolbackoff, gjenopptak etter avbrudd, tilgangsvarsel og serverstyrt kjøring. Aktiver bare godkjente live observasjoner. | Demokø og avgrenset arrangementspolling for automatisk status finnes. Kontolås, feilventetid, tilgangsstopp og utløp er testet syntetisk. Faktiske kvoter, poolmodell, driftsvarsling og produksjonsprøve gjenstår. |
| M5.4 Webhooks og avstemming | Verifiser leverandørens signatur/autentisering, konto, tidsvindu, leverings-ID og retry. Lagre en minimal jobb før kvittering, dedupliser, hent autoritativt API-bilde og behold polling som reparasjon. | Abonnements-API dokumentert; kontrakten for mottak gjenstår. Polling kan være eneste kilde dersom sikre webhooks ikke støttes. |
| M5b Bekreftede konverteringer | Dokumentert overleveringsreferanse gjennom registrering/betaling og tilbake i API/webhook, med kjøps-/påmeldings-/refusjonsstatus. Idempotent kobling til samtykket besøksøkt. | Separat fra kapasitet, ikke implementert. |

L1–L10 står åpne til de er prøvd mot et avtalt LetsReg-testarrangement. En lokal test med syntetiske svar lukker ikke et leverandørkriterium.

## Implementert autentiseringsoppsett

Administrator har arbeidsflaten **LetsReg-tilkobling** med serveroppsett og manuell kontroll. I kursoppsettet kan både administrator og kursansvarlig søke etter arrangementer hos konfigurert arrangør, velge priskategorier og lagre lokal kurskobling. Kursansvarlig bruker en egen rettighet og objektkontroll; API-oppsett og generelle driftskontroller er fortsatt administratoroppgaver. Enkeltimport og bulkimport er tilgjengelig for kursansvarlig med `rnl_import_letsreg` (rolleversjon 3), kursopprettingsrettighet og tilgang til perioden. Kursansvarlig kan også bestille tillatte kapasitetskontroller. Ingen adgang til nøkler eller globale kontovalg.

Hemmeligheter legges i serverens hemmelighetskonfigurasjon, ikke i repo, offentlig JavaScript, vanlige WordPress-options eller rå hendelseslogger. Affiliate, brukernavn og passord er grunnlaget for den dokumenterte flyten; konfigurasjonsnavnene og fremgangsmåten finnes i [oppsettsveiledningen](LETSREG-TILKOBLING.md). Grensesnittet skal vise «mangler», «ikke kontrollert», «bekreftet for [organisasjon]» eller en forståelig feil – ikke skrive ut hemmeligheten. Bytte av konto/legitimasjon skal ugyldiggjøre tidligere verifisering og stoppe bruk av gamle bekreftelser.

Autentisering er bare første kontroll: en vellykket HTTP-status alene er ikke nok. Kontotilhørighet og tillatte ressurser må bekreftes før kurskoblinger godtas. Test-/produksjonstilgang holdes adskilt. `401/403` skal stoppe automatisk tilgangsretry og gi administrator beskjed; det skal ikke skape en token-/forespørselsstorm. Den manuelle kontrollen har egne tilgangsfeil, ventetid og ingen automatisk retry. WP-Cron-polling er implementert; en avgrenset bakgrunnsrunde kan gjenbruke ett gyldig token i minnet for inntil tre arrangementer. Token-cache/refresh og faktisk serverplanlegging gjenstår.

## To arbeidsflyter, felles koblingsmodell

Prosjekteier prioriterer **lokal opprettelse først**: periode og kurs opprettes i RegiNor; administrator eller kursansvarlig søker på arrangementsnavn direkte i kursoppsettet og velger treff/kategorier. Eierkontroll skjer automatisk. Kobling og valgt offentlig API-lenke lagres direkte; publiserte kurs trenger ingen ny publiseringsrunde. Andre utfylte kursfelt beholdes. Ingen API-kall utføres under lagring av lokale kurs.

Fase 2 støtter enkeltimport og **samlet opprettelse av opptil 20 kursutkast** i en lokal periode i kladd. Administrator eller kursansvarlig velger arrangementer, gjennomgår priskategorier, lokal sal og undervisning for hvert kurs og legger dem i en importliste før samlet opprettelse. Beskrivelser kan opprettes fra renset `Event.description` eller gjenbrukes lokalt. Nye beskrivelser opprettes i samme transaksjon som kursene; eksisterende beskrivelser overskrives ikke. Ingen eksisterende ACF-kurs migreres, og ingen kurs publiseres automatisk.

Begge flyter bruker `LetsRegConnection` for autentisert søk/oppslag, `LetsRegEventInspection` for minimal responsvalidering og `LetsRegMapping` for koblingsskjema, rettigheter og kontrollert kategorivalg. Koblingen inneholder arrangør-/affiliate-/arrangement-ID, kategorier, eksplisitt rolle/påmeldingsform og kontrollreferanse. `CourseRepository` deler standardverdier og validering mellom vanlig kursopprettelse og import. `CourseImport` planlegger øktene og lagrer kurs, økter og kobling samlet i én transaksjon. Ingen API-kall skjer under lokal forhåndsvisning eller bekreftelse.

Importen har signerte, brukerbundne kildegrunnlag (`LetsRegImportSource`) og forhåndsvisninger med 15 minutters levetid. Kildegrunnlagene er uavhengige av senere søk/valg og bundet til API-oppsettets HMAC; kontoendring eller påvist tilgangsfeil stopper bruk. Ingen rå credentials eller tokens inngår. Bekreftelse kontrollerer kilde, kontooppsett, periodeversjon og versjonene for kursbeskrivelse, sal og sted på nytt under lokal lås. Duplikatvern avviser overlappende kategorier for samme arrangement/konto i samme periode, inkludert kurs i papirkurven. Lagringsfeil ruller tilbake alle nye kurs og beskrivelser i importlisten, også når feilen skjer etter at første kurs er satt inn. Periodens grenser og kursfrie dager håndheves; kollisjoner vises som arbeid som må løses før publisering. Disse egenskapene er prøvd med syntetiske svar. Verken navn eller kategorivalg regnes som verifiserte kapasitetspooler. [Arbeidsflyt og sikkerhetsgrenser](LETSREG-TILKOBLING.md#importer-et-nytt-kursutkast).

## Dokumentasjon vi trenger fra LetsReg

Den offentlige OpenAPI-kontrakten er nå funnet. Følgende gjenstående avklaringer kan sendes til LetsReg av prosjekteier; ingen henvendelse er sendt fra dette arbeidet:

> Vi bygger RegiNor Lite for SalsaNor og har lest Integration API 2.2.8 på integrate.deltager.no. Kan dere bekrefte API-tilgang for kontoen vår og hvordan vi får en egnet API-bruker og et testmiljø?
>
> Swagger oppgir /swagger/token og brukernavn affid:username. Er dette anbefalt for serverintegrasjoner, og skal client_id=swagger brukes utenfor Swagger UI? Er legacyapi.deltager.no/token fortsatt støttet? Vi trenger tokensvar, tokenlevetid/refresh, anbefalt credential-rotasjon, minste leserettigheter, kvoter/Retry-After og test av valgt arrangør via /organizers/{organizerId}.
>
> For kapasitet trenger vi betydningen av salgbare plasser, fører/følger/par og delte pooler, samt hvordan reservasjoner, faktura, betaling, avmelding, flytting og refusjon påvirker tallene. Kan dere tilby et testarrangement og anonymiserte eksempelsvar?
>
> Tilbyr dere webhooks? I så fall trenger vi dokumentert autentisering/signatur, leverings-ID, tidsstempel, retry og eksempelpayload. For senere konverteringsmåling trenger vi også å vite om en tilfeldig ekstern referanse kan følge registreringen og returneres ved bekreftet påmelding/kjøp/refusjon. Vi ønsker ikke et lokalt deltakerregister.

Dokumentasjonslenker, tilgangstype og anonymiserte eksempler kan deles i prosjektet. Hemmelige nøkler/passord leveres gjennom avtalt sikker kanal og legges direkte i serveroppsettet.

## Verifikasjon

`npm run test:api-transport` besto 58 kontroller. Prøven avskjærer alle HTTP-kall med WordPress' testfilter og prøver adresse-/headergrenser, låsevern, `401/403/429/5xx`, redirects, feilsvar uten rådata, `Retry-After`, JSON og svarstørrelse. `test:capacity` besto 39 regresjonskontroller. Ingen forespørsler sendes til LetsReg. Kapasitetsdemonstrasjonen og offentlig reservevisning fortsetter uendret.
