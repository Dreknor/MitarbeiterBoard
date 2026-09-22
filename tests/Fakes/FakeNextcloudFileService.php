<?php

namespace Tests\Fakes;

use App\Models\Group;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use PHPUnit\Framework\Assert;

/**
 * Fake-Implementierung des NextcloudFileServiceInterface für Tests.
 * Speichert alle Operationen in einem internen Log für Assertions.
 */
class FakeNextcloudFileService implements NextcloudFileServiceInterface
{
    private array $operations = [];
    private array $existingPaths = [];
    private array $fileContents = [];

    public function ensureDirectoryExists(string $path): bool
    {
        $this->operations[] = ['method' => 'ensureDirectoryExists', 'path' => $path];
        $this->existingPaths[] = $path;

        return true;
    }

    public function uploadFile(string $localPath, string $remotePath): bool
    {
        $this->operations[] = ['method' => 'uploadFile', 'local' => $localPath, 'remote' => $remotePath];
        $this->existingPaths[] = $remotePath;

        return true;
    }

    public function downloadFile(string $remotePath): string|false
    {
        $this->operations[] = ['method' => 'downloadFile', 'remote' => $remotePath];

        return $this->fileContents[$remotePath] ?? false;
    }

    public function deleteFile(string $remotePath): bool
    {
        $this->operations[] = ['method' => 'deleteFile', 'remote' => $remotePath];
        $this->existingPaths = array_filter($this->existingPaths, fn ($p) => $p !== $remotePath);

        return true;
    }

    public function listDirectory(string $path): array
    {
        $this->operations[] = ['method' => 'listDirectory', 'path' => $path];

        return array_values(array_filter($this->existingPaths, function ($p) use ($path) {
            return str_starts_with($p, $path) && $p !== $path;
        }));
    }

    public function getFileInfo(string $path): ?array
    {
        $this->operations[] = ['method' => 'getFileInfo', 'path' => $path];

        if (in_array($path, $this->existingPaths)) {
            return [
                'name'         => basename($path),
                'size'         => 1024,
                'lastModified' => now()->toRfc7231String(),
                'mimeType'     => 'application/octet-stream',
            ];
        }

        return null;
    }

    public function getShareLink(string $path): ?string
    {
        $this->operations[] = ['method' => 'getShareLink', 'path' => $path];

        return 'https://fake-nextcloud.test/s/' . md5($path);
    }

    public function moveDirectory(string $sourcePath, string $targetPath): bool
    {
        $this->operations[] = ['method' => 'moveDirectory', 'source' => $sourcePath, 'target' => $targetPath];

        // Pfade umschreiben
        $this->existingPaths = array_map(function ($p) use ($sourcePath, $targetPath) {
            if (str_starts_with($p, $sourcePath)) {
                return str_replace($sourcePath, $targetPath, $p);
            }

            return $p;
        }, $this->existingPaths);

        return true;
    }

    public function createGroupFolderStructure(Group $group): bool
    {
        $this->operations[] = ['method' => 'createGroupFolderStructure', 'group' => $group->name];
        $basePath = '/Personal/' . $group->name;
        $this->existingPaths[] = $basePath;
        $this->existingPaths[] = $basePath . '/Angestellt';
        $this->existingPaths[] = $basePath . '/Ausgeschieden';

        return true;
    }

    public function exists(string $path): bool
    {
        $this->operations[] = ['method' => 'exists', 'path' => $path];

        return in_array($path, $this->existingPaths);
    }

    // --- Assertion-Helfer ---

    public function assertUploaded(string $remotePath): void
    {
        $uploaded = array_filter($this->operations, fn ($op) => $op['method'] === 'uploadFile' && $op['remote'] === $remotePath
        );
        Assert::assertNotEmpty($uploaded, "Erwartet Upload nach: {$remotePath}");
    }

    public function assertDirectoryCreated(string $path): void
    {
        $created = array_filter($this->operations, fn ($op) => $op['method'] === 'ensureDirectoryExists' && $op['path'] === $path
        );
        Assert::assertNotEmpty($created, "Erwartet Verzeichniserstellung: {$path}");
    }

    public function assertMoved(string $source, string $target): void
    {
        $moved = array_filter($this->operations, fn ($op) => $op['method'] === 'moveDirectory'
            && $op['source'] === $source
            && $op['target'] === $target
        );
        Assert::assertNotEmpty($moved, "Erwartet Verschiebung von {$source} nach {$target}");
    }

    public function assertDeleted(string $remotePath): void
    {
        $deleted = array_filter($this->operations, fn ($op) => $op['method'] === 'deleteFile' && $op['remote'] === $remotePath
        );
        Assert::assertNotEmpty($deleted, "Erwartet Löschung: {$remotePath}");
    }

    public function getOperations(): array
    {
        return $this->operations;
    }

    public function reset(): void
    {
        $this->operations = [];
        $this->existingPaths = [];
        $this->fileContents = [];
    }

    /**
     * Simuliert Dateiinhalt für downloadFile().
     */
    public function seedFileContent(string $remotePath, string $content): void
    {
        $this->fileContents[$remotePath] = $content;
        $this->existingPaths[] = $remotePath;
    }
}

