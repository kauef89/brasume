<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaServeController extends Controller
{
    public function serve(Request $request)
    {
        $path = $request->query('path');

        if (blank($path)) {
            abort(400, 'Caminho ausente.');
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Arquivo não localizado.');
        }

        return Storage::disk('public')->response($path);
    }
}
