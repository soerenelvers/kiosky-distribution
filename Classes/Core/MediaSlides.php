<?php

declare(strict_types=1);

namespace Kiosky\Core;

final class MediaSlides
{
    /** Validate the entire selection before any drafts are written. */
    public static function inputs(mixed $ids, array $assets): array
    {
        if (!is_array($ids) || !array_is_list($ids) || count($ids) < 1 || count($ids) > 100) {
            throw new \RuntimeException('Bitte 1 bis 100 Bilder oder Videos auswählen.', 422);
        }
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '' || strlen($id) > 200) throw new \RuntimeException('Bitte 1 bis 100 Bilder oder Videos auswählen.', 422);
        }
        $byId = [];
        foreach ($assets as $asset) if (empty($asset['deletedAt'])) $byId[(string)$asset['id']] = $asset;
        $inputs = [];
        foreach (array_unique($ids) as $id) {
            $asset = $byId[$id] ?? null;
            if (!$asset || !in_array($asset['type'] ?? '', ['image', 'icon', 'video'], true) || !is_string($asset['src'] ?? null) || $asset['src'] === '') {
                throw new \RuntimeException('Mindestens ein Medium fehlt, ist gelöscht oder kein Bild/Video.', 422);
            }
            $metadata = is_array($asset['metadata'] ?? null) ? $asset['metadata'] : [];
            $type = $asset['type'] === 'video' ? 'video' : 'image';
            $name = '';
            foreach (preg_split('//u', trim((string)($asset['name'] ?? 'Medium')) ?: 'Medium', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                if (strlen($name . $character) > 100) break;
                $name .= $character;
            }
            $portrait = (float)($metadata['height'] ?? 0) > (float)($metadata['width'] ?? 0);
            $element = ['id'=>'media-content', 'type'=>$type, 'mediaAssetId'=>$id, 'src'=>$asset['src'], 'x'=>0, 'y'=>0, 'width'=>100, 'height'=>100, 'mediaFit'=>'contain', 'background'=>'transparent', 'opacity'=>100, 'rotation'=>0, 'padding'=>0];
            if ($type === 'video') {
                $element['muted'] = true;
                $element['trimStart'] = (float)($metadata['trimStart'] ?? 0);
                if (isset($metadata['trimEnd'])) $element['trimEnd'] = (float)$metadata['trimEnd'];
            }
            $inputs[] = [
                'name'=>$name ?: 'Medium', 'type'=>$type, 'orientation'=>'auto', 'status'=>'draft',
                'width'=>$portrait ? 1080 : 1920, 'height'=>$portrait ? 1920 : 1080,
                'document'=>[
                    'designOrientation'=>$portrait ? 'portrait' : 'landscape',
                    'settings'=>['background'=>['mode'=>'solid', 'color'=>'#000000']],
                    'elements'=>[$element],
                ],
            ];
        }
        return $inputs;
    }
}
