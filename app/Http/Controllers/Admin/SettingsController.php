<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Services\Ai\AiConfig;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ResolvesOrganization;

    public function index()
    {
        $organization = $this->currentOrganization();

        return view('admin.settings', [
            'organization' => $organization,
            'aiConfigured' => AiConfig::isConfigured(),
        ]);
    }

    public function updateAi(Request $request)
    {
        $organization = $this->currentOrganization();

        $enable = $request->boolean('ai_enabled');

        $organization->update(['ai_enabled' => $enable]);

        return redirect()
            ->route('admin.settings')
            ->with('status', $enable
                ? 'AI Mode is on. AI generation and automation features are now available for your firm.'
                : 'AI Mode is off. AI generation and automation are paused — Semi-AI Assist remains available.');
    }
}
