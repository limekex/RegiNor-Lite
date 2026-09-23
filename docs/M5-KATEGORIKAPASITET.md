# M5 – kapasitet per kategori

Levert 20. september 2026. Prosjekteier valgte visning **både i frontend og administrasjonen**. Bygger på den eksisterende automatiske LetsReg-kontrollen; ingen nye deltaker-/ordrekall eller webhook-abonnementer.

## Hvor vises det?

- Kurskort og kalender: bruk «Se kurset» for kapasitetsdetaljer. Kategoritabellen er fjernet fra disse kortene.
- Kursdetalj: kategoriene står ved pris og påmelding.
- Kurs i perioden: «Se plasser per kategori» åpner den aktuelle kapasitetsoversikten.
- Kapasitet: koblede kurs viser virkelig API-observasjon, kategorinavn, rolle/påmeldingsform, rapportert antall og beregnet visning. Kurs uten kobling beholder tydelig merket demonstrasjon.
- Kursoppsett: samme tabell ligger under en sammenleggbar «Kapasitet fra LetsReg».

Frontend viser tall bare for **Automatisk fra LetsReg**, med siste kjente gyldige observasjon og effektiv status tilgjengelig/fullt/venteliste. En manuell overstyring regnes ikke som API-bekreftet kapasitet. Backend kan vise siste gyldige API-bilde også ved manuell status, men forklarer at disse tallene ikke publiseres, og at oppdatering fra LetsReg ikke endrer manuelt valgt status.

## Tallene og begrensningene

Offisiell [OpenAPI 2.2.8](https://integrate.deltager.no/swagger/v1/swagger.json), lest på nytt 22. september 2026, har `Event.availableRegistrations` og `EventPriceCategory.available`, samt kategoriens `availableFrom` / `availableTill`. Priskategorisvaret er en liste uten dokumentert paginering. Skjemaet beskriver ikke kapasitetspooler eller effekten av alle betalings-/reservasjonsstatuser.

Vi viser derfor **«Opptil X plasser»**, som en øvre grense fra kjente API-felter, ikke som en garanti for at hele antallet kan bestilles. Kontrolltidspunkt og informasjon om delt kapasitet vises sammen med tallene. LetsReg gjør den endelige bestillingskontrollen.

| Situasjon | Visning |
| --- | --- |
| Kategorien har 6, arrangementet har 20 | Opptil 6 plasser |
| Kategorien har 6, arrangementet har 3 | Opptil 3 plasser |
| Påmeldte når eller overstiger positiv plassgrense på arrangementet | Fullt |
| Plassgrense 0 på arrangementet | Ubegrenset på arrangementsnivå; kategori- og partnergrenser gjelder fortsatt |
| Kategorien og arrangementet er ubegrenset (0) | ∞ |
| Kategorien er ubegrenset, arrangementet har 8 ledige | Opptil 8 plasser |
| Ukjent/negativt/ugyldig kategoritall, positiv arrangementskapasitet | Antall ikke oppgitt |
| Kjent kategoritall, ukjent arrangementskapasitet | Antall ikke oppgitt |
| Kategorien er inaktiv eller utenfor salgsvindu | Stengt / Åpner senere, uten antall |
| Ingen gyldig observasjon | Ingen offentlige antall |

Bare eksplisitt koblede kategori-ID-er brukes. Flere kategorier for samme rolle forblir egne rader; de summeres aldri. Arrangementets samlede ledighet blir ikke kursets ledighet. Alle tall er antall deltakerplasser, ikke beløp eller bekreftede kjøp.

For parpåmelding vises én samlet rad, **i hele par**, i frontend og backend. Et par krever både fører- og følgerplass. Visningen begrenses av partnerkapasiteten og halvparten av arrangementets ledighet, avrundet ned. Eksempel: 6 førerplasser og 2 følgerplasser gir opptil 2 par; totalt 3 plasser gir opptil 1 par. Alternative kategorier summeres aldri. Manglende partner eller ugyldige verdier gir «Antall ikke oppgitt», ikke 0. Råtall for parkategorienes påmeldte/ledig summeres ikke i den samlede raden.

Prosjekteier avklarte 22. september at 0 i plassgrensen betyr ubegrenset, også for kategoriens nullverdi. Ubegrenset vises med ∞ og tilgjengelig etikett. På arrangementsnivå beregnes ledighet fra positiv `maxAllowedRegistrations` minus `registeredParticipants`, minimum 0. Ved arrangementsgrense 0 brukes kategori-/partnergrensene. Positivt `EventPriceCategory.available` brukes som rapportert øvre ledighet, og `registered` vises separat. Manglende/ugyldige verdier er ukjente, ikke ubegrensede. Semantikken for null er prosjekteiers avklaring; Swagger alene beskriver ikke spesialverdien eller delte pooler.

Siste gyldige kapasitet beholdes ved utløpt kontroll, nettverksfeil og tømt cache. Tidspunktet for faktisk henting beholdes. Under alle kategoriene står én diskret merknad: «Siste kjente kapasitet. Kategorier kan dele plasser. Faktisk tilgjengelighet bekreftes hos LetsReg.» Ved fullt arrangement og aktivert venteliste vises «Fullt - Venteliste aktiv» på kurset. Tall er fortsatt ingen reservasjon eller garanti.

## Ferskhet og sikkerhet

- Samme kontobinding, lås, kø, kontrollintervall og femten minutters ferskhetsfrist som [påmeldingsstatusen](PAMELDINGSSTATUS.md).
- Mislykket arrangementskontroll beholder siste kjente tall og status. Feil gir ikke ny suksessdato.
- Salgs- og synlighetsgrenser styrer offentlig sideutløp. API-kontrollfristen fjerner ikke siste kjente status eller kapasitet. Opprinnelig kontrolltidspunkt vises alltid.
- Ingen API-kall ved offentlig visning. Kun navn, rolle, påmeldingsform, begrenset antall/status og kontrolltid er offentlig. Ingen konto-/kategori-ID-er, rå kapasitetssvar, tokens eller deltakerdata.
- Schema får ikke `InStock`, eksakte lagerantall eller egne kategori-tilbud på grunnlag av denne øvre-grense-visningen.
- Publisering, kursplan, priser, påmeldingslenkens parametere og klikkmåling er ikke endret.

## Testbevis og neste fase

Bestått lokalt: Composer 202 tester / 396 assertions; kategorigrenser, ukjente tall, flere kategorier, partner-/totalgrenser og datoer. WordPress-statusprøven har 79 kontroller, inkludert alle tre frontendflater, reell kapasitetsadministrasjon, manuell overstyring, privat/offentlig skille og utløp. LetsReg-autentisering 160, demonstrasjonsregresjoner 39, offentlig visning 74, admin/språk 91, offentlig HTTP 51 og alle ti DOM-skript besto. WordPress-oppstart, POT og ZIP inngår i leveransen.

Ved den opprinnelige leveransen kunne visuell nettleserprøve ikke utføres: nettleserverktøyet feilet under oppstart. Faktisk Avada/mobil/WPML-kontroll gjenstår. Eksakte salgbare rolle-/pooltall, reservasjoner, betaling, refusjon, kvoter/servercron og webhook-avstemming forblir åpne M5-kriterier. Denne leveransen lukker kategorivisningen med øvre grenser, ikke hele M5 eller L4/L5.

## Tokenbruk i en kontrollrunde

Under faktisk kontroll ble ett vellykket tokenkall etterfulgt av `401 invalid_grant` ved et nytt identisk tokenkall umiddelbart etterpå. Sammenligningen kontrollerte bare at forespørslene var like; credentials og tokens ble ikke logget. Årsaken hos leverandøren er ikke fastslått.

Kontrollkøen gjenbruker nå ett gyldig token i minnet for inntil tre arrangementer i samme avgrensede kjøring. Kontoavtrykk og minst 40 sekunders gjenværende oppgitt levetid kontrolleres før gjenbruk; hvert GET-kall beholder sin utløpskontroll. Token fjernes alltid ved rundens slutt eller feil og lagres ikke i database, transients eller logger. Uten oppgitt gyldig levetid utsettes neste arrangement minst ett minutt. Manuelle kontroller beholder egen tilgangskontroll. En syntetisk leverandør som avviser andre tokenutstedelse i samme runde er dekket av regresjonstest.

Etter rettingen ble en faktisk avgrenset runde mot de to allerede koblede lokale arrangementene gjennomført. Ett tokenkall var tilstrekkelig for begge arrangementene. Begge fikk gyldig observasjon, kategorirader og offentlig HTML med kategorivisning. Ingen påmelding, betaling, koblingsendring eller publiseringsendring ble gjort. Dette verifiserer lesing og presentasjon, ikke bestillings-/poolsemantikken under endring av deltakerregistreringer.
