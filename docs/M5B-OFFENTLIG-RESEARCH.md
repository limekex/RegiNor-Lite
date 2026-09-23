# M5b – finsøk i offentlig LetsReg-dokumentasjon

Undersøkt 20. september 2026. Bare offentlig, lesende research; ingen credentials, kundeordre, påmeldinger eller webhook-abonnementer hentet/opprettet. Dette supplerer [kontraktgjennomgangen](M5B-KONTRAKTGJENNOMGANG.md), og er ikke en godkjenning av konverteringsmåling.

**Senere leverandørsvar, registrert 21. september:** Besøkskobling er satt på vent etter supportsak #134895094. Funnene nedenfor er historiske undersøkelsesspor, ikke bekreftet støtte for SalsaNors kjøpsmåling. Se [oppdatert kontraktstatus](M5B-KONTRAKTGJENNOMGANG.md#leverandørsvar-besøkskobling-satt-på-vent).

## Nye avklaringer

### Offisiell API-veiledning bekrefter legacy-token og webhook-omfang

[API Integration](https://help.letsreg.com/nb/articles/13598282-api-integration), datert 2. februar 2026, dokumenterer:

- Token: `POST https://legacyapi.deltager.no/token`, skjemakodet med `grant_type=password`, `username`, `password` og separat `affid`. Artikkelens `affid=1` er et eksempel; bruk kontoens konfigurerte affiliate.
- Videre kall bruker bearer-token. Ved utløp må klienten autentisere på nytt; ingen refresh-token-flyt beskrives.
- Webhooks dekker opprettet/endret arrangement og opprettet/slettet påmelding. Eksakte `hookName`-verdier skal hentes fra `GET /webhooks`, ikke oversettes fra denne beskrivelsen til oppdiktede API-verdier.
- For ett arrangement: sett `eventId` og `organizerId=0`. For alle arrangementer hos én arrangør: sett `organizerId` og `eventId=0`. Nye arrangementer krever arrangøromfang.
- De fleste skriveoperasjoner krever ekstra rettigheter. Eksisterende lesetilgang beviser ikke at abonnement kan opprettes.

Dette støtter eksisterende serveroppsett og Betaits tokenflyt. Swagger-tokenadressen er en separat dokumentert flyt, ikke grunn til å bytte den fungerende konfigurasjonen. Artikkelen dokumenterer fremdeles ikke callback-body, signatur, leverings-ID, retries eller kjøpsreferanse.

Kildeavvik: artikkelen sier arrangementsordrer mangler filtre, mens gjeldende [OpenAPI](https://integrate.deltager.no/swagger/v1/swagger.json) har betalingsdatofiltre. Faktiske svar må avgjøre implementasjonen; ikke anta endringsfeed for refusjoner ut fra et betalingsdatofilter.

### Refusjoner har mer dokumentasjon enn bare en dato

Legacy-API-et beskriver både [automatiske refusjoner](https://legacyapi.deltager.no/Help/Api/GET-api-v1-settlement-refunds-automatic-clubId_take_skip) og [manuelle refusjoner](https://legacyapi.deltager.no/Help/Api/GET-api-v1-settlement-refunds-manual-clubId_take_skip). Modellen inneholder blant annet `OrderId`, `OrderDetailId`, `Amount`, `RefundedAmount`, `RefundedDate` og `RefundReference`.

Dette er en mulig kilde til refusjonsavstemming. Det er ikke prøvd om SalsaNors konto har tilgang, om ID-ene samsvarer med det nyere API-et, eller om hver rad er én refusjon eller et akkumulert beløp. Valuta, delrefusjoner, paginering og senregistrerte endringer må verifiseres. Ingen automatisk utvidelse av hvilke verter som mottar token.

LetsRegs [veiledning om delvis refusjon](https://help.letsreg.com/nb/articles/13598223-kan-jeg-delvis-refundere-en-deltager-uten-a-slette-pameldingen) beskriver sletting med delrefusjon og ny offline-påmelding, eller refusjon utenfor løsningen. Følgelig kan én reell kundereise få flere ordre; en ny påmelding etter refusjon er ikke nødvendigvis et nytt salg. Eksterne refusjoner kan mangle i API-et.

[Offline betaling](https://help.letsreg.com/nb/articles/14106343-hva-er-offline-betaling-i-letsreg) beskrives også som en administrativ måte å teste påmelding uten faktisk betaling. En registrering eller et webhook-varsel om registrering er derfor ikke alene betalingsbevis. En slik test oppretter likevel data hos leverandøren og er ikke utført her.

### Egne sporingspiksler er omtalt, men ingen ferdig GA4-kontrakt

I [personvernerklæringens «Unntakstilfeller»](https://www.letsreg.com/no/om-oss/personvern/) skriver LetsReg at arrangører kan be om egne cookies/piksler på påmeldingssiden, med samtykke. Dette var et undersøkelsesspor; senere supportsvar bekrefter ikke at SalsaNor kan få det etterspurte måleoppsettet.

Kilden dokumenterer ikke selvbetjent oppsett, GTM-container, cross-domain-overføring, `purchase`, `transaction_id`, beløp eller tilgjengelighet på betalingsbekreftelsen. Den gir heller ikke grunnlag for å anta at Complianz-samtykke automatisk gjelder på LetsReg. Piksler er derfor et mulig separat målespor, ikke dokumentasjon av serverbekreftet salg i RegiNor.

## Hva finsøket ikke avklarte

- Hvordan en tilfeldig besøksreferanse fra vår påmeldingslenke lagres på ordre/ordrelinje. `externalId` og egne skjemafelter finnes, men ingen verifisert URL-parameter eller returkontrakt ble funnet.
- Callback-payload, signatur/autentisering, leverings-ID, tidsvindu, rekkefølge og gjenlevering.
- Entydig betalingsstatus for gratis, offline, faktura, delbetaling og refusjon.

Ikke bruk vilkårlige `ref`, `externalId` eller UTM-parametre som om ordrekobling er støttet. [Håndbokens betalingsreferanse](https://help.letsreg.com/nb/articles/13450151-letsreg-handbok) gjelder arrangørens utbetalingsrapport, ikke dokumentert besøksidentitet.

## Søkeomfang og begrensninger

- Hentet og sammenlignet OpenAPI 2.2.8 på nytt; SHA-256 er uendret: `d821b83dbd2efee77bdfec2eba15a4e41f3648a0bfb5388113eaab7c021ea408`.
- Leste produksjonsvertens `/Help` og relevante ordre-, deltaker- og refusjonsbeskrivelser. Ordre-/deltakereksemplene har generisk `Data` uten brukbar referansekontrakt.
- Gjennomgikk norske/engelske arrangørindekser, teknisk hjelpekategori, norsk håndbok og relevante betalingsartikler. API-artikkelens nedlastede HTML hadde SHA-256 `b77fadf41ec4d51f73ec62398a9f129e383cc9534d7e53104b8098a165e0424f` (HTML kan endres uten faglige endringer).
- Søkte offentlig nett og indeksert GitHub-kode etter LetsReg-/Deltager-domener, webhook og ekstern referanse; ingen leverandørimplementasjon som avklarer kontrakten ble funnet. Ingen treff i de målrettede Betait-søkene er ikke bevis for at funksjonen ikke finnes i all kode.
- Forsøk på å hente offentlig påmeldingsside og portalens inngang ga HTTP 403. Derfor ble påmeldingsklientens JavaScript ikke analysert. Ingen innlogging eller omgåelse forsøkt.
- Tredjepartskataloger nevner Deltager.no/Zapier, men søket ga ingen verifisert nåværende trigger-/payload-kontrakt. Dette brukes ikke som bevis for M5b.

## Forslag fra researchen 20. september – se nyere leverandørsvar

1. Lag en avgrenset, administratorstyrt **lesekontroll av `GET /webhooks`** med eksisterende autentisering. Vis navn og beskrivelser; ikke opprett abonnement automatisk. Dette kan avklare faktiske hendelsestyper uten kundeordre.
2. Gjennomfør en separat test av ordreoppslag på et avtalt testarrangement. Skill påmelding, betaling og refusjon; sjekk rettigheter og beløpsgrunnlag. Offline-test beviser ikke betalingsflyten.
3. Prioriter API-avstemming før webhook-mottak. Bruk API-et som autoritet; webhooks kan senere utløse raskere avstemming. Betalingsdatofilter alene fanger ikke nødvendigvis senere refusjoner.
4. Hold besøkskoblingen deaktivert til vi har en konkret måte å sende og lese tilbake samme tilfeldige referanse på. Test med to forskjellige referanser, flere deltakere og avbrutt/ferdig påmelding. Uten dette kan ordre eventuelt telles per kurs etter statusverifisering, men kilde/kampanje forblir ukjent.

**Anbefaling:** M5b kan deles i ordreavstemming og besøksattribusjon. Webhook-usikkerhet trenger ikke stoppe første del; manglende referanseoverføring stopper fortsatt den andre. Hvis leverandøren bare svarer på ett spørsmål, prioriter hvilket dokumentert felt/URL-parameter som kan ta imot en egen referanse, og hvor nøyaktig samme verdi returneres på ordren.
