# Nivåarkiver – 0.1.27

Nye redaksjonelle nivåarkiver ligger under `/kursrekke/niva/<nivåadresse>/`. Gamle `/niva/`-taksonomier, ACF-felt, relasjoner og kurs endres ikke. Dette er RegiNors egne visninger av eksisterende nivåoppføringer, ikke en ny offentlig WordPress-taksonomi.

## Oppsett

Administrator åpner **Kursinnhold og ressurser → Kursnivåer → ønsket nivå → Nivåarkiv på nettsiden**.

1. Fyll inn arkivtittel, adresse, introduksjon over kursene og ekstra informasjon etter kursene. De to lange tekstfeltene har Visuell/HTML-editor med samme tillatte HTML som øvrig kursinnhold.
2. Velg eventuelt FAQ- og artikkelutvalg. Hvert valg viser **innholdstype · taksonomi · stikkord**. Dermed kan den eksisterende FAQ-typen bruke sin egen taksonomi, mens artikler bruker WordPress-innlegg og innleggstikkord. Hierarkiske kategorier kan også velges, men underkategorier inkluderes ikke automatisk.
3. Velg 1–12 treff per seksjon og nyeste først eller alfabetisk. Nye publiserte treff kommer automatisk med; dette er ikke et manuelt ACF Relationship-utvalg.
4. Kryss av **Publiser nivåarkivet** og lagre. Opprettelse av et nivå alene publiserer ikke et arkiv. Publisering påvirker ikke kursperiodenes status. Bruk **Se nivåarkivet** for kontroll.

Kursdelen viser bare riktig nivå og offentlig tilgjengelige pågående/kommende perioder med treff. Besøkende kan velge periode, dag og kort/kalender, men kan ikke utvide nivåutvalget gjennom URL-parametere. Introduksjon, ekstratekst og relatert innhold blir stående hvis ingen aktuelle kurs finnes. Nivåets «Tilgjengelig for nye kurs» er et separat driftsvalg, ikke arkivets publiseringsbryter.

På skjermer fra 1000 px står artiklene til venstre med 2/3 av tilgjengelig bredde og FAQ-ene til høyre med 1/3. Under denne bredden stables de med artiklene først. Har bare én av seksjonene innhold, bruker den hele bredden.

FAQs vises som en kort liste med tittel, tekstutdrag og **Les hele svaret** til den eksisterende FAQ-posten. Artikler vises med bilde når det finnes, tittel, utdrag, dato og lenke i rolige kort. Karusellen viser to kort på brede flater og ett med antydning av neste på mobil. Den skifter automatisk hvert sjuende sekund, har piler og pause/start, stopper ved fokus/musepeker og når fanen er skjult. Berøring pauser avspillingen; redusert bevegelse gir pause som standard. Manuell rulling og lenker fungerer uten JavaScript.

Utvalget viser bare publiserte innlegg uten passord. Tomme FAQ-/artikkelseksjoner skjules. Ingen ACF-data leses eller kopieres, og ingen WordPress-innlegg/termer opprettes av oppsettet.

## Språk

Arkivtekstene og adressene redigeres i egne språkseksjoner på samme nivå. Tilgjengelige språk følger WPML og publiserte oversettelser av den valgte kurssiden, slik som øvrige delingsadresser. Felles publisering, kildevalg, antall og sortering endres bare under **Originalspråk og felles valg**. Dette separate metadataoppsettet ignoreres av WPMLs automatiske kopiering; nivå- og kurs-ID-er dupliseres ikke.

WPMLs objektkobling finner valgt term og relevante innlegg på gjeldende språk. Innholdstyper og taksonomier må være satt opp for oversettelse i WPML. Manglende oversettelse av term/innlegg gir ingen treff fra originalspråket. Ikke-oversettbare innholdstyper følger WPMLs delte innholdsoppsett. Arkivets egne tekster/adresse faller tilbake til originalen frem til en språkvariant er lagret. Dette er en eksplisitt forskjell fra relaterte artikler/FAQs.

Språkvelger, canonical, Open Graph og sitemap peker til nivåarkivet på valgt språk. Ingen garanti om faktisk WPML/Avada-kompatibilitet utledes fra lokale hook-stubber; den kombinasjonen skal kontrolleres i staging.

## Adresser og drift

`niva` er reservert som nytt kursperiodenavn i adressene. En ny periode med dette navnet får et annet unikt slug; manuell reservasjon av `niva` avvises. Eksisterende perioder/aliaser med denne adressen omdøpes ikke: pene nivåadresser slås da av og administrasjonen forklarer konflikten. Vanlig spørreadresse til valgt kursside brukes som reserve. Tilsvarende gjelder en eksisterende side eller registrert rute som eier dette området.

Endret nivåadresse bevarer tidligere adresser som videresending til gjeldende adresse. Avpubliserte arkiver og deres aliaser returnerer 404 og fjernes fra sitemap. Adressehistorikken reserveres også mens arkivet er avpublisert. Dynamiske svar bruker RegiNors no-store-policy for LiteSpeed/Cloudflare. Redigering krever administrator, gyldig nonce, riktig nivåobjekt og versjonskontroll på server.

Arkivet bruker sidemalen til valgt kursside. Hvis temaet viser kurssidens egen overskrift over innholdet, skjules denne i sidemalen/Avada-oppsettet; nivåarkivet har allerede sin egen hovedoverskrift. Dette endres ikke globalt av pluginen.

## Kontroller

`test:level-archive` prøver lagring, validering, rettigheter, HTML, tomtilstand, relaterte innlegg, språk og reservasjon av adresser. `test:level-archive-http` prøver de faktiske rutene, innhold, cache, metadata, sitemap, alias og avpublisering. `tests/level-carousel.test.cjs` dekker avspilling, pause, fokus, tastatur, berøring og redusert bevegelse. Endelige resultater føres i releasenotatet.
