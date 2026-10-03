<?php

namespace App\Http\Controllers;

use App\Enums\CustomizationKey;
use App\Services\Helpers\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateThemeController extends Controller
{
    public function __invoke(Request $request, ThemeService $themeService): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in(array_keys($themeService->getThemeOptions()))],
        ]);

        user()?->setCustomization(CustomizationKey::Theme, $data['theme']);

        return redirect()->back();
    }
}
