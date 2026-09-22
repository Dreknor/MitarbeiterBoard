<?php

namespace App\Services\Personal\Contracts;

use App\Models\Group;

interface NextcloudFileServiceInterface
{
    /**
     * Stellt sicher, dass ein Verzeichnis existiert (erstellt es rekursiv).
     */
    public function ensureDirectoryExists(string $path): bool;

    /**
     * Lädt eine lokale Datei zu Nextcloud hoch.
     */
    public function uploadFile(string $localPath, string $remotePath): bool;

    /**
     * Lädt eine Datei von Nextcloud herunter. Gibt Inhalt als String zurück.
     */
    public function downloadFile(string $remotePath): string|false;

    /**
     * Löscht eine Datei in Nextcloud.
     */
    public function deleteFile(string $remotePath): bool;

    /**
     * Gibt den Inhalt eines Verzeichnisses zurück.
     */
    public function listDirectory(string $path): array;

    /**
     * Gibt Datei-Metadaten zurück (Name, Größe, Änderungsdatum, MimeType).
     */
    public function getFileInfo(string $path): ?array;

    /**
     * Gibt einen öffentlichen Share-Link zurück (oder null wenn nicht möglich).
     */
    public function getShareLink(string $path): ?string;

    /**
     * Verschiebt ein Verzeichnis (WebDAV MOVE).
     */
    public function moveDirectory(string $sourcePath, string $targetPath): bool;

    /**
     * Erstellt die komplette Gruppenordnerstruktur:
     * /Personal/{Gruppenname}/Angestellt/
     * /Personal/{Gruppenname}/Ausgeschieden/
     */
    public function createGroupFolderStructure(Group $group): bool;

    /**
     * Prüft ob eine Datei/ein Verzeichnis existiert.
     */
    public function exists(string $path): bool;
}

