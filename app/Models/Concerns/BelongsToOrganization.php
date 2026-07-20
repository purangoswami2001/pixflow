<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::creating(function (Model $model) {
            if (! empty($model->organization_id)) {
                return;
            }

            $organizationId = app(OrganizationContext::class)->id();

            if ($organizationId) {
                $model->organization_id = $organizationId;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int|Organization $organization): Builder
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where($query->getModel()->getTable().'.organization_id', $organizationId);
    }

    public function scopeForCurrentOrganization(Builder $query): Builder
    {
        return $query->forOrganization(app(OrganizationContext::class)->requireId());
    }
}
