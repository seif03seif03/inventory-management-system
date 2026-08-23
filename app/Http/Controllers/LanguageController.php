<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * Switch application language between English and Arabic.
     */
    public function switch(string $locale)
    {
        if (in_array($locale, ['en', 'ar'], true)) {
            session(['locale' => $locale]);
        }

        return back();
    }
}
