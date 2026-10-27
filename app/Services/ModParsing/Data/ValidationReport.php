<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Data;

use App\Enums\IssueSeverity;
use App\Enums\ManifestType;
use App\Enums\ModStatus;

final class ValidationReport
{
    /** @var list<Issue> */
    private array $issues = [];

    private ?ParsedManifest $manifest = null;

    public function __construct(
        public readonly ?ManifestType $manifestType = null,
    ) {}

    public function error(
        string $code,
        string $message,
        ?int $line = null,
        ?int $column = null,
        ?string $context = null,
    ): self {
        $this->issues[] = new Issue(
            severity: IssueSeverity::Error,
            code: $code,
            message: $message,
            line: $line,
            column: $column,
            context: $context,
        );

        return $this;
    }

    public function warning(
        string $code,
        string $message,
        ?int $line = null,
        ?int $column = null,
        ?string $context = null,
    ): self {
        $this->issues[] = new Issue(
            severity: IssueSeverity::Warning,
            code: $code,
            message: $message,
            line: $line,
            column: $column,
            context: $context,
        );

        return $this;
    }

    public function withManifest(ParsedManifest $manifest): self
    {
        $this->manifest = $manifest;

        return $this;
    }

    public function manifest(): ?ParsedManifest
    {
        return $this->manifest;
    }

    /**
     * @return list<Issue>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * @return list<Issue>
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->issues,
            fn (Issue $issue) => $issue->severity === IssueSeverity::Error
        ));
    }

    /**
     * @return list<Issue>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->issues,
            fn (Issue $issue) => $issue->severity === IssueSeverity::Warning
        ));
    }

    public function hasErrors(): bool
    {
        return count($this->errors()) > 0;
    }

    public function status(): ModStatus
    {
        if ($this->hasErrors() || $this->manifest === null) {
            return ModStatus::Invalid;
        }

        if (count($this->warnings()) > 0) {
            return ModStatus::Warning;
        }

        return ModStatus::Validated;
    }

    public function isValid(): bool
    {
        return $this->status() !== ModStatus::Invalid;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $status = $this->status();

        return [
            'valid' => $this->isValid(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'badge_class' => $status->badgeClass(),
            'manifest_type' => $this->manifestType?->value,
            'manifest_label' => $this->manifestType?->label(),
            'game_slug' => $this->manifestType?->gameSlug(),
            'manifest' => $this->manifest?->toArray(),
            'counts' => [
                'errors' => count($this->errors()),
                'warnings' => count($this->warnings()),
            ],
            'issues' => array_map(fn (Issue $i) => $i->toArray(), $this->issues),
        ];
    }
}

