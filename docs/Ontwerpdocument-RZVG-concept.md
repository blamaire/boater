# Ontwerpdocument — Webapplicatie Roei- en Zeilvereniging "Gouda" (RZVG)

## Concept en detailuitwerking

*Status: levend ontwerpdocument. Deel 1 legt het concept op hoofdlijnen vast; Deel 2 bevat de detailuitwerking per module en onderdeel.*

---

## Inhoudsopgave

**Deel 1 — Concept**
- 1\. Inleiding en doel
- 2\. Doelstellingen en ontwerpprincipes
- 3\. Technische uitgangspunten
- 4\. Architectuur op hoofdlijnen
- 5\. Content- en paginamodel (CMS)
- 6\. Rechten- en rollenmodel
- 7\. Lidmaatschapsvormen en regelhandhaving
- 8\. Goedkeuringsmotor (versiebeheer en second/third view)
- 9\. Reserveringen (concept)
- 10\. Migratie uit e-Captain en WordPress
- 11\. Boekhouding, facturatie en betalingen (spoor)
- 12\. Privacy en AVG
- 13\. Modules en beheerfuncties (overzicht)
- 14\. Openstaande punten en vervolg

**Deel 2 — Detailuitwerking**
- 15\. Modulestramien
- 16\. CMS
- 17\. Activiteiten
- 18\. Reserveren
- 19\. Lidmaatschap (Lid worden en Ledenbeheer)
- 20\. Goedkeuringsmotor
- 21\. Leden zoeken
- 22\. Schade melden
- 23\. Financiën (contributie, facturatie en betalingen)
- 24\. Mailing en notificaties
- 25\. Migratie en import
- 26\. Rechtenbeheer
- 27\. Vrijwilligersplanning
- 28\. Nieuws/berichten
- 29\. Documenten
- 30\. Communicatielogboek
- 31\. Audit trail
- 32\. Reviewinstellingen
- 33\. Samenvattend datamodeloverzicht
- 34\. Implementatie- en faseringsvoorstel
- 35\. Niet-functionele eisen
- 36\. Rapportage en dashboards

---

## 1. Inleiding en doel

De RZVG wil haar bestaande opzet — een WordPress-website naast e-Captain voor ledenadministratie en logica — vervangen door één samenhangende, professioneel inzetbare webapplicatie. Die moet goed te onderhouden zijn, modern en elegant ogen, veel gebruiksgemak bieden en voldoen aan wet- en regelgeving (in het bijzonder de AVG).

Dit document beschrijft het concept: de architectuur, de belangrijkste modellen en de leidende keuzes. Het dient als fundament waaraan de applicatie gebouwd kan worden en als basis voor de detailuitwerking per module.

### Uitgangssituatie

- WordPress-website voor content.
- e-Captain voor content met logica (inschrijving activiteiten, lidmaatschap, reserveringen).
- Zeer technische opzet, waardoor veel taken bij beheerders terechtkomen.

### Gewenste situatie

- Eén webapplicatie.
- Zoveel mogelijk zelfservice, met waar nodig goedkeuring (second view).
- Promotie van eigen initiatief: leden kunnen content of wijzigingen voorstellen, gevolgd door een goedkeuringstraject.
- Sterk en inzichtelijk versiebeheer van content (en breder).

---

## 2. Doelstellingen en ontwerpprincipes

- **Zelfservice eerst.** Leden en functionarissen regelen zoveel mogelijk zelf; goedkeuring alleen waar nodig.
- **Voorstellen en goedkeuren.** Wijzigingen lopen waar van toepassing via een voorstel → review → publicatie-traject (second/third view).
- **Versiebeheer en transparantie.** Wijzigingen zijn herleidbaar, vergelijkbaar en terugdraaibaar; alle handelingen worden gelogd.
- **Modern en elegant.** Een verzorgde, hedendaagse uitstraling op basis van een consistent design-systeem.
- **Mobiel-eerst.** Alles werkt goed op een telefoon; dit is een harde ontwerpeis, geen bijzaak.
- **Toegankelijkheid.** Smaakvolle animaties die automatisch worden uitgezet voor wie "verminderde beweging" heeft ingesteld; leesbaarheid en bedienbaarheid als basis.
- **Privacy by design.** Gegevensbescherming is vanaf het ontwerp meegenomen, met extra zorg voor minderjarigen.

---

## 3. Technische uitgangspunten

- **Taal/stack:** PHP en MySQL.
- **Framework:** Laravel (aanbevolen). Voor een professioneel, onderhoudbaar systeem is een volwassen framework verkieslijk boven kale PHP — vergelijkbaar met de keuze voor Spring boven losse servlets in de Java-wereld. Parallellen voor wie uit Java/Angular/React komt:
  - Laravel ≈ Spring Boot (opinionated, "batteries included", dependency-injectie).
  - Eloquent (ORM) ≈ JPA/Hibernate, maar Active Record (minder boilerplate).
  - Composer ≈ Maven/Gradle; Artisan-migraties ≈ Flyway/Liquibase.
  - Blade-templates ≈ Thymeleaf; bestaande React/Angular-kennis is herbruikbaar voor interactieve delen.
- **Draaiomgeving:** PHP 8.2+, Composer, een webserver (nginx of Apache) en MySQL. Draait op vrijwel elke VPS of moderne hosting; geen zware infrastructuur of applicatieserver vereist.
- **Dubbele runtime (eis):** de applicatie moet zowel op een klassieke webserver (productie/hosting) als in een Docker-container (lokaal) kunnen draaien, vanuit dezelfde codebase. Een meegeleverde container-opzet (bijvoorbeeld `docker-compose` met app, webserver en MySQL) verzorgt de lokale omgeving. Uitgangspunt is **omgevingspariteit**: gelijke PHP-versie, extensies en configuratie, zodat "werkt lokaal" ook "werkt op de server" betekent. Omgevingsspecifieke instellingen lopen via configuratie/omgevingsvariabelen, niet via code.

---

## 4. Architectuur op hoofdlijnen

De applicatie kent drie toegangslagen bovenop één gedeeld kernsysteem. De lagen zijn vensters op dezelfde kern; ze bevatten zelf geen losse logica. Iemand kan gelijktijdig in meerdere lagen actief zijn (een lid is bijvoorbeeld ook beheerder).

1. **Publieke laag** — werving en open informatie. Doelen: nieuwe leden informeren en werven, niet-leden informeren over open activiteiten (met inschrijfmogelijkheid), en de mogelijkheid bieden zich als lid in te schrijven.
2. **Besloten laag** — zelfservice voor leden en functionarissen: informatie raadplegen, privileges van lidmaatschap/functie gebruiken, en toevoegingen/wijzigingen voorstellen.
3. **Beheerlaag** — de back office voor de bedrijfsvoering van de vereniging.

De lagen zijn een logische, geen visuele scheiding. De beheerlaag is zoveel mogelijk **contextueel geïntegreerd** in de publieke en besloten front-end: beheren gebeurt waar het kan in-context op de pagina zelf (ter plekke bewerken, contextuele acties), binnen dezelfde app-omgeving en hetzelfde design-systeem. Alleen zwaardere back-officetaken (zoals boekhouding, bulk-ledenbeheer en mailing) krijgen eigen schermen, met dezelfde uitstraling.

**Toegang en autorisatie** vormen de poort vóór alle lagen: hier wordt per persoon bepaald wat zichtbaar en toegestaan is, op basis van twee onafhankelijke assen (zie hoofdstuk 6).

Het **kernsysteem** levert diensten die alle lagen delen:

- de generieke **goedkeuringsmotor** (voorstel → review → publicatie);
- **content en modules** (het CMS- en paginamodel);
- **rechten en rollen** (autorisatie);
- **audit en versiebeheer** (logging en historie).

**Migratie** uit e-Captain en WordPress staat bewust als apart spoor onder de architectuur: een eenmalige gegevensoverdracht, niet verweven met de live-werking.

---

## 5. Content- en paginamodel (CMS)

De applicatie bevat drie soorten inhoud:

1. **Content** — beheerd via het CMS.
2. **Systeempagina's** — beheerd via het CMS, maar niet te verwijderen (zoals de homepage).
3. **Modules** — herbruikbare functionele componenten die aan pagina's worden toegevoegd.

### Opbouw van pagina's

Een pagina is een **geordende reeks blokken**. Een blok is óf een **contentblok** (tekst, beeld, media) óf een **moduleblok** (een verwijzing naar een herbruikbare module, zoals "Lid worden", "Reserveren", "Activiteiten" of "Leden zoeken").

De gekozen opbouw is **hybride** (zones met vrije blokken): een sjabloon legt vaste zones vast, en binnen elke zone is de redacteur vrij blokken toe te voegen en te ordenen. Dit garandeert een consistente lay-out terwijl zelfservice mogelijk blijft, zonder dat iemand per ongeluk een pagina "sloopt".

Concreet vertaalt dit zich naar een **band-gebaseerde** opmaak: een pagina is een verticale stapel banden (volle breedte), en elke band kiest een kolomindeling (één onderwerp over de volle breedte, of twee/drie naast elkaar). Op een telefoon vallen meerkolomsbanden automatisch terug naar één kolom.

### Uitstraling

De moderne, elegante uitstraling komt uit een **design-systeem**: vaste typografie, ruimte- en kleurtokens, en herbruikbare bandtypes. Dat houdt het geheel verzorgd en consistent, ook wanneer verschillende vrijwilligers pagina's bouwen. Subtiele scroll-animaties zijn toegestaan, maar worden automatisch uitgezet bij de voorkeur "verminderde beweging".

### Overige uitgangspunten

- **Eén CMS-engine** voor zowel publieke als besloten pagina's; zichtbaarheid wordt per pagina geregeld via de autorisatielaag.
- **Navigatie**: menu's vullen zich automatisch uit de paginahiërarchie, met de mogelijkheid handmatig items toe te voegen, te herordenen of te verbergen (beide mogelijk).
- **Gedeelde mediabibliotheek** voor beeld en documenten (sluit aan op Documentbeheer, 3.04).
- **Versiebeheer per pagina** via de goedkeuringsmotor.
- **Nederlandstalig.**
- **SEO-basis**: nette URLs, meta-gegevens en een sitemap.
- **Samenwerken aan content**: meerdere redacteuren kunnen tegelijk aan dezelfde pagina werken, zonder vergrendeling. Elke bewerking vertakt vanaf een basisversie; bij overlappende wijzigingen ontstaan conflicterende versies, die achteraf via een conflictresolutiescherm worden samengevoegd. De vergelijking gebeurt per blok, zodat niet-overlappende wijzigingen automatisch samengaan en alleen botsende blokken handmatige resolutie vragen. Realtime tonen van wat een ander wijzigt is niet nodig.

---

## 6. Rechten- en rollenmodel

De autorisatie rust op twee **onafhankelijke assen** die samenkomen in één persoon en samen één beslissing voeden.

Een **persoon** is de centrale entiteit. Een inlogaccount is daar los aan gekoppeld en optioneel: zo kunnen ook personen worden vastgelegd die (nog) niet inloggen, en niet-leden met enkel een functierol.

### As 1 — Lidmaatschap → ledenrechten

Een persoon kan een **lidmaatschap** van een bepaalde vorm hebben. De vorm bepaalt de **ledenrechten** (botengebruik, instructie, introducés, wedstrijddeelname). Zie hoofdstuk 7.

### As 2 — Functierollen → permissies

Een persoon kan **nul of meer functierollen** hebben. Belangrijk: rollen veronderstellen géén lidmaatschap — een terrein- of gebouwbeheerder kan een niet-lid zijn. Een rol bundelt **permissies**, granulair per module × actie (inzien, toevoegen, wijzigen, publiceren, goedkeuren, verwijderen) — overeenkomstig de functielijsten in de beheermodules.

### Werking en uitgangspunten

- **Cumulatief**: de effectieve permissies van een persoon zijn de optelsom van al diens rollen.
- **Beheerbare rollen**: een rechtenbeheerder stelt rollen zelf samen uit permissies, met zinnige standaardrollen vooraf (sluit aan op Rechtenbeheer, 3.01).
- **Globaal in v1**: rollen gelden systeembreed. Scoping op objectniveau (bijv. "beheerder van één specifieke activiteit") is een latere uitbreiding.
- **Least privilege** als principe; toekenning van rollen en gevoelige handelingen worden gelogd.
- **Inloggen als andere gebruiker** (3.15, supportdoel) is een aparte, streng gelogde permissie.

---

## 7. Lidmaatschapsvormen en regelhandhaving

### Vormen

| Vorm | Kern |
|---|---|
| A-lid | Min. 21 jaar; botengebruik en instructie toegestaan; mag 3×/jaar introduceren |
| B-lid | Min. 21 jaar; botengebruik toegestaan; mag 3×/jaar introduceren (geen instructie) |
| Gezins-/partnerlid A | Partner van A-lid (huwelijk of gezamenlijk adres); zelfde rechten als A-lid |
| Gezins-/partnerlid B | Partner van B-lid; zelfde rechten als B-lid |
| Jeugdlid | 12 tot 21 jaar; botengebruik toegestaan |
| Studentlid | Rechten als A-lid; geldige OV-studentenkaart en studentenpas vereist; geldt per kalenderjaar, vervalt automatisch tenzij vóór 1 november verlengd |
| Buitenstudentlid | Rechten als studentlid; 21 tot 27 jaar; botengebruik alleen in de zomerschoolvakantie; geen wedstrijden |
| Aspirantlid | Jonger dan 12; wordt jeugdlid bij aanvang van het verenigingsjaar waarin men leerling van minimaal groep 4 wordt |
| Sociëteitslid | Alleen toegang tot de sociëteit |

*Bron: lidmaatschapspagina, statuten en huishoudelijk reglement van de RZVG. De precieze juridische formuleringen worden in de detailfase geverifieerd tegen statuten en HR.*

### Handhavingsbeleid

Het systeem is overwegend **signalerend**: het waarschuwt en laat de beslissing aan een mens, behalve waar veiligheid of kernregels dat anders vereisen. Per regel:

- **Leeftijdsgrenzen** — signaleren; een mens bepaalt de vervolgactie.
- **Botengebruik (reserveringen)** — hard afdwingen: staat de lidmaatschapsvorm geen botengebruik toe, dan is reserveren niet mogelijk.
- **Seizoensbeperking ("alleen zomervakantie")** — niet afdwingen.
- **Studentbewijs** — het systeem nodigt periodiek uit bewijsmiddelen (OV-kaart, studentenpas) op te sturen of een andere vorm te kiezen. Bij uitblijvende reactie escaleert het naar een mens, die over beëindiging beslist. Geen automatische opzegging.

De regels worden vastgelegd als een **aanpasbare regelset** (zonder codewijziging), die signalen oplevert. Die signalen verschijnen op het moment van handelen én in de review-stap van de goedkeuringsmotor. Een mens kan altijd doorzetten; zo'n overschrijving wordt gelogd.

---

## 8. Goedkeuringsmotor (versiebeheer en second/third view)

Eén generiek mechanisme bedient álle voorstel-soorten: contentwijziging, activiteit, ledenmutatie en reservering. Per soort hangt er een **beleid** dat de routering bepaalt.

### Routering

- **Binnen beleid** (bijv. een reservering onder de drempel, een import van bestaande data, een kleine wijziging door een vertrouwde rol) → direct toegepast.
- **Buiten beleid** → review, met een instelbaar aantal beoordelaars (tweede view; voor gevoelige zaken een derde).
- **Direct doorvoeren zonder review** → bepaalde vertrouwde rollen hebben een permissie om de review-stap over te slaan. De wijziging wordt meteen toegepast en verschijnt in de logging/audit trail, zodat goedkeurders achteraf kunnen zien wat er gebeurd is en zo nodig kunnen ingrijpen (zichtbaarheid achteraf in plaats van vooraf).

### Regels en uitkomsten

- **Functiescheiding**: de beoordelaar mag niet de indiener zijn.
- Een review-stap kan worden toegewezen aan een **groep goedkeurders**, waarbij één goedkeuring uit de groep volstaat. Dit is combineerbaar met meerdere opeenvolgende stappen (tweede/derde view).
- Een review kent naast **goedkeuren** ook **afkeuren** (met reden) en **terugsturen** (om aan te passen).
- Een indiener kan een voorstel **intrekken**.
- De **signalen** uit hoofdstuk 7 worden in de review-stap getoond, zodat de beoordelaar ze meeweegt.

### Versiebeheer en audit

Elke goedgekeurde of direct doorgevoerde wijziging levert een **nieuwe versie** op. Vorige versies blijven bewaard, zijn vergelijkbaar en terugdraaibaar. Alle handelingen — inclusief overschrijvingen en directe doorvoeringen — landen in de **audit trail** (3.13).

---

## 9. Reserveringen (concept)

- **Hard recht**: botengebruik wordt vooraf in de reserveringsmodule geblokkeerd als de lidmaatschapsvorm dit niet toestaat. (De goedkeuringsmotor gaat over *goedkeuren*, niet over *mogen*.)
- **Beleidsdrempels**: een vaste regelset bepaalt wat zónder goedkeuring mag, bijvoorbeeld een maximum aantal objecten per dag en een maximum aantal uren per object.
- **Boven de drempel**: het verzoek mag nog steeds worden gedaan, maar loopt dan via de goedkeuringsmotor.
- Functies: reserveringen inzien, toevoegen, wijzigen en intrekken; daarnaast schade melden aan verenigingsobjecten.

---

## 10. Migratie uit e-Captain en WordPress (spoor)

- **Bronnen**: ledenadministratie en logica uit e-Captain; content (pagina's, berichten) uit WordPress.
- **Eenmalige migratie** van leden, lidmaatschappen, historische gegevens en eventueel reserveringen/financiële posten, met de mogelijkheid van een tijdelijke parallelloop.
- **Tussenformaat**: de importmodule leest een gestandaardiseerd tussenformaat (vermoedelijk CSV/Excel). Dat ontkoppelt de bron van het datamodel; of dat tussenformaat handmatig wordt geëxporteerd of met een script wordt gevuld, staat los van het ontwerp.
- **Migratie-vriendelijk datamodel**: herkomst-ID's worden bewaard (bijv. `ecaptain_id`) om te kunnen traceren en zonder dubbelen te her-importeren.
- **Dry-run en rapportage** vóór de definitieve import; geïmporteerde records worden als "reeds bestaand" gemarkeerd en omzeilen de review-workflow.
- **Archief en zichtbaarheid**: sommige gegevens worden alleen bewaard voor latere raadpleging en hoeven niet voor iedereen toegankelijk te zijn (bijvoorbeeld oude activiteiten en oude berichten/pagina's). Zulke data wordt in een **gearchiveerde** staat geïmporteerd, met beperkte zichtbaarheid die via de autorisatielaag wordt geregeld.
- Een eventuele latere live-koppeling is een apart, los te bouwen spoor. Een reverse-engineerde e-Captain-API wordt hooguit als eenmalig extractiemiddel overwogen, niet als permanente afhankelijkheid (let op gebruiksvoorwaarden en fragiliteit).

---

## 11. Boekhouding, facturatie en betalingen (spoor)

- **In-app boekhouding**, bij voorkeur op basis van open source, abonnementsvrij. Zelf-gehoste open source betekent geen abonnement; de kosten verschuiven naar zelf hosten en onderhouden.
  - *Akaunting (On-Premise, Standard)*: gratis, open source, op Laravel/PHP — dezelfde stack als dit project — met dubbel boekhouden. Kanttekening: veel uitbreidingen lopen via een app-store met betaalde modules (o.a. payroll, CRM, custom velden, branding/factuuropmaak, extra betaalkoppelingen, en zelfs het omzetten van enkel naar dubbel boekhouden). Voor de bescheiden behoefte van een vereniging is de gratis kern doorgaans toereikend, maar er is een reëel risico dat één of twee gewenste functies tóch betaald zijn. In reviews klinken bovendien zorgen over ondoorzichtige facturatie, zwakke support na aankoop en bugs in betaalde modules — een leveranciersrisico om mee te wegen.
  - *LedgerSMB*: een volwaardige open-source boekhoud-/ERP-keuze voor rigoureus dubbel boekhouden, ook in het Nederlands beschikbaar. Let op: geschreven in Perl (met PL/pgSQL) en draaiend op PostgreSQL — dus een andere stack dan PHP/MySQL — en sterk op accountants gericht. "Minder toegankelijk" betekent hier een stevigere leercurve en een meer technische interface, plus lastiger te integreren door de afwijkende techniek.
  - *Alternatief om te overwegen*: gezien de bescheiden behoefte (contributie, facturen, grootboek, SEPA/iDEAL) kan het verstandig zijn de boekhouding licht zelf te bouwen op de Laravel-stack (eventueel met een open-source facturatietool als InvoiceNinja of Crater), met export naar een externe boekhouder. Afweging: "niet het wiel opnieuw uitvinden" tegenover leveranciersafhankelijkheid.
  - Het betreft complete applicaties, geen kant-en-klare module: we koppelen ernaar of nemen selectief de boekhoudkern over.
- **Betalingen** via Mollie: iDEAL en SEPA-incasso (met mandaatbeheer).
- Functies in dit spoor: facturatie/contributie-inning, en koppeling met de boekhouding.

---

## 12. Privacy en AVG

- **Minderjarigen**: jeugd- en aspirantleden zijn minderjarig. Toestemming van ouders/verzorgers en extra zorgvuldigheid met hun gegevens zijn vereist.
- **Veld-niveau zichtbaarheid**: leden kunnen per gegeven (bijv. telefoon, adres, e-mail) zelf bepalen wie het onder andere leden mag zien. Dit wordt vroeg in het datamodel meegenomen en raakt de module "Leden zoeken".
- **Least privilege en logging**: minimale rechten als uitgangspunt; gevoelige handelingen en inzage worden gelogd.
- De precieze grondslagen, bewaartermijnen en een verwerkingsregister worden in de detailfase uitgewerkt.

---

## 13. Modules en beheerfuncties (overzicht)

**Publieke laag**
- Lid worden (verbonden met de ledenadministratie).

**Besloten laag**
- Contactgegevens van andere leden zoeken (met veld-niveau zichtbaarheid).
- Lidmaatschap en gegevens beheren.
- Reserveren van verenigingsobjecten (inzien, toevoegen, wijzigen, intrekken).
- Schade melden aan verenigingsobjecten.
- Activiteiten (aanmelden, toevoegingen/wijzigingen voorstellen).

**Beheerlaag**
- 3.01 Rechtenbeheer — overzicht van rechten, onderhouden van rollen.
- 3.02 Activiteiten — inzien, toevoegen, wijzigen, publiceren/intrekken/archiveren, verwijderen, goed-/afkeuren.
- 3.03 Boekhouding.
- 3.04 Communicatielogboek — loggen van contact (telefoon, social media, e-mail, gesprek).
- 3.05 Documentbeheer.
- 3.06 Factureren.
- 3.07 Importeren.
- 3.08 Ledenbeheer.
- 3.09 Mailing.
- 3.10 Reserveringen.
- 3.11 Vrijwilligersplanning.
- 3.12 Webbeheer — pagina's inzien, toevoegen, wijzigen, publiceren/intrekken/archiveren, verwijderen, goed-/afkeuren.
- 3.13 Audit trail.
- 3.14 Reviewinstellingen.
- 3.15 Website zien als een andere gebruiker (support).

*(Nummering volgens de aangeleverde opzet; in de detailfase wordt de nummering opgeschoond.)*

---

## 14. Openstaande punten en vervolg

**Nog te bepalen / uit te zoeken**
- Wijze van data-extractie uit e-Captain (handmatige CSV/Excel-export of script) en uit WordPress.
- Aanpak boekhouding: bestaand open-source pakket koppelen (Akaunting) vs. licht zelf bouwen op de Laravel-stack; mate van integratie.
- Uitwerking van conflictdetectie en het resolutiescherm bij gelijktijdige bewerking (optimistische concurrency, vergelijking per blok).
- Precieze reserveringsdrempels per objecttype.
- Standaardrollen en hun permissiesets; samenstelling van goedkeurdersgroepen.
- AVG: grondslagen, bewaartermijnen, verwerkingsregister en regeling ouderlijke toestemming.

**Vervolg (detailfase, per module)**
- Datamodel (entiteiten en relaties).
- Schermontwerpen/wireframes per module.
- API- en interactieontwerp.
- Concrete beleids- en regelsets (handhaving, reserveringen, goedkeuring).

**To-do (uit de review)**
- Overdracht van zeggenschap bij meerderjarigheid (jeugdlid → 18): ouder/verzorger verliest `may_act_on_behalf`.
- Samenloop verenigingsjaar (contributie) en kalenderjaar (studentverlenging vóór 1 november).
- Partnerafhankelijkheid: wat gebeurt er met een afgeleid partner-/gezinslidmaatschap als het hoofdlidmaatschap eindigt.
- Opzegtermijn, opzegmoment en eventuele restitutie bij opzeggen.
- Inspanning voor de e-Captain-mapping, in het bijzonder financiële historie en het afleiden van huishoudens/partnerrelaties (reserveer hier tijd).

**Out of scope (v1)**
- Registratie van introducés en dagpassen (loopt via een papieren boek).
- Plaatsing van sponsoradvertenties (formaat, vertoningsduur); sponsoring loopt uitsluitend als gefactureerd product.

---

*Einde fase 1 — conceptueel ontwerp.*

---

# Deel 2 — Detailuitwerking

*Per module en deelgebied uitgewerkt, voortbouwend op het concept. Groeit mee naarmate meer modules worden uitgewerkt.*

## 15. Modulestramien

Elke module wordt langs hetzelfde stramien uitgewerkt:

- **Front-end-weergaven**: overzicht, detail, acties (zoals inschrijven), en — vanuit de besloten laag — voorstellen via de goedkeuringsmotor.
- **Beheeraspecten**: levenscyclus (concept → publiceren → intrekken → archiveren → verwijderen), goed-/afkeuren, en modulespecifieke beheerschermen.
- **Datamodel-uitbreiding**: de entiteiten en velden van de module.
- **Koppelingen**: relaties met andere modules en het kernsysteem.
- **Instellingen en zichtbaarheid**: gedeelde opties (waaronder zichtbaarheid: iedereen / alleen leden / specifieke rol).

## 16. CMS — detailuitwerking

### 16.1 Datamodel

Kernentiteiten:

- **PAGE** — `id`, `slug`, `title`, `type` (content/systeem), `visibility` (publiek/leden/beperkt), `parent_id` (hiërarchie voor navigatie), `template_id`, `published_version_id`.
- **PAGE_VERSION** — `id`, `page_id`, `version_no`, `status` (concept/in_review/gepubliceerd/gearchiveerd), `base_version_id` (de versie waarvan deze vertakt), `created_by`, `created_at`.
- **BAND** — `id`, `page_version_id`, `zone` (uit het sjabloon), `layout` (1/2/3 kolommen), `sort_order`.
- **BLOCK** — `id`, `band_id`, `column_index`, `sort_order`, `type` (content/module), `module_id` (bij een moduleblok), `content` (JSON: rijke tekst/instellingen).
- **MODULE** — `id`, `key`, `name`: de registratie van beschikbare modules.
- **MEDIA_ASSET** — `id`, `path`, `type`, `alt`: de gedeelde mediabibliotheek.
- **NAV_ITEM** — `id`, `menu_id`, `page_id`, `parent_id`, `sort_order`, `visible`.
- **TEMPLATE** — `id`, `name`, `zones` (JSON): legt de zones van de hybride opzet vast.

Relaties: een pagina heeft veel versies; een versie bevat banden; een band bevat blokken; een moduleblok verwijst naar een module; blokken verwijzen naar media-assets; een pagina gebruikt een sjabloon, heeft subpagina's (`parent_id`) en verschijnt in navigatie-items.

**Versiebeheer en samenwerking.** Banden en blokken hangen onder een *versie*, niet onder de pagina, zodat elke versie een complete momentopname is. Bewerken maakt een conceptversie aan die vertakt vanaf `base_version_id`. Publiceren loopt via de goedkeuringsmotor en zet `published_version_id` om. Er wordt niet vergrendeld: bij gelijktijdige bewerking ontstaan conflicterende versies, die per blok tegen de basisversie worden vergeleken — niet-overlappende wijzigingen gaan automatisch samen, alleen botsende blokken vragen handmatige resolutie. Oude versies blijven bewaard (vergelijken en terugdraaien).

### 16.2 Paginabewerker

De bewerker is in-context: de redacteur bewerkt de pagina zoals die eruitziet.

- **Bovenbalk**: paginatitel, versiestatus (bijv. "Concept · v3"), en knoppen voor voorvertoning, opslaan, indienen (start de goedkeuringsmotor) en versiegeschiedenis.
- **Banden**: elke band heeft een contextuele knoppenbalk (verplaatsen, instellingen, dupliceren, verwijderen); een geselecteerde band is gemarkeerd.
- **Invoegpunten**: tussen banden kan een nieuwe band worden toegevoegd met directe keuze van de kolomindeling.
- **Blokken**: per blok knoppen (bewerken, verwijderen); moduleblokken tonen hun instellingen ter plekke; een lege plek nodigt uit een content- of moduleblok toe te voegen.

### 16.3 Blok- en moduletypecatalogus

Gedeelde blokinstellingen: **zichtbaarheid** (iedereen / alleen leden / specifieke rol) en witruimte/marges. Moduleblokken hebben daarnaast een optionele titel.

**Contentblokken**

| Blok | Doel | v1 |
|---|---|---|
| Tekst | Rijke tekst met koppen, lijsten, links | ✓ |
| Kop | Sectietitel (H1–H3) | ✓ |
| Afbeelding | Eén beeld uit de bibliotheek | ✓ |
| Knop (CTA) | Oproep tot actie (pagina/URL/module) | ✓ |
| Kaart/tegel | Beeld + titel + tekst + link | ✓ |
| Icoon + tekst | Kort kenmerk of uitleg | ✓ |
| Gallerij | Meerdere beelden (raster/carrousel) | ✓ |
| Accordeon | In-/uitklapbare items (FAQ) | ✓ |
| Citaat | Uitspraak met bron | ✓ |
| Video/embed | Ingesloten video of insluitcode | ✓ |
| Bestand/download | Downloadbaar document | ✓ |
| Scheiding/witruimte | Visuele rust | ✓ |
| Tabel | Eenvoudige gegevenstabel | later |

**Modules**

| Module | Doel | Laag | v1 |
|---|---|---|---|
| Lid worden | Inschrijven als lid | Publiek | ✓ |
| Activiteiten | Lijst/agenda van activiteiten | Publiek + besloten | ✓ |
| Reserveren | Objecten reserveren | Besloten | ✓ |
| Leden zoeken | Contactgegevens (respecteert veld-zichtbaarheid) | Besloten | ✓ |
| Schade melden | Schade aan objecten melden | Besloten | ✓ |
| Nieuws/berichten | Lijst van berichten | Publiek + besloten | ✓ |
| Documenten | Downloads uit documentbeheer | Besloten | ✓ |
| Contactformulier | Algemeen contact (logt in communicatielogboek) | Publiek | later |
| Nieuwsbrief-aanmelding | Aanmelden mailing | Publiek | later |
| Vrijwilligerstaken | Aanmelden voor taken | Besloten | later |

Nieuwe modules zijn later toe te voegen zonder het paginamodel te wijzigen (registratie via `MODULE`).

## 17. Module Activiteiten — detailuitwerking

### 17.1 Front-end-weergaven

- **Overzicht/agenda**: lijst- én kalenderweergave, met filters (open/besloten, periode, categorie). Een serie kan worden getoond als één item met de komende voorkomens, of als losse voorkomens.
- **Detailview** (één activiteit of voorkomen): omschrijving, datum/tijd, locatie, capaciteit en vrije plekken, eventuele kosten, inschrijfknop en deelnemers (met respect voor zichtbaarheid). Binnen een serie: navigeren tussen voorkomens en kiezen tussen inschrijven voor de hele serie of voor losse voorkomens.
- **Inschrijven**: capaciteit en wachtlijst, bevestiging, eventueel betaling (Mollie), afmelden; statuten-signalen (zoals leeftijd) worden getoond.
- **Voorstellen**: leden stellen een toevoeging of wijziging voor → goedkeuringsmotor.

### 17.2 Beheeraspecten (3.02)

- CRUD plus levenscyclus en goed-/afkeuren.
- **Series en voorkomens**: een serie met herhaalpatroon genereert voorkomens; een voorkomen kan afwijken (uitzondering).
- **Inschrijvingenbeheer**: deelnemerslijsten per voorkomen en/of serie, capaciteit, wachtlijst, aanwezigheid en afmeldingen.
- **Koppelingen**: categorieën, locaties/objecten (mogelijk gekoppeld aan Reserveren) en kosten (Facturatie/Betalingen).

### 17.3 Datamodel

- **ACTIVITY_SERIES** — `id`, `category_id`, `title`, `recurrence_rule`, `enrollment_level` (serie/voorkomen/beide), `default_capacity`, `status`, `visibility`, `split_from` (bij afsplitsing via "dit en volgende").
- **ACTIVITY** (voorkomen of losse activiteit) — `id`, `series_id` (optioneel), `title`, `starts_at`, `ends_at`, `location`, `capacity`, `status`, `is_exception`, `product_id` (activiteitsbijdrage, optioneel).
- **ACTIVITY_OPTION** — `id`, `activity_id` of `series_id`, `name` (bijvoorbeeld maaltijd, opleidingsniveau, aantal deelnemers), `type` (keuze/aantal), `price_effect`.
- **ENROLLMENT** — `id`, `person_id` (begunstigde), `requested_by` (wie inschrijft), `series_id`, `activity_id`, `level` (serie/voorkomen), `status` (aangemeld/wachtlijst/afgemeld/aanwezig), `enrolled_at`.
- **ENROLLMENT_OPTION** — `id`, `enrollment_id`, `activity_option_id`, `value` (gekozen optie of aantal).
- **ACTIVITY_CATEGORY** — `id`, `name`.
- **PERSON** — verwijst naar het rechten- en rollenmodel (hoofdstuk 6).

### 17.4 Vastgestelde gedragsregels

- **Inschrijfniveau** is een instelling op de serie (`enrollment_level`). Een serie-inschrijving omvat alle voorkomens, met de mogelijkheid zich per voorkomen af te melden. Bij "beide" kiest het lid één ingang. Capaciteit wordt altijd per voorkomen bewaakt. Een losse activiteit kent inschrijving op dat ene voorkomen.
- **Wachtlijst** staat altijd aan: zit een voorkomen vol, dan komen nieuwe inschrijvingen op status "wachtlijst" en schuiven ze door zodra een plek vrijkomt.
- **Serie-bewerking** kent drie reikwijdtes: alleen dit voorkomen, dit en volgende, of de hele serie. Bij "dit en volgende" splitst de serie op dat punt: voorkomens ervóór blijven bij het origineel, vanaf het gekozen voorkomen ontstaat een vervolgserie (gekoppeld via `split_from`). Reeds aangepaste voorkomens (`is_exception`) blijven beschermd tegen serie-brede wijzigingen.
- **Activiteitsbijdrage als eigenschap van de activiteit**: de prijs hangt af van het lidmaatschapstype én van aanvullende keuzes binnen de activiteit (`ACTIVITY_OPTION`), bijvoorbeeld een maaltijd, het opleidingsniveau dat het tarief bepaalt, of bij een extern team het aantal deelnemers.
- **Inschrijving genereert een post**: een inschrijving leidt, op basis van het lidmaatschapstype en de gekozen opties, tot een `CHARGE` (zie §23) die op een factuur wordt gebundeld.
- **Inschrijven namens**: een inschrijving legt expliciet de begunstigde vast (`person_id`); de inschrijver kiest actief of het voor zichzelf is of voor iemand voor wie hij gemachtigd is (zie §19, "handelen namens").
- **Wachtlijst-notificatie**: komt een plek vrij, dan wordt de eerste op de wachtlijst per e-mail genotificeerd (Mailing).

---

*Document groeit mee; volgende uit te werken module: Reserveren.*

## 18. Module Reserveren — detailuitwerking

### 18.1 Front-end-weergaven (besloten laag)

- **Overzicht**: een beschikbaarheidskalender per object of objectcategorie, met filters (categorie, locatie, periode).
- **Detail**: een object met zijn reserveringen, of een enkele reservering. Acties: reserveren, wijzigen, intrekken.
- **Reserveren**: een tijdvak kiezen plus óf een specifiek object, óf een beschikbaar object van een categorie (het systeem wijst er dan een toe); optioneel voor een ploeg. Signalen worden al tijdens het invullen getoond. Vervolgens doorlopen de controles (zie gedragsregels): binnen beleid direct bevestigd, anders via de goedkeuringsmotor.
- **Voor een ander**: een reservering kan voor een andere persoon worden aangevraagd; zo'n aanvraag gaat altijd via goedkeuring.

### 18.2 Beheeraspecten (3.10)

- Objecten en objectcategorieën beheren (inclusief de vlag of een categorie een vaartuig betreft waarvoor botengebruik-recht nodig is).
- Reserveringsregels onderhouden (per categorie, additief).
- Reserveringen inzien en beheren; aanvragen boven de drempel en aanvragen voor een ander goed-/afkeuren.

### 18.3 Datamodel

- **OBJECT_CATEGORY** — `id`, `name`, `parent_id` (hiërarchie), `requires_boat_right`.
- **RESERVABLE_OBJECT** — `id`, `name`, `category_id`, `status` (beschikbaar/buiten gebruik), `location`, `attributes`.
- **RESERVATION_RULE** — `id`, `name`, `category_id` (geldt inclusief subcategorieën), `constraint_type` (gelijktijdig/per_dag/duur), `limit_value`, `per_person`.
- **RESERVATION** — `id`, `object_id` (specifiek object, of leeg bij een beschikbaar object van een categorie), `category_id` (bij "beschikbaar van categorie"), `person_id` (voor wie de reservering is), `requested_by` (wie de aanvraag deed), `crew_id` (optioneel, een ploeg), `starts_at`, `ends_at`, `status` (bevestigd/in_review/afgewezen/geannuleerd), `activity_id` (optionele koppeling).
- **CREW** (ploeg) — `id`, `name`.
- **CREW_MEMBER** — `id`, `crew_id`, `person_id`.

### 18.4 Vastgestelde gedragsregels

- **Categoriehiërarchie**: een regel op een categorie geldt ook voor onderliggende categorieën. Regels zijn additief — alle toepasselijke regels worden meegewogen. Een zeilboot telt zo mee voor zowel de zeilboot- als de bootregels; een portofoon valt buiten beide.
- **Twee harde invarianten**, altijd afgedwongen: botengebruik-recht (een vaartuig kan niet worden gereserveerd als de lidmaatschapsvorm dat niet toestaat) en geen dubbelboeking (geen overlappende bevestigde reserveringen op hetzelfde object).
- **Drempels blokkeren niet**: een aanvraag die een drempel (gelijktijdig, per dag, duur) overschrijdt, mag altijd worden gedaan en gaat dan via de goedkeuringsmotor.
- **Aanvraag voor een ander** (`requested_by` ≠ `person_id`) gaat via goedkeuring, behalve wanneer de aanvrager gemachtigd is voor de begunstigde (bijvoorbeeld een ouder/verzorger voor het eigen kind, zie §19) — dan niet.
- **Objectkeuze**: er wordt óf een specifiek object gereserveerd, óf een beschikbaar object van een categorie waarbij het systeem er een toewijst.
- **Ploeg**: een reservering kan voor een ploeg (`CREW`) zijn; de ploegleden zijn de deelnemers. Dit is iets anders dan "voor een ander" en vraagt op zichzelf geen extra goedkeuring.
- **Signalen tijdens invoer**: drempel- en statutensignalen (zoals "je tweede boot vandaag" of een leeftijdsgrens) worden al tijdens het invullen getoond, niet pas als afwijzing achteraf.
- **Handelen namens**: bij het reserveren kiest de gebruiker actief of het voor zichzelf is of voor iemand voor wie hij gemachtigd is (zie §19).
- Reserveringen zijn **voorlopig gratis**; koppeling met Facturatie en een kostenveld volgen wanneer dat speelt.
- **Terugkerende reserveringen** zijn een latere uitbreiding.

### 18.5 Koppelingen

Activiteiten (een voorkomen kan objecten vasthouden via `activity_id`), Schade melden (op objecten), Rechten (botengebruik), de goedkeuringsmotor (drempels en aanvragen voor een ander), en later Facturatie.

---

*Document groeit mee; volgende uit te werken module: Lidmaatschap (Lid worden en Ledenbeheer).*

## 19. Module Lidmaatschap (Lid worden en Ledenbeheer) — detailuitwerking

### 19.1 Front-end-weergaven

- **Lid worden** (publiek): kies een lidmaatschapsvorm (alleen de relevante getoond), vul persoonsgegevens in, ga akkoord met statuten/HR/privacy en dien in. Voor een minderjarige vraagt het formulier gegevens, contactgegevens en toestemming van een ouder/verzorger; die ouder/verzorger krijgt via deze inschrijving een account (ook als hij zelf geen lid is), zodat communicatie naar hem kan. Voor een studentvorm vraagt het formulier de bewijsmiddelen (OV-kaart, studentenpas). Inschrijving loopt via de goedkeuringsmotor (administratie keurt goed).
- **Mijn lidmaatschap** (besloten): eigen gegevens en lidmaatschapsvorm/rechten inzien, gegevens wijzigen, zichtbaarheid per gegeven instellen, bewijsmiddelen uploaden en opzeggen.

### 19.2 Beheeraspecten (3.08)

- Ledenadministratie: zoeken, detail per lid, toevoegen/wijzigen, lidmaatschap toekennen/wijzigen/beëindigen.
- Inschrijvingen en wijzigingsvoorstellen goed-/afkeuren; bewijsmiddelen beoordelen.
- Lidmaatschapsvormen en hun ledenrechten beheren.
- Signalen en het studentbewijs-traject opvolgen.

### 19.3 Datamodel

- **PERSON** — `id`, naamvelden, `geboortedatum`, contact (e-mail/telefoon/adres), `household_id`, `account_id` (optionele login), `ecaptain_id` (herkomst), `status`.
- **HOUSEHOLD** — `id`, `naam`, adresvelden.
- **MEMBERSHIP** — `id`, `person_id`, `membership_type_id`, `start_date`, `end_date`, `status` (aanvraag/actief/opgezegd/vervallen/geweigerd), `derives_from_membership_id` (voor partner-/gezinslid), `billing_person_id` (de betaler; bij een minderjarige de ouder/verzorger).
- **MEMBERSHIP_TYPE** — `id`, `name`, `product_id` (verwijzing naar het contributie-artikel, zie §23), en de ledenrechten-config: `min_age`, `max_age`, `allows_boat_use`, `allows_instruction`, `intro_per_year`, `allows_competition`, `seasonal_only`, `auto_expiry`, `requires_proof`, `is_partner_type`.
- **PROOF_DOCUMENT** — `id`, `membership_id`, `type`, `media_asset_id`, `status`, `valid_until`, `reviewed_by`.
- **GUARDIANSHIP** (voogdij/machtiging) — `id`, `minor_person_id`, `guardian_person_id` (een meerderjarige `PERSON`), `is_payer` (betaler), `may_act_on_behalf` (gemachtigde), `consent_at`.
- **PERSON_FIELD_VISIBILITY** — `id`, `person_id`, `field_key`, `visible_to_members` (zichtbaar voor andere leden, ja/nee).

### 19.4 Vastgestelde gedragsregels

- **Veld-zichtbaarheid**: per gegeven kiest een lid of het zichtbaar is voor andere leden (ja/nee); de keuze geldt voor alle leden. Geconsumeerd door Leden zoeken, dat simpelweg de vlag controleert.
- **Eigen wijzigingen**: velden hebben een configureerbare markering "gevoelig" (bijv. naam, geboortedatum, lidmaatschapsvorm). Gevoelige wijzigingen lopen via de goedkeuringsmotor; overige gaan direct door, met logging.
- **Partner-/gezinsleden via huishouden**: personen delen een `HOUSEHOLD` (adres); een partner-/gezinslidmaatschap leidt zijn rechten af van het hoofdlidmaatschap (A of B) in hetzelfde huishouden (`derives_from_membership_id`).
- **Statusovergangen** (aspirant → jeugd, studentverlenging vóór 1 november of vervallen, leeftijdsgrenzen) zijn signalerend; het studentbewijs-traject escaleert naar een mens (geen automatische opzegging).
- **Minderjarigen**: een minderjarig lid moet minstens één meerderjarige ouder/verzorger hebben (`GUARDIANSHIP`). Die geeft toestemming (`consent_at`, vereist vóór goedkeuring), is doorgaans de betaler (`is_payer`; contributie en facturen gaan naar deze persoon, zie `MEMBERSHIP.billing_person_id`) en mag als gemachtigde namens het kind handelen (`may_act_on_behalf`: gegevens beheren, inschrijven, reserveren). Een gemachtigde die voor het eigen kind handelt, is een erkende bevoegdheid en valt niet onder de extra goedkeuring voor een "aanvraag voor een ander" (nuanceert §18.4).
- **Ouder/verzorger als persoon**: een ouder/verzorger is zelf een `PERSON` en kan een eigen lidmaatschap hebben; de voogd- en betalersrol staan daar volledig los van. Eén ouder/verzorger kan voor meerdere minderjarige leden voogd en betaler zijn (meerdere `GUARDIANSHIP`-relaties, met `billing_person_id` op elk kinderlidmaatschap).
- **Handelen namens**: op plekken met een gevolg (zoals een reservering of een inschrijving) kiest de gebruiker actief of hij voor zichzelf handelt of namens iemand voor wie hij gemachtigd is (een ouder/verzorger voor het kind, of een functionaris namens een lid). Die keuze wordt bij de handeling vastgelegd.
- **Opzeggen**: een lid kan zelf via de website opzeggen. Opzegtermijn, opzegmoment en eventuele restitutie staan op de to-dolijst (§14).

### 19.5 Koppelingen

Rechten (ledenrechten via `MEMBERSHIP_TYPE`; functierollen staan daar los van), de goedkeuringsmotor (inschrijving en gevoelige wijzigingen), Leden zoeken (veld-zichtbaarheid), Facturatie/Contributie (later), Communicatielogboek, Documentbeheer (bewijsmiddelen) en migratie uit e-Captain (`ecaptain_id`).

---

*Document groeit mee; volgende uit te werken onderdeel: de goedkeuringsmotor (detailuitwerking).*

## 20. Goedkeuringsmotor — detailuitwerking

Cross-cutting mechanisme waar de modules met voorstellen op leunen (content, activiteiten, ledenwijzigingen, reserveringen).

### 20.1 Entiteiten

- **PROPOSAL** (voorstel) — `id`, `subject_type` (paginaversie/activiteit/ledenwijziging/reservering/…), `subject_id`, `change_type` (aanmaken/wijzigen/verwijderen), `payload` (de voorgestelde wijziging of verwijzing naar de conceptversie), `proposed_by`, `status`, `policy_id`, `created_at`.
- **REVIEW_POLICY** (beleid) — `id`, `subject_type`, `condition` (voorwaarde: binnen/boven drempel, gevoelig veld, aanvraag voor een ander…), `auto_apply`, `required_steps`, `bypass_permission`, `resubmit_behavior` (opnieuw vanaf stap 1 / verder bij huidige stap), `reminder_after_days`, `escalation_after_days`, `escalation_group_id`.
- **REVIEW_STEP** — `id`, `proposal_id`, `sequence`, toegewezene (rol/groep/persoon), `status`, `decided_by`, `decided_at`, `reason`, `due_at`, `reminder_sent_at`, `escalated_at`.

### 20.2 Levenscyclus van een voorstel

concept → ingediend → in_review → goedgekeurd → toegepast, met de zijpaden afgekeurd (met reden), teruggestuurd (terug naar indiener) en ingetrokken. Bypass-route: ingediend → direct toegepast, gelogd.

### 20.3 Routering bij indienen

1. Heeft de indiener de bypass-permissie voor dit `subject_type`? → direct toepassen, zichtbaar in de log.
2. Anders evalueert de motor het beleid. Binnen beleid (`auto_apply`) → direct toepassen. Daarbuiten → reviewstappen aanmaken.
3. Stappen zijn sequentieel (tweede, dan eventueel derde view). Een stap kan naar een rol of groep gaan; bij een groep volstaat één goedkeuring. Functiescheiding: de beoordelaar is nooit de indiener.
4. Bij volledige goedkeuring effectueert een per-`subject_type` handler de wijziging (paginaversie publiceren, ledenveld schrijven, reservering bevestigen…), met een nieuwe versie waar van toepassing en een vermelding in de audit trail.

### 20.4 Vastgestelde gedragsregels

- **Bypass met logging achteraf**: vertrouwde rollen voeren direct door; de wijziging is achteraf zichtbaar in de log.
- **Functiescheiding**: de beoordelaar is nooit de indiener.
- **Groepsstap**: bij toewijzing aan een groep volstaat één goedkeuring.
- **Sequentiële stappen**: tweede view, daarna eventueel derde view.
- **Heropenen na terugsturen**: instelbaar per beleid (`resubmit_behavior`) — opnieuw vanaf stap 1 of verder bij de huidige stap.
- **Termijnbewaking**: per beleid een herinnerings- en escalatietermijn; bij overschrijding eerst een herinnering, daarna escalatie naar de ingestelde groep. Vereist een planner met wachtrij (Laravel scheduler + queue).
- **Signalen** (statuten/leeftijd) worden bij het voorstel aan de beoordelaar getoond.
- **Apply-time hervalidatie**: bij het toepassen hervalideert de motor tegen de actuele staat. Is de onderliggende data intussen gewijzigd, dan wordt een conflict gedetecteerd in plaats van nieuwere gegevens te overschrijven (analoog aan het conflictmodel bij content-versies).
- **Beoordelaarsdashboard**: beoordelaars hebben een overzicht van openstaande voorstellen met filters en, waar zinvol, bulkafhandeling, zodat de goedkeuringslast voor vrijwilligers hanteerbaar blijft.

### 20.5 Koppelingen

Reviewinstellingen (3.14) configureert het beleid per `subject_type`; Audit trail (3.13) logt alle handelingen, inclusief bypass en overschrijvingen; Mailing (3.09) verstuurt notificaties en herinneringen; Rechten levert de bypass- en beoordelaarspermissies.

---

## 21. Module Leden zoeken — detailuitwerking

### 21.1 Front-end-weergaven (besloten laag)

- **Zoeken**: leden opzoeken (standaard op naam), met een resultatenlijst die per lid alleen de zichtbare velden toont.
- **Detail**: een profielkaart met uitsluitend de zichtbare velden. Alleen-lezen.

### 21.2 Datamodel

- **FIELD_DEFINITION** — `id`, `field_key`, `label`, `is_hideable` (mag een lid verbergen), `is_searchable` (doorzoekbaar), `is_sensitive` (wijziging via goedkeuring), `default_visible`. Consolideert de drie veldgedragingen (verbergbaar, doorzoekbaar, gevoelig) en wordt ook door Lidmaatschap gebruikt.
- Consumeert verder `PERSON`, `MEMBERSHIP` en `PERSON_FIELD_VISIBILITY`.

### 21.3 Vastgestelde gedragsregels

- **Zichtbaarheid**: respecteert per veld `visible_to_members`; welke velden verbergbaar of doorzoekbaar zijn, staat in `FIELD_DEFINITION`. Standaard wordt op naam gezocht; doorzoekbare velden zijn uitbreidbaar.
- **Toegang**: geregeld door het recht "andere leden opzoeken", een permissie. Dit recht zit standaard in de automatisch toegekende "Lid"-rol en in de meeste functionarisrollen, en kan daarnaast individueel als uitzondering aan een persoon worden toegekend (zie §26). Het is dus standaard beschikbaar voor leden, maar blijft per rol of persoon instelbaar.
- **Onderscheid met Ledenbeheer**: de ledenadministratie ziet via een eigen permissie álle gegevens, los van de vlaggen; Leden zoeken respecteert ze juist.
- **Minderjarigen** verschijnen als andere leden, gestuurd door de vlaggen. Voor een minderjarige worden die vlaggen beheerd door de ouder/verzorger (gemachtigde), met een privacybewuste standaard: contactgegevens staan standaard verborgen (opt-in om te tonen).

### 21.4 Koppelingen

Lidmaatschap (`PERSON`, zichtbaarheidsvlaggen; de voogd beheert de vlaggen van een minderjarige), Rechten (toegang via het recht "andere leden opzoeken", als rolpermissie of ledenprivilege) en AVG (de ledengids is een verwerking; de vlaggen vormen de controle).

---

## 22. Module Schade melden — detailuitwerking

### 22.1 Front-end-weergaven (besloten laag)

- **Melden**: een object kiezen (of starten vanuit een eigen reservering), de schade omschrijven, ernst aangeven, eventueel "niet bruikbaar" aanvinken en foto's toevoegen. Indienen.
- **Mijn meldingen**: de status van eigen meldingen volgen.

### 22.2 Beheeraspecten

- Meldingen afhandelen: statusverloop (gemeld → in behandeling → opgelost/afgewezen), toewijzen, notities/oplossing vastleggen, en zo nodig het object op "buiten gebruik" zetten.
- Loopt **niet** via de goedkeuringsmotor; een melding heeft een eigen, eenvoudige workflow.

### 22.3 Datamodel

- **DAMAGE_REPORT** — `id`, `object_id`, `reported_by`, `reservation_id` (optioneel), `description`, `severity`, `reporter_marked_unusable` (signaal van de melder), `status` (gemeld/in_behandeling/opgelost/afgewezen), `reported_at`, `assigned_to`, `resolution`, `resolved_at`.
- **DAMAGE_REPORT_MEDIA** — `id`, `damage_report_id`, `media_asset_id` (één of meer foto's).
- **CATEGORY_RESPONSIBLE** — `id`, `object_category_id`, `person_id` (één of meer verantwoordelijken per categorie).

### 22.4 Vastgestelde gedragsregels

- **Onmiddellijk buiten gebruik, omkeerbaar**: vinkt de melder "niet bruikbaar" aan (`reporter_marked_unusable`), dan wordt het object meteen op "buiten gebruik" gezet (`RESERVABLE_OBJECT.status`) en is het direct onreserveerbaar — een voorzorg. Een schadebehandelaar kan dit later weer ongedaan maken (terug naar beschikbaar). Komende reserveringen op het object worden gesignaleerd voor opvolging.
- **Toewijzing per objectcategorie**: een melding wordt gerouteerd naar de verantwoordelijke(n) van de categorie van het object (`CATEGORY_RESPONSIBLE`), met overerving naar de bovenliggende categorie als er geen eigen verantwoordelijke is — consistent met de categoriehiërarchie van Reserveren. De betrokkenen worden geattendeerd via Mailing.
- **Geen goedkeuringsmotor**: een eigen statusworkflow volstaat.

### 22.5 Koppelingen

Reserveren (object en categorie, optioneel vanuit een reservering; buiten gebruik → onreserveerbaar), Media/Documentbeheer (foto's), Rechten (afhandelen is een permissie), Mailing (notificaties) en optioneel het Communicatielogboek.

---

## 23. Financiën — contributie, facturatie en betalingen — detailuitwerking

Een eigen, lichte boekhouding op de Laravel-stack (geen extern pakket), met export naar een externe boekhouder. Omvat de beheermodules Factureren (3.06) en Boekhouding (3.03). De financiële kant is **product-gebaseerd**: lidmaatschap (contributie), de eigen bijdrage voor een activiteit en een sponsoradvertentie zijn elk een product/artikel met een prijs. Een lidmaatschap heeft daarmee twee kanten: een financiële component (het product, dat gefactureerd en betaald wordt) en de status met privileges (hoofdstuk 19, daar los van).

### 23.1 Front-end-weergaven

- **Lid/betaler** (besloten): eigen facturen inzien en betalen, betaalwijze en SEPA-machtiging beheren.
- **Bij inschrijving** (Lid worden): de contributie en de betaalwijze worden gepresenteerd, met de SEPA-machtiging als voorkeur.

### 23.2 Beheeraspecten

- Contributietarieven per lidmaatschapsvorm en verenigingsjaar onderhouden.
- Contributie-run: per betaler een geconsolideerde factuur genereren.
- Facturen beheren (versturen, herinneren, crediteren), betalingen volgen, en journaalposten met export naar de boekhouder.

### 23.3 Datamodel

- **PRODUCT** (artikel) — `id`, `name`, `type` (contributie/activiteitsbijdrage/advertentie/overig), `ledger_account_id`, `is_recurring`, `recurrence`.
- **PRODUCT_PRICE** — `id`, `product_id`, `valid_from`, `amount` (prijshistorie, bijvoorbeeld per verenigingsjaar).
- **CHARGE** (te factureren post) — `id`, `product_id`, `debtor_person_id`, `subject_type`, `subject_id` (bv. lidmaatschap, activiteitsinschrijving, sponsoring), `amount`, `period`, `status` (open/gefactureerd/betaald/gecrediteerd/vervallen), `invoice_id` (leeg tot gefactureerd), `due_at`, `created_at`.
- **INVOICE** — `id`, `debtor_person_id`, `status` (concept/verzonden/betaald/deels_betaald/vervallen/gecrediteerd), `issued_at`, `due_at`, `total`.
- **PAYMENT** — `id`, `invoice_id`, `method` (sepa/ideal), `amount`, `status`, `mollie_payment_id`, `paid_at`.
- **SEPA_MANDATE** — `id`, `person_id`, `iban`, `status` (actief/ingetrokken), `signed_at`, `mandate_ref`.
- **LEDGER_ACCOUNT** — `id`, `code`, `name`, `type`.
- **JOURNAL_ENTRY** — `id`, `date`, `description`, `reference`.
- **JOURNAL_LINE** — `id`, `journal_entry_id`, `account_id`, `debit`, `credit`.

### 23.4 Vastgestelde gedragsregels

- **Product-gebaseerd**: contributie, activiteitsbijdragen en advertenties zijn producten (`PRODUCT`) met een prijs (`PRODUCT_PRICE`). Een lidmaatschapsvorm verwijst naar zijn contributie-product (`MEMBERSHIP_TYPE.product_id`).
- **Posten los van facturen**: te factureren bedragen ontstaan als losse posten (`CHARGE`), soms op een terugkerend schema (contributie per verenigingsjaar, een advertentie per periode). Posten lopen dus niet allemaal gelijktijdig.
- **Facturen bundelen posten**: een factuur (`INVOICE`) bundelt de op dat moment openstaande posten van één betaler (`debtor_person_id`) — geconsolideerd, inclusief de posten voor minderjarige kinderen. Niet elke post hoeft tegelijk te worden gefactureerd.
- **Termijnbetaling**: een post kan in termijnen worden gefactureerd (per maand/kwartaal/halfjaar), per post instelbaar met uitzonderingen — bijvoorbeeld instroom in september in drie maandtermijnen.
- **Contributie per verenigingsjaar**, met configureerbare startdatum; pro rata bij instroom gedurende het jaar (voorstel: naar rato vanaf de instroommaand) — te bevestigen.
- **Betaling via Mollie**: SEPA-incasso (met machtiging, `SEPA_MANDATE`) is de voorkeur en wordt bij inschrijving aangeboden, maar niet verplicht — zonder machtiging betaalt de betaler per factuur via iDEAL. Alternatief voor terugkerende incasso: zelf een SEPA-batchbestand voor de eigen bank genereren in plaats van Mollie (zie §23.6).
- **Herinnerings-/aanmaningstraject** via Mailing en de scheduler (vervaldatum → herinnering → aanmaning).
- **Lichte dubbele boekhouding**: posten, facturen en betalingen genereren journaalposten (`JOURNAL_ENTRY`/`JOURNAL_LINE` op `LEDGER_ACCOUNT`); export naar een externe boekhouder.

### 23.5 Koppelingen

Lidmaatschap (`MEMBERSHIP_TYPE` → contributie-product, betaler, contributie bij inschrijving), Activiteiten en Reserveren (bijdragen en kosten als producten), Betalingen/Mollie (iDEAL en SEPA-mandaten), Mailing (facturen en herinneringen) en Audit (financiële mutaties).

### 23.6 Mogelijke extra financiële functies

Kandidaten op basis van wat vergelijkbare pakketten (zoals e-Captain) bieden, met een voorstel voor v1 versus later:

- **Debiteurenbeheer**: overzicht van openstaande posten plus het aanmaningstraject. (v1)
- **Kortingen en toeslagen**: bijvoorbeeld gezins-/meervoudskorting of vroegboek, als prijsregels op producten. (v1)
- **Creditnota's/restituties**: basis. (v1)
- **Crediteuren/uitgaven**: inkoopfacturen, leveranciers en declaraties (vrijwilligersvergoedingen). (later)
- **Rapportages**: balans, winst-en-verliesrekening, kwartaal-/jaaroverzicht (openstaande posten al in v1). (later)
- **Begroting versus realisatie**. (later)
- **Kas/kantine**: contante verkopen, dagstaten, eventueel een kassa met pin. (later)
- **Btw-ondersteuning**: de meeste sportverenigingen zijn grotendeels vrijgesteld, maar kantine/sponsoring kan btw-plichtig zijn — btw per product en aangifte-hulp. (later, indien nodig)
- **SEPA-batchbestand via de eigen bank** (pain.008) als goedkoper alternatief voor terugkerende incasso via Mollie; Mollie blijft sterk voor losse iDEAL-betalingen. (afweging in de betaal-fase)
- **Bankafschriften inlezen en afletteren** (reconciliatie). (later)

Deze lijst is bewust ruim; in een vervolgsessie bepalen we welke daadwerkelijk in scope komen.

---

## 24. Module Mailing en notificaties — detailuitwerking

### 24.1 Soorten communicatie

- **Transactioneel** (systeemmails): goedkeuringsnotificaties en escalaties, facturen en aanmaningen, het studentbewijs-traject. Altijd verstuurd, geen afmeldkeuze.
- **Redactioneel** (nieuwsbrieven/bulk): naar segmenten van leden, met opt-in en een afmeldlink (AVG).

### 24.2 Front-end-weergaven

- **Lid** (besloten): communicatievoorkeuren beheren (opt-in/afmelden voor redactionele categorieën); elke redactionele mail bevat een afmeldlink.
- **Notificatiecentrum** (besloten): een in-app overzicht van meldingen (goedkeuringen, een vrijgekomen wachtlijstplek, herinneringen), naast de e-mailnotificaties.

### 24.3 Beheeraspecten

- Sjablonen beheren, een mailing opstellen, doelgroep kiezen, plannen en verzenden; verzendstatus volgen. Geplande en automatische mails lopen via de scheduler en queue.

### 24.4 Datamodel

- **MESSAGE_TEMPLATE** — `id`, `name`, `subject`, `body`, `type` (transactioneel/redactioneel).
- **MAILING** — `id`, `template_id`, `segment` (doelgroepdefinitie), `status`, `scheduled_at`, `sent_at`.
- **MAILING_RECIPIENT** — `id`, `mailing_id`, `person_id`, `status` (verzonden/geopend/gebounced/afgemeld).
- **COMMUNICATION_PREFERENCE** — `id`, `person_id`, `category`, `opted_in` (voor redactionele categorieën).
- **NOTIFICATION** — `id`, `person_id`, `type`, `subject`, `body`, `link`, `read_at`, `created_at` (in-app meldingen).

### 24.5 Vastgestelde gedragsregels

- **Verzending via een EU-gehoste transactionele e-maildienst** (afleverbaarheid, SPF/DKIM/DMARC, bounce- en afmeldverwerking), aangeroepen via API of SMTP. AVG: EU-dienst of verwerkersovereenkomst.
- **Transactioneel altijd**; redactioneel alleen met opt-in en met afmeldlink.
- **Doelgroepen op beide manieren**: vaste selecties op rollen en lidmaatschapsvormen, plus vrije filters/segmenten.
- **Verzonden communicatie wordt gelogd** in het Communicatielogboek.
- **Bounces en afmeldingen** worden verwerkt: een afmelding zet `COMMUNICATION_PREFERENCE.opted_in` uit; harde bounces markeren het adres.
- **In-app naast e-mail**: belangrijke meldingen (goedkeuringen, wachtlijst-plek, herinneringen) verschijnen ook in het in-app notificatiecentrum (`NOTIFICATION`), niet alleen per e-mail.

### 24.6 Koppelingen

Goedkeuringsmotor (notificaties en escalaties), Financiën (facturen en herinneringen), Lidmaatschap (studentbewijs, voorkeuren), Communicatielogboek (loggen) en de scheduler/queue.

---

## 25. Module Migratie en import — detailuitwerking

Beheer-gericht onderdeel (3.07); geen publieke of besloten schermen.

### 25.1 Beheeraspecten

- Importdefinitie: bron (e-Captain/WordPress) + entiteitstype + upload van het tussenformaat (CSV/Excel).
- Mapping: bronkolommen aan doelvelden koppelen, als herbruikbaar profiel.
- Dry-run: validatie en rapportage vóór de definitieve import.
- Uitvoeren: in batches via de queue; records gemarkeerd als bestaand, met behoud van herkomst-ID.
- Historie: overzicht van importruns, met terugdraaien per run.

### 25.2 Datamodel

- **IMPORT_RUN** — `id`, `source`, `entity_type`, `file_ref`, `status` (concept/dry_run/uitgevoerd/teruggedraaid), `summary`, `created_by`, `created_at`.
- **IMPORT_MAPPING** — `id`, `name`, `source`, `entity_type`, `mapping` (JSON: bronkolom → doelveld).
- **IMPORT_RECORD** — `id`, `import_run_id`, `source_id` (herkomst-ID), `target_type`, `target_id`, `status` (geïmporteerd/overgeslagen/fout), `messages`.
- Herkomst-ID's op de doelentiteiten (`ecaptain_id`, `wordpress_id`) voor idempotentie.

### 25.3 Vastgestelde gedragsregels

- **Bronnen**: e-Captain (leden, lidmaatschappen, financiële posten, reserveringen, relaties) en WordPress (content). Extractie via CSV/Excel; een reverse-engineerde e-Captain-API hooguit als eenmalig extractiemiddel, niet als permanente koppeling.
- **Tussenformaat** ontkoppelt de bron van het datamodel; mapping is een herbruikbaar profiel.
- **Dry-run verplicht** met validatie en rapportage vóór de definitieve import.
- **Idempotent op herkomst-ID**: her-import werkt bij in plaats van dubbelen.
- **Goedkeuringsmotor omzeild**: geïmporteerde records worden als bestaand gemarkeerd.
- **WordPress-content** wordt heuristisch omgezet naar banden/blokken en landt in de status concept/te-controleren; een redacteur controleert handmatig vóór publicatie.
- **Archivering**: een combinatie van standaardregels (per bron/type, bijvoorbeeld op ouderdom/datum — afgelopen activiteiten, verlopen reserveringen, financiële historie, oud-leden, oude berichten/pagina's) plus handmatige bijstelling. Gearchiveerd betekent bewaard met beperkte zichtbaarheid via de autorisatielaag.
- **Terugdraaibaar per importrun** (via de `IMPORT_RECORD`-traceerbaarheid).
- **AVG-bewust**: alleen noodzakelijke gegevens; oude data archiveren of niet migreren.

### 25.4 Koppelingen

Lidmaatschap, Financiën, CMS/content, Reserveren-objecten en Activiteiten-historie (doelentiteiten), de goedkeuringsmotor (omzeild), de Audit trail (importlog) en de scheduler/queue.

---

## 26. Module Rechtenbeheer — detailuitwerking (3.01)

### 26.1 Front-end-weergaven (besloten)

- Een lid of functionaris kan een recht of rol **aanvragen** — voor zichzelf, of (als functionaris) voor anderen. Dit loopt via de goedkeuringsmotor.

### 26.2 Beheeraspecten

- Permissieoverzicht: de catalogus per module × actie (inclusief gevoelige zoals bypass en inloggen-als-ander).
- Rollen onderhouden en permissies toekennen, met standaardrollen vooraf.
- Rollen toewijzen aan personen (met optionele einddatum).
- Goedkeurdersgroepen beheren (gebruikt door de goedkeuringsmotor).
- Ledenprivileges instellen per lidmaatschapsvorm.

### 26.3 Datamodel

- **PERMISSION** — `id`, `key`, `module`, `action`, `description`, `is_sensitive`.
- **ROLE** — `id`, `name`, `description`, `is_system`.
- **ROLE_PERMISSION** — `id`, `role_id`, `permission_id`.
- **ROLE_ASSIGNMENT** — `id`, `person_id`, `role_id`, `status` (actief/gedeactiveerd/verlopen), `assigned_by`, `assigned_at`, `ends_at` (optioneel), `deactivated_at`. Toewijzingen worden niet verwijderd maar behouden voor de historie.
- **APPROVER_GROUP** — `id`, `name`; **GROUP_MEMBER** — `id`, `group_id`, `person_id`.
- **MEMBERSHIP_TYPE_PERMISSION** — `id`, `membership_type_id`, `permission_id`.
- **PERSON_PERMISSION** — `id`, `person_id`, `permission_id`, `ends_at` (optioneel), `status` — een individueel toegekend recht of uitzondering.

### 26.4 Vastgestelde gedragsregels

- **Effectieve permissies**: de unie van (a) alle rolpermissies, (b) eventueel individueel toegekende permissies (`PERSON_PERMISSION`, voor uitzonderingen) en (c) de lidmaatschapsprivileges — alle uit dezelfde permissiecatalogus. Domein-ledenrechten zoals botengebruik blijven aparte vlaggen op `MEMBERSHIP_TYPE` (ze voeden harde regels).
- **Standaard "Lid"-rol**: bij een actief lidmaatschap wordt automatisch een standaard "Lid"-rol toegekend met baseline-permissies (waaronder "andere leden opzoeken"). De meeste functionarisrollen bevatten dat recht eveneens; individueel kan het als uitzondering worden gegeven of ontnomen.
- **Rechtwijzigingen via de goedkeuringsmotor** (`subject_type` rechttoewijzing): een lid dat voor zichzelf een recht aanvraagt en een functionaris die voor anderen aanvraagt, lopen via goedkeuring; houders van de juiste bypass-permissie kennen direct toe, met logging.
- **Optionele geldigheidsduur**: roltoewijzingen kunnen een einddatum hebben (`ends_at`); de scheduler laat ze automatisch verlopen en signaleert dit.
- **Historie en heractivering**: roltoewijzingen worden nooit hard verwijderd. Deactiveren zet de status op gedeactiveerd (`deactivated_at`), zodat naspeurbaar blijft welke rechten iemand in het verleden had. Een gedeactiveerde of verlopen toewijzing kan later opnieuw worden geactiveerd (een nieuwe toewijzingsperiode van dezelfde rol).
- **Cumulatief en least privilege**: rollen stapelen; standaardrollen vooraf; globaal in v1 (objectniveau-scoping later).
- **Gevoelige permissies** (impersonate, bypass) zijn gemarkeerd (`is_sensitive`). Bij inloggen-als-ander (impersonatie) is permanent en prominent zichtbaar dat men als een andere gebruiker kijkt (een vaste banner), en wordt de hele sessie geaudit.
- **Accounts voor niet-leden**: niet-lid-functionarissen (zoals een terrein- of gebouwbeheerder) krijgen een gebruikersaccount aangemaakt door een beheerder of een andere functionaris.
- **Alle rechtenwijzigingen** worden gelogd in de Audit trail.

### 26.5 Koppelingen

Goedkeuringsmotor (rechttoewijzing als `subject_type`, bypass, groepen), alle beheermodules (permissies), Lidmaatschap (ledenprivileges), de scheduler (verlopen toewijzingen) en de Audit trail.

---

## 27. Module Vrijwilligersplanning — detailuitwerking (3.11)

### 27.1 Front-end-weergaven (besloten)

- Overzicht van openstaande diensten (lijst en kalender, met filters), detail met aanmeldknop, en "mijn diensten" om aan- en af te melden.

### 27.2 Beheeraspecten

- Taken/diensten en terugkerende reeksen definiëren, met benodigd aantal, tijd en locatie.
- Aanmeldingen beheren, vrijwilligers toewijzen, herinneringen versturen en presentie bijhouden.
- Vrijwilligers werven/uitnodigen via Mailing.

### 27.3 Datamodel

- **VOLUNTEER_TASK_SERIES** — `id`, `title`, `description`, `recurrence_rule`, `needed_count`, `location`, `status`, `split_from`.
- **VOLUNTEER_TASK** — `id`, `series_id` (optioneel), `activity_id` (optioneel), `title`, `starts_at`, `ends_at`, `location`, `needed_count`, `status`, `is_exception`.
- **VOLUNTEER_SIGNUP** — `id`, `task_id`, `person_id`, `status` (aangemeld/afgemeld/aanwezig/niet_verschenen), `source` (zelf/toegewezen), `assigned_by` (optioneel), `signed_up_at`.
- **QUALIFICATION** — `id`, `name` (bijvoorbeeld EHBO, instructeur, vaarbewijs).
- **PERSON_QUALIFICATION** — `id`, `person_id`, `qualification_id`, `verified`, `valid_until` (optioneel), `proof_media_id` (optioneel).
- **TASK_REQUIREMENT** — `id`, `task_id` of `series_id`, `qualification_id`.

### 27.4 Vastgestelde gedragsregels

- **Drie verschijningsvormen**, combineerbaar: een losse taak, een terugkerende reeks (bijvoorbeeld bardienst) en een aan een activiteit gekoppelde taak (bijvoorbeeld lesgeven, via `activity_id`).
- **Serie-bewerking** volgt dezelfde drie reikwijdtes als Activiteiten (dit voorkomen / dit en volgende / hele serie; `is_exception` blijft beschermd).
- **Koppeling op beide manieren**: vrijwilligers melden zichzelf aan, of een planner wijst toe (`source`, `assigned_by`).
- **Aanmelding meestal direct, soms via goedkeuring**: een taak of reeks kan vereisten stellen (bijvoorbeeld een diploma zoals EHBO, instructeur of vaarbewijs, via `TASK_REQUIREMENT`). Heeft de vrijwilliger de vereiste, geverifieerde kwalificatie (`PERSON_QUALIFICATION`), dan gaat de aanmelding direct door; ontbreekt die of vereist ze nog verificatie, dan loopt de aanmelding via de goedkeuringsmotor (een coördinator verifieert en keurt goed).
- **Aanmeldniveau**: bij een reeks (zoals een bardienst — een losse reeks zonder activiteit) meldt een vrijwilliger zich per voorkomen aan; optioneel kan, net als bij Activiteiten, op serieniveau worden aangemeld voor wie een vaste dienst draait.
- **Herinneringen** via Mailing en de scheduler.
- **Vrijwilligersvergoeding** geparkeerd bij Financiën (crediteuren/declaraties, later).

### 27.5 Koppelingen

Activiteiten (`activity_id`), Reserveren/objecten (locatie), Mailing (werving en herinnering), Lidmaatschap (`PERSON`) en later Financiën.

---

*Document groeit mee; resterende onderdelen: Nieuws/berichten, Documenten, Communicatielogboek, Audit trail en Reviewinstellingen.*

## 28. Module Nieuws/berichten — detailuitwerking

Een lichte module bovenop het CMS: een bericht is in essentie een vereenvoudigde pagina met een datum en een categorie.

### 28.1 Front-end-weergaven

- **Overzicht**: een lijst van berichten (via de module "Nieuws/berichten", plaatsbaar op pagina's), met filter op categorie.
- **Detail**: een enkel bericht.

### 28.2 Beheeraspecten

- Berichten aanmaken, bewerken en publiceren via het CMS — met hetzelfde versiebeheer en dezelfde goedkeuringsmotor als pagina's.
- Categorieën beheren.

### 28.3 Datamodel

- **POST** — `id`, `title`, `slug`, `category_id`, `visibility`, `published_version_id`, `published_at`. Hergebruikt het content-/versiemodel van het CMS (banden/blokken, versies).
- **POST_CATEGORY** — `id`, `name`.

### 28.4 Koppelingen

CMS (content, versiebeheer, goedkeuringsmotor), Mailing (een nieuwsbrief kan berichten bundelen) en Rechten/AVG (zichtbaarheid publiek/leden).

## 29. Module Documenten (Documentbeheer, 3.04) — detailuitwerking

Een documentenbibliotheek bovenop de gedeelde media-/bestandsopslag, met mappen en zichtbaarheid.

### 29.1 Front-end-weergaven

- **Documenten-module**: toont downloads (publiek of besloten), gefilterd op zichtbaarheid en map.

### 29.2 Beheeraspecten

- Documenten uploaden, ordenen in mappen, versie en zichtbaarheid instellen.

### 29.3 Datamodel

- **DOCUMENT** — `id`, `title`, `media_asset_id`, `folder_id`, `visibility`, `version`, `uploaded_by`, `created_at`.
- **DOCUMENT_FOLDER** — `id`, `name`, `parent_id` (hiërarchie).

### 29.4 Koppelingen

Media (`MEDIA_ASSET`), Rechten (zichtbaarheid per rol/lid), Lidmaatschap (bewijsmiddelen kunnen hier landen) en het CMS (de Documenten-module op pagina's).

## 30. Module Communicatielogboek (3.03) — detailuitwerking

Vastleggen van contactmomenten met leden en relaties.

### 30.1 Beheeraspecten

- Automatisch loggen: verzonden mailings, facturen/herinneringen en andere systeemcommunicatie landen hier automatisch.
- Handmatig vastleggen blijft mogelijk: een functionaris voegt contactmomenten toe (telefoon, social media, gesprek, brief) en kan ze per persoon inzien.

### 30.2 Datamodel

- **COMMUNICATION_LOG** — `id`, `person_id`, `channel` (telefoon/email/social/gesprek/brief), `direction` (in/uit), `subject`, `notes`, `logged_by`, `occurred_at`, `related_type`/`related_id` (optionele koppeling, bv. een mailing of factuur).

### 30.3 Vastgestelde gedragsregels en koppelingen

- Privacygevoelig: inzage is een permissie (Rechten); AVG-bewust met bewaartermijnen.
- Koppelingen: Mailing (automatisch loggen van verzonden communicatie), Lidmaatschap (`PERSON`) en Rechten (inzage).

## 31. Audit trail (3.13) — detailuitwerking

Een cross-cutting, alleen-toevoegen logboek van alle betekenisvolle handelingen.

### 31.1 Beheeraspecten

- Doorzoekbaar overzicht met filters (persoon, module, periode); alleen-lezen.

### 31.2 Datamodel

- **AUDIT_ENTRY** — `id`, `actor_person_id`, `action`, `subject_type`, `subject_id`, `before` (JSON), `after` (JSON), `context` (bv. via bypass of overschrijving), `occurred_at`, `ip`, `user_agent`.

### 31.3 Vastgestelde gedragsregels en koppelingen

- **Append-only en onveranderbaar**: entries worden nooit gewijzigd of verwijderd; bewaartermijn conform beleid.
- Legt onder meer vast: goedkeuringen, bypass en overschrijvingen, rechtenwijzigingen, financiële mutaties en imports.
- Koppelingen: alle modules (schrijven naar de log), de goedkeuringsmotor en Rechten.

## 32. Reviewinstellingen (3.14) — detailuitwerking

De configuratieschil van de goedkeuringsmotor; voegt geen nieuwe entiteiten toe maar beheert bestaande.

### 32.1 Beheeraspecten

- Per `subject_type` het beleid (`REVIEW_POLICY`) instellen: voorwaarden, aantal reviewstappen, toegewezen rollen/groepen, bypass-permissies, heropen-gedrag (`resubmit_behavior`) en de termijn-/escalatie-instellingen.
- Markeren welke velden gevoelig zijn (`FIELD_DEFINITION.is_sensitive`).
- De modulespecifieke drempels onderhouden (bijvoorbeeld de reserveringsregels, `RESERVATION_RULE`).

### 32.2 Datamodel en koppelingen

- Hergebruikt `REVIEW_POLICY` (hoofdstuk 20), `FIELD_DEFINITION` (hoofdstuk 21) en de modulespecifieke regels (zoals `RESERVATION_RULE`).
- Koppelingen: de goedkeuringsmotor (het beleid dat het uitvoert), Rechtenbeheer (rollen/groepen/permissies) en de modules waarop het beleid van toepassing is.

---

## 33. Samenvattend datamodeloverzicht

Alle entiteiten gegroepeerd per domein; `PERSON` is de centrale, gedeelde entiteit.

**Personen en rechten** — PERSON, HOUSEHOLD, ROLE, PERMISSION, ROLE_PERMISSION, ROLE_ASSIGNMENT, APPROVER_GROUP, GROUP_MEMBER, MEMBERSHIP_TYPE_PERMISSION.

**Lidmaatschap** — MEMBERSHIP, MEMBERSHIP_TYPE, PROOF_DOCUMENT, GUARDIANSHIP, PERSON_FIELD_VISIBILITY, FIELD_DEFINITION.

**CMS en content** — PAGE, PAGE_VERSION, BAND, BLOCK, MODULE, TEMPLATE, NAV_ITEM, MEDIA_ASSET, POST, POST_CATEGORY, DOCUMENT, DOCUMENT_FOLDER.

**Activiteiten** — ACTIVITY_SERIES, ACTIVITY, ENROLLMENT, ACTIVITY_CATEGORY.

**Reserveren en objecten** — OBJECT_CATEGORY, RESERVABLE_OBJECT, RESERVATION_RULE, RESERVATION, CATEGORY_RESPONSIBLE, DAMAGE_REPORT, DAMAGE_REPORT_MEDIA.

**Vrijwilligers** — VOLUNTEER_TASK_SERIES, VOLUNTEER_TASK, VOLUNTEER_SIGNUP, QUALIFICATION, PERSON_QUALIFICATION, TASK_REQUIREMENT.

**Financiën** — PRODUCT, PRODUCT_PRICE, CHARGE, INVOICE, PAYMENT, SEPA_MANDATE, LEDGER_ACCOUNT, JOURNAL_ENTRY, JOURNAL_LINE.

**Goedkeuring en governance** — PROPOSAL, REVIEW_POLICY, REVIEW_STEP, AUDIT_ENTRY.

**Communicatie** — MESSAGE_TEMPLATE, MAILING, MAILING_RECIPIENT, COMMUNICATION_PREFERENCE, COMMUNICATION_LOG.

**Migratie** — IMPORT_RUN, IMPORT_MAPPING, IMPORT_RECORD (plus herkomst-ID's `ecaptain_id`/`wordpress_id` op de doelentiteiten).

### Kernrelaties en dwarsverbanden

- `PERSON` is de spil: lidmaatschap(pen), roltoewijzingen, inschrijvingen, reserveringen, facturen (als betaler), aanmeldingen en kwalificaties hangen eraan.
- De goedkeuringsmotor (`PROPOSAL`) is polymorf via `subject_type`/`subject_id` en bedient content, activiteiten, ledenwijzigingen, reserveringen en rechttoewijzingen.
- Versiebeheer zit in `PAGE_VERSION` (en `POST`); content (`BAND`/`BLOCK`) hangt onder een versie.
- De categoriehiërarchie (`OBJECT_CATEGORY`) draagt zowel de reserveringsregels als de schade-verantwoordelijken.
- Het product-model (`PRODUCT`/`CHARGE`/`INVOICE`) verbindt lidmaatschap, activiteitsbijdragen en advertenties met de financiële administratie.
- `FIELD_DEFINITION` bundelt de veldgedragingen (verbergbaar, doorzoekbaar, gevoelig) voor Lidmaatschap en Leden zoeken.

## 34. Implementatie- en faseringsvoorstel

Voorgestelde bouwvolgorde, met de kern eerst en al vroeg een toonbare publieke site.

**Fase 0 — Fundament.** Projectopzet (Laravel, MySQL, CI/CD), authenticatie en accounts, `PERSON`, het rechten- en rollenmodel, de kern van de goedkeuringsmotor en de Audit trail. Plus gedeelde infrastructuur: scheduler en queue, en de transactionele e-maildienst.

**Fase 1 — CMS en publieke site.** Content-/banden-/blokkenmodel, paginabewerker, navigatie, mediabibliotheek en sjablonen. Hiermee staat snel een eenvoudige, verzorgde publieke website.

**Fase 2 — Lidmaatschap.** `HOUSEHOLD`/`MEMBERSHIP`/`MEMBERSHIP_TYPE`, Lid worden, Ledenbeheer, veld-zichtbaarheid en Leden zoeken. Inclusief migratie van leden uit e-Captain.

**Fase 3 — Financiën.** Producten, contributie, facturatie, betalingen via Mollie (SEPA/iDEAL) en de lichte boekhouding met export. Inclusief migratie van financiële historie.

**Fase 4 — Activiteiten, Reserveren en Schade melden.** De modules met de goedkeuringsdrempels, de objecten en de categoriehiërarchie.

**Fase 5 — Aankleding en back office.** Vrijwilligersplanning, Mailing en notificaties, Communicatielogboek, Nieuws/berichten, Documenten en Reviewinstellingen.

Dwars door alle fasen heen: migratie/import per relevant domein, en doorlopende aandacht voor AVG (toestemming, zichtbaarheid, bewaartermijnen) en voor toegankelijkheid en mobiel-eerst.

---

## 35. Niet-functionele eisen

### 35.1 Beveiliging

- OWASP Top 10 als leidraad; de ingebouwde bescherming van het framework tegen SQL-injectie, XSS en CSRF benutten.
- Versleuteling in transit (TLS/HTTPS overal) en van gevoelige gegevens at rest.
- Sterke authenticatie: wachtwoordbeleid, brute-force-/rate-limiting, en tweefactorauthenticatie (verplicht voor beheerders/functionarissen, optioneel voor leden).
- Autorisatie volgens least privilege (het rechten- en rollenmodel) met functiescheiding.
- Beveiligingsheaders (waaronder Content-Security-Policy) en veilige sessie- en cookie-instellingen.
- Geen betaalkaartgegevens opslaan: betalingen via Mollie (redirect), zodat de PCI-scope minimaal blijft.
- Periodieke dependency- en kwetsbaarheidsscans, met tijdig patchen.
- Een volledige, onveranderbare audit trail (hoofdstuk 31).

### 35.2 Privacy en gegevensbescherming (AVG)

- Dataminimalisatie en doelbinding; bewaartermijnen per gegevenssoort.
- Ondersteuning voor de rechten van betrokkenen: inzage, correctie, verwijdering/anonimisering en export.
- Verwerkingsregister; verwerkersovereenkomsten met externe diensten (e-maildienst, Mollie, hosting); EU-datahosting.
- Expliciete toestemming, met extra zorg voor minderjarigen (ouderlijke toestemming).

### 35.3 Testen en kwaliteit

- Geautomatiseerde tests op meerdere niveaus: unit, feature/integratie en een set regressietests; kritieke flows (inschrijven, reserveren, betalen, goedkeuren) end-to-end getest.
- Statische analyse (bijvoorbeeld PHPStan) en een vaste codestijl (PSR-12), automatisch afgedwongen via een linter/formatter.
- Een CI-pijplijn die tests, analyse en stijl bij elke wijziging draait; code review vóór samenvoegen.
- Een streefwaarde voor testdekking op de kernlogica.

### 35.4 Onderhoudbaarheid en leesbaarheid

- Goed leesbare, consistent gestructureerde code die de conventies van het framework volgt.
- Modulaire opzet met lage koppeling; gedeelde mechanismen (goedkeuringsmotor, rechten, audit) als herbruikbare bouwstenen.
- Technische documentatie en belangrijke ontwerpbeslissingen vastgelegd (ADR's).
- Databasewijzigingen via migraties; een herhaalbare, gedocumenteerde omgevingsopzet.

### 35.5 Betrouwbaarheid en beschikbaarheid

- Regelmatige, geteste back-ups en een herstelprocedure (disaster recovery).
- Monitoring en alerting; foutopvolging (error tracking).
- Nette foutafhandeling; achtergrondtaken via een wachtrij met herhaalpogingen. De scheduler en de queue-workers zijn kritieke infrastructuur en worden gemonitord.

### 35.6 Performance en schaalbaarheid

- Vlotte laadtijden, ook op mobiel; efficiënte queries (N+1 vermijden) en caching waar zinvol.
- Zware of piekgevoelige taken (mailings, contributie-run, import) via de wachtrij.
- Bestand tegen de te verwachten gelijktijdige belasting, bijvoorbeeld bij het openstellen van reserveringen of populaire activiteiten.

### 35.7 Toegankelijkheid en bruikbaarheid

- WCAG 2.1 niveau AA als richtlijn: semantische HTML, toetsenbordbediening, voldoende contrast en respect voor "verminderde beweging".
- Mobiel-eerst en consistent via het design-systeem.

### 35.8 Lokalisatie en techniek

- Nederlandstalig, met Nederlandse datum-, getal- en valutaopmaak en de tijdzone Europe/Amsterdam; de opzet is voorbereid op eventuele meertaligheid.
- Ondersteuning van gangbare, actuele browsers (desktop en mobiel).

### 35.9 Beheer en uitrol

- **Meerdere omgevingen (OTAP + lokaal)**: een lokale ontwikkelomgeving (Docker) per ontwikkelaar, plus één of meer server-omgevingen — bijvoorbeeld ontwikkel, test, acceptatie en productie. Wijzigingen promoveren door de straat (ontwikkel → test → acceptatie → productie), bij voorkeur via een CI/CD-pijplijn.
- **Configuratie en data per omgeving**: omgevingsspecifieke instellingen en secrets via omgevingsvariabelen (niet in code); een eigen database per omgeving. In test/acceptatie geen productie-persoonsgegevens zonder anonimisering (AVG); acceptatie zo productie-gelijk mogelijk.
- Reproduceerbare, liefst laag-impact uitrol (migraties, omgevingsconfiguratie).
- Dubbele runtime met omgevingspariteit: dezelfde codebase draait op een klassieke webserver (productie) en in een Docker-container (lokaal); een meegeleverde container-opzet verzorgt de lokale omgeving, met gelijke PHP-versie, extensies en configuratie.
- Bijhouden van de licenties van externe componenten (open-source compliance).

---

## 36. Rapportage en dashboards

Cross-cutting overzichten voor bestuur en functionarissen.

### 36.1 Functies

- **Bestuursdashboard**: ledenaantallen en -verloop (in- en uitstroom), financiële stand (openstaande posten, geïnde contributie), deelname aan activiteiten en het gebruik van reserveringen/objecten.
- **Module-overzichten**: per domein relevante lijsten en kerncijfers (bijvoorbeeld wachtlijsten, openstaande schademeldingen, vrijwilligersbezetting).
- **Export** naar CSV/Excel/PDF voor verslagen en de externe boekhouder.

### 36.2 Uitgangspunten

- Toegang per rol/permissie (bestuur, penningmeester, ledenadministratie).
- Read-only, op basis van de bestaande data; AVG-bewust (geaggregeerd waar mogelijk).
- De concrete rapporten en grafieken worden in de detailfase per doelgroep uitgewerkt.

### 36.3 Koppelingen

Lidmaatschap, Financiën, Activiteiten, Reserveren, Schade melden en Vrijwilligersplanning (databronnen), Rechten (toegang) en de Audit trail.

---

*Einde ontwerpdocument.*
