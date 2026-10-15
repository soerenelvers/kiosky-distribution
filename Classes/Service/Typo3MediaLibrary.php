<?php

declare(strict_types=1);
namespace Kiosky\Kiosky\Service;

use Kiosky\Core\MediaLibrary;
use Kiosky\Core\MediaUpload;
use RuntimeException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;

final readonly class Typo3MediaLibrary implements MediaLibrary
{
    public function __construct(private ResourceFactory $factory, private StorageRepository $storages, private MetaDataRepository $metadata) {}

    public function read(): array
    {
        $result = ['folders'=>[], 'assets'=>[]];
        $storages = isset($GLOBALS['BE_USER']) && !empty($GLOBALS['BE_USER']->user['uid'])
            ? $GLOBALS['BE_USER']->getFileStorages() : $this->storages->findAll();
        foreach ($storages as $storage) {
            if (!$storage->isOnline() || !$storage->isPublic()) continue;
            $roots = array_column($storage->getFileMounts(), 'folder') ?: [$storage->getRootLevelFolder(true)];
            foreach ($roots as $root) {
                if (in_array($this->folderId($root), array_column($result['folders'], 'id'), true)) continue;
                $result['folders'][] = ['id'=>$this->folderId($root),'name'=>$storage->getName().($root->getName()?' / '.$root->getName():''),'parentId'=>null,'system'=>true];
                $this->walk($root,$result);
            }
        }
        return $result;
    }

    private function walk(Folder $folder, array &$result): void
    {
        foreach ($folder->getFiles() as $file) {
            if ($file->getPublicUrl() && $file->checkActionPermission('read')) $result['assets'][] = $this->asset($file);
        }
        foreach ($folder->getSubfolders() as $child) {
            $result['folders'][] = ['id'=>$this->folderId($child),'name'=>$child->getName(),'parentId'=>$this->folderId($folder)];
            $this->walk($child,$result);
        }
    }

    private function folderId(Folder $folder): string { return 'fal-folder:'.$folder->getCombinedIdentifier(); }
    private function folder(string $id): Folder { return $this->factory->getFolderObjectFromCombinedIdentifier(substr($id,11)); }

    private function asset(File $file): array
    {
        $native = $this->metadata->findByFileUid($file->getUid());
        $data = json_decode((string)($native['tx_kiosky_data']??''),true) ?: [];
        $mime = $file->getMimeType();
        return ['id'=>'fal:'.$file->getUid(),'name'=>($native['title']??'')?:$file->getName(),'src'=>(string)$file->getPublicUrl(),'type'=>str_starts_with($mime,'video/')?'video':($mime==='image/svg+xml'?'icon':(str_starts_with($mime,'image/')?'image':'file')),'folderId'=>$this->folderId($file->getParentFolder()),'metadata'=>array_replace($data,['cms'=>'typo3','cmsId'=>$file->getUid(),'mimeType'=>$mime,'size'=>$file->getSize(),'width'=>$native['width']??null,'height'=>$native['height']??null]),'deletedAt'=>$data['deletedAt']??null];
    }

    public function exportAssetData(array $asset): string
    {
        if (!str_starts_with((string)$asset['id'],'fal:')) throw new RuntimeException('Ungültiges CMS-Medium.',422);
        $file = $this->factory->getFileObject((int)substr($asset['id'],4));
        if (!$file->checkActionPermission('read')) throw new RuntimeException('Keine Leseberechtigung für dieses TYPO3-Medium.',403);
        if ($file->getSize()>20000000) throw new RuntimeException('Die Mediendatei ist größer als 20 MB.',422);
        return 'data:'.$file->getMimeType().';base64,'.base64_encode($file->getContents());
    }

    public function saveFolder(array $folder): array
    {
        if (str_starts_with($folder['id'],'fal-folder:')) {
            $native = $this->folder($folder['id']);
            if (empty($folder['system']) && $native->getName() !== $folder['name']) $native = $native->rename($folder['name']);
        } else {
            $parent = !empty($folder['parentId']) ? $this->folder($folder['parentId']) : $this->defaultFolder();
            $storage = $parent->getStorage();
            $name = $storage->sanitizeFileName((string)$folder['name']);
            $native = $storage->hasFolderInFolder($name,$parent) ? $storage->getFolderInFolder($name,$parent) : $storage->createFolder($name,$parent);
        }
        return ['id'=>$this->folderId($native),'name'=>$folder['name'],'parentId'=>$folder['parentId']??null];
    }

    private function defaultFolder(): Folder
    {
        $storages = isset($GLOBALS['BE_USER']) && !empty($GLOBALS['BE_USER']->user['uid']) ? $GLOBALS['BE_USER']->getFileStorages() : $this->storages->findAll();
        foreach ($storages as $storage) {
            if (!$storage->isOnline() || !$storage->isPublic() || !$storage->isWritable()) continue;
            $mounts = array_column($storage->getFileMounts(), 'folder');
            foreach ($mounts ?: [$storage->getDefaultFolder()] as $folder) {
                if ($storage->checkFolderActionPermission('add', $folder)) return $folder;
            }
        }
        throw new RuntimeException('Kein beschreibbarer öffentlicher TYPO3-Dateispeicher verfügbar.',422);
    }

    public function deleteFolder(string $id): void
    {
        $folder = $this->folder($id);
        if ($folder->getIdentifier()==='/') throw new RuntimeException('Ein CMS-Dateispeicher kann hier nicht gelöscht werden.',422);
        $parent = $folder->getParentFolder();
        foreach ($folder->getFiles() as $file) $file->moveTo($parent);
        foreach ($folder->getSubfolders() as $child) $child->moveTo($parent);
        $folder->delete();
    }

    public function saveAsset(array $asset): array
    {
        $file = str_starts_with($asset['id'],'fal:') ? $this->factory->getFileObject((int)substr($asset['id'],4)) : null;
        if (!$file) {
            foreach ($this->read()['assets'] as $existing) {
                if (($existing['metadata']['legacyId']??'')===$asset['id'] || $existing['src']===($asset['src']??'')) { $file=$this->factory->getFileObject((int)substr($existing['id'],4)); break; }
            }
        }
        $src = (string)($asset['src']??'');
        if ($file && !$file->checkActionPermission('write')) throw new RuntimeException('Keine Schreibberechtigung für diese TYPO3-Datei.',403);
        if (!$file || $src !== (string)$file->getPublicUrl()) {
            if (!str_starts_with($src,'data:')) {
                MediaUpload::assertPublicUrl($src);
                $response = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Http\RequestFactory::class)->request($src, 'GET', ['timeout'=>30,'allow_redirects'=>false,'http_errors'=>false,'stream'=>true]);
                $bytes = $response->getBody()->read(20_000_001);
                if ($response->getStatusCode() !== 200 || strlen($bytes)>20_000_000) throw new RuntimeException('Das bisherige Medium konnte nicht importiert werden.',422);
                $asset['src']='data:application/octet-stream;base64,'.base64_encode($bytes);
            }
            $upload = MediaUpload::decode($asset);
            if ($file) $file->setContents($upload['bytes']);
            else {
                $folder = !empty($asset['folderId']) ? $this->folder($asset['folderId']) : $this->defaultFolder();
                $temp = tempnam(sys_get_temp_dir(),'kiosky-media-');
                if ($temp===false) throw new RuntimeException('Upload konnte nicht vorbereitet werden.',500);
                try {
                    file_put_contents($temp,$upload['bytes']);
                    $file = $folder->getStorage()->addFile($temp,$folder,$upload['name']);
                } finally { if (is_file($temp)) unlink($temp); }
            }
        }
        if (empty($asset['folderId']) && $file->getParentFolder()->getIdentifier() !== $this->defaultFolder()->getIdentifier()) $file=$file->moveTo($this->defaultFolder());
        if (!empty($asset['folderId']) && $this->folderId($file->getParentFolder())!==$asset['folderId']) $file=$file->moveTo($this->folder($asset['folderId']));
        if (!empty($asset['deletedAt']) && !$file->checkActionPermission('delete')) throw new RuntimeException('Keine Löschberechtigung für diese TYPO3-Datei.',403);
        $data = array_replace($asset['metadata']??[],['legacyId'=>$asset['id'],'deletedAt'=>$asset['deletedAt']??null]);
        $this->metadata->update($file->getUid(),['title'=>$asset['name'],'tx_kiosky_data'=>json_encode($data,JSON_THROW_ON_ERROR)]);
        return $this->asset($file);
    }

    public function deleteAsset(string $id): void
    {
        $file=$this->factory->getFileObject((int)substr($id,4));
        if (!$file->checkActionPermission('delete')) throw new RuntimeException('Keine Löschberechtigung für diese TYPO3-Datei.',403);
        $file->delete();
    }
}
