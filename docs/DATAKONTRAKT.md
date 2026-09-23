# Datakontrakt – sprint 4

Dette er den implementerte, private lagringen for **nye** RegiNor-kurs. Ingen ACF-import inngår. Feltdefinisjoner og full JSON Schema ligger i `Infrastructure/StateSchema.php`; dette dokumentet forklarer hvordan de skal brukes.

## Objekter og eierskap

| Type | WordPress CPT | Innhold |
| --- | --- | --- |
| Kursbeskrivelse | `rnl_course` | Tittel, ren tekstbeskrivelse, nivåforklaring, dansestil og partnerinformasjon |
| Kursperiode | `rnl_period` | Navn, tidssone, foreslått start, standard antall kvelder/sal/pris, synlighets-/påmeldingsvinduer, kommendevalg, avlysning og felles opphold |
| Kursgruppe | `rnl_group` | Beskrivelse-/periodereferanse, kopierte standarder, ukeplan, prisvilkår, LetsReg-lenke/status, gruppeopphold og faktiske økter |
| Kurssted | `rnl_venue` | Navn og adresse |
| Sal | `rnl_room` | Navn og referanse til ett RegiNor-kurssted |
| Kursnivå | `rnl_level` | Navn, valgfri forklaring, sorteringsrekkefølge og tilgjengelighet for nye valg. Valgfri `level_id` på enkeltkurset; 0/utelatt betyr uten nivå. |

Oppfølging 20. september 2026: [nivåregisteret](KURSNIVAER.md) erstatter det gamle `audience`-valget i UI/filter. Det bruker samme versjonerte lagring og avgrensede ressursoppslag. Administrator-capabilities er oppgradert til versjon 3. Historiske beskrivelser beholder eventuelle `audience`-verdier uten at frontend bruker dem.

Sted og sal er egne private oppslagsposter. Ingen eksisterende stedstaksonomi eller TEC-post overtas. En senere bekreftet TEC-adapter kan referere til eksterne steder; den er ikke implementert her. Instruktører refereres med eksisterende WordPress-ID-er fra en eksplisitt tillatt profiltype.

Alle objekter opprettes som `draft`. CPT-ene har `public=false`, `publicly_queryable=false`, `show_ui=false`, `show_in_rest=false`, ingen rewrite og ingen offentlige arkiver. Nye kursdata blir ikke publisert av aktivering eller lagring. Sprint 3 kan sette perioden og gruppene til `publish` gjennom en kontrollert operasjon, men de private CPT-innstillingene endres ikke. Fra sprint 4 bruker offentlig renderer felles publiseringspolicy for RegiNor-innhold. Rå objekter er fortsatt utilgjengelige gjennom generiske CPT-/REST-ruter.

## Tilstand, revisjon og historikk

Hvert objekt har nøyaktig én registrert objektmetaverdi, `rnl_state`:

```text
version      positivt heltall; 1 ved opprettelse
data         validert og normalisert objektdata
actor_id     WordPress-bruker som gjorde endringen
changed_at   UTC på formen YYYY-MM-DDTHH:mm:ssZ
history      tidligere snapshots, uten rekursiv historikk
event        valgfri hendelsestype fra sprint 3: lagring, publisering eller statusendring
```

En oppdatering krever forventet versjon og bruker hele forrige metaverdi som compare-and-swap-betingelse. Ny tilstand og historikksnapshot skrives som én verdi. Avvik gir `VersionConflict` med kode 409. Dette erstatter antakelser om at WordPress' vanlige postrevisjoner automatisk dekker metadata.

Metadata har et eksplisitt REST-skjema med `context=edit`, men CPT-ene har ingen REST-ruter. Direkte metadataredigering gjennom WordPress-rettighetskontroll avvises; interne tjenester må bruke repository. Betrodd PHP-kode med database-/WordPress-API-tilgang kan teknisk omgå repository, så nye endepunkter skal aldri sende rå metaskriving videre.

## Datoer, beløp og referanser

- Lokale datoer: `YYYY-MM-DD`; lokale tider: `HH:mm`; normalt `Europe/Oslo`. UTC lagres eksplisitt og må stemme med datoens faktiske offset. Tvetydig/manglende DST-tid avvises.
- Synlighet og påmelding har hver sine nullable UTC-grenser i kladd. Når begge er angitt må slutt være etter start. Publisering krever begge vinduer og eksplisitt bekreftelse. Skjemaene viser lokal tid og avviser uklare eller manglende DST-tidspunkt.
- Beløp: heltall i øre; gruppevaluta `NOK`; prisgrunnlag `person` eller `pair`. Andre prisgrunnlag må utvides eksplisitt før de kan brukes. Presentasjon må forklare grunnlaget og eventuelle tillegg.
- Gruppens `room_id=0` og tom instruktørliste tillates i kladd. Publisering krever sal og instruktører på alle faktiske ikke-avlyste økter. Salreferanser må peke på en RegiNor-sal med et gyldig kurssted.
- Instruktør-ID-er må peke på publiserte profiler av konfigurerte typer. Ingen navn-/tekstgjetting. Administrator velger tillatte typer i `rnl_instructor_types`; filteret `rnl_instructor_post_types` kan tilpasse listen. Uten konfigurasjon er listen tom. Oppslaget returnerer bare ID og navn.
- Påmeldingslenker i kladd må være HTTPS uten innloggingsopplysninger når satt. Publisering kontrollerer eksakt vert mot `rnl_registration_hosts`, som har `letsreg.com` og `www.letsreg.com` som standard. Riktig arrangement/gruppe bekreftes redaksjonelt; automatisk leverandørverifisering er ikke implementert. Ingen kapasitetstall lagres her.

## Økter og opphold

Økter lagres som `data.sessions` i gruppen, ikke som separate innlegg. Hver økt har UUID, opprinnelig dato, faktisk lokal dato/tid, tidssone, beregnet start/slutt i UTC, status (`scheduled`, `moved`, `cancelled`), forklaring og faktisk sal-/instruktørfordeling. Identitet og opprinnelig dato kan ikke endres ved en manuell flytting. Flytting/avlysning krever forklaring. Påbegynte økter er låst for vanlig fremtidig omplanlegging.

Opphold lagres på perioden eller gruppen som objekter med UUID, `from`, `until` og forklaring. Begge datoer er inklusive. ID-er er unike innen sitt objekt. Periodens opphold og gruppens opphold behandles samlet ved planlegging.

Et kalenderforslag fra sprint 1 er ikke en lagret økt. `ScheduleReplanner` knytter forslaget til eksisterende identiteter og produserer før/etter-differansen. Fjernede fremtidige ordinære økter finnes fortsatt i objektets historikk. Manuelt avlyste/flyttede og påbegynte økter fjernes ikke av vanlig regenerering.

## Repository-grensesnitt

| Metode | Betydning |
| --- | --- |
| `create(kind, data)` | Opprett kladd av beskrivelse, periode, sted eller sal. Krever typebestemt opprettelsesrettighet. |
| `createGroup(periodId, courseId, fields)` | Opprett gruppe som kladd. Kopier periodestandarder én gang; uke-/tidsfelt gis eksplisitt. Ingen økter lagres før bekreftet plan. |
| `newGroupDefaults(periodId)` | Felles lokale standardverdier for ny kursopprettelse og import; krever redigerbar periode i kladd. |
| `previewImport(periodId, version, courseId, fields, mapping)` | Valider nytt kurs med kontrollert LetsReg-kobling, undervisningsgrenser og duplikatvern; signer øktplan og ressursversjoner uten skriving. |
| `confirmImport(proposal)` | Administrator bekrefter signert importforslag innen 15 minutter. Kontroller kilde, versjoner og duplikater på nytt; opprett kurs, økter og kobling samlet som kladd i én transaksjon. |
| `confirmImportBatch(periodId, proposals)` | Opprett 1–20 signerte kursforslag samlet. Hele listen, inkludert nye beskrivelser, rulles tilbake ved feil. |
| `get(id, expectedKind)` | Les autorisert tilstand og historikk; avvis feil type, slettet objekt eller tvetydig metaverdi. |
| `update(id, expectedVersion, data)` | Endre en annen kladdtype enn gruppe. Bevarer full historikk. |
| `previewGroup(id, expectedVersion, changes)` | Valider gruppeendring og beregn plan, differanse, uavklarte problemer, ressurskonflikter og vindusvarsler. Ingen skriving. |
| `previewSessionChange(id, version, sessionId, changes)` | Foreslå flytting/avlysning med stabil ID og kontrollert objektbinding. |
| `confirm(proposal)` | Kontroller eksakt signert forslag, bruker, objekt-/periodeversjon, referanser og fremtidige tidspunkter på nytt. Lagre kladd atomisk. |
| `groups(periodId)` | Autorisert intern planleggingsliste; må aldri brukes direkte som offentlig kursliste. |
| `conflicts()` | Konflikter mellom faktiske økter i lagrede perioder. Avlyste grupper/perioder/økter og objekter i papirkurv utelates. |
| `periodModel(id)` | Domenemodell med første/siste faktiske ikke-avlyste økt fra publiserte grupper. Brukes av publiserings-/salgsstatuspolicyen; offentlig renderer bruker de samme domenereglene gjennom sin avgrensede `Catalog`-projeksjon. |
| `previewReplacement(id, version, cancelledId, date, reason)` | Ny beskyttet erstatningsøkt, uten å fjerne den avlyste. Bekreftes med `confirm`. |
| `previewReview(id, version)` | Gjennomgå faktiske lagrede datoer etter periodeendring uten å regenerere; oppholdskonflikter må løses. |
| `resource(id, kind)`, `instructors()` | Begrensede oppslag for valg av beskrivelser, steder, saler og eksisterende profiler. Ingen historikk/private profilmetadata. |
| `previewPublication(id, version, windows, outside, lateSales)` | Full periodekontroll og signert forslag med eksplisitte bekreftelser, gruppeversjoner og relevante oppslagsdata. |
| `publish(proposal)` | Ny kontroll under global skriverlås; status og historikk oppdateres samlet i transaksjon. |
| `lifecycle(id, version, groupVersions, target)` | Avpubliser, legg kladd i papirkurv eller gjenopprett, med kontroll av hele versjonssettet. |
| `groupLifecycle(id, version, periodVersion, restore)` | Flytt enkeltgruppe til/fra papirkurv i kladdperiode. Tidligere publisering hindrer sletting av kursansvarlig. |
| `copyPeriod(id, version, title, startDate)` | Kopier til ny kladd med nye datoer/ID-er, uten gamle vinduer, lenker, opphold eller særflyttinger. |
| `salesStatus(groupId)` | Effektiv status fra synlighet, avlysning, eget salgsvindu og redaksjonell status. Ingen kapasitetsgjetting. |
| `periodReview(id)` | Grupper med foreldet periodeversjon og økter utenfor synlighetsvinduet. Ingen automatisk endring. |

Rå gruppetilhørighet og øktliste kan ikke sendes inn som en vanlig gruppeoppdatering. `confirm` godtar bare uendrede forslag fra samme bruker. Gruppe- og periodeversjoner avvises ved avvik, og ressurskonflikter beregnes på nytt før lagring.

## Skrivegrenser fra sprint 3

Vanlige redigeringsmetoder endrer fortsatt bare kladder. Bruk eksplisitt avpublisering av hele perioden før korrigering, og publiser igjen etter ny kontroll. Kopiering og statusendringer kjører som transaksjoner på InnoDB; en global MySQL/MariaDB-navnelås serialiserer alle repository-skrivere, også på tvers av perioder. Aktør og hendelsestype ligger i samme versjonerte snapshot som dataene. Papirkurven skiller enkeltgrupper fra grupper som ble slettet sammen med perioden, slik at gjenoppretting ikke gjenoppliver tidligere slettede grupper.

Kursansvarlig har egne periode-/gruppe-capabilities og `rnl_select_resources`. Rollen kan ikke lese gjenbrukbare objekters komplette repository-snapshot, endre ressursene, råskrive metadata eller bruke generisk WordPress-skriving/permanent sletting. Administrator beholder sine vanlige rettigheter. HTTP-skjemaene bruker `CourseActions` med nonce og serverkontroll; ingen egne REST-/AJAX-skriveruter er innført.

## Gjenstår før offentlig bruk

Avada/TEC/SEO/cache-staging og visuell/menneskelig/tilgjengelighetsmessig kvalitetssikring. Se [sprint 4](SPRINT-04.md) for offentlig leveranse, driftspremisser og testbevis.

## Offentlig projeksjon og nye valg fra sprint 4

Kursbeskrivelse kan ha `audience = mixed | beginner | experienced`. Periode kan ha `default_view = list | week`. Feltene er valgfrie i lagrede snapshots fra tidligere sprinter; standard er henholdsvis `mixed` og `list`. Ingen data migreres eller gjettes fra gamle titler. Nye skjemaer lagrer eksplisitte valg.

`Catalog::read()` returnerer bare synlige perioder/grupper, faktiske økter, offentlige ressursnavn/adresser, pris, effektiv påmeldingsstatus og aktuelle valg. Det returnerer ikke repository-tilstand/historikk/aktør-ID-er. En påmeldingslenke fjernes fra projeksjonen når handlingen ikke er tilgjengelig. Samme projeksjon brukes i liste, detalj, kalender, schema og sitemap. Innhold er ikke avhengig av JavaScript; bare fokus og utløp i allerede åpne faner forbedres med skript.

## Separat kapasitetslagring – sprint 5

Kapasitetsdemonstrasjonen bruker privat metadata `_rnl_capacity` på gruppen, utenfor `rnl_state`. Dermed endrer automatiske kontroller ikke publiseringsversjon eller redaksjonell historikk. `CapacityStore` håndhever objekt-/administratorrettigheter, versjon ved konfigurasjon, lås, atomisk oppdatering og ventetid. Ingen generisk REST-/metadataflate er åpnet.

Data består av versjon, demoscenario, konfigurasjonsaktør/-tid, siste komplette normaliserte observasjon, separate forsøks-/suksesstider, feilkode/feilantall og neste tillatte/bestilte kontroll. Snapshot-kontrakten tillater bare `complete`, `captured_at`, `expires_at`, `available`, `roles` og `source_version`. Tidspunkter er UTC Unix-sekunder. Antall er heltall eller null; ukjent omtolkes aldri til fullt. Kapabiliteter for ekte kilde, eksakte offentlige antall, webhooks og verifiserte pooler er ikke aktivert.

Kursansvarlig bestiller kontroll av et eksisterende oppsett; administrator kan konfigurere eller stoppe demonstrasjonen. Live-eksterne ID-er/hemmeligheter lagres ikke. Ved periodekopiering opprettes nye grupper uten denne metadataraden. Se [sprint 5](SPRINT-05.md).

## Valgfri undervisningsgrense (skjemaoppfølging etter sprint 5)

`period.end_date` er valgfri/nullable lokal `YYYY-MM-DD`, inklusive, og må være på eller etter `start_date`. Eldre metadata og historikk uten feltet forblir gyldig. Opphold valideres mot periodens start og eventuell slutt. Omplanlegging bruker den strengeste grensen av `period.end_date` og `group.latest_date`; manuelle økter og publisering kontrolleres også. Avlyste økter kan beholdes utenfor grensen som historikk. Kopiering nullstiller `end_date`. Offentlige datoer og periodeoversikten bruker fortsatt faktiske økter; ingen synlighets-/salgsgrense brukes som undervisningsslutt.


### Valgfrie kursfelt for fra-pris og påmeldingsdatoer

`price_from` er en valgfri boolsk verdi (standard false); `price_minor` er fortsatt heltall i øre og `price_basis` angir person/par. `registration_from` og `registration_until` er valgfrie UTC-tidspunkt (`Y-m-dTH:i:sZ`) eller null. Begge tidspunkt må være gyldige, og slutt må være etter start når begge er oppgitt. Den effektive påmeldingsperioden er snittet med kursperiodens salgsdatoer; manglende periodevindu åpnes ikke av kursdatoene. Datoene nullstilles ved kopiering.

Internt `start_date` beholdes for kalenderkontrakten. Kurseditoren avleder dette fra gjeldende periode; bare `first_date` brukes som synlig, valgfritt avvik. Ingen datamigrering er nødvendig. LetsReg-forslag er separat fra koblingsmodellen og blir vanlige lokale felt først når bruker velger å bruke og lagre dem.

## Beskrivelser ved LetsReg-import

Importforslag kan ha en ny, validert kursbeskrivelse og midlertidig `course_id=0`. Dette er kun et privat, signert forslag i nettleseren; det lagrede skjemaet krever fortsatt en reell positiv beskrivelses-ID. Ved bekreftelse opprettes beskrivelsen som kladd først, og kurset valideres med den nye ID-en før samlet commit. Ingen forhåndsvisning skriver midlertidige poster. Eksisterende beskrivelser refereres med ID/versjon og oppdateres ikke av import. Importkilden er signert, brukerbundet og kortlivet; den er ikke en permanent kapasitetstilstand.


## M4.1 – privat nettidentitet

`_rnl_web` på kurs/periode lagrer en separat presentasjonsversjon: `version`, `languages` (slug, reserverte aliaser, delingstittel/-beskrivelse, bilde-ID), og de siste 50 endringsoppføringene med aktør/tidspunkt. Driftstilstand og undervisning forblir i `rnl_state`. Nettvalg krever objektets redigeringsrettighet og samme globale skrivelås; admin-POST krever nonce og forventet nettversjon. Ingen generisk REST-eksponering. Språkvariantene deler alltid samme drifts-ID.

Slugs er unike per periode for kurs og globalt for perioder; historiske aliaser reserveres også. Offentlig oppslag leser bare identiteter for objekter som finnes i den offentlige katalogen. Kopiering oppretter nye identiteter. Felles bilde (`rnl_sharing_image`) og adressebryter (`rnl_pretty_urls`) er administratorvalg. Se [M4.1](PERMALENKER-OG-METADATA.md).
