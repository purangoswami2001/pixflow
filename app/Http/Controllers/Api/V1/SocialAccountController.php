<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->organizationAccountsQuery()
            ->where('provider', 'pinterest');

        $accounts = $query->with(['destinations' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->orderBy('provider')
            ->orderBy('name')
            ->get()
            ->map(fn (SocialAccount $account) => $this->accountPayload($account));

        return response()->json([
            'data' => $accounts,
        ]);
    }

    public function destinations(Request $request, int $account): JsonResponse
    {
        $socialAccount = $this->organizationAccountsQuery()->findOrFail($account);

        $destinations = $socialAccount->activeDestinations()
            ->get()
            ->map(fn ($destination) => [
                'id' => $destination->id,
                'name' => $destination->name,
                'destination_type' => $destination->destination_type?->value ?? $destination->destination_type,
                'provider' => $destination->provider?->value ?? $destination->provider,
                'provider_destination_id' => $destination->provider_destination_id,
            ]);

        return response()->json([
            'account' => [
                'id' => $socialAccount->id,
                'name' => $socialAccount->name,
                'provider' => $socialAccount->provider?->value ?? $socialAccount->provider,
            ],
            'data' => $destinations,
        ]);
    }

    protected function organizationAccountsQuery()
    {
        $adminIds = User::role('admin')->pluck('id');

        return SocialAccount::query()
            ->where('status', 'active')
            ->whereIn('user_id', $adminIds);
    }

    /**
     * @return array<string, mixed>
     */
    protected function accountPayload(SocialAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'username' => $account->username,
            'provider' => $account->provider?->value ?? $account->provider,
            'avatar_url' => $account->avatar_url,
            'destinations' => $account->destinations->map(fn ($destination) => [
                'id' => $destination->id,
                'name' => $destination->name,
                'destination_type' => $destination->destination_type?->value ?? $destination->destination_type,
            ])->values()->all(),
        ];
    }
}
