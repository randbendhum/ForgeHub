<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Contracts;

use App\Services\ModParsing\Data\ParseContext;
use App\Services\ModParsing\Data\ValidationReport;

interface ManifestParser
{
    public function supports(string $filename, string $rawContent): bool;

    public function parse(string $rawContent, ParseContext $context): ValidationReport;
}

