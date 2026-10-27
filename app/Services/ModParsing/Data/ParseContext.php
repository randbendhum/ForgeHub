<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Data;

final readonly class ParseContext
{
    /**
     * @param  list<string>  $archiveEntries
     */
    public function __construct(
        public string $originalFilename,
        public string $manifestFilename,
        public bool $fromArchive = false,
        public array $archiveEntries = [],
        public ?string $jarVersion = null,
    ) {}

    public function hasArchiveEntry(string $relativePath): bool
    {
        $normalized = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
        if ($normalized === '') {
            return false;
        }

        foreach ($this->archiveEntries as $entry) {
            if (strcasecmp(ltrim(str_replace('\\', '/', $entry), '/'), $normalized) === 0) {
                return true;
            }
        }

        // Farming Simulator converte frequentemente .png para .dds no pacote final
        if (str_ends_with(strtolower($normalized), '.png')) {
            $ddsVariant = substr($normalized, 0, -4) . '.dds';
            foreach ($this->archiveEntries as $entry) {
                if (strcasecmp(ltrim(str_replace('\\', '/', $entry), '/'), $ddsVariant) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function archiveBaseName(): string
    {
        return pathinfo($this->originalFilename, PATHINFO_FILENAME);
    }
}

