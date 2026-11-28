<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Only identity fields are mass assignable. Privileged account flags
     * (admin, active, guest, etc.) must be set via createAccount() or
     * direct property assignment — never from request::all()/fill().
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Privileged columns that may be set through createAccount()'s $flags.
     *
     * @var list<string>
     */
    private const ACCOUNT_FLAGS = [
        'admin',
        'active',
        'must_change_password',
        'is_guest',
        'is_restricted',
        'email_verification_code',
        'email_verification_code_expires_at',
    ];

    /**
     * Create a user with mass-assignable identity fields, then apply privileged
     * flags via direct assignment so they cannot ride in on request input.
     *
     * @param  array{name: string, email: string, password: string}  $identity
     * @param  array<string, mixed>  $flags
     */
    public static function createAccount(array $identity, array $flags = []): self
    {
        foreach (array_keys($flags) as $key) {
            if (!in_array($key, self::ACCOUNT_FLAGS, true)) {
                throw new \InvalidArgumentException("Unsupported account flag: {$key}");
            }
        }

        $user = new static();
        $user->fill([
            'name' => $identity['name'],
            'email' => self::normalizeEmail($identity['email']),
            'password' => $identity['password'],
        ]);

        // Single insert, so a row never briefly exists with the column defaults
        // for flags the caller is overriding (notably active, which defaults true).
        foreach ($flags as $key => $value) {
            $user->{$key} = $value;
        }

        $user->save();

        return $user;
    }

    /**
     * Emails are stored lowercased and trimmed (issue #244). Mail servers
     * treat addresses case-insensitively in practice, and a user who signed
     * up as Bob@Example.com should be able to log in as bob@example.com.
     */
    public static function normalizeEmail(?string $email): ?string
    {
        return $email === null ? null : strtolower(trim($email));
    }

    /**
     * Normalise an email field on the request before validation, so unique
     * rules and lookups compare like with like. Leaves a missing or non-string
     * field alone so optional-field validation still works.
     */
    public static function normalizeRequestEmail(Request $request, string $field = 'email'): void
    {
        $value = $request->input($field);
        if (is_string($value)) {
            $request->merge([$field => self::normalizeEmail($value)]);
        }
    }

    /**
     * Every write through the model is normalised too, as a backstop for any
     * path that forgets to normalise first.
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = is_string($value) ? self::normalizeEmail($value) : $value;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
        'email_verification_code_expires_at',
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
            'email_verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'admin' => $this->admin,
            'active' => $this->active,
            'must_change_password' => $this->must_change_password,
            'guest' => $this->is_guest == 1 ? true : false,
            'restricted' => $this->is_restricted ? true : false
        ];
    }

    /**
     * Check if this user is a restricted user (can only participate in liveshares).
     */
    public function isRestricted(): bool
    {
        return (bool) $this->is_restricted;
    }

    public function invite()
    {
        return $this->hasOne(ReverseShareInvite::class, 'guest_user_id');
    }

    public function invites()
    {
        return $this->hasMany(ReverseShareInvite::class, 'user_id');
    }

    public function shares()
    {
        return $this->hasMany(Share::class);
    }

    public function getFirstNameAttribute()
    {
        return explode(' ', $this->name)[0];
    }

    /**
     * The authentication providers that this user has linked
     */
    public function authProviders()
    {
        return $this->belongsToMany(AuthProvider::class, 'user_auth_provider')
            ->withPivot(['provider_user_id', 'provider_email', 'access_token', 
                         'refresh_token', 'token_expires_at', 'provider_data'])
            ->withTimestamps();
    }
}
