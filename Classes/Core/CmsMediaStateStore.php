<?php

declare(strict_types=1);
namespace Kiosky\Core;

use RuntimeException;

/** Projects native CMS media into the shared API; never persists a parallel media catalogue. */
final class CmsMediaStateStore implements StateStore
{
    private array $snapshot = ['folders'=>[], 'assets'=>[]];
    private array $references = [];
    private array $legacyMedia = [];

    public function __construct(private readonly StateStore $store, private readonly MediaLibrary $media, private readonly bool $migrate = true, private readonly bool $hydrateMedia = true) {}

    public function load(): array
    {
        $this->references = [];
        $state = $this->store->load();
        $this->legacyMedia = array_intersect_key($state, array_flip(['mediaFolders','mediaAssets']));
        // Machine control requests need stored content IDs, not a full CMS file inventory.
        if (!$this->hydrateMedia) return $state;
        $this->snapshot = $this->media->read();
        $legacyFolders = $state['mediaFolders'] ?? [];
        $legacyAssets = $state['mediaAssets'] ?? [];
        $state['mediaFolders'] = $this->merge($this->snapshot['folders'], $legacyFolders);
        $state['mediaAssets'] = $this->merge($this->snapshot['assets'], $legacyAssets);
        if ($this->migrate && ($legacyFolders || $legacyAssets)) {
            $this->persist($state);
            $this->snapshot = $this->media->read();
            $state['mediaFolders'] = $this->snapshot['folders'];
            $state['mediaAssets'] = $this->snapshot['assets'];
        }
        return $this->hydrate($state, array_column($state['mediaAssets'], null, 'id'));
    }

    private function hydrate(mixed $value, array $assets): mixed
    {
        if (!is_array($value)) return $value;
        foreach ($value as &$item) $item = $this->hydrate($item, $assets);
        unset($item);
        $asset = $assets[$value['mediaAssetId'] ?? ''] ?? null;
        if ($asset && empty($asset['deletedAt'])) {
            $value[array_key_exists('imageSrc',$value) ? 'imageSrc' : 'src'] = $asset['src'];
            foreach (['trimStart','trimEnd'] as $key) if (isset($asset['metadata'][$key])) $value[$key] = $asset['metadata'][$key];
        }
        return $value;
    }

    private function merge(array $native, array $legacy): array
    {
        $ids = array_column($native, 'id');
        return array_merge($native, array_values(array_filter($legacy, static fn(array $row): bool => !in_array($row['id'], $ids, true))));
    }

    public function save(array $state): void
    {
        if (!$this->migrate) {
            // Player heartbeats and viewer actions must never initiate a migration.
            unset($state['mediaFolders'], $state['mediaAssets']);
            $this->store->save(array_replace($state, $this->legacyMedia));
            return;
        }
        $this->persist($state);
    }

    private function persist(array &$state): void
    {
        $folders = array_column($this->snapshot['folders'], null, 'id');
        $assets = array_column($this->snapshot['assets'], null, 'id');
        $removedFolders = array_column(array_filter($state['mediaFolders'] ?? [], static fn(array $row): bool => !empty($row['deletedAt'])), null, 'id');
        $state['mediaFolders'] = array_values(array_filter($state['mediaFolders'] ?? [], static fn(array $row): bool => empty($row['deletedAt'])));
        foreach ($state['mediaFolders'] as &$folder) {
            if (isset($removedFolders[$folder['parentId'] ?? ''])) $folder['parentId'] = $removedFolders[$folder['parentId']]['parentId'] ?? null;
        }
        unset($folder);
        foreach ($state['mediaAssets'] ?? [] as $key => $asset) {
            if (isset($removedFolders[$asset['folderId'] ?? ''])) $state['mediaAssets'][$key]['folderId'] = $removedFolders[$asset['folderId']]['parentId'] ?? null;
        }
        $pending = $state['mediaFolders'];
        $done = [];
        while ($pending) {
            $progress = false;
            foreach ($pending as $key => $folder) {
                $parent = $folder['parentId'] ?? null;
                if ($parent && !isset($folders[$parent]) && !isset($done[$parent])) continue;
                $oldId = $folder['id'];
                $folder = $this->resolveReferences($folder);
                if (!isset($folders[$oldId]) || $folder !== $folders[$oldId]) {
                    $saved = $this->media->saveFolder($folder);
                    $this->references[$oldId] = $saved['id'];
                }
                $done[$oldId] = true;
                unset($pending[$key]);
                $progress = true;
            }
            if (!$progress) throw new RuntimeException('Die Medienordner enthalten ungültige Elternverweise.', 422);
        }
        // Folder operations may change public URLs without changing file identities.
        foreach ($this->media->read()['assets'] as $native) {
            $previous = $assets[$native['id']] ?? null;
            if ($previous && $previous['src'] !== $native['src']) $this->references[$previous['src']] = $native['src'];
        }
        foreach ($state['mediaAssets'] ?? [] as $asset) {
            $oldId = $asset['id'];
            if (isset($assets[$oldId]) && $asset === $assets[$oldId]) continue;
            $oldSrc = (string)($asset['src'] ?? '');
            $resolved = $this->resolveReferences($asset);
            if (str_starts_with($oldSrc,'data:')) $resolved['src'] = $oldSrc;
            $saved = $this->media->saveAsset($resolved);
            $this->references[$oldId] = $saved['id'];
            if (isset($assets[$oldId]) && $assets[$oldId]['src'] !== $saved['src']) $this->references[$assets[$oldId]['src']] = $saved['src'];
            if ($oldSrc !== '' && $oldSrc !== $saved['src']) $this->references[$oldSrc] = $saved['src'];
        }
        foreach ($assets as $id => $asset) {
            if (!in_array($id, array_column($state['mediaAssets'] ?? [], 'id'), true)) $this->media->deleteAsset($id);
        }
        foreach ($folders as $id => $folder) {
            if (!in_array($id, array_column($state['mediaFolders'] ?? [], 'id'), true)) $this->media->deleteFolder($id);
        }
        $state = $this->resolveReferences($state);
        $stored = $state;
        unset($stored['mediaAssets'], $stored['mediaFolders']);
        $this->store->save($stored);
    }

    public function resolveReferences(mixed $value): mixed
    {
        if (is_string($value)) {
            if (isset($this->references[$value])) return $this->references[$value];
            if (str_starts_with($value, 'fal-folder:')) {
                foreach ($this->references as $old => $new) if ($old !== $new && str_starts_with($old, 'fal-folder:') && str_starts_with($value, $old)) return $new . substr($value, strlen($old));
            }
            return $value;
        }
        if (is_array($value)) foreach ($value as &$item) $item = $this->resolveReferences($item);
        return $value;
    }
}
