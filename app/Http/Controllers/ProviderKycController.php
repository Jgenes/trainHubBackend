<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Services\ProviderKycDocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ProviderKycController extends Controller
{
    public function update(Request $request, ProviderKycDocumentStorage $documents)
    {
        $request->validate([
            'brela_doc' => ['required_without:tin_doc', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'tin_doc'   => ['required_without:brela_doc', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $provider = $this->currentProvider();
        if (! $provider) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $provider->update($documents->store($provider, $request));

        return response()->json([
            'message' => 'KYC documents uploaded successfully.',
            'data' => $provider->fresh(),
            'kyc_documents' => [
                'brela' => $provider->brela_doc ? route('provider.kyc.documents.download', 'brela') : null,
                'tin' => $provider->tin_doc ? route('provider.kyc.documents.download', 'tin') : null,
            ],
        ]);
    }

    public function downloadForProvider(string $document): Response|BinaryFileResponse
    {
        $provider = $this->currentProvider();
        if (! $provider) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return $this->download($provider, $document);
    }

    public function downloadForAdmin(string $id, string $document): Response|BinaryFileResponse
    {
        return $this->download(Provider::findOrFail($id), $document);
    }

    private function currentProvider(): ?Provider
    {
        return Provider::where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere('user_id', Auth::id());
        })->first();
    }

    private function download(Provider $provider, string $document): Response|BinaryFileResponse
    {
        $field = match ($document) {
            'brela' => 'brela_doc',
            'tin' => 'tin_doc',
            default => abort(404),
        };
        $path = $provider->{$field};

        if (! $path) {
            abort(404);
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, basename($path));
        }

        // Continue serving documents uploaded before KYC files moved to private storage.
        if (str_starts_with($path, 'uploads/providers/')) {
            $legacyPath = public_path('uploads/providers/'.basename($path));
            if (is_file($legacyPath)) {
                return response()->download($legacyPath, basename($legacyPath));
            }
        }

        abort(404);
    }
}
