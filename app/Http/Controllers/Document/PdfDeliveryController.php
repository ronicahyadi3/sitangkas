<?php

declare(strict_types=1);

namespace App\Http\Controllers\Document;

use App\Actions\Document\ServeResolvedPdf;
use App\Enums\Document\PdfDeliveryPurpose;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PdfDeliveryController extends Controller
{
    public function content(
        Request $request,
        string $document,
        string $resource,
        ServeResolvedPdf $serveResolvedPdf,
    ): StreamedResponse {
        return $serveResolvedPdf->handle(
            $this->authenticatedUser($request),
            $document,
            $resource,
            PdfDeliveryPurpose::View,
        );
    }

    public function download(
        Request $request,
        string $document,
        string $resource,
        ServeResolvedPdf $serveResolvedPdf,
    ): StreamedResponse {
        return $serveResolvedPdf->handle(
            $this->authenticatedUser($request),
            $document,
            $resource,
            PdfDeliveryPurpose::Download,
        );
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
