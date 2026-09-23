# Sprint 1 – domenefundament for neste nye kursperiode

Startet 17. september 2026. Teknisk leveranse er ferdig lokalt; verifisering og videre faser er beskrevet nedenfor. Ingen dato for produksjonsinnføring er avtalt.

## Mål og avgrensning

Gi den nye kursadministrasjonen en testet kjerne for datoer, opphold, kollisjoner og periodetilgjengelighet. Prosjekteier har avklart at RegiNor tas i bruk fra neste nye kursperiode. **Ingen gamle kurs eller ACF-data skal migreres.** Avada/TEC-kompatibilitet kartlegges parallelt; gamle kursposter og URL-er beholdes.

Sprinten leverer PHP-logikk, ikke ferdige kurseditorer eller publiserte kurssider. Alle datoer, ID-er og kursoppsett i tester er syntetiske. Den første virkelige perioden opprettes senere med bekreftede opplysninger.

## Arbeidspakker

| ID | Leveranse | Status |
| --- | --- | --- |
| S1-01 | Avklart oppstart fra neste nye periode og faseplan uten import | Levert |
| S1-02 | Klasselaster og PHPUnit med låsefil; tester kjøres uten WordPress/ACF | Levert |
| S1-03 | Kalenderforslag med ukedager, opphold, antall økter, lokale tider, UTC og søkehorisont | Levert |
| S1-04 | Kollisjoner for faktiske intervaller og sal-/instruktørreferanser | Levert |
| S1-05 | Synlighetsvindu, gruppe-/periodestatus og deterministisk periodevalg med testbar klokke | Levert |
| S1-06 | Lokale tester, pakking og WordPress-smoketest | Se testbevis |

## Kode og kontrakter

- `Domain/LocalDateTime.php`: streng dato-/tidsvalidering og entydige lokale klokkeslett. Ugyldige og tvetydige DST-tidspunkt avvises.
- `Domain/Scheduling/`: `ScheduleRequest`, `BreakPeriod`, `ScheduledTime`, `ScheduleGenerator`, `SessionAllocation` og `ConflictValidator`.
- `Domain/Publication/`: `Clock`, `SystemClock`, `Period` og `PublicationService`.
- `tests/Unit/`: atferdstester for kalender, kollisjoner og periodetilgang.

Se [arkitekturens kontrakter](ARKITEKTUR.md) for forutsetninger. Kalenderforslaget har ikke persistente økt-ID-er og kan ikke brukes til å overskrive eksisterende økter. Kollisjonskontrollen trenger faktiske økter fra alle relevante perioder; uthenting fra repository kommer senere. Periodemodellen trenger første/siste faktiske økt og offentlig gruppetilhørighet fra samme datagrunnlag.

## Akseptanse og begrensninger

| Krav | Bevis i sprint 1 | Gjenstår |
| --- | --- | --- |
| A4/A5 | Seks mandager, opphold og bevaring av lokal tid over begge DST-skifter | Bruk i editor og lagring |
| A6 | Alle syv ISO-ukedager gir riktig første dato | Kalender, filtre og schema |
| A7 | Sal-/instruktøroverlapp, tilgrensende intervaller og separate saler | Automatisk uthenting på tvers av lagrede perioder |
| R4/R6 | Halvåpent vindu; kladd/privat/papirkurv/ugyldige vinduer avvises av domenepolicy | HTTP, REST, sitemap, cache og øvrige kanaler |
| R7/R8 | Pauseuker, overlappende perioder, nærmeste kommende og tomtilstand | Periodevelger og navigasjon |
| R9/R11 | Oppholdsunion, gruppeunntak, sluttdato, DST og deterministiske grensevalg | Lagring, endringsforhåndsvisning og ende-til-ende-kontroll |

A8 om bevarte øktidentiteter/manuelle endringer er ikke implementert. Full salgsstatus, feltvalidering, kursansvarligrolle, repository og cacheintegrasjon gjenstår. Ingen komplette offentlige A-/R-kriterier markeres bestått bare på grunnlag av domenetestene.

## Testbevis – 17. september 2026

| Kontroll | Resultat |
| --- | --- |
| `composer check` på PHP 8.5.7 | Bestått: Composer-/låsefilvalidering, lint av 21 PHP-filer, 57 PHPUnit-tester / 94 assertions. |
| Samme PHPUnit-suite på PHP 8.2.29 i midlertidig Docker-container | Bestått: 57 tester / 94 assertions; repoet montert skrivebeskyttet. |
| `npm run test:wordpress` på lokalt WordPress 7.1 / PHP 8.2 | Bestått etter bytte til klasselaster; pluginlasting, adminstatus og 403 ved uautorisert tilgang. |
| ZIP-pakking og integritetskontroll | Bestått: 15 runtime-filer, inkludert klasselaster og domeneklasser; ingen PHPUnit/vendor eller testdata i pakken. |
| Dokumentlenker, JSON og `git diff --check` | Bestått. |

Lokal Composer 2.5.8 gir egne deprecation-varsler på PHP 8.5, men kontrollen avsluttes med kode 0. GitHub CI og PHP 8.3/8.4 er konfigurert, ikke kjørt her. Avada/TEC, virkelig staging, nettleser og full offentlig kursflyt er ikke testet i sprinten.

## Neste sprint og faser

1. **Sprint 2 – egen lagring:** nye RegiNor-objekter, metadata, stabile økt-/oppholds-ID-er, repository, historikk og kontrollert omplanlegging. Kontroller navnerom og relevante profilgrensesnitt mot installasjonen før staging.
2. **Sprint 3 – kursarbeidsflate:** opprett første nye periode, grupper, opphold, priser og LetsReg-lenker; kursansvarligrolle, forhåndsvisning og validering før publisering.
3. **Sprint 4 – offentlig visning:** Avada-innbygging, kort/detalj, ukeskalender, filtre, felles publiseringskontroll og schema.
4. **Sprint 5 – kapasitet:** demonstrasjonsadapter/status og eventuell verifisert LetsReg-tilkobling.
5. **Sprint 6 – innføring:** staging, faktisk ny kursperiode, brukertest og bytte av kursinngang med tilbakeføring. Gamle kurs beholdes.

Detaljer og leveranseporter finnes i [roadmapen](ROADMAP.md).
