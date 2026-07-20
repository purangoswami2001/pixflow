<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationMembership extends Pivot
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CREATOR = 'creator';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INVITED = 'invited';

    public const STATUS_SUSPENDED = 'suspended';

    protected $table = 'organization_user';

    public $incrementing = true;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            self::ROLE_OWNER => 'Owner',
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_CREATOR => 'Creator',
            default => 'Member',
        };
    }
}
