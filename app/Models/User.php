<?php

namespace App\Models;

use App\Services\Communication\MessageDispatcher;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $disabled_at
 * @property-read Person|null $person
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'disabled_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'disabled_at' => 'datetime',
        ];
    }

    public function person(): HasOne
    {
        return $this->hasOne(Person::class, 'account_id');
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * Verstuur de e-mailverificatie-notificatie via de queue (§24,
     * `MessageDispatcher`), zodat de HTTP-request niet blokkeert op de
     * SMTP-call. URL-opbouw 1-op-1 overgenomen van Laravel's eigen
     * `VerifyEmail::verificationUrl()`.
     */
    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        app(MessageDispatcher::class)->send('email_verification', $this->email, [
            '{{verificatie_url}}' => $url,
        ], recipient: $this->person);
    }

    /**
     * Verstuur de wachtwoord-reset-notificatie via de queue (§24,
     * `MessageDispatcher`).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = url(route('password.reset', ['token' => $token, 'email' => $this->getEmailForPasswordReset()], false));
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        app(MessageDispatcher::class)->send('password_reset', $this->email, [
            '{{reset_url}}' => $url,
            '{{minuten}}' => (string) $minutes,
        ], recipient: $this->person);
    }
}
