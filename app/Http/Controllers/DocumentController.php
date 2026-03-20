<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documentService)
    {
    }

    public function download(Document $document): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $document);

        $actor = Auth::user();

        abort_unless($actor instanceof User, 403);

        return $this->documentService->serve($document, $actor);
    }
}
