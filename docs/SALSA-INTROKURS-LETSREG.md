# Salsa introkurs – beskrivelse hos LetsReg

Introkursvariant av [felles beskrivelsesmal](LETSREG-BESKRIVELSESMAL.md). Bruker de eksisterende markørene og gjenkjennes av dagens import uten kodeendring. Kopier bare HTML-blokken til LetsRegs HTML-/kildevisning. Ingen CSS, tabeller eller H-tagger er nødvendige.

Datoen er beholdt som oppgitt: **22. november, uten årstall**. Bekreft år i arrangementets datofelt og at tirsdagskurset fortsatt er riktig tilbud før publisering. Medlemslenken gjelder 2026. Pris og partnerordning var ikke oppgitt; disse er ikke lagt til. Partnerseksjonen er derfor bevisst tom, men markøren må beholdes. Medlemsomtalen lover rabatt på ordinære kurs, ikke på introkurset.

## Beskrivelse til kopiering

```html
<p><strong>Kursbeskrivelse</strong></p>
<p><strong>Prøv salsa på 2,5 timer hos SalsaNor!</strong></p>
<p>Er du nysgjerrig på salsa? På introkurset får du en smakebit og lærer grunnleggende trinn og enkle figurer. Våre erfarne instruktører veileder deg underveis, slik at du får en trygg start og kan prøve deg på dansegulvet.</p>
<p>Siden 1999 har SalsaNor spredt salsa og andre latinamerikanske danser i Norge og introdusert tusenvis av nybegynnere for salsa. Nå er det din tur!</p>
<p><strong>Meld deg på og ta dine første salsatrinn med SalsaNor!</strong></p>
<p><strong>Bruk av sko</strong><br>Ta med innesko til bruk i salene og fellesområdet. Slik hjelper du oss med å holde dansegulvene frie for skader.</p>

<p><strong>Dansestil</strong></p>
<p>Salsa</p>

<p><strong>Nivå og forkunnskaper</strong></p>
<p>Introkurset passer for deg som er nybegynner og vil prøve salsa. Du trenger ingen forkunnskaper.</p>
<p>Etter denne smakebiten kan du fortsette på tirsdager på <strong>Salsa for Nybegynnere/Litt Øvet</strong>. Vi tilbyr salsa på flere nivåer, slik at du kan fortsette å lære i ditt eget tempo.</p>

<p><strong>Partnerinformasjon</strong></p>

<p><strong>Prisvilkår og tillegg</strong></p>
<p><a href="https://www.letsreg.com/no/register/medlem2026#/">Tegn medlemskap i SalsaNor for 2026</a>.</p>
<p>Medlemskapet gir rabatt på alle ordinære kurs og andre SalsaNor-arrangementer, som SalsaNors dansereiser, SalsaNor Rueda Congress, konserter og andre sosialdansarrangementer.</p>

<p><strong>Praktisk informasjon</strong></p>
<p><strong>Kurssted:</strong> STUDIO ORIENT, Arbeidergata 4, Sentrum.<br>
<strong>Varighet:</strong> Én samling på 2,5 timer.<br>
<strong>Tid:</strong> 22. november kl. 16.00–18.30.<br>
<strong>Instruktører:</strong> Instruktør kan variere.</p>
<p><strong>SalsaNor på sosiale medier</strong><br>Følg <a href="https://www.facebook.com/groups/SalsaNor.Oslo">SalsaNor Oslo på Facebook</a> for oppdatert informasjon om kurs, konserter, festivaler og andre arrangementer.</p>
<p><strong>Spørsmål?</strong><br>Ta kontakt med <a href="mailto:oslo@salsanor.com">oslo@salsanor.com</a> på forhånd hvis du har spørsmål om kurs eller påmelding.</p>
<p><strong><em>Velkommen til SalsaNor!</em></strong></p>
```

## Lokalt oppsett ved import

Malen fordeler tekst, men oppretter ingen egen kurstype. Bruk én gjenbrukbar beskrivelse, for eksempel **Salsa introkurs**, og ett lokalt kurs per gjennomføring. Velg ny beskrivelse fra LetsReg ved første import; en allerede valgt lokal beskrivelse erstattes ikke bare fordi arrangementet har denne malen.

| Felt | Oppsett for dette introkurset |
| --- | --- |
| Kursnavn | Salsa Introkurs |
| Dansestil | Salsa; leses fra malen |
| Nivå og forkunnskaper | Forklaringsteksten leses fra malen |
| Kursnivå i filteret | Velg Introkurs hvis dette nivået er opprettet og aktivt. Navnet «Salsa Introkurs» kan gi forslag ved entydig navnetreff. Malen oppretter eller velger ikke nivået. |
| Første dato / ukedag | 22. november i bekreftet år / tilhørende ukedag; kontroller API-forslaget |
| Antall kurskvelder | 1, også hvis kursperiodens standard er flere uker |
| Start / slutt | 16.00 / 18.30 i Europe/Oslo, altså 150 minutter |
| Kurssted og sal | Velg riktig sal ved STUDIO ORIENT, Arbeidergata 4. Sal er ikke oppgitt i kildeteksten. |
| Instruktør | Kan stå tomt til instruktør er avklart |
| Pris og påmelding | Kontroller arrangementets priskategorier, pris, påmeldingslenke og salgsvindu. Medlemsomtalen setter ingen rabatt eller pris. |

Forhåndsvisningen skal vise **akkurat én faktisk økt på riktig dato, kl. 16.00–18.30**. Kontroller også kursperiodens opphold og synlighetsvindu. Datoer og tider foreslås fra egne API-felt; teksten «2,5 timer» eller «én samling» styrer ikke kalenderen.

Sko er lagt i kursbeskrivelsen slik at dette vises også lokalt. Alt under «Praktisk informasjon», inkludert sosiale medier og kontakttekst, beholdes bare hos LetsReg. Partnerinformasjon blir tom lokalt; bekreft eventuell partnerordning før dere legger til tekst.

Ved gjenbruk: bytt dato i HTML og arrangementets datofelt, kontroller videre kurstilbud og oppdater medlemslenken ved nytt år. Behold alle fem obligatoriske markører. Faktisk lagring/henting gjennom LetsRegs editor og API må fortsatt prøves på et avtalt arrangement.
