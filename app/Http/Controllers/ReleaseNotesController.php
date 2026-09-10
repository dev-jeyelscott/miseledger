<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ReleaseNotesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('release-notes/index');
    }
}
