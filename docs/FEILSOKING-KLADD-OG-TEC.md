# Kladd og TEC-låser – 24. september 2026

## Rapport og bevis

Prosjekteier får «En annen kursendring pågår» ved forsøk på å ta Høst 3 2026 tilbake til kladd. Kalenderstatus oppgir også manglende tilgang til databaselås.

Vedlagt loggutdrag fra 08:01–08:03 UTC viser 138 meldinger om `Commands out of sync` og 14 om `Lock wait timeout exceeded`. Førstnevnte vises blant annet i TEC Tickets Plus / Shepherd / Action Scheduler ved avslutning av forespørsler. Låseventingen gjelder WPMLs lagring av `wpml_resolved_url_persist`. Utdraget inneholder ingen GET_LOCK-forespørsel eller RegiNor-stack som beviser hvem som holder kurslåsen. Samtidige forespørsler er blandet i loggen; rekkefølgen alene beviser ikke en årsakskjede.

Andre meldinger gjelder blant annet TEC Pro/WPMLs dynamiske PHP-egenskaper, tidlig oversettelseslasting (også RegiNor), child-temaets functions.php og PWA/HTML-behandling. Disse er ikke i seg selv bevis for at kladdfeilen skyldes dem. Rå produksjonslogg er ikke kopiert inn i repoet.

MySQL dokumenterer at [GET_LOCK](https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html) gir 1 ved lås, 0 ved ventetidsutløp og NULL ved feil. [Commands out of sync](https://dev.mysql.com/doc/refman/8.0/en/commands-out-of-sync.html) gjelder ugyldig rekkefølge/tilstand i databaseklienten; dette beviser ikke manglende GET_LOCK-støtte.

## Rettet i RegiNor 0.1.16

- Tidligere holdt TEC-synkroniseringen den globale kurslåsen gjennom eksterne lagrings- og språkhooks. Nå holder den en separat kalenderlås. Kurslåsen beskytter bare lesing av kildegrunnlaget, slik at det ikke leses midt i RegiNors publisering.
- Innsendte skjemaendringer behandles før kalenderkontrollen; TEC-skriving starter ikke lenger i `wp_loaded` på POST.
- Kalenderkontroller bruker ventetid 0 og gir plass til eksisterende kurs- eller kalenderarbeid. Ordinære kursendringer beholder gjensidig låsing, transaksjoner og versjonskontroll.
- NULL/feilet databaseforespørsel gir egen melding med `RNL-DB-LOCK` og avbryter før skriving. Opptatt kurslås gir fortsatt konflikt; den omgås ikke.
- Lås og intern aktiv-status ryddes selv ved unntak i språk-/cachehooks. Offentlig synlighet vurderes fra kildegrunnlaget etter TEC-skriving, uten gjenbruk av gammel synlighetscache.
- En allerede startet TEC-oppdatering kan skrive en eldre kalenderkopi. Offentlige vern skjuler den hvis kursperioden i mellomtiden er tatt til kladd; neste kontroll reparerer selve TEC-statusen. Dette er en kalenderprojeksjon, ikke en tilbakeskriving til kursperioden.

Det er ikke lagt inn global tilbakestilling av databaseforbindelser, tvungen frigjøring av andre forespørslers låser eller endringer i tredjepartspluginer. RegiNor kan ikke reparere en ødelagt databaseforbindelse ved å ignorere feilen.

## Kontroll i faktisk miljø

1. Installer 0.1.17, last periodeoppsettet på nytt og prøv «Til kladd». Bekreft at periode og kurs blir kladd og forsvinner offentlig, også fra TEC. Legg deretter til kurs gjennom vanlig arbeidsflyt.
2. Ved fortsatt feil: noter nøyaktig tidspunkt, handling og kontrollkode, og hent et kort loggutdrag rundt forsøket. Ikke tøm eller slett kursdata.
3. Administrator/host kan kontrollere aktive databaseforbindelser og transaksjoner, hvem som eier den aktuelle navngitte låsen, og hvilke operasjoner som venter. Felles kurslås er `rnl:` + MD5 av databasenavn, kolon og WordPress-tabellprefiks; kalenderlåsen har prefiks `rnl:tec:`. `IS_USED_LOCK` kan identifisere eierforbindelsen. Ikke avslutt forbindelser ukritisk.
4. Hvis `Commands out of sync` fortsetter: undersøk faktisk TEC/Tickets Plus/WPML-kombinasjon i isolert staging, med tidsstemplet logg. Den første observerte feilen i en avslutningshook kan være en følge av tidligere arbeid i samme forespørsel.

Lokal test dekker injiserte feil og konkurrerende databaseforbindelser. Faktisk TEC Pro/Tickets Plus/WPML-miljø og serverårsaken er fortsatt uavklart.

## Ny prøve etter 0.1.16 – 24. september, kl. 19 UTC

TEC Tickets / Tickets Pro er deaktivert ved denne prøven. Prosjekteier får fortsatt en generell versjonskonflikt, varsel om pågående kalenderarbeid og 504 Gateway Time-out. Nytt loggutdrag viser maksimal PHP-kjøretid på **120 sekunder**, låseventing ved Avadas lagring av `fusion_dynamic_js_filenames`-transienter og senere feil med lukket mysqli-resultat i en avslutningskjede som inkluderer Really Simple SSL. Dette identifiserer ventende operasjoner og etterfølgende feil, ikke forbindelsen som opprinnelig blokkerer databasen. Deaktivering av Tickets løste altså ikke hele problemet.

Sucuri og Wordfence er også i bruk. Deres lagrings-/statushooks kan bidra til total tidsbruk uten egen PHP-feil, men loggen beviser ikke en slik sammenheng. Ingen sikkerhetsutvidelser er endret eller deaktivert av denne rettingen. Ved fortsatt treghet bør administrator/host måle den konkrete forespørselen og dens databaseventing i staging, og identifisere hvilken forbindelse/transaksjon som blokkerer. Loggens siste komponent er ikke nødvendigvis årsaken.

### Endring i 0.1.17

- TEC-skriving flyttes helt til en deduplisert WP-Cron-jobb, med periodisk kontroll. Kurslagring venter ikke på å gjennomføre denne jobben. Kladdvern virker fortsatt før TEC-kopien er oppdatert, og samme kalender-ID gjenbrukes ved republisering.
- Mislykket `update_post_meta()` ble tidligere alltid meldt som versjonskonflikt. En lokal regresjonstest gjenskapte denne feildiagnosen. Nå skilles avvist/databasefeilet lagring (`RNL-DB-WRITE`) fra reell endring under lagring (`RNL-WRITE-CONFLICT`), utdatert versjon (`RNL-VERSION`) og endret kursliste (`RNL-COURSE-LIST`). Låser, transaksjoner og versjonskontroll beholdes.
- Status viser om kalenderjobben venter, ikke er registrert som fullført eller ikke kunne legges i kø. Dette erstatter løftet om at hver sidelasting forsøker tung kalenderlagring igjen.
- Lokal TEC-reservelagring tåler tom/manglende datovelgerinnstilling. HTTP-testen krever nå at kalenderarbeidet er fullført før den første offentlige forespørselen; sidelasting får ikke skjule feil ved å reparere dem synkront.

En 504 forteller ikke om endringene ble lagret før forbindelsen ble brutt. Last periodeoversikten på nytt og kontroller faktisk status før nytt forsøk. For en vedvarende versjonskonflikt: noter kursnavn og innsendt/lagret versjon fra den nye meldingen. Ved `RNL-DB-WRITE`: bruk tidspunktet til å finne den første databasefeilen i samme forespørsel.

Faktisk test av Høst 3 2026, TEC Pro, Avada, WPML, Sucuri og Wordfence sammen gjenstår. Ingen rå produksjonslogg eller deltakerdata er lagret i repoet.

## Prøve etter 0.1.17 – 19:47–19:59 UTC

Ny logg inneholder 32 låsetidsavbrudd rundt Avadas `fusion_dynamic_js_filenames`-transienter. Ett av disse kommer via Optimization Detectives lagring av URL-målinger og Avadas `save_post`-hook, resten fra side-/kalendermaler. Klokken 19:53:12 UTC stopper PHP etter 120 sekunder; feil med allerede lukket mysqli-resultat følger i feilvisning/avslutning. To RegiNor-spor gjelder lesende kalendervern under Optimization Detectives outputbuffer, ikke kurslagring eller cron-kølegging. Loggen inneholder ingen forklaring fra WordPress på selve køavvisningen. Dette er aktive feil, men blokkerende forbindelse og transaksjon er fortsatt ukjent.

Skjermbildets Action Scheduler-oppgave `shepherd_tec_process_task` startet og feilet klokken 18:54:21 UTC med «The task class does not exist». Den refererer til oppgaven `etp_prs_on_chg_…`. Dette kan være en rest fra en deaktivert/oppdatert utvidelse; eksakt eierklasse er ikke verifisert. Den er ikke RegiNors kalenderjobb, og tidspunktet er ikke bevis for at den blokkerer den senere forespørselen. Ikke slett eller kjør ukjente oppgaver som en generell reparasjon.

### Hva kan være igjen etter et avbrudd?

En køoppføring, feilmelding eller allerede lagret kalenderkopi kan bli igjen. En proxy-504 betyr heller ikke nødvendigvis at PHP-forbindelsen sluttet samtidig. Men en MySQL-navngitt lås frigjøres når eierforbindelsen avsluttes; det er ikke en permanent rad som må slettes. At en gammel feilet oppgave finnes, beviser derfor ikke at den holder en databaselås nå. Transaksjoner og forbindelser må undersøkes mens problemet pågår. Se [MySQL om låsenes levetid](https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html).

### Diagnoseutgave 0.1.18

Køfeilen får konkret kode og jobbnavn. `could_not_set` betyr at WordPress ikke fikk lagret cron-arrayet; det kan skyldes databasefeil, filteravvisning av option-skriving eller konkurrerende oppdatering. `pre_schedule_event_false` / `schedule_event_false` betyr at et filter avviste selve planleggingen. Ukjente leverandørfeil merkes `provider_error` uten å vise rå innhold eller data.

Når en parallell forespørsel allerede har lagt inn samme jobb, kontrolleres faktisk køinnhold før vi rapporterer feil. En ny bekreftet køoppføring kan rydde en tidligere køfeil for den samme jobben; feil i selve kalenderarbeidet beholdes. Under Nettsidevisning → Arrangementskalender finnes en sammenleggbar, lesende teknisk status med jobbnavn, køtid, start/avslutning, nettsteds-ID og informasjon om `DISABLE_WP_CRON`.

Dette er diagnose og avgrenset køretting, ikke en bekreftet reparasjon av 504. Verken låser, køtabeller eller kursdata er tømt. Neste nødvendige eksterne bevis er [webhotellets kontroll av aktive blokkeringer](HOSTING-DATABASEKONTROLL.md).

## Prøve med køoversikt etter 0.1.18 – 20:29–20:32 UTC

Nettsteds-ID er bekreftet som **19**. `DISABLE_WP_CRON` er aktiv. Skjermbildet viser `rnl_tec_sync_periodic` i køen til 24.09.2026 kl. 21:32:38 CEST, 54 minutter forsinket, og ingen `rnl_tec_sync_soon`. LetsReg-kontrollen er over tre dager forsinket og oppryddingsjobben nesten to dager forsinket. Dette viser manglende normal fremdrift for flere jobber på samme nettsted; det beviser ikke om servercron mangler, peker på feil nettsted eller selv feiler/blokkeres.

«Siste start» er ikke registrert. Dette alene beviser ikke at kroken aldri ble kalt: RegiNor registrerer start etter at kalenderlåsen er tatt. Men at flere cron-jobber står med gamle forfall støtter behovet for kontroll av selve kjøringen. Den generelle kømeldingen har ordlyd fra 0.1.17 og lagret tidspunkt 21:32:08 CEST. I 0.1.18 kan en slik eldre status uten `schedule_hook`/`schedule_code` bli stående frem til en ny registrert jobbstart/fullføring eller ny kølegging. Den er ikke i seg selv bevis for en fersk køfeil. Eksisterende periodisk jobb beviser at minst én køregistrering har lykkes.

Den nye loggen inneholder ti låsetidsavbrudd fra 20:31:23 til 20:32:29 UTC, alle ved Avadas JavaScript-transienter i kalenderens frontendmal. PHP stopper etter 120 sekunder kl. 20:32:42 UTC, altså **22:32:42 CEST**. Feil med lukket mysqli-resultat følger under feilvisning og Really Simple SSLs outputbuffer. Ingen cron-/RegiNor-skrivekjede i dette utdraget identifiserer den opprinnelige blokkeringen.

Neste kontroll er todelt: få bekreftet servercron for riktig nettsted, og identifiser aktiv databaseblokkering ved statuslagringen. Dette er driftsbevis, ikke grunnlag for å slette kurs eller å hevde at en ny pluginutgave løser tidsavbruddet. Se den oppdaterte [hostingkontrollen](HOSTING-DATABASEKONTROLL.md#bekreftet-køproblem-på-nettsted-19).

## Servercron bekreftet, men WP-CLI avviser PHP-CGI

Prosjekteiers cPanel-kommando kjører hvert femte minutt med `flock`, men starter WP-CLI uten eksplisitt PHP-binær. Cronloggen bekrefter at WP-CLI avbryter med `cgi-fcgi` i stedet for `cli`. Disse forsøkene kommer derfor ikke til kjøring av WordPress-jobbene. Dette er en konkret forklaring på manglende cron-fremdrift, men ikke bevis for at databaseblokkeringen ved «Til kladd» er løst eller har samme årsak.

Dokumentert retting er eksplisitt PHP-CLI, kontroll av PHP-versjon/nettsted 19, én avgrenset kalenderprøve og deretter oppdatering av eksisterende serverjobb. Før hele køen gjenopptas må gamle `publish_future_post`-jobber vurderes; utdraget har planlagte publiseringer fra april 2025. Se [cPanel-fremgangsmåten](HOSTING-DATABASEKONTROLL.md#bekreftet-oppstartsfeil-i-cpanel-cron). Faktisk gjennomføring og stabil drift er fortsatt ubekreftet.

SQL-utdraget i samme tilbakemelding inneholder bare spørringen, ikke verdiene for `course_connection` og `calendar_connection`. Det gir derfor ingen ny konklusjon om eier av RegiNors navngitte låser.

Etterfølgende terminalprøve bekrefter `/usr/local/bin/php` som **PHP 8.3.33 (cli)**. OPcache-advarselen om dobbel lasting vises fortsatt, men endrer ikke dette SAPI-resultatet. WP-CLI med eksplisitt CLI-binær, nettsted 19 og faktisk kalenderkjøring er ennå ikke prøvd i det mottatte resultatet.

## Ny terminalprøve – nettsted 19 og kalenderkjøring bekreftet

To nye terminalutskrifter viser:

- `get_current_blog_id()` returnerer **19** med eksplisitt PHP-CLI og `--url=https://www.salsanor.no`.
- Første kalenderprøve med `wp` fra terminalen returnerer to hendelser på **4,928** og **2,146** sekunder. Andre prøve med eksplisitt PHP-CLI og samme `flock` som servercron returnerer én hendelse på **0,131** sekunder. Begge avslutter med `Success`; ingen fatalfeil eller låsetidsavbrudd vises i disse utdragene.
- To cron-hendelser er to kjøringer av samme hook, ikke dokumentasjon på doble kurs eller TEC-arrangementer. Fortsatt flere planlagte forekomster kan undersøkes lesende i cron-oversikten; ikke slett dem basert på denne utskriften alene.
- WP-CLI-resultatet beviser at callbacken returnerte, men ikke at en konkret TEC-oppføring ble lagret. Broen håndterer leverandørfeil og låsekonkurranse uten nødvendigvis å kaste ut av WP-CLI. Kontroller RegiNors siste start/avslutning, periodens kalenderstatus og offentlig oppføring. Den korte siste kjøringen kan være kontroll av uendret innhold; det kan ikke utledes sikkert av tidsbruken.

Oppstartsvarsler om OPcache, TEC Pro/WPML, Sucuri, PWA og RegiNor forekommer fortsatt. Den første prøven har også WPML-varsler om tomt innlegg og child-temaets nøkkel `99`. Disse varslene stoppet ikke prøvene, men er heller ikke dermed bevist ufarlige i alle sammenhenger. Tidligere 504 under «Til kladd» må kontrolleres separat. Automatisk femminutterskjøring er fortsatt ikke dokumentert av den manuelle testen.

### Avgrenset lokal retting av RegiNors oversettelsesvarsel

En lokal oppstartstest gjenskaper at en annen utvidelse spør etter `wp_get_schedules()` på `plugins_loaded`. RegiNors to `cron_schedules`-filtre oversatte visningsnavn før `init` og utløste samme WordPress-varsel. De beholder nå intervall og norsk standardnavn tidlig, og oversetter navnet først etter `init`. Dette følger [WordPress' dokumenterte tidspunkt for oversettelser](https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/).

`npm run test:translations` feilet før rettingen og består etterpå; den bekrefter også at begge intervaller finnes før `init` og faktisk oversettes etterpå. WordPress-smoketest, admin 91, kapasitet 39, TEC 102 og Composer 225/518 består. POT er oppdatert. Ingen ny ZIP er bygget og ingen produksjonskode er endret. Det konkrete oppstartssporet på produksjon er ikke logget med stack trace; testen beviser at denne tidlige cron-lesingen kunne utløse varselet i vår kode.

## Køstatus kl. 23:43:51 CEST og PHP-innstillinger

Nytt skjermbilde viser både siste start og avslutning **24.09.2026 kl. 23:43:51 CEST**, neste periodiske jobb kl. **23:48:51**, og ingen generell køfeil i panelet. Kalenderarbeidet har altså kommet forbi kalenderlåsen og registrert avslutning. `rnl_tec_sync_soon` er ikke registrert; det er ikke en feil i seg selv når ingen egen snarlig jobb venter. Bildet alene bekrefter fortsatt ikke status for en bestemt periode eller automatisk femminutterskjøring.

Prosjekteier melder at første nye «Til kladd»-forsøk feilet. Nøyaktig feilmelding og tidspunkt for dette forsøket er etterspurt; ikke anta uten videre at det er samme 504 eller versjonskonflikt som tidligere. Videre prøve av automatisk cron **avventes etter prosjekteiers beskjed**.

PHP-panelet viser `error_reporting = ~E_ALL`, `log_errors = Off`, `display_errors = Off`, `max_execution_time = 120`, `max_input_time = 120`, `max_input_vars = 5000`, `memory_limit = 256M`, og opplastings-/POST-grenser på 128M. For feilsøking anbefales `E_ALL` uten tilde, logging på og skjermvisning fortsatt av. `~` er bitvis negasjon og fjerner dermed de kjente feilnivåene i `E_ALL` fra masken; det betyr ikke «alle feil». WordPress' `wp_debug_mode()` kan overstyre feilnivået og aktivere logging når `WP_DEBUG`/`WP_DEBUG_LOG` er på, så cPanel-bildet alene viser ikke nødvendigvis effektive verdier under den aktuelle WordPress-forespørselen. Dette kan forklare hvorfor tidligere logger finnes likevel.

120-sekundersverdien samsvarer med tidligere fatalfeil, men beviser ikke at løsningen er høyere tidsgrense. Proxy/webserver har egne grenser; databasen har også egne låseventetider. Ingen mottatt logg har så langt påvist minneoverskridelse som årsak. `max_input_time` gjelder mottak/tolking av input, ikke tiden brukt til statuslagring. Disse grensene endres ikke på bakgrunn av skjermbildet alene.

`include_path` viser `/opt/alt/php83/`, mens `session.save_path` viser `/opt/alt/php81/var/lib/php/session`. Mappenavnet alene beviser ingen feil; host bør bekrefte at det er den tilsiktede, skrivbare sesjonsmappen for aktiv web-PHP. RegiNors runtime har ingen egen `session_start()`. CLI- og web-PHP kan bruke ulike konfigurasjoner, så den vellykkede CLI-prøven verifiserer ikke alle verdier fra webpanelet.

Kodegjennomgangen bekrefter at `CourseWorkflow::changeLifecycle()` fortsatt oppdaterer periodemetadata og hver kursstatus med `wp_update_post()` inne i `Mutation`-transaksjonen. WordPress-/tredjepartshooks kjører der; TEC-synkroniseringen er separat. Ved et nytt bekreftet tidsavbrudd må målingen derfor identifisere hvilken skriveoperasjon/hook eller blokkerende transaksjon forespørselen stopper ved. Ingen ny endring i transaksjonsvern eller publiseringsflyt er gjort på dette bevisgrunnlaget.

Kilder: [PHP feillogging](https://www.php.net/manual/en/errorfunc.configuration.php), [PHP bitvise operatorer](https://www.php.net/manual/en/language.operators.bitwise.php), [PHP tids-/inputgrenser](https://www.php.net/manual/en/info.configuration.php), [WordPress' effektive debug-innstillinger](https://developer.wordpress.org/reference/functions/wp_debug_mode/).


## Ny kritisk feil – 24. september 21:58–22:05 UTC

Skjermbildet viser nå WordPress' kritiske feil. Loggen bekrefter to PHP-stopp etter **120 sekunder** kl. 22:01:37 og 22:01:40 UTC, altså **25. september kl. 00:01:37 og 00:01:40 CEST**. Begge stopper i `wpdb`; ingen minneoverskridelse er påvist.

Det er 37 låsetidsavbrudd i `wptb_19_options`: 28 ved `fusion_dynamic_css_ids`, åtte ved `fusion_dynamic_css_posts` og ett ved JavaScript-cache. Tre kommer fra WP-CLI-kjøring av `od_trigger_page_cache_invalidation`, via `save_post` til Avadas cacheopprydding. Resten kommer fra offentlig visning. Dermed finnes det nå spor av en annen cron-jobb som faktisk utføres, men dette bekrefter verken permanent servercron eller forbindelsen som blokkerer «Til kladd». Videre automatisk cron-testing avventes fortsatt etter prosjekteiers beskjed.

To tilkoblingsforsøk til QUIC.cloud utløper kl. 21:58:32 og 21:58:43 UTC i LiteSpeeds `fsockopen()`. Den offentlige [LiteSpeed-kilden](https://raw.githubusercontent.com/litespeedtech/lscache_wp/master/src/utility.cls.php) bruker en slik tilkobling i sin tilkoblingskontroll. Utdraget mangler forespørsels-ID og beviser ikke at nettverksventingen tilhører kladdforsøket. Fem «Commands out of sync» oppstår i avslutnings-/outputbufferkjeder. Ingen av disse sporene viser den opprinnelige RegiNor-skriveoperasjonen.

### 0.1.19: valgfri sporing av kurslagring

`RNL_TRACE_MUTATIONS` aktiverer tekniske `RNL-MUTATION`-linjer rundt lås, transaksjon, metadata, hver statusendring, cacheopprydding og frigjøring. Hver ytre operasjon får en egen `trace_id`; nøstede operasjoner beholder samme spor. Feltet `db_connection` leses fra den eksisterende mysqli-forbindelsen uten ekstra SQL og kan sammenholdes med webhotellets aktive låseventing. `0` betyr at forbindelses-ID ikke kunne leses.

Sporingen er av som standard, kan avgrenses til nettsted 19 og endrer ikke rettigheter, versjonskontroll eller transaksjonsvern. Den registrerer ikke navn, beskrivelser, innsendte felter, rå SQL, URL-er, brukerkontoer eller unntaksmeldinger. En shutdown-handler forsøker å registrere siste trinn ved PHP-fatalfeil eller uventet avslutning. En drept prosess/proxyfeil garanterer ikke en avsluttende logglinje.

Dette er et måleverktøy for den fortsatt uløste driftsfeilen, ikke en bekreftet reparasjon. [Aktivering og tolkning](HOSTING-DATABASEKONTROLL.md#avgrenset-lagringsspor-fra-0119).


## Første lagringsspor – 25. september 05:31–05:35 UTC

Diagnostikken er aktiv på nettsted 19. Begge spor gjelder «Til kladd» for periode 26318. Kurslåsen tas på omtrent 0,3 millisekunder; den første periodemetadataoppdateringen bruker 14–16 millisekunder. Dette avgrenser tregheten tydelig: den oppstår senere, særlig inne i `wp_update_post()` for hvert kurs, mens den samlede transaksjonen fortsatt er åpen.

Det lengste sporet er `644928e1f748db70`, PHP-prosess 2204614, databaseforbindelse 637717, startet 05:32:13 UTC (07:32:13 CEST):

| Kurs-ID | Tid i statusoppdatering | Samlet tid etter statusoppdatering |
| --- | ---: | ---: |
| 26328 | 10,02 s | 10,20 s |
| 26330 | 25,84 s | 40,15 s |
| 26332 | 26,89 s | 71,40 s |
| 26334 | 26,29 s | 102,44 s |
| 26336 | 31,95 s | 138,54 s |
| 26338 | 30,42 s | 173,58 s |

Siste markør er `status.write` for kurs 26340 etter 178,59 sekunder. Det kortere sporet `82c29cb40dbbb0b4` på forbindelse 637665 stopper også ved `status.write`, for kurs 26330. Ingen av sporene har bekreftet commit, rollback eller avslutning i utdraget. Det er ikke bevis for at kursdata ble lagret eller at forespørslene fortsatt kjører nå.

Loggen har dessuten 11 låsetidsavbrudd i Avadas cache fra offentlige forespørsler og 20 «Commands out of sync» i avslutningskjeder. PHP melder 120-sekundersgrense kl. 05:35:21 UTC, deretter feil med lukket mysqli-resultat. Fatalfeilen mangler prosess-/spor-ID. Sekvensiell kursoppdatering er nå bekreftet; hvilken konkret tredjepartshook som står for hver forsinkelse er ikke identifisert. TEC-arbeidet er fortsatt separat.

### Bekreftet lokal ytelsesfeil og retting i 0.1.20

`Mutation` slo av cache-priming gjennom hele transaksjonen med `wp_suspend_cache_addition(true)`. Når en lagringshook tømmer en cacheverdi, hindrer dette WordPress i å gjenbruke etterfølgende lesinger av blant annet metadata og autoloadede options. På et nettsted med mange hooks eller store options kan samme grunnlag dermed hentes fra databasen svært mange ganger. [WordPress-funksjonen](https://developer.wordpress.org/reference/functions/wp_suspend_cache_addition/) og [core-implementasjonen av cache-add](https://developer.wordpress.org/reference/classes/wp_object_cache/add/) bekrefter mekanismen.

En syntetisk `save_post`-prøve gjenskapte feilen: 100 gjentatte lesinger ga **100 SQL-kall før retting og 2 etter**. Dette er en målt reduksjon i den avgrensede prøven, ikke en måling av total produksjonstid eller et bevis for hele årsaken til 504.

I 0.1.20 beholdes WordPress' vanlige, forespørselslokale objektcache under transaksjonen. Den lokale minnecachen tømmes ved transaksjonsgrensen, før ordinær opprydding, slik at blant annet tilbakerullede options, poststatus, metadata og spørringsresultater ikke blir liggende igjen. Testen bekrefter vanlig kjøring av lagringshooks og korrekt rollback. En eventuell cachesperre satt av en annen komponent respekteres.

Rettingen er bevisst avgrenset til core `WP_Object_Cache` uten ekstern objektcache. Redis/Memcached og ikke-standard implementasjoner beholder tidligere policy og tømmes ikke. LiteSpeed-sidecache og Cloudflare-cache endres ikke. Faktisk cachebackend på nettsted 19 og ytelseseffekt der må bekreftes; diagnostikken viser nå `object_cache`, `cache_addition_suspended` og `db_queries` for dette.

Prosjekteier bekrefter at LiteSpeed Object Cache er **OFF**; skjermbildet viser også Redis- og Memcached-utvidelsene deaktivert. Dette støtter at rettingen for WordPress' vanlige lokale cache er relevant. Endelig runtime-bekreftelse kommer fra `object_cache=runtime` i neste spor; bildet alene utelukker ikke en annen ikke-standard cacheimplementasjon. Ingen aktivering av ekstern cache er nødvendig for rettingen.


## Resultat etter 0.1.20 – kladdprøve bekreftet

Prosjekteier bekrefter 25. september 2026: «Da funket det og gikk forholdsvis raskt.» Dette gjelder det foreslåtte «Til kladd»-forsøket på nettsted 19 etter cacherettingen. Den konkrete brukerreisen regnes som bekreftet løst i denne prøven. Eksakt varighet, nytt diagnosespor og runtime-cachefelt er ikke mottatt; ingen tallfestet produksjonsforbedring oppgis.

Slå av midlertidig sporing ved å sette eksisterende `RNL_TRACE_MUTATIONS` til `false`. Republisering, etterfølgende TEC-status og stabil automatisk cron er separate kontrollpunkter og er ikke bekreftet av denne tilbakemeldingen. Automatisk cron-testing avventes fortsatt. Dokumentasjonen er oppdatert uten nye kodeendringer, tester eller ny ZIP.
