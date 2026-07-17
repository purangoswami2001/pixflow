<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    /**
     * Persist the authenticated user's appearance preference.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(['light', 'dark', 'system'])],
        ]);

        $user = $request->user();
        $user->theme = $validated['theme'];
        $user->save();

        if ($request->expectsJson() || $request->ajax()) {
            $systemDark = $request->has('system_dark')
                ? $request->boolean('system_dark')
                : null;

            return response()->json([
                'ok' => true,
                'theme' => $user->themePreference(),
                'resolved' => $user->resolvedTheme($systemDark),
            ]);
        }

        return back()->with('status', 'theme-updated');
    }
}
