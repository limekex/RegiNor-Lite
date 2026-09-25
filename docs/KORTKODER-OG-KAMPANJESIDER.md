# Kortkoder og kampanjesider

Implementert i 0.1.21. `[reginor_courses]` beholder vanlig kursoversikt. Utvidelsen lar samme side ha flere utvalg med egne filtre og visningsvalg. «Kategorier» i denne sammenhengen betyr **kursnivåer**, som avklart med prosjekteier 25. september 2026.

## Fremhevede introkurs, deretter resten

Legg hver kortkode i et eget Avada Text Block-element, og bruk sidens egne overskrifter over dem.

Første oversikt: bare kurs med nivå **Intro** og avkrysset **Fremhev**, vist som kort uten innledning, filtre eller visningsvalg:

```text
[reginor_courses levels="intro" featured="only" default_view="list" show_header="0" show_filters="0" show_view_switch="0"]
```

Andre oversikt: alle kurs unntatt nivå **Intro**, med kalender først. Besøkeren kan filtrere og bytte mellom kort og kalender:

```text
[reginor_courses exclude_levels="intro" default_view="week" show_header="0"]
```

Begge bruker vanlig valg av kursperiode. De viser ikke automatisk alle kursperioder samtidig. Når filtrene skjules, brukes vanlig standardperiode eller et eksplisitt periodevalg i adressen. Synlighetsvinduer og publiseringsstatus håndheves som før.

## Flere nivåer

```text
[reginor_courses levels="nybegynner,intro" default_view="list" show_header="0"]
```

Navnene må svare til nivåene opprettet under **RegiNor Lite → Kursinnhold og ressurser → Kursnivåer**. Store/små bokstaver spiller ingen rolle. Både `Øvet 1` og `ovet-1` kan brukes. Ingen synonymer gjettes: «Introduksjon» matches ikke automatisk av `intro`.

Du kan også bruke nivå-ID, for eksempel `levels="123,456"`. ID er særlig nyttig ved like navn, oversettelser og navneendringer. Navnet sammenlignes med det felles norske kildenavnet; WPML kan fortsatt oversette nivånavnet som vises til deltakeren.

- `levels` viser bare oppgitte nivåer. Et ukjent nivå gir ingen treff, ikke alle kurs.
- `exclude_levels` fjerner oppgitte nivåer, også fra nivåfilteret. Utelukking vinner hvis samme nivå står i begge felter.
- Kurs uten nivå vises når `levels` er tomt, med mindre andre valg utelukker dem.
- `featured="only"` kombineres med nivåvalget. Det fremhever ikke kurs automatisk.
- Utvalget begrenses på serveren før deltakerens filtre brukes. URL-parametere kan ikke utvide utvalget.

## Alle valg

| Attributt | Standard | Betydning |
| --- | --- | --- |
| `levels` | Tomt | Kommaseparerte nivånavn eller ID-er som skal vises. |
| `exclude_levels` | Tomt | Kommaseparerte nivånavn eller ID-er som skal utelates. |
| `featured` | `all` | `all`: alle, `only`: bare fremhevede, `exclude`: utelat fremhevede. |
| `default_view` | `site` | `list`: kort, `week`: kalender, `site`: felles utseendevalg, `period`: kursperiodens valg. |
| `allowed_views` | `list,week` | Tillatte visninger. Bruk `list` eller `week` for én fast visning. |
| `show_header` | `1` | `0` skjuler innledning, periodeoverskrift og synlig treffantall. Treffantall beholdes for skjermlesere. |
| `show_filters` | `1` | `0` skjuler periode-, nivå- og dagfilter. Skjulte dag-/nivåfiltre påvirkes ikke av URL-valg. |
| `show_view_switch` | `1` | `0` skjuler visningsvalget og låser startvisningen. |

`0`, `false`, `no` og `off` betyr av for visningsbryterne. Valgt startvisning må være tillatt; ellers brukes første tillatte visning. Med bare én tillatt visning skjules visningsvalget automatisk.

WordPress-blokken **RegiNor kursoversikt** har de samme valgene. Eksempler og kort hjelp finnes også under **Nettsidevisning → Kortkoder for kampanjesider**.

## Flere oversikter på samme side

På innbyggingssider får oversiktene egne URL-parametere og anker-ID-er etter rekkefølgen på siden, for eksempel `rnl_embed[2][rnl_view]=week`. Filter- og visningslenker går til samme landingsside og bevarer andre oversikters valg og kampanjeparametere. Hvis kortkodene flyttes i innholdet, endres numrene; bruk derfor selve landingsadressen i annonser fremfor disse interne filteradressene.

Den valgte hovedoversikten beholder eksisterende `rnl_day`, `rnl_level`, `rnl_period` og `rnl_view`. «Se kurset» åpner kursets vanlige profil. Profilens tilbake-lenke går til hovedoversikten; nettleserens tilbakeknapp kan brukes for å vende tilbake til landingssiden.

Direkte LetsReg-lenker beholder opprinnelig URL, parametere, fragment, ny fane og målingsattributter. Hver oversikt har sin egen liste-markør og strukturerte kursliste. Cache- og utløpsvern gjelder også kampanjesidene.

## Kontroll

Lokale HTTP-prøver dekker de to utvalgene på samme side, uavhengig visning, navne-/ID-valg, ukjente og motstridende nivåvalg, filteralternativer, skjulte kontroller, blokken, unike ankre/schema, sporing og cache-headere. Generelle offentlige prøver og DOM-prøver kjøres i tillegg. Faktisk layout i Avada og en kampanjelandingsside i staging må fortsatt kontrolleres.
