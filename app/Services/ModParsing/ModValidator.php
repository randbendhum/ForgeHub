<?php

declare(strict_types=1);

namespace App\Services\ModParsing;

use App\Enums\ManifestType;
use App\Services\ModParsing\Contracts\ManifestParser;
use App\Services\ModParsing\Data\ParseContext;
use App\Services\ModParsing\Data\ValidationReport;
use App\Services\ModParsing\Parsers\FabricModJsonParser;
use App\Services\ModParsing\Parsers\FarmingSimulatorModDescParser;
use App\Services\ModParsing\Parsers\ForgeMcmodInfoParser;
use Illuminate\Http\UploadedFile;

final class ModValidator
{
    /** @var list<ManifestParser> */
    private array $parsers;

    public function __construct(
        private readonly ArchiveInspector $archiveInspector = new ArchiveInspector(),
    ) {
        $this->parsers = [
            new FarmingSimulatorModDescParser(),
            new FabricModJsonParser(),
            new ForgeMcmodInfoParser(),
        ];
    }

    public function validateUploadedFile(UploadedFile $file): ValidationReport
    {
        $realPath = $file->getRealPath();
        $originalName = $file->getClientOriginalName() ?: $file->hashName();

        if (! is_string($realPath) || $realPath === '') {
            return (new ValidationReport())->error('UPLOAD_INVALID', 'Uploaded file is not accessible.');
        }

        return $this->validatePath($realPath, $originalName);
    }

    public function validatePath(string $filePath, ?string $originalFilename = null): ValidationReport
    {
        $originalFilename ??= basename($filePath);
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        if (in_array($extension, ['zip', 'jar'], true)) {
            $inspection = $this->archiveInspector->inspect($filePath, $originalFilename);

            if ($inspection['error_code'] !== null || $inspection['content'] === null || $inspection['manifest_filename'] === null) {
                $inferredType = $extension === 'jar' ? ManifestType::FabricJson : ManifestType::FsModDesc;

                return (new ValidationReport($inferredType))->error(
                    code: $inspection['error_code'] ?? 'ARCHIVE_ERROR',
                    message: $inspection['error_message'] ?? 'Failed to inspect archive.',
                );
            }

            return $this->validateRawContent(
                rawContent: $inspection['content'],
                manifestFilename: $inspection['manifest_filename'],
                context: $inspection['context'],
            );
        }

        $rawContent = @file_get_contents($filePath);
        if ($rawContent === false) {
            return (new ValidationReport())->error('FILE_READ_ERROR', 'Unable to read submitted file.');
        }

        return $this->validateRawContent(
            rawContent: $rawContent,
            manifestFilename: $originalFilename,
            context: new ParseContext(
                originalFilename: $originalFilename,
                manifestFilename: $originalFilename,
                fromArchive: false,
            ),
        );
    }

    public function validateRawContent(
        string $rawContent,
        string $manifestFilename,
        ?ParseContext $context = null,
    ): ValidationReport {
        $context ??= new ParseContext(
            originalFilename: $manifestFilename,
            manifestFilename: $manifestFilename,
            fromArchive: false,
        );

        foreach ($this->parsers as $parser) {
            if ($parser->supports($manifestFilename, $rawContent)) {
                return $parser->parse($rawContent, $context);
            }
        }

        if (str_ends_with(strtolower($manifestFilename), '.json')) {
            return (new FabricModJsonParser())->parse($rawContent, $context);
        }

        return (new ValidationReport())->error(
            code: 'UNSUPPORTED_MANIFEST',
            message: sprintf('Unsupported manifest format for "%s". Submit modDesc.xml, fabric.mod.json, or mcmod.info.', $manifestFilename),
        );
    }
}
