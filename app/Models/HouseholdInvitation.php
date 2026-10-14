<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\HouseholdInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An e-mail invitation to join the household with a one-time registration token.
 *
 * The plain token is only ever sent by mail; the database stores its SHA-256
 * hash. An invitation is pending until it is accepted or expires (default
 * 24 hours, see `HOUSEHOLD_INVITATION_TTL`).
 *
 * @property string $email Invited e-mail address.
 * @property string $token SHA-256 hash of the registration token.
 * @property int|null $invited_by ID of the inviting user.
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 */
class HouseholdInvitation extends Model
{
    /** @use HasFactory<HouseholdInvitationFactory> */
    use HasFactory;

    protected $fillable = ['email', 'token', 'invited_by', 'expires_at', 'accepted_at'];

    /**
     * Generate a new cryptographically random plain-text registration token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Find the pending (neither accepted nor expired) invitation for a plain token.
     */
    public static function findPendingByToken(string $token): ?self
    {
        return static::where('token', static::hashToken($token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Hash a plain-text token the way it is stored in the database.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Whether the invitation is still open: not accepted and not expired.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->expires_at->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
