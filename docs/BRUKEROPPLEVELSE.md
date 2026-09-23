# Brukeropplevelse – styrende krav

Avklart med prosjekteier 18. september 2026: **Både administrasjon og offentlig kursvisning skal være svært brukervennlig og intuitiv for personer med lite eller ingen digital kompetanse, samtidig som uttrykket er elegant og innbydende.** Dette er et krav til utforming og brukertesting, ikke en påstand om at brukervennligheten allerede er bevist.

## Prinsipper

- Én tydelig hovedhandling i hver oppgave. Vis hva brukeren gjør nå og hva neste steg er.
- Vanlige norske ord: «kurs», «kursperiode», «kurskveld», «lagre» og «meld deg på». Interne ID-er, databasetermer og tekniske statuser skal ikke være nødvendig for å bruke løsningen.
- Vis det viktigste først. Avanserte valg, historikk og sletting er tilgjengelige ved behov, adskilt fra hovedhandlingen.
- Gi trygge standardvalg. Forklar felt der betydningen ikke er åpenbar. Ikke be brukeren oppgi opplysninger systemet allerede kjenner.
- Behold utfylte felt ved feil. Forklar hva som må rettes og hvordan. Vis konsekvenser før viktige endringer.
- Bruk rolig typografi, god kontrast, luft og et konsekvent visuelt uttrykk. Ingen bevegelse eller dekor som konkurrerer med kursvalget.
- Store handlinger, synlig tastaturfokus og tydelige etiketter. Farge er aldri eneste statusforklaring. Hovedflyten skal fungere uten JavaScript.
- Kursdeltakeren skal raskt forstå nivå, tid, sted, pris og neste steg. Intern organisering skal ikke være et hinder.

## Akseptanse før lansering

Prøv hovedoppgavene med 1–2 kursansvarlige og 4–6 deltakere, inkludert personer med lav digital erfaring. Observer uten å forklare grensesnittet underveis. Registrer hvor de stopper, feiltolker eller trenger hjelp.

1. Deltaker finner et passende kurs og kan forklare nivå, oppstart, sted og totalpris før overgangen til LetsReg.
2. Deltaker går tilbake fra kursdetalj uten å miste periode, filtre eller orientering.
3. Kursansvarlig oppretter en periode, legger til en gruppe, retter en kurskveld og publiserer etter kontroll uten utviklerhjelp.
4. Brukeren forstår forskjellen mellom lagret kladd, publisert og synlig fra en fremtidig dato.
5. Mobil 320/390 px, 200 % tekst/zoom, tastatur og skjermleser fungerer uten tap av innhold eller hovedhandlinger.

Automatiske tester og en visuell gjennomgang støtter disse kriteriene. De erstatter ikke prøven med målgruppen.

## Konkretisert etter første prøve

Periodeoversikten bruker tabell med nyeste først, svak grønn aktiv-status og gul neste-status, alltid med tekst. Egen side per periode samler oppgavene i **Periode → Kurs → Publiser**; kopiering/historikk/papirkurv ligger separat. «Kurs» brukes i grensesnittet. Instruktør er valgfritt, og tidsinformasjon som ikke blokkerer publisering presenteres uten ekstra godkjenningsbokser. Alle nye tekster skal kunne oversettes. Se [brukerflyt og språk](BRUKERFLYT-OG-SPRAK.md).

## LetsReg-kobling etter UX-gjennomgang

Tekniske tilgangs-/eierkontroller er systemets ansvar. Administrator skal kunne søke, velge arrangement/kategorier og lagre koblingen inne i kurset, med øvrige utfylte felt bevart. Boksen står rett under «Pris og påmelding» i kursoppsettet. En koblingsendring krever ikke ny publisering av et allerede publisert kurs eller en hel periode. Offentlig API-lenke fylles automatisk inn i et tomt felt; eksisterende annen lenke erstattes bare ved aktivt valg. Egen tilkoblingsside er for drift og reserveflyt, ikke en nødvendig omvei i hovedflyten. Automatisk DOM-prøve er implementert; visuell brukertest med målgruppen gjenstår.


Kursoppsettet følger nå Undervisning, Pris og påmelding, Påmelding hos LetsReg, Kursfrie dager og flere valg, Utseende og øvrige handlinger, i samme lese- og tastaturrekkefølge. Fremheving og drop-in ligger under undervisning. Første dato er ett valgfritt avvik fra perioden. Utfylling fra LetsReg er et synlig forslag til valgte felt; sal og øvrige lokale valg beholdes. Antall undervisningskvelder og pris må kunne forstås som et kontrollbart forslag, særlig ved ferieuker, rabattkategorier og parpåmelding.

## Kursprofil og kalender – 21. september 2026

Kursprofilens tittel følges av korte piller for valgt dansestil og nivå. Hovedkolonnen har egne bokser i rekkefølgen Kursbeskrivelse, Nivå og forkunnskaper, Partnerinformasjon og Prisvilkår og tillegg. Tomme valgfrie tekstområder skjules. Pris, tid, sted og påmelding beholdes i sidekolonnen. På mobil beholdes fakta/påmelding først i leserekkefølgen.

Kurskort og kalenderkort viser nivåpill rett under tittelen. Lange nivåforklaringer og prisvilkår finnes på kursprofilen, ikke i oversikten. Klokkeslettene i kalenderen skal stå hver for seg ved riktig høyde og aldri brytes. Mobilvisningen bruker fortsatt en lesbar liste med tid på hvert kurs.

Kurssted og kart vises i egen boks i hovedkolonnen, etter beskrivelsesfeltene og før kurskveldene. Kartet vises automatisk hvis stedet har koordinater. Sidekolonnen beholder adressen som ankerlenke til boksen. Kartlenke til OpenStreetMap finnes som alternativ.
