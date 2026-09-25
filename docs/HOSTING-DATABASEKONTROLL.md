# Database- og cron-kontroll hos webhotellet

**Status 25. september 2026:** Prosjekteier bekrefter at «Til kladd» fungerer og går forholdsvis raskt med 0.1.20. Den konkrete kladdfeilen er løst i denne prøven. Slå av midlertidig `RNL_TRACE_MUTATIONS`-sporing. Kontrollene nedenfor beholdes som referanse ved tilbakefall; automatisk cron-testing avventes fortsatt.

Gjelder salsanor.no, RegiNor Lite og en vedvarende 504 ved endring av kursperioden «Høst 3 2026». Databasekontrollene er **lesende**; cron-kjøreprøvene nedenfor kan oppdatere kalenderoppføringer og er merket særskilt. Kjør databasekontrollen mens en kontrollert reproduksjon pågår, og sammenhold med nøyaktig UTC-tid. Begrens gjentatte lagringsforsøk til én av gangen.

## Henvendelse til webhotellet

> Vi får 504 og PHP «Maximum execution time of 120 seconds exceeded» ved lagring i WordPress. Loggen 24.09.2026 kl. 19:49:56–19:53:06 UTC viser 32 «Lock wait timeout exceeded» ved oppdatering/sletting av Avada-transientene `_transient_timeout_fusion_dynamic_js_filenames` og `_transient_fusion_dynamic_js_filenames` i `wptb_19_options`. Klokken 19:53:12 UTC avbrytes PHP i wpdb. Kan dere under ett kontrollert nytt forsøk identifisere ventende og blokkerende databaseforbindelse/transaksjon, hvor lenge blokkeringen har vart, og hvilken PHP-forespørsel som eier den? Vi trenger også å vite om en tidligere forespørsel fortsatt kjører etter proxyens 504. Ikke avslutt forbindelser eller tøm køer før årsaken er identifisert.

Loggen viser både `wptb_19_` og `wptb_`-tabeller. Kontroller riktig nettsted/database; ikke anta at nettverkets hovedside har samme kø.

## Lesende databasekontroll

Utføres av host/databaseadministrator med nødvendige rettigheter. `trx_mysql_thread_id` kan brukes til å koble transaksjonen til prosess-/applikasjonsloggen. Disse spørringene henter ikke kursbeskrivelser, deltakere eller SQL-parametere.

```sql
SELECT VERSION();
SELECT trx_id, trx_state, trx_started, trx_wait_started, trx_mysql_thread_id,
       trx_rows_locked, trx_rows_modified
FROM information_schema.innodb_trx
ORDER BY trx_started;
```

For MySQL 8 med sys-skjema:

```sql
SELECT wait_started, wait_age_secs, waiting_pid, blocking_pid, locked_table
FROM sys.innodb_lock_waits;
```

For MariaDB/MySQL-versjoner med `INNODB_LOCK_WAITS`:

```sql
SELECT requesting_trx_id, blocking_trx_id
FROM information_schema.innodb_lock_waits;
```

Når riktig WordPress-database er valgt, og prefikset er bekreftet som `wptb_19_`, kan eier av RegiNors to navngitte låser leses slik:

```sql
SELECT
  IS_USED_LOCK(CONCAT('rnl:', MD5(CONCAT(DATABASE(), ':wptb_19_')))) AS course_connection,
  IS_USED_LOCK(CONCAT('rnl:tec:', MD5(CONCAT(DATABASE(), ':wptb_19_')))) AS calendar_connection;
```

NULL betyr at ingen forbindelse eier den navngitte låsen akkurat da. Dette utelukker ikke InnoDB-radlåser på options eller andre tabeller. Ikke kjør `KILL`, slett transients eller tøm `cron` som del av denne kontrollen.

## Lesende WordPress-kontroll

Under **RegiNor Lite → Nettsidevisning → Arrangementskalender → Teknisk status for kalenderkøen** vises RegiNors køstatus fra 0.1.18.

Alternativt fra WordPress-installasjonens mappe:

```bash
wp --url=https://www.salsanor.no cron event list --fields=hook,next_run_gmt,recurrence
```

Se etter `rnl_tec_sync_soon` og `rnl_tec_sync_periodic`. Dette er WordPress-cron, ikke Action Scheduler. `RNL-TEC-SCHEDULE` er en feilkode, ikke navnet på en jobb. En jobb i køen beviser ikke at cron kjører. Dersom besøksutløst cron er deaktivert, bekreft serverens separate cron-oppsett og riktig nettstedsadresse.

Behold eksisterende køer og kursdata. Ikke kjør alle cron-/Action Scheduler-jobber manuelt for å teste én kalenderfeil. Del bare nødvendige, redigerte utdrag; fulle prosesslister kan inneholde sensitiv SQL.

Kilder: [WordPress planlegging og feilkoder](https://developer.wordpress.org/reference/functions/wp_schedule_single_event/), [WP-CLI cron](https://developer.wordpress.org/cli/commands/cron/event/), [MySQL-navngitte låser](https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html).

Databasevisninger: [MySQL sys.innodb_lock_waits](https://dev.mysql.com/doc/refman/8.4/en/sys-innodb-lock-waits.html), [MariaDB INNODB_LOCK_WAITS](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-innodb-tables/information-schema-innodb_lock_waits-table).

## Bekreftet køproblem på nettsted 19

Ny køoversikt bekrefter nettsted 19 og deaktivert besøksutløst cron. Kalenderjobben er forsinket 54 minutter, LetsReg-kontrollen over tre dager og oppryddingsjobben nesten to dager. Flere jobber har altså ikke normal fremdrift. Administrator/host må kontrollere faktisk cron-kommando, intervall, siste kjøring, exitkode og riktig nettsted. Ikke anta at en jobb mot hovednettstedet kjører delnettstedets jobber.

`DISABLE_WP_CRON` stopper automatisk oppstart ved sidebesøk, ikke registrering av hendelser. Med denne innstillingen trenger nettstedet fungerende separat kjøring. WordPress beskriver [serverbasert cron-oppsett](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/). `wp cron test` undersøker besøks-/HTTP-spawning og kan avvise når konstanten er satt; den verifiserer ikke en separat serverjobb som bruker WP-CLI.

Bekreft først riktig kontekst fra WordPress-installasjonen. Stien under er hentet fra den mottatte loggen; host må bekrefte at den er riktig for serverjobben:

```bash
wp --path=/home/salsaybm/public_html --url=https://www.salsanor.no eval 'echo get_current_blog_id(), PHP_EOL;'
```

Forventet svar er `19`. Hvis svaret er et annet, finn riktig URL for nettsted 19 før videre kjøring.

### Én avgrenset kjøreprøve

Når ingen annen kontrollert prøve pågår, kan administrator velge **Run now** bare for `rnl_tec_sync_periodic` i cron-oversikten på nettsted 19. Alternativt kan host kjøre følgende én gang, med nødvendig tilgang, og ta vare på tidsbruk, exitkode og feilmeldinger:

```bash
wp --path=/home/salsaybm/public_html --url=https://www.salsanor.no cron event run rnl_tec_sync_periodic
```

Dette er en **skrivende kjøreprøve**: den oppdaterer RegiNors kalenderoppføringer på nettstedet. Den endrer ikke kursoppsettet og kjører ikke LetsReg-kontroll eller alle andre cron-jobber. Kommandoen er dokumentert, ikke kjørt på produksjonsserveren av Codex. [WP-CLI: kjør valgt hook](https://developer.wordpress.org/cli/commands/cron/event/run/).

Etter prøven kontrolleres **Siste start**, **Siste avslutning**, periodens kalenderstatus og selve TEC-oppføringen. WP-CLI «Executed» alene beviser ikke at TEC-lagringen lyktes; broen kan ha registrert en håndtert feil. Hvis prøven henger eller gir serverfeil, ikke start flere samtidig. La host fange blokkeringen mens den pågår.

- Fullført kalenderjobb, men cron forblir forsinket over tid: rett den automatiske serverkjøringen for nettstedet.
- Kalenderjobben starter og får en konkret TEC-feil: bruk denne feilen til videre integrasjonskontroll.
- Jobben eller «Til kladd» henger: undersøk ventende og blokkerende databaseforbindelser med kontrollene ovenfor.

Permanent cron-oppsett må avstemmes med hostens eksisterende jobb og WP-CLI-/PHP-stier. Kontroller at vanlige jobber faktisk flytter forfallstid over flere intervaller, ikke bare at cron-kommandoen finnes. Ikke legg en parallell serverjobb på toppen av et ukjent eksisterende oppsett.

Nyeste logg viser ti Avada-relaterte låsetidsavbrudd 24.09.2026 kl. 20:31:23–20:32:29 UTC og PHP-stopp kl. 20:32:42 UTC (22:32:42 CEST). Disse er fortsatt et separat bevispunkt ved siden av cron-problemet.

## Bekreftet oppstartsfeil i cPanel-cron

Prosjekteier har nå vist den eksisterende femminuttersjobben: `flock -n /tmp/wpcron-salsanor.lock` starter `/usr/local/bin/wp` direkte, med `--path=/home/salsaybm/public_html`, `--url=https://salsanor.no` og `cron event run --due-now --quiet`. Cronloggen melder at WP-CLI bruker **`cgi-fcgi` i stedet for `cli`** og avbryter. Dette bekrefter en feil før cron-hendelsene kjøres i disse forsøkene. Det er ikke bevis for hvor lenge feilen har vart, eller for årsaken til databaseventingen under kurslagring.

cPanel dokumenterer at `/usr/bin/php` bruker CGI og `/usr/local/bin/php` bruker CLI. Standard `PATH` kan være forskjellig i terminal og cron. Angi derfor PHP-binæren eksplisitt. `--ea-reference-dir` velger PHP-versjonen for WordPress-mappen i EasyApache-oppsett. Bekreft dette på kontoen før kommandoen tas i bruk; ved et annet PHP-oppsett må ProISP oppgi riktig CLI-sti.

**Bekreftet av prosjekteier i cPanel-terminalen:** Fra `public_html` viser `command -v php` `/usr/local/bin/php`, og `/usr/local/bin/php -v` viser **PHP 8.3.33 (cli)**. Samme kjøring viser fortsatt advarselen om dobbel OPcache-lasting. Riktig CLI-binær er dermed identifisert; WP-CLI-oppstart med denne, riktig nettsted og utføring av hendelser gjenstår å bekrefte. Kjøring fra terminalen viser ikke hvilken `PATH` cron bruker.

### Bekreft PHP og nettsted før kjøring

Kjør i cPanel-terminalen som nettstedets kontobruker:

```bash
/usr/local/bin/php --ea-reference-dir=/home/salsaybm/public_html -v
/usr/local/bin/php --ea-reference-dir=/home/salsaybm/public_html /usr/local/bin/wp --info
/usr/local/bin/php --ea-reference-dir=/home/salsaybm/public_html /usr/local/bin/wp --path=/home/salsaybm/public_html --url=https://www.salsanor.no eval 'echo get_current_blog_id(), PHP_EOL;'
```

Første kommando skal vise **`(cli)`** og en PHP-versjon som støtter installasjonen (RegiNor krever minst 8.2). Andre kommando skal vise WP-CLI-informasjon. Eksemplet forutsetter at `/usr/local/bin/wp` er PHP-/PHAR-startfilen; hvis host bruker et shell-omslag, må host oppgi den faktiske PHAR-stien. Siste kommando skal bekrefte **19**. `www` og adressen uten `www` må ikke antas å velge samme nettsted i WP-CLI; bruk den bekreftede adressen konsekvent.

OPcache-advarselen om dobbel lasting må undersøkes i PHP-konfigurasjonen hvis den fortsatt vises med riktig CLI-binær. Den er ikke dokumentasjon på årsaken til SAPI-valget. Ikke slå av alle PHP-innstillinger med `-n` som en generell omgåelse.

### Test bare kalenderjobben først

Etter bekreftet CLI og nettsted, kjør følgende én gang. Dette er den skrivende kalenderprøven beskrevet ovenfor, nå med eksplisitt PHP-CLI. Den bruker samme `flock` som serverjobben og utelater `--quiet`:

```bash
flock -n /tmp/wpcron-salsanor.lock /usr/local/bin/php --ea-reference-dir=/home/salsaybm/public_html /usr/local/bin/wp --path=/home/salsaybm/public_html --url=https://www.salsanor.no cron event run rnl_tec_sync_periodic
```

Kontroller WP-CLI-resultatet, RegiNors siste start/avslutning og faktisk kalenderstatus. Dersom `flock` avviser fordi en prosess kjører, vent på denne i stedet for å starte uten lås. Ikke slett låsefilen for å tvinge frem parallell kjøring.

### Oppdater eksisterende serverjobb etter kontroll av etterslepet

Køutdraget inneholder også `publish_future_post` med datoer fra april 2025 og flere andre utvidelsers jobber fra juni 2025. Før `--due-now` gjenopptas, kontroller hvilke gamle planlagte innlegg som fortsatt skal publiseres. En slik gjenopptakelse kan publisere innlegg som fortsatt står med fremtidig/planlagt status og passert publiseringstid. Bevar øvrige jobber; ikke slett etterslepet samlet.

Når CLI, nettsted, kalenderprøven og gamle publiseringer er avklart, **rediger eksisterende jobb**, behold intervallet `*/5 * * * *`, og bruk følgende i cPanels kommandofelt (én linje):

```bash
flock -n /tmp/wpcron-salsanor.lock /usr/local/bin/php --ea-reference-dir=/home/salsaybm/public_html /usr/local/bin/wp --path=/home/salsaybm/public_html --url=https://www.salsanor.no cron event run --due-now >> /home/salsaybm/logs/wpcron-salsanor.log 2>&1
```

`DISABLE_WP_CRON` kan beholdes aktiv når denne serverjobben fungerer. `--quiet` er utelatt mens driften verifiseres, slik at fullførte hendelser blir synlige i loggen. Bekreft at forfall flyttes over flere femminuttersintervaller og at RegiNor har nye vellykkede kontrolltidspunkter. Kommandoen dekker bare det bekreftede nettstedet, ikke automatisk hele multisite-nettverket. Produksjonskommandoene er dokumentert, ikke utført av Codex.

Kilder: [cPanel: PHP CGI og CLI i cron](https://support.cpanel.net/hc/en-us/articles/1500006210942-PHP-CGI-vs-PHP-CLI-What-s-the-difference-How-to-use-them), [cPanel: PHP-binærer og referansemappe](https://docs.cpanel.net/ea4/php/easyapache4-and-the-ea-php-cli-package/), [WP-CLI: valgt hook, due-now og nettsted](https://developer.wordpress.org/cli/commands/cron/event/run/).

### Resultat mottatt etter kjøreprøven

Prosjekteiers nye terminalutskrift bekrefter **nettsted 19** med den eksplisitte PHP-CLI-kommandoen. Kalenderprøven med `flock` returnerer én hendelse på **0,131 sekunder** og `Success`. En tidligere terminalprøve returnerer to hendelser på 4,928 og 2,146 sekunder. Ingen timeout vises i disse prøvene. Oppstartsvarsler vises fortsatt, inkludert dobbel OPcache-lasting.

Neste bekreftelse er periodens faktiske kalenderstatus og et nytt kontrollert «Til kladd»-forsøk. Når de gamle planlagte publiseringene er gjennomgått og cPanel-kommandoen er oppdatert, kontrolleres minst to automatiske femminuttersintervaller. En vellykket manuell terminalkommando beviser ikke at den lagrede cPanel-jobben er endret eller at automatisk kjøring virker. Ved fortsatt 504 følges databasekontrollen ovenfor; manuell cron-suksess identifiserer ikke den tidligere blokkerende forbindelsen.


## Avgrenset lagringsspor fra 0.1.19

For å knytte «Til kladd» til riktig databaseforbindelse:

1. Installer testutgave 0.1.19. Automatisk cron-testing er fortsatt på vent; dette krever ingen cron-kjøring.
2. Sett følgende i `wp-config.php`, før WordPress lastes. Endre eksisterende definisjoner hvis de finnes; ikke legg inn dubletter:

   ```php
   define('RNL_TRACE_MUTATIONS', true);
   define('RNL_TRACE_SITE_ID', 19);
   ```

3. Bruk eksisterende PHP-feillogg. Med `WP_DEBUG` og `WP_DEBUG_LOG` aktivt går linjene normalt til WordPress' konfigurerte debuglogg; ellers til PHPs `error_log`. Behold feilvisning til besøkende av. Konstantene slår ikke selv på generell PHP-logging.
4. Last periodeoversikten på nytt og kontroller faktisk lagret status. Hvis perioden fortsatt er publisert, gjør **ett** «Til kladd»-forsøk mens host leser aktive databaseventinger. Noter klokkeslettet. Ikke start flere samtidige forsøk.
5. Ta ut linjene med `RNL-MUTATION` for samme `trace_id`, samt nærmeste fatalfeil. Send `db_connection` og tidspunkt til host for sammenligning med `trx_mysql_thread_id` / `waiting_pid` / `blocking_pid`.
6. Sett `RNL_TRACE_MUTATIONS` tilbake til `false` eller fjern de to definisjonene etter forsøket. Håndter utdraget som midlertidig driftslogg og slett det etter avklaring.

| Siste trinn / hendelse | Hva det avgrenser |
| --- | --- |
| `lock.acquire` uten `lock.acquired` | Venter på eller feiler ved kurslåsen |
| `lifecycle.validate` | Lesing av periode/kurs og kontroll av rettigheter/versjoner |
| `metadata.write` uten `metadata.done` | Metadataoppdateringen eller hooks den utløser for `object_id` |
| `status.write` uten `status.done` | `wp_update_post()` og hooks den utløser for `object_id` |
| `commit` uten `committed` | Fullføring av databasetransaksjonen |
| `cache.clean` uten `cache.cleaned` | Cacheopprydding etter transaksjonen |
| `failed` | Operasjonen kastet et unntak på dette trinnet; se etter påfølgende `rolled_back` |
| `fatal` / `interrupted` | PHP-fatalfeil eller avslutning mens operasjonen fortsatt var aktiv |
| `finished` | RegiNor-operasjonen returnerte normalt; en eventuell senere feil må undersøkes etter dette tidspunktet |

`elapsed_ms` er samlet tid for operasjonen. `since_previous_ms` er tid siden forrige sporlinje, altså tiden brukt **før** det nå loggede trinnet. `transaction` viser om RegiNor har startet en transaksjon som ikke er bekreftet avsluttet. `error_type` er bare PHPs numeriske feiltype. Sporet identifiserer et trinn og en forbindelse, ikke automatisk den utvidelsen eller andre forbindelsen som er årsaken.


## Nytt etter første lagringsspor – 0.1.20

Lagringssporet 25. september avgrenser hovedventingen til hver kursstatusoppdatering inne i en åpen transaksjon. Kurslåsen tas raskt. Det lengste forsøket brukte 10–32 sekunder per `wp_update_post()` og hadde fortsatt ingen bekreftet commit etter nesten tre minutter. [Tidsmålinger og lokal retting](FEILSOKING-KLADD-OG-TEC.md#første-lagringsspor--25-september-05310535-utc).

0.1.20 retter unødvendig deaktivering av WordPress' interne cache under transaksjonen når standardcache brukes. Behold eksisterende sporingsflagg under ett kontrollert forsøk etter installasjon. Ta med hele `RNL-MUTATION`-sporet fra `begin` til `finished` eller siste linje ved feil.

- `object_cache=runtime` og `cache_addition_suspended=false` i `operation` bekrefter at rettingen brukes. `begin` viser tilstanden før transaksjonen.
- `object_cache=external` / `nonstandard` betyr at denne avgrensede cacherettingen ikke endrer backendens policy. Kontroller da faktisk objektcache og hook-tidsbruk videre. Dette feltet gjelder ikke sidecache/CDN.
- `db_queries` er antall SQL-kall siden operasjonen startet. Forskjellen mellom `status.write` og `status.done` viser samlet antall spørringer inne i statusoppdateringen og dens hooks. Ingen SQL-tekst logges.
- `cache.runtime_clear` betyr at bare WordPress' lokale minnecache for denne forespørselen tømmes ved transaksjonsgrensen.

Ikke bruk de gamle forbindelses-ID-ene som bevis på en nåværende lås; sammenhold nye spor og aktive databaseventinger samtidig. Automatisk cron-testing forblir på vent. Etter prøven slås den valgfrie sporingen av igjen.
