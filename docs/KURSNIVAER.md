# Kursnivåer og nivåfilter

Implementert lokalt 20. september 2026. Nivåfilteret **erstatter** «Hvem passer kurset for?» og snarveiene «Jeg er nybegynner» / «Jeg har danset før». Nybegynner er et vanlig nivå som administrator kan opprette sammen med Øvet 1, Øvet 2, Videregående eller andre navn.

## Sett opp nivåutvalget

Åpne **RegiNor Lite → Kursinnhold og ressurser → Kursnivåer**. Menyvalget het tidligere «Beskrivelser og steder»; siden samler nå nivåer, kursbeskrivelser, kurssteder, saler og konfigurasjon av instruktørprofiler.

Administrator kan opprette og endre:

- **Navn:** for eksempel Nybegynner eller Øvet 1.
- **Kort forklaring av nivået:** valgfri tekst som vises på kursdetaljen.
- **Rekkefølge:** laveste tall først, for eksempel 10, 20 og 30.
- **Tilgjengelig for nye kurs:** slå av for å ta nivået ut av nye valg. Eksisterende kurs og periodekopier beholder nivået; det er fortsatt synlig og filtrerbart når et offentlig kurs bruker det. Nivået kan aktiveres igjen.

Ingen nivåer opprettes automatisk, og eksisterende kurs omklassifiseres ikke. Navn og forklaring er klargjort for WPML String Translation, med felles nivå-ID mellom språkene.

## Velg nivå på kurset

Åpne kursets oppsett og velg **Undervisning → Kursnivå**. Valget følger vanlig forhåndsvisning og lagring av kursoppsettet. Publiserte perioder følger eksisterende regel om kladd før redigering. Kursansvarlige kan velge nivå, men trenger administrator for å endre det felles nivåutvalget.

Nivået tilhører enkeltkurset. To kurs som bruker samme kursbeskrivelse kan derfor ha forskjellige nivåer. Feltet er valgfritt; kurs uten nivå vises under «Alle nivåer». LetsReg-import bruker samme valg i kursoppsettet. Ved kobling og import kan arrangementsnavnet gi et redigerbart nivåforslag, se nedenfor.

Den eksisterende friteksten **Nivå og forkunnskaper** i kursbeskrivelsen beholdes som forklaring, med eksisterende publiseringskontroll. Den er ikke lenger en filterkategori. Tidligere `audience`-verdier beholdes i lagrede beskrivelser for kompatibilitet, men vises ikke som et redigerbart målgruppefelt og styrer ikke frontendfilteret.

## Nivåforslag fra LetsReg

Når et arrangement velges, sammenlignes navnet med de aktive nivåene i **Kursinnhold og ressurser**. Eksempler, forutsatt at nivåene er opprettet:

| Arrangementsnavn | Foreslått kursnivå |
| --- | --- |
| Salsa Nybegynner | Nybegynner |
| Salsa Øvet 1 / Salsa Øvet nivå 1 / Salsa Øvet1 | Øvet 1 |
| Rueda Videregående | Videregående |

Store/små bokstaver, mellomrom og skilletegn håndteres. Hele nivånavn må stemme; Øvet 1 forveksles ikke med Øvet 10. Et mer spesifikt nivånavn foretrekkes fremfor et navn som inngår i det. Flere ulike treff, dupliserte nivånavn, manglende treff og inaktive nivåer gir ingen automatisk plassering. Skjemaet forklarer da at nivå må velges manuelt. Forkortelser og synonymer gjettes ikke.

Ved lokal kobling ligger nivået i **Fyll ut kursoppsettet fra LetsReg**. Bruk valgte forslag for å fylle det inn. Et allerede valgt nivå er ikke krysset av for erstatning; det krever et aktivt valg. Endrer du nivået etter henting, fjernes avkrysningen for nivåforslaget.

Ved enkelt- og bulkimport fylles et tomt nivåfelt fra et entydig forslag sammen med de øvrige kursforslagene. Du kan endre det før forhåndsvisning og opprettelse. Hvert nytt arrangement vurderes separat; nivå fra forrige importkurs tas ikke med videre. Et manuelt nivå beholdes ved redigering av kurs i importlisten. Ingen nivå lagres bare ved søk eller valg av arrangement, og henting endrer ingen eksisterende kurs.

## Frontend

Ett nivåfilter viser «Alle nivåer» og nivåene som faktisk brukes av offentlige kurs i valgt periode, sortert etter oppsettet. Filteret virker sammen med periode- og dagvalget i både kursliste og ukeskalender, uten JavaScript. Det følger lenkene til kursdetalj og tilbake.

Nivånavnet vises på kurskort, i kalender og på kursdetalj; den valgfrie nivåforklaringen vises på detaljen. Schema bruker samme navn i `educationalLevel`. Kurs fra skjulte perioder og ubrukte nivåer blir ikke røpet i filteret.

Nivåfilteret bruker stabil nivå-ID i `rnl_level`. Gamle målgruppelenker som `rnl_level=beginner` gir nå oversikten uten nivåavgrensning; systemet gjetter ikke hvilket brukerdefinert nivå som tilsvarer det gamle valget. Ved et gyldig nivå-ID uten treff vises en tydelig tomtilstand, med mulighet til å velge «Alle nivåer».

## Kontroll

`npm run test:levels` dekker nivåoppsett, validering, rettigheter, versjoner, valg per enkeltkurs, felles kursbeskrivelse med ulike nivåer, liste/kalender, returvalg, oversettelse, deaktivering og periodekopiering. Testen bruker egne syntetiske objekter og rydder dem etterpå. Nivåtesten inngår i GitHub CI-definisjonen.

Offentlig HTTP-prøve tester nivåfilter, lenker og schema uten JavaScript. Faktisk WPML og visuell prøve i Avada gjenstår.

Bestått lokalt: 37 nivåkontroller, lagring (61), arbeidsflyt (86), admin (91), admin-HTTP (40), offentlig modell (50), offentlig HTTP (47), menyer (52), LetsReg-import (86), WordPress-oppstart, alle ni DOM-testskript og Composer (156 tester / 282 assertions). Oversettelsesmalen er oppdatert. Ingen reelle kurs er tildelt eller fratatt nivå av testene.

Nivåforslagene er i tillegg dekket av enhetstester for navnetreff, DOM-prøver for lokal kobling/import og WordPress-prøver for kobling og importforhåndsvisning. Bestått lokalt: Composer (172 tester / 301 assertions), alle ni DOM-skript, LetsReg-kobling (120), import (88), arbeidsflyt (86), admin-HTTP (40), admin/språk (91) og WordPress-oppstart. Prøvene bruker syntetiske API-svar og rydder egne testobjekter. POT og pluginpakke er oppdatert. Faktisk visuell Avada/WPML-prøve gjenstår.
