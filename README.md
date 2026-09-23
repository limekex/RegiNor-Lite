# RegiNor Lite

WordPress-plugin for SalsaNors kursoversikt og kursadministrasjon. WordPress eier kursinnhold og timeplan; LetsReg håndterer påmelding og betaling.

**Status 23. september 2026: M0–M4 og M4.1 implementert lokalt, pluginversjon 0.1.14.** Kursadministrasjon, frontend, navnebaserte permalenker og delingsmetadata er levert. M5 har kurskobling/import, automatisk påmeldingsstatus og kategoriers øvre kapasitetsgrenser, med avgrenset faktisk lesetest. Faktisk Avada/WPML/SEO-staging, cache/CDN, brukertest og produksjonsdrift for API-et gjenstår. [Roadmapen](docs/ROADMAP.md) skiller leverte funksjoner fra åpne godkjenningspunkter.

**Styrende UI-krav:** Løsningen skal være intuitiv for brukere med lite eller ingen digital kompetanse, og samtidig elegant. Se [krav til brukeropplevelse](docs/BRUKEROPPLEVELSE.md).

**Oppstart fra neste nye kursperiode, uten migrering.** Nye perioder opprettes direkte i RegiNor, som erstatter ACF-kursløsningen for disse. Gamle kursdata og URL-er beholdes i dagens løsning. Avada og The Events Calendar videreføres; kartleggingen avgrenser seg til kompatibilitet og relevante grensesnitt.

**M4.2/M4.3 levert lokalt:** kursprofilen har kalendernedlasting, abonnement på faktiske kurskvelder og delingsknapper. Import/oppdateringer i faktiske kalenderapper og SoMe-/visuell staging gjenstår. Kampanjestatistikk med Complianz/GTM finnes lokalt og er av som standard; besøkskobling til bekreftede kjøp er satt på vent etter LetsRegs supportsvar i sak #134895094. Valgfri historikk over LetsRegs ordresum/deltakerantall og eksperimentelle tidssammenfall med målte klikk er implementert lokalt. Dette er ikke bekreftet betaling eller kampanjeattribusjon.

## Prosjektdokumenter

- [LetsReg-endringer og beskrivelsesgjenbruk](docs/LETSREG-ENDRINGER-OG-BESKRIVELSER.md) – kildevarsler, før/etter, delte beskrivelser og vern av lokale endringer.

- [Nyeste testutgave – 0.1.14](docs/releases/0.1.14.md) – kontroll av faktisk TEC-lagring og reserveoppdatering av samme oppføring. Faktisk Pro-stage må bekreftes.
- [Cache og kursoppdatering](docs/CACHE-OG-KURSOPPDATERING.md) – konkrete funn og oppsett for Cloudflare/LiteSpeed.
- [Kurs uten LetsReg](docs/LOKAL-PAMELDING-OG-DROPIN.md) – egen lenke og kun drop-in.
- [Felles LetsReg-mal](docs/LETSREG-BESKRIVELSESMAL.md) – markører og ferdig Rueda-eksempel. [E-postutkast til Erik](docs/EPOST-ERIK-BESKRIVELSESMAL.md).
- [Første testutgave – 0.1.0](docs/releases/0.1.0.md) – samlet funksjonsomfang, installasjon og kjente avgrensninger for staging/kontrollert produksjonstest.

- [Klikk og utvikling hos LetsReg](docs/KLIKK-OG-LETSREG-UTVIKLING.md) – aktivering under Statistikk, daglig historikk og avgrenset tidsvurdering.

- [M5b: kontraktgjennomgang](docs/M5B-KONTRAKTGJENNOMGANG.md) – ordrefelt, manglende referanse-/webhook-kontrakt og neste steg.

- [M5: kapasitet per kategori](docs/M5-KATEGORIKAPASITET.md) – kategoriantall i backend og frontend, øvre grenser, parpåmelding og utløp.

- [Påmeldingsstatus](docs/PAMELDINGSSTATUS.md) – AJAX-meny per kurs uten ny publisering, automatisk LetsReg-status, manuell overstyring og oppdateringsintervaller.

- [Kursnivåer](docs/KURSNIVAER.md) – eget nivåutvalg, nivå på enkeltkurs og frontendfilter som erstatter målgruppevalget.
- [Backend: UI- og stilgjennomgang](docs/BACKEND-STILGJENNOMGANG.md) – konkrete avvik, rettelser og gjenstående visuell kontroll.

- [LetsReg-tilkobling: serveroppsett og kontroll](docs/LETSREG-TILKOBLING.md) – prosjekteier har bekreftet tilgang til SalsaNor Oslo og henting av ett arrangement med fire priskategorier. Lokal kurskobling og enkelt- og bulkimport av opptil 20 kursutkast med valgfrie nye beskrivelser fra LetsReg er implementert, med felles søk, kategorivalg og offentlig påmeldingslenke. Importen er testet med syntetiske svar; kapasitetssemantikk og automatisk tokenfornyelse gjenstår.

- [M5: LetsReg API og autentisering](docs/LETSREG-API-M5.md) – transportgrunnlag, tilgangsavklaringer og rekkefølge for live integrasjon.
- [Kontrollert LetsReg-kontrakt](docs/LETSREG-API-KONTRAKT.md) – sammenligning med Betait-integrasjonen, tokenadresse og affiliate-format fra gjeldende Swagger.

- [Kursperioder i The Events Calendar](docs/TEC-KURSPERIODER.md) – valgfri oppføring gjennom hele perioden med lenke til alle kurs.

- [Kartvelger for kurssteder](docs/KARTVELGER.md) – adressesøk, kartpunkt og offentlig kart.
- [Utseende, globale farger og drop-in](docs/UTSEENDE-OG-DROPIN.md) – fargeprøver, alpha, visningsvalg og kursstyrt fremheving/drop-in.

- [Markedsføring, måling og testdata](docs/MARKEDSFORING-OG-ATTRIBUSJON.md) – Complianz, GTM/GA4-oppsett og plan for bekreftede LetsReg-konverteringer.
- [Ny brukerflyt og språkstøtte](docs/BRUKERFLYT-OG-SPRAK.md) – siste avklaringer, periodeoversikt, I18n/WPML og testbevis.

- [Foreløpig spesifikasjon v1.2](docs/SalsaNor-kursoversikt-spesifikasjon.md) – produktkrav og akseptansekriterier.
- [Roadmap](docs/ROADMAP.md) – milepæler, leveranseporter og neste oppgaver.
- [Gjennomgang M0–M4](docs/M0-M4-GJENNOMGANG.md) – rettede hull, oppdaterte lokale testbevis og konkrete gjenstående staging-/brukerkontroller.
- [Permalenker og metadata](docs/PERMALENKER-OG-METADATA.md) – implementert M4.1 med lesbare kursadresser, SEO, Open Graph, Event-schema og sted.
- [Kalender og deling av kurs](docs/KALENDER-OG-KURSDELING.md) – implementert lokalt M4.2/M4.3: kalendernedlasting, abonnement med endringer og SoMe-knapper på kursprofilen.
- [Sprint 1 og videre faser](docs/SPRINT-01.md) – levert kode, testbevis og neste arbeidsbolk.
- [Sprint 5](docs/SPRINT-05.md) – kapasitetsdemonstrasjon, kø, rettigheter og driftsavgrensning.
- [Sprint 4](docs/SPRINT-04.md) – offentlig kursreise, UI-forenkling og innbygging.
- [Sprint 3](docs/SPRINT-03.md) – kursarbeidsflate, rolle, publisering og testbevis.
- [Sprint 2](docs/SPRINT-02.md) og [datakontrakt](docs/DATAKONTRAKT.md) – privat lagring, historikk og omplanlegging.
- [WordPress-kartlegging](docs/WORDPRESS-KARTLEGGING.md) – nødvendig grunnlag før eksisterende innhold kobles til.
- [Avada/TEC-samspill fra neste kursperiode](docs/SAMSPILL-AVADA-ACF-TEC.md) – ansvarsdeling og integrasjonsprøver.
- [Arkitektur og beslutninger](docs/ARKITEKTUR.md) – tekniske rammer og åpne avklaringer.
- [Utvikling og kontroll](docs/UTVIKLING.md) – lokalt miljø, pakking og teststrategi.
- [Endringslogg](CHANGELOG.md).

## Lokal oppstart

Krever Node.js 22+, npm 10+ og kjørende Docker. PHP 8.2+ brukes til lokal lint; Composer 2 og Python 3 brukes til henholdsvis metadatakontroll og ZIP-pakking.

```bash
npm ci
composer install
npm run env:start
```

Åpne <http://localhost:8888/wp-admin/> og logg inn med wp-envs lokale standardbruker `admin` / `password`. Velg **RegiNor Lite** i menyen. Dette er et isolert utviklingsmiljø med demoinnlogging, uten kobling til SalsaNors nettsted. Første oppstart laster ned WordPress og Docker-images.

LetsReg-tilgang i Docker: kjør `npm run env:configure`, fyll inn **`.env.letsreg.json`**, og kjør `npm run env:start -- --update` første gang. Filen ignoreres av Git og monteres utenfor webroten. `.wp-env.override.json` inneholder bare filkoblingene. Se [lokalt serveroppsett](docs/LETSREG-TILKOBLING.md#lokalt-testmiljø--docker). Ingen API-kall starter automatisk.

```bash
npm run lint
npm run test:local-config
composer test
npm run test:wordpress
npm run test:storage
npm run test:calendar
npm run test:levels
npm run test:capacity
npm run test:api-transport
npm run test:letsreg
npm run test:letsreg-mapping
npm run test:letsreg-import
npm run test:letsreg-changes
npm run test:admin
npm run test:admin-menu
npm run test:appearance
npm run test:maps
npm run test:workflow
npm run test:http
npm run test:public
npm run test:public-http
npm run test:permalinks
npm run test:permalinks-http
npm run test:interface
npm run test:journey
npm run test:analytics
npm run test:sales-history
npm run package
npm run env:stop
```

Lag valgfrie lokale testdata med `npm run demo:courses`, og kontroller dem med `npm run test:demo`: to påfølgende perioder, tre kurs per sal i to saler både mandag og tirsdag. Scriptet kan kjøres igjen uten duplikater.

Valgfrie TEC-integrasjonsprøver krever at The Events Calendar 6.17.5 er installert lokalt: `npm run test:tec` og `npm run test:tec-http`.

Installerbar ZIP opprettes i `dist/reginor-lite.zip`. Bare innholdet i `plugin/reginor-lite/` skal installeres som plugin. Node og Composer er ikke nødvendige på webserveren for denne grunnversjonen.

## Struktur

```text
plugin/reginor-lite/  Installerbar WordPress-plugin
docs/                Spesifikasjon, roadmap og tekniske notater
scripts/             PHP-lint og ZIP-pakking
tests/               PHPUnit-domenetester og WordPress-integrasjonstester
.github/workflows/   CI for PHP og WordPress
```

Midlertidig utviklingsminimum er WordPress 6.8 og PHP 8.2. Disse må bekreftes mot faktisk staging før innføring. Lokalmiljøet er låst til WordPress 7.1; CI er satt opp for 6.8 og 7.1. Lagrings-, arbeidsflyt- og HTTP-testene bruker bare et lokalt testmiljø og rydder egne testobjekter etterpå. `test:http` bruker port 8888. Rå innholdstyper er fortsatt private; den offentlige rendereren viser bare godkjent, synlig innhold. Velg kursside under **RegiNor Lite → Nettsidevisning** og legg inn blokken **RegiNor kursoversikt** eller `[reginor_courses]`. Ingen eksisterende kursside endres automatisk.
