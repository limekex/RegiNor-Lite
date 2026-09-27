# Automatisk tid/pris og e-postvarsler

Implementert lokalt i testutgave 0.1.33, etter avklaringen 27. september 2026: tekst godkjennes manuelt, tid og pris kan oppdateres automatisk. Ingen innstillinger aktiveres på eksisterende kurs ved oppgradering.

## Bruk

Åpne enkeltkurset → **Endringer hos LetsReg → Automatiske oppdateringer og e-postvarsler**. Kontroller LetsReg først. Velg automatisk klokkeslett, automatisk vist pris og/eller e-postvarsler. Velg ordinær priskategori når pris aktiveres. Lagre valgene.

Valgene gjelder dette kurset, også når det er publisert. Ved aktivering lagres nåværende kilde og lokale verdier som grunnlag; automatikk gjelder etterfølgende endringer, ikke umiddelbar innhenting av gamle avvik. Uten tidligere kontrollgrunnlag etableres dette ved lagring. Deaktivering er mulig selv om siste API-kontroll har feilet. «Gjennomgått – behold lokalt» avslutter også automatisk behandling av akkurat det vurderte grunnlaget.

## Hva endres?

- **Klokkeslett:** start/slutt i grunnoppsettet og gjenstående, ikke påbegynte ordinære kurskvelder med samme gamle tider. ID-er, datoer, sal og instruktører beholdes. Tidligere, avlyste, flyttede og kvelder med avvikende egne klokkeslett beholdes.
- **Pris:** `price_minor` fra én eksplisitt valgt koblet priskategori. Ingen gjetting ut fra billigste kategori. Aktiv kategori, heltallsbeløp og lokalt NOK-grunnlag kreves. Prisgrunnlaget per person/per par beholdes. Per par krever valgt parkategori og bruker to ganger kategoriens deltakerpris; det er en oppgitt visningspris, ikke en kalkulasjon av alle rabattkombinasjoner. Drop-in-pris oppdateres ikke automatisk.
- **Tekst:** kursbeskrivelse, nivåforklaring, dansestil, partnerinformasjon og prisvilkår må fortsatt gjennomgås og godkjennes manuelt med eksisterende sammenligning.
- **Publisering:** ingen kladdovergang, republisering eller endring av periodens versjon. Lokale kalenderdata oppdateres etter vellykket endring. Ingen synkron TEC-nettverksjobb eller e-post holdes inne i kurslagringens transaksjon.

Datoendringer, manglende tid/pris, avvik mellom tidligere kildetid og lokal tid, lokale etterfølgende redigeringer, ugyldige sommertidspunkter og nye sal-/instruktørkollisjoner stopper oppdateringen. Varslet forklarer hvorfor. Tid og pris i samme kontroll lagres samlet; feil i én del beholder begge lokale verdier. Andre LetsReg-felt forblir ubehandlet i sammenligningen. Kapasitets-/påmeldingsstatus følger sitt eksisterende system.

## E-post

Eget aktivt valg per kurs. Mottakere er alle brukere med rollen `rnl_course_manager` på det aktuelle nettstedet og lesbar/redigerbar kurstilgang, med gyldig e-postadresse. Administratorrollen alene mottar ikke varsler. Det sendes én separat e-post til hver mottaker, uten å dele adresselisten.

Varslet inneholder kursnavn, utførte tid-/prisendringer, beskjed om ubehandlede endringer, eventuell blokkering og lenke til gjennomgangen i administrasjonen. Manuelt godkjent beskrivelse eller prisvilkår legger også et varsel til senere sending. Deltakere varsles ikke av denne funksjonen.

Samme kilde/endringsstatus varsles ikke på nytt ved hver kontroll. E-postgrunnlaget for vellykkede endringer lagres i samme transaksjon som kursendringen. Sending skjer etterpå. Feilet `wp_mail` forsøkes igjen tidligst etter én time; vellykkede mottakere beholdes ved nye forsøk. `wp_mail=true` betyr akseptert av transporten, ikke bekreftet levering til innboksen. WordPress sitt vanlige e-postoppsett, inkludert WP Mail SMTP, brukes.

Oppfølgingen lagrer siste varsel per kurs, ikke en full historisk e-postkø. Nye endringer kan erstatte et eldre usendt varsel. Ved prosessavbrudd akkurat mellom ekstern sending og lokal kvittering kan et varsel bli sendt på nytt. Levering i produksjon må prøves med nettstedets SMTP-oppsett.

## Bakgrunn og tilgang

Kjører etter den eksisterende WordPress-cron-hooken `rnl_letsreg_availability_tick`. Automatikk bruker ferske, vellykkede kildekontroller; ingen ekstra API-kall gjøres under kurslåsen. «Kontroller LetsReg nå» henter grunnlag; automatikk og e-post behandles ved etterfølgende cron, ikke som en tung jobb i skjemaets svar.

Brukeren som lagret valgene registreres som delegert ansvarlig. Ved hver behandling kontrolleres brukerens aktuelle LetsReg- og objektrettigheter, periodetilgang og kontotilhørighet på nytt. Tap av tilgang eller bytte av kobling stopper behandling. En kursansvarlig med riktig tilgang må da lagre valgene på nytt. Innlogget bruker gjenopprettes etter arbeidet. Ingen ny WPML-kopi av driftsdata opprettes.

## Verifikasjon og gjenstående kontroll

Syntetiske lokale tester dekker tid, UTC, pris, parpris, tekstvern, publisering, kollisjoner, historiske kvelder, lokale prisavvik, ferskhet, deaktivering under feil, nonce/versjon, delegerte rettigheter, mottakere, dublettvern, e-postfeil/nytt forsøk og manuell tekstgodkjenning. E-posttransport og LetsReg-nettverk er avskåret i prøvene.

Gjenstår: faktisk LetsReg-endring med aktivert automatikk på et kontrollert kurs, visuell administrasjonskontroll i Avada/WPML og levering til reelle kursansvarlige gjennom produksjonens SMTP. Ingen produksjonsinnstillinger er endret.
