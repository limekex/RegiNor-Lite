# Oppfølging etter sprint 5 – periodeoversikt, kontroller og språk

Implementert lokalt 18. september 2026 etter prosjekteiers gjennomgang av kursadministrasjonen.

## Endrede regler

- Instruktør er valgfritt, også ved publisering. Oppgitte instruktørreferanser må fortsatt være gyldige, og eventuelle kollisjoner kontrolleres. En kurskveld må fortsatt ha sal.
- Undervisning utenfor synlighetsvinduet gir informasjon, ikke en publiseringsfeil eller ekstra godkjenningsboks. De faktiske synlighetsgrensene gjelder fortsatt for nettsiden.
- Påmelding kan stenge etter kursstart. Dette gir informasjon, ikke et krav om særskilt godkjenning. Slutten på salgsvinduet må fortsatt være etter begynnelsen.
- Flere perioder kan ha overlappende synlighets- og salgsvinduer. Salg av neste periode kan åpne mens undervisningen i den nåværende pågår.
- Brukerne møter «Kurs» i stedet for «Gruppe». Interne `group`-identifikatorer, lagrede data, rettigheter og URL-parametre beholdes for kompatibilitet.

Publiseringskontrollen skiller mellom **Dette må rettes før publisering** og **Til informasjon – hindrer ikke publisering**. Det er fortsatt én samlet bekreftelse før perioden publiseres. Ingen data eller vinduer endres automatisk på grunn av et informasjonsvarsel.

Oppfølging 20. september 2026: ved kontroll av hele kursperioden peker manglende kursbeskrivelse, nivå/forkunnskaper og påmeldingsstatus direkte til riktig kurs og feltseksjon. Meldingen navngir det konkrete feltet og gir et eksempel. «Kursbeskrivelse, nivå og forkunnskaper» finnes nå på kursets egen side, med lenke tilbake til publiseringskontrollen. Administrator kan lagre tekstene separat uten omplanlegging; kursansvarlige ser innholdet og hvem som kan fylle det ut. Hvis flere kurs bruker samme beskrivelse, forklares det før redigering. Tidligere tekstvalg og øvrige rettigheter beholdes.

## Ny periodearbeidsflate

Oversikten har en tabell med nyeste opprettede periode først. Den viser periodenavn, sted/steder, første og siste faktiske kursdato, status, dager igjen/til oppstart og handlinger med både ikon og tekst. **Aktiv nå** har svak grønn bakgrunn; **Neste** har svak gul bakgrunn. Farge er ikke eneste statusmarkering.

Aktiv periode betyr publisert, ikke avlyst og innenfor første–siste undervisningsdag i periodens tidssone. Dager telles som lokale kalenderdager, også over sommertid. «Neste» er nærmeste planlagte fremtidige oppstart; en kladd merkes samtidig tydelig som kladd. Flere perioder med samme nærmeste oppstart kan være markert. Uten ferdig øktplan brukes foreslått oppstart; sluttdato gjettes ikke.

**Opprett kursperiode** åpner et eget skjema. Hver periode har en egen adresse og tre oppgavesider:

1. **Periode:** felles opplysninger, standardverdier, synlighet og påmelding.
2. **Kurs:** tabell over periodens kurs, med tider, sal, kontrollstatus og lenke til eget kursoppsett.
3. **Publiser:** oppsummering av vinduer, kontroll og samlet publisering.

Kopiering, historikk og papirkurv er flyttet til en egen side for perioden. Papirkurvhandlingen krever fortsatt en bekreftet POST-handling og riktige rettigheter; et tabellikon sletter ingenting ved GET. Kursansvarlige får ikke utvidede sletterettigheter til tidligere publiserte perioder.

## I18n og WPML

Faste PHP-tekster, domenefeil, JavaScript-meldinger og blokkeditoren bruker text domain `reginor-lite`. Oversettelsesmalen ligger i `plugin/reginor-lite/languages/reginor-lite.pot`. Flertallsformer, tilbakevendende ukedager og sammensatte tekster med plassholdere kan oversettes. Offentlig prisformatering følger WordPress-lokalisering; maskinverdier for pris, dato og schema beholder riktig format.

PHP-domenetestene kan fortsatt kjøre uten WordPress. Det lille oversettelseslaget bruker WordPress-gettext når det finnes, og originaltekst ellers. POT-filen genereres av WP-CLI, som finner de vanlige `__`-/`_n`-kallene i kildekoden. JavaScript bruker `wp.i18n` og `wp_set_script_translations`; oversettelsesfiler for JavaScript inngår i pakkens tillatte filer.

```bash
npm run i18n:pot
```

Originaltekstene er norsk bokmål. Oversettelser opprettes fra POT-filen i ønsket språk, og leveres som standard WordPress MO-/JSON-filer eller håndteres av WPML String Translation. **Det følger ingen ferdig engelsk oversettelse med denne leveransen.** Sett riktig kildespråk for norske plugintekster i WPML når de skannes. Språkpakker for selve WordPress påvirker blant annet dato- og tallformatering.

### Kursinnhold og felles driftsdata

`wpml-config.xml` markerer RegiNors private innholdstyper som felles for språkene. Aggregate metadata (`rnl_state` og `_rnl_capacity`) ignoreres av WPMLs kopiering/oversettelse. Dermed blir ikke kursperioder, økter, kurs-ID-er, priser, påmeldingslenker eller kapasitetsjobber duplisert per språk.

Visningstekster registreres separat under **RegiNor Lite courses** i WPML String Translation: titler, kursbeskrivelser, nivåforklaringer, partnerinformasjon, prisvilkår, steds-/salnavn, adresser og forklaringer på endringer/opphold. Nøkler er stabile objekt-/feltidentiteter. Registrering leser endelig lagret tilstand ved slutten av forespørselen, slik at et tilbakeført databaseforsøk ikke registrerer den mislykkede mellomtilstanden. Eksisterende RegiNor-tekster registreres ved første administratorbesøk etter aktivering av String Translation. Ingen ACF-data leses.

Offentlig visning oversetter bare tekstfeltene. Kurssiden velges gjennom WPMLs sidekobling; kursparametrene beholder samme kurs-ID. Instruktørnavn kan følge eksisterende profiloversettelser, og schema bruker samme oversatte innhold og gjeldende locale som siden. Uten WPML brukes originalinnhold og opprinnelig kursside.

Løsningen bruker dokumenterte WPML-grensesnitt for [registrering av egne innholdstekster](https://wpml.org/wpml-hook/wpml_register_single_string/), [henting av oversettelser](https://wpml.org/wpml-hook/wpml_translate_single_string/) og [kobling til oversatt side/profil](https://wpml.org/wpml-hook/wpml_object_id/). Registrering/henting av innholdstekster krever WPML String Translation-modulen. Operasjonelle felt oversettes ikke gjennom disse hookene.

**Avgrensning:** WPML er ikke installert i testmiljøet. Hook-kontraktene er prøvd med testfiltre; faktisk WPML-språkvelger, String Translation-editor, cache per språk, Avada-innbygging og flerspråklig sitemap/SEO må prøves i staging. Dette er klargjøring for WPML, ikke en kompatibilitetssertifisering. Ved sletting kan ubrukte tekstregistreringer fortsatt finnes i WPMLs oversettelsesoversikt og ryddes der.

## Testbevis

| Kontroll | Resultat |
| --- | --- |
| `composer check` | 102 tester / 192 assertions, Composer-validering og PHP-lint bestått. |
| `test:admin`, WordPress 6.8 og 7.1 | 26 kontroller i hvert miljø: valgfrie instruktører, fortsatt obligatorisk sal, ikke-blokkerende vinduer, sortering/dager/sommertid, språk-hooker, escaping og stabile data. |
| `test:workflow`, WordPress 6.8 og 7.1 | 73 kontroller i hvert miljø. |
| `test:public`, WordPress 6.8 og 7.1 | 37 kontroller i hvert miljø. |
| `test:http`, WordPress 7.1 | 29 kontroller av opprettelse, kurssider, publisering, kopiering, papirkurv og kapasitetstilgang. |
| `test:public-http`, WordPress 7.1 | 37 kontroller av offentlige flater og synlighet. |
| Øvrig WordPress 7.1-regresjon | 58 lagringskontroller, 39 kapasitetskontroller og smoketest bestått. |
| `test:interface` | 10 kontroller av utløp og oversatt JavaScript-tekst. |
| `i18n:pot` | POT generert fra PHP/JavaScript uten ekstraksjonsvarsler. |

Visuell nettleserprøve og brukertest med målgruppen gjenstår. Tidligere sprintprotokoller er historikk; denne avklaringen erstatter deres krav om instruktør og særskilt godkjenning av de to tidsvarslene.

## Oppfølging: rolige skjemaer og validering ved utfylling

Implementert 18. september 2026 etter skjermbildene fra kurseditoren:

- Kursfrie dager legges til via én knapp. Bare opphold brukeren har lagt til vises. Tomme rader fra tidligere skjemafeil blir ikke lagt til på nytt. Opphold kan markeres for fjerning og angres før lagring.
- Uten JavaScript vises én sammenfoldet inngang for et nytt opphold; lagre og åpne skjemaet igjen for å legge til flere.
- «Endre én kurskveld» er én samlet, sammenfoldet inngang med små datorader. Øktendringer har fortsatt forhåndsvisning og bekreftelse.
- Feltene i periode-, kurs-, beskrivelses- og stedsoppsett får rolige feltkort, forklaring, relevante eksempler og kobling mellom felt, hjelp og feilmelding. Påkrevde tekst-/datofelt er merket. Eksemplene er hjelp/placeholder, aldri ferdig utfylte kursdata.
- Datoer, rekkefølge på tidsvinduer, ukedag for første kursdato, klokkeslett, antall kvelder, pris, tidssone og lenkeformat kontrolleres mens brukeren fyller ut. Feil meldes ved feltet. Ved innsending åpnes lukkede feltgrupper og fokus flyttes til første feil.
- Manglende LetsReg-lenke ved påmelding/venteliste, feil domene og feil port gir beskjed om hva som må ordnes før publisering. Kladd kan fortsatt lagres uten lenke. Feil HTTPS-format gir feltfeil med eksempel. Serverens publiseringskontroll er fortsatt avgjørende.
- Utfylte felt beholdes ved serverfeil; berørt kurskveld åpnes igjen. Alle nye tekster er oversettbare og inkludert i POT-malen.

### Undervisningsgrenser

Perioden hadde tidligere bare en planlagt startdato. Det er nå lagt til **Siste tillatte kursdato (valgfritt)** (`period.end_date`). Dette er en planleggingsgrense, ikke tidspunktet kursene skjules eller påmeldingen stenger.

Start og valgt slutt er inklusive: kursfrie dager/perioder og kursdatoer må ligge innenfor grensene. Hvis sluttdato ikke er satt, gjelder bare startgrensen; dette forklares i skjemaet. Eksisterende perioder uten feltet fungerer videre uten migrering. Oversikten og offentlige kursdatoer avledes fortsatt fra faktiske økter.

Serveren kontrollerer opphold i både periode og kurs. Ved omplanlegging brukes den strengeste av periodens sluttdato og kursets egen absolutte siste dato. En pause som gjør at undervisningskveldene ikke får plass gir feil, slik at brukeren kan utvide perioden eller justere antall kvelder. Flytting, erstatningskvelder, beholdt plan, bekreftelse og publisering kan ikke omgå periodens grenser. Avlyste kurskvelder beholdes som historikk. Endring av perioden skriver ikke automatisk om kursene.

Kopiering til en ny periode nullstiller sluttdatoen, slik at den gamle periodens grense ikke følger med. Synlighets- og salgsvinduer er fortsatt uavhengige; salg etter oppstart og overlappende perioder er tillatt.

### Testbevis for denne oppfølgingen

Lokalt på WordPress 7.1 / PHP 8.2:

- `composer check`: PHP-lint og 102 domenetester / 192 assertions (lokal PHP 8.5.7).
- `test:admin`: 69 kontroller, inkludert grenser, opphold på sluttdagen, omplanlegging, flytting, erstatning, publisering, kopiering, tomme rader, utfylte felt og tilgjengelige hjelpekoblinger.
- `test:workflow`: 73, `test:storage`: 58, `test:http`: 29; WordPress-smoketest bestått.
- `test:interface`: 41 nye JavaScript-kontroller av feltregler samt 10 eksisterende kontroller av utløp/oversettelse.
- `test:public`: 37 og `test:public-http`: 37 kontroller bestått. POT-mal generert uten advarsler. ZIP inneholder 49 runtime-filer.

Visuell nettleser-, tastatur- og skjermleserprøve er **ikke** bekreftet. Nettleserverktøyet feilet ved tilkobling (`Cannot redefine property: process`). Rene JavaScript-regeltester og HTTP-tester erstatter ikke en interaktiv prøve av de dynamiske feltene. Den nye oppfølgingen er ikke kjørt på WordPress 6.8 eller faktisk WPML/Avada-staging.

## URL-retting: LetsReg.no

Den lokalt lagrede kurslenken `https://www.letsreg.no` ble avvist ved publisering fordi standardlisten bare inneholdt `letsreg.com` og `www.letsreg.com`. [LetsRegs norske adresse](https://www.letsreg.no/) ble kontrollert 18. september 2026 og videresendte til `https://www.letsreg.com/no/`.

Standardlisten inkluderer nå også `letsreg.no` og `www.letsreg.no`. `RegistrationDomains::allowed()` leverer samme liste til editorens JavaScript, publiseringskontrollen og den offentlige lesemodellen. Eksisterende filter `rnl_registration_hosts` beholdes. Ingen kursdata endres automatisk. HTTPS-, port- og domenekontroll beholdes; etterlignende domener og andre underdomener godtas ikke. Domenegodkjenning bekrefter ikke at lenken peker til riktig kurs: rotadressen over går til leverandørens forside.

Regresjonstestene dekker alle fire domenenavn, den konkrete rotadressen, kurslenke med query/fragment, offentlig videreføring, lik domeneliste i skjemaet, feil port og etterlignende domener.

Bestått for URL-rettingen på lokalt WordPress 7.1/PHP 8.2: `test:admin` 78, `test:workflow` 73, `test:http` 29, `test:public` 37 og `test:public-http` 37. `test:interface`: 56 feltkontroller og 10 utløps-/oversettelseskontroller. `composer check`: lint og 102 domenetester / 192 assertions. Ny ZIP med 50 runtime-filer er integritetskontrollert. Ingen nye oversettbare tekster ble introdusert. Visuell nettleserprøve er ikke kjørt for denne rettingen.

## URL-retting: norske tegn i kursnavnet

Brukerens konkrete adresse `https://www.letsreg.com/no/register/SalsaØvet1_4_26` avdekket en egen feil i tillegg til `.no`-domeneavvisningen: PHPs `FILTER_VALIDATE_URL` avviste rå UTF-8 i stien, selv om nettleseren godtok adressen.

`RegistrationUrl::normalize()` koder nå ikke-ASCII-tegn i sti, query og fragment før formatkontroll. Adressen over blir `https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26`, som peker til samme mål. Eksisterende prosentkoding, understreker, skilletegn og parametere bevares. Allerede kodede adresser kan lagres gjentatte ganger uten dobbeltkoding. Normaliseringen brukes gjennom `StateSchema`, både ved innsending/lagring og offentlig lesing. Ingen eksisterende kurs blir omskrevet i bakgrunnen.

HTTPS, fravær av innloggingsopplysninger og kontroll av godkjent domene/port håndheves fortsatt. Vi koder ikke om vertsnavnet for å få ugyldige adresser gjennom kontrollen. Mellomrom, kontrolltegn, bakovervendt skråstrek og ugyldig UTF-8 avvises fortsatt.

Tester inkluderer den nøyaktige rapporterte lenken som faktisk HTTP-skjemainnsending, bekreftet lagring, publisering, offentlig påmeldingsknapp og schema. Egne domenetester dekker også æ/ø/å, allerede kodet URL, parametere, fragment, idempotens og ugyldige adresser. JavaScript-testene bekrefter at både lesbar og kodet lenke godtas uten feil domenevarsel.

Bestått for Unicode-rettingen: `composer check` 119 tester / 216 assertions; `test:interface` 62 feltkontroller og 10 utløpskontroller. Lokalt WordPress 7.1/PHP 8.2: `test:admin` 90, `test:storage` 58, `test:workflow` 73, `test:http` 31, `test:public` 37 og `test:public-http` 37. Den offentlige HTTP-testen velger nå sin egen testperiode eksplisitt, slik at allerede publiserte lokale kurs ikke påvirker listetestene. POT er oppdatert og ZIP med 51 runtime-filer er bygget. Ingen faktisk påmelding ble sendt; visuell nettleserprøve er ikke utført for denne rettingen.
