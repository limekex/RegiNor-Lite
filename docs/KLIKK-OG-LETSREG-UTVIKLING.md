# Klikk og utvikling hos LetsReg

Implementert lokalt 21. september 2026. Dette er en separat observasjonsrapport i M5a/M5b. Bekreftet kobling mellom et besøk og et kjøp er fortsatt på vent etter supportsak #134895094.

## Bruk rapporten

1. Administrator åpner **RegiNor Lite → Statistikk** og krysser av for **Lagre LetsRegs ordresum og deltakerantall over tid**. Lagre måleoppsettet. Valget er av som standard.
2. Velg et kurs under **Klikk og utvikling hos LetsReg** og trykk **Vis utvikling**. Kurset må være koblet til arrangement hos den konfigurerte LetsReg-arrangøren.
3. **Hent siste tall fra LetsReg** gir et utgangspunkt. Senere kontroller viser utviklingen. Historikken kan ikke gjenskape tidligere kjøpstidspunkter fra en totalsum.
4. Besøksmålingen må også være aktivert, med Complianz og samtykke, for at målte klikk skal vises. Arrangementshistorikken har eget valg og inneholder ingen besøksidentitet.

Dagstabellen viser målte besøksøkter med klikk og siste observerte LetsReg-total den dagen. Totalene er ikke antall kjøp eller inntekt den dagen. Kampanjeklikk og eksperimentelle tidssammenfall ligger i utvidbare avsnitt. Rapporten sender ingen nye hendelser til GTM, GA4 eller annonseplattformer.

## Bekreftet API-grunnlag og begrensninger

Offentlig [OpenAPI-skjema](https://integrate.deltager.no/swagger/v1/swagger.json) hentet 21. september 2026: API 2.2.8, SHA-256 `d821b83dbd2efee77bdfec2eba15a4e41f3648a0bfb5388113eaab7c021ea408`.

| Felt fra `GET /events/{eventId}` | Bruk | Avgrensning |
| --- | --- | --- |
| `Event.registeredParticipants` (`int32`) | Rapportert deltakerantall og endring mellom kontroller | Ikke antall betalte ordre; flere deltakere kan høre til samme ordre. |
| `Event.ordersTotalSum` (`double`) | Rapportert ordresum og endring mellom kontroller | Feltet beskriver ikke valuta, betalt/ubetalt, avgifter eller hvordan refusjoner påvirker summen. Visning uten valutasymbol. |

En økning i ordresum er et tilleggssignal til en endring i deltakerantall. Den verifiserer ikke at et bestemt klikk førte til et betalt salg. Samtidige refusjoner/rettelser og nye registreringer kan også utligne hverandre i totalen.

Begge tall gjelder hele LetsReg-arrangementet. De fordeles ikke på lokale kurs, priskategorier eller annonser. Hvis flere lokale kurs deler arrangementet, vises en forklaring og klikk fra alle disse kursene vurderes samlet for arrangementet. Rapportene skal ikke summeres til omsetning på tvers av slike kurs.

Manglende/ugyldige felt blir «Ikke oppgitt», aldri null solgte. Ordresum lagres med to desimalers presisjon for sammenligning; verdier med større presisjon forkastes fremfor å avrundes til et antatt beløp. Dette fastsetter ikke valuta.

OpenAPI har også oppgjørsendepunkter (`/organizers/{organizerId}/settlements` og oppgjørsdetaljer). Dokumentasjonen krever forhøyede API-rettigheter for oversikten. Disse endepunktene er ikke tatt i bruk; rapporten henter ikke ordre- eller deltakerregistre.

## Eksperimentelle tidssammenfall

Tidsvindu kan velges til 5, 15, 30 eller 60 minutter. Standard er 15. En økning oppdages mellom forrige og ny kontroll. Kjøpstidspunktet innenfor dette intervallet er ukjent, og leverandørens oppdateringsforsinkelse er ikke avklart.

For de siste 50 kontrollene undersøkes målte klikk fra **valgt vindu før forrige kontroll til ny kontroll**. Eksempel: kontroller kl. 18:00 og 18:10, vindu 15 minutter → klikk kl. 17:45–18:10 undersøkes. Dette er en sensitivitetssammenligning, ikke en statistisk kalibrert sannsynlighet.

- Første kontroll lager et utgangspunkt og teller ikke som en økning.
- Mer enn 20 minutter mellom kontroller gir ingen tidsvurdering, selv om tallene endret seg.
- Ingen målte klikk: kilde ukjent. Én målt besøksøkt: mulig sammenfall. Flere: tvetydig sammenfall. Rapporten viser kildene til klikkene, men tilordner ingen salg.
- Flere klikk fra samme besøksøkt telles én gang i intervallet. En besøksøkt er ikke en identifisert person. Samme klikk kan passe flere økninger; vurderingene kan ikke summeres til antall salg.
- Direkte besøk, andre enheter, blokkering, manglende samtykke og forsinkede påmeldinger er alltid mulige forklaringer.
- Ufullstendige rapportuttrekk deaktiverer tidsvurderingen og viser varsel. Ingen eksport av antatte `purchase`-hendelser, annonsekonverteringer eller prosentvise sannsynligheter.

## Lagring og kontroll

- Historikken gjenbruker vellykkede, autentiserte arrangementskontroller med konto-/arrangørvalidering. Ingen nye API-endepunkter eller nettverkskall fra offentlig frontend.
- Fra 0.1.1 følger eksisterende bakgrunnskontroll alle koblede kurs, også ved manuell påmeldingsstatus, for å oppdage endret kursinformasjon. Arrangementshistorikk lagres bare når historikkvalget er aktivert. Manuell status endres ikke. Normal kontrollalder er ti minutter, maks tre arrangementer per runde med eksisterende kvoter, lås og feilventetid. Faktisk hyppighet avhenger av cron, kø og leverandørtilgang.
- Privat tabell `rnl_sales_history`: kontoavtrykk, arrangements-ID, kontrolltidspunkt, to nullable tall og avtrykk av lokale kurskoblinger. Høyst én måling per minutt per konto/arrangement. Ingen token, personopplysninger eller rå API-payload lagres.
- Samtykkede klikk lagrer lokal konto-/arrangementsbinding ved klikket i eksisterende måledetaljer. Eldre klikk uten binding gjettes ikke inn i tidsvurderingen. Endret konto eller priskategorikobling gjenbruker ikke gammel kurshistorikk.
- Tidsvurderingen beregnes ved visning. Tilbaketrukket samtykke og sletting fra besøksmålingen fjerner klikket også fra nye vurderinger. Det lagres ingen antatt konvertering som kan leve videre etter sletting.
- Historikk beholdes i 30 dager. Eksisterende timeplanlagte opprydding og rapportvisning sletter eldre data, også når innsamlingen er slått av. Fungerende cron er nødvendig for regelmessig sletting.
- Rapport/aktivering/manuell kontroll krever administrator (`manage_options`); endringer krever nonce. Kursansvarligs søk/importrettigheter utvides ikke til statistikk.
- Første måling, nedgang, feil, utløpt kildegrunnlag og delte arrangementer forklares i rapporten. Feilede kontroller skriver ikke over historikken med null eller oppdiktede totaler.

## Verifikasjon og gjenstående arbeid

`npm run test:sales-history` bruker bare syntetiske svar. Den prøver opt-in, utgangspunkt/delta, duplikatvern, manuell status med bakgrunnskontroll, kildefeil, delte arrangementer, samtykkesletting, konto-/kategoriendring, tilgang og opprydding. Domenetester dekker tallvalidering, korreksjoner, manglende felt og tidsvinduer. Eksisterende samtykke-, statistikk-, kapasitets-, autentiserings-, status-, lagrings- og admintester kjøres i tillegg.

Før rapporten brukes til økonomiske konklusjoner: prøv feltene mot et avtalt LetsReg-arrangement med kjent gratis/betalt registrering, flere deltakere, avmelding og refusjon; avklar valuta, beløpsgrunnlag og oppdateringsforsinkelse. Kontroller faktisk cron, API-kvoter, Complianz og GTM i staging. Ingen ekte ordre, deltakerregistre, påmeldinger eller live historikk er hentet/opprettet/aktivert under denne implementeringen.
