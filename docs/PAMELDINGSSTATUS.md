# Påmeldingsstatus uten ny publisering

Implementert 20. september 2026. Gjelder både publiserte kurs og kursutkast. Ingen eksisterende kurs bytter status automatisk ved pluginoppdatering.

## Bruk

1. Åpne **Kursperioder → ønsket periode → Kurs i perioden**.
2. Velg status i kolonnen **Påmelding**. Valget lagres straks, med «Lagrer …» og deretter «Lagret».
3. Velg **Automatisk fra LetsReg** for å følge arrangementet og de koblede priskategoriene. Velg en **Manuelt:**-status for å overstyre. Gå tilbake til automatisk med samme meny.

Et eksternt lenkeikon ved kursnavnet åpner den lagrede LetsReg-lenken i ny fane for kontroll. Ikonet vises når kurset har en LetsReg-lenke, uavhengig av om påmeldingen er åpen. Kontrollklikk i administrasjonen telles ikke som deltakerklikk.

Kurs og periode beholder publiseringen. Bare statusfeltet endres, med versjonskontroll og historikk. Samtidige endringer avvises med beskjed om å hente siste versjon. Uten JavaScript brukes «Lagre status». Administrator og kursansvarlig kan bruke menyen; kobling til LetsReg og API-oppsett krever fortsatt administrator.

Automatisk krever en lagret LetsReg-kobling og en gyldig påmeldingslenke. Manuelt tilgjengelig/venteliste krever også lenke. En statusmeny kan derfor ikke alene gjøre uferdige kurs klare til påmelding.

## Hvordan status beregnes

- Automatisk bruker API-ets `registrationStartDate` / `registrationEndDate`, arrangementets aktiv/publisert/avlyst/arkivert-status og åpning/stenging på valgte priskategorier.
- Arrangementsledighet beregnes fra positiv `maxAllowedRegistrations` minus `registeredParticipants` når begge er kjent. Plassgrense 0 betyr ubegrenset. Ellers kan positiv `availableRegistrations` brukes som reservegrunnlag; null alene beviser ikke fullt. Kategorien bruker rapportert `available`. Prosjekteier har også avklart at kategoriens nullverdi representerer ubegrenset kapasitet (∞). Positivt rapportert kategoritall brukes som øvre ledighetsgrense; det summeres ikke eller tolkes som totalgrense. Manglende, negative eller ugyldige kapasitetstall er ukjent. Ukjent kapasitet alene stopper ikke en ellers åpen LetsReg-lenke, men gir ingen bekreftelse på ledige plasser.
- Venteliste krever eksplisitt `hasWaitinglist=true`. LetsReg-lenken brukes som inngang; faktisk ventelisteflyt må prøves mot det aktuelle arrangementet.
- En manuell status erstatter den automatiske vurderingen, også ved API-feil. Den beholdes til du velger automatisk igjen.
- Automatisk status bruker LetsRegs salgsvindu uten å arve periodens salgsdatoer. Egne påmeldingsdatoer på enkeltkurset kan fortsatt begrense vinduet. Uten gyldig API-observasjon vises «Må avklares», selv om perioden har fremtidige salgsdatoer.
- Ved manuell status gjelder periodens og kursets lokale salgsdatoer fortsatt. Avlyst periode, avsluttet kurs og offentlig synlighet håndheves i begge modi.

Kategorikapasitet summeres aldri. Parpåmelding gjelder per deltaker og vurderer partnerrolle og behovet for to deltakere. To positive parkategorier alene gir ingen garanti for at et helt par får plass i en delt kapasitetspool. Kategorier vises nå med «Opptil X plasser» fra kjente grenser, uten ubekreftet `InStock`. Se [kategorivisningen](M5-KATEGORIKAPASITET.md).

Nye utfyllingsforslag fra LetsReg foreslår automatisk status. Eksisterende manuelle valg beholdes. Kopiering av API-datoer til egne lokale salgsgrenser er ikke forhåndsvalgt: automatisk modus følger endringer hos LetsReg uten å fryses til importdatoene. Allerede lagrede lokale salgsgrenser beholdes og kan fortsatt begrense påmelding.

## Oppdatering og drift

Oversikten starter oppdatering straks den åpnes og deretter hvert minutt mens fanen er synlig. Den autentiserte AJAX-handlingen lar den eksisterende kontrollkøen hente forfalte arrangementer fra valgt periode (inntil tre per runde, delt kontogrense på tre per minutt), og viser når data sist ble hentet. Dette fungerer også for kursansvarlig og krever ikke at hvert kurs åpnes manuelt. Dette er periodisk oppdatering, ikke sanntid. Offentlige sideoppslag beregner datogrenser på nytt uten å kontakte LetsReg. Manuelle statusvalg, kursinnhold og publisering endres ikke av kontrollen. Offentlig visning utløper ved relevante salgs- og synlighetsgrenser, ikke ved kontrollfrist for API-data.

WP-Cron-hendelsen `rnl_letsreg_availability_tick` kjører hvert minutt. Den velger inntil tre unike arrangementer per kjøring, med eldste kontroll først. Et arrangement blir aktuelt for ny henting etter ti minutter. Femten minutter markerer en utdatert kontroll, men siste kjente status og kapasitet beholdes. API-feil, forsinket cron og tømt objektcache lukker ikke påmeldingen. Siste observasjon lagres i en privat, ikke-autoloadet WordPress-option bundet til konto og arrangement. Kjent salgsvindu og kursslutt beregnes fortsatt mot nåtid, og en ny gyldig API-observasjon erstatter den gamle. Kapasiteten merkes med opprinnelig kontrolltidspunkt og en felles merknad om at faktisk tilgjengelighet bekreftes hos LetsReg. Dette er et avgrenset første driftsoppsett; spesifikasjonens foreslåtte femminuttersintervall må avstemmes mot kvoter og måling.

WP-Cron trenger trafikk eller en faktisk serverplanlagt kjøring. På et nettsted med lite trafikk bør drift kjøre WordPress sine forfalte cron-hendelser hvert minutt. Dette er dokumentert, ikke konfigurert i produksjon av denne leveransen. Administrator kan også åpne kurset og velge **Kontroller påmeldingsstatus nå**. Den åpne kursoversikten supplerer bakgrunnsjobben, men erstatter ikke servercron når ingen arbeider i administrasjonen.

Bakgrunnskontroll deler kontolås med manuelle kontroller, begrenses til tre automatiske kontroller per minutt og henter ett arrangement med priskategorier. Ingen nettverk under kurslåsen. `429` / serverfeil bruker vedvarende ventetid og `Retry-After`; manuelle kontroller kan ikke omgå bakgrunnskøens feilventetid. `401/403` stopper automatisk retry. Kursoversikten viser da konkret at LetsReg avviste innlogging eller tilgang, med henvisning til administratorens tilkoblingskontroll. En vellykket manuell tilgangskontroll kan gjenoppta køen. Hver bakgrunnsrunde henter ett token som kun lever i minnet under den avgrensede runden med inntil tre arrangementer. Gyldig oppgitt levetid kreves for gjenbruk; uten den utsettes neste arrangement. Manuelle kontroller henter fortsatt eget token. Token-cache/refresh og leverandørkvoter er ikke verifisert i produksjon.

Minimale observasjoner lagres privat som siste kjente tilstand uten tidsstyrt sletting. Transient-kopien varer opptil ett døgn; en ikke-autoloadet option bevarer grunnlaget ved cachetømming. Femten minutter er ferskhetsfrist, ikke slettetid eller automatisk stenging. Konto-/legitimasjonsbytte ugyldiggjør gamle observasjoner. Deltakerdata, ordredata og tokens lagres ikke her. Demonstrasjonskapasitet brukes aldri som virkelig ledighet.

## Verifikasjon og gjenstående kontroll

Lokale domenetester, WordPress-prøver med avskårne HTTP-kall og DOM-prøver dekker datogrenser, ukjent/fullt/venteliste, par, utløp, kontobinding, feilstopp, ventetid og samme effektive status i backend/frontend. Statuslagring prøves på publisert kurs med versjonskonflikt, rettigheter, nonce og uendret periode/timeplan. Se endringsloggen for kjørte regresjoner.

Faktisk salgsvindu, tidssoner, kapasitetspooler, reservasjoner, ventelisteflyt og forespørselskvoter må fortsatt prøves mot et avtalt LetsReg-arrangement. Ingen ekte påmelding er gjennomført. En avgrenset autentisert lesetest er dokumentert nedenfor. M5-webhooks, bekreftede kjøp og eksakte rolle-/pooltall er fortsatt åpne.

Oppfølging 20. september: feilprioritering mellom periodedatoer og automatisk status er rettet. Etter vellykket ny tilgangskontroll ble status for de to rapporterte arrangementene hentet i lokalmiljøet. Begge ga tilgjengelig påmelding, med åpning fra API og ingen oppgitt stengedato. Dette bekrefter de aktuelle lesekallene og statusvisningen; delte kapasitetspooler, kjøp og venteliste er fortsatt ikke fullstendig verifisert.

Avklart 22. september: plassgrense 0 betyr ubegrenset. Med positiv grense og kjent påmeldttall beregnes arrangementsledighet som `max(0, grense − påmeldte)`. Manglende grense er ukjent. Kategori-null er etter prosjekteiers videre avklaring ubegrenset. Manglende/ugyldig verdi er fortsatt ukjent og vises som «Antall ikke oppgitt». Salgsvindu og manuell overstyring gjelder som før.

Offentlige statusmerker (0.1.9): Påmelding Tilgjengelig, Åpner snart, Fullt, Drop-in, Fullt - Venteliste aktiv, Stengt, Avlyst og Må avklares. Ferdige kurs beholder Avsluttet. Backendens valg for automatisk/manuell styring har fortsatt forklarende navn.
