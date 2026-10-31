<?php

namespace App\Services;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProviderKycDocumentStorage
{
    private const DOCUMENT_FIELDS = ['brela_doc', 'tin_doc'];

    public function store(Provider $provider, Request $request): array
    {
        $paths = [];

        foreach (self::DOCUMENT_FIELDS as $field) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = Storage::disk('local')->putFileAs(
                'provider-kyc/'.$provider->id,
                $file,
                Str::uuid().'.'.$file->extension()
            );

            if ($path === false) {
                throw new RuntimeException("Unable to store provider KYC document: {$field}");
            }

            $paths[$field] = $path;
        }

        return $paths;
    }
}
