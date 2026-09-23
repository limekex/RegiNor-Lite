# Sprint 4 – offentlig kursreise og enklere brukergrensesnitt

Oppfølging 20. september 2026: [M0–M4-gjennomgangen](M0-M4-GJENNOMGANG.md) har nyere testbevis og tre rettelser: korrekt effektiv påmeldingsåpning, fakta først i HTML-/tastaturrekkefølge med faktisk oppstart, og tidlig cachekontroll på andre innbyggingssider. Sprintresultatene nedenfor beholdes som historikk.

Implementert lokalt 18. september 2026. Prosjekteier har presisert at **både backend og frontend skal være svært intuitive for personer med lite eller ingen digital kompetanse, og samtidig elegante**. Se [styrende krav til brukeropplevelse](BRUKEROPPLEVELSE.md). God brukervennlighet er et mål som skal bekreftes med målgruppen, ikke noe automatiske tester alene beviser.

## Leveranse

| Område | Implementert |
| --- | --- |
| Offentlig inngang | Kurskort med nivåforklaring, tid, oppstart, adresse og pris. Tydelig nybegynnerinngang, periode-/dag-/nivåvalg og forståelige tomtilstander. |
| Kursdetalj | Beskrivelse, partnerinformasjon, instruktører, alle faktiske datoer, avvik, sted, prisvilkår og tydelig overgang til LetsReg. |
| Ukeskalender | Fast ukemønster med dager, saler og tidsplassering. Opphold og endringer peker til faktiske datoer. Overlappende faste mønstre får egne kolonner. |
| Innbygging | Shortcode `[reginor_courses]` og dynamisk blokk `reginor-lite/courses` bruker samme PHP-renderer. Administrator velger eksisterende kursside eksplisitt. |
| Rett offentlig innhold | `Catalog` projiserer bare publiserte, synlige opplysninger. Rå CPT-/metadataflater forblir private. Historikk, aktører og private profilfelter returneres ikke. |
| URL og metadata | Stabil gruppeadresse på valgt kursside, canonical uten filtre, sidetittel, Course/CourseInstance fra faktiske økter, og eget WordPress-sitemap. |
| Tilbakeføring av valg | Visning, periode, nivå og dag følger detaljlenken. Tilbakelenken peker til samme kurskort; JavaScript flytter fokus til ankermålet. |
| Åpne sider og cache | Ingen egen sidecache; dynamiske responser bruker `no-store`. Et lite skript fjerner utløpt innhold/påmeldingshandling i åpne faner og ber om oppdatering. |
| Administrasjon | Sammenhengende visuelt uttrykk, periodekort, tydelige trinn, feltgrupper, hjelpetekster, navngitte konflikter og avanserte/destruktive valg adskilt fra hovedflyten. |

Ingen eksisterende kurs, ACF-data, sider eller URL-er endres automatisk. Ingen produksjonsinnbygging eller migrering er utført.

## Design og forståelighet

Uttrykket bruker en varm lys bakgrunn, mørk plommefarget hovedhandling, tydelige kort, rolige flater og god luft. Typografien arves fra nettstedet. CSS avgrenses til RegiNor-klasser; Avada/TEC får ingen globale endringer i knapper eller overskrifter. Hovedhandlinger har minst 44–48 pikslers høyde, tekstetiketter og synlig tastaturfokus.

Administrasjonen viser normale oppgaver først. Tidssone, spesialdatoer, kursfrie dager, historikk og sletting er samlet der de trengs. Utfylte periode-/gruppefelter beholdes etter valideringsfeil, og avanserte felter åpnes da for korrigering. En kursansvarlig trenger ikke forholde seg til metadata, UUID-er eller API-er for å følge hovedflyten.

På smale skjermer vises ukeskalenderen som en lesbar dagliste med sal og tid på hvert kurs. Ved mer enn to samtidige sal-/overlappskolonner brukes også liste. Dette prioriterer lesbarhet fremfor å presse smale kolonner sammen. Den foreløpige spesifikasjonens ønske om to saler side om side på mobil er derfor ikke markert oppfylt; løsningen må vurderes i mobil-/brukerprøven.

## Innbygging og visningsvalg

1. Administrator velger en publisert, ubeskyttet WordPress-side under **RegiNor Lite → Nettsidevisning**. Ingen side opprettes eller endres automatisk.
2. Legg inn blokken **RegiNor kursoversikt**, eller `[reginor_courses]` i et Avada Text Block-element som tolker shortcodes. Avada dokumenterer shortcodes i dette elementet; installert versjon er fortsatt ikke verifisert. [Avada Text Block](https://avada.com/element/text-block/).
3. Kursansvarlig velger periodens standardvisning. Innbyggingen kan overstyre med `default_view="list"` eller `default_view="week"`. `allowed_views="list,week"` er standard; én visning kan angis ved behov. Besøkendes eget valg gjelder innenfor tillatte visninger.
4. Kontroller siden, cache og SEO i staging før den eksisterende kursinngangen kobles om.

Én oversikt per innbyggingsside er støttet. Gruppeadresser bruker valgt sides permalink og `rnl_course=<stabil ID>`. Ingen nye rewrite-regler eller overtakelse av gamle kursslugs er nødvendig. Ikke bytt valgt kursside etter lansering uten en eksplisitt URL-/redirect-plan.

Kursbeskrivelser har nå et valgfritt `audience`-felt: `beginner`, `experienced` eller `mixed`. Nivåforklaringen vises fortsatt i fulltekst. Eldre RegiNor-beskrivelser uten felt regnes som uavklart/flere nivåer; systemet gjetter ikke nybegynnernivå fra tittel. Dette er ingen import av gammel ACF-taksonomi.

## Tilgang, cache og schema

Synlighet og effektiv salgsstatus vurderes ved lesing med domenets klokke/policy. Kladd, utløpt og fremtidig skjult innhold gir ikke kurskort, detalj, sitemap eller JSON-LD. Direkte skjulte gruppe-/periodevalg på den valgte kurssiden gir 404. Core REST-/feed-/søkeveier får ingen rå RegiNor-CPT-er. Når WordPress REST gjengir en side med shortcoden, brukes samme filtrerte renderer og responsen merkes `no-store`.

Påmeldingslenken vises bare ved effektiv tilgjengelig-/ventelistestatus og eksakt godkjent HTTPS-vert. Avlysning, utløpt salg og avsluttet kurs gir ingen påmeldingsknapp. Ingen eksakte kapasitetstall eller live LetsReg-status er innført.

JSON-LD har ett Course-objekt med CourseInstance, faktiske start-/sluttider og en Schedule per ikke-avlyst kurskveld. Underhendelser beskriver de faktiske kurskveldenes sted, tid og avlysning/flytting, uten egne billetter/tilbud. `eventSchedule` brukes ikke parallelt med start/slutt. Dette følger modellskillet i [Schema.org CourseInstance](https://schema.org/CourseInstance) og [Googles arrangementsdokumentasjon](https://developers.google.com/search/docs/appearance/structured-data/event); ingen bestemt Google-visning loves.

**Driftskrav:** valgt kursside, dens kursparametre og RegiNor-sitemap må unntas fra fullsidecache/CDN som kan svare før WordPress kjører. Pluginens `DONOTCACHEPAGE` og HTTP-headere er ikke en garanti for at en ukjent CDN-konfigurasjon respekterer dem. Ingen cron-jobb er nødvendig for korrekt serverrespons. En allerede åpen side uten JavaScript oppdateres først ved ny forespørsel. Persistent objektcache, Avada/TEC og faktisk SEO-plugin må prøves i staging; bare core canonical/sitemap er HTTP-testet lokalt.

## Testbevis

| Kontroll | Resultat |
| --- | --- |
| `composer check`, PHP 8.5.7 | 77 domenetester / 129 assertions bestått; Composer-validering og PHP-lint bestått. |
| `test:public`, WordPress 6.8 og 7.1 / PHP 8.2 | 37 kontroller bestått i hvert miljø: eksakte tidsgrenser, publiseringsstatus, filtre, faktisk flytting, schema, private/passordbeskyttede profiler og ingen historikklekkasje. |
| `test:public-http`, WordPress 7.1 | 31 kontroller bestått: reelle offentlige URL-er, canonical, sitemap, skjult/avlyst/stengt innhold, søk, feed, REST og rå ID-forsøk. |
| `test:http`, WordPress 7.1 | 23 kontroller av den oppdaterte administrasjonsflyten bestått. |
| `test:workflow`, WordPress 6.8 og 7.1 | 73 kontroller bestått i hvert miljø. |
| `test:storage`, WordPress 7.1 | 58 regresjonskontroller bestått. |
| ZIP-pakking | 36 runtime-filer, inkludert avgrenset CSS og JavaScript; ingen tester eller utviklingsavhengigheter. |
| `test:interface` | 7 kontroller av utløpt innhold i åpne sider bestått; JavaScript-syntaks kontrollert. |

HTTP-testene bruker bare syntetiske lokale objekter, og gjenoppretter innstillinger og rydder egne brukere/objekter. De skal ikke kjøres samtidig i samme WordPress-miljø.

**Gjenstår:** visuell nettleserkontroll, reell 320/390 px-/zoom-/tastatur-/skjermleserprøve, prøve med personer med lav digital erfaring, eksterne schema-validatorer og faktisk Avada/TEC/SEO/cache-staging. Nettleserverktøyet feilet fortsatt ved oppstart (`Cannot redefine property: process`). Ingen WCAG- eller brukervennlighetsgodkjenning er utstedt. GitHub CI er konfigurert, men ikke bekreftet fra dette arbeidsområdet.

## Neste leveranse

Sprint 5 bygger kapasitetsadapteren med demonstrasjonsdata og tydelige status-/ferskhetsregler. Visuell og menneskelig kvalitetssikring fra sprint 3–4 må fullføres før lansering. Reell LetsReg-tilkobling krever den avklarte API-kontrakten; Avada/TEC-tilpasning krever faktisk staging.
