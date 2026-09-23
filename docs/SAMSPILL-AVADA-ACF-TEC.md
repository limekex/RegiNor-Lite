# Samspill fra neste nye kursperiode

Oppdatert 17. september 2026 etter prosjekteiers beslutning: **RegiNor Lite brukes fra neste nye kursperiode. Ingen gamle kurs eller ACF-data migreres.** Tidligere planer om ACF-import, feltkonvertering og overtakelse av gamle registreringer er erstattet av denne planen.

## Ansvarsdeling

| Del | Ansvar ved innføring |
| --- | --- |
| RegiNor Lite | Nye kursperioder, grupper, faktiske økter, opphold, validering, kursadministrasjon og offentlig kursvisning. Egen lagring uten ACF-avhengighet. |
| Avada | Nettstedets ramme, navigasjon, typografi og sidemaler; RegiNor bygges inn i eksisterende side. |
| The Events Calendar | Eksisterende arrangementer og kalenderflater. Eventuell kobling til nye kurs avklares separat. |
| ACF | Eksisterende kurs/annet innhold kan bestå. Ingen import eller automatisk sletting. Nye RegiNor-kurs bruker ikke ACF som datakilde eller editor. |
| LetsReg | Påmelding, betaling og verifisert kapasitet. |

Ny kursperiode opprettes fra bunnen av. Gjenbruk av en eksisterende instruktør eller et sted er en eksplisitt referanse, ikke import av gammel kursstruktur. Eksakte profil-/stedsgrensesnitt må kartlegges. RegiNor overtar ikke gamle CPT-navn eller globale ACF-registreringer; egne navn og ruter må unngå kollisjoner.

## Avada

Avada dokumenterer betingede layouts og Single Event Layouts for TEC fra Avada 7.11.10. Dette gir mulige innbyggingspunkter, men installert versjon og layoutbetingelser er fortsatt uverifisert. [Conditional Layouts](https://avada.com/documentation/understanding-conditional-layouts), [Single Event Layout](https://classic.avada.com/documentation/how-to-create-a-single-event-layout-section-in-avada/).

Shortcode `[reginor_courses]` og dynamisk blokk med felles PHP-renderer er implementert i sprint 4. Avadas [Text Block-element](https://avada.com/element/text-block/) dokumenterer støtte for shortcodes; dette er anbefalt prøvepunkt i staging. Administrator velger eksisterende kursside under RegiNor Lite → Nettsidevisning. Kursinnhold og påmelding fungerer uten JavaScript. Avada styrer ramme og profil; RegiNor styrer kontrollert kursinnhold, synlighet og status. Stiler avgrenses til komponenten. Ingen globale endringer av alle knapper, overskrifter eller containere.

Når kursinngangen byttes til RegiNor skal gamle kurssider fortsatt finnes på sine eksisterende URL-er. Gamle ACF-bindinger trenger ikke konverteres til nye felt. Den nye kursflaten bygges med RegiNors renderer; gamle maler kan beholdes for historisk innhold og tilbakeføring.

## ACF og eksisterende innhold

Ingen kursimport, ACF-feltmapping, toveis synkronisering eller oppdatering av gamle kurs skal bygges. ACF kan fortsatt være aktivt for historiske kurs eller øvrig innhold. Kompatibilitetskartlegging skal avdekke navnekollisjoner og delte profilreferanser, ikke bli en full importanalyse.

Nye RegiNor-kurs skal kunne opprettes, redigeres, publiseres og leses med ACF deaktivert i isolert testmiljø. Hvis delte profiler i dag registreres via ACF, avklares en ACF-uavhengig referansekontrakt eller relevant separat registrering; ingen duplisering eller ombygging skjer automatisk.

Det lesende strukturverktøyet kan fortsatt vise ACF-definisjoner for å oppdage avhengigheter. At verktøyet finnes betyr ikke at import er nødvendig eller bestilt.

## The Events Calendar

Oppfølging 18. september 2026: prosjekteier ønsker vurdert kursperioder i TEC-kalenderen. Se [konkret forslag og neste integrasjonssteg](TEC-KURSPERIODER.md). Énveis kobling med én heldagsoppføring gjennom hele perioden er implementert og testes mot lokal TEC 6.17.5.

TEC skiller mellom arrangement, forekomst og Series. Nyere lagring kan bruke provisoriske forekomst-ID-er; bruk støttet API/ORM og tydelig mapping dersom en kobling innføres. Ikke skriv direkte til private TEC-tabeller. [Events, occurrences and series](https://docs.theeventscalendar.com/apis/custom-tables/events/), [Events ORM](https://docs.theeventscalendar.com/apis/orm/query/events/).

Gjentakelser og Series er dokumentert i Events Calendar Pro; installert utgave er ukjent. RegiNor-kursperioden med flere grupper skal ikke automatisk likestilles med en Series. [Recurring events](https://dev.theeventscalendar.com/knowledgebase/creating-a-recurring-event-2/).

Standard plan er sameksistens: nye kurs ligger i RegiNor, øvrige arrangementer fortsetter i TEC. Dersom nye kurs også skal vises i TEC, vurderes en avgrenset énveis kalenderrepresentasjon fra RegiNor som eget behov. Det skal fortsatt være én eier av kurstidene. Prosjekteiers avklaring 18. september 2026 velger én kalenderrepresentasjon gjennom hele perioden. Den merkes som kursperiode, forklarer faste kursdager og lenker til alle kursene. Dette er ikke kontinuerlig undervisning eller separat kjøpbare billetter for hver kveld.

Eksisterende TEC-steder kan refereres når de er kartlagt; sal trenger egen stabil ID og stedstilhørighet. Arrangør er ikke automatisk det samme som instruktør. Offentlig TEC-kobling krever at synlighetsvinduer, REST, feeds/ICS, Avada Events-elementer, cache, canonical og schema følger samme kursregler. Globale filtre skal ikke påvirke andre arrangementer.

## Integrasjonsprøver

| ID | Bestått når |
| --- | --- |
| I1 | Ny kursliste/detalj fungerer i faktisk Avada-layout uten å endre global styling, mobilmeny eller øvrige arrangementer. |
| I2 | Gamle ACF-kursposter, felt, registreringer og URL-er er uendret ved installasjon og bruk av RegiNor. |
| I3 | Nye CPT-/metadata-/URL-navn kolliderer ikke med eksisterende ACF/TEC/temaregistreringer. |
| I4 | Nye kurstider har én redigerbar kilde i RegiNor og samme validering i alle tillatte skriveveier. |
| I5 | Kursansvarlig får avgrenset tilgang til nye kurs og nødvendige oppslag, uten generelle ACF-/TEC-/redaktørrettigheter. |
| I6 | Skjulte nye kurs er skjult i alle kanaler der RegiNor-innhold vises, inkludert eventuelle Avada-/TEC-koblinger. |
| I7 | Ny detaljside har korrekt canonical/schema uten motstridende hendelser fra øvrige plugins. |
| I8 | Eventuelle referanser til instruktør, sted og TEC-forekomst er eksplisitte og stabile. Ingen innholdsduplisering kreves for oppstart. |
| I9 | Ingen kapasitetstall kommer fra gamle ACF-verdier eller TEC/Event Tickets; LetsReg-adapteren er autoritativ. |
| I10 | Avada/cache/CDN respekterer statusgrenser; kurs og påmelding fungerer uten JavaScript. |
| I11 | Hele den nye kursflyten fungerer med ACF deaktivert i isolert testmiljø. |
| I12 | Kursinngangen kan tilbakeføres uten å slette nye RegiNor-data eller endre gamle kurs. |

Prøvene er planlagt og ikke utført mot SalsaNors installasjon. Dokumentasjonsreferansene ble lest 17. september 2026; de er ikke en bekreftelse på installert kompatibilitet.

Sprint 4s lokale HTTP-tester bekrefter egne kursflater, canonical, core sitemap og tilgangsgrenser uten ACF/Avada/TEC installert. Det er ikke en bekreftelse på I-kriteriene mot SalsaNors faktiske installasjon. Se [sprintprotokollen](SPRINT-04.md).
