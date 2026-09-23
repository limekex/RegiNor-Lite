# Kartlegging av eksisterende WordPress

Status 17. september 2026: **påbegynt; faktisk installasjon ikke kartlagt**. RegiNor starter fra neste nye kursperiode, uten import av gamle kurs eller ACF-data. Avada og The Events Calendar videreføres. Kartleggingen gjelder kompatibilitet, navnekollisjoner, maler/cache og eventuelle instruktør-/stedsreferanser. Det er ikke nødvendig å levere en gammel kursrunde eller full ACF-felteksport for å starte utviklingen.

Se [samspillsplanen](SAMSPILL-AVADA-ACF-TEC.md). Dokumentert produktstøtte og indekserte offentlige spor er ikke bekreftet installasjonskonfigurasjon. Ingen produksjonsendringer er utført.

Oppfølging 20. september 2026: [lokalmiljøet er nå kartlagt](M0-M4-GJENNOMGANG.md#faktisk-lokalmiljø), inkludert TEC og RegiNor-registreringer. Dette er ikke et uttrekk fra SalsaNors staging/produksjon. Rapportversjon 2 inkluderer database/miljøtype, cacheflagg, privat lagringsstruktur, shortcode/blokk, valgt kurssides status/innbygging og konfigurerte instruktørtyper. Ved kjøring uten nyere RegiNor-kode oppdages direkte shortcode/blokk; synkroniserte blokkreferanser krever den nye hjelperen eller manuell kontroll.

## Miljø og drift

| Punkt | Faktisk verdi / kilde | Status |
| --- | --- | --- |
| Staging og produksjonsadresse | Offentlig referanse: `https://www.salsanor.no/`; staging ikke oppgitt | Delvis |
| WordPress-, PHP- og databaseversjon | Ikke kartlagt | Åpent |
| Aktivt tema / child theme / sidebygger | Avada bekreftet av bruker; versjon, child theme, Core/Builder og layoutoppsett ukjent | Delvis |
| Installerte plugins, særlig SEO og feltverktøy | ACF og The Events Calendar bekreftet av bruker; utgaver/versjoner, Pro-tillegg, SEO og øvrige plugins ukjent | Delvis |
| Sidecache, objektcache, CDN og tømmerutiner | Cloudflare og LiteSpeed Cache bekreftet av prosjekteier 21.09.2026. Offentlig HTML viser Guest Mode og utsatt JS. | [Observasjon og oppsett](CACHE-OG-KURSOPPDATERING.md); faktisk regel-/tømmetest gjenstår |
| Permalenker, språk og tidssone | Ikke kartlagt | Åpent |
| Cron, kømuligheter og driftsansvar | Ikke kartlagt | Åpent |
| Backup, gjenoppretting og staging-isolasjon | Ikke kartlagt | Åpent |
| Nåværende roller og direkte brukerrettigheter | Ikke kartlagt | Åpent |

## Innholdsmodell og identiteter

For hvert objekt: noter faktisk CPT-/taksonominøkkel, feltnavn/type, eksempel-ID, relasjoner, eierplugin, REST-eksponering og offentlig URL. Ikke bruk foreslåtte `rnl_`-nøkler som om de allerede finnes.

| Objekt | Avklaring |
| --- | --- |
| Kurs | Er ett innlegg en gjenbrukbar beskrivelse eller en konkret gjennomføring? Finnes samme navn i flere grupper? |
| Kursperioder/grupper | Hvordan er koblinger og publiseringsstatus lagret i dag? |
| Økter/tidsluker | Reelle datoer eller taksonomier/fritekst? Hvordan angis unntak? |
| Instruktører | Eksisterende CPT, stabile ID-er, relasjoner og offentlige/private felt |
| Nivå/dansestil | Nøkler, rekkefølge, nivåforklaring og gjenbruk |
| Sted/sal | Entydige ID-er, adresse og salens stedstilhørighet |
| Pris/påmelding | Øre/kroner, prisgrunnlag, tillegg, partnerpraksis og medlemsplikt |

### Første offentlige spor

Nettverktøyet kunne denne gangen lese en **indeksert** utgave av [kursoversikten](https://www.salsanor.no/kurs-i-oslo/). Den er oppgitt som innhentet omtrent fem måneder før kartleggingen og viser eldre kursinnhold; dette dokumenterer ikke dagens tilbud eller en fersk visuell gjennomgang.

- Oversikten lenker ukentlige kurs til blant annet `/kursoslo/salsa-ovet1/` og introduksjonskurs til `/arrangement/salsa-intro-25-t-april/`. Det tyder på ulike innholdsløp; CPT-nøkler og eierskap kan ikke utledes sikkert fra URL-ene. Detaljene for disse to adressene kunne ikke hentes.
- Det finnes en offentlig [instruktørside](https://www.salsanor.no/instruktor/andres-estevez/) med tilknyttede kurs. Dette støtter gjenbruk av eksisterende profiler, men verifiserer ikke lagrede relasjonsfelt eller ID-er.
- Oversikten viser nivålenker, flere saler og LetsReg-handlinger. Ingen feltmapping eller nye kursdata er opprettet fra denne teksten.

Spesifikasjonens opprinnelige merknad om blokkert nettlesing beholdes som historikk. Den nye observasjonen er avgrenset til tekst/lenker i tilgjengelige indekserte sider.

### Avada-spesifikk kartlegging

- Avada-versjon, Builder/Core-versjoner, aktivt child theme og eventuelle maloverstyringer.
- Avada Layouts-betingelser for `/kurs-i-oslo/`, kursdetalj, instruktør, TEC-arrangement og arkiver. Registrer hvilken mal som eier hver flate.
- Hvilke Post Cards, Events-elementer, shortcodes og ACF-felt som brukes; særlig eventuelle dupliserte mobil-/desktop-oppsett.
- Globale farger, typografi, innholdsbredden og cache/CSS-/JS-optimalisering som pluginens avgrensede stiler må fungere med.

### ACF: avgrens eksisterende løsning fra nye kurs

- Identifiser eksisterende CPT-/taksonominavn og offentlige ruter, slik at nye RegiNor-registreringer ikke kolliderer.
- Registrer hvilke Avada-maler/innbygginger som fortsatt viser gamle kurs og skal bestå.
- Identifiser eventuelle delte instruktør-/stedsprofiler som nye kurs skal referere til. Bekreft hvordan de kan brukes uten ACF-avhengighet i kursmodulen.
- Ingen konvertering av gamle kursfelt, overtakelse av registreringer, import eller sletting inngår. ACF kan fortsatt være aktivt for eksisterende innhold.

### The Events Calendar-spesifikk kartlegging

- Free/Pro-versjoner, tillegg (Event Tickets, Filter Bar, import m.m.) og status for eventuell datalagringsmigrering.
- Hvilke eksisterende kurs er TEC-arrangementer, og hvilke er egne kursinnlegg? Brukes gjentakelser eller Series?
- Eksisterende steder, arrangører og kategorier med ID-er; ikke anta at en TEC-arrangør er en instruktør, eller at et kurssted er én sal.
- Publiceringskanaler: kalender, Avada Events-element, enkeltarrangement, REST, søk, feeds/ICS, sitemap og JSON-LD.
- Dokumenter hvem som i dag kan endre arrangementer og tider. Velg én eier av hver timeplan før synkronisering vurderes.

## Lesende innsamling fra WordPress

Et [WP-CLI-skript](../scripts/inspect-wordpress.php) er klart for første strukturuttrekk. Det trenger ikke at RegiNor-pluginen installeres. Kopier skriptet til en privat servermappe og kjør fra riktig WordPress-installasjon, helst staging:

```bash
wp eval-file /privat/sti/inspect-wordpress.php > /privat/sti/reginor-kartlegging.json
```

Bytt plassholderstiene til faktiske private stier. For multisite: velg korrekt nettsted med WP-CLIs `--url`. La aktuelle plugins og tema lastes; `--skip-plugins`/`--skip-themes` gir et ufullstendig bilde.

Skriptet leser versjoner, tema/parent, pluginliste, registrerte posttyper/taksonomier, rettighetsmapping og avgrensede ACF-feltdefinisjoner. Det utfører ingen skriveoperasjoner eller eksterne API-kall. Innleggstekst, feltverdier, brukere, passord, lisensnøkler og generelle opsjonsdumper eksporteres ikke. Rapporten er likevel intern teknisk informasjon og skal gjennomgås før deling; bruk git-ignorert `var/` om den lagres lokalt i repoet.

Utdata erstatter ikke målrettet kontroll av faktiske kurseksempler, uregistrerte eldre metafelt, Avada-maler, cache/CDN, brukerrettigheter eller TEC-forekomster. ACFs detaljerte visningsregler må etterkontrolleres i admin; rapporten utelater enkelte regelverdier. Se også utviklingsveiledningen for kjøring mot lokalmiljøet.

## Første nye kursperiode

Bekreft navn, ønsket oppstart, ukedager, tider, antall kvelder, opphold, nivåforklaringer, instruktører, sted/saler, prisvilkår og faktiske LetsReg-lenker når administrasjonen er klar. Opprett perioden direkte som kladd i RegiNor. Syntetiske testdatoer i sprint 1 er ikke SalsaNors neste bekreftede kurstilbud.

## URL-er og presentasjon

- Kartlegg forsiden, `/kurs-i-oslo/`, kursdetaljer, instruktørsider, nivåhjelp og eksisterende arkiver.
- Registrer canonical, schema, sitemap, indekserte historiske sider og eventuell redirectlogikk.
- Noter nåværende maler, innbyggingspunkt for blokk/shortcode og mobilmenyens eier.
- Prøv mobilflyt på 320/390 px og tastatur; dokumenter faktisk observert atferd separat fra hypotesene i spesifikasjonen.
- Kapittel 19.5s synlighetsregel gjelder nye RegiNor-grupper. Eksisterende ACF-kurs og deres URL-er beholdes uten automatisk redirect eller skjuling.

## LetsReg

Dokumenter faktiske lenker og om de gjelder én gruppe eller en felles periode. Bekreft organisasjon, stabile eksterne ID-er, godkjente målverter og testarrangement. Bruk leverandørens støttede serverkonfigurasjon for hemmeligheter; ingen passord/token i dokumentet.

Før live integrasjon: verifiser autentisering, tokenfornyelse, kapasitet/roller/par/pooler, reservasjoner, tidssoner, kvoter og webhook-kontrakt etter kapittel 16.1. Skjemaet i spesifikasjonen er referansegrunnlag; kontospesifikk semantikk er fortsatt uavklart.

## Beslutningsrapport etter kartlegging

- Dato, miljø, utfører og hvilke eksport-/kodeversjoner funnene bygger på.
- Bekreftede navn/ruter for nye RegiNor-objekter, uten kollisjon med eksisterende innhold.
- Nødvendige versjoner og Avada-/TEC-integrasjonspunkter; eventuelle avgrensede instruktør-/stedsreferanser.
- Uløste verdier med ansvarlig og konsekvens; ingen automatisk gjetting.
- Plan for staging, første nye periode, representative tester og tilbakeføring av ny kursvisning.
- Oppdatert estimat for roadmap M2–M6.
