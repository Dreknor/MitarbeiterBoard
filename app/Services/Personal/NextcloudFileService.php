<?php

namespace App\Services\Personal;

use App\Models\Group;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NextcloudFileService implements NextcloudFileServiceInterface
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $basePath;
    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl  = config('nextcloud.personal.url', '');
        $this->username = config('nextcloud.personal.username', '');
        $this->password = config('nextcloud.personal.password', '');
        $this->basePath = config('nextcloud.personal.base_path', '/Personal');
        $this->enabled  = config('nextcloud.personal.enabled', false);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    private function webdavUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/remote.php/dav/files/' . $this->username
            . '/' . ltrim($path, '/');
    }

    public function ensureDirectoryExists(string $path): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $path = $this->sanitizePath($path);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->send('MKCOL', $this->webdavUrl($path));

            // 405 = existiert bereits
            return $response->successful() || $response->status() === 405;
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: ensureDirectoryExists fehlgeschlagen', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function uploadFile(string $localPath, string $remotePath): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $remotePath = $this->sanitizePath($remotePath);

        try {
            $contents = file_get_contents($localPath);
            if ($contents === false) {
                return false;
            }

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withBody($contents, 'application/octet-stream')
                ->put($this->webdavUrl($remotePath));

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: uploadFile fehlgeschlagen', [
                'local'  => $localPath,
                'remote' => $remotePath,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function downloadFile(string $remotePath): string|false
    {
        if (! $this->enabled) {
            return false;
        }

        $remotePath = $this->sanitizePath($remotePath);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->get($this->webdavUrl($remotePath));

            return $response->successful() ? $response->body() : false;
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: downloadFile fehlgeschlagen', [
                'remote' => $remotePath,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function deleteFile(string $remotePath): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $remotePath = $this->sanitizePath($remotePath);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->delete($this->webdavUrl($remotePath));

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: deleteFile fehlgeschlagen', [
                'remote' => $remotePath,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function listDirectory(string $path): array
    {
        if (! $this->enabled) {
            return [];
        }

        $path = $this->sanitizePath($path);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->send('PROPFIND', $this->webdavUrl($path), [
                    'headers' => ['Depth' => '1'],
                ]);

            if (! $response->successful()) {
                return [];
            }

            // Einfaches XML-Parsing der WebDAV-Antwort
            $xml = simplexml_load_string($response->body());
            if ($xml === false) {
                return [];
            }

            $items = [];
            $xml->registerXPathNamespace('d', 'DAV:');
            foreach ($xml->xpath('//d:response') as $entry) {
                $href = (string) $entry->xpath('d:href')[0];
                $items[] = urldecode(basename($href));
            }

            return array_filter($items);
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: listDirectory fehlgeschlagen', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getFileInfo(string $path): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        $path = $this->sanitizePath($path);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->send('PROPFIND', $this->webdavUrl($path), [
                    'headers' => ['Depth' => '0'],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $xml = simplexml_load_string($response->body());
            if ($xml === false) {
                return null;
            }

            $xml->registerXPathNamespace('d', 'DAV:');
            $props = $xml->xpath('//d:prop')[0] ?? null;

            if (! $props) {
                return null;
            }

            return [
                'name'         => basename($path),
                'size'         => (int) ($props->xpath('d:getcontentlength')[0] ?? 0),
                'lastModified' => (string) ($props->xpath('d:getlastmodified')[0] ?? ''),
                'mimeType'     => (string) ($props->xpath('d:getcontenttype')[0] ?? ''),
            ];
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: getFileInfo fehlgeschlagen', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getShareLink(string $path): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        $path = $this->sanitizePath($path);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->post(rtrim($this->baseUrl, '/') . '/ocs/v2.php/apps/files_sharing/api/v1/shares', [
                    'path'        => $path,
                    'shareType'   => 3, // Public link
                    'permissions' => 1, // Read only
                ]);

            if ($response->successful()) {
                $data = $response->json('ocs.data');
                return $data['url'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: getShareLink fehlgeschlagen', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function moveDirectory(string $sourcePath, string $targetPath): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $sourcePath = $this->sanitizePath($sourcePath);
        $targetPath = $this->sanitizePath($targetPath);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Destination' => $this->webdavUrl($targetPath),
                    'Overwrite'   => 'F',
                ])
                ->send('MOVE', $this->webdavUrl($sourcePath));

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Nextcloud Personal: moveDirectory fehlgeschlagen', [
                'source' => $sourcePath,
                'target' => $targetPath,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function createGroupFolderStructure(Group $group): bool
    {
        $basePath = $this->basePath . '/' . self::sanitizeFilename($group->name);

        return $this->ensureDirectoryExists($basePath)
            && $this->ensureDirectoryExists($basePath . '/Angestellt')
            && $this->ensureDirectoryExists($basePath . '/Ausgeschieden');
    }

    public function exists(string $path): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $path = $this->sanitizePath($path);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->send('PROPFIND', $this->webdavUrl($path), [
                    'headers' => ['Depth' => '0'],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verhindert Path-Traversal-Angriffe.
     */
    private function sanitizePath(string $path): string
    {
        $parts = explode('/', $path);
        $safe  = array_filter($parts, fn ($p) => $p !== '' && $p !== '.' && $p !== '..');

        return '/' . implode('/', $safe);
    }

    /**
     * Bereinigt Dateinamen für Nextcloud-Pfade.
     */
    public static function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\-_\.]/', '_', $name);
    }
}

