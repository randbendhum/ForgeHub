<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\ModParsing\ArchiveInspector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafeArchive implements ValidationRule
{
    public function __construct(
        private readonly ArchiveInspector $inspector = new ArchiveInspector(),
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The submitted file is invalid.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension() ?: pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION));
        if (! in_array($extension, ['zip', 'jar'], true)) {
            return;
        }

        $realPath = $value->getRealPath();
        if (! is_string($realPath) || $realPath === '') {
            $fail('Unable to access temporary upload package.');

            return;
        }

        $error = $this->inspector->validateSafety($realPath);
        if ($error !== null) {
            $fail($error);
        }
    }
}
