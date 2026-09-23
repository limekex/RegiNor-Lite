# M5b – gjennomgang av konverteringskontrakten

Kontrollert 20. september 2026 mot [offentlig OpenAPI](https://integrate.deltager.no/swagger/v1/swagger.json), Deltager Integration API **2.2.8**, OpenAPI 3.0.4. Nedlastet dokument: SHA-256 `d821b83dbd2efee77bdfec2eba15a4e41f3648a0bfb5388113eaab7c021ea408`. Gjennomgangen brukte bare offentlig dokumentasjon og lokal kode. Ingen ordre, deltakere eller abonnementer ble hentet/opprettet.

## Leverandørsvar: besøkskobling satt på vent

Registrert i prosjektet 21. september 2026. Kilde er supportsvaret prosjekteier delte i samtalen, sak **#134895094: Sporing fra annonser til gjennomført påmelding hos LetsReg**, markert «Ferdig behandlet». E-postens sendetid er ikke oppgitt.

LetsReg skriver: «Slik løsningen vår er bygd opp i dag, har vi dessverre ingen måte å tracke denne informasjonen på per arrangør.» De opplyser at teknisk team arbeider med en løsning, uten dato eller teknisk kontrakt.

**Prosjektets konklusjon:** Vi har ingen bekreftet, støttet løsning for annonse → besøk → konkret påmelding/salg hos LetsReg. Besøksattribusjonen i M5b settes derfor på vent til en konkret leverandørløsning foreligger. At supportsaken er lukket betyr ikke at funksjonen er levert.

Svaret avklarer ikke hvert mulig API-felt eller pikseloppsett, og er ikke bevis for at alle tekniske alternativer er umulige. Tidligere funn om `externalId`, skjemafelter og arrangørpiksler skal likevel ikke presenteres som tilgjengelige løsninger for SalsaNor. Ingen udokumenterte parametre eller kjøpshendelser aktiveres.

- **M5a fortsetter:** samtykkestyrt kampanjekilde, besøk, kursvisning og klikk videre til LetsReg. Faktisk Complianz/GTM/GA4-oppsett må fortsatt prøves i staging. Klikk skal betegnes som utgående klikk, ikke gjennomført kjøp.
- **Ordreavstemming er et separat mulig spor:** antall påmeldinger og eventuell omsetning per kurs kan undersøkes gjennom API-et etter verifisering av rettigheter, betalingsstatus, refusjoner og beløp. Dette er ikke implementert og gir ikke kampanjetilhørighet.
- **Besøkskobling er på vent:** gjenåpnes først ved konkret dokumentasjon av overføring/retur av besøksreferanse eller et støttet måleoppsett med bekreftet kjøpshendelse. En eventuell løsning bare i GA4 gir ikke automatisk ordrekobling i RegiNor.

Rapporter skal ikke tilordne samlet kurssalg til annonser på grunnlag av sammenfallende tidspunkt, utgående klikk eller kapasitetsendringer.

**Oppfølging 21. september:** [Separat arrangementshistorikk og eksperimentell tidsvurdering](KLIKK-OG-LETSREG-UTVIKLING.md) er implementert lokalt. `Event.ordersTotalSum` og `registeredParticipants` finnes i samme offentlige skjema og gjenbrukes fra autentiserte arrangementskontroller. Dette er ikke ordreavstemming: betalings-/refusjonsbetydning og valuta er ikke dokumentert for summen. Rapporten viser klikk og totalendringer uten å hevde bekreftede eller prosentvis sannsynlige annonsesalg.

## Hva er faktisk dokumentert?

**Oppfølging samme dag:** [Finsøk i offisielle hjelpesider og offentlig kode](M5B-OFFENTLIG-RESEARCH.md) avklarer legacy-tokenflyten, webhook-omfang og refusjonsendepunkter. Tabellen nedenfor beskriver OpenAPI-grunnlaget; referanseoverføring og callback-autentisering er fortsatt uavklart.

| Del | Funn | Konsekvens |
| --- | --- | --- |
| Ordreoppslag | `GET /orders/{orderId}`, `GET /orders/{orderId}/orderdetails/{orderDetailId}` | Autoritativ avstemming er teknisk mulig hvis kontoen har rettigheter. Rettighetene er ikke prøvd med ekte ordre. |
| Minimal ordreoversikt | `GET /events/{eventId}/orders/ids` returnerer `OrderReference` med ordre-ID, ordredato og linje-ID-er | Kan brukes til avstemming uten å hente hele deltakerlisten, men er ikke betalingsbevis. |
| Betaling | `Order.orderDate`, `Order.paidDate` | Feltnavn er dokumentert; tidspunkt alene avklarer ikke gratis påmelding, delbetaling, kreditering eller netto omsetning. |
| Ordrelinje | `OrderDetail.id`, `orderId`, `quantity`, `price`, `priceCategory`, `event` | Stabile ID-er finnes for arrangement, arrangør og kategori. Semantikk for beløp, valuta, antall og parpåmelding må avklares. Pris per deltaker beholdes. |
| Endringer | `OrderDetail.deleted`, `refunded` er nullable datotid | Mulige signaler for sletting/refusjon, men ikke dokumentasjon av full tilstandsmaskin eller delvis refusjon. |
| Ekstern referanse | `OrderDetail.externalId` og `fields[]` (`EventField.id/name/value`) finnes | **Det er ikke dokumentert hvordan en tilfeldig besøksreferanse kan overføres i den offentlige LetsReg-lenken, bindes til ordren og returneres etter betaling.** Feltene alene er ikke tilstrekkelig kontrakt. |
| Webhook-abonnement | GET/POST `/webhooks`, GET `/webhooks/my`, GET/DELETE `/webhooks/{id}` | Registrering støttes. POST krever `hookUrl`, `organizerId`, `webHookType`; har også affiliate-/arrangements-ID. |
| Webhook-mottak | Modellen viser abonnement-ID og callback-adresse | Ingen dokumentert signatur, leverings-ID, replay-vindu, retry-/rekkefølgegaranti eller ordre-callback-payload i det kontrollerte dokumentet. Abonnement-ID er ikke leverings-ID. |
| Link Mobility | Separate delivery-report-/health-ruter | Gjelder SMS-levering, ikke bekreftet kurskjøp. Skal ikke gjenbrukes som ordrekontrakt. |
| API-autentisering | Swagger beskriver password-flyt med `affid:username`, token-URL `https://integrate.deltager.no/swagger/token` | Dette er Swagger-flyten, ikke bevis for at produksjonsintegrasjonens tokenadresse skal byttes. Eksisterende, bekreftet serverkonfigurasjon beholdes. API-bearer autentiserer heller ikke automatisk innkommende webhooks. |

## Brukerlevert webhook-eksempel

Prosjekteier har delt et eksempel med `subscriptionId`, `webhookType` og `data`. Det viser en mulig konkret callback-struktur for `participant.registered`, men er ikke en selvstendig verifisert leveranse fra LetsReg. HTTP-headere, avsenderkontroll og opprinnelsen til eksemplet er ikke dokumentert. Råpayload og personopplysninger er ikke lagret i repoet.

| Felt i eksemplet | Mulig bruk | Hva som fortsatt må bekreftes |
| --- | --- | --- |
| `subscriptionId` | Slå opp vårt konfigurerte abonnement | Identifiserer abonnement, ikke en unik levering; er heller ikke alene avsenderautentisering. |
| `webhookType = participant.registered` | Kandidat for hendelse ved registrert deltaker | Eksakt verdi er observert i brukerens eksempel. Bekreft mot webhook-katalog og faktisk test; navnet beviser ikke betaling. |
| `data.event.organizerId`, `data.event.id`, `data.priceCategory.id` | Kontroller tilhørighet og finn lokal kurskobling | Bare konfigurerte arrangører/arrangementer/kategorier behandles. Flere lokale kurs kan dele samme arrangement. |
| `data.id`, `data.order.id` | Kandidater for ordrelinje og ordre til API-avstemming | Strukturen ligner `OrderDetail`; bekreft at `data.id` faktisk er API-ets ordrelinje-ID. Ordre-ID ligger under `order.id`, ikke i et flatt `data.orderId` i dette eksemplet. |
| `data.order.paidDate` | Signal som bør undersøkes mot API-et | Ingen generell regel for gratis, offline, faktura eller delbetaling kan utledes fra ett eksempel. |
| `data.price`, `data.quantity`, `data.priceCategory.price` | Mulig grunnlag for beløp og antall | Linjepris og kategoripris er ulike i eksemplet. Årsak, netto/brutto, enhets-/linjebeløp og valuta er uavklart. Ikke beregn omsetning fra listepris eller anta NOK. |
| `data.deleted`, `data.refunded` | Kandidater for endringsstatus | Begge er null i eksemplet. Dette viser ikke hvordan senere sletting eller hel/delvis refusjon varsles. |
| `data.externalId`, `data.fields[]` | Mulige beholdere for en overleveringsreferanse | `externalId` er null; skjemafeltene inneholder ingen besøksreferanse. Skrivemåte, automatisk utfylling og retur må fortsatt verifiseres. |
| `data.consents`, `data.bookingConsents` | Eventuelle samtykkeopplysninger hos leverandøren | Tom/null gir ikke grunnlag for markedsføringssamtykke og kan ikke erstatte den lokale samtykkekontrollen. |

**Avklart på eksempelnivå:** callback-konvolutt, én hendelsesverdi og plassering av ID-er. **Ikke avklart:** autentisert avsender, unik leverings-ID, hendelsestid/versjon, retries, betalingssemantikk og besøkskobling. Ordre-/betalingstidspunkt er ikke nødvendigvis webhookens hendelsestidspunkt. Fravær av signatur i JSON sier ingenting om eventuelle signaturheadere.

Anbefalt behandling når mottakskontrakten er verifisert: valider avsender og tilhørighet, legg minimale ID-er i kø, og hent gjeldende ordre-/kapasitetsstatus fra API-et. Gjentatt behandling skal oppdatere samme ordrelinje fremfor å telle et nytt salg. Ikke blokker alle senere hendelser for samme linje; refusjon og sletting må fortsatt kunne oppdatere tilstanden. Abonnement-ID brukes ikke som dedupliseringsnøkkel for enkeltleveranser.

For å koble kjøpet til en kampanje må en tilfeldig referanse fra et samtykket besøk sendes gjennom påmeldingen og returneres på ordren. `externalId` eller et eget felt kan være aktuelt, men eksemplet beviser ikke at dette lar seg fylle via lenken. Navn, e-post, telefon, adresse og fødselsopplysninger trengs ikke til den planlagte avstemmingen og skal ikke brukes til å gjette besøkskoblingen.

Neste avgrensede verifikasjon: bekreft eksemplets kilde og autentiseringsmekanismen uten å dele hemmelige verdier, hent webhook-katalogen, og sammenlign en avtalt testpåmelding med API-svaret for dens ordre/linje. Betalings-/refusjonstilstander prøves separat. Referanseoverføring prøves først når LetsReg tilbyr en konkret løsning. Ingen mottaker eller testpåmelding er opprettet i denne vurderingen.

## Status i RegiNor

M5a har samtykkestyrte besøk, kursvisninger og utgående klikk. M5-kapasitet har autentisert lesetransport og oppdatering av minimale tilgjengelighetsbilder. **Det finnes ingen aktiv M5b-mottaker eller felles verifisert webhook-transport ennå.** Tidligere formulering om gjenbruk av «M5s webhook-transport» var et arkitekturønske, ikke levert kode.

API-et gir dermed et mulig grunnlag for ordreavstemming, men bekreftede konverteringer med kampanjekilde krever fortsatt verifisert referanseoverføring. Etter supportsvaret ovenfor er besøkskoblingen satt på vent; offentlig research alene gir ikke grunnlag for å aktivere den. Ingen udokumentert referanse legges til påmeldingslenkene. Endringer i kapasitet teller ikke som salg.

## Videre arbeid og vilkår for gjenopptakelse

1. Verifiser M5a i faktisk Complianz/GTM/GA4-oppsett, med tydelig skille mellom kursinteresse, LetsReg-klikk og kjøp.
2. Undersøk separat, administratorstyrt ordreavstemming på et avtalt testarrangement: rettigheter, statusoverganger, gratis/betalt/offline, refusjoner, valuta og beløpsgrunnlag. API-et kan undersøkes uten først å opprette webhook-abonnement. Ingen rå deltakerpayload skal lagres.
3. Verifiser callback-struktur og autentisering før eventuell webhook-mottaker. Alternativet er autoritativ polling innenfor bekreftede kvoter. Gjentatte varsler må ikke telle som nye påmeldinger eller salg.
4. **På vent:** få en konkret leverandørkontrakt for besøksreferanse eller arrangørspesifikk kjøpsmåling. En varslet fremtidig løsning uten tekniske detaljer er ikke tilstrekkelig.
5. Ved gjenopptakelse av besøkskobling: test to ulike referanser, flere deltakere/par, avbrutt og fullført påmelding, utløp og tilbakekalt samtykke. Koble bare kjent, samtykket referanse til kampanje; øvrige påmeldinger forblir uten attribusjon.

Dette er en **gjennomført kontraktgjennomgang**, ikke en ferdig M5b-integrasjon. Det trengs ingen endring av dagens kapasitetsvisning for å beholde denne grensen.
