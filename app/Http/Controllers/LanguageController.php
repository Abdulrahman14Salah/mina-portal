<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LanguageController extends Controller
{
    public function switch(string $locale): RedirectResponse
    {
        $locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'en';

        session(['locale' => $locale]);

        return redirect()->back();
    }
}
