<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TermsController extends Controller
{
    public function show(): Response
    {
        $markdown = File::get(resource_path('legal/terms.md'));

        return Inertia::render('public/terms', [
            'content' => Str::markdown($markdown),
            'termsVersion' => (string) config('legal.terms_version'),
        ]);
    }
}
