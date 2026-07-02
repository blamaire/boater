# Productie-mailconfiguratie

Deze notitie beschrijft hoe de RZVG-applicatie transactionele mail in productie
verstuurt en hoe je een externe mailprovider aansluit. Voor lokale ontwikkeling
gebruikt de app Mailpit (draait als losse container, zie `docker-compose.yml`),
zonder verdere configuratie.

## Welke mails verstuurt de app?

| Trigger | Notification-class | Wanneer |
| --- | --- | --- |
| Registratie | `App\Notifications\QueuedVerifyEmail` | Bij aanmaken account (Breeze) |
| Wachtwoord vergeten | `App\Notifications\QueuedResetPassword` | Bij `POST /forgot-password` |
| Toekomstig: notificaties (bestuur, ledenmailings) | Nog te bouwen | Zie `§26` ontwerpdoc |

Beide bovenstaande zijn queueable varianten van Laravel's default notifications
(`Illuminate\Auth\Notifications\VerifyEmail` en `ResetPassword`). Ze worden in
`App\Models\User::sendEmailVerificationNotification()` respectievelijk
`sendPasswordResetNotification()` gebruikt.

## Waarom via de queue?

De `QueuedVerifyEmail`- en `QueuedResetPassword`-notifications implementeren
`ShouldQueue`. Laravel plaatst het bericht daardoor op de queue-connection
(`QUEUE_CONNECTION=database`, zie `.env.example`) in plaats van het synchroon
te versturen.

Voordelen:

- **Snellere requests** — de HTTP-response wacht niet op een externe SMTP-call
  die honderden ms tot enkele seconden kan duren.
- **Retry-logica** — mislukte verzending (netwerkfout, rate-limit) wordt door
  Laravel automatisch opnieuw geprobeerd volgens de `tries`/`backoff`-instellingen.
- **Isolatie** — een storing bij de mailprovider blokkeert geen registraties.

In productie draait een aparte worker (bijv. `php artisan queue:work`) via
`systemd`, `supervisord` of een sidecar-container. Draai die worker altijd naast
de web-container.

## Provider-keuze

| Provider | Voor- en nadeel (kort) |
| --- | --- |
| **Postmark** | Beste deliverability voor puur transactionele mail; iets duurder. |
| **Mailgun** | Goede EU-optie (endpoint `api.eu.mailgun.net`), krachtige API; setup wat complexer. |
| **Amazon SES** | Zeer goedkoop op volume; vereist DKIM/SPF-configuratie in Route53. |
| **Resend** | Moderne developer-UX, MJML-templates; jong platform, kleinere reputatie. |
| **Generieke SMTP** | Werkt met elke hosting-mailbox; geen dashboard, beperkte deliverability-inzichten. |

Voor RZVG wordt **Postmark** aanbevolen wanneer alleen transactionele
mails worden verstuurd (verificatie, reset). Bij bulkmailings (ledennieuwsbrief)
is Mailgun of SES kostenefficiënter.

## Stappenplan aansluiten

1. **Composer-package installeren** (in de `app`-container):

   ```sh
   docker compose exec -T app composer require symfony/postmark-mailer symfony/http-client
   # of, per provider:
   #   composer require symfony/mailgun-mailer symfony/http-client
   #   composer require aws/aws-sdk-php
   #   composer require resend/resend-laravel
   ```

2. **Env-variabelen zetten** in het productie-`.env` (zie het commentblok in
   `.env.example` voor de exacte variabelen per provider). Vergeet niet:

   ```dotenv
   MAIL_FROM_ADDRESS="noreply@rzvg.nl"
   MAIL_FROM_NAME="Roei- en Zeilvereniging Gouda"
   ```

3. **DNS-records aanmaken** voor `rzvg.nl` bij de domeinregistrar:

   - **SPF** — TXT-record dat de provider machtigt namens `rzvg.nl` te verzenden.
     Voorbeeld voor Postmark: `v=spf1 a mx include:spf.mtasv.net ~all`.
   - **DKIM** — CNAME- of TXT-record (waarde krijg je van de provider).
   - **DMARC** — Optioneel maar aanbevolen: `v=DMARC1; p=quarantine; rua=mailto:postmaster@rzvg.nl`.

   Verifieer records via de provider-console; wacht tot de status "verified" is
   voordat je live gaat.

4. **Config-cache verversen** en de queue-worker herstarten:

   ```sh
   docker compose exec -T app php artisan config:cache
   docker compose exec -T app php artisan queue:restart
   ```

5. **Testen** vanuit tinker:

   ```sh
   docker compose exec -T app php artisan tinker
   >>> Mail::raw('Test vanuit RZVG-productie', fn ($m) => $m->to('jij@example.com')->subject('Test'));
   ```

   Controleer daarna in de provider-console of het bericht is verzonden en
   controleer je inbox (én de spam-map de eerste keer). Voor een end-to-end test
   van de queue-flow: registreer een testgebruiker en controleer dat de
   verificatie-mail aankomt en dat `queue:work` de job als `completed` markeert
   in de `jobs`- respectievelijk `failed_jobs`-tabel.

## Waarschuwingen (later te implementeren)

- **Bounces & complaints** — een aparte PR voegt webhook-endpoints toe voor de
  gekozen provider, zodat harde bounces automatisch de betreffende gebruiker
  op een suppression-list zetten (zie `§26.7` ontwerpdoc).
- **Rate-limiting** — bij ledenmailings moet uitgaande mail worden gethrottled
  (bijv. via Laravel's `RateLimited`-middleware op de mailable) om te voorkomen
  dat de provider je account tijdelijk blokkeert. Ook aparte PR.
- **Templates in huisstijl** — de huidige notifications gebruiken Laravel's
  default markdown-template. Een kleine follow-up-PR vernieuwt deze naar
  RZVG-huisstijl (logo, kleuren, footer).
