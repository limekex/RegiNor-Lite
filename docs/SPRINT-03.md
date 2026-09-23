# Sprint 3 – kursarbeidsflate, rolle og publisering

Implementert og testet lokalt 18. september 2026. RegiNor brukes fra neste **nye** kursperiode. Ingen eksisterende ACF-kurs eller URL-er er migrert eller endret.

## Leveranse

| ID | Arbeidspakke | Resultat |
| --- | --- | --- |
| S3-01 | Egen kursansvarligrolle | `rnl_course_manager`, egne periode-/gruppe-capabilities og avgrenset ressursoppslag. Ingen generelle redaktørrettigheter. |
| S3-02 | Kursarbeidsflate | RegiNor Lite → Kursperioder, nye perioder, grupper, felles/egne opphold, ukeplan og faktiske datoer. PHP-skjemaer uten JavaScript-avhengighet. |
| S3-03 | Kontrollerte endringer | Signert forhåndsvisning, før/etter-differanse, aktør-/versjonskontroll, full tilstandshistorikk og synlig siste undervisningsdag. |
| S3-04 | Øktkorrigering | Flytt/avlys enkeltøkt, angi årsak, legg til eksplisitt erstatningskveld. Avlyst økt og identitet bevares. |
| S3-05 | Publisering | Hele perioden kontrolleres og publiseres samlet, med eksplisitte vindusvalg og kontroll mot andre perioder. |
| S3-06 | Kopiering | Nye periode-/gruppe-/økt-ID-er, kladd, nye ordinære datoer, tomme lenker/vinduer og ingen arvede opphold/særflyttinger. |
| S3-07 | Avpublisering og papirkurv | Hele perioden til kladd; kladder kan legges i papirkurv og gjenopprettes. Enkeltgrupper håndteres separat. Historiske grupper/perioder beskyttes mot sletting av kursansvarlig. |
| S3-08 | Uavhengig salgsstatus | Synlighet, avlysning, salgsgrenser og redaksjonell status gir en deterministisk effektiv status. |
| S3-09 | Serverkontroller | Capabilities, objektbinding, nonce, streng feltliste, sanitering, godkjente lenkeverter, global skriverlås og transaksjoner ved samlede operasjoner. |

**Publisert i kursoppsettet betyr ikke at en offentlig nettside er levert.** RegiNors CPT-er er fortsatt private. Avada-innbygging, offentlig detaljside, kalender, schema og offentlig cache/tilgang følger i sprint 4. Dette er en lokal kodeleveranse, ikke produksjonsutrulling.

## Arbeidsflyt

1. Administrator registrerer beskrivelser, steder og saler under **Beskrivelser og steder**. Velg eksisterende instruktørtype etter kartlegging; bare publiserte profiler kan velges. Ingen profiler dupliseres.
2. Administrator gir navngitte brukere rollen **Kursansvarlig**. For en avgrenset konto brukes rollen uten andre privilegerte roller. Eksisterende brukerrettigheter fjernes ikke automatisk.
3. Kursansvarlig oppretter en periode med standarder, lokal tidssone, opphold og synlighets-/påmeldingsvinduer.
4. Opprett grupper fra beskrivelser. Velg sal, instruktører, prisgrunnlag, vilkår og LetsReg-lenke/status. Forhåndsvis konkrete datoer, konflikter og endringer før bekreftelse.
5. Etter periodeendring må gruppene gjennomgås. Regenerer planen eller velg å beholde og kontrollere eksisterende datoer. Økter som treffer opphold må korrigeres eksplisitt.
6. Bekreft tidsvinduene. Ved undervisning utenfor synlighetsvinduet eller påmelding etter kursstart kreves egne uttrykkelige valg. Publiseringskontrollen viser feil og varsler før endelig bekreftelse.
7. Ta perioden tilbake til kladd før endringer i publisert oppsett. Korriger, forhåndsvis og publiser på nytt. Dette vil med kommende offentlig policy skjule perioden mens den er kladd.

Ved feil beholdes innsendte periode-/gruppefelter i skjemaet. Vellykkede skrivehandlinger sender en 303-omdirigering til lagret oppsett, slik at vanlig oppfrisking ikke oppretter nye kopier. Utløpte eller foreldede forslag må lages på nytt.

## Publiseringsregler

Kontrollen krever nivåforklaring og beskrivelse, gyldige ressursreferanser, sal og instruktører for faktiske ikke-avlyste økter, komplett øktplan, prisgrunnlag, avklart påmeldingsstatus og korrekte tidsvinduer. Åpen påmelding og venteliste krever påmeldingslenke. En satt lenke må bruke en eksakt godkjent HTTPS-vert (`letsreg.com` eller `www.letsreg.com` som standard); ingen leverandør-API eller automatisk arrangementsverifisering er koblet til. Flere bekreftede verter kan legges til med `rnl_registration_hosts`.

Alle kollisjoner som berører perioden må avklares, også mot andre kladder. Uvedkommende konflikter mellom to andre perioder blokkerer ikke denne periodens publisering. Avlyste perioder/grupper/økter tas ut av ressurskontrollen. En eksplisitt avlyst kveld kan beholdes uten erstatning; hvis erstatning velges får den en ny beskyttet øktidentitet, mens avlysningen ligger igjen.

Publiseringsforslaget binder aktør, periode-/gruppeversjoner, relevante gjenbrukbare opplysninger og eksplisitte valg. Ved bekreftelse beregnes kontrollen på nytt under samme skriverlås som andre repository-endringer. Endrede forslag og nye konflikter avvises.

## Samtidighet og drift

`Mutation` serialiserer repository-skriving på tvers av perioder gjennom en MySQL/MariaDB-navnelås. Ventetiden er begrenset til tre sekunder; opptatt lås gir konfliktmelding. Kopiering, publisering, avpublisering og papirkurv/gjenoppretting bruker i tillegg transaksjon over posts/postmeta og krever InnoDB. Databaseendringer og historikk rulles tilbake samlet ved feil. Objektcacher ryddes etterpå.

Dette dekker pluginens skriveveier. Betrodd administrator-/plugin-kode som skriver direkte til WordPress/SQL kan omgå repository, og eksterne sideeffekter i tredjeparts WordPress-hooks er ikke en del av databasetransaksjonen. Kontroller databaseoppsett, persistent objektcache og aktuelle hooks i faktisk staging før drift.

Direkte metadataoppdatering via REST-rettigheter avvises. Ingen egne REST-/AJAX-skriveruter er åpnet. Generiske WordPress-postendringer og permanent sletting er stengt for den avgrensede kursansvarligrollen; bare den kontrollerte arbeidsflaten kan skrive kursoppsett. Det er ingen masse- eller permanent sletteknapp i denne leveransen.

## Testbevis

| Kontroll | Resultat |
| --- | --- |
| `composer check`, PHP 8.5.7 | 77 PHPUnit-tester / 129 assertions bestått, Composer-validering og PHP-lint bestått. Lokal Composer 2.5.8 gir egne deprecation-varsler. |
| `npm run test:storage`, WordPress 6.8 og 7.1 / PHP 8.2 | 58 regresjonskontroller bestått i hvert miljø. |
| `npm run test:workflow`, WordPress 6.8 og 7.1 / PHP 8.2 | 73 kontroller bestått i hvert miljø. |
| `npm run test:http`, WordPress 7.1 | 23 kontroller bestått gjennom reelle HTTP-skjemaer, cookies, noncer og omdirigeringer. Midlertidige brukere/objekter og instruktørinnstilling ryddes/gjenopprettes etter testen. |
| ZIP-pakking | 27 runtime-filer; ingen tester, lokale testbrukere eller utviklingsavhengigheter følger pakken. |
| WordPress-smoketest, 7.1 | Aktiv plugin, administratorstatus og 403 for anonym direkte tilgang bestått. |

Arbeidsflyttestene dekker samarbeid mellom to kursansvarlige, manglende rettigheter, ugyldig nonce/signatur, foreldede forslag, endrede beskrivelser, eksplisitte vindusvalg, nye identiteter ved kopiering, avlysning/erstatning, separate papirkurvomfang, lås fra en annen databaseforbindelse og tilbakeføring etter simulert publiseringsfeil. HTTP-testen gjennomfører hovedflyten som kursansvarlig og kontrollerer direkte admin-tilgang, HTML-escaping, bevarte skjemaverdier og privat caching.

**Ikke utført:** visuell nettlesertest, mobil-/tastatur-/skjermleserprøve, menneskelig A15-brukertest og faktisk Avada/ACF/TEC-staging. Browser-runtime feilet ved oppstart med `Cannot redefine property: process`, også etter ny oppstart. HTTP-kontroller erstatter ikke visuell kvalitetssikring. GitHub CI er oppdatert, men ikke kjørt fra dette arbeidsområdet.

## Neste sprint

Sprint 4 bygger offentlig kursreise og Avada-innbygging med samme synlighets-/salgsregler: liste, detalj, ukeskalender, faktiske datoer, påmeldingshandling, schema og konsistent tilgang/cache. Avklar faktiske Avada/TEC-versjoner og instruktørtype før staging-tilpasning. Ingen gammel kursrunde skal migreres.
