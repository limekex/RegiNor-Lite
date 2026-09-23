# Backend: UI- og stilgjennomgang

Gjennomført 19. september 2026. Omfatter kursperioder og kursoppsett, beskrivelser/steder, nettsidevisning/TEC, kapasitet, LetsReg-tilkobling, statistikk, utseende og prosjektstatus. Importens dynamiske grensesnitt er også gjennomgått.

## Funn og rettelser

| Avvik | Konsekvens | Rettelse |
| --- | --- | --- |
| `rnl-button--secondary` i PHP og JavaScript, mens CSS bare definerte `rnl-button-secondary` | Tilbake, avbryt, rediger og andre hjelpehandlinger fikk primærknappens uttrykk | Alle interne brukere benytter samme definerte sekundærklasse |
| Bulkimportens samlede opprettelse brukte samme hjelpefunksjon som sekundærknapper | Hovedhandlingen mistet tydelig prioritet når sekundærstilen ble rettet | Opprett-knappen har eksplisitt primærklasse |
| Prosjektstatus hadde bare `wrap`, og manglet stilark i sideregisteret | Siden falt utenfor RegiNors øvrige uttrykk | Samme `rnl-ui rnl-admin`-ramme og stilinnlasting som andre backendsider |
| Bildevalg/fjerning og nullstilling av farger brukte bare WordPress-klassen `button` | Små standardknapper avvek fra resten av grensesnittet | Felles sekundærknapper med samme størrelse og fokusmarkering |
| H1 og H2 delte frontendens store skala; WordPress kunne gi avsnitt en egen mindre skriftstørrelse | Svakt overskriftshierarki og ujevne tekststørrelser | Egen backendskala for overskrifter og avsnitt; den innebygde offentlige fargeprøven beholder sin skala |
| Paneler manglet jevn vertikal avstand; priskategorier brukte store seksjonslegender | Tett eller dominerende oppsett | Felles panelavstand og mer kompakte kategorikort med feltkort |
| Generell input-selektor traff `type=range` | Alpha-slider fikk ramme og innvendig avstand beregnet for tekstfelt | Range er skilt ut, med egen berøringsflate og farge |
| LetsRegs priskategoritabell manglet rulleområde; captions og definisjonslister var ujevne | Svakere småskjermvisning og lesbarhet | Navngitt, tastaturfokuserbart rulleområde og felles tabell-/listeutforming |
| Beskrivelsesforhåndsvisning og lagret bilde brukte presentasjon direkte i HTML | Flere steder å vedlikeholde samme stil | Navngitte klasser for linjeskift og bildevisning |

`assets/admin.css` lastes bare på RegiNors åtte administrasjonssider og etter `interface.css`. Fargeprøvens dynamiske CSS-variabler beholdes i HTML: de representerer brukerens valgte farger og er ikke et stilavvik. ID-er og `data-*`-attributter for JavaScript er heller ikke manglende CSS-klasser.

## Kontroll og begrensninger

Oppfølging 20. september: paneler og feltseksjoner har 28 px vertikal marg, feltkort 18 px innvendig avstand og feltrutenettet 16 px radavstand. Knappgrupper får mer avstand, og direkte handlingsknapper får vertikal marg. Flervalg for import og LetsReg-forslag får tydelige rammer. Felles feiloversikt viser feltnavn, årsak og retting med knapper som setter fokus i feltet og åpner skjulte detaljer. Nye DOM-prøver dekker manglende dansestil/sal/kategori, retting uten tap av data, manglende/utdatert forhåndsvisning, klart kurs og bevaring av serverfeil. Dette er automatisk kontroll; avstander og uttrykk er ennå ikke visuelt godkjent i nettleser.

Kodegjennomgangen dekker PHP-generert HTML, dynamiske LetsReg-elementer, CSS-selektorer og innlasting. Eksisterende DOM-, administrasjons-, skjema-, import-, utseende- og TEC-prøver brukes til regresjonskontroll. HTTP-menyprøven er utvidet med kontroll av HTML-ramme og begge stilark på alle åtte sider, samt at backendstilarket ikke lastes på WordPress-dashbordet.

Bestått etter rettingene: PHP-lint (100 filer), CSS-parsing, `test:interface`, `test:admin` (90), `test:admin-menu` (52), `test:http` (40), `test:letsreg-import` (57), `test:appearance` (47), `test:tec-http` (15) og `test:wordpress`. Oversettelseskatalog og installerbar ZIP er oppdatert. Ingen autentiserte LetsReg-oppslag ble startet som del av gjennomgangen.

Visuell kontroll ble forsøkt med Browser-ferdigheten, men verktøyet feilet under tilkobling med `Cannot redefine property: process`. Det er derfor ikke gjort en ny visuell godkjenning. Små skjermer, 200 % zoom, native slider i ulike nettlesere, tastaturfokus og faktisk Avada/WPML-staging må fortsatt prøves visuelt. Automatiske prøver bekrefter ikke at målgruppen opplever alle oppgavene som intuitive.
