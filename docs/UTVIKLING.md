# Utvikling og kvalitetssjekker

Nyeste samlede kontroll av grunnleveransen finnes i [M0–M4-gjennomgangen](M0-M4-GJENNOMGANG.md), med testresultater, lokalt miljø og gjenstående staging-/brukerprøver.

## Miljø

Bruk Node.js 22+, npm 10+, Docker og PHP 8.2+. Composer 2 validerer prosjektmetadata; Python 3 lager ZIP. Installer npm-avhengigheter med `npm ci` og behold låsefilen i Git. Kjør `composer install` for PHPUnit-utviklingsavhengighetene fra `composer.lock`. Ingen Composer-pakker kreves av den installerte pluginen. `qs` er midlertidig overstyrt til 6.16.0 fordi wp-envs transitive Express-avhengighet låser en eldre serie med npm-audit-varsler; vurder å fjerne overstyringen når oppstrømsavhengigheten er oppdatert.

```bash
npm ci
composer install
npm run env:start
```

[WordPress sitt wp-env-verktøy](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) oppretter et isolert lokalmiljø på port 8888. Det ekstra testmiljøet er slått av; den lesende smoketesten bruker utviklingsmiljøet. Bare pluginmappen monteres som plugin; testene monteres separat under `rnl-tests`. Lokale standardinnlogginger er `admin` / `password`.

Konfigurasjonen bruker PHP 8.2 og er låst til WordPress 7.1 for reproduksjon. For et annet miljø, opprett git-ignorert `.wp-env.override.json`, for eksempel:

```json
{
  "core": "WordPress/WordPress#6.8",
  "port": 8890
}
```

Velg kartlagt staging-versjon i dette feltet når den er kjent. Kjør `npm run env:start -- --update` etter konfigurasjonsendring. Stopp uten å slette data med `npm run env:stop`. Ikke kjør reset/destroy som vanlig opprydding; det sletter lokale data.

## Kontroll av grunnversjonen

```bash
composer check
npm run test:wordpress
npm run test:storage
npm run test:capacity
npm run test:admin
npm run test:workflow
npm run test:http
npm run test:public
npm run test:public-http
npm run test:interface
npm run package
```

`composer check` validerer Composer-filen/låsefilen, sjekker PHP-syntaks og kjører PHPUnit. `composer test` kjører bare domenetestene, uten WordPress eller ACF. Uten Composer kan syntaks kontrolleres med `npm run lint` eller `php scripts/lint.php`. Ved lokal Composer 2.5 på PHP 8.5 kan Composer selv gi deprecation-varsler; disse er separate fra pluginens lintresultat.

WordPress-smoketesten krever kjørende wp-env. Den laster pluginen gjennom ekte WordPress, kontrollerer registrert admin-hook, administratorvisning og avvisning med 403 ved uautorisert direkte kall. Den endrer bare gjeldende bruker i CLI-prosessen og oppretter ikke innhold eller brukere. Dette er ingen bekreftelse av fremtidig kursrolle eller offentlig tilgangskontroll.

Lagringstesten krever `WP_ENVIRONMENT_TYPE=local`, som er satt i wp-env. Den oppretter syntetiske kursobjekter og testbrukere, og rydder bare disse i en `finally`-blokk. Den kontrollerer schema, rettigheter, referanser, historikk, omplanlegging, versjonskonflikter og faktiske periodegrenser. Den skal ikke kjøres i staging eller produksjon.

Arbeidsflyttesten bruker syntetiske administrator-/kursansvarlig-/abonnentkontoer og tester publisering, kopiering, rettigheter, papirkurv, transaksjoner og en lås fra en annen databaseforbindelse. HTTP-testen kjører mot localhost:8888 med midlertidig kursansvarligkonto, reelle skjemaer og noncer. Den velger midlertidig en lokal profiltype og gjenoppretter innstillingen i oppryddingen. Kjør HTTP-testen separat fra andre tester i samme miljø. Begge testene er begrenset til `WP_ENVIRONMENT_TYPE=local`.

CI er konfigurert for PHP-lint og PHPUnit på 8.2–8.5 og WordPress-smoke-, lagrings-, arbeidsflyt- og HTTP-tester på WP 6.8 og 7.1 med PHP 8.2. CI er ikke en full kompatibilitetsmatrise og må få nye kontroller når funksjonalitet innføres. GitHub-kjøring bekreftes etter push.

## Strukturkartlegging

`scripts/inspect-wordpress.php` kjøres med WP-CLI mot installasjonen som skal kartlegges. Det trenger ikke RegiNor som aktiv plugin. Se [kartleggingsveiledningen](WORDPRESS-KARTLEGGING.md) for privat rapportlagring og avgrensning.

I lokalt wp-env er `scripts/` montert som `rnl-tools`. Etter endringen i mounts kjøres `npm run env:start` før:

```bash
npm run inspect:wordpress
```

Dette viser **lokalmiljøets** struktur, ikke SalsaNors installasjon. npm/wp-env legger statuslinjer rundt JSON-utskriften; bruk ren `wp eval-file` på serveren ved eksport til JSON-fil. Kartleggingsskriptet inngår ikke i pluginens ZIP.

Kontrollert 17. september 2026: skriptet kjørte i WordPress 7.1/PHP 8.2.29 og ga gyldig JSON med 16 registrerte posttyper og 8 taksonomier. Fravær av ACF håndteres med `available: false` og tom feltgruppeliste. PHP-lint, konfigurasjons-JSON og lokale dokumentlenker passerer. ACF-eksportgrenen og samspill med Avada/TEC er ikke funksjonstestet, siden disse ikke er installert i lokalmiljøet; det gjenstår mot relevant staging-oppsett.

## Pakking

`npm run package` lager `dist/reginor-lite.zip` med rotmappen `reginor-lite`. Pakkeskriptet har en eksplisitt tillatt filliste: hovedfil, PHP under `src`, CSS/JavaScript under `assets` og kompilerte oversettelser. Legg til fremtidige maler, blokkmetadata og frontendfiler her når de innføres. Dokumentasjon, tester, node_modules og lokale hemmeligheter skal ikke følge pakken.

Pakken kan installeres i lokal WordPress/staging via **Utvidelser → Legg til ny → Last opp utvidelse**. Den gir privat kursarbeidsflate, kursansvarligrolle og administrativ publisering. Offentlig kursvisning kan bygges inn på en eksplisitt valgt side. Faktisk Avada/TEC-prøve og visuell/menneskelig kvalitetssikring gjenstår; dagens kursinngang skal ikke byttes automatisk.

## Videre teststrategi

- M2: automatiske tester av faktiske kalenderutfall, DST, alle ukedager, opphold, kollisjoner, stabile økt-ID-er, synlighetsgrenser og periodevalg. PHPUnit er innført i sprint 1.
- M3: WordPress-integrasjonstester med separate kursansvarlig-/administrator-/anonyme brukere; direkte admin/REST/AJAX, kopiering, versjonskonflikter og sletting.
- M4: HTTP-/nettlesertester av alle offentlige kanaler, utløpt cache og JavaScript-fri visning. Manuell mobil, zoom, fokus og skjermleser.
- M5: adapterkontrakttester, ufullstendige svar, utdaterte snapshots, duplikater, rekkefølge, retry og rolle-/poolkapasitet uten persondata.
- M6: første nye kursperiode, uendrede gamle URL-er, tilbakeføring av ny visning og faktisk brukertest. Ingen kursmigrering. Testbevis kobles til A-, U-, R- og eventuelle L-kriterier i spesifikasjonen.

Ingen produksjonsdata eller hemmeligheter i testfixtures, logger, ZIP eller Git.

## Verifisert ved initialisering – 17. september 2026

| Kontroll | Resultat |
| --- | --- |
| `composer check` | Bestått; lokal Composer 2.5.8 gir egne deprecation-varsler på PHP 8.5.7. |
| PHP-lint | Fem PHP-filer uten syntaksfeil på lokal PHP 8.5.7. |
| `npm run env:start` | Bestått med WordPress 7.1 og konfigurert PHP 8.2 i Docker. |
| `npm run test:wordpress` | Bestått: aktiv/lastet plugin, admin-hook, administratorvisning og 403 for anonym direkte tilgang. |
| `npm run package` og ZIP-integritetskontroll | Bestått; tre runtime-filer under `reginor-lite/`. |
| npm audit etter qs-overstyring | Ingen kjente sårbarheter rapportert ved installasjon. |
| Lokale dokumentlenker, JSON og `git diff --check` | Bestått. |
| GitHub CI, WP 6.8 og full PHP-kjørematrise | Konfigurert, ikke kjørt i denne initialiseringen. |
| Visuell nettlesertest, tilgjengelighet og SalsaNor-staging | Ikke utført; funksjonalitet og tilgang mangler foreløpig. |

Testresultatet gjelder grunnversjonen. Ingen A-, U-, R- eller L-kriterier for den fremtidige kursfunksjonaliteten er markert bestått.

## Sprint 1

Se [sprintprotokollen](SPRINT-01.md) for testbevis og avgrensning fra sprint 1. Initialiseringstabellen ovenfor er historikk fra før sprint 1.

## Sprint 2

Privat WordPress-lagring og kontrollert omplanlegging er implementert. Se [sprint 2](SPRINT-02.md) for oppdaterte testbevis. Kursarbeidsflate, publisering og samspill med faktisk Avada/TEC-installasjon er ikke verifisert av lagringstestene.

## Sprint 3

Se [sprint 3](SPRINT-03.md) for gjeldende testbevis: arbeidsflyt og rollegrenser på WordPress 6.8/7.1, HTTP-hovedflyt og utvidede domenetester. Visuell nettlesertilgang feilet ved oppstart; mobil, tastatur, skjermleser og menneskelig brukertest gjenstår. Tidligere testtabeller er historikk fra de respektive sprintene.

## Sprint 4

Se [sprint 4](SPRINT-04.md) for gjeldende testbevis og [brukeropplevelse](BRUKEROPPLEVELSE.md) for akseptanse før lansering. `test:public` bruker injisert klokke i lokal WordPress og kontrollerer offentlige projeksjoner ved eksakte grenser. `test:public-http` kjører reelle anonyme HTTP-forespørsler, setter opp syntetiske kurs og kursside og gjenoppretter lokal side-/instruktørinnstilling i oppryddingen. Kjør den separat fra andre tester i samme miljø. `test:interface` prøver tidsgrenseadferden i tilleggsskriptet, uten å erstatte nettleser- eller tilgjengelighetstest.

Nye frontendfiler inngår i ZIP-pakkens eksplisitte tillatte filer. Ingen npm-pakker kreves på webserveren. Se **RegiNor Lite → Nettsidevisning** for blokk/shortcode og avgrenset valg av kursside.

## Sprint 5

Se [sprint 5](SPRINT-05.md) for kapasitetsdemonstrasjon, kontrollkø, driftskommando og testbevis. `test:capacity` kontrollerer syntetiske observasjoner, rettigheter, retry, avbrutt lagring og gjenopptak i lokal WordPress. Testen bruker egne grupper og en injisert klokke, og kjører ikke andre gruppers jobber. HTTP-testene prøver noncer/ratebegrensning og kontrollerer at demotilstander aldri blir offentlig lagerstatus. Kjør dem sekvensielt i samme miljø.

Demonstrasjonsadapteren har ingen nettverkskall. All offentlig kapasitet er foreløpig ukjent; den godkjente LetsReg-lenken fungerer ved åpen påmelding. Serverstyrt jobbkjøring, live pool-/kvotemodell og faktisk API-verifisering gjenstår før en ekte adapter kan aktiveres.

## Periodeoversikt og språk – oppfølging etter sprint 5

`test:admin` prøver nye publiseringsregler, periodeoversikt/dagtelling og WPML/gettext-kontrakter med syntetiske data. `i18n:pot` genererer pluginens POT-mal fra PHP/JavaScript. ZIP-pakken inkluderer `wpml-config.xml`, POT og eventuelle MO-/JSON-/l10n.php-filer. Se [gjeldende brukerflyt og språkstøtte](BRUKERFLYT-OG-SPRAK.md) for testbevis og avgrensning. Ingen ekte WPML-installasjon eller ferdig engelsk oversettelse følger testene.

## Lokale LetsReg-variabler

Kjør `npm run env:configure` og fyll inn `.env.letsreg.json`. Første gang kjøres `npm run env:start -- --update` for å montere filen utenfor webroten og laste den via en lokal MU-plugin. Eksisterende override-filer beholdes. Se [oppsett og test](LETSREG-TILKOBLING.md#lokalt-testmiljø--docker). Tilgangsverdier skal ikke ligge i wp-envs `config`, siden denne verktøyversjonen bygger shell-kommandoer av verdiene.

## Kalender og deling (M4.2/M4.3)

`npm run test:calendar` prøver kalenderidentitet, revisjoner, flytting, avlysning, språk, kopiering, synlighet og atomisk publisering i lokalt WordPress. `npm run test:interface` inkluderer delings-/kopieringsatferd.

HTTP-prøven bruker en uavhengig parser som bare trengs under testing:

```bash
python3 -m venv /tmp/rnl-calendar-check
/tmp/rnl-calendar-check/bin/pip install -r tests/requirements-calendar.txt
RNL_CALENDAR_PYTHON=/tmp/rnl-calendar-check/bin/python npm run test:calendar-http
```

Prøven oppretter og rydder syntetiske kurs, og skal kjøres sekvensielt med andre WordPress-/HTTP-prøver. Kalenderfilen valideres med `icalendar` og prøves via anonym HTTP, inkludert samtidige lesinger og utløp. Faktiske klientprøver, CDN/HTTPS og SoMe-forhåndsvisninger er separate leveranseporter i [kalender-/delingskontrakten](KALENDER-OG-KURSDELING.md).
