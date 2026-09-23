# LetsReg-tilkobling – M5.1 og kursflytene i M5.2

Automatisk påmeldingsstatus og overstyring på publiserte kurs er levert som egen oppfølging 20. september. Se [gjeldende statusflyt og oppdateringsregler](PAMELDINGSSTATUS.md).

Implementert lokalt 19. september 2026. **RegiNor Lite → LetsReg-tilkobling** gir administrator en manuell kontroll av API-tilgang og valgt arrangør. Den endrer ingen arrangementer, registreringer eller webhook-abonnementer.

## Slik tas kontrollen i bruk

1. Driftsansvarlig legger API-tilgangen i serverens miljø/hemmelighetskonfigurasjon eller i `wp-config.php` utenfor pluginrepoet. Bruk LetsReg-tilgangen som er avtalt for integrasjonen.
2. Administrator åpner **LetsReg-tilkobling**. Siden viser om oppsettet mangler eller er klart for kontroll. Ingen passordfelt, tokenverdier eller brukernavn skrives ut i siden.
3. Velg **Kontroller tilkoblingen**. RegiNor henter token og kontrollerer valgt arrangør og affiliate. Resultatet viser arrangørnavn ved suksess, eller en forklaring ved feil.
4. Under **Undersøk et LetsReg-arrangement** kan administrator skrive inn arrangementets numeriske API-ID. Kontrollen viser navn, status og priskategori-ID-er etter ny arrangør-/eierkontroll. Bruk navnesøk eller ID-oppslag som grunnlag for kurskoblingen nedenfor. Kapasitetssiden er fortsatt en demonstrasjon, og offentlig påmelding bruker LetsReg-lenken som før.

## Koble et lokalt kurs til LetsReg

Fase 1 er lokal opprettelse, etter prosjekteiers prioritering. Opprett perioden og kurset som vanlig.

1. Åpne kurset. **Påmelding hos LetsReg** står rett under «Pris og påmelding» i kursoppsettet. Søkefeltet foreslår kursnavnet.
2. Søk og velg arrangement. Tilgang, arrangør/affiliate og priskategorier kontrolleres automatisk, uten sidebytte eller egen kontrollknapp. Velg hvilke kategorier og roller som gjelder kurset.
3. Velg **Lagre koblingen**. Koblingen lagres direkte, også på publiserte kurs, uten ny periodekontroll eller republisering. Datoer, pris, synlighet og salgsstatus beholdes.

Påmeldingslenken hentes fra API-feltet `Event.eventUrl`. En gyldig lenke fylles automatisk inn når kursfeltet er tomt. Hvis en annen lenke allerede er utfylt, vises forskjellen og «Bruk denne påmeldingslenken» må velges før den erstattes. Valgt API-lenke lagres sammen med koblingen. Ved avbryting gjenopprettes automatisk utfylt lenke, med mindre brukeren har endret den selv. Manglende eller ugyldig API-lenke forklares og eksisterende lenke beholdes. HTTPS, ingen innloggingsopplysninger, tillatt LetsReg-domene og port kontrolleres; norske tegn kodes som i vanlig kurseditor.

Andre utfylte kursfelt blir stående under søk og lagring. De lagres fortsatt gjennom kursets ordinære lagre-/forhåndsvisningshandling. Versjonsfelt på siden oppdateres etter koblingslagring, slik at egne etterfølgende kursendringer ikke får en falsk versjonskonflikt. Reelle samtidige endringer avvises, med valgene bevart i skjemaet. En valgt, ulagret kobling varsles ved forsøk på å forlate siden.

Den separate **LetsReg-tilkobling**-siden er bare tilgjengelig for administratorens driftskontroll. Administrator har fortsatt en serverrendret reserveflyt uten JavaScript. Kursansvarlig får en forklaring om å aktivere JavaScript for søk/valg i kurseditoren, uten lenke til en side vedkommende ikke har tilgang til. Den nye, sammenhengende flyten er et JavaScript-tillegg til kurseditoren.

**Parpåmelding – per deltaker** betyr én deltaker per kategorivalg. Fører/følger velges separat fra enkelt/par. Ved første valg av arrangement forhåndsutfylles et redigerbart forslag fra kategorinavnet: «fører»/«følger» foreslår rolle, og «parpåmelding» eller eget ord «par» foreslår parpåmelding; ellers foreslås enkeltpåmelding. Store/små bokstaver og «par-påmelding»/«par påmelding» støttes. Forslaget merkes med forklaring og lagres gjennom den vanlige koblings-/importhandlingen. Manglende rolle, begge roller eller både enkelt- og parpåmelding i navnet krever manuelt valg. Lagrede koblinger, kategorier som er valgt bort og manuelle valg ved ny henting av samme åpne arrangement beholdes. Navneforslagene er ikke en bekreftet API-kontrakt eller kapasitetsberegning. Partneren legges fortsatt til separat hos LetsReg; pris per deltaker dobles ikke. Kategorien kan også velges som «Uten rollefordeling» for kurs uten partnerroller. Inaktive kategorier og inaktive/avlyste arrangementer kan ikke kobles.

**Delvis utfylt kobling:** Navneforslagene vises for hver kategori selv om én eller flere allerede er koblet. «Fyll ut forslag for kategorier uten rolle» tar med alle resterende aktive kategorier med entydig navneforslag i én handling. Allerede valgte roller/påmeldingsformer beholdes. Dette er et eksplisitt valg fordi lagringen ikke skiller tidligere utelatte kategorier fra kategorier som aldri ble vurdert. Knappen oppdaterer også prisforslag og ugyldiggjør eventuell gammel importforhåndsvisning. Kontroller valgene og bruk vanlig lagring/forhåndsvisning etterpå.

Administrator setter opp API-tilgangen og arrangørvalget. Både administrator og kursansvarlig kan deretter søke, hente priskategorier og opprette, bytte eller fjerne koblingen på et lokalt kurs de kan redigere. Kursansvarlig får den egne rettigheten `rnl_link_letsreg` gjennom rolleoppgradering versjon 2. Rolleversjon 3 gir også `rnl_import_letsreg` for enkeltimport og bulkimport. De får ikke API-innstillinger, generelt API-oppslag eller ordre-/deltakerdata. Koblingen er privat, ligger i kursets versjonerte oppsett og følger historikken. Offentlig katalog, schema og REST eksponerer den ikke. Kopiering av en periode nullstiller koblingene og påmeldingslenkene i den nye perioden.

En kontroll er gyldig for lagring i 15 minutter. En ny kontroll erstatter den forrige, også mellom faner/brukere. Grunnlaget for en lokal kurskobling er bundet til brukeren som hentet arrangementet; en annen bruker må gjøre eget oppslag. Direkte lagring krever samme kontrollreferanse, konto, kategorioppsett og kursversjon. Den eldre reserveflytens forhåndsvisning kontrollerer også periodeversjonen. Utløp eller endringer gir beskjed om å kontrollere på nytt. Lagrede koblinger kan fortsatt vises og vanlige kursendringer lagres etter utløp; kontrolltiden er ikke en garanti for fortsatt API-tilgang eller kapasitet. Eldre forhåndskontroller uten kontrollreferanse må hentes på nytt før første kobling.

**Søk og ventetid:** Søket viser kommende/pågående arrangementer fra `GET /organizers/{organizerId}/events`, med `Query`, `Offset` og `Limit=20`. Hvert treff må tilhøre konfigurert arrangør og affiliate. Bare ID, navn, aktiv/publisert/avlyst og en eventuell validert offentlig URL lagres. En full side viser mulighet for neste side; en tom siste side er tillatt. Dette er et manuelt utvalg, ikke en komplett kapasitetshenting. Navnesøk bruker ingen datofilter: leverandørens `FromDate`/`ToDate` gjelder arrangementets sluttdato og kan derfor ikke uten videre brukes som samsvar med lokal periodestart. Kontroller lokalt at valgt arrangement passer kurset.

Den innebygde søkeflyten tillater umiddelbart valg etter et vellykket søk. En felles kontogrense på ti oppslag per minutt og separat databaselås begrenser forespørslene. API-feil beholder tidligere backoff og `Retry-After`; interaktivt søk omgår ikke tilgangsfeil. Den separate driftssiden har fortsatt ett minutts ventetid mellom manuelle kontroller. Dette er lokal kvotestyring, ikke en bekreftet leverandørkvote. Søket gjør maksimalt tre HTTP-kall; arrangements-/kategorikontrollen maksimalt fire. Ingen API-kall skjer under lokal lagring.


## Importer et nytt kursutkast

Fase 2 er implementert for administrator og kursansvarlig, med enkeltimport eller samlet opprettelse av opptil 20 kursutkast. Kursperioden må være i kladd.

1. Åpne **Kurs** i perioden og velg **Hent nytt kurs fra LetsReg**.
2. Søk og velg ett arrangement i nedtrekkslisten, eller åpne **Velg flere kurs til samlet import**, kryss av for treff og velg **Kontroller valgte kurs**. Utvalget beholdes ved filtrering og henting av flere treff.
3. Kontroller ett kurs om gangen. Velg relevante priskategorier, rolle og enkelt-/parpåmelding. **Opprett ny fra LetsReg** fyller navn og beskrivelse fra API-et; teksten kan redigeres. Oppgi dansestil og kontroller nivå/partnerinformasjon. Du kan i stedet velge **Bruk eksisterende lokal beskrivelse**. Eksisterende beskrivelser overskrives ikke.
4. Velg sal og kontroller undervisningen. Navn, ukedag, tider, første dato og påmeldingsdatoer foreslås fra tilgjengelige LetsReg-data. Antall kvelder tar hensyn til kursfrie dager. En gyldig offentlig påmeldingslenke fylles når feltet er tomt. «Bruk fra-pris per person» bruker laveste pris i valgte kategorier, også ved parpåmelding; kontroller valuta og tillegg.
5. Velg **Forhåndsvis kursutkastet**, kontroller datoene og velg **Legg i importlisten**. Fortsett med **Kontroller neste valgte kurs**. Listen lar deg se, redigere eller fjerne hvert kurs før opprettelse. Ved enkeltimport kan du også velge **Opprett kursutkast** direkte.
6. Når alle valgte kurs er gjennomgått, velg **Opprett alle kursutkast**. Alle kurs, økter, koblinger og eventuelle nye beskrivelser lagres i én transaksjon. Ved feil rulles hele operasjonen tilbake, og listen beholdes i den åpne siden. Kursene ligger som kladder i perioden; publisering følger vanlig kontroll.

Beskrivelsen kommer fra det nullable feltet `Event.description` i [LetsRegs OpenAPI 2.2.8](https://integrate.deltager.no/swagger/v1/swagger.json), kontrollert 19. september 2026. HTML fjernes, avsnitt og linjeskift beholdes som ren tekst. Bilder, skript og innebygd innhold importeres ikke. Manglende, ugyldig eller for stor tekst gir tomt beskrivelsesfelt som kan fylles manuelt. Det utledes ikke dansestil eller nivå fra friteksten. Hvert valg av ny beskrivelse oppretter et eget beskrivelsesutkast; ingen automatisk navnebasert sammenslåing. Saler opprettes ikke automatisk.

Import krever JavaScript; uten dette brukes vanlig «Legg til kurs». Utvalget og redigeringen ligger i den åpne siden, ikke som en varig importjobb. Navigasjon varsler om ulagrede valg. Ingen kurs lagres ved søk, gjennomgang eller ved å legge dem i listen.

**Kurs som allerede har startet (20. september):** Importen beregner hele kursrekken fra første kursdato, ikke fra dagens dato. «Antall undervisningskvelder» er totalen, inkludert tidligere datoer; kursfrie dager hoppes over. Import kan også registrere en avsluttet rekke. Forhåndsvisningen viser hvor mange starttidspunkter som har passert og hvor mange som kommer senere, regnet ved det oppgitte kontrolltidspunktet. Dette er planlagte datoer, ikke bekreftet oppmøte eller gjennomføring. Tidligere datoer blokkerer ikke ny import. Etter lagring bevares de ved omplanlegging, og kommende kvelder kan fortsatt endres. Synlighetsdatoene styrer når kursoversikten vises på nettsiden og begrenser ikke hvilke undervisningsdatoer som kan registreres innenfor kursperioden. Om periodens synlighet ikke dekker alle kveldene, vises forklaring med berørte datoer og feltene «Synlig fra»/«Synlig til»; ingen ekstra godkjenning kreves.

Kontrollert etter rettelsen: 86 importkontroller, 58 lagringskontroller, 73 arbeidsflytkontroller, 90 admin-/språkkontroller, 40 HTTP-kontroller, WordPress-oppstart og alle DOM-prøver bestått. `composer check` besto; utvidede enhetsprøver besto deretter med 140 tester og 266 assertions. Testene bruker syntetiske data og fast klokke, og har ikke importert brukerens faktiske arrangement.

Hvert importert arrangement får et signert kontrollgrunnlag bundet til brukeren som henter arrangementet og API-oppsettet, gyldig i 15 minutter fra henting. Senere søk eller valg av et annet arrangement ugyldiggjør ikke de øvrige grunnlagene. Bekreftelse kontrollerer fortsatt konto, utløp, periode og ressursversjoner. Påvist tilgangsfeil stopper bekreftelse. Ved utløp bruker du **Rediger** på kurset i listen: grunnlaget hentes på nytt, mens lokale felt og kategorivalg beholdes der kategoriene fortsatt finnes. Gjennomgå og legg kurset tilbake i listen. Den eksisterende, lokale koblingsflyten bruker fortsatt siste arrangementsforhåndskontroll. Ingen API-kall skjer under lagring.

Samme arrangement/konto med overlappende priskategorier kan ikke importeres flere ganger til samme periode, heller ikke internt i samme bulkimport. Vernet inkluderer papirkurven. Listen tillater én oppføring per arrangement; deling av ett arrangement i flere lokale kurs gjøres separat med ulike kategorier. Publiseringskontrollen kontrollerer også kollisjoner mellom de nye kursene. Kollisjoner med eksisterende kurs vises i den enkelte forhåndsvisningen og hindrer ikke opprettelse som kladd.

Importflyten er testet med syntetiske HTTP-svar og midlertidige lokale data. Visuell brukertest og import med faktisk LetsReg-arrangement gjenstår. Kapasitet, automatisk tokenfornyelse og webhooks aktiveres ikke av import. Se [felles arkitektur](LETSREG-API-M5.md#to-arbeidsflyter-felles-koblingsmodell).

## Lokalt testmiljø / Docker

I dette repoet bruker vi **`.env.letsreg.json` i prosjektroten** til de lokale tilgangsverdiene. Filen er ignorert av Git og tas ikke med i pluginens ZIP.

```bash
npm run env:configure
# Åpne .env.letsreg.json i editoren og fyll inn verdiene.
npm run env:start -- --update
```

Oppsettskommandoen oppretter tilgangsfilen fra `.env.example` og `.wp-env.override.json` fra `.wp-env.override.example.json`, med filrettigheter 0600. Eksisterende filer overskrives aldri. Hvis du allerede har en override-fil, legg til de to `mappings` fra malen i den eksisterende filen. Behold andre lokale innstillinger.

Tilgangsfilen er JSON, med disse fem nøklene:

```json
{
  "RNL_LETSREG_AFFILIATE_ID": "",
  "RNL_LETSREG_ORGANIZER_ID": "",
  "RNL_LETSREG_USERNAME": "",
  "RNL_LETSREG_PASSWORD": "",
  "RNL_LETSREG_CLIENT_ID": ""
}
```

Fyll inn de fire første. Brukernavnet oppgis uten affiliate-prefiks. Klient-ID kan stå tom. Bruk vanlig JSON-escaping for `"` og `\`; `$`, `&`, `+` og backticks er vanlige tegn og kjøres aldri som shell-kode. Tom mal gir «API-tilgang mangler» og utløser ingen forespørsler. Etter første Docker-oppstart med mappingene blir filendringer lest ved neste forespørsel; passordendring trenger ikke omstart.

Docker monterer filen som `/var/www/rnl-letsreg.json`, **utenfor** `/var/www/html`. En lokal MU-loader (`scripts/local-letsreg.php`) leser JSON og setter de fem miljøvariablene i både WordPress og WP-CLI. Loaderen virker bare ved `WP_ENVIRONMENT_TYPE=local`, kjører ingen API-kall og inngår ikke i den installerbare pluginen. Verken filen eller innholdet lagres i databasen. Ikke legg credentials i `.wp-env.json` eller `config` i override-filen: wp-env 11.15.0 bygger shell-kommandoer av disse verdiene. En vanlig `.env` på vertsmaskinen videresendes heller ikke automatisk til containerne.

Åpne deretter **RegiNor Lite → LetsReg-tilkobling → Kontroller tilkoblingen**. Bruk avtalt testtilgang fra LetsReg. Produksjon/staging bruker fortsatt serverens hemmelighetsoppsett eller `wp-config.php`, som beskrevet under.

De syntetiske `test:letsreg`-prøvene erstatter miljøvariablene bare inne i testprosessen, avskjærer alle HTTP-kall og gjenoppretter tidligere status. De kan kjøres med denne lokale JSON-løsningen. Permanente `RNL_LETSREG_*`-konstanter i `wp-config.php` må ikke brukes i dette testmiljøet; testen stopper da før nettverk. Konstanter har fortsatt forrang i vanlig drift.

## Serveroppsett

| Konfigurasjon | Verdi |
| --- | --- |
| `RNL_LETSREG_AFFILIATE_ID` | Forventet positiv affiliate-ID fra LetsReg. Ikke anta standardverdien 1. |
| `RNL_LETSREG_ORGANIZER_ID` | Forventet positiv arrangør-ID for SalsaNor. Kontrolleres mot svaret. |
| `RNL_LETSREG_USERNAME` | API-brukernavn **uten** `affid:`-prefiks. |
| `RNL_LETSREG_PASSWORD` | API-brukerens passord. Mellomrom og spesialtegn bevares ved skjemakoding. |
| `RNL_LETSREG_CLIENT_ID` | Valgfritt, utelates som standard. Sett bare etter avklaring med LetsReg; Swagger UI bruker `swagger`. |

Konstanter med disse navnene har forrang over miljøvariabler. Manglende eller ugyldig oppsett stopper forespørselen før nettverk. Ingen hemmeligheter skal legges i repoet, en vanlig WordPress-option eller deles i chat. Ingen automatisk import fra Betait-pluginens lagrede credentials er implementert.

API-opprinnelsen er fast `https://integrate.deltager.no`, og tokenkallet går til `/swagger/token`. Disse adressene velges ikke fra et formfelt eller en kurslenke, og det finnes ingen automatisk fallback til legacy-adressen. TLS kontrolleres; redirects følges ikke. Avklar separat testkonto/testmiljø og at denne tokenadressen er egnet for faktisk serverdrift før produksjonsbruk. Se [kildegjennomgangen](LETSREG-API-KONTRAKT.md).

## Hva kontrollen gjør

POST-kallet bruker `application/x-www-form-urlencoded` med `grant_type=password`, sammensatt `username=affid:username`, `password` og eventuell eksplisitt `client_id`. Det sendes ikke et separat `affid`-felt til den dokumenterte Swagger-tokenadressen.

Svaret må inneholde et gyldig Bearer-token og `token_type=bearer`. Når `expires_in` finnes, må det være et gyldig positivt antall sekunder med mer enn ti sekunder igjen før arrangørkallet. Dersom levetid mangler, brukes tokenet bare til den umiddelbare kontrollen. Ingen levetid antas, og ingen refresh-flyt er implementert.

Tokenet brukes til `GET /organizers/{organizerId}`. Både `id` og `affiliateId` må stemme med serveroppsettet. HTTP 200 eller et mottatt token alene gir ikke bekreftet arrangørtilgang. Rå organisasjonsdata, bankkontoer og kontaktopplysninger lagres ikke. Bare et renset arrangørnavn beholdes ved suksess.

Token og passord ligger bare i minnet mens kontrollen kjører. Ingen token-cache i database, transients eller objektcache er innført. Hver tillatte kontroll henter nytt token. Dette er en avgrenset administrativ testflyt med maksimalt fire HTTP-kall (token, arrangør, arrangement, priskategorier); automatisert polling må få separat tokenlevetid-/gjenbruksmodell etter verifikasjon mot leverandøren. Andre plugins som logger WordPress HTTP-kall må også gjennomgås før ekte credentials tas i bruk.

## Arrangement og priskategorier – første del av M5.2

Et numerisk arrangement-ID mellom 1 og 2147483647 sendes til det faste `GET /events/{eventId}` etter token-/arrangørkontroll. Returnert `id`, `organizer.id` og `organizer.affiliateId` må stemme. Deretter leses `GET /events/{eventId}/prices`. Ingen ID utledes fra påmeldingslenkens kursnavn, og ingen eksterne URL-er fra svaret åpnes.

Bare en validert offentlig `eventUrl`, arrangement-ID, renset navn, de tre boolske feltene `active`, `published`, `isCancelled` og priskategorienes `id`, navn og `active` lagres. Priskategoriene må være en JSON-liste med unike positive int32-ID-er; en tom liste er tillatt. Inntil videre avvises svar med mer enn 200 kategorier samlet, uten trunkering. Endepunktet har ingen pagineringsparametere i OpenAPI 2.2.8. Embedded `prices` på arrangementet brukes ikke som reserve ved feil. Offentlig skjema ble kontrollert på nytt 19. september 2026; feltene var uendret.

Manglende/feil eier, ugyldige felter, API-feil og delvise svar fjerner forrige arrangementsforhåndsvisning. Kontroll av et nytt arrangement omgår ikke ventetiden. Tokenlevetid kontrolleres før hvert GET-kall når `expires_in` er oppgitt, uten automatisk refresh. Manglende levetid tillater bare den umiddelbare, avgrensede kontrollen; token gjenbrukes ikke mellom kontroller. Endret serveroppsett gjør resultatet ugyldig, også ved endring underveis. Etter 15 minutter merkes det lagrede resultatet som historisk.

Selve oppslaget oppretter ingen kurskobling. Administrator eller kursansvarlig velger deretter kategorier, rolle og påmeldingsform i kursoppsettet. Priskategori tolkes ikke som kapasitetspool. Kapasitetsfelt lagres separat som en minimal observasjon for kurs med automatisk status. Ingen deltakerinformasjon, ordredata eller bekreftede salg inngår. Eksakte rolle-/pooltall krever fortsatt verifisert semantikk.

## Status, låsing og feil

- Bare administrator (`manage_options`) kan lese driftssiden eller starte generell tilkoblingskontroll. Kursansvarlig kan bruke avgrenset søk og arrangementsoppslag fra et redigerbart lokalt kurs. AJAX krever både `rnl_link_letsreg`, kursrettigheter, tilgang til objektet og korrekt nonce. Søk/oppslag bruker serverens arrangør/affiliate; klienten kan ikke velge API-vert eller konto. Import krever i tillegg `rnl_import_letsreg`, rett til å opprette kurs og tilgang til perioden. Disse kravene gjelder både kontroller og lagringslag. Nye beskrivelser kan opprettes gjennom import, men eksisterende felles ressurser blir ikke redigerbare.
- Én separat databaselås hindrer samtidige tokenforsøk. Nettverkskall holder ikke den globale kurslåsen; forsøk inne i en kursmutasjon avvises.
- Forsøket lagres før sending. Hvis lagring feiler, sendes ikke credentials. Avbrudd vises som ufullført kontroll og gir ikke falsk suksess.
- Manuell kontroll begrenses til tidligst etter ett minutt. Gjentatte feil gir eksponentiell ventetid med jitter. `429/5xx` respekterer gyldig `Retry-After`; `400` fra tokenendepunktet og `401/403` gir tilgangsfeil med minst fem minutters ventetid. Det finnes ingen automatisk retry-/refresh-løkke.
- Siste forsøk og siste vellykkede kontroll holdes adskilt. Feil opphever gjeldende bekreftelse selv om siste suksessdato beholdes.
- «Tilgangen ble bekreftet» beskriver kontrolltidspunktet. Etter 15 minutter ber siden om ny kontroll. **Dette er en lokal statusgrense, ikke tokenets levetid eller en garanti for fortsatt tilgang.** Fremtidige kapasitetsjobber må gjøre sin egen autoritative tilgangskontroll.
- Konfigurasjonen bindes til en HMAC med WordPress-salt. Endret passord, brukernavn, konto, klient-ID eller salt ugyldiggjør gammel bekreftelse. Endring under et pågående forsøk får ikke lagre en bekreftelse for det nye oppsettet.

Den private optionen `rnl_letsreg_connection` har `autoload=false` og inneholder kun fingeravtrykk, kontrollstatus, trinn, generisk feilkode, tider, feilantall, kontrollerende bruker-ID, eventuelt arrangørnavn, kontrollreferanse og siste fullførte arrangementsforhåndsvisning eller minimale søkeresultat. Den er ikke registrert som offentlig REST-innstilling. Det finnes ingen rå forespørsels-/responslogg i denne funksjonen.

## Verifikasjon og neste port

`test:letsreg` besto 160 lokale kontroller med syntetiske HTTP-svar: skjemakoding, tokenvalidering, organisasjon/affiliate, feil og ventetid, fravær av hemmeligheter i lagring/HTML, rettigheter, endring av credentials under kontroll, globalt låsevern, separat samtidig databaseforbindelse og mislykket lagring før sending. Prøven dekker også arrangementets ID/eier/affiliate, hele priskategorilisten, ugyldige/dupliserte ID-er, tom liste, delvis/feil svar, utløpt forhåndsvisning og felles ventetid. Navnesøk prøves også med norske tegn, mellomrom og spesialtegn, paginering, tom side, feil eier, ugyldige/dupliserte treff og felles ventetid. `test:letsreg-mapping` besto 106 kontroller av kategorivalg, én deltaker per parkategori, signert forhåndsvisning, rettigheter, kildeutløp/endringer, historikk, kopiering, ordinær redigering og fravær av koblingsdata i offentlig katalog. Ingen av disse testene kontakter LetsReg.

`test:letsreg-import` besto 57 kontroller: forhåndsvisning uten skriving, faktisk øktplan med opphold, periodens grenser, påkrevd sal, duplikater også i papirkurven, endret kilde/konto/ressurs/periode, ugyldig signatur, rettigheter, rensing av beskrivelser, flere uavhengige importgrunnlag, samlet opprettelse av beskrivelser/kurs og full tilbakeføring ved feil i andre kurs. DOM-testen under `test:interface` dekker felles søk, utfyllingsforslag, manuell plassering, samlet forhåndsvisning, foreldet forhåndsvisning, feilbevaring og bekreftelse. En egen bulk-DOM-test dekker flervalg, nye/eksisterende beskrivelser, importliste, oppfrisking med bevarte felt og samlet opprettelse. `composer check` besto 140 tester / 261 assertions; lagring (58), arbeidsflyt (73), HTTP (40), administrasjon/språk (90), WordPress-oppstart og offentlig visning (46) besto også etter importendringen. Dette er automatiserte lokale prøver, ikke visuell brukertest eller en faktisk leverandørimport.

Prosjekteier har rapportert en vellykket faktisk kontroll mot **SalsaNor Oslo**. Administrasjonen viste siste forsøk **18.09.2026 22:47:03** og siste vellykkede kontroll **18.09.2026 22:47:04**. Tidene gjengis som vist, uten antatt tidssone. Resultatet bekrefter at tokeninnlogging og kontroll av konfigurert arrangør-/affiliate-ID fungerte ved dette forsøket. Dette er brukerrapportert testbevis; kodeagenten har ikke utført en ny innlogging eller lest credentials/rå tokensvar.

Prosjekteier har deretter levert skjermbilde av en vellykket arrangementskontroll for **Rueda Øvet 1, arrangement-ID 613037**. Resultatet viser aktivt: ja, publisert hos LetsReg: ja, avlyst: nei. Følgende priskategorier vises som aktive:

| API-ID | Navn som vist i kontrollen |
| --- | --- |
| 1401566 | Rueda Øvet1 som Fører |
| 1401567 | Rueda Øvet1 som Følger |
| 1401568 | Parpåmelding Rueda Øvet1 som Fører |
| 1401569 | Parpåmelding Rueda Øvet1 som Følger |

Dette dokumenterer en faktisk manuell henting av arrangement og priskategorier gjennom kontrollflyten. Skjermbildet viser ikke kontrolltidspunkt eller kapasitetstall. ID-ene er testbevis fra prosjekteier, ikke hardkodede standarder eller en automatisk kobling til et lokalt kurs.

**Avklaring fra prosjekteier om parpåmelding:** Prisen gjelder én deltaker i et par. Partneren må registreres separat med «LEGG TIL DELTAKER» før «SEND PÅMELDING». En parkategori er dermed ikke en billett for to personer. For dette arrangementet er grunnlaget for eksplisitt kurskobling:

| API-ID | Rolle | Påmeldingsform | Deltakere per kategorivalg |
| --- | --- | --- | --- |
| 1401566 | Fører | Enkeltpåmelding | 1 |
| 1401567 | Følger | Enkeltpåmelding | 1 |
| 1401568 | Fører | Parpåmelding, pris per deltaker | 1 |
| 1401569 | Følger | Parpåmelding, pris per deltaker | 1 |

Et par består av to deltakerregistreringer. RegiNor skal holde rolle og påmeldingsform adskilt, vise eventuell parpris som pris per deltaker og ikke multiplisere én parkategori med to ved deltakertelling. Dette er en avklaring av påmeldings-/prismodellen for det viste oppsettet, ikke dokumentasjon av API-feltenes kapasitetsberegning. Om enkelt- og parkategorier deler kapasitetsgrenser, og hvordan `available` påvirkes av registreringer/reservasjoner, må fortsatt verifiseres. Ledighet på kategoriene skal ikke summeres uten denne avklaringen.

Faktisk tokenlevetid/refresh, minste rettigheter, avtalt testmiljø og kapasitetssemantikk gjenstår. Et vellykket tokenkall dokumenterer ikke om `expires_in` var med i svaret eller hvilken levetid som ble oppgitt. Visuell brukertest av den nye siden gjenstår. **Manuell tilgangs- og arrangementskontroll er bekreftet gjennom prosjekteiers testresultater; L1 som helhet og resten av live-integrasjonen er ikke godkjent.**

Øvrige kontroller: 138 enhetstester / 256 assertions, 93 PHP-filer med syntakskontroll, WordPress-smoketest, 90 administrasjonskontroller, 58 lagringskontroller 73 arbeidsflytkontroller, 34 kurs-HTTP-kontroller, 58 transportkontroller, 39 kapasitetskontroller og 43 adminmeny-HTTP-kontroller besto. HTTP-prøven dekker korrekt menyrute, uautorisert tilgang, ugyldig nonce og ugyldige arrangements-ID-er/søkeparametere. Manglende serveroppsett prøves med syntetisk konfigurasjon; vellykket API-svar prøves med HTTP-stub i `test:letsreg`. Oversettelsesmal og installasjonspakke er oppdatert.

`test:local-config` besto to tester av filrettigheter og bevaring av eksisterende konfigurasjon/symbolske lenker. Docker ble startet med mappingene; WP-CLI bekreftet lesbar fil på `/var/www/rnl-letsreg.json` og innlasting til miljøet uten utskrift av verdier. HTTP-prøver av `/rnl-letsreg.json` og `/.env.letsreg.json` ga 404; direkte kall til MU-loaderen ga tom respons. Kodeagentens automatiserte prøver brukte ingen reelle credentials; prosjekteiers faktiske tilgangskontroll er dokumentert separat over.

### UX-oppfølging etter prosjekteiers gjennomgang

`letsreg-picker.test.cjs` kjører den faktiske klientsiden i jsdom med syntetiske svar: søk/valg, URL-autofyll og eksplisitt erstatning, avbryt, feilmeldinger, bevaring av kursfelt og oppdatering av riktige versjonsfelt. WordPress-testen verifiserer direkte kobling og URL-lagring på publisert kurs uten annen dataendring, nonce/rettigheter, historikk, kildeutløp og avvisning av utrygge API-lenker. Ingen autentiserte LetsReg-kall ble sendt av disse testene. Browser-ferdigheten kunne ikke starte; visuell nettleserprøve og brukertest er derfor fortsatt åpne.


### Kursoppsett og utfyllingsforslag

Rekkefølgen er **Undervisning → Pris og påmelding → Påmelding hos LetsReg → Kursfrie dager og flere valg → Utseende**. Fremheving og drop-in er undervisningsvalg. Utseende styrer bare egen bakgrunn. Sal velges lokalt; prisgrunnlag og lenkeomfang beholder standardene per person og valgt kurs for nye perioder/kurs.

Kursstart følger første valgte ukedag fra kursperiodens start. Bruk bare «En annen første kursdato» for avvik. Ingen ekstra «Foreslått startdato» vises i kursoppsettet. Periodegrensene og valideringen gjelder også for dette feltet.

Velg arrangement og kategorier under Påmelding hos LetsReg. Åpne deretter **Fyll ut kursoppsettet fra LetsReg**, velg hvilke forslag som skal brukes, og bruk dem i skjemaet. Navn, ukedag, start/slutt, første kursdato og påmeldingsdatoer foreslås når gyldige API-felt finnes. Kursnivå foreslås ved entydig treff mellom arrangementsnavnet og aktive lokale nivåer, med eksisterende valg beholdt. Se [nivåforslag](KURSNIVAER.md#nivåforslag-fra-letsreg). Kursantallet antar én undervisningskveld per uke mellom start og slutt, minus kjente kursfrie dager, og må kontrolleres i datoforhåndsvisningen. Ingen ukentlig gjentakelse er bevist bare av arrangementets start/slutt.

Tidspunkt med offset konverteres til kursets lagrede tidssone. API-tidspunkt uten offset foreslås som lokal tid i samme sone, forklart i grensesnittet. Ugyldige datoer gir ikke forslag. Ved endring av tidssone må denne lagres før nye forslag hentes.

**Bruk fra-pris per person** tar minimum av de valgte aktive priskategoriene, bare når alle har et gyldig prisbeløp. Parpris dobles ikke. Forslaget bruker NOK; valuta, avgiftsbehandling og tillegg må kontrolleres mot LetsReg, ettersom OpenAPI-feltene `price`/`vat` ikke alene dokumenterer dette. Prisvilkår beholdes. Deltakerne ser én fra-pris, ikke hele pristabellen.

Valgfrie egne påmeldingsdatoer ligger under Pris og påmelding. De kan begrense periodens påmeldingsvindu, men ikke utvide det eller åpne en periode uten oppsatte salgsdatoer. Forslaget velger «Automatisk fra LetsReg». Egne datogrenser er valgfrie og ikke forhåndsvalgt; API-datoene følges automatisk. Eksisterende manuelle statusvalg beholdes. Tomme kursdatoer følger perioden. Kopiering av perioden nullstiller disse datoene.

Forslag endrer bare de valgte feltene i kursutkastet på skjermen. De lagres med vanlig forhåndsvisning og bekreftelse. Direkte lagring av LetsReg-koblingen fortsetter å endre bare kobling og eventuelt offentlig URL, også på publiserte kurs. Status kan endres separat i kursoversikten uten kladdflyt. Øvrige kursendringer følger eksisterende kladdflyt. Polling gjelder kurs med automatisk påmeldingsstatus; pris synkroniseres ikke automatisk.

Automatiske prøver dekker skjemarekkefølge, felter utenfor selve form-elementet, datovalidering/fokus, progressive opphold, bevaring av lokale valg, prisforslag, tidssonekonvertering og påmeldingsgrenser. API-prøvene bruker syntetiske HTTP-svar. Visuell nettleserprøve, reell pris-/datokontrakt og brukertest gjenstår.


Testbevis for denne oppfølgingen: 140 enhetstester / 261 assertions, 58 lagringskontroller, 73 arbeidsflytkontroller, 39 kurs-HTTP-kontroller, 90 administrasjonskontroller, 46 offentlige kontroller, 37 offentlige HTTP-kontroller, 160 LetsReg-tilgangskontroller og 102 koblingskontroller besto. JavaScript-prøvene inkluderer faktisk DOM-kjøring av utfyllingsforslag, pris per person, skjematilknytning, datoavvik, fokus og progressive opphold. WordPress-smoketest besto. Tester bruker lokale syntetiske kurs og rydder etter seg.


### Nedtrekksliste og periodefilter i kurssøket

Treffene vises i en vanlig nedtrekksliste med navn, oppstartsdato og ID. Listen lukkes ved valg og flytter fokus til det valgte arrangementets oppsett når henting lykkes. Ved feil beholdes kursfeltene, og arrangementet kan velges på nytt.

Bryteren **Kun treff i valgt kursperiode** er på som standard. Den bruker arrangementets oppstartsdato, ikke synlighets- eller salgsdatoene: periodens start og eventuell sluttdato er inkludert. Uten sluttdato brukes bare startgrensen. Datoer med offset konverteres til periodens tidssone; tidspunkt uten offset tolkes som lokal tid. Ingen dato konstrueres hvis LetsReg mangler oppstart. Slå av bryteren for å vise slike treff og treff utenfor perioden.

API-søket henter fortsatt 20 arrangementer om gangen med eksisterende tilgangs- og ratekontroll. Bryteren filtrerer de hentede treffene lokalt, uten nytt API-kall. **Hent flere treff** henter neste side og beholder tidligere treff i samme nedtrekksliste. En tom filtrert side oppgis ikke som et komplett tomt søk når API-et har flere sider. LetsRegs eksisterende søkeavgrensning beholdes; det sendes ikke `ToDate`, som gjelder arrangementsslutt og kunne ha ekskludert kurs med riktig oppstart.

Regresjonsprøver dekker native select, datoetiketter, begge grensedager, manglende dato, av/på uten nettverkskall, flere sider, tom filtrert side, utrygge navn og nytt forsøk etter feil. Serverprøven kontrollerer blant annet UTC-dato som blir neste kalenderdag i Europe/Oslo og faktiske periodegrenser fra lagringen. Visuell nettleserprøve gjenstår.

Testet for søkeendringen: 106 LetsReg-koblingskontroller, 73 arbeidsflytkontroller, 39 kurs-HTTP-kontroller og hele JavaScript-suiten besto. Syntakskontroll av 96 PHP-filer besto. Ingen reelle LetsReg-kall ble sendt av testene.

## Endringer etter kobling og import

Se [endringskontroll og beskrivelsesgjenbruk](LETSREG-ENDRINGER-OG-BESKRIVELSER.md). Alle koblede kurs inngår nå i avgrenset bakgrunnskontroll, også ved manuell påmeldingsstatus. Kursansvarlig kan godkjenne endringer av dokumentert importerte beskrivelser med vern av lokale redigeringer og tilgangskontroll for delte kurs. Ingen generell ressursredigering følger denne avgrensede retten.
