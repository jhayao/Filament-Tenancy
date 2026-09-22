<?php

return [
    'members' => 'Members', 'invitation' => 'Invitation', 'invitations' => 'Invitations',
    'invite' => 'Invite members', 'emails' => 'Email addresses', 'emails_help' => 'Separate addresses with commas, spaces, or new lines.',
    'role' => 'Role', 'change_role' => 'Change role', 'remove' => 'Remove member',
    'transfer' => 'Transfer ownership', 'leave' => 'Leave team', 'create_link' => 'Create invitation link',
    'shareable_link' => 'Shareable link', 'expires' => 'Expires at', 'use_limit' => 'Use limit',
    'uses' => ':used of :limit uses', 'copy_link' => 'Copy link', 'copied' => 'Copied', 'resend' => 'Resend', 'revoke' => 'Revoke',
    'active' => 'Active', 'inactive' => 'Inactive', 'no_invitations' => 'No invitations yet.',
    'saved' => 'Changes saved', 'invited' => 'You have been invited to a team', 'accept' => 'Accept invitation',
    'email_body' => 'You have been invited to join a team. Sign in or create an account to accept your invitation.',
    'email_expiry' => 'Expires: :date', 'join' => 'Join :team', 'personal_name' => ':name’s team', 'pruned' => 'Pruned :count invitations.',
    'errors' => [
        'forbidden' => 'You do not have permission to perform this action.',
        'invalid_role' => 'This role is unavailable or cannot be assigned by you.',
        'full' => 'This team has reached its seat limit, including pending invitations.',
        'last_owner' => 'Transfer ownership before leaving this team.',
        'invalid_invitation' => 'This invitation is invalid, expired, revoked, or has reached its use limit.',
        'cooldown' => 'Please wait before resending this invitation.',
        'already_member' => 'This person is already a member.',
        'email_mismatch' => 'Sign in using the email address this invitation was sent to.',
        'configuration' => 'Team configuration is incomplete.',
        'shield_configuration' => 'Install Filament Shield, enable permission.teams, and configure a user with Spatie HasRoles support.',
        'shield_connection' => 'Configure Spatie role and permission models with an explicit central connection.',
        'personal_creator' => 'Configure teams.personal_team_creator when using external tenant models.',
    ],
];
