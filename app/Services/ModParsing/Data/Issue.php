<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Data;

use App\Enums\IssueSeverity;

final readonly class Issue
{
    public function __construct(
        public IssueSeverity $severity,
        public string $code,
        public string $message,
        public ?int $line = null,
        public ?int $column = null,
        public ?string $context = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'code' => $this->code,
            'message' => $this->message,
            'line' => $this->line,
            'column' => $this->column,
            'context' => $this->context,
        ];
    }
}

