<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

final class ZipFixture
{
    /**
     * Cria um ficheiro ZIP/JAR real num diretório temporário e devolve o caminho.
     *
     * @param  array<string, string>  $entries
     */
    public static function createArchive(array $entries, string $extension = 'zip'): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'forgemod_test_' . uniqid('', true) . '.' . $extension;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $path;
    }

    /**
     * Cria um UploadedFile de teste contendo um arquivo ZIP/JAR real.
     *
     * @param  array<string, string>  $entries
     */
    public static function uploadedArchive(
        string $clientFilename,
        array $entries,
        string $mimeType = 'application/zip',
    ): UploadedFile {
        $ext = pathinfo($clientFilename, PATHINFO_EXTENSION) ?: 'zip';
        $path = self::createArchive($entries, $ext);

        return new UploadedFile(
            path: $path,
            originalName: $clientFilename,
            mimeType: $mimeType,
            error: UPLOAD_ERR_OK,
            test: true,
        );
    }
}

