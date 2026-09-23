# Sprint 5 – kapasitetsdemonstrasjon og kontrollkø

Implementert lokalt 18. september 2026. Demonstrasjonen gjør det mulig å prøve kapasitet uten tilgang til LetsReg og uten å påvirke påmelding. **Ingen ekte API-tilkobling, webhook, deltakerdata eller offentlige demoantall er innført.** Gamle ACF-kurs og URL-er berøres ikke.

## Leveranse

| Område | Implementert |
| --- | --- |
| Prøvesituasjoner | Ukjent, ledige plasser, fullt, ulik tilgjengelighet for fører/følger, utløpt, midlertidig feil og ratebegrensning. Kun syntetiske observasjoner. |
| Enkel arbeidsflate | **RegiNor Lite → Kapasitet**: velg kurs med periode, navn, ukedag og tid. Se en forståelig status, siste forsøk, siste suksess og neste kontroll. Avansert prøveoppsett ligger adskilt. |
| Rettigheter | Bare administrator setter opp/stopper prøven. Kursansvarlig kan se resultat og bestille «Kontroller nå» for eksisterende oppsett. Objektkontroll, capability og nonce håndheves på serveren. |
| Varig kø | Én privat metadatarad per gruppe inneholder jobb, oppsett og observasjon. Manuell kontroll og automatikk bruker samme jobb og begrensninger. |
| Polling/retry | Normalt nytt forsøk etter fem minutter. Manuell kontroll tidligst etter ett minutt. Feil gir eksponentiell ventetid med jitter; en normalisert Retry-After-verdi kan aldri forkortes av kontrollknappen. |
| Atomisk lagring | Felles skriverlås og sammenligning av gammel verdi beskytter jobben. Forsøket lagres før resultatet; avbrudd eller mislykket lagring gir ikke en ny suksessdato. |
| Ferskhet | Høyst 15 minutter per observasjon. Utløp, feil og ufullstendige/eldre svar gir reservevisning. Siste gode bilde beholdes internt ved feil. |
| Offentlig visning | Kort, detalj og ukeskalender bruker «Sjekk ledige plasser hos LetsReg» ved åpen påmelding. Ingen demoopplysninger leses inn som ekte ledighet. Påmeldingslenken beholdes ved demofeil. |
| Schema og åpne faner | Ubekreftet `InStock` er fjernet. JavaScript fjerner utløpt kapasitetsprøve i en åpen administrasjonsfane. Offentlige synlighets-/salgsgrenser gjelder fortsatt. |
| Kopiering og stopp | Kopiert periode får ingen kapasitetsmapping, jobb eller observasjon. «Ingen prøve» fjerner gruppens prøveoppsett. Sletting av egne kurs rydder metadata gjennom WordPress. |

## Slik prøves funksjonen

1. Opprett en kursperiode med minst én gruppe. Kurset kan være kladd.
2. Åpne **Kapasitet**, velg kurset og trykk **Vis valgt kurs**.
3. Som administrator: velg en prøvesituasjon under **Prøveoppsett for administrator**, og lagre.
4. Den bestilte kontrollen behandles av jobbkjøreren. Oppdater siden for å se resultatet. Kursansvarlig kan senere bruke **Kontroller nå**, uten tilgang til oppsettet.
5. Velg **Ingen prøve – stopp kontrollene** når prøven er ferdig.

Hovedflyten fungerer uten JavaScript. I en åpen fane uten JavaScript må siden lastes på nytt for å oppdatere prøveresultatet. Eksakte antall vises ikke i grensesnittet, og demovisningen sier tydelig at opplysningene er oppdiktet. Ingen bruker må oppgi tekniske arrangement-ID-er for å prøve funksjonen.

## Lagring og avgrensning

`_rnl_capacity` på en privat `rnl_group` inneholder oppsettsversjon, `provider=demo`, scenario, konfigurasjonsaktør/-tid, snapshot, `last_attempt_at`, `last_success_at`, feilkode, feilantall, `next_attempt_at`, `not_before` og `requested_at`. Feltene er ikke åpnet i REST eller generell metadataredigering. Kursenes redaksjonelle versjon og publiseringshistorikk endres ikke av kontrolljobber.

Observasjoner tillater bare komplette, normaliserte felter: tidspunkt, utløp, antall eller fører/følger, og valgfri kildeversjon. Negative spesialverdier, ukjente felter, manglende roller og blanding av samlet antall med rolleantall avvises. `null` er ukjent; `0` er bekreftet ingen plasser i prøvesituasjonen. Ingen par-/poolberegning utledes fra disse verdiene. Ufullstendige eller eldre observasjoner får ikke erstatte det sist godkjente bildet.

Den offentlige projeksjonen er bevisst låst til ukjent kapasitet. Verken endring av demometadata eller et ferskt demobilde kan aktivere offentlig lagerstatus. Redaksjonell avlysning, skjuling, salgsvindu og avsluttet kurs beholder sin forrang. Venteliste følger eksisterende redaksjonelle regler og lenke; demonstrasjonen oppretter ingen venteliste.

Jobbkjøreren behandler inntil 25 forfalte grupper per kjøring, i en felles lås. Adapteren har ingen nettverkskall, og én gruppe har bare én vedvarende jobb. Dette er en avgrenset demonstrasjonskø, **ikke en ferdig live kø-/poolmodell**. Ved live API må eksterne ID-er, delte pooler, kvoter, nettverkstidsgrenser, oppdeling/skalering, eventuell kildeversjon og driftsvarsling verifiseres. Ikke legg nettverkskall under den globale kurslåsen uten å endre denne modellen.

## Jobbkjøring og drift

Pluginen registrerer `rnl_capacity_tick` hvert minutt når et prøveoppsett finnes. Jobben kontrollerer selv hvilke grupper som er forfalt. Ved deaktivering fjernes cron-hendelsen; data beholdes. Ved reaktivering gjenopprettes hendelsen for gjenværende prøveoppsett. Metadata slettes sammen med kursgruppen; det bygges ikke opp en ubegrenset hendelseslogg.

WP-Cron kan brukes lokalt, men en fast kontrollfrist kan ikke loves ut fra nettstedstrafikk. Før eventuell live innføring må faktisk serverstyrt kjøring settes opp, overvåkes og prøves. WordPress dokumenterer [planlegging og opprydding av cron-hendelser](https://developer.wordpress.org/plugins/cron/scheduling-wp-cron-events/) og [WP-CLI-kjøring](https://developer.wordpress.org/cli/commands/cron/event/).

Lokal manuell kjøring etter oppsett:

```bash
npm run wp-env -- run cli wp cron event run rnl_capacity_tick
```

Eksempel for driftsansvarlig, kjørt hvert minutt av riktig systembruker og med faktisk WordPress-sti:

```cron
* * * * * wp cron event run rnl_capacity_tick --path=/sti/til/wordpress --quiet
```

Dette er dokumentasjon; ingen server-crontab er endret. Behold øvrige WordPress-jobber. Ikke deaktiver all WP-Cron uten en plan for andre plugins. Oppsett-/køfeil bekreftes ikke som vellykket lagring, avbrutte forsøk kan gjenopptas, og flere påfølgende kildefeil forklares i arbeidsflaten. Varsling utenfor arbeidsflaten inngår ikke.

## Testbevis

| Kontroll | Resultat |
| --- | --- |
| `composer check`, PHP 8.5.7 | 102 tester / 192 assertions; Composer-validering og PHP-lint bestått. |
| `test:capacity`, WordPress 6.8 og 7.1 / PHP 8.2 | 39 kontroller: kø, duplikatbegrensning, rettigheter, versjonskonflikt, siste gode bilde, Retry-After, avbrutt resultatlagring, gjenopptak, privat visning og nullstilling ved kopiering. |
| `test:http`, WordPress 7.1 | 29 kontroller, inkludert kapasitetsnonce, avvist oppsettendring for kursansvarlig, bestilling og ratebegrensning. |
| `test:public-http`, WordPress 7.1 | 37 kontroller, inkludert bevart booking ved fem demotilstander og fravær av ubekreftet lagerstatus. |
| Regresjon, WordPress 7.1 | Smoketest, 58 lagringskontroller, 73 arbeidsflytkontroller og 37 offentlige kontroller bestått. |
| Minimumsmiljø, WordPress 6.8 | I tillegg til kapasitet: 37 offentlige kontroller og smoketest bestått. |
| ZIP-pakking | 42 runtime-filer; integritet kontrollert. |
| `test:interface` | 9 kontroller av tidsgrenser i offentlig side og privat kapasitetsprøve. |

Lokale testobjekter, brukere og midlertidige innstillinger ryddes etter kjøring. Testene sender ingen forespørsler til LetsReg. GitHub CI er utvidet med kapasitetsprøven, men er ikke kjørt fra dette arbeidsområdet.

Visuell nettleserprøve, mobil/zoom/tastatur/skjermleser, menneskelig brukertest og faktisk Avada/TEC/SEO/cache-staging gjenstår fra sprint 4. Denne sprinten gir ingen visuell eller WCAG-godkjenning. Ingen L1–L10-kriterier er bekreftet mot et reelt LetsReg-arrangement.

## Neste fase

Prosjekteier har prioritert videre M5-arbeid med API-autentisering. Se [M5.1–M5.4 og leverandøravklaringer](LETSREG-API-M5.md). HTTP-leselag og [manuell token-/arrangørkontroll](LETSREG-TILKOBLING.md) er nå implementert og testet med syntetiske svar. Oppfølgingen omfatter også lokalt Docker-oppsett og første M5.2-kontroll av arrangement/priskategori-ID-er. Prosjekteier har rapportert vellykket faktisk token-/arrangørkontroll mot SalsaNor Oslo. Live kapasitet og webhooks er fortsatt ikke prøvd/aktivert. Innføringsarbeidet under kan fortsette uavhengig av dette.

Sprint 6 forbereder innføring fra neste **nye** kursperiode: faktisk miljøkartlegging, staging, brukertest med 1–2 kursansvarlige og 4–6 deltakere, cache/schema-kontroll og tilbakeføringsprøve. Live kapasitet er valgfritt ved første innføring. Ingen migrering av gamle kurs inngår.
