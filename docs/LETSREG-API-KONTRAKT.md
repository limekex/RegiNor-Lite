# LetsReg – kontrollert API-kontrakt og eksisterende integrasjon

Kontrollert **19. september 2026, Europe/Oslo**. Dette er en kildekontroll uten credentials, ikke en bekreftet innlogging på SalsaNors konto.

**Tillegg 20. september:** LetsRegs [offisielle API-veiledning](https://help.letsreg.com/nb/articles/13598282-api-integration) bekrefter `https://legacyapi.deltager.no/token` med separat `affid`, bearer-token og ny autentisering ved utløp. Tabellen under sammenligner Betait med Swagger, som beskriver en annen tokenadresse. Eksisterende fungerende serverkonfigurasjon beholdes. Se [oppdatert kildegjennomgang](M5B-OFFENTLIG-RESEARCH.md).

## Kilder

- [Leverandørens Swagger UI](https://integrate.deltager.no/index.html) laster [OpenAPI-dokumentet](https://integrate.deltager.no/swagger/v1/swagger.json) via `index.js`.
- Hentet dokument: **Deltager Integration API 2.2.8**, OpenAPI **3.0.4**. SHA-256: `d821b83dbd2efee77bdfec2eba15a4e41f3648a0bfb5388113eaab7c021ea408`.
- [Betait-referansen](https://github.com/limekex/betait-letsreg-integration/tree/5a8b97f1da955773b1c432703ac2402c95c3c0c3), commit `5a8b97f1da955773b1c432703ac2402c95c3c0c3`. Den aktive pluginmappen er undersøkt; backup- og kopifiler er ikke grunnlag for konklusjonene.
- Tokenkode: [class-betait-letsreg-admin.php, fetch_access_token](https://github.com/limekex/betait-letsreg-integration/blob/5a8b97f1da955773b1c432703ac2402c95c3c0c3/betait-letsreg-integration/admin/class-betait-letsreg-admin.php#L814). Endepunktene finnes i `includes/class-betait-letsreg-endpoints.php` i samme mappe.

## Autentisering: samme hovedflyt, forskjellig tokenforespørsel

| Del | Betait-koden | Gjeldende leverandørdokumentasjon |
| --- | --- | --- |
| API-base | `https://integrate.deltager.no` | Swagger og API-stier ligger på samme vert; dokumentet har ikke eget `servers`-felt. |
| Tokenadresse | `https://legacyapi.deltager.no/token` som standard/fallback | `https://integrate.deltager.no/swagger/token` i `components.securitySchemes.oauth2.flows.password.tokenUrl`. |
| Flyt | `grant_type=password` | OAuth2 password flow. |
| Affiliate | Separat `affid` i skjemadata | Brukernavn skal ha formen **`affid:username`**, ifølge beskrivelsen av security scheme. |
| Forespørsel | POST med `application/x-www-form-urlencoded`, `grant_type`, `username`, `password`, `affid` | OAuth2 password-tokenutveksling. `/swagger/token` er angitt som tokenadresse, men har ikke et eget request-/response-skjema under `paths`. |
| Videre API-kall | `Authorization: Bearer <access_token>` | Globalt OAuth2 security scheme; tom scope-liste. Det dokumenterer ikke finkornede lesescopes. |
| Swagger-klient | Ikke relevant i Betaits tokenmetode | `index.js` setter `clientId=swagger`. Det beviser ikke at en separat serverintegrasjon må ha samme klient-ID. |
| Tokenlevetid | Metoden lagrer `access_token`; håndterer ikke `expires_in`/refresh i den undersøkte flyten | Tokenrespons, levetid og refresh er ikke beskrevet med eget skjema i OpenAPI-dokumentet. Må prøves/avklares før varig tokenhåndtering. |

**Konklusjon:** brukerens beskrivelse er riktig: credentials sendes til et tokenendepunkt, og token brukes videre mot API-et. Den gamle forespørselen kan ikke kopieres uendret til Swagger-adressen, særlig på grunn av affiliate-formatet. Den offisielle hjelpeartikkelen bekrefter nå også legacy-flyten; behold denne når den er konfigurert og verifisert. Ikke forsøk automatisk fallback som sender credentials til flere verter ved feil.

Uautentiserte HEAD-kontroller ga `400` fra legacy-adressen og `405 Allow: POST` fra `/swagger/token`. Dette viser at vertene/endepunktene svarer, men **beviser ikke** at legacy-autentisering fortsatt godtas eller at et passord kan brukes på begge steder. Ingen credentials, tokenforespørsel med innlogging eller brukerdata ble sendt/hentet i denne kontrollen. Bekreft også at den Swagger-angitte tokenadressen er leverandørens anbefalte adresse for serverdrift.

## Hva vi viderefører og hva som må endres

Vi kan videreføre prinsippet om serverstyrt tokenuthenting og Bearer-autentiserte lesekall. Integrasjonen skal beholde eksplisitt arrangør-ID og validere tilhørighet før et kurs kobles.

Betaits `fetch_access_token()` logger hele skjemakroppen, hele tokensvaret og tokenverdien dersom debugloggeren er aktiv. Den lagrer også passord og token i vanlige WordPress-options, og adminmalen fyller inn lagret passord/token i HTML. Dette videreføres ikke. Hvis den gamle pluginen har vært brukt med debug aktivert, bør driftsansvarlig undersøke tilgang til disse loggene og rotere eksponert legitimasjon. Ingen reelle logger eller lagrede credentials er lest i denne gjennomgangen.

RegiNor trenger egne tiltak for utløp, eventuell fornyelse, samtidige tokenforsøk, bytte av credentials og blokkering/backoff ved tilgangsfeil. Passord/token må ikke inngå i feilmeldinger, offentlige svar eller logger. Manuell kontroll har nå låsing, ventetid og ugyldiggjøring ved endrede credentials. Tokenet brukes kun i minnet under én avgrenset manuell kontroll; vedvarende token-cache/refresh for automatisk polling gjenstår. Se [M5.1-leveransen](LETSREG-TILKOBLING.md).

## Konkrete M5-oppslag

- `GET /organizers` dokumenteres som arrangører brukeren i tokenet har tilgang til. Listen har `Offset`/`Limit`; standard limit er 40. Ikke velg første arrangør automatisk.
- `GET /organizers/{organizerId}` gir et `Organizer`-objekt med blant annet `id`, `name` og `affiliateId`. Det er et egnet lesekall for å kontrollere valgt arrangør mot forventede ID-er. Hele svaret skal ikke lagres: modellen kan også inneholde bankkonto-/kontaktopplysninger som RegiNor ikke trenger.
- `GET /organizers/{organizerId}/events` gir en liste av `Event`. `Query` søker i navn, `Offset`/`Limit` styrer paginering (leverandørstandard 40). Uten datoavgrensning returneres kommende/pågående arrangementer. `FromDate`/`ToDate` filtrerer på **sluttdato**, ikke oppstart; `ToDate` er eksklusiv ved midnatt. RegiNors manuelle navnesøk bruker 20 treff per side og utelater felt-, instruktør- og avlysningsdetaljer med `IncludeFields=false`, `IncludeInstructors=false`, `IncludeCancellationInfo=false`.
- `GET /events/{eventId}` kontrollerer valgt treff på nytt før kategoriene hentes og koblingen lagres. Treffet identifiseres med ID; navn er bare visningstekst.
- `Event.availableRegistrations` og `EventPriceCategory.available` er dokumenterte heltallsfelt. Priskategorier hentes via `GET /events/{eventId}/prices`. Feltene er kandidater for kapasitet, men betydningen av negative spesialverdier, reservasjoner, fører/følger/par og delte pooler må fortsatt bekreftes før offentlig bruk.
- `Event` beskriver også `hasWaitinglist` og `isCancelled`. Riktig handling/lenke må fortsatt prøves mot konkrete arrangementer.

## Webhooks og konverteringer

API-et dokumenterer `GET /webhooks`, `GET /webhooks/my`, `POST /webhooks` og GET/DELETE per abonnement-ID. Abonnementsopprettelse krever `hookUrl`, `organizerId` og `webHookType`; modellen har også `affiliateId` og `eventId`.

Det er dermed bekreftet at abonnementer er del av API-kontrakten. De undersøkte generelle webhook-modellene dokumenterer likevel ikke signaturheader, leverings-ID, retry-policy eller det faktiske hendelsesformatet som mottakeren skal validere. Et abonnements-ID er ikke det samme som en unik leverings-ID. Link Mobility delivery-report-endepunktene gjelder en annen webhook-funksjon og skal ikke tolkes som kontrakt for påmelding/kjøp.

Ingen abonnement er opprettet. M5.4 og M5b forblir åpne til mottakssikkerhet og eventuell retur av overleveringsreferanse er verifisert. Kapasitetsfeltene er ikke betalingsbevis.

## Implementeringsoppfølging

1. Implementer den dokumenterte tokenforespørselen med serverkonfigurert affiliate, brukernavn og passord, faste godkjente adresser og skjemakoding. Ingen automatisk overføring fra Betaits lagrede credentials.
2. Legg til administrativ «Kontroller tilkoblingen» som henter token og leser valgt arrangør; vis bare resultat og nødvendige organisasjonsopplysninger. Hold tokenhenting og arrangørverifisering som separate statuser.
3. Prosjekteier har bekreftet vellykket token-/arrangørkontroll mot SalsaNor Oslo (vist tidspunkt 18.09.2026 kl. 22:47:04). Verifiser fortsatt faktisk tokenlevetid og eventuell refresh med avtalt API-bruker. L1 som helhet forblir åpen; testarrangement, minste rettigheter og tokenmodell gjenstår.
4. Fortsett deretter med kurs-/priskategorikobling og kapasitetssemantikk under M5.2.

Punkt 1 og 2 er nå implementert som manuell administratorstyrt kontroll, se [serveroppsett og testbevis](LETSREG-TILKOBLING.md). Prosjekteiers faktiske innlogging og arrangementsoppslag er dokumentert i veiledningen. Lokal kurskobling og navnesøk i punkt 4 er også implementert, med syntetiske tester. Tokenmodell, kapasitetssemantikk og live kapasitet gjenstår; kodeagenten har ikke gjort nye autentiserte leverandørkall.
