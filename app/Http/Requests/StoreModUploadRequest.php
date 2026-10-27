<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Mod;
use Illuminate\Validation\Rule;

class StoreModUploadRequest extends ValidateModUploadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'mod_id' => [
                'nullable',
                'integer',
                Rule::exists('mods', 'id')->whereNull('deleted_at'),
            ],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'min:2', 'max:30', 'regex:/^[\pL\pN \&\-_]+$/u'],
            'changelog' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('tags'))) {
            $raw = trim((string) $this->input('tags'));
            $parts = $raw === ''
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $raw)), fn (string $v) => $v !== ''));
            $this->merge(['tags' => $parts]);
        }
    }

    public function targetMod(): ?Mod
    {
        $modId = $this->input('mod_id');
        if ($modId === null || $modId === '') {
            return null;
        }

        return Mod::query()->find((int) $modId);
    }
}

