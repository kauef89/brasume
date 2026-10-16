<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeDownloadController extends Controller
{
    public function download(Request $request)
    {
        $url = $request->query('url');
        $format = $request->query('format', 'svg');

        if (!$url) {
            return abort(400, 'A URL é obrigatória.');
        }

        $fileName = 'Brasume_Equipe_QR.' . $format;
        $qr = QrCode::size(500)->margin(2);

        if ($format === 'png') {
            $image = $qr->format('png')->generate($url);
            return response($image)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }

        $svg = $qr->generate($url);
        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
}
