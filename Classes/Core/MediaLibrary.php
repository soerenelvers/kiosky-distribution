<?php

declare(strict_types=1);
namespace Kiosky\Core;

/** Files and media metadata are owned by the host CMS. */
interface MediaLibrary
{
    public function read(): array;
    public function saveFolder(array $folder): array;
    public function deleteFolder(string $id): void;
    public function saveAsset(array $asset): array;
    public function deleteAsset(string $id): void;
}
