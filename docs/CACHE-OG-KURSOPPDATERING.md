# Cache og oppdatering av kursinformasjon

## Undersøkelse 21. september 2026

Prosjekteier bekrefter Cloudflare og LiteSpeed Cache. To anonyme lesekall mot `https://www.salsanor.no/kursrekke/` (det andre med en unik `rnl_refresh`-parameter) viste at HTML-ens `data-rnl-expiry` lå nøyaktig **60 sekunder** etter `data-rnl-now`. I koden fikk automatisk LetsReg-status uten gyldig observasjon en kunstig frist på ett minutt. Den korteste fristen gjaldt hele oversikten. JavaScript erstattet deretter innholdet med en omlastingsmelding.

Svarene omkring 23:04–23:05 norsk tid hadde `Cache-Control: no-store, private, max-age=0, must-revalidate` og `X-LiteSpeed-Cache-Control: no-cache`. HTML viste LiteSpeed Cache 7.9.1, Guest Mode og utsatt/kombinert JavaScript. Ingen `CF-Cache-Status`, `Age` eller dokumentert cache-HIT ble observert. Dette beviser kodefeilen og tilstedeværelse av LiteSpeed-optimalisering, men **ikke at Cloudflare serverte en gammel side**. Utsatt JavaScript kan gjøre at fristen allerede er passert når skriptet begynner å kjøre.

## Rettelse i 0.1.3

- Ukjent/utløpt LetsReg-status får ingen kunstig 60-sekundersfrist. Ukjent kapasitet forblir ukjent; utløpte tall eller lenker gjenåpnes ikke.
- Faktiske grenser for synlighet, salg, kursavslutning og gyldig kapasitet beholdes.
- Meldingen heter «Kursinformasjonen må oppdateres». Den hevder ikke lenger at en innholdsendring er bekreftet.
- Knappen legger til en ny `rnl_refresh`-verdi og beholder sti, filtre, kampanjeparametre og anker. Ingen automatisk omlastingssløyfe. Dette hjelper ikke dersom en CDN-regel uttrykkelig ignorerer query-parametre.
- Kursvisninger sender eksplisitt LiteSpeed `litespeed_control_set_nocache` samt `CDN-Cache-Control` og `Cloudflare-CDN-Cache-Control: no-store`. Eksisterende `DONOTCACHEPAGE`/WordPress no-cache beholdes. Se [LiteSpeeds API](https://docs.litespeedtech.com/lscache/lscwp/api/).
- JavaScript-versjonen følger filens endringstid, slik at nettleseren får den nye filen etter oppgradering.

## Oppsett på nettstedet

1. Installer ny pluginutgave.
2. LiteSpeed Cache → Cache → Excludes → **Do Not Cache URIs**: legg til `/kursrekke/` og `^/kursrekke$`. Ta også med den valgte kortkodesiden, andre sider med kursoversikt og eventuelle språkvarianter. Under **Do Not Cache Query Strings**, legg til `rnl_refresh`. Se [LiteSpeeds cacheinnstillinger](https://docs.litespeedtech.com/lscache/lscwp/cache/).
3. Ved Guest Mode/utsatt JavaScript: unnta kurssidene fra sideoptimalisering under **Page Optimization → Tuning → URI Excludes**. Det sørger for at utløpskontrollen og dens WordPress-avhengigheter kjører uten brukerinteraksjon. Kontroller at `interface.js` og `wp-i18n` faktisk kjører ved innlasting. Innstillingen berører bare oppgitte sider. Se [LiteSpeeds optimaliseringsinnstillinger](https://docs.litespeedtech.com/lscache/lscwp/pageopt/).
4. Cloudflare → Cache Rules: sett **Bypass cache** for `/kursrekke` og alle stier som starter med `/kursrekke/`, samt øvrige kurs-/kortkodesider og språkvarianter. Regelen må ikke overstyres av en senere generell cacheregel. Se [Cloudflare Cache Rules](https://developers.cloudflare.com/cache/how-to/cache-rules/settings/) og [regelrekkefølge](https://developers.cloudflare.com/cache/how-to/cache-rules/order/).
5. Tøm LiteSpeeds berørte side- og optimeringscache, deretter Cloudflares cache for berørte sider. Pluginens nye headere kan ikke fjerne HTML som allerede ligger foran WordPress.

## Kontroll etter installasjon

Åpne siden utlogget/i privat vindu. Gjenta innlasting, bruk oppdateringsknappen og la fanen stå åpen. Kontroller at en ukjent LetsReg-observasjon ikke gir nøyaktig ett minutts levetid, at en reell kapasitetsfrist fortsatt skjuler utløpte opplysninger, og at oppdatering gir en fersk visning. Prøv både kort, kalender, kursprofil og språkvarianter. Ved umiddelbart utløp med fersk HTML må også enhetens klokke kontrolleres.

Ingen cacheinnstillinger er endret eksternt. Lokal funksjonstest er ikke bevis for korrekt CDN-/Guest Mode-oppsett i produksjon. Kontrollen over gjenstår etter installasjon.

## Oppfølging 22. september – alle kursflater

Felles CachePolicy sender `Cache-Control: no-store, no-cache, private, max-age=0, must-revalidate`, `CDN-Cache-Control: no-store`, `Cloudflare-CDN-Cache-Control: no-store`, `Surrogate-Control: no-store` og `X-LiteSpeed-Cache-Control: no-cache`. ETag/Last-Modified fjernes. LiteSpeeds nocache-hook og DONOTCACHEPAGE brukes fortsatt.

Dette gjelder arkiv, kursperiode, enkeltkurs, kortkoder/blokker og arkiver med innebygd kursinnhold, kalendernedlasting, pluginens admin/AJAX og REST-svar inkludert feilsvar. WordPress REST-sider som renderer kurskortkoden får samme policy. Temaoutput bufres når en kursside er konfigurert, slik at en kortkode fra Avada-mal/widget også kan sette headere før responsen sendes. Vanlige sider uten kursinnhold får ikke no-store av dette. Et tema som eksplisitt flusher output før kortkoden må fortsatt unntas via cacheoppsettet eller filteret `rnl_no_cache_page`.

API-kontrollfristen på femten minutter utløser ikke lenger «Kursinformasjonen må oppdateres». Reelle dato-/synlighetsgrenser beholdes. Nye headere tømmer ikke allerede lagret edgecache. Cloudflare-regler som overstyrer origin kan også overstyre disse headerne; bruk derfor den dokumenterte Bypass-regelen og tøm eksisterende cache ved oppgradering.
