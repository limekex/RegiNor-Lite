# Markedsføring, besøksvei og LetsReg

Oppdatert 23. september 2026. Complianz, WP Consent API og Site Kit brukes på salsanor.no. GTM-KDW4RQJS er integrert, og RegiNor-workspace 7 er konfigurert mot GA4-property 355411161 / G-Z4N25LJ0NH. RegiNor-taggen er upublisert. Første faktiske test fant for tidlige hendelser og en ekstra reisestart under tilbaketrekking; rettingen leveres i 0.1.15. LetsReg-kjøpskobling er fortsatt ikke verifisert.

## Levert nå

**RegiNor Lite → Statistikk** gir administrator oversikt over målte besøksøkter, kursinteresse og kampanjer for de siste 30 dagene. Innsamling er avslått som standard og aktiveres med «Registrer besøksveien etter samtykke». Uten Complianz og samtykke samles det ikke inn besøksdata. Det opprettes ingen ny Google-/Meta-tag eller GTM-container.

| Trinn | Lokal måling og GTM-hendelse | Betydning |
| --- | --- | --- |
| Innkomst | `rnl_landing` | Første målte side i en besøksøkt etter samtykke |
| Side | `rnl_page_view` | En offentlig side er lastet |
| Kursoversikt | `rnl_course_list` | Oversikten er vist |
| Kursdetaljer | `rnl_course_view` | Et bestemt kurs er vist |
| Videre til LetsReg | `rnl_letsreg_click` | Klikk på kursets offentlige påmeldingslenke |
| Bekreftet påmelding/kjøp | **Ikke tilkoblet** | Må bekreftes av LetsReg; nettleseren kan ikke rapportere dette til endepunktet |

Dette er besøksøkter i nettleseren, ikke identifiserte personer eller en garantert komplett salgstrakt. Rapporten teller unike målte besøksøkter per trinn, kampanje og kurs. Ett besøk kan ha flere kurs og manglende mellomtrinn. Visning og klikk telles høyst én gang per kurs per sideinnlasting. Ikke summer kursradene som et antall personer. Innloggede brukere, forhåndsvisninger, private/passordbeskyttede sider og 404-sider utelates. Målingen krever JavaScript; kursvisning og påmeldingslenke fungerer uten.

UTM-feltene `utm_source`, `utm_medium`, `utm_campaign`, `utm_id`, `utm_content`, `utm_term` tas med. Rapporten grupperer på kilde, kanal, kampanje og annonsevariant (`utm_content`). Eksempel på en **syntetisk** kampanjelenke:

```text
https://ditt-nettsted.no/kurs/?utm_source=facebook&utm_medium=paid_social&utm_campaign=host_2026&utm_content=video_a
```

Bruk kampanjenavn, aldri deltakernavn, e-post eller andre personopplysninger i parametrene. Verdier er begrenset til korte bokstaver/tall og enkel tegnsetting; e-post og URL-lignende verdier forkastes. Dette er dataminimering, ikke en garanti for at fritekst aldri kan inneholde personopplysninger.

Uten UTM brukes gjenkjennelig henvisningsdomene som Google, Bing, Facebook, Instagram eller TikTok; ellers «referral» eller «direct». Henvisningens adresse eller søketekst lagres ikke. Facebook-klikk-ID alene beviser ikke betalt annonsering. Kilde settes ved første målte side i økten og overskrives ikke av senere sider. Hvis samtykke først gis på en senere side, kan opprinnelig kampanje være tapt; den gjettes ikke tilbake.

## Arrangementshistorikk og eksperimentell sammenligning

Administrator kan separat slå på **Lagre LetsRegs ordresum og deltakerantall over tid**. Rapporten viser daglige målte klikk sammen med siste rapporterte total fra LetsReg. Utvidbar målehistorikk viser mulige/tvetydige tidssammenfall med et valgbart vindu, uten å tilordne salg eller beregne sannsynlighetsprosenter. Valuta og betalings-/refusjonsbetydning for ordresummen er ikke avklart.

[Oppsett, API-grunnlag, beregningsregler og gjenstående verifikasjon](KLIKK-OG-LETSREG-UTVIKLING.md). Klikk lagrer nå også lokal konto-/arrangementsbinding i måledetaljene; denne sendes ikke til LetsReg eller GTM. Sammenligningen beregnes fra beholdte samtykkede klikk og forsvinner for en økt når dens måledata slettes. Selve arrangementshistorikken inneholder ingen besøks-ID og beholdes i 30 dager. Delte arrangementstall fordeles ikke på lokale kurs eller kategorier.

## Direkte påmelding fra kursoversikten

Oppdatert 20. september 2026: Kurskort har **Se kurset** og **Meld meg på** som separate knapper. Ukeskalenderen bruker tilsvarende tekstlenker. Leselenken beholder periode-/nivå-/dag-/visningsvalg. Påmeldingslenken går direkte til kursets godkjente LetsReg-adresse i ny fane, med `rel="noopener"`, kursnavn og opplysning om ny fane i tilgjengelig lenketekst. Venteliste merkes som venteliste; før salgsåpning, etter stenging, ved fullt/avlyst/avsluttet kurs eller manglende URL vises ingen aktiv påmeldingslenke. En lenke til hele perioden forklares også i oversikten.

Alle påmeldingslenkene bruker `data-rnl-letsreg` med samme lokale kurs-ID. Vanlig klikk, tastaturaktivering og Ctrl/Cmd-klikk følger eksisterende klikkmåling; midtklikk støttes også. Hendelsen er fortsatt `rnl_letsreg_click`, med eksisterende kampanje-/besøksdata og Complianz-styring. Høyreklikk og avbrutte klikk telles ikke. Direktelenken kan hoppe over kursdetaljen; det opprettes da ingen kunstig `course_view`. Klikk via nettleserens kontekstmeny kan ikke garanteres målt.

Hele den godkjente LetsReg-adressen, inkludert query-parametere og fragment, beholdes. Eksisterende UTM-, referanse- og eventuelle `_gl`-parametere fjernes ikke. Ingen JavaScript-redirect eller `preventDefault` overtar navigasjonen, og pluginen omskriver ikke lenken når den måler klikk. Dette lar et eksisterende GTM-oppsett dekorere lenkene. Nettleserens vanlige referrer-policy gjelder.

Dette aktiverer ikke ny automatisk overføring av besøks-ID, annonse-ID eller UTM til LetsReg. Lokal kampanjeattribusjon følger den samtykkede besøksøkten som før. Faktisk GTM/GA4-dekorering og gjenkjenning på LetsReg-siden må verifiseres separat; bekreftet salg forblir M5b.

## Complianz og datalagring

JavaScript bruker Complianz-API-et og samtykkehendelsene. Fra 0.1.15 samles kategoriendringer til neste oppgave i nettleseren før nye målehendelser, slik at Site Kit rekker å legge Googles oppdatering i datalaget. Tilbaketrekking stopper pågående måling straks; ny måling vurderes først mot den endelige tilstanden. Pluginen lytter også til WP Consent API og setter aldri Google-samtykket selv. En kategori må både være tillatt av `cmplz_has_consent` og ha eksplisitt «allow» gjennom Complianz' egen `cmplz_get_cookie`-funksjon. Cookieprefikser hardkodes ikke. API-et kontrolleres også på server for hver innsending. Dette følger grensesnittene i [Complianz' utviklerveiledning](https://complianz.io/help/wordpress/configuration/developers-guide-for-third-party-integrations/) og [JavaScript-kilden](https://github.com/complianz/complianz-gdpr/blob/master/cookiebanner/js/complianz.js).

Fra **0.1.22** erklærer pluginens hovedfil støtte med `wp_consent_api_registered_` + `plugin_basename(__FILE__)`, som beskrevet i [WP Consent API-dokumentasjonen](https://wordpress.org/plugins/wp-consent-api/). Erklæringen gjelder bare RegiNor og registreres også når innsamling er avslått. Den aktiverer ikke sporing og endrer ikke samtykkevalg.

Når `wp_has_consent` finnes, kreves også tillatelse derfra for hver kategori, både i nettleseren og ved mottak på serveren. Avslag på statistikk stopper måling; avslag på markedsføring hindrer annonse-ID-er. Eksisterende krav om Complianz og eksplisitt nettleservalg beholdes. Consent APIs standardtillatelse uten samtykkeløsning starter dermed ikke måling alene. Uten WP Consent API fortsetter eksisterende Complianz-integrasjon. Dette utvider ikke løsningen til andre samtykkebannere.

Tilbaketrekking via `wp_listen_for_consent_change` stanser sendinger og rydder besøksøkten. Sletting av egne data er fortsatt tillatt etter at begge API-er har trukket samtykket tilbake. RegiNor setter ikke Consent API- eller Google-samtykke selv.

Etter installasjon kan **Verktøy → Nettstedhelse** kontrolleres på nytt. RegiNor skal ikke lenger stå på listen over utvidelser uten erklært Consent API-støtte; samlevarselet kan fortsatt vises hvis andre utvidelser mangler erklæring. Dette er en kompatibilitetserklæring, ikke en full gjennomgang av nettstedets måleoppsett.

- **Statistikksamtykke:** en tilfeldig besøks-ID, avgrensede kampanjefelt, kurs-/side-ID og måletrinn. Ingen måling eller lesing av tidligere besøksdata før dette samtykket.
- **Markedsføringssamtykke i tillegg:** tillater `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`, `ttclid`, `ScCid` og `li_fat_id` fra første målte side. Hvis markedsføringssamtykke gis senere, etterfylles ikke en annen sides ID i den opprinnelige kampanjen; nye besøksøkter kan fange nye ID-er.
- `sessionStorage.rnl_journey_v1` holder data i fanen. Økten fornyes ved neste måling etter 30 minutters inaktivitet eller senest etter 24 timer. Ingen sporing mellom enheter eller fingeravtrykk. Hvis lagring blokkeres, brukes bare minne for den åpne siden.
- WordPress lagrer en saltet HMAC av besøks-ID-en i egen privat tabell, og serverens mottakstid. Ingen navn, e-post, WordPress-bruker-ID, IP eller fullstendige nettadresser lagres av denne funksjonen. Annonse-ID-er og besøks-ID er likevel pseudonyme opplysninger. Vanlige webserverlogger styres separat.
- Data eldre enn 30 dager slettes av en timeplanlagt WP-Cron-jobb og før rapportvisning. Faktisk slettetid avhenger av at cron kjører; staging/produksjon trenger fungerende cron. Slått av innsamling stopper ikke oppryddingen.
- Ved tilbaketrekking stoppes nye hendelser, pågående sendinger avbrytes, lokal økt slettes og sletting av dens WordPress-data forsøkes. En servermarkør i 24 timer avviser forsinkede hendelser for samme besøks-ID. Hvis slettingen ikke når serveren, gjelder ordinær 30-dagers opprydding. GA4-/annonseplattformers allerede mottatte data slettes ikke av dette endepunktet.
- Ved tilbaketrekking av markedsføring slettes den gamle målte økten. Hvis statistikk fortsatt er tillatt, starter en ny økt uten annonse-ID-er. Tidligere avsluttede økter kobles ikke sammen for å identifisere personen.

Offentlig POST-mottak er `reginor/v1/journey`. Det krever samme origin, JSON, egen header, maksimalt 8 KiB og serverkontrollert samtykke. Offentlig kurs-/påmeldingsstatus verifiseres før kurshendelser lagres. Et browser-endepunkt er ikke bevis for menneskelig aktivitet eller salg: tall kan påvirkes av automatiserte forespørsler. Hendelses-ID hindrer gjentatt lagring; inntil 200 hendelser per besøksøkt og 1000 totalt per minutt aksepteres. Rapport og aktivering krever `manage_options`; lagring av innstilling krever nonce.

## Oppsett i eksisterende GTM/GA4

Dette er en konkret konfigurasjon som kan prøves i GTM Preview. Den er **ikke publisert i brukerens container**, og GA4-måle-ID må hentes fra faktisk oppsett.

1. Bekreft at nettstedets eksisterende Google-tag bruker riktig GA4-strøm og at Complianz styrer dens samtykke. Behold én container/Google-tag. Se [Complianz' GTM-veiledning](https://complianz.io/definitive-guide-to-tag-manager-and-complianz/) og [Consent Mode-veiledning](https://complianz.io/simplified-guide-to-google-consent-mode-v2/).
2. Lag en **Custom Event**-utløser, med regex `^rnl_(landing|page_view|course_list|course_view|letsreg_click)$`.
3. Lag Data Layer Variables (versjon 2) for feltene nedenfor. Pluginen nullstiller `rnl` før hver hendelse slik at eldre nested-verdier ikke henger igjen.
4. Lag en GA4 Event-tag mot eksisterende Google-tag, hendelsesnavn `{{Event}}`, med parametrene fra tabellen og utløseren fra steg 2. La samtykkeinnstillingene følge det verifiserte Complianz-oppsettet.
5. Ikke legg `rnl_page_view` til som en ekstra standard `page_view`: eksisterende GA4 kan allerede telle sidevisninger. Bruk egne `rnl_*`-hendelser for denne kursreisen. Et LetsReg-klikk kan være en interessehendelse, men skal ikke hete `purchase` eller bekreftet påmelding.
6. Prøv avvisning, bare statistikk, statistikk + markedsføring og tilbaketrekking i GTM Preview/GA4 DebugView. Kontroller faktiske nettverkskall, cache og sider uten kurs. Aktiver pluginens innsamling under **Statistikk** i testmiljøet for prøven.

| Data Layer Variable | Foreslått GA4-parameter |
| --- | --- |
| `rnl.course_id` | `rnl_course_id` |
| `rnl.page_id` | `rnl_page_id` |
| `rnl.campaign.utm_source` | `rnl_campaign_source` |
| `rnl.campaign.utm_medium` | `rnl_campaign_medium` |
| `rnl.campaign.utm_campaign` | `rnl_campaign_name` |
| `rnl.campaign.utm_content` | `rnl_campaign_content` |

Disse egendefinerte parameterne er til eksplisitte RegiNor-rapporter; de endrer ikke automatisk GA4s standard trafikkattribusjon. Registrer bare nødvendige dimensjoner. Hele payloaden skal ikke sendes blindt videre: rå besøks-/hendelses-ID-er og annonse-ID-er skal ikke opprettes som egendefinerte GA4-dimensjoner. Det er ingen automatisk Google Ads Enhanced Conversions, Meta CAPI eller opplasting av kundedata.

## M5b: bekreftet påmelding og salg

**Status etter supportsvaret i sak #134895094, registrert 21. september 2026:** LetsReg oppgir at etterspurt arrangørspesifikk sporing ikke er tilgjengelig i dag. Besøkskobling til salg er satt på vent uten leveransedato. [Kilde, tolkning og vilkår for gjenopptakelse](M5B-KONTRAKTGJENNOMGANG.md#leverandørsvar-besøkskobling-satt-på-vent). M5a måler reisen frem til utgående LetsReg-klikk; ordreavstemming er fortsatt et separat, ikke implementert spor. Historikk over rapporterte arrangementstotaler og eksperimentell tidsvurdering er implementert lokalt som beskrevet over. Samlede kurskjøp kan ikke fordeles på kampanjer uten en verifisert kobling.

Oppdatert gjennomgang 20. september: [dokumenterte ordrefelt og gjenstående kontrakthull](M5B-KONTRAKTGJENNOMGANG.md).

**En webhook kan knytte sammen besøket og salget bare hvis LetsReg returnerer en referanse som vi først har kunnet overlevere.** Identitet eller markedsføringskilde kan ikke utledes sikkert fra ledige plasser. LetsReg oppgir [API-tilkobling og integrasjoner](https://www.letsreg.com/no/funksjoner/), men vi har ikke verifisert en kontrakt for overføring/retur av en egendefinert referanse, signatur eller kjøpshendelse for denne kontoen.

Foreslått flyt, foreløpig **ikke implementert/aktivert**:

```text
Annonse/UTM → samtykket besøksøkt → kurs → tilfeldig overleveringsreferanse
                                             ↓ dokumentert LetsReg-felt
                                        ordre/påmelding
                                             ↓ verifisert webhook/API
                             bekreftet status + samme referanse
                                             ↓
                         kampanje → kurs → bekreftet konvertering
```

Referansen skal være tilfeldig, kortlivet og opprettet på server. Serveren knytter den til en samtykket besøksøkt og korrekt kurs; selve URL-en skal ikke inneholde e-post, navn, Google-/Meta-ID eller intern deltakerprofil. Nåværende versjon endrer **ikke** LetsReg-lenken eller legger til udokumenterte parametere.

Før implementering må følgende avklares med faktiske API-/webhook-eksempler:

1. Tillater konto/produktet et egendefinert referansefelt i registreringen, og returneres nøyaktig samme verdi i ordre/påmelding og API/webhook? Overlevering må overleve eventuelle mellomsteg og betaling.
2. Hvilken autentisering/signatur, tidsgrense, leverings-ID og retry-policy dokumenterer LetsReg? Valider etter leverandørens kontrakt; ikke anta HMAC eller headernavn.
3. Hvilke hendelser betyr opprettet, reservert, fullført gratispåmelding, betalt, avlyst og refundert? Definer brutto/netto salg, antall deltakere versus ordre, valuta og beløp i minste valutaenhet.
4. Kan organisasjon, arrangement, produkt/priskategori og kurs kobles med stabile ID-er? Flere kurs kan dele arrangement; navn eller total kapasitet er utilstrekkelig kobling.
5. Hvordan behandles duplikater, hendelser i feil rekkefølge, refusjoner, bytte av kurs, utløpt referanse og tilbaketrukket samtykke? Konvertering må lagres idempotent per leverandørhendelse/ordre; ukjent referanse blir uten kampanjekobling.
6. Kreves verifiserende API-oppslag etter webhook, og hva er kvotene? Behold bare nødvendig referanse og konverteringsstatus; LetsReg eier deltakerregisteret.

En fremtidig M5-webhook-transport kan deles **etter** verifisert signatur-/kontovalidering; slik transport er ennå ikke implementert. Kapasitetsoppdatering og konverteringsbehandling er to forskjellige jobber. Et kapasitetsvarsel er ikke et salg. En retur til «takk»-side i nettleseren er heller ikke betalingsbevis. Ikke forsøk kobling via IP, fingeravtrykk eller navn/e-post.

GA4 cross-domain er bare en betinget fremtidig mulighet, ikke et bekreftet alternativ etter supportsvaret. Det forutsetter at samme måleoppsett kan brukes hos begge parter og linkerdata bevares. Det må bekreftes med LetsReg; det gir ikke i seg selv WordPress en bekreftet webhook-konvertering. Se [Googles cross-domain-veiledning](https://support.google.com/analytics/answer/10071811?hl=en).

## Lokale testdata

```bash
npm run demo:courses
npm run test:demo
```

Krever lokalt wp-env. Scriptet oppretter data eksplisitt; pluginaktivering lager ingen kurs. To seksukersperioder følger hverandre uten mellomrom, den første fra mandagen i opprettelsesuken. **12 kurs per periode:** to saler × mandag/tirsdag × 18:15, 19:20, 20:25. Normalt varer hvert kurs én time. Bachata i sal B tirsdag er unntaket: **20:55–22:25**, altså 30 minutter senere start og 30 minutter lengre varighet. Det gir en forskjøvet og lengre kalenderblokk uten romkollisjon. Nybegynnerkurset i sal B på mandag starter én uke senere og har fem kvelder; de øvrige har seks. Totalt 24 kurs og 142 faktiske økter.

Idempotent markør gjør at ny kjøring returnerer samme objekter. Første demoversjon med én sal oppgraderes ved å legge til sal B i egne demoperioder. Versjon 3 oppdaterer det siste tirsdagskurset i sal B med tidsforskyvningen, uten å lage nye objekter. Eksisterende kursside gjenbrukes hvis den er konfigurert. Egne «Demo –»-navn og ingen reelle påmeldingslenker. Ingen gamle kurs eller ACF-data endres. «Senere oppstart» beregnes fra faktisk første økt sammenlignet med periodens første mulige kursdag, og vises også i mobilens dagliste. Saloverskriftene har større, fet skrift og tydelig bakgrunn/kant.

Opprettet lokalt 18. september: **14. september–25. oktober 2026**, deretter **26. oktober–6. desember 2026**. Forskjøvet oppstart er **21. september** og **2. november**. Startdatoene flyttes ikke automatisk når scriptet kjøres igjen senere.

## Verifikasjon og gjenstående prøve

Automatiske prøver omfatter domenekontrakt, samtykkestyrt JavaScript, WordPress-mottak/lagring/rapport, tilbaketrekking, duplikater, ugyldig innhold, tilgang og opprydding. WordPress-prøven bruker en **Complianz-API-stub**, ikke faktisk Complianz/GTM/GA4. Testdataenes sal-/dagfordeling, perioder, økter og kalenderens oppstartsmerking kontrolleres separat. Se testkommandoer i README.

Kontrollert lokalt med WordPress 7.1 / PHP 8.2 og domenetester på lokal PHP 8.5:

| Kontroll | Resultat |
| --- | --- |
| `composer check` | 130 tester / 239 assertions; syntakskontroll bestått |
| `test:journey` | 25 samtykke-/runtimekontroller |
| `test:analytics` | 27 WordPress-kontroller med Complianz-stub |
| `test:demo` | 212 kontroller; 24 kurs / 142 økter |
| WordPress bootstrap / storage / admin / workflow | Bestått; henholdsvis røykprøve / 58 / 90 / 73 kontroller |
| HTTP / public / public-http / interface | 31 / 37 / 37 / 72 kontroller |
| POT / ZIP | Generert; 56 runtimefiler i pakken |

CI er utvidet for måling og opprettelse/kontroll av demo i WordPress 6.8/7.1. CI er ikke kjørt på GitHub i denne leveransen. Lokal demomarkør bevarer samme ID-er ved gjentatt kjøring.

Gjenstår før lansering: faktisk Complianz/GTM/GA4-oppsett, fungerende cron, Avada/cache, mobil/zoom/tastatur og visuell brukertest. Verifisert LetsReg-overlevering og konverteringskontrakt gjenstår som M5b. Ingen produksjonsinnsamling, GTM-publisering eller ekte kjøp er utført.
