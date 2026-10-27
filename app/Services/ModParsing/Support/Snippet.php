<?php

declare(strict_types=1);

namespace App\Services\ModParsing\Support;

final class Snippet
{
    public static function around(string $rawContent, ?int $targetLine, int $radius = 2): ?string
    {
        if ($targetLine === null || $targetLine < 1 || trim($rawContent) === '') {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $rawContent);
        $lines = explode("\n", $normalized);
        $totalLines = count($lines);

        $targetLine = min($targetLine, $totalLines);
        $start = max(1, $targetLine - $radius);
        $end = min($totalLines, $targetLine + $radius);

        $output = [];
        for ($i = $start; $i <= $end; $i++) {
            $marker = ($i === $targetLine) ? '>> ' : '   ';
            $lineContent = mb_substr($lines[$i - 1] ?? '', 0, 160);
            $output[] = sprintf('%s%4d | %s', $marker, $i, $lineContent);
        }

        return implode("\n", $output);
    }
}

