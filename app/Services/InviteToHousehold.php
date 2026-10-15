<?php

namespace App\Services;

use App\Mail\HouseholdInvitationMail;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Invites a new member to the household: stores a hashed one-time token and
 * mails the plain registration link. Invited accounts are created as admins,
 * because only admins can access the panel at all.
 */
final class InviteToHousehold
{
    /**
     * Create and mail an invitation for the given e-mail address.
     *
     * Any earlier pending invitations for the address are replaced, so a
     * fresh invite also works as a resend. The token expires after the
     * configured TTL (default 24 hours, `HOUSEHOLD_INVITATION_TTL`).
     *
     * @throws ValidationException When the e-mail is invalid or the address already has an account.
     */
    public function invite(string $email, int $invitedById): HouseholdInvitation
    {
        $email = strtolower(trim($email));
        Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255']])->validate();
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('users.invite.account_exists')]);
        }

        $token = HouseholdInvitation::generateToken();
        $invitation = DB::transaction(function () use ($email, $invitedById, $token): HouseholdInvitation {
            HouseholdInvitation::where('email', $email)->whereNull('accepted_at')->delete();

            return HouseholdInvitation::create([
                'email' => $email,
                'token' => HouseholdInvitation::hashToken($token),
                'invited_by' => $invitedById,
                'expires_at' => now()->addHours(config('app.invitation_ttl_hours')),
            ]);
        });

        Mail::to($invitation->email)->send(new HouseholdInvitationMail($invitation, $token));

        return $invitation;
    }
}
