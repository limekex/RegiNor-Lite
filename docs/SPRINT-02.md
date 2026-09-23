# Sprint 2 – privat WordPress-lagring og kontrollert omplanlegging

Gjennomført lokalt 17. september 2026. RegiNor brukes fra neste nye kursperiode. Ingen gamle kurs eller ACF-data er importert eller endret.

## Leveranse

| ID | Arbeidspakke | Status |
| --- | --- | --- |
| S2-01 | Separate innholdstyper for kursbeskrivelse, periode, gruppe, sted og sal | Levert |
| S2-02 | Registrert metadataskjema, sanitering, referansevalidering og egne capabilities | Levert |
| S2-03 | Repository for kladder; kopiering av periodestandarder ved gruppeopprettelse | Levert |
| S2-04 | Stabile økt-/oppholds-ID-er og eksplisitt forhåndsvisning før gruppeendringer | Levert |
| S2-05 | Bevaring av flyttede, avlyste og påbegynte økter; uløste omplanleggingsavvik blokkerer bekreftelse | Levert |
| S2-06 | Atomisk versjonskontroll med full økthistorikk og registrert endringsaktør | Levert |
| S2-07 | Ressurskonflikter på tvers av lagrede perioder og faktiske periodegrenser | Levert |
| S2-08 | WordPress-integrasjonstester og utvidede domenetester | Se testbevis |

Kurseditor og publisering er ikke åpnet ennå. Lagringstjenesten kan opprette og endre kladder gjennom et internt PHP-grensesnitt. Ingen offentlig kursvisning, generisk CPT-editor eller REST-skriverute er aktivert. Dette gjør det mulig å ferdigstille publiseringsvalidering og kursansvarligrolle i sprint 3 før de nye dataene tas i bruk offentlig.

## Hva som er bygget

`Infrastructure/ContentTypes` registrerer fem separate `rnl_`-typer og metadata. En kollisjon med et allerede registrert typenavn stanser RegiNors registrering og gir administrator et varsel; eksisterende type overtas ikke. Kartlegging mot faktisk Avada/TEC-installasjon gjenstår.

`CourseRepository` kontrollerer capabilities med objekt-ID, validerer data og relasjoner, kopierer standardverdier ved gruppeopprettelse og lagrer samlet tilstand. `StateSchema` beskriver lagringsformatet og avviser ukjente felt, feil referanser, ugyldige tidspunkt og prisverdier. Kursbeskrivelser lagres foreløpig som ren tekst.

`ScheduleReplanner` lager en differanse. Uendrede opprinnelige datoer beholder ID. Manuelt flyttede/avlyste og allerede påbegynte økter beholdes eksakt. Nye opphold som treffer flyttede økter, eller et avvikende antall undervisningskvelder etter bevaring, krever avklaring. Bekreftelse sjekker signatur, bruker, gruppe-/periodeversjon og at berørte økter fortsatt ligger i fremtiden.

Kollisjoner kan finnes i et utkast og vises både i forhåndsvisningen og lagringsresultatet. De skal blokkere publisering i sprint 3. Ingen offentlig publiseringsgaranti kan utledes fra at en kladd lar seg lagre.

## Kontrakt og begrensninger

Se [datakontrakten](DATAKONTRAKT.md) for felter, metoder og invariants.

- Administrator får egne RegiNor-capabilities gjennom versjonert oppsett. Kursansvarligrollen og begrensede profiloppslag leveres i sprint 3.
- Instruktørtyper må konfigureres eksplisitt gjennom `rnl_instructor_post_types` etter kartlegging. Standard er ingen tillatte typer. Ingen instruktørprofiler dupliseres.
- Innbygget posttittel er en intern opprettelsesetikett. Den autoritative tittelen er i `rnl_state.data`; kommende editor og renderer må lese repository.
- Historikk og nåtilstand lagres i samme metaverdi. Slik er én objektendring atomisk; ingen database-transaksjon på tvers av hele perioden er etablert. Publisering på tvers av grupper trenger en egen samtidighetskontroll i sprint 3.
- Alle endringer av periodedata merker gruppenes opprinnelige periodeversjon som foreldet. `periodReview()` viser behov for ny gjennomgang; dette er konservativt og kan også varsle ved bare en tittelendring. Ingen gruppe endres automatisk.
- Avlysning kan lagres uten erstatningskveld. Automatisk regenerering som gir feil antall blokkeres. Egen arbeidsflyt for å velge erstatningskveld og gjenopprette historikk kommer i kurseditoren.
- Metadatahistorikken har en grense på 2 MiB per objekt. Når den nås, avvises ny lagring med forståelig feil; historikk slettes ikke lydløst. Vurder separat historikklagring hvis faktisk volum krever det.
- Signert forhåndsvisning er intern bekreftelse av et konkret forslag. Når HTTP-/REST-ruter innføres må de også kontrollere nonce, capabilities, objektbinding og publiseringsregler.

## Testbevis – 17. september 2026

| Kontroll | Resultat |
| --- | --- |
| `composer check`, PHP 8.5.7 | Bestått: Composer-validering, syntakskontroll av 29 PHP-filer og 66 PHPUnit-tester / 118 assertions. Lokal Composer 2.5.8 gir egne deprecation-varsler. |
| PHPUnit, PHP 8.2.29 i Docker | Bestått: 66 tester / 118 assertions. |
| `npm run test:storage`, WordPress 6.8 og 7.1 / PHP 8.2 | Bestått: 58 kontroller i hvert miljø. Testobjektene er ryddet bort. |
| WordPress-smoketest, begge versjoner | Bestått: plugin lastes, administrator ser status, anonym direkte tilgang avvises. |
| `npm run package` | Bestått: ZIP med 21 runtime-filer, uten tester eller utviklingsavhengigheter. |

Lagringstesten dekker blant annet standardkopiering, streng schema-/referansevalidering, signaturmanipulering, foreldede forslag, samtidige versjonsendringer, bevaring av økt-ID-er, sal-/instruktørkonflikter på tvers av perioder, rettigheter, avlyste økters periodegrenser og at annet eksisterende innhold er uendret. Publiserte testobjekter settes opp direkte i den isolerte testen for å prøve lesemodellen; dette er ikke en ferdig publiseringsfunksjon.

GitHub CI er konfigurert, men ikke kjørt fra dette arbeidsområdet. Faktisk Avada/ACF/TEC-installasjon, kursansvarligrollen, kurseditor og offentlige kanaler er ikke testet. Disse gjenstår i senere leveranser.

## Neste sprint

Sprint 3 bygger kursarbeidsflaten: opprett neste nye periode, velg beskrivelser/saler/instruktører, rediger grupper og opphold, se faktiske datoer/differanser, og publiser etter komplett kontroll. Rollen kursansvarlig og serverkontroller på alle nye skriveveier inngår. Avada/TEC-innbygging og offentlige kursflater følger i sprint 4.
