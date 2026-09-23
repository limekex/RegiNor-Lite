# Endringslogg

## 23. september 2026 – Testutgave 0.1.14: robust TEC-lagring

- TECs dokumentasjon oppgir upålitelige ORM-oppdateringssvar (BTRIA-2310). RegiNor kontrollerer nå faktiske lagrede felt etter oppdatering, inkludert publiseringsstatus, heldag, datoer og kurslenke.
- Hvis ORM ikke har lagret endringen, prøves TECs direkte oppdaterings-API på samme oppføring. Ingen erstatningsoppføring opprettes. Datovelgerformatet hos TEC respekteres.
- Ved fortsatt feil vises arrangements-ID, avvikende felt og kontrollkode, fremfor bare råd om versjon/feillogg. Suksessmerket lagres bare etter vellykket tilbakekontroll.
- Lokale integrasjonstester fremprovoserer manglende skriving, nullsvar etter vellykket skriving, falsk suksess, tomt oppslag, unntak og avvist reserveoppdatering. [Releasenotat](docs/releases/0.1.14.md).

## 23. september 2026 – Testutgave 0.1.13: TEC Pro-identitet og kalenderdiagnose

- Bruker TECs normaliseringsfilter for forekomst-ID-er. Stabil identitet ved opprettelse/kobling, godtar vellykket lagring med bekreftet ID for samme arrangement, og normaliserer ved tilgangskontroll, REST, ICS og lenker. Tomt/feilet svar eller resultat for annet arrangement avvises fortsatt.
- Retter eldre kobling som inneholder en forekomst-ID under ordinær synkronisering, uten å opprette en ekstra oppføring.
- Kalenderstatus viser konkrete hindringer og hvilken del av synkroniseringen som feilet, fremfor å forklare alle tilfeller med publisering/synlighet.
- [Releasenotat](docs/releases/0.1.13.md). Mulig Pro-kompatibilitetsfeil rettet; årsaken hos SalsaNor er ennå ikke bekreftet.

## 23. september 2026 – Testutgave 0.1.12: kursrekker i arrangementskalenderen

- Bekreftet eksisterende støtte for flere fremtidige kursperioder samtidig, uavhengig av standardperiode og «Vis som kommende». Publisering, kalenderdeling og åpent synlighetsvindu kreves fortsatt. Hjelpeteksten forklarer dette.
- Kalenderoppsettet viser perioder med kalenderdeling, status og lenker. TECs arrangementsoversikt får lenker til riktig periodeoppsett og offentlig kursrekke. Periodesidens kalenderstatus får offentlig kurslenke når perioden er synlig.
- [Releasenotat](docs/releases/0.1.12.md).

## 22. september 2026 – Testutgave 0.1.11: dagsfarger og salvisning

- Utseende har valg for dagsfarger i kun kalender, kun kursliste eller begge. Gjelder standardfarge og dagsvise overstyringer, med alpha/palett/tekstfarge. Kalender er bakoverkompatibel standard. På listekort går egen kursfarge og fremheving foran dagsfargen; enkeltkursdetaljen påvirkes ikke.
- Kalenderens saloverskrifter er sentrert med salnavn i eksisterende størrelse og sted på egen mindre linje. Salnavn er tydelig på listekort og i enkeltkursets fakta- og kartboks.
- Forhåndsvisningen viser kalender og kort med valgt fargeomfang. [Releasenotat](docs/releases/0.1.11.md).

## 22. september 2026 – Testutgave 0.1.10: rik tekst i backend og prisvilkår

- Rettet hull i oppdateringsflyten: formaterte prisvilkår kan sammenlignes og godkjennes separat på publiserte kurs, selv når LetsReg-grunnlaget allerede er gjennomgått. Ingen automatisk overskriving av lokale vilkår.
- Visuell WordPress-editor med HTML-fane for kursbeskrivelse, nivå/forkunnskaper, partnerinformasjon og prisvilkår i ressurser, kursoppsett og import. Synkronisering ved forslag, skjemalagring, AJAX, bytte av importkurs og bulkredigering.
- Administrator kan redigere prisvilkår i det publiserte kursets tekstskjema. Kursansvarlig får formatert lesevisning og kan godkjenne kursets importerte prisvilkår. Prisbeløp, timeplan og publisering beholdes.
- Gjennomgående test med lenker, avsnitt, fet/kursiv tekst og lister i alle fire offentlige seksjoner. Undersøkt offentlig Salsa nybegynner-side: andre seksjoner har avsnitt og avstand, mens eldre prisvilkår leveres som ren tekst med rå nettadresser. Tidligere generell HTML-bekreftelse i 0.1.9 dekket ikke dette oppdateringshullet. [Releasenotat](docs/releases/0.1.10.md).

## 22. september 2026 – Testutgave 0.1.9: korte statusmerker

- Ens statusmerker på kurskort, kalender og kursprofil: Påmelding Tilgjengelig, Åpner snart, Fullt, Drop-in, Fullt - Venteliste aktiv, Stengt, Avlyst og Må avklares. Avsluttet beholdes for avsluttede kurs. Kapasitetsmeldinger erstatter ikke lenger statusmerket. Påmeldingsregler, lenker og sporing er uendret.
- Kontrollert eksisterende HTML-støtte: import og lokal lagring bevarer tillatte tekst-/lenkeelementer; frontend renderer dem. Backend bruker HTML-tekstbokser, ikke en visuell editor. Dansestil og SEO/schema er ren tekst. [Releasenotat](docs/releases/0.1.9.md).

## 22. september 2026 – Testutgave 0.1.8: stabil status, cache og tekstoppdatering

- Felles no-store-policy for kursflater, kortkoder/blokker, REST (også feil) og pluginens AJAX/admin. Cloudflare-/CDN- og LiteSpeed-headere, cache-hook og forsinket temaoutput for dynamiske Avada-innbygginger. Eksisterende edgecache må tømmes separat.
- Siste kjente status og kapasitet beholdes gjennom kontrollfrist, API-feil og cachetømming, bundet til konto og arrangement. Kontrolltidspunkt og diskret merknad beholdes; reelle salgs-/synlighetsgrenser og manuelle valg gjelder fortsatt.
- Parkategorier samles til én rad i hele par. Nullgrense vises som ∞ etter prosjekteiers avklaring; ukjente verdier skilles fra ubegrenset. Fullt med aktivert venteliste får egen badge.
- Administrator kan redigere beskrivelsestekster på publiserte kurs. Eksisterende godkjenning av LetsReg-tekster virker uten kladd og er tydeligere forklart. Versjonsvern, rettigheter og beskyttelse av lokale tekster beholdes. [Releasenotat](docs/releases/0.1.8.md).

## 22. september 2026 – Testutgave 0.1.7: plassgrense og påmeldte

- Prosjekteier har avklart at plassgrense 0 betyr ubegrenset. På arrangementsnivå beregnes resterende kapasitet fra positiv grense minus påmeldte; lik eller overskredet grense gir fullt. Ubegrensede arrangementer begrenser ikke ellers kjente kategori-/partall.
- Ukjent grense holdes adskilt fra ubegrenset. API-et mangler eget kategori-maksimum; kategori-null blir fortsatt ikke automatisk fullt eller ubegrenset. [Releasenotat](docs/releases/0.1.7.md).

## 22. september 2026 – Testutgave 0.1.6: kompakt oversikt og automatisk statuskontroll

- Mindre eksternt lenkeikon, korte ukedager og visuelt skjult Merker-overskrift i kurstabellen; etiketter/popover beholdes.
- Kategorikapasitet er flyttet ut av kort og kalender. Se kurset gir tilgang til detaljene ved påmelding.
- Kursoversikten starter automatisk en avgrenset kontroll av forfalte koblede kurs, straks og hvert minutt. Delt API-grense/backoff beholdes, også for kursansvarlig. Manuelle valg og publisering endres ikke.
- Påmeldte (`registered`) og rapportert ledig (`available`) vises som separate backendkolonner. Etter skjermbilder med ∞ håndteres null i kategorier uten kjent maksimum som ukjent, ikke fullt; arrangementets null krever positiv maksimalgrense for å bevise fullt. Påmeldttall brukes aldri som ledighet. Faktisk sammenligning på kategori-ID/pooler gjenstår. [Releasenotat](docs/releases/0.1.6.md).

## 22. september 2026 – Testutgave 0.1.5: kart på kursprofilen

- Kartet vises automatisk i egen hovedkolonneboks med navn, adresse og sal. Sidekolonnens adresse lenker til boksen. Reserverute til OpenStreetMap beholdes uten JavaScript; steder uten koordinater viser fortsatt adresse.
- Rettet manglende kartfiler på navnebaserte kursadresser: innlastingen bruker nå rutens kursidentitet, ikke bare `$_GET`. Administrasjonens kartvelger åpnes fortsatt ved klikk.
- Lokal kartvisning med fliser/markør og adresseanker med tastaturfokus er visuelt bekreftet. PHP-lint og relevante kart-, DOM-, offentlig-/HTTP-, admin- og WordPress-tester kjørt. [Releasenotat](docs/releases/0.1.5.md). Ingen produksjonsutrulling.

## 21. september 2026 – Testutgave 0.1.4: kursprofil og kalenderlayout

- Beskrivelsesfeltene vises i egne bokser i hovedkolonnen. Dansestil og nivå vises som piller ved tittelen; prisvilkår flyttes ut av sidekolonnen. Tomme valgfrie felt skjules.
- Kort og kalender har nivåpill rett under tittelen og viser ikke lang nivåforklaring eller prisvilkår. Påmeldingshandlinger og sporing beholdes.
- Gjenskapt tidskolonnefeil på den filtrerte offentlige Avada-siden: etterfølgende avsnittsformatering pakket klokkeslettene inn i ett avsnitt. Blokkelementer bevarer plasseringen; klokkeslett brytes ikke og etikettenes høyde strekker ikke enkeltminutter.
- Bestått: Composer (215 tester / 484 assertions), offentlig visning (96, inkludert gjentatt wpautop), offentlig HTTP (53), DOM, nivåer (37), lokal påmelding (17) og admin/språk (91). Lokalt visuelt kontrollert. Se [releasenotat](docs/releases/0.1.4.md). Ingen produksjonsutrulling.


## 21. september 2026 – Testutgave 0.1.3: cache, rik tekst og lokal påmelding

- Rettet kunstig ettminuttsutløp for hele frontend når LetsReg-status er ukjent. Reelle grenser består. Presisert melding, oppdateringsknapp med unik adresse/bevarte filtre og sporing, filversjonert JavaScript og eksplisitt LiteSpeed/CDN no-cache.
- Bevarer renset rik tekst og lenker fra LetsReg gjennom feltfordeling, lagring, WPML, importkontroll, før/etter og frontend. SEO/schema forblir ren tekst. Lokale redigeringer og beskrivelsesgjenbruk beskyttes.
- Egen påmeldingslenke og Kun drop-in fungerer uten LetsReg-kobling. Pris per kveld, oppmøte uten påmeldingsknapp og statusbytte uten avpublisering er støttet. Egne lenker telles ikke feilaktig som LetsReg-klikk.
- Bestått lokalt: Composer/lint, 215 enhetstester / 484 assertions, DOM, lokal påmelding (17), offentlig (81), offentlig HTTP (53), import (112), kilde/gjenbruk (42), status (80), kapasitet (39), lagring (61), arbeidsflyt (86), HTTP (43), admin/språk (91), permalenker og WordPress-oppstart. Se [releasenotat](docs/releases/0.1.3.md).
- [Cacheobservasjon og driftsoppsett](docs/CACHE-OG-KURSOPPDATERING.md) og [lokal påmelding](docs/LOKAL-PAMELDING-OG-DROPIN.md). Ingen produksjonsutrulling eller ekstern cacheendring. Faktisk live-/visuell kontroll gjenstår.

## 21. september 2026 – Fremtidig kursgenerator til LetsReg

- Utvidet roadmapens F06 til kursgenerator for enkeltkurs og hele perioder, basert på lokale standardbeskrivelser, dansestil, nivå, saler/steder, timeplan og prismaler.
- Beskrevet generering av LetsReg-tekst med felles markører, forhåndsvisning, ekstern opprettelse og lokal kobling til arrangements-/kategori-ID-er og påmeldingslenke. Faser for lokal generator, ett arrangement, hel periode og senere kontrollerte oppdateringer.
- Dokumentert nødvendige avklaringer for skriverettigheter, publisering, feltansvar, duplikatvern og gjenopptakelse ved delvis feil. Kun fremtidig omfang i dokumentasjonen; ingen implementering, API-skriving, funksjonstester eller ny pluginrelease.

## 21. september 2026 – Testutgave 0.1.2: felles beskrivelsesmal

- Release ferdigstilt: oppdatert releasenotat med nedlastingslenker og kontrollsum, ny pakkebygging og kontroll av alle 119 filer mot kildekoden. Innholdet er uendret fra første 0.1.2-bygg; samme versjon og versjonerte ZIP beholdes. Ingen utrulling utført.
- Fem vanlige tekstmarkører fordeler LetsReg-beskrivelsen på lokale beskrivelsesfelt og prisvilkår. Fungerer med eller uten H-tagger/fet skrift. Valgfri «Praktisk informasjon» utelates; eldre fritekst beholdes samlet. Ufullstendige maler gir forklaring og bevarer hele teksten.
- Import/bulkimport fyller redigerbare felt før kontroll. Senere kildeoppdatering viser feltvis forskjell for beskrivelse, dansestil, nivåforklaring og partnerinformasjon. Prisvilkår foreslås i kursoppsettet og overskriver ikke utfylte lokale vilkår automatisk. Lokale redigeringer, gjenbruk og delte beskrivelser beskyttes.
- [Mal med Rueda-eksempel](docs/LETSREG-BESKRIVELSESMAL.md), [e-postutkast til Erik](docs/EPOST-ERIK-BESKRIVELSESMAL.md) og [releasenotat](docs/releases/0.1.2.md). Ingen e-post sendt eller LetsReg-arrangementer endret.
- Bestått lokalt: Composer/PHP-lint, 213 enhetstester / 472 assertions, 42 kilde-/gjenbrukskontroller, import (112), kobling (136), lagring (61), arbeidsflyt (86), HTTP (43), admin/språk (91), kalender (43), offentlig (74), offentlig HTTP (51), WordPress-oppstart og DOM-prøver. POT og pakke oppdatert. Faktisk WYSIWYG/API-roundtrip og visuell staging gjenstår.

## 21. september 2026 – Testutgave 0.1.1: endringer hos LetsReg og gjenbruk av beskrivelser

- Koblede kurs kontrolleres for endringer med arrangements-ID, `lastUpdate` og sammenligning av redaksjonelle felt. Varsel i «Kurs i perioden» og egen sammenligning på kurssiden. Alle koblede kurs omfattes av eksisterende begrensede bakgrunnskontroll, også ved manuell påmeldingsstatus og avslått arrangementshistorikk.
- Brukeren kan beholde lokal versjon, godkjenne oppdatering av eksisterende beskrivelse eller opprette en separat beskrivelse. Delte beskrivelser og konsekvensen for publiserte kurs vises før lagring. Lokale redigeringer beskyttes; tid, pris og kursoppsett endres ikke automatisk.
- Import gjenbruker identiske beskrivelser innen samme arrangør. Mindre endringer foreslår oppdatering med samme ID; større avvik krever eksplisitt valg. Kontrollgrunnlag, ressursversjon, berørte kurs og rettigheter kontrolleres igjen ved bekreftelse, også i bulkimport.
- Bestått: 35 nye endrings-/gjenbrukskontroller, import (112), kobling (136), autentisering (160), kapasitet (39), status (79), historikk (31), lagring (61), arbeidsflyt (86), HTTP (43), admin/språk (91), admin-HTTP (56), offentlig visning (74), offentlig HTTP (51), kalender (43), DOM-prøver, WordPress-oppstart og Composer (208 tester / 445 assertions). POT er oppdatert. API-prøvene bruker syntetiske svar; faktisk endringskontroll og visuell gjennomgang i staging gjenstår.
- [Brukerveiledning](docs/LETSREG-ENDRINGER-OG-BESKRIVELSER.md) og [releasenotat for 0.1.1](docs/releases/0.1.1.md). Ny versjonert ZIP med kontrollsum; 0.1.0 beholdes uendret.

## 21. september 2026 – Første testutgave 0.1.0

- Samlet [releasenotat for 0.1.0](docs/releases/0.1.0.md) med funksjonsomfang, miljøkrav, installasjon, avgrensninger, lokalt testgrunnlag og tilbakeføring.
- Bygget versjonert installasjonspakke og SHA-256-kontrollsum for staging/kontrollert produksjonstest. Pluginversjonen er fortsatt 0.1.0. Ingen publisering, utrulling eller nye runtime-endringer inngår.

## 21. september 2026 – Fremtidige muligheter for deltakerdrift

- Lagt F01–F09 til roadmapen som mulige utvidelser: deltakerlister, instruktør-/innsjekkroller, oppmøte per kurskveld, rapporter/rollebalanse, ventelisteoversikt, overføring av arrangementer/priser, økonomisk avstemming, avviksvarsler og QR/offline.
- Dokumentert foreslått rekkefølge, avgrenset tilgang og skillet mellom LetsRegs påmeldinger og RegiNors eventuelle lokale oppmøte. Offentlig API-støtte er skilt fra uavklart innsjekksynkronisering og nødvendige kontoprøver.
- Kandidatene er ikke implementeringsbestillinger eller nye krav før staging/innføring. Kun dokumentasjon endret; ingen funksjonstester kjørt eller integrasjoner aktivert.

## 21. september 2026 – Klikk, arrangementstotaler og mulige tidssammenfall

- Ny valgfri rapport under Statistikk: daglige samtykkede klikk/kampanjer sammen med rapportert deltakerantall og ordresum fra LetsReg. Offentlig OpenAPI 2.2.8 bekrefter `registeredParticipants` og `ordersTotalSum`; valuta, betaling og refusjonssemantikk er fortsatt uavklart. Ordresum vises uten valutasymbol og kalles ikke bekreftet omsetning.
- Privat 30-dagers historikk gjenbruker eksisterende autentiserte kontroller, også for koblede kurs med manuelt overstyrt status. Første måling er utgangspunkt; etterfølgende målinger viser endringer. Ingen ordre-/deltakerregistre eller nye API-endepunkter brukes.
- Utvidbar eksperimentell vurdering med 5/15/30/60 minutters vindu, ukjent/tvetydig kilde, målte kampanjekilder, datagap og delte arrangementer. Ingen sannsynlighetsprosent, automatisk salgstilordning eller `purchase` til GA4/Ads. Tilbaketrukket samtykke fjerner klikk fra nye vurderinger; antatte konverteringer lagres ikke.
- Administratorstyrt aktivering og oppdatering, tilgangs-/noncekontroll, konto-/kursbinding, varsel om kildefeil og begrensede uttrekk. [Bruk og avgrensninger](docs/KLIKK-OG-LETSREG-UTVIKLING.md).
- Bestått lokalt: 31 historikkontroller, 27 statistikkontroller, 34 samtykkekontroller, 39 kapasitetskontroller, 160 autentiseringskontroller, 79 statuskontroller, 61 lagringskontroller, 91 admin-/språkkontroller, 55 admin-HTTP-kontroller, WordPress-oppstart og Composer (208 tester / 445 assertions). Syntetiske API-svar; ingen ekte salg, deltakerregistre eller live historikk hentet/aktivert. Faktisk økonomisk semantikk, cron/kvoter og visuell staging gjenstår.

## 21. september 2026 – LetsReg-svar om kampanjemåling

- Dokumentert supportsvaret prosjekteier delte, sak #134895094: etterspurt arrangørspesifikk sporing tilbys ikke i dag; teknisk team arbeider med en løsning uten oppgitt dato.
- M5bs kobling mellom besøk og kjøp settes på vent. M5a frem til LetsReg-klikk videreføres; mulig ordre-/påmeldingsavstemming per kurs holdes adskilt fra kampanjeattribusjon.
- Roadmap, kontraktnotat og måledokumentasjon oppdatert. Bare dokumentasjon endret; ingen runtime-tester, API-kall, sporingsaktivering eller ny supporthenvendelse utført.

## 21. september 2026 – Standard kursoversikt på /kursrekke/

- `/kursrekke/` viser samme standardinnhold som `[reginor_courses]` i kurssidens temamal. Filtre og «Vis alle kurs» bruker denne grunnadressen når lesbare adresser er aktivert. Periode- og kursadresser videreføres.
- Ruteregler oppgraderes automatisk. Eksisterende WordPress-seksjoner beskyttes, canonical er uten sporingsparametre, og synlighets-/cachekontroll gjelder også rotoversikten.
- Bestått: 62 HTTP-kontroller for permalenker, inkludert likt kortkodeinnhold, filtre, canonical, sporingsparametre og utløpt innhold; 55 WordPress-kontroller for permalenker, 74 offentlige kontroller, 51 offentlige HTTP-kontroller, DOM-prøvene, WordPress-oppstart og Composer 204 tester / 424 assertions. Pluginpakken er oppdatert.

## 21. september 2026 – Kursimport for kursansvarlig

- Kursansvarlig får enkeltimport og bulkimport av opptil 20 kurs med `rnl_import_letsreg` i rolleversjon 3. Eksisterende roller oppgraderes automatisk. Importknapp og importside bruker samme rettighet som AJAX og lagringslaget.
- Nye beskrivelser opprettes som del av den kontrollerte importen. Eksisterende beskrivelser, saler og steder velges gjennom begrenset oppslag, med ressurskontroll før lagring. Ingen generell redigeringstilgang til felles ressurser, API-innstillinger eller ordre-/deltakerdata er lagt til.
- Importgrunnlag og bekreftelse er fortsatt bundet til bruker, konto, periode og versjoner. Kursene opprettes i kladd; feil i bulkimport ruller tilbake hele importen.
- Bestått lokalt: 112 importkontroller, 136 koblingskontroller, 86 arbeidsflytkontroller, 43 HTTP-kontroller, 91 adminkontroller, 61 lagringskontroller, 160 autentiseringskontroller, WordPress-oppstart, DOM-prøvene og Composer (204 tester / 424 assertions). Oversettelsesgrunnlag og installasjonspakke er oppdatert. Leverandørsvar er syntetiske; import mot ekte LetsReg er ikke prøvd i denne endringen.

## 20. september 2026 – LetsReg-søk og kurskobling for kursansvarlig

- Administrator beholder API-oppsett, arrangørvalg, driftsside og import. Kursansvarlig får `rnl_link_letsreg` i rolleversjon 2 og kan søke, hente priskategorier og lagre/fjerne koblingen direkte på et redigerbart kurs, også når det er publisert.
- Separate tillatelser for kursoppslag og administratorens tilkoblingskontroll. Eksisterende konto-/eierkontroll og felles ventetid beholdes; nonce og objekttilgang kontrolleres før nettverkskall. Siste kontrollerte arrangement kan bare brukes av brukeren som hentet det. Importens kontroll- og lagringslag beholder egen administratorgrense.
- Kurseditoren laster søkefunksjonen for kursansvarlig. Uten JavaScript forklares behovet direkte; brukeren sendes ikke til administratorens driftsside. Spesifikasjon og brukerveiledning er oppdatert.
- Bestått: 138 koblings-/rettighetskontroller, 160 autentiseringskontroller, 88 importkontroller, 86 arbeidsflytkontroller, 42 HTTP-kontroller, DOM-prøvene og Composer (204 tester / 424 assertions). API-prøvene bruker syntetiske svar; ingen reelle leverandøroppslag eller nye brukertildelinger utført.
- Bestått: 52 meny-/tilgangskontroller over HTTP, WordPress-oppstart, lagring 61, admin 91 og kapasitet 39. Oversettelsesgrunnlaget er oppdatert. Faktisk stagingprøve med kursansvarlig og LetsReg gjenstår.

## 20. september 2026 – M5b: vurdert brukerlevert webhook-eksempel

- Dokumentert callback-konvolutt og mulige ID-er for kurskobling, ordreavstemming og gjentatt behandling uten dobbelttelling.
- Presisert at betalingsdato, ulik linje-/kategoripris og tom ekstern referanse ikke avklarer bekreftet omsetning eller kampanjeattribusjon. HTTP-autentisering er fortsatt ukjent.
- Kun kontraktnotat og roadmap endret; ingen råpayload/personopplysninger lagret, ingen mottaker aktivert og ingen runtime-tester kjørt.

## 20. september 2026 – M5b: offentlig dokumentasjon undersøkt videre

- Funnet offisiell API-hjelpeartikkel som bekrefter legacy-token med separat affiliate og beskriver webhook-omfang. Dokumentert mulige refusjonskilder, offline-/delrefusjonsbegrensninger og arrangørpiksler.
- Skilt ordreavstemming fra besøksattribusjon i videre plan. Referanseoverføring, betalingssemantikk og callback-sikkerhet er fortsatt åpne; ingen konverteringssporing aktivert.
- [Kilder, funn og begrensninger](docs/M5B-OFFENTLIG-RESEARCH.md). Kun dokumentasjon endret; ingen runtime-tester eller autentiserte leverandørkall utført i denne researchen.

## 20. september 2026 – M4.2a/b og M4.3 implementert lokalt

- Kursprofilen har «Legg til i kalender» med nedlasting av alle faktiske kurskvelder og abonnementslenke, samt korte veiledninger for Apple Kalender, Google Kalender og Outlook. Kalenderabonnement er uavhengig av TECs oppføring for hele perioden.
- Stabil kalender-/øktidentitet, UTC over sommertid, faktiske pauser/forskyvninger, avlysninger og bevarte avlyste oppføringer etter fjerning. Publisering og direkte statusendring lagrer kalenderhistorikk i samme transaksjon; testet tilbakeføring ved feil. Slugendring bryter ikke abonnementet, og kopiering arver ingen kalenderidentitet.
- Anonymt kalenderendepunkt med samme offentlighetspolicy, no-store/noindex, GET/HEAD og 404 for skjulte/utløpte mål. Ingen LetsReg-kall eller måling ved feed-henting.
- «Del kurset» med enhetens delingsmeny, kopiering, Facebook og WhatsApp. Rene kursadresser, eksisterende Open Graph, lesbare reservefelt, I18n og ingen tredjepartsressurser før handling. Ingen nye analysehendelser; eksisterende LetsReg-sporing beholdes.
- Bestått: kalender WordPress 43, kalender HTTP/uavhengig ICS-parser 26, tre nye DOM-atferdstester, alle eksisterende DOM-prøver og Composer 204 tester / 424 assertions. WordPress-oppstart, lagring 61, admin 91, arbeidsflyt 86, kurs-HTTP 40, offentlig visning 74, offentlig HTTP 51, permalenker 50/47, automatisk status 79, TEC 35/15 og samtykke 34/27 besto. POT og installasjonspakke oppdatert.
- Faktiske kalenderklienter, SoMe-dialoger/forhåndsvisning, Avada/WPML og CDN/visuell tilgjengelighet gjenstår. Nettleserverktøyet feilet ved oppstart. [Implementasjon, drift og gjenstående porter](docs/KALENDER-OG-KURSDELING.md).

## 20. september 2026 – roadmapvurdering og kalender-/delingsplan

- Samlet vurdering av gjenstående arbeid: faktisk miljø/brukerprøve for M0–M4.1, produksjonsavklaringer for M5 og kontrakthull for M5b. Rettet utdaterte statuslinjer om offentlig kategorikapasitet og faktisk lesetest.
- Formalisert M4.2a for nedlasting av faktiske kurskvelder som ICS, og M4.2b for kalenderabonnement med stabil identitet, flytting, avlysning, synlighetsgrenser og klientprøver.
- Formalisert M4.3 med enhetens deling, kopiering, Facebook og WhatsApp per kursprofil. Ren kursadresse, eksisterende Open Graph, tilgjengelige reservevalg og samtykkestyrt måling inngår.
- Dokumentasjonsleveranse med akseptansekriterier og anbefalt rekkefølge; ingen kalenderendepunkter, delingsknapper eller nye målehendelser implementert. Ingen runtime-tester kjørt for denne dokumentasjonsendringen.

## 20. september 2026 – navnebaserte adresser for eksisterende kurs

- Rettet at eksisterende RegiNor-perioder og kurs fikk reserveadresser som `kurs-628/kurs-629` frem til en administrator åpnet backend. Engangsinitialiseringen kjører nå før offentlig visning og lager slugs fra navnene.
- Tidligere ID-adresser beholdes som aliaser med direkte 301 til navneadressene. Egendefinerte slugs, delingsvalg og kursinnhold beholdes; oppgraderingen kan gjentas trygt.
- Bestått: 50 permalinkkontroller, 47 permalink-HTTP, lagring 61, offentlig visning 74, offentlig HTTP 51, WordPress-oppstart, alle DOM-prøver og Composer (202 tester / 396 assertions).
- Brukerens konkrete lenke kontrollert anonymt: 301 til `/kursrekke/demo-aktiv-kursperiode/demo-salsa-nybegynner/`, som svarer 200 med riktig kurs.

## 20. september 2026 – M4.1 og gjennomgang av M5b

- Nye periode-/kursadresser under `/kursrekke/`, stabile slugs, ettledds 301 fra gamle RegiNor-lenker og bevarte kampanjeparametre. Skjulte mål gir 404.
- Valgfri «Adresse og deling» på kurs og periode: språkvariant, tittel, beskrivelse og mediebilde. Privat, versjonskontrollert lagring uten å ta publiserte kurs tilbake til kladd.
- Serverrenderte SEO-/Open Graph-/sosiale metadata, rene canonical-adresser, filtre med noindex og sitemap med samme synlighetskontroll. Separate bilder for kurs, periode og nettsted.
- Faktiske kursøkter får stabile Event-ID-er, PostalAddress og lagrede GeoCoordinates, også for annet øktsted. Ingen ubekreftet InStock.
- WPML- og SEO-integrasjonspunkter; faktisk Avada/WPML/Yoast/Rank Math-staging gjenstår. Nye lokale WordPress-, HTTP- og DOM-prøver.
- M4.1-prøver bestått: 42 WordPress- og 47 HTTP-kontroller, inkludert lagring på publisert kurs, nonce og versjonskonflikt. Eksisterende lagrings-, admin-, publiserings-, frontend-, TEC- og samtykkeprøver bestått. POT og ZIP (105 filer) oppdatert.
- M5b gjennomgått mot offentlig OpenAPI 2.2.8. Ordrefelt finnes; overleveringsreferanse, callback-autentisering og konverteringssemantikk må fortsatt verifiseres. Ingen ordre-/deltakerdata hentet eller webhook opprettet.

## M5: ledige plasser per kategori – 20. september 2026

- Kurskort, kalender og detalj viser kategorier med rolle/påmeldingsform, «Opptil X plasser» og kontrolltid. Ingen summering av kategorier. Par vises per deltaker og begrenses av kjent partner-/arrangementsgrunnlag.
- Kapasitet-siden viser nå LetsReg-data for koblede kurs, med rapporterte tall og begrenset vurdering. Samme tabell er tilgjengelig i kursoppsettet; kursoversikten har direkte lenke. Ukoblede kurs har fortsatt separat merket demonstrasjon.
- Manglende/ugyldige tall blir ukjent, null blir fullt. Utløp, API-feil, stengt/avlyst og manuell overstyring skjuler offentlig antall. Åpne backendpaneler har nå også utløpskontroll i kurseditoren.
- Bakgrunnskøen gjenbruker ett token i minnet i en avgrenset runde med inntil tre arrangementer. Dette fjerner gjentatte tokenkall som under faktisk kontroll ble avvist med `401 invalid_grant`. Konto/utløp valideres, og token kastes ved feil eller rundens slutt.
- Avvik mellom kategorinavnets forslag og lagret påmeldingsform vises for kontroll uten automatisk endring.
- Bestått: Composer 202 tester / 396 assertions, automatisk status/kategorier 79, kapasitet 39, LetsReg-autentisering 160, offentlig visning 74, admin 91, offentlig HTTP 51 og alle ti DOM-skript. WordPress-oppstart, POT og installasjonspakke kontrollert. Nettleserverktøyet feilet ved oppstart; visuell prøve gjenstår.
- Faktisk avgrenset kontroll etter tokenrettingen hentet begge lokale arrangementer med ett tokenkall. Kategoritall og offentlig HTML ble kontrollert uten endring av påmeldinger, kurskoblinger eller publisering.
- [Regler og testavgrensning](docs/M5-KATEGORIKAPASITET.md). Eksakte salgbare pooltall og webhooks er fortsatt åpne i M5.

## Automatisk status følger LetsRegs salgsvindu – 20. september 2026

- Rettet at periodens salgsdato kunne gi «Åpner senere» på automatisk kurs, selv uten gyldig API-kontroll. Automatisk modus bruker nå LetsRegs datoer; egne datoer på enkeltkurset kan begrense dem. Manuell modus beholder periodens datogrenser.
- Kursdetaljen bruker samme effektive API-dato som statusberegningen, også uten sluttdato. Manglende API-data blir «Må avklares».
- Backend viser konkret innloggings-/rettighetsfeil i stedet for bare «Venter på kontroll». Lesende kontroll av lokalmiljøet viste en lagret innloggingsfeil for dagens API-oppsett og utløpte observasjoner på de to rapporterte kursene. En ny avgrenset tilgangskontroll lyktes, gjenopptok køen, og henting av begge arrangementene ga `available` både fra API-projeksjonen og lokal statusberegning. Ingen kursfelt eller publisering ble endret.
- Regresjonsprøver dekker fremtidig/utløpt/manglende lokalt periodevindu, egne kursgrenser, avlyst/skjult, ukjent API og korrekt åpningsdato i frontend. Bestått: Composer (197 tester / 356 assertions), automatisk status (62), offentlig visning (74), admin (91), arbeidsflyt (86), kapasitet (39), kurs-HTTP (40), offentlig HTTP (51) og alle ti DOM-skript. POT og ZIP er oppdatert.

## Påmeldingsstatus direkte i kursoversikten – 20. september 2026

- Kompakt statusmeny per kurs i «Kurs i perioden», med AJAX-lagring og statusoppdatering hvert minutt. Publisering, periode og timeplan beholdes. Rettigheter, nonce, versjonskonflikter og historikk håndheves; vanlig lagreknapp finnes uten JavaScript.
- Automatisk fra LetsReg følger arrangementets/kategorienes salgsdatoer og kapasitetsstatus. Manuelle valg overstyrer API-vurderingen og beholdes til automatisk velges igjen. Lokale salgsgrenser, avlyst periode, avsluttet kurs og synlighet håndheves fortsatt.
- Avgrenset WP-Cron-polling, kontolås, konto-/credentialbinding, tidsstemplet observasjon, femten minutters utløp, Retry-After og stopp ved tilgangsfeil. Nytt token i minnet per jobb; ingen deltakerdata eller offentlige eksakte kapasitetstall. Ingen kategorisummering eller garanti for delt parkapasitet.
- Nye LetsReg-forslag velger automatisk status uten å forhåndsvelge kopiering av API-datoer til permanente lokale salgsgrenser. Eksisterende manuelle valg og eksisterende kurs beholdes.
- Eksternt lenkeikon ved kursnavnet åpner lagret LetsReg-side i ny fane for kontroll, også når påmeldingen ikke er åpen. Tilgjengelig etikett og forklaring ved fokus/peker.
- Bestått: Composer (197 tester / 356 assertions), statuslagring/polling (53), LetsReg-autentisering (160), kobling (120), import (88), alle ti DOM-skript, offentlig visning (74), offentlig HTTP (51), kapasitetsregresjoner (39), admin/språk (91), lagring (61), arbeidsflyt (86), kurs-HTTP (40) og WordPress-oppstart. Oversettelsesmal og installasjonspakke oppdatert.
- Prøvene bruker syntetiske API-svar. Faktisk API-kapasitet, ventelisteflyt, kvoter, servercron og visuell Avada-prøve gjenstår. [Bruk og driftsgrenser](docs/PAMELDINGSSTATUS.md).

## Direkte påmelding fra kurskort og kalender – 20. september 2026

- Kurskort har separate «Se kurset» og «Meld meg på»-knapper; kalenderen bruker tekstlenker. LetsReg-lenker åpnes i ny fane, også fra detaljen, med tilgjengelig forklaring og `noopener`. Knapper brytes over flere linjer ved lite plass.
- Samme statusgrenser gjelder overalt. Venteliste merkes tydelig, og lenker til hele perioden forklares før overgangen.
- Eksisterende LetsReg-adresse med query-parametere/fragment beholdes. Felles kurs-ID og `rnl_letsreg_click` beholdes med Complianz-samtykke, kampanjedata og GTM-hendelse. Midtklikk registreres; navigasjonen overtas ikke.
- Direkte påmelding registrerer ikke en kursdetaljvisning eller et bekreftet salg. Faktisk GTM/GA4-/LetsReg-samspill må fortsatt kontrolleres i staging.

- Bestått: Composer (172 tester / 301 assertions), alle ni DOM-skript, besøksmåling (34), offentlig visning (74), offentlig HTTP (51), statistikk med Complianz-stub (27), admin/språk (91) og WordPress-oppstart. Oversettelsesmal og pluginpakke oppdatert. Visuell Avada-prøve og faktisk GTM/GA4-kontroll gjenstår.

## Nivåforslag fra LetsReg – 20. september 2026

- Arrangementsnavnet foreslår nivå fra det aktive lokale nivåutvalget, både ved lokal kobling og enkelt-/bulkimport. Støtter norske tegn, store/små bokstaver og «Øvet1» / «Øvet nivå 1».
- Hele navn og nivånummer sammenlignes; tvetydige treff og inaktive nivåer gir manuelt valg med forklaring. Ingen nivåer opprettes automatisk.
- Forslaget er redigerbart og lagres gjennom vanlig kursflyt. Eksisterende nivåvalg krever eksplisitt erstatning. Bulkimport nullstiller mellom arrangementer og bevarer manuelle valg ved redigering av importlisten.

- Bestått lokalt: Composer (172 tester / 301 assertions), alle ni DOM-skript, LetsReg-kobling (120), import (88), arbeidsflyt (86), admin-HTTP (40), admin/språk (91) og WordPress-oppstart. Prøvene bruker syntetiske API-svar og rydder egne testobjekter. POT og pluginpakke er oppdatert. Faktisk visuell Avada/WPML-prøve gjenstår.

## Kursnivåer erstatter målgruppefilteret – 20. september 2026

- «Kursinnhold og ressurser» erstatter menyen «Beskrivelser og steder». Administrator kan opprette/redigere nivånavn, valgfri forklaring, sortering og tilgjengelighet for nye valg.
- Enkeltkurs har eget nivåvalg under Undervisning, uavhengig av delt kursbeskrivelse. Valget inngår i vanlig forhåndsvisning/lagring og LetsReg-import. Kursansvarlig kan velge nivå, men ikke endre det felles registeret. Eksisterende kurs og periodekopier beholder også deaktiverte nivåvalg.
- «Hvem passer kurset for?» og frontendens nybegynner-/erfaringssnarveier er fjernet. Ett nivåfilter med brukerdefinerte nivåer virker i liste/kalender, sammen med dag/periode og med returvalg bevart. Kurs uten nivå vises under Alle nivåer; ingen automatisk gjetting eller omklassifisering utføres.
- Offentlig nivånavn/forklaring er WPML-klargjort, og schema bruker samme educationalLevel. Ubrukte nivåer og nivåer som bare brukes av skjulte kurs vises ikke i filteret. Historiske audience-verdier beholdes i lagring, men brukes ikke av filter/UI.
- Privat `rnl_level` med versjonert lagring, referansekontroll, oppgraderte administratorrettigheter og ingen rå offentlig REST-rute. Egen nivåregresjon er lagt til i CI. Se [bruk og avgrensning](docs/KURSNIVAER.md).
- Bestått: nivåer (37), lagring (61), arbeidsflyt (86), admin (91), admin-HTTP (40), offentlig (50), offentlig HTTP (47), menyer (52), import (86), WordPress-oppstart, alle ni DOM-skript og Composer (156 tester / 282 assertions). POT oppdatert; faktisk WPML/Avada-prøve gjenstår.

## Gjennomgang og rettinger M0–M4 – 20. september 2026

- Sammenholdt roadmap, kode og automatiske kontroller. Ny rapport samler testbevis, lokalt miljø, kobling til akseptansekriterier og åtte konkrete gjenstående kontroller med ansvar og krav til bevis. Utdaterte påstander om uprøvde offentlige kanaler og fravær av AJAX er korrigert. M4.1 står fortsatt som egen planlagt leveranse.
- Påmeldingsåpning på kursdetaljen bruker nå samme effektive tidsvindu som salgsstatus. Passerte eller ugyldige åpninger annonseres ikke som kommende datoer.
- Nøkkelfakta og hovedhandling står før lang beskrivelse i HTML-/tastaturrekkefølgen. Faktisk første kursdato er lagt til i faktaboksen; desktop beholder to kolonner.
- Andre enkeltsider med kursoversikt får tidlig no-store, også ved nestede synkroniserte blokker. Oppdagelsen har vern mot referansesirkler og for dype kjeder. Eksternt cachelag og innbygging fra temamaler må fortsatt kontrolleres i staging.
- Lesende kartlegging inkluderer nå database/miljø, registreringsstruktur, shortcode/blokk, valgt kursside og instruktørtyper. Ingen kursinnhold eller hemmeligheter eksporteres.
- Bestått: Composer (156 tester / 282 assertions), alle ni DOM-skript, lagring (58), arbeidsflyt (86), admin (91), adminmeny (52), admin-HTTP (40), offentlig (50), offentlig HTTP (41), utseende (46), kart (242), TEC (35), TEC-HTTP (15) og WordPress-oppstart. POT oppdatert. Visuell nettlesertilkobling feilet; staging og menneskelige kontroller er fortsatt åpne.

## Planlagt: permalenker og metadata – 20. september 2026

- Formalisert M4.1 med `/kursrekke/rekkenavn/kursslug`, periodeoversikt, Open Graph, SEO-metadata, Event-schema og lokasjonsmetadata. Kontrakten beskriver redirects, synlighet, WPML og samspill med Avada/TEC/SEO-plugin. Dette er dokumentasjon av planlagt arbeid; frontend er ikke endret.

## Finn og rett mangler ved publisering – 20. september 2026

- Publiseringskontrollen for hele kursperioden skiller nå mellom manglende kursbeskrivelse, nivå/forkunnskaper og uavklart påmeldingsstatus. Hver melding forklarer feltet og lenker til riktig sted i det aktuelle kursoppsettet.
- Kursbeskrivelse og nivå/forkunnskaper vises direkte på kursets side. Administrator kan lagre tekstene separat; delt bruk på flere kurs forklares. Kursansvarlig kan lese tekstene og får beskjed dersom administrator må fylle dem ut.
- Lagring beskytter nonce, rettigheter, koblet beskrivelses-ID og versjoner. Andre kursopplysninger og økter endres ikke. Arbeidsflytprøven dekker lenker, delte beskrivelser, rettigheter og versjonskonflikter.
- Bestått: `composer check` (156 tester / 282 assertions), DOM-prøvene, admin (91, inkludert bevaring av tekst ved feil), arbeidsflyt (86), lagring (58), HTTP (40) og WordPress-oppstart. Visuell nettleserprøve gjenstår.

## Forslag ved delvis utfylt kategorikobling – 20. september 2026

- Rettet at ett tidligere kategorivalg skjulte navneforslagene for hele listen. Alle aktive kategorier får nå synlig forklaring og forslag der navnet er entydig, også når koblingen allerede er delvis utfylt.
- «Fyll ut forslag for kategorier uten rolle» fyller resterende kategorier i én handling. Kategorier med valgt rolle og påmeldingsform beholdes. Nye koblinger forhåndsutfylles fortsatt automatisk; tidligere utelatte kategorier tas med etter aktivt valg.
- Egen regresjonsprøve gjenskaper én utfylt fører-/parkategori og tre tomme kategorier. Prøvene dekker alle fire sluttverdier, manuelle endringer, tvetydige/inaktive kategorier, AJAX-innlasting og gjenoppretting fra importlisten.
- Bestått: `composer check` (156 tester / 282 assertions), alle DOM-prøver, kobling (118), import (86), admin (90), arbeidsflyt (73), HTTP (40) og WordPress-oppstart. Oversettelser og pluginpakke oppdatert. Prøvene brukte syntetiske data; brukerens lagrede koblinger ble ikke endret.

## Forslag til LetsReg-priskategorier – 20. september 2026

- Rolle og påmeldingsform forhåndsutfylles fra tydelige kategorinavn ved første arrangementsvalg, i både lokal kobling og import: fører/følger og enkelt/par. Forslaget forklares ved feltene og kan endres før vanlig lagring.
- Tvetydige navn og navn uten rolle beholdes til manuelt valg. Inaktive kategorier foreslås ikke. Tidligere lagrede valg og kategorier som er valgt bort, erstattes ikke; manuelle valg beholdes ved ny henting av samme åpne arrangement.
- Parpåmelding er fortsatt pris per deltaker. Forslagene endrer ikke prisbeløp eller fastsetter kapasitet.
- Enhetsprøver dekker de fire oppgitte eksemplene, store/små bokstaver, ordgrenser og tvetydighet. WordPress- og DOM-prøver dekker forhåndsutfylling, forklaring og bevaring av valg.
- Bestått: `composer check` (156 tester / 282 assertions), `test:interface`, kobling (115), import (86), admin (90), arbeidsflyt (73), HTTP (40) og WordPress-oppstart. Oversettelseskatalog og pluginpakke er oppdatert. Ingen faktiske LetsReg-arrangementer ble endret; prøvene bruker syntetiske svar.

## Import av kurs som allerede har startet – 20. september 2026

- Rettet at ny LetsReg-import brukte omplanleggingsregler og avviste tidligere datoer. Importen oppretter nå hele kursrekken fra første kursdato, med kursfrie dager og periodegrenser beholdt. Antall undervisningskvelder betyr totalen, ikke gjenstående kvelder fra dagens dato.
- Forhåndsvisningen oppgir totalt antall, antall med passert starttidspunkt og antall som starter senere, med kontrolltidspunkt. Tidligere datoer bekrefter ikke oppmøte eller faktisk gjennomføring. Eksisterende kursøkter endres ikke av importen.
- Synlighetsinformasjon viser berørte datoer og tidsrommet kursoversikten vises. Den merkes uttrykkelig som ikke-blokkerende, med navn på feltene som eventuelt kan endres.
- Omplanlegging av eksisterende kurs beholder vernet av tidligere økter. Avviste datoer samles i én konkret forklaring uten en ekstra misvisende antallsfeil. Reelle avvik viser ønsket og faktisk antall kvelder som ikke er avlyst.
- Utvidet importtest med fast klokke: allerede startet og avsluttet kursrekke, lagring etter at enda en kveld har startet, bevaring av tidligere økter og redigering av kommende kvelder. 86 importkontroller bestått med syntetiske data.

## Konkrete valideringsfeil og mer luft i backend – 20. september 2026

- Felles feiloversikt navngir feltene og lar brukeren gå direkte til dem. Datofeil viser valgt dato og tillatt grense; løste feil fjernes løpende. Utfylte verdier beholdes.
- Bulkimport forklarer manglende sal, dansestil og priskategorier. Meldingen om et åpent kurs skiller nå mellom feltfeil, manglende oppdatert forhåndsvisning og et kurs som er klart til å legges i importlisten.
- Feil fra serverens forhåndsvisning beholdes ved forsøk på å gå videre. For mange kurskvelder gir ønsket antall, antall som får plass og sluttdato, med forslag til retting. Kursfri første dato og feil klokkeslett forklares konkret.
- Økt avstand mellom paneler, feltkort og knapper. Importvalg og forslag fra LetsReg har rammer og innvendig luft. Endringene ligger i administrasjonens stilark.
- Bestått: `composer check` (140 tester / 261 assertions), `test:interface`, `test:letsreg-import` (68), `test:letsreg-mapping` (106), `test:workflow` (73), `test:admin` (90), `test:http` (40) og `test:wordpress`. Visuell nettleserkontroll gjenstår.

## Backend: felles UI og stil – 19. september 2026

- Rettet manglende sekundærknappklasse i LetsReg-flytene og standardisert bilde-/fargeknapper. Samlet bulkopprettelse har eksplisitt primærstil.
- Prosjektstatus bruker samme visuelle ramme og stilark som øvrige backendsider. Eget `admin.css` samler roligere overskriftshierarki, tekststørrelser, panelavstand, feltkort og tabellutforming.
- Alpha-slider er skilt fra tekstfeltstilen. LetsRegs priskategoritabell har navngitt rulleområde, og beskrivelses-/bildevisning bruker felles klasser.
- HTTP-menyprøven kontrollerer stilinnlasting på alle åtte administrasjonssider. Se `docs/BACKEND-STILGJENNOMGANG.md` for funn og gjenstående visuell kontroll.

## LetsReg-beskrivelser og bulkimport – 19. september 2026

- Velg «Opprett ny fra LetsReg» for en redigerbar kopi av navn og beskrivelse, eller gjenbruk en lokal beskrivelse. HTML blir ren tekst med avsnitt/linjeskift; eksisterende beskrivelser overskrives ikke. Dansestil og øvrig beskrivelsesinformasjon kontrolleres lokalt.
- Velg flere søkeresultater, gjennomgå hvert kurs og legg det i importlisten. Opptil 20 kursutkast med eventuelle nye beskrivelser opprettes samlet i én transaksjon. Feil i ett kurs ruller tilbake hele listen.
- Signert importgrunnlag per arrangement holder flere valg gyldige samtidig, med 15 minutters utløp og binding til bruker/kontooppsett. Redigering fra listen henter nytt grunnlag og beholder lokale felt. Vanlig kurskobling beholder sine tidligere kildekontroller.
- Utvidede WordPress- og DOM-prøver dekker beskrivelser, flervalg, redigering, duplikater, kildekontroll og tilbakeføring ved feil i andre kurs. Prøvene bruker syntetiske svar; faktisk import og visuell brukertest gjenstår.

## LetsReg-import av nye kursutkast – 19. september 2026

- Administrator kan velge «Hent nytt kurs fra LetsReg» i en kursperiode i kladd. Ett arrangement med valgte priskategorier blir ett nytt kursutkast; eksisterende kurs og ACF-data endres ikke.
- Importen gjenbruker søk, periodetoggle og kursfelter. Navn, datoer, ukedag, klokkeslett og påmeldingslenke foreslås fra kontrollert API-data. Kursbeskrivelse og sal velges manuelt; fra-pris kan fylles fra valgte kategorier, per person også ved parpåmelding.
- Forhåndsvisningen viser faktiske kurskvelder, kursfrie dager og eventuelle kollisjoner før bekreftelse. Kurs, økter og privat kobling lagres samlet som kladd. Vanlig periodekontroll gjelder før publisering.
- Duplikatvern omfatter overlappende kategorier i samme periode, også kurs i papirkurven. Endret periode, sal, kursbeskrivelse, kildekontroll eller API-oppsett avviser gammel forhåndsvisning. Feil bevarer utfylte felt.
- Nye WordPress- og DOM-tester dekker import, tilgang, kildeutløp, samtidige endringer, duplikater og tilbakeføring ved lagringsfeil. Testene bruker syntetiske svar. Visuell brukertest og import med faktisk LetsReg-arrangement gjenstår; live kapasitet, webhook og tokenfornyelse er fortsatt ikke aktivert.

## Kompakt LetsReg-søk – 19. september 2026

- Treff vises i en vanlig nedtrekksliste som lukkes ved valg, med navn, oppstartsdato og arrangements-ID. Native tastatur- og skjermleserfunksjoner beholdes.
- Én bryter, «Kun treff i valgt kursperiode», er på som standard. Den sammenligner oppstartsdato med periodens start/slutt, inkludert grensedagene, i periodens tidssone. Manglende dato vises tydelig når filteret er av.
- Filtrering bruker hentede treff uten ekstra API-kall. «Hent flere treff» legger neste side til samme nedtrekksliste. Tom filtrert side skjuler ikke muligheten til å hente flere. Feilet arrangementsvalg kan prøves på nytt.

## Kursoppsett og LetsReg-forslag – 19. september 2026

- Fast rekkefølge: Undervisning → Pris og påmelding → Påmelding hos LetsReg → Kursfrie dager og flere valg → Utseende → øvrige handlinger. Fremheving og drop-in/pris ligger under Undervisning; instruktør er fortsatt valgfritt under flere valg.
- Én valgfri første kursdato. Tomt felt følger kursperiodens start og valgt ukedag. Den tidligere ekstra startdatokontrollen er fjernet fra kurseditoren. Eksisterende særskilt start bevares som synlig avvik når den kan identifiseres.
- Ved valg av LetsReg-arrangement kan administrator bruke forslag til navn, ukedag, tid, første dato og påmeldingsdatoer. Ukentlig antall beregnes fra arrangementets datospenn med fratrekk for periodens og skjemaets kursfrie dager. Forslagene lagres først gjennom vanlig kursforhåndsvisning; sal og andre felt beholdes.
- Laveste pris i valgte aktive kategorier kan fylles inn som fra-pris per person, også ved parpåmelding. Frontend merker fra-pris tydelig; schema hevder ikke at minimumet er en fast tilbudspris. Valuta/avgifter og faktisk ukemønster må kontrolleres, siden API-kontrakten ikke fastslår disse.
- Valgfrie påmeldingsdatoer på kursnivå begrenser periodens vindu, med samme status i administrasjon og frontend og korrekt utløp av åpne sider. Kopiering nullstiller datoene. Kapasitet utledes ikke fra disse datoene.
- Separate LetsReg-skjemaer og eksplisitt skjematilknytning for etterfølgende kursfelt gir samme lese-, tastatur- og lagringsrekkefølge uten nestede skjemaer.

## Plassering av LetsReg-kobling

- LetsReg-boksen ligger rett under «Pris og påmelding» i kurseditoren. Kursfeltene beholder én samlet lagring; LetsReg-skjemaene er separate, uten nestede skjemaer.

## UX-oppfølging – LetsReg i kursoppsettet

- LetsReg-boksen flyttet opp under kursnavnet. Søk, arrangementsvalg og kategorier på samme side; teknisk kontroll skjer automatisk. Andre utfylte felt bevares.
- Koblingen kan lagres direkte også på publiserte kurs, uten ny kurs-/periodekontroll eller republisering. Versjonskonflikter og kildekontroll håndheves fortsatt på server.
- Offentlig påmeldingslenke fra API-feltet `eventUrl` fylles inn automatisk når feltet er tomt. Ved forskjell må brukeren velge å erstatte eksisterende lenke. Valgt URL og kobling lagres sammen; ugyldige lenker avvises for autofyll.
- Vellykket søk kan følges umiddelbart av arrangementsvalg. Kontogrense på ti oppslag/minutt; feil, tilgangsstopp og leverandørens ventetid respekteres.
- Automatisert DOM-test med jsdom og utvidede WordPress-tester for bevaring av felt, publisering, historikk og trygg URL-håndtering. Ingen reelle API-kall i testene. Visuell brukertest gjenstår.

## Oppfølging 19. september 2026 – lokal kurskobling til LetsReg

- Fase 1 følger prosjekteiers prioritering: opprett kurs lokalt, søk etter LetsReg-arrangement på navn, kontroller treffet og velg priskategorier i kursoppsettet. Søket har manuell paginering med 20 treff og felles ventetid med tilkoblingskontrollen.
- Administrator velger rolle (fører/følger/uten rollefordeling) og enkelt-/parpåmelding separat. Parpris gjelder én deltaker; ingen dobling eller summering av kapasitet.
- Kobling lagres privat med forhåndsvisning, versjonskontroll og historikk. Utløpt/endret kontroll og endrede rettigheter avvises før bekreftelse. Vanlig kursredigering bevarer koblingen; kopiering av perioden nullstiller den.
- Søk, responsvalidering, kategorikobling og lokal oppbygging av kursdata kan gjenbrukes når import innføres i fase 2. Import, live kapasitet, webhook og tokenfornyelse er fortsatt ikke aktivert.
- Regresjonstester bruker syntetiske HTTP-svar og midlertidige lokale kurs. Ingen reelle LetsReg-kall er utført av kodeagenten i denne leveransen.

## Tidligere oppfølging 19. september 2026 – LetsReg API-kontrakt

- Avklart parpåmelding med prosjekteier: parkategoriene prises per deltaker; partner registreres separat via «LEGG TIL DELTAKER». Dokumentert rolle/påmeldingsform og én deltaker per kategorivalg for arrangement 613037. Delte kapasitetsgrenser og API-feltenes betydning gjenstår; ingen runtime- eller kapasitetsendring gjort i denne dokumentasjonsoppfølgingen.

- Prosjekteiers skjermbilde bekrefter faktisk arrangementskontroll for Rueda Øvet 1 (613037) og fire aktive priskategorier. M5.2-testbevis er dokumentert; sammenhengen mellom enkelt-/parpåmelding og kapasitet er fortsatt uavklart. Ingen lokale kurs er koblet automatisk, og ingen nye API-kall er utført i denne dokumentasjonsoppfølgingen.

- Prosjekteier har rapportert vellykket faktisk tokeninnlogging og arrangørkontroll mot SalsaNor Oslo (vist tidspunkt 18.09.2026 kl. 22:47:04). Roadmapen skiller nå denne bekreftelsen fra gjenstående tokenfornyelse, rettigheter og kapasitetsverifisering. Bare dokumentasjon er endret i denne oppfølgingen; ingen ny API-forespørsel er sendt.

- Lokalt Docker-oppsett for API-tilgang: `npm run env:configure` oppretter `.env.letsreg.json` og filkoblinger uten å overskrive eksisterende filer. Credentials monteres utenfor webroten og leses som JSON i WordPress/WP-CLI; ingen shell-interpolasjon av passord.
- M5.2 grunnlag: administrator kan kontrollere ett arrangement og få en privat oversikt over navn, status og priskategori-ID-er. ID/eierskap og hele kategorisvaret valideres før lagring; feil, endret tilgang og utløp håndteres. Kurskobling, live kapasitet og webhook er fortsatt ikke aktivert.

- M5.1: administrator får egen LetsReg-tilkobling med serverkonfigurerte credentials, dokumentert token-POST og kontroll av forventet arrangør-/affiliate-ID. Passord/token lagres ikke i databasen. Separat lås, varig ventetid, utløpt kontrollstatus og ugyldiggjøring ved endrede credentials er implementert. Testet med syntetiske svar; ingen reell kontoinnlogging eller live kapasitet er aktivert i miljøet.

- Sammenlignet Betait-integrasjonen med leverandørens OpenAPI 2.2.8. Dagens Swagger oppgir `/swagger/token` på integrate.deltager.no og brukernavn som `affid:username`, mens referansekoden bruker legacy-tokenadressen og separat `affid`.
- Dokumentert arrangørkontroll, kapasitetsfelt og webhook-abonnementsmodeller, samt gjenstående avklaringer om tokenlevetid, kapasitetssemantikk og mottakssignatur. M5-roadmapen er oppdatert; runtime-autentisering er ikke aktivert og ingen credentials er brukt.
- Påpekt at referansepluginens debugkode kan logge passord/token, og at dette ikke skal videreføres til RegiNor.

## Oppfølging 18. september 2026 – dag/sal, kart og ikoner

- Standardfarger kan overstyres per ukedag under Utseende og per sal i saloppsettet, med palettprøver og alpha.
- OpenStreetMap-kartvelger for kurssteder: eksplisitt adressesøk, flyttbar markør, koordinatvalidering og valgfritt kart på kursdetaljen. Leaflet leveres lokalt; Nominatim-søk er administratorbeskyttet, mellomlagret og ratebegrenset.
- Periodehandlinger viser bare ikoner med tilgjengelige navn og forklaringer. Kurstabellen viser stjerne/billett for fremheving/drop-in, med prisforklaring.
- Automatiske lokale prøver er utvidet; visuell kart-/popoverprøve og faktisk Avada-staging gjenstår.

## Under arbeid

- M5 er delt i konkrete steg for dokumentert autentisering, kurs-/poolkobling, live kontrollkø og eventuelle webhooks. Leverandøruavhengig HTTP-lesetransport med fast HTTPS-opprinnelse, ingen redirects, tids-/størrelsesgrenser og tilgangs-/retryfeil er implementert og testet med syntetiske svar. Ingen ekte autentisering, API-kapasitet eller webhook er aktivert; LetsReg-kontrakten må innhentes først.

- Valgfri énveis kobling til The Events Calendar: én heldagsoppføring fra periodestart til periodeslutt med lenke til alle kurs. Koblingen oppdaterer samme arrangement, følger synlighet og beskytter egne kalenderoppføringer mot separat redigering. Lokal TEC 6.17.5 er installert for integrasjonstesting; produksjon/staging er ikke endret.
- Fast arrangementskategori fra TEC og valgfritt standard fremhevet bilde fra mediebiblioteket under Nettsidevisning. Felles valg oppdaterer nye og eksisterende synlige kalenderoppføringer fra RegiNor, med validering og mulighet for å fjerne valgene.
- Drop-in-merket er flyttet 14 px ned over kortkanten uten å forskyve innhold.

- Fjernet «Fremhevet kurs»-etiketten fra frontend og fargeeksemplet. Fremhevingsfarge/ramme og administrasjonens stjerne beholdes.

- Drop-in-merket ligger over kortkanten uten ekstra innholdsavstand. Detaljvisningen bruker samme løsning uten egen høydereservasjon.

- Salkolonnene har 8 px luft på hver side av kurskortene i ukeskalenderen.

- Ny «Utseende»-side for frontendens bakgrunner, dag-/salkolonner, kurskort og fremhevingsfarge. Globale farger vises som fargeprøver med navn ved fokus/peking; WordPress-palett og Avada-CSS-kobling, alpha 0–100 %, tekstvalg og umiddelbart fargeeksempel. Standardvisning og dagplassering kan velges.
- Egen kursbakgrunn og fremheving styres i kursoppsettet, ikke under Utseende. Nye felt følger versjonert forhåndsvisning/bekreftelse. Drop-in aktiveres per kurs med påkrevd pris per person/kveld; skråstilt banner åpner forklaring og pris i kalender, liste og detaljer.
- HTTP- og lagringstester for de nye feltene, publiseringsgrenser, paletter og alpha. Faktisk Avada og visuell nettleserprøve gjenstår.

- Rettet registreringsrekkefølgen for administrasjonsmenyen: «Statistikk» registreres etter hovedmenyen og får riktig `admin.php?page=rnl-analytics`-lenke og side-hook. Egen HTTP-regresjon kontrollerer menylenke, administratortilgang, nonce og avvisning av kursansvarlig/anonym.

- Lokale testdata: to påfølgende perioder med tre kurs per sal i to saler, mandag/tirsdag etter kl. 18. Ett kurs per periode starter én uke senere; tirsdagens siste kurs i sal B går 20:55–22:25 (30 min senere og 30 min lengre). 24 kurs / 142 økter; idempotent script uten ekte påmeldingslenker.
- Større og tydeligere saloverskrifter, og «Senere oppstart» med faktisk dato i ukeskalenderen.
- Valgfri kampanjestatistikk under «Statistikk»: UTM, besøksøkt, side-/kursvisning og LetsReg-klikk med Complianz-samtykke; markedsførings-ID-er krever tilleggssamtykke. Egen privat lagring, 30-dagers opprydding og tilbaketrekking. Av som standard.
- GTM-dataLayer-hendelser for eksisterende oppsett, dokumentert GA4-konfigurasjon og avgrenset M5b-plan for verifisert kjøpskobling. Ingen ekstern tag, ekte webhook eller kjøpsmåling aktivert.

- Rettet avvisning av norske tegn i LetsReg-lenker, blant annet `SalsaØvet1_4_26`. UTF-8-tegn i sti, parametere og fragment kodes før URL-kontroll; eksisterende prosentkoding bevares uten dobbeltkoding. HTTPS-, innloggings- og domenekontroll videreføres.

- Rettet falsk domeneavvisning av LetsReg.no. `letsreg.no` og `www.letsreg.no` godtas sammen med .com-adressene. Skjema, publisering og offentlig visning bruker én felles domeneliste; vilkårlige underdomener og lignende domenenavn godtas fortsatt ikke.

- Skjemaoppfølging: én progressiv inngang for kursfrie dager, samlet og diskret redigering av kurskvelder, feltkort med hjelp/eksempler og løpende feltvalidering. Tomme opphold mangedobles ikke ved feil.
- Valgfri siste undervisningsdato på perioden; opphold, omplanlegging, flytting, erstatning og publisering kontrolleres mot undervisningsgrensene. Ingen kobling til synlighet eller salg. Nye tekster er oversettbare.

- Oppfølging etter sprint 5: instruktør er valgfritt; undervisning utenfor synlighetsvinduet og påmelding etter kursstart gir informasjon uten å blokkere eller kreve ekstra avkrysning.
- «Gruppe» heter «Kurs» i brukergrensesnittet. Periodeoversikten er en tabell med nyeste først, faktiske datoer, sted, dagtelling, svak grønn/gul status og handlinger med ikoner/tekst. Egne oppgavesider for Periode → Kurs → Publiser.
- PHP-/JavaScript-tekster er klargjort for oversettelse. POT-mal, script-translations og WPML-konfigurasjon er inkludert. WPML kan oversette visningstekster og kursside uten å duplisere driftsdata. Faktisk WPML/Avada-test gjenstår.

- Sprint 5: privat kapasitetsdemonstrasjon med ledighet, fullt, fører/følger, ukjent, utløp, feil og ratebegrensning. Ingen ekte LetsReg-tilkobling eller offentlige demotall.
- Egen side «Kapasitet», administratorstyrt prøveoppsett og begrenset «Kontroller nå» for kursansvarlig. Varig kontrollkø, periodisk kontroll, retry, atomiske observasjoner og adskilte forsøks-/suksesstider.
- Felles offentlig tekst «Sjekk ledige plasser hos LetsReg», bevart påmeldingslenke ved demofeil og fjernet ubekreftet InStock fra schema. Kapasitetsprøven utløper også i åpne faner.
- Nye domene-/WordPress-tester og utvidet HTTP-kontroll av rettigheter, noncer, ratebegrensning og fravær av offentlig testkapasitet.

- Sprint 4: offentlig kursliste, detaljer og ukeskalender via felles PHP-renderer i shortcode/blokk, med eget sitemap og canonical/schema fra faktiske økter.
- Dokumentert eksplisitt UX-krav for personer med lite eller ingen digital kompetanse. Forenklet administrasjon med trinn, feltgrupper, hjelpetekster og adskilte avanserte/destruktive valg.
- Felles visuell stil med avgrenset CSS, mobiltilpasset liste, store handlinger og synlig fokus. Visuell og menneskelig godkjenning gjenstår.
- Offentlig lesemodell, synlighets-/salgsgrenser, private metadata, cacheheadere og utløp av åpne sider er kontrollert med egne PHP-, HTTP- og JavaScript-tester.

- Sprint 3: kursarbeidsflate uten JavaScript-avhengighet, egen kursansvarligrolle og avgrensede ressursoppslag.
- Samlet publiseringsvalidering, uavhengig salgsstatus, eksplisitte vindusvalg, global skriverlås og transaksjoner ved publisering/kopiering/statusendring.
- Nye kladder ved kopiering med nullstilte eksterne lenker og vinduer, øktkorrigering/erstatningskvelder, avpublisering og kontrollert papirkurv for perioder og enkeltgrupper.
- Lagt til arbeidsflyt- og HTTP-tester av reelle skjemaer, rollegrenser, samtidighet og tilbakeføring. Offentlig kursvisning følger i sprint 4.

- Sprint 2: private innholdstyper og registrerte metadata, repository for kladder, stabile økter/opphold, signert endringsforhåndsvisning, objektbaserte rettigheter og atomisk versjon med full historikk.
- Knyttet kollisjonskontroll og faktiske periodegrenser til lagrede kursgrupper. Oppholds-/vindusendringer overskriver ikke grupper automatisk.
- Låst lokalt WordPress til 7.1 og CI til 6.8/7.1 etter at automatisk sisteversjon pekte på en manglende referanse.

- Sprint 1: kalenderforhåndsvisning, oppholdsunion, DST-validering, ressurskollisjoner, synlighetsregler og periodevalg implementert i ren PHP, med PHPUnit og egen klasselaster.
- Siste brukeravklaring erstatter tidligere migreringsplan: RegiNor starter fra neste nye kursperiode. Ingen import eller endring av gamle ACF-kurs. Roadmap og faseplan er oppdatert.

### Tidligere avklaringer (erstattet der siste beslutning over sier noe annet)

- Presisert målbildet etter brukeravklaring: RegiNor Lite erstatter ACF-kursløsningen og skal fungere uten ACF-avhengighet for kurs; Avada/TEC videreføres, og ACF-bruk utenfor kurs kartlegges separat.

- Startet M1 med Avada, ACF og The Events Calendar som brukerbekreftet plattform.
- Dokumentert foreløpig samspillsstrategi, offentlige URL-spor og integrasjonsprøver uten å låse en uverifisert produksjonsmodell.
- Lagt til lesende WP-CLI-kartlegging av versjoner, innholdstyper og avgrensede ACF-feltdefinisjoner; faktisk staging-kartlegging gjenstår.

## 0.1.0 – 17. september 2026

- Erstattet generisk HTML-starter med WordPress-pluginens grunnstruktur og en lesende administratorstatus.
- Opprettet roadmap basert på spesifikasjon v1.2, kartleggingsmal, arkitektur og utviklingsveiledning.
- Konfigurert lokalt WordPress-miljø, PHP-lint, WordPress-smoketest, CI og ZIP-pakking.
- Kursadministrasjon, offentlig kursvisning, migrering og LetsReg-integrasjon gjenstår i roadmapen.
