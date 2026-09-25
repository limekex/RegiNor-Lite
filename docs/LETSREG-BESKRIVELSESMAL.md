# Felles beskrivelsesmal for LetsReg og RegiNor

Implementert lokalt i 0.1.2. Bruk samme beskrivelse hos LetsReg som grunnlag for fem lokale felt. Innholdet foreslås i importskjemaet og kontrolleres før lagring; det publiseres ikke automatisk.

## Slik skriver dere teksten hos LetsReg

Start med **Kursbeskrivelse** på egen linje. Bruk alle fem overskriftene nedenfor én gang, og skriv innholdet under hver overskrift. Behold skrivemåten. Store/små bokstaver, kolon til slutt og fet skrift fungerer. Vanlig tekst på egne linjer fungerer også: **H-tagger eller en overskriftsfunksjon i editoren er ikke nødvendig**.

| Overskrift / markør | Lokalt felt |
| --- | --- |
| Kursbeskrivelse | Kursbeskrivelse |
| Dansestil | Dansestil, for eksempel Rueda de Casino eller Salsa |
| Nivå og forkunnskaper | Nivåforklaring; ikke selve nivåvalget i frontendfilteret |
| Partnerinformasjon | Partnerinformasjon |
| Prisvilkår og tillegg | Prisvilkår på enkeltkurset; ikke beløp/priskategorier |
| Praktisk informasjon (valgfri, plasseres til slutt) | Utelates fra lokale tekstfelt; beholdes hos LetsReg |

Sett linjeskift mellom markør og innhold. Skriv for eksempel `Dansestil` og deretter `Rueda de Casino` på neste linje, ikke `Dansestil: Rueda de Casino` på samme linje. Alle fem markørene kreves for sikker gjenkjenning. Kursbeskrivelse og dansestil må ha innhold; dansestil kan være maks 250 tegn. Tomme seksjoner betyr tomme felt, ikke «behold gammel tekst». Nivåforklaring kontrolleres av vanlig publiseringsvalidering.

Overskriftene markerer hvor en seksjon begynner; neste markør avslutter den. Markører må derfor ikke brukes på egne linjer inne i brødteksten. «Praktisk informasjon» avslutter de lokale tekstfeltene. Alt derfra utelates, også eventuelle underoverskrifter om sko, instruktører og sosiale medier. Ikke legg informasjon som også må vises lokalt i denne seksjonen.

Dato, sal, instruktører, varighet, faktisk pris og kursnivå styres fortsatt av egne lokale felt/API-forslag. Ingen tall eller datoer tolkes fra brødteksten. Fra 0.1.3 beholdes avsnitt, linjeskift, fet/kursiv/understreket/gjennomstreket tekst, lister, sitater og lenker i kursbeskrivelse, nivåforklaring, partnerinformasjon og prisvilkår. Dansestil forblir ren tekst. Skript, innbygginger, bilder, CSS og hendelsesattributter fjernes; lenker åpnes i ny fane med beskyttet vindusreferanse. Overskrifter omformes til avsnitt slik at LetsRegs layout ikke overstyrer lokal design. Markører gjenkjennes fortsatt uten H-tagger.

Eksisterende importert ren tekst får ikke tilbake tapt formatering automatisk. Hent arrangementet på nytt og gjennomgå tekstforskjellen under Endringer hos LetsReg før oppdatering av eksisterende beskrivelse. Lokale redigeringer og delte beskrivelser beskyttes fortsatt. Forhåndsvisning og før/etter viser den rike teksten; tekstfeltene kan inneholde enkel HTML. Fra 0.1.10 har tekstfeltene en visuell editor med fanene Visuell og HTML. Avsnitt, lenker og formatering synkroniseres med vanlige skjemaer og enkelt-/bulkimport.

## Introkurs med én samling

Bruk [introkursvarianten med enkel HTML](SALSA-INTROKURS-LETSREG.md) for en 2,5-timers introduksjon til salsa. Den bruker de samme fem markørene og gjenkjennes av eksisterende import. Ingen egen markør eller ny kurstype er nødvendig for tekstfordelingen. Partnerinformasjon kan stå tom når ordningen ikke er oppgitt; behold overskriften.

Lokalt skal kurset ha én faktisk økt med riktig dato, start/slutt og sal. Kontroller **antall kurskvelder = 1** i importen, også når kursperioden varer flere uker. Filterets kursnivå velges separat; et aktivt nivå med navnet «Introkurs» kan foreslås fra arrangementsnavnet ved entydig treff. Beskrivelsen setter ikke timeplan eller nivåvalg automatisk. Introkurseksemplet har en egen tabell for lokalt oppsett.

## Ferdig eksempel for Rueda

Kopier innholdet nedenfor fra «Kursbeskrivelse». Fet skrift på markørene er valgfritt. Eksemplet bygger på oppgitt kurs; kontroller datoer, personer, rabattvilkår og lenker før bruk på et annet arrangement.

---

**Kursbeskrivelse**

Rueda de Casino er den cubanske ringdansvarianten av salsa. Noen av figurene vil være kjente fra vanlig salsa i par og brukes også når vi danser salsa i ring. Andre figurer er mest vanlige i rueda og læres på dette kurset.

For å lære cubanske figurer og utvikle seg som fører eller følger kan det være lurt å gå på salsakurs parallelt, slik at man også trener på dansen i par.

**Dansestil**

Rueda de Casino

**Nivå og forkunnskaper**

Du må minimum ha gjennomført et nybegynnerkurs i Rueda for å begynne på Rueda 1.

**Partnerinformasjon**

Ved parpåmelding registrerer du først den ene deltakeren. Trykk «Legg til ny deltager» og fyll inn opplysningene til partneren før du bekrefter påmeldingen og går til betaling. Prisen for parpåmelding gjelder per deltaker i paret.

**Prisvilkår og tillegg**

Kurset er litt billigere ved påmelding i par.

Mengderabatt på kurs nummer 2, 3 og 4 i samme kursrunde, 19. oktober til 7. desember 2026. Velg rabatten i påmeldingsskjemaet. Rabatten øker for hvert tilleggskurs og trekkes automatisk fra ved betaling.

Studentrabatt: 100 kr per påmelding. Ta med gyldig student-ID ved oppmøte.

Seniorrabatt: 100 kr per påmelding for deg over 67 år.

Medlemsrabatt: 100 kr. Medlemskap for 2026 koster 125 kr og kan tegnes her: https://www.letsreg.com/no/register/medlem2026#/

**Praktisk informasjon**

Kurssted: Arbeidergata 4, Oslo.

Varighet: 8 uker. Start 19. oktober 2026, siste kursdag 7. desember 2026.

Instruktør: Andrès – https://www.salsanor.no/instruktor/andres-estevez/

Assistent: Susanne – https://www.salsanor.no/instruktor/susanne-svarverud/

Bruk av sko

Alle må benytte innesko i saler og fellesområder for å holde dansegulvene frie for skader.

Medlemskap

Medlemskapet gir rabatt på kurs og andre SalsaNor-arrangementer, som SalsaNors Rueda Congress, konserter og sosialdansarrangementer.

SalsaNor på sosiale medier

Følg med på https://www.facebook.com/groups/SalsaNor.Oslo for oppdatert informasjon om kurs, konserter, festivaler og andre arrangementer.

Spørsmål?

Kontakt oslo@salsanor.com på forhånd dersom du har spørsmål om kurs eller påmelding.

Velkommen til SalsaNor!

---

## Import og senere endringer

- Enkelt-/bulkimport fyller de fire beskrivelsesfeltene og foreslår prisvilkår i kursoppsettet. Alle felt kan redigeres før forhåndsvisning og bekreftelse. Et allerede valgt lokalt prisvilkår er ikke forhåndsvalgt for erstatning.
- Tekst uten `Kursbeskrivelse` som egen markør behandles som eldre fritekst: hele teksten foreslås som kursbeskrivelse. Vi gjetter ikke hvor nivå- eller rabattinformasjon begynner.
- Ved ufullstendig mal eller gjentatte markører beholdes hele originalteksten i importskjemaet. En forklaring viser manglende/gjentatte markører. Rett malen og hent på nytt, eller fordel teksten manuelt før import.
- Endringskontrollen sammenligner fortsatt hele kildebeskrivelsen; endringer i praktisk informasjon kan derfor gi et varsel, men teksten legges ikke inn i lokale felt.
- Ved godkjent kildeoppdatering fordeles kursbeskrivelse, dansestil, nivåforklaring og partnerinformasjon. En ugyldig mal blokkerer denne oppdateringen. Lokale redigeringer og delte beskrivelser beskyttes som før.
- Prisvilkår på eksisterende kurs kan fra 0.1.10 sammenlignes og godkjennes separat under «Endringer hos LetsReg», også på publiserte kurs og når kildegrunnlaget allerede er gjennomgått. «Godkjenn prisvilkår for dette kurset» erstatter bare dette kursets vilkår, etter synlig før/etter; lokale redigeringer erstattes bare ved dette aktive valget. Prisbeløp, filterets kursnivå, andre beskrivelser og timeplan beholdes. Administrator kan også redigere prisvilkår direkte i tekstskjemaet uten å ta kurset tilbake til kladd.

Faktisk roundtrip gjennom LetsRegs WYSIWYG-editor og API gjenstår å prøve med ett avtalt testarrangement. Importen testes lokalt med både HTML, fet skrift, linjeskift og helt vanlige tekstmarkører.
