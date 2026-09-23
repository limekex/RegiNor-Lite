# Kurs uten LetsReg-kobling

Fra 0.1.3 finnes to ekstra valg under **Kursoppsett → Pris og påmelding → Påmeldingsstatus**, og i statusmenyen under **Kurs i perioden**:

| Valg | Oppsett | Deltakeren ser |
| --- | --- | --- |
| Egen påmeldingslenke | Full HTTPS-lenke, uten innloggingsopplysninger, på vanlig HTTPS-port. Ingen LetsReg-kobling kreves. | «Meld meg på» åpner adressen i ny fane. Parametre og anker beholdes. Periodens salgsvindu og eventuelle kursgrenser gjelder. |
| Kun drop-in | Drop-in-pris per person og kurskveld; 0 for gratis. Ingen lenke eller LetsReg-kobling kreves. Drop-in aktiveres ved lagring. | «Kun drop-in – møt opp uten påmelding», pris per kveld og kursdetaljer. Ingen påmeldingsknapp eller nettbasert tilbud for hele kurset. |

Drop-in følger kursenes synlighet, avlysning og slutt, men ikke et salgsvindu for forhåndspåmelding. Bruk **Prisvilkår og tillegg** til betalingsmåte og praktisk oppmøteinformasjon. Vanlig kurspris lagres fortsatt, men brukes ikke som offentlig pris for «Kun drop-in».

Status kan byttes på publiserte kurs uten å gjøre perioden til kladd. Før bytte må nødvendig lenke eller drop-in-pris være lagret i kursoppsettet. Menyen gir en konkret forklaring dersom noe mangler. Et statusbytte endrer ikke øktplan eller periodens versjon.

Eksisterende LetsReg-koblinger fjernes ikke av statusbyttet; bruk «Fjern koblingen» hvis kurset ikke lenger skal følges opp mot LetsReg. Kategoriantall vises bare i automatisk LetsReg-modus. Egne eksterne lenker telles ikke feilaktig som `rnl_letsreg_click`; en egen analysehendelse for slike lenker er ikke del av denne leveransen. Eksisterende LetsReg-lenker beholder sin måling.
