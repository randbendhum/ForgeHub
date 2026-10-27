<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\SafeArchive;
use Illuminate\Foundation\Http\FormRequest;

class ValidateModUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('forgemod.max_upload_kb', 51200);

        return [
            'file' => [
                'required',
                'file',
                'max:' . $maxKb,
                'extensions:zip,jar,xml,json,info',
                'mimetypes:application/zip,application/x-zip-compressed,application/java-archive,application/x-java-archive,application/octet-stream,application/xml,text/xml,application/json,text/plain',
                new SafeArchive(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'SELECT OR DRAG A MOD FILE (.ZIP, .JAR, .XML OR .JSON).',
            'file.file' => 'THE UPLOADED PAYLOAD IS NOT A VALID FILE.',
            'file.max' => 'THE FILE EXCEEDS THE MAXIMUM ALLOWED SIZE OF 50 MB.',
            'file.extensions' => 'BLOCKED EXTENSION: ONLY .ZIP, .JAR, .XML, .JSON OR .INFO FILES ARE ACCEPTED.',
            'file.mimetypes' => 'INVALID MIME TYPE FOR MOD PACKAGE OR MANIFEST.',
        ];
    }
}
