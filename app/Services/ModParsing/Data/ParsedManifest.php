<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Data;

final readonly class ParsedManifest
{
    /**
     * @param  list<string>  $compatibleGameVersions
     * @param  list<string>  $suggestedTags
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $identifier,
        public string $name,
        public string $version,
        public string $author,
        public ?string $description = null,
        public array $compatibleGameVersions = [],
        public array $suggestedTags = [],
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'version' => $this->version,
            'author' => $this->author,
            'description' => $this->description,
            'compatible_game_versions' => $this->compatibleGameVersions,
            'suggested_tags' => $this->suggestedTags,
            'extra' => $this->extra,
        ];
    }
}

