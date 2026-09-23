# Kartvelger for kurssteder

Implementert lokalt 18. september 2026. Offentlig visning oppdatert 22. september 2026.

## Bruk

Administrator åpner **RegiNor Lite → Kursinnhold og ressurser → Kurssteder → velg eller opprett sted**. Fyll inn navn og adresse, søk etter gateadresse/by, velg riktig treff og flytt markøren til inngangen. Alternativt åpnes kartet direkte og punktet velges ved klikk. Med tastatur: flytt kartet med piltastene og bruk «Bruk midten av kartet». Koordinater kan også skrives inn uten JavaScript. Punktet lagres først med **Lagre oppføring**.

Kartpunkt er valgfritt. «Fjern kartpunkt» tømmer begge koordinatfeltene; lagre for å bekrefte. Adressen beholdes. Serveren krever begge koordinater eller ingen, breddegrad −90 til 90 og lengdegrad −180 til 180. Ekvator/nullmeridian støttes. Versjonskonflikter håndteres som øvrige stedsendringer.

På det offentlige kursets detaljside vises **Kurssted og kart** i egen boks i hovedkolonnen, etter tekstområdene og før kursdatoene. Boksen inneholder stedsnavn, adresse, sal og et kart som lastes automatisk når kartpunkt er registrert. Sidekolonnens tekstadresse er en ankerlenke til boksen med tastaturfokus. En vanlig OpenStreetMap-lenke åpnes i ny fane og er tilgjengelig også uten JavaScript. Kartpunktet gjelder det faste kursstedet; særskilt flyttede kvelder viser sine egne adresser i datolisten. Det brukes ikke nettleserposisjon eller markedsføringsidentifikatorer. Steder uten koordinater beholder adressevisningen.

## Teknikk og tjenestegrenser

Leaflet 1.9.4 leveres lokalt med lisens og tilhørende ressurser. JavaScript/CSS-filenes SHA-256 stemmer med [Leaflets offisielle distribusjon](https://leafletjs.com/download.html). Ingen CDN brukes for selve biblioteket ved visning.

Kart bruker HTTPS-fliser fra OpenStreetMap med synlig kreditering og vanlig nettlesercache. Ingen bulk-/offline-nedlasting. Nettstedet må ikke fjerne HTTP Referer for kartflisene. Tjenesten har ingen tilgjengelighetsgaranti; ved større trafikk må egen flistjeneste vurderes. Se [flisetjenestens vilkår](https://operations.osmfoundation.org/policies/tiles/).

Adressesøk skjer bare ved eksplisitt søkeknapp/Enter, via en administratorbeskyttet WordPress AJAX-handling med nonce. Ingen anonym eller kursansvarlig tilgang. Serveren bruker Nominatim, identifiserer nettstedet i User-Agent, mellomlagrer resultat i ett døgn og reserverer minst to sekunder mellom nye søk for hele nettstedet med atomisk databasereservasjon. Maks fem treff, ingen autocomplete. Adressefeltene sendes bare når administrator søker; ikke send private opplysninger. Endpoint kan erstattes med filteret `rnl_map_search_endpoint`. Se [Nominatims bruksvilkår](https://operations.osmfoundation.org/policies/nominatim/).

## Verifikasjon

`test:maps` kontrollerer lagring/tømming/grenser for koordinater, salfarger, arv, skjema-ID-er og adressesøk med kontrollert HTTP-svar, cache og rategrense. `test:appearance` kontrollerer offentlig kartlenke/punkt, dag-/salfarger og kursmerker. `test:interface` har koordinatprøver; `test:admin-menu` prøver kartvelgerens faktiske administratorrespons og avvisning av feil nonce/rolle/anonyme søk.

22. september 2026: automatisk offentlig kart med kartfliser og markør, samt adresseanker med fokus, er visuelt bekreftet i lokal desktopnettleser. DOM-test dekker automatisk åpning, fortsatt klikkstyrt administrativ kartvelger og reservelenke når Leaflet mangler. HTTP-test kontrollerer at kartfilene lastes på kursets permalenke. Faktisk Avada, LiteSpeed/Cloudflare, samtykkeoppsett, mobil og adressesøk mot Nominatim må fortsatt prøves i driftsmiljøet.
