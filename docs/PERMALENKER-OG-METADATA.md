# Permalenker og metadata – M4.1

Status: **implementert og prøvd lokalt**, 20. september 2026. M4.1 utvider den offentlige kursreisen med adresser og metadata. Faktisk Avada/WPML/SEO-oppsett og delingsforhåndsvisninger i staging gjenstår som leveranseport.

Dette omfatter metadata når en kurslenke deles. Delingsknappene på kursprofilen er implementert lokalt i **M4.3**, og kalendernedlasting/abonnement i **M4.2**. Se [egen kontrakt og akseptansekriterier](KALENDER-OG-KURSDELING.md); faktiske klient-/plattformprøver gjenstår.

## Adresser og identitet

| Visning | Adresse | Eksempel |
| --- | --- | --- |
| Standard kursoversikt | `/kursrekke/` | Samme innhold og standardvalg som `[reginor_courses]` |
| Alle kurs i en kursperiode | `/kursrekke/rekkenavn/` | `/kursrekke/oslo-host-2026/` |
| Ett bestemt kurs i perioden | `/kursrekke/rekkenavn/kursslug` | `/kursrekke/oslo-host-2026/rueda-videregaende/` |

`/kursrekke/` bruker den konfigurerte kurssidens temamal og kortkodens standardvisning, filtre og synlighetsregler. «Vis alle kurs» og filterets grunnadresse peker hit når lesbare adresser er aktivert. Den eksisterende kurssiden kan fortsatt brukes til innbygging. Rotruten krever en offentlig, passordfri kursside og overtar aldri en eksisterende WordPress-seksjon. Nye ruteregler installeres automatisk én gang ved oppgradering.

Periodenavnet blir grunnlag for `rekkenavn`. Det enkelte lokale kurset blir grunnlag for `kursslug`; en gjenbrukt kursbeskrivelse er ikke en egen offentlig kursidentitet. Flere kurs med samme navn, for eksempel på ulike dager eller saler, skal få entydige adresser innenfor perioden. Perioder med samme navn skal også kunne skilles. Sluttstrek følger én valgt canonical-konvensjon og nettstedets permalinkoppsett.

Slugs foreslås automatisk og beholdes når en tittel endres. Valgfri redigering ligger under flere valg i kurs-/periodeoppsettet, med forhåndsvisning av hele adressen. ID-er forblir intern identitet. Eksplisitte slugendringer skal bevare gamle adresser som 301-videresendinger uten kjeder eller løkker. Kopiering av en periode oppretter nye adresser.

Eksisterende RegiNor-lenker med `rnl_course`/`rnl_period` skal lede til tilsvarende ny adresse når innholdet er offentlig. Filtre beholdes der de er relevante, og sporingsparametre må ikke mistes før samtykkestyrt kampanjemåling kan lese dem. Canonical skal være uten kampanjeparametre. Andre WordPress-sider, TEC-ruter og gamle ACF-kursadresser skal ikke overtas eller omdirigeres.

## SEO og sosiale delinger

Metadata skal følge siden som faktisk vises og være tilgjengelige i første HTML-respons, også uten JavaScript.

- Unik sidetittel og metabeskrivelse avledet fra kursnavn, beskrivelse, periode og sted. HTML fjernes fra metatekst. Ingen oppdiktet kursinformasjon ved manglende data.
- Canonical, robots og sitemap peker til de nye offentlige adressene. Filtervarianter skal ikke opprette konkurrerende indekserbare kopier.
- Open Graph: tittel, beskrivelse, URL, egnet type, bilde, bildets alternative tekst og språk. Tilsvarende kortmetadata for sosiale plattformer der det er relevant.
- Bildefall: kursbilde → periodebilde → felles standardbilde for kursdeling. Mangler alle, utelates bildefeltet. Valg skjer via mediebiblioteket; TECS standardbilde er et separat eksisterende valg og skal bare gjenbrukes dersom dette er eksplisitt konfigurert.
- Valgfrie overstyringer for delingstittel, metabeskrivelse og bilde ligger i kurs-/periodeoppsettet med en enkel forhåndsvisning. Ingen nye obligatoriske SEO-felt ved opprettelse av kurs.

«Tags» betyr her metadata i HTML-hodet og data for sosiale delinger. Egen søkeordtaksonomi eller `meta keywords` inngår ikke i denne leveransen.

## Event-schema og lokasjon

Dagens løsning har allerede Course/CourseInstance og Event-data for faktiske økter. M4.1 skal utvide og samordne dette grunnlaget, ikke lage en separat kalender med andre datoer.

- Kurs, periodeoversikt, kursforekomst og økter får stabile identifikatorer knyttet til de nye offentlige adressene. Velg én sammenhengende schema-struktur uten motstridende duplikater.
- Event-data bygger på faktiske økter, med start/slutt, tidssone, avlysning og flytting. Kursfrie dager opprettes ikke som arrangementer. Forskjøvet oppstart og varierende varighet må gjengis korrekt.
- Påmelding/tilbud viser bare bekreftet prisgrunnlag og status. Fra-pris må ikke presenteres som én fast pris, og parpåmelding beholder pris per deltaker. Ukjent kapasitet blir ikke til bekreftet tilgjengelighet.
- Sted knyttes til riktig økt og kurs: Place med stedsnavn, PostalAddress med tilgjengelige adressefelt og GeoCoordinates når kursstedet har et lagret, kontrollert kartpunkt. Flyttede økter kan ha annet sted.
- Manglende adresseledd eller koordinater utelates, ikke gjettes. Kartpunkt og offentlig kart skal bruke samme lagrede kilde. Eksisterende fritekstadresse bevares; eventuell oppdeling i adressefelt må være valgfri og enkel.
- Periodens TEC-oppføring skal lenke til periodeoversikten. TEC-arrangement og RegiNors kursøkter må beholde tydelige identiteter og ikke produsere motstridende data om samme oppføring.

Strukturerte data er en korrekt beskrivelse av tilbudet; visning som utvidet søkeresultat er ikke et leveranseløfte.

## Tilgang, språk og samspill

Den samme offentlige synlighetspolicyen gjelder HTML, metadata, schema, sitemap og gamle adresser. Kladd, skjult eller utløpt innhold skal ikke lekke gjennom delingsdata eller en videresending som avslører en skjult adresse. Avklar og test returstatus ved utløpt innhold i tråd med eksisterende policy.

WPML skal oversette visningstekster og kunne gi språktilpassede slugs uten å duplisere perioder, kurs eller økter. Canonical peker til riktig språkvariant; hreflang viser bare tilgjengelige offentlige varianter. Språkbytte beholder kursidentiteten.

Rutene skal bruke nettstedets vanlige Avada-/WordPress-ramme og den eksisterende kursrendereren. Før implementering kartlegges hvilket SEO-plugin nettstedet faktisk bruker. Metadata skal ha én ansvarlig produsent per felt, med integrasjon der et SEO-plugin allerede leverer feltet. Kontroller også Avada og TEC. Rewrite-regler oppdateres ved aktivering eller konfigurasjonsendring, ikke ved hvert sidebesøk.

## Akseptansekriterier

- [x] Lokalt: direkte åpning og intern navigasjon virker for både periode og kurs, med og uten JavaScript.
- [x] Lokalt: like navn, norske tegn, kopiering og slugendring gir entydige adresser og korrekte redirects; annen periodes kursslug viser ikke feil kurs.
- [x] Lokalt: tidligere RegiNor-lenker fungerer, mens gamle ACF-, WordPress- og TEC-adresser beholder sin eksisterende oppførsel.
- [x] Lokalt: kildeteksten inneholder riktig tittel, beskrivelse, canonical, robots og delingsmetadata; bildevalgenes reserver er prøvd.
- [x] Lokalt: kladd/skjult/utløpt kurs er utilgjengelig i alle nye kanaler, også via tidligere slugs og språkvarianter.
- [ ] Sitemap, språkbytte, hreflang, filtre og kampanjelenker er prøvd med faktisk WPML- og måleoppsett.
- [x] Lokalt: Event-data stemmer med faktiske datoer, sommertid, opphold, flytting, avlysning, prisgrunnlag og sted; manglende kartpunkt gir ikke fiktive koordinater.
- [ ] Schema og sosiale forhåndsvisninger er kontrollert i staging. Ingen motstridende metadata fra Avada, TEC eller nettstedets SEO-plugin.
- [x] Lokalt: cache og synlighetsutløp følger eksisterende krav på de nye adressene. Tilbakeføring til tidligere kursinngang er dokumentert.


## Bruk og lagring

- Åpne **Adresse og deling** på kurset eller fanen **Periode**. Seksjonen er sammenfoldet og alle overstyringer er valgfrie. Egen lagring endrer ikke undervisning, påmeldingsstatus eller publisering.
- **Nettsidevisning → Adresse og deling** har felles delingsbilde og bryter for lesbare adresser. Mediebiblioteket åpnes for brukere med opplastingsrettighet. Kursansvarlige uten den rettigheten kan endre tekst/adresse og bruke eksisterende standardbilde.
- Slugs opprettes fra navn ved opprettelse, med nummersuffiks ved kollisjon. Eksisterende RegiNor-objekter får navnebasert identitet ved første oppstart etter oppgradering, før offentlig visning og TEC lager lenker. Dette krever ikke et administratorbesøk. Tidligere reserveadresser som `kurs-628/kurs-629` beholdes som aliaser og videresendes direkte til navneadressen. Egendefinerte slugs og delingsvalg beholdes. Gamle ACF-data berøres ikke.
- Privat `_rnl_web`-metadata inneholder versjon, språkvalg, gamle slugs og begrenset endringshistorikk. Lagring krever objektets redigeringsrettighet, nonce, versjonskontroll og samme skrivelås som kurslageret. Ingen generisk REST-tilgang.
- Gamle slugs reserveres for samme objekt. Videresendingen beregnes direkte til gjeldende adresse, også når både periode og kurs har fått ny slug. Skjult mål gir 404 i stedet for redirect.
- WPML-språk med en faktisk publisert oversettelse av kurssiden kan velges i delingsseksjonen. Her lagres språkets slug/tittel/beskrivelse/bilde uten kopiering av driftsdata. Uten overstyring brukes standardidentiteten; ordinære kursbeskrivelser følger eksisterende WPML String Translation.
- Rewrite oppdateres ved aktivering, engangsoppgradering og endring av nettside-/adressevalg. Den oppdateres ikke ved hvert offentlig besøk. En eksisterende WordPress-side eller offentlig CPT-/taksonomi med roten `kursrekke` hindrer at denne roten overtas.

## Metadataeierskap og sitemap

RegiNor produserer metadata for sine virtuelle kurs-/periodesider. På disse sidene undertrykkes Yoasts head-presentere gjennom `wpseo_frontend_presenters`, og Rank Maths `rank_math/head`-handlinger. Vanlige sider beholder sine produsenter. Tittelfiltre, robots og canonical tilpasses kursidentiteten. Dette forhindrer at den felles innbyggingssiden gir alle kurs samme bilde og schema. Integrasjonspunktene er kodet; de faktiske pluginene er ikke installert lokalt og må prøves i staging.

WordPress-provider `reginor` inkluderer kurs og perioder med samme synlighetsfilter. En separat XML-inngang `/?rnl_sitemap=index` med paginerte undersitemaps virker også når SEO-pluginen slår av WordPress' standardprovider. Yoast-/Rank Math-indeksfiltrene legger til undersitemapene direkte. Disse svarene får `no-store`; skjulte ruter og oversettelser utelates. Hreflang overtar WPMLs generelle sidealternativer bare på de virtuelle sidene.

Kilder for integrasjonspunkter: [Yoast sitemap](https://yoast.com/help/add-external-sitemap-to-index/), [Yoast schema](https://developer.yoast.com/features/schema/api/), [Rank Math sitemap](https://rankmath.com/docs/filters-and-hooks/admin/sitemap/). Staging må også kontrollere om Avada eller et annet SEO-plugin skriver egne felt.

## Lokal verifikasjon

- `test:permalinks`: 50 kontroller av identitet, like navn, versjonskonflikt, rettigheter, aliaser, bildeprioritet, språk via WPML-stub, metadata, schema, sitemap og utløp.
- `test:permalinks-http`: 47 kontroller av direkte ruter, HTML-head, 301 uten kjeder, slash-konvensjon, sporingsparametre, filter/noindex, XML og skjulte adresser.
- Eksisterende `test:public` og `test:public-http`: 74 og 51 kontroller. Schema-testens adresseforventning er oppdatert til PostalAddress.
- Øvrige beståtte regresjonsprøver: lagring 61, administrasjon 91, publiseringsflyt 86, kurs-HTTP 40, TEC 35 + TEC-HTTP 15, samtykkeflyt 34 og statistikk 27. WordPress-oppstart og Composer (202 tester / 396 assertions) bestått.
- POT er oppdatert, og ZIP-pakken er kontrollert mot alle 105 runtimefiler. CI er utvidet; GitHub-kjøring er ikke utført i denne leveransen.
- Delingsskjemaets JavaScript prøves med DOM-test for tekstescaping, bildevalg/fjerning og standardtekst.

WPML-testen bruker stubber. Lokal WordPress har The Events Calendar, men ikke Avada, WPML, Yoast eller Rank Math. Ingen ekstern søkemotor-/delingsgodkjenning er utført.

## Tilbakeføring og innføring

Fjern haken for lesbare adresser under Nettsidevisning for å bruke den tidligere kursinngangen med query-parametre. Kursdata, delingsvalg og aliaser beholdes. Dette er en teknisk tilbakeføring; tidligere delte pretty-lenker vil da ikke være aktive. Aktiver igjen for å gjenopprette dem. WordPress må ha lesbare permalenker og fungerende server-rewrite for de nye adressene.

Unnta **`/kursrekke/*` på alle språk**, kurssiden, query-inngangene og RegiNor-sitemap fra fullside-/CDN-cache. Bekreft cacheutløp, språkbytte, metadataeierskap, Tastatur/mobil og mediebibliotek i faktisk Avada/WPML-oppsett før lansering.
