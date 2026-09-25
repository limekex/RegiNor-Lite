# Arkitektur og beslutninger

Grunnlag: [spesifikasjon v1.2](SalsaNor-kursoversikt-spesifikasjon.md). Dette dokumentet skiller mellom dagens grunnstruktur og planlagt implementering.

Gjeldende premiss 17. september 2026: **RegiNor starter fra neste nye kursperiode, uten import eller migrering.** Pluginen eier nye perioders kursmodell og administrasjon uten ACF-avhengighet. Gamle ACF-kurs, registreringer og URL-er beholdes. Avada eier presentasjonsrammen; The Events Calendar videreføres med et avklart grensesnitt. Kartlegging gjelder kompatibilitet, navnekollisjoner, innbygging og eventuelle referanser til eksisterende profiler/steder. Se [samspillsplanen](SAMSPILL-AVADA-ACF-TEC.md).

## Beslutninger ved initialisering

| Valg | Begrunnelse / status |
| --- | --- |
| WordPress-plugin i `plugin/reginor-lite/` | Kan pakkes uten dokumentasjon, tester eller utviklingsavhengigheter. |
| Namespace `RegiNor\Lite`, prefiks `rnl_`, text domain `reginor-lite` | Nye registreringer holdes adskilt fra eksisterende produksjonsnøkler. |
| PHP-rendering med begrenset JavaScript | Kursinnhold og påmelding skal fungere uten JavaScript. Ingen separat SPA. |
| Foreløpig minimum WP 6.8 / PHP 8.2 | Utviklingsvalg, ikke kartlagt produksjonskrav. Må bekreftes i M1. |
| Ingen runtime-pakker foreløpig | Grunnstrukturen bruker WordPress API-er direkte. Composer kjører prosjektkontroller; npm brukes bare til lokalt WordPress. |
| Egen avgrenset klasselaster i `src/autoload.php` | Laster bare `RegiNor\Lite` fra pluginens `src/`. Samme laster brukes av PHPUnit. Ingen Composer-runtime i installasjonspakken. |
| Kursarbeidsflate med egne capabilities | Sprint 3 har kursansvarligrolle, egne periode-/gruppe-rettigheter og avgrenset oppslag. Ressursoppsett og prosjektstatus krever administrator. |
| Versjonert capability-oppsett ved `init` | Sprint 2 registrerer private innholdstyper og gir administrator egne RegiNor-rettigheter. Ingen kursdata opprettes automatisk. Deaktivering sletter ikke data eller rettigheter. Import er utenfor omfanget. |
| Lisens/distribusjon avklares før ekstern utgivelse | Composer er markert `proprietary` og npm `private`; dette tildeler ingen ny åpen lisens. |

## Modulgrenser

| Modul/tjeneste | Ansvar |
| --- | --- |
| Domene / `ScheduleGenerator` | Lokale datoer og tider, stabile økter, opphold og endringsforslag; testbar uten WordPress. |
| `ConflictValidator` | Faktiske tidsintervaller, instruktører og saler på tvers av aktive perioder. |
| `CourseRepository` | RegiNor-eid WordPress-lagring uten ACF-avhengighet; skjuler at økter først ligger i gruppemetadata. Nye innholds-ID-er og eventuelle eksterne profilreferanser håndteres eksplisitt. |
| `PublicationService` | Felles offentlig tilgjengelighet, periodevalg og tidsgrenser med injiserbar klokke. |
| Admin / applikasjonstjenester | Rettigheter, publiseringsvalidering, kopi, versjonskonflikter, logg og forhåndsvisning. |
| Offentlig renderer / `SchemaPresenter` | Samme normaliserte data og tilgangspolicy for liste, kalender, detalj og JSON-LD. |
| LetsReg-adapter / kø | Aggregerte snapshots, mapping, ferskhet og kontroller. Ingen deltakerlagring eller bestillinger. |
| Systemoppgraderinger | Versjonert oppsett for egne roller/metadata ved behov. Ingen ACF-leser eller import av kurs skal implementeres. |

Sprint 1 implementerte `ScheduleGenerator`, `ConflictValidator` og domenedelen av `PublicationService`. Sprint 2 kobler domenet til privat WordPress-lagring gjennom `CourseRepository`, `StateSchema` og `ContentTypes`, og legger til `ScheduleReplanner`. Sprint 3 legger til `CourseWorkflow`, `Mutation`, `Roles`, `SalesStatus` og serverrendret kurseditor. Sprint 4 legger til offentlig `Catalog`, `Renderer`, `PublicSite`, `SchemaPresenter` og `CourseSitemap`. LetsReg-adapteren er fortsatt planlagt.

### Sprint 1-kontrakter og grenser

- `ScheduleRequest` beskriver en ny gruppe med ISO-ukedag, start/slutt samme lokale dag, antall kvelder, periode-/gruppeopphold og valgfri absolutt siste dato. `firstDate` er eksplisitt første dato og overstyrer foreslått start; feil ukedag eller opphold på den datoen avvises.
- `ScheduleGenerator::preview()` lager bare et uforanderlig forslag av `ScheduledTime`. Stabile ID-er og bevaring av historiske/manuelt endrede økter håndteres fra sprint 2 av `ScheduleReplanner` og repository, med differanse og bekreftelse.
- Søkehorisonten er 104 kandidatuker som standard, konfigurerbar 1–520. Opphold er inklusive og vurderes som union. Ingen automatisk helligdagsregel. Økter over midnatt støttes ikke i denne første kontrakten.
- Lokal tid som ikke finnes eller forekommer to ganger ved sommertidsskifte avvises med norsk feil; UTC beregnes per dato. Ingen skjult normalisering.
- `SessionAllocation` beskriver faktiske tidspunkt og sal-/instruktør-ID-er. Repository leverer fra sprint 2 alle relevante lagrede perioders økter og globale ressurs-ID-er. Manglende sal gir ikke en automatisk kollisjon, og blokkeres derfor separat av publiseringsvalideringen fra sprint 3.
- `Period` er en lesemodell. Repository avleder fra sprint 2 første/siste faktiske ikke-avlyste økt og `hasPublishedGroup` fra publiserte grupper. `PublicationService` håndterer synlighetsvindu og periodevalg. Sprint 3 har publiseringsoperasjon og separat effektiv salgsstatus; sprint 4 bruker samme regler i offentlig HTTP-visning, sitemap og eventuell sidegjengivelse gjennom core REST. Faktisk cache/CDN-tilpasning gjenstår.
- Ved lik start sorteres perioder etter stabil ID som streng stigende. Avsluttede/avlyste perioder kan leses innenfor vinduet, men foreslås ikke i periodevelgeren.

### Sprint 2-kontrakter og grenser

Fem private innholdstyper lagrer beskrivelse, periode, gruppe, sted og sal. Hvert objekt har én versjonert `rnl_state`-metaverdi med nåtilstand og historikk; atomisk sammenligning av forrige verdi avviser konkurrerende oppdateringer. Økter og opphold ligger i denne tilstanden med stabile UUID-er. Detaljert format og API er beskrevet i [datakontrakten](DATAKONTRAKT.md).

Repository tillater bare kladdendringer. Gruppeendringer krever et signert forslag med gruppe-/periodeversjon og endringsaktør. Flyttede, avlyste og påbegynte økter bevares. Ressurskonflikter og vindusvarsler rapporteres; konflikter kan lagres i kladd. Ingen vinduer utvides automatisk. Periodeendringer endrer aldri eksisterende grupper automatisk.

Rå REST-skriving og generiske CPT-editorer er slått av. Sprint 3 har publiseringsvalidering, samtidighetskontroll på tvers av objekter og kursansvarligrolle. Publisering endrer interne statuser, mens de generiske CPT-flatene fortsatt er private. Eksisterende instruktørprofiler kan refereres bare fra eksplisitt konfigurerte posttyper etter kartlegging; ACF brukes ikke som lagringslag.

### Sprint 3-kontrakter og grenser

`CourseActions` tar imot eksplisitte skjemaoperasjoner, verifiserer nonce/rettigheter og oversetter lokale tidspunkt og kronebeløp til lagringsformatet. `CoursePage` bruker vanlige PHP-skjemaer, forhåndsvisning og 303-omdirigering etter vellykket skriving. Ingen JavaScript er nødvendig for hovedflyten.

`CourseWorkflow` utvider repository med avgrensede ressursoppslag, erstatningskvelder, publiseringsforslag, kopiering og statusendringer. `Mutation` låser alle repository-skrivere på tvers av perioder og bruker InnoDB-transaksjoner ved samlede operasjoner. Både gruppeversjoner, relevante ressursopplysninger og konflikter kontrolleres på nytt før publisering. Vindusbekreftelser er en del av det signerte forslaget.

Fra 0.1.20 beholdes WordPress' standard, forespørselslokale objektcache under transaksjonen. Tidligere global sperre mot cache-priming ga gjentatte SQL-oppslag i lagringshooks. Standard minnecache ryddes ved transaksjonsgrensen før objektspesifikk opprydding, også etter rollback, slik at ubekreftede leseresultater ikke lever videre i forespørselen. Ekstern/ikke-standard objektcache beholder eksisterende policy. Sidecache/CDN og øvrige WordPress-hooks endres ikke. Se [testbevis og avgrensning](releases/0.1.20.md).


Publisert oppsett tas eksplisitt tilbake til kladd før redigering. Ingen skjult kopi av en publisert versjon holdes offentlig under redigering i denne første arbeidsflyten. Avlyste økter kan beholdes uten erstatning eller få en separat erstatningsøkt; den gamle identiteten bevares.

Kursansvarligrollen opprettes uten generelle redaktørrettigheter. Bare perioder og grupper kan endres; profiloppslag returnerer ID/navn og ressursoppslag bare definerte presentasjonsfelter. Tilgangskontroll bygger på [WordPress-capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/) og eksplisitt [CPT-mapping](https://developer.wordpress.org/reference/functions/register_post_type/). Se [sprint 3](SPRINT-03.md) for driftspremisser og manglende staging-/brukertester.

### Sprint 4-kontrakter og grenser

`Catalog` leser og validerer egne publiserte data og projiserer bare tillatte offentlige opplysninger. Den gjenbruker `Period`, `PublicationService` og `SalesStatus`; den autentiserte repository-metoden åpnes ikke for anonyme brukere. Manglende/private/passordbeskyttede referanser gir ingen offentlig gruppe. Aktører, historikk og profilmetadata inngår aldri i projeksjonen.

`Renderer` bruker samme projeksjon for liste, ukemønster og detalj. `SchemaPresenter` bruker faktiske økter, inklusive egne sted-/tidsavvik. `PublicSite` registrerer shortcode og dynamisk blokk og håndterer direkte kursvalg på en eksplisitt valgt eksisterende WordPress-side. Generiske CPT-ruter forblir lukket. `CourseSitemap` bruker også `Catalog`.

Dynamiske svar bruker `no-store`; det er ingen egen HTML-/objektcache i katalogen. Et lite tilleggsskript fjerner utgått innhold i åpne faner og gjenoppretter fokus ved retur. Fullsidecache/CDN som svarer før WordPress må konfigureres i staging. Det er ingen automatisk overtakelse av eksisterende sider, ACF-kurs eller TEC-arrangementer.

UI følger [styrende brukeropplevelseskrav](BRUKEROPPLEVELSE.md). CSS er avgrenset til RegiNor-klasser og bygger på temaets typografi. Visuell/mobile/tastatur-prøve og faktisk Avada/SEO-integrasjon er fortsatt uverifisert; se [sprint 4](SPRINT-04.md).

## Datakontrakter som skal bevares

- Periode → gruppe → faktiske økter; beskrivelser og eksisterende instruktører gjenbrukes.
- Økter har stabile ID-er, opprinnelig dato, faktisk lokal tid, UTC, tidssone og status. Flytting/avlysning og historikk bevares.
- ISO-ukedag mandag 1 til søndag 7; dato `YYYY-MM-DD`, tid `HH:mm`, normalt `Europe/Oslo`. Beregn UTC per dato.
- Beløp er heltall i øre, valuta NOK, med eksplisitt prisgrunnlag. Publisering, hendelsesstatus og billettstatus holdes adskilt.
- Synlighetsvindu er `[visible_from, visible_until)`. Påmeldingsvindu og undervisningsperiode er egne tidsforløp.
- Ukeskalenderen viser gruppenes faste mønster; faktiske økter styrer datoer, kollisjoner og schema.
- Eksakte kapasitetstall er av inntil datakontrakten er validert; `null` betyr ukjent, mens `0` er bekreftet fullt.

## Krav som trenger særlig oppfølging

Kapittel 19.5 gjelder nye RegiNor-grupper: direkte URL skal gi 404 når synlighetsvinduet utløper. Gamle ACF-kurssider omfattes ikke av denne innføringen og skal ikke omdirigeres, skjules eller endres automatisk.

Innledningen avgrenser egen påmeldingsmotor. Kapittel 15–16 krever likevel demonstrasjonsadapter og kapasitetsstatus i MVP, med reell API-tilkobling som egen verifisert leveranse. Denne funksjonen er med i roadmapen; ingen live API-kobling bygges på antatte webhooknavn eller kapasitetsregler.

`show_as_upcoming` styrer bare fremtidig oversiktsvisning. Det er ingen tilgangssperre for en ellers offentlig direkte URL. Skjult innhold skal alltid avgjøres av samme publiseringspolicy i alle kanaler, også ved utdatert cache og uteblitt cron.

## Kapasitetsdemonstrasjon – sprint 5

`Domain/Capacity` validerer komplette observasjoner, skiller null fra null plasser, avviser eldre kildeversjoner og projiserer ferskhet/feil. `DemoCapacityAdapter` produserer bare syntetiske data. `CapacityStore` lagrer én privat, atomisk `_rnl_capacity`-rad per gruppe med versjon, oppsett, jobb og siste gode bilde. Forsøk og suksess har separate tidspunkt. Periodisk kontroll og manuell bestilling deler jobb og ventetid. Ingen live-provider kan velges.

`Admin/CapacityPage` avgrenser oppsettet til administrator og kontrollbestilling til kursansvarlig med tilgang til gruppen. Offentlig projeksjon leser aldri demometadata som ledighet; felles reservevisning og ordinær påmeldingslenke brukes. Schema oppgir ikke ubekreftet `InStock`. Kopierte perioder får nye grupper uten kapasitetsmetadata.

Demojobber kjøres under eksisterende skriverlås og inneholder ingen nettverksarbeid. En fremtidig live-adapter trenger verifiserte organisasjons-/arrangements-/kategori-/poolreferanser, kvoter og en kømodell som frigjør kurslåsen under nettverksarbeid og avviser utdaterte resultater ved lagring. Webhooks, par-/poolberegning og eksakte offentlige tall er ikke implementert. Se [sprint 5](SPRINT-05.md) for drift og testavgrensning.
