# RegiNor Lite

Les `README.md`, `docs/ROADMAP.md` og relevante kapitler i `docs/SalsaNor-kursoversikt-spesifikasjon.md` før endringer. Spesifikasjonen er foreløpig; dokumenter avklaringer og skill implementert funksjonalitet fra planer.

- Lever som WordPress-plugin i `plugin/reginor-lite/`, med PHP-rendering og begrenset JavaScript. Bruk namespace `RegiNor\Lite`, prefiks `rnl_` og text domain `reginor-lite`.
- Både backend og frontend skal være intuitive for personer med lite eller ingen digital kompetanse og ha et elegant, rolig uttrykk. Følg `docs/BRUKEROPPLEVELSE.md`: tydelig hovedhandling, vanlige ord, gode standarder, hjelp ved behov og tilgjengelighet.
- Bruk norsk bokmål i brukergrensesnitt og feilmeldinger. «Kursperiode» er administrasjonens navn på en runde.
- Bruk «Kurs», ikke «Gruppe», i brukergrensesnittet. Instruktør er valgfritt. Økter utenfor synlighetsvindu og salg etter kursstart er informasjon uten ekstra godkjenning; faktiske vindusgrenser håndheves fortsatt.
- Alle nye brukerrettede PHP-/JavaScript-tekster skal være oversettbare i `reginor-lite`. Behold driftsdata felles mellom språk; WPML oversetter bare utvalgte visningstekster. Se `docs/BRUKERFLYT-OG-SPRAK.md`.
- Kjør `npm run test:admin` ved endringer i periodeoversikt, nye publiseringsregler eller språkstøtte; oppdater POT med `npm run i18n:pot` ved tekstendringer.
- RegiNor Lite tas i bruk fra neste nye kursperiode. Ingen eksisterende kurs eller ACF-data skal migreres, omdøpes eller slettes. Gamle kurs og URL-er beholdes i dagens løsning.
- RegiNor erstatter ACF-kursløsningen for nye perioder og har ingen ACF-avhengighet. Avada og The Events Calendar videreføres. Kartlegg nødvendige grensesnitt, navnekollisjoner og instruktørreferanser før installasjon; ikke gjør importkartlegging til krav for ren domenelogikk.
- Hold faktiske økter som felles grunnlag for datoer, kollisjoner og schema. Fast ukeskalender er en presentasjon av gruppens ukemønster.
- Håndhev rettigheter på server med capabilities og objekt-ID, og hold offentlig tilgang konsistent i alle kanaler. Nonce alene er ikke tilgangskontroll.
- Ikke legg hemmeligheter, deltakerdata eller produksjonseksporter i repoet. LetsReg eier registrering, betaling og bekreftet kapasitet.
- Kjør `composer check` (eller `php scripts/lint.php` for syntaks), relevante funksjonstester og `npm run test:wordpress` ved endringer i bootstrap/WordPress-integrasjon. Testen krever `npm run env:start`.
- Kjør `npm run test:storage` ved endringer i lagring, metadata eller repository. Testen er begrenset til lokalt wp-env og rydder sine egne syntetiske testobjekter.
- Kjør `npm run test:journey` og `npm run test:analytics` ved endringer i kampanjemåling, samtykke eller statistikk. Sistnevnte bruker Complianz-stub og krever isolert lokalmiljø uten ekte Complianz. Bekreftede salg krever verifisert LetsReg-kontrakt.
- Kjør `npm run test:capacity` ved endringer i kapasitetsadapter, kontrollkø eller kapasitetstilgang. Demonstrasjonsdata skal bare vises i administrasjonen, aldri som ekte offentlig ledighet. Live API og webhooks krever separat verifisering.
- Kjør `npm run test:workflow` og `npm run test:http` ved endringer i roller, kurseditor eller publiseringsflyt. HTTP-testen krever lokalt wp-env på port 8888 og gjenoppretter sin midlertidige instruktørinnstilling. Ikke kjør den samtidig med andre tester i samme miljø.
- Kjør `npm run test:public`, `npm run test:public-http` og `npm run test:interface` ved endringer i offentlig visning, synlighet, schema eller utløp. Offentlig HTTP-test oppretter/rydder lokale testobjekter og gjenoppretter sin midlertidige kurssideinnstilling.
- Kjør `npm run package` ved endringer i installasjonsstrukturen. Utvid pakkeskriptets tillatte filer når nye runtime-filtyper innføres.
- Oppdater roadmap/status og endringslogg når en konkret leveranse er ferdig. Rapporter tester som ikke er kjørt; ikke marker planlagte kriterier som bestått.
