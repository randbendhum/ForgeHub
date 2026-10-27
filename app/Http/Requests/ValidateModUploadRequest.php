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
        $maxKb = (int) config('forgemod.max_upload_kb', 102400);

        return [
            'file' => [
                'required',
                'file',
                'max:' . $maxKb,
                'extensions:zip,jar,xml,json,info,toml',
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
            'file.required' => 'SELECT OR DRAG A MOD FILE (.ZIP, .JAR, .XML, .JSON OR .TOML).',
            'file.file' => 'THE UPLOADED PAYLOAD IS NOT A VALID FILE.',
            'file.max' => 'THE FILE EXCEEDS THE MAXIMUM ALLOWED SIZE OF 100 MB.',
            'file.extensions' => 'BLOCKED EXTENSION: ONLY .ZIP, .JAR, .XML, .JSON, .TOML OR .INFO FILES ARE ACCEPTED.',
        ];
    }
}
