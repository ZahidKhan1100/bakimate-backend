<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves files from the public disk when `public/storage` symlink is missing (common on PaaS)
 * or when static file serving does not map to storage/app/public.
 */
final class PublicStorageController extends Controller
{
    public function __invoke(Request $request, string $path): Response
    {
        $path = str_replace('\\', '/', trim($path, '/'));

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        /** Only user-uploaded DuitNow QR images — block arbitrary disk reads. */
        if (! str_starts_with($path, 'duitnow/')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }
}
