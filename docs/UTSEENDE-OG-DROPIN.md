# Utseende, globale farger og drop-in

Implementert lokalt 18. september 2026. Prosjekteiers avklaring: felles frontendvalg ligger under **RegiNor Lite → Utseende**, mens valg for ett kurs ligger i **kursoppsettet**.

## Felles utseende

Administrator velger bakgrunn for hele oversikten, dagkolonner, dagoverskrifter, salkolonner, vanlige kurskort og fremhevede kurs. Salkolonnens bakgrunn dekker både overskriften og selve tidsplanen. På mobil brukes dagliste; salnavnet står på kurset.

Hvert fargefelt tilbyr:

- Fargeprøver fra nettstedets globale palett; navn vises ved fokus/peking. Trykk velger fargen og fokuserer prøven. Skjermleser får navn og valgt-status. Uten JavaScript finnes et vanlig navngitt valg.
- Egen farge, eller en koblet global farge som fortsetter å følge paletten.
- **Alpha / dekkevne 0–100 %**, med skyveknapp og tallfelt. 0 er transparent, 100 er helt dekkende. Alpha gjelder bare bakgrunnen, ikke tekst eller knapper.
- Automatisk utgangspunkt for tekst, eller eksplisitt mørk/lys tekst. Gjennomsiktighet, bilder bak komponenten og senere endringer i temapaletten gjør at automatisk utgangspunkt ikke er en garanti for kontrast. Kontroller valgt kombinasjon på den faktiske siden.

Fargeeksemplet oppdateres umiddelbart med JavaScript. «Bruk standardfargene» nullstiller fargevalgene i skjemaet, inkludert alpha og palettkoblinger. «Lagre farger og visning» gjør valgene gjeldende på nettsiden. Skjemaet tilbyr også standardvisning (periodevalg, liste, ukeskalender) og plassering av dagene ved siden av eller under hverandre. Besøkendes filter-/visningsvalg og eksplisitte blokk-/shortcodevalg går fortsatt foran standardvalget.

## Dagsfarger per visning – 0.1.11

Under **Utseende → Hvor skal dagsfargene brukes?** velges **Kun kalendervisning**, **Kun kurslistevisning (kort)** eller **Begge**. Valget gjelder både standard dagsfarger og overstyringer per ukedag. Eldre oppsett beholder **Kun kalendervisning** som standard.

I kalenderen gjelder fargen dagens bakgrunn/overskrift. Med kun kursliste får kalenderen nøytral dagsbakgrunn; sal- og kursfarger beholdes. I kurslisten blir dagsfargen kortets bakgrunn. Egen kursfarge går foran dagsfargen, og fremheving går foran begge. Alpha, global palettkobling og tekstfarge følger fargevalget. Dagsfarger påvirker ikke enkeltkurssiden. Forhåndsvisningen viser både kalender og et mandagskort, inkludert eventuell mandagsoverstyring.

Kalenderens saloverskrifter er sentrert: salnavn på første linje med eksisterende størrelse, sted på egen linje med 80 % størrelse. Lange navn kan brytes videre ved behov. Listekort viser salnavnet tydelig under Hvor, før sted og adresse. Enkeltkurssiden viser tydelig salnavn i både påmeldingssidefelt og kart-/adresseboksen.

## Unntak per dag og sal

Under **Utseende → Egne farger for bestemte dager** kan administrator gi hver ukedag egen bakgrunn og overskrift. Hver dag arver standardfargene til «Bruk egen farge denne dagen» velges. Fjern haken for å arve igjen. Valget følger ukedagen på tvers av kursperioder.

Under **Beskrivelser og steder → Saler → velg sal** finnes «Bruk egen farge for denne salen». Fargeprøver, alpha og tekstfarge fungerer på samme måte som andre fargefelt. Fargen gjelder salens overskrift og bakgrunn i kalenderen på alle dager. Saldata lagres med eksisterende versjonskontroll; ingen kurstider endres. Kurskortets egen farge og fremheving er fortsatt separate valg.

## Ikoner i tabellene

Periodeoversiktens Åpne, Rediger og Kopier viser bare ikoner i 44 × 44 px trykkflater. `aria-label` gir et kort navn, og `aria-describedby` kobler til en utfyllende forklaring. Forklaringen vises ved peking/fokus og kan lukkes med Escape. Popover-laget hindrer at den klippes av tabellens scrollområde i nettlesere med støtte for Popover API; eldre nettlesere får CSS-basert forklaring.

Kurstabellen har kolonnen **Merker**: stjerne for fremhevet kurs og billettikon for drop-in. Forklaringen for drop-in inkluderer pris per person og kurskveld. Merkene følger de lagrede kursvalgene; et kurs uten disse valgene viser en tankestrek.

## Kursoppsett

**Kursperioder → velg periode → åpne kurset → Utseende og drop-in.** Både administrator og kursansvarlig kan bruke dette som del av vanlig kursredigering.

- **Fremhev dette kurset:** ramme og fremhevingsfarge i liste, kalender og detaljer, uten egen etikett. Fremhevingsfargen går foran eventuell egen kursbakgrunn.
- **Bruk egen bakgrunn:** velg fargeprøve, egen farge, alpha og tekstvalg for kurset. Fjern avkrysningen for å gå tilbake til felles kursfarge.
- **Dette kurset tilbyr drop-in:** viser et skråstilt «Drop-in»-banner over kortkanten, uten å forskyve det øvrige innholdet. Det kan åpnes med klikk, trykk eller Enter/Space og viser forklaringen «Dette kurset tilbyr drop-in.» samt pris per person og kurskveld.
- **Drop-in-pris:** oppgis i kroner, eksempel `200,50`. På server lagres 20050 øre. Pris er påkrevd når drop-in er aktivert; 0 betyr gratis. Slå av drop-in for å fjerne banner og prisinformasjon. Hele kursets pris og LetsReg-lenke endres ikke av dette valget.

Kursvalg inngår i versjonert kursdata, forhåndsvisning og bekreftelse. Drop-in-prisen og fremhevingsstatus vises også i oppsummeringen før lagring/publisering. Vanlig kladd-/publiseringsflyt gjelder: en publisert periode tas tilbake til kladd før kursene endres og publiseres igjen. Ingen fremheving eller drop-in-innstilling gjør et skjult kurs offentlig. Kursdatoer flyttes ikke av presentasjonsvalgene. Kopiering av et RegiNor-kurs følger eksisterende kopieringsflyt og kopierer disse kursfeltene med øvrig prisoppsett.

Drop-in er redaksjonell informasjon om tilbudet. Det opprettes ikke en ekstra checkout, prisintegrasjon eller bekreftet ledighet hos LetsReg. Kontroller at kursets tilbud og LetsReg-oppsett stemmer før publisering.

## Palettkoblingen

WordPress/Gutenberg-paletten hentes gjennom `wp_get_global_settings(['color', 'palette'])`, inkludert temaets og brukerens globale farger. Standardpaletten utelates hvis temaet har deaktivert den. Referansen lagres som palettens slug og bruker `--wp--preset--color--<slug>` på nettsiden. Se [WordPress API](https://developer.wordpress.org/reference/functions/wp_get_global_settings/) og [fargekontrakten i theme.json](https://developer.wordpress.org/themes/global-settings-and-styles/settings/color/).

Avada aktiveres ved aktivt Avada-tema/child theme eller lastet Avada-klasse. Avadas fargeposisjoner bindes til `--awb-colorN`; pluginen leser ikke udokumenterte interne temainnstillinger. En skjult forhåndsvisning av nettstedets forside leser faktiske CSS-farger, inkludert ekstra fargeposisjoner, og sender dem til innstillingssiden. Dette krever samme origin og administratorinnlogging; scriptet på forsiden lastes bare med administratorrettighet og gyldig nonce. Meldinger kontrolleres mot origin og riktig iframe. En forespørsel/svar-mekanisme håndterer både varm cache og ulik lasterekkefølge.

Avada-fargene vises med posisjonsnavn, eksempel «Avada · Farge 4», fremfor egendefinerte navn som bare ligger i Avadas private datamodell. Fargeprøvene er de faktiske fargene når temaforhåndsvisningen er tilgjengelig. Koblingen består etter at en farge er endret sentralt. Hvis tema/palett fjernes, brukes den lagrede reservefargen. Første åtte Avada-posisjoner tilbys uten dynamisk deteksjon; ekstra posisjoner oppdages fra frontend-CSS. Se [Avadas globale farger](https://avada.com/blog/working-with-color-options-in-avada/) og [offisielt eksempel på CSS-variabel](https://avada.com/blog/avada-financial-advisor-deconstructing-a-prebuilt-website/).

Alpha bruker `color-mix(in srgb, …, transparent)` rundt den koblede fargen. Hvis selve globale fargen allerede er transparent, kombineres denne alphaen med RegiNors dekkevne. Ingen CSS-tekst eller URL-er aksepteres fra skjemafeltene; bare avgrensede fargekoder, palettreferanser og prosentverdier.

## Verifikasjon

- `composer check`: 138 tester / 256 assertions.
- `test:appearance`: 42 lokale kontroller av paletter/alpha, kurslagring, drop-in/pris, reset, publiseringsgrenser og visningsvalg.
- `test:http`: 34 kontroller, inkludert kursansvarlig som velger kursfarge/drop-in og bekrefter pris med desimaler.
- WordPress bootstrap, storage (58), workflow (73), admin (90), public (37), public-http (37), interface (96) og demo (212) bestått lokalt.
- `test:maps`: kartkoordinater, gjenbrukbare sted-/sal-skjemaer, arving av farger og simulert adressesøk med cache/rategrense. Se [kartvelgeren](KARTVELGER.md).
- Egen HTTP-prøve for Utseende-meny, administratortilgang, nonce, lagring og ugyldig alpha. Innstillinger og syntetiske testdata gjenopprettes etter prøvene.

Faktisk Avada-installasjon er ikke tilgjengelig lokalt. WordPress-palett, CSS-kontrakten og egne skjemaer er kontrollert; Avada-farger, ekstra posisjoner, iframe/cache og navn må prøves i staging. Nettleserverktøyet kunne ikke starte (`Cannot redefine property: process`), så visuell kontroll, pekeflate, tastatur/popover og mobil er ikke verifisert i en ekte nettleser. Automatiske tester erstatter ikke denne prøven.
