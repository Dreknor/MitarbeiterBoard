<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class NextcloudTalkService
{
    protected $client;
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $enabled;

    public function __construct()
    {
        $this->baseUrl = config('nextcloud.url');
        $this->username = config('nextcloud.username');
        $this->password = config('nextcloud.password');
        $this->enabled = config('nextcloud.enabled', false);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'auth' => [$this->username, $this->password],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'verify' => false, // SSL-Verifikation deaktivieren (für Entwicklung)
        ]);
    }

    /**
     * Check if Nextcloud Talk is enabled and properly configured
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled &&
               !empty($this->baseUrl) &&
               !empty($this->username) &&
               !empty($this->password);
    }

    /**
     * Send a message to a Nextcloud Talk chat
     *
     * @param string $token The chat room token
     * @param string $message The message to send
     * @return bool Success status
     */
    public function sendMessage(string $token, string $message): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('Nextcloud Talk is not enabled or not properly configured');
            return false;
        }

        if (empty($token)) {
            Log::error('Nextcloud Talk: No chat token provided');
            return false;
        }

        try {
            $response = $this->client->post("/ocs/v2.php/apps/spreed/api/v1/chat/{$token}", [
                'json' => [
                    'message' => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                Log::info('Message successfully sent to Nextcloud Talk', [
                    'token' => $token,
                    'message_length' => strlen($message),
                ]);
                return true;
            }

            Log::error('Failed to send message to Nextcloud Talk', [
                'status_code' => $statusCode,
                'token' => $token,
            ]);
            return false;

        } catch (GuzzleException $e) {
            Log::error('Nextcloud Talk API error: ' . $e->getMessage(), [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Share a file to a Nextcloud Talk chat
     *
     * @param string $token The chat room token
     * @param string $filePath The path to the file in Nextcloud (e.g., '/Dienstpläne/plan.pdf')
     * @param string $message Optional message to send with the file
     * @return bool Success status
     */
    public function shareFile(string $token, string $filePath, string $message = ''): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('Nextcloud Talk is not enabled or not properly configured');
            return false;
        }

        Log::info('Sharing file to Nextcloud Talk', [
            'token' => $token,
            'file_path' => $filePath,
        ]);

        // Method 1: Try the official share API with file object
        $shareSuccess = $this->tryShareViaApi($token, $filePath, $message);

        if ($shareSuccess) {
            return true;
        }

        // Method 2: Fallback - send message with download link
        Log::info('Using fallback: Sending file link via message');
        return $this->shareFileAsLink($token, $filePath, $message);
    }

    /**
     * Try to share file via Nextcloud Talk Share API
     *
     * @param string $token Chat token
     * @param string $filePath File path
     * @param string $message Message
     * @return bool Success
     */
    protected function tryShareViaApi(string $token, string $filePath, string $message): bool
    {
        try {
            // Get file info first
            $fileId = $this->getFileId($filePath);

            if (!$fileId) {
                Log::debug('Could not get file ID, skipping API share attempt');
                return false;
            }

            Log::debug('Attempting to share via API', ['file_id' => $fileId]);

            // Try method 1: Share with referenceId
            $webdavClient = new Client([
                'base_uri' => $this->baseUrl,
                'auth' => [$this->username, $this->password],
                'headers' => [
                    'OCS-APIRequest' => 'true',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'verify' => false,
                'http_errors' => false,
            ]);

            // Try posting with just the path (simplest approach)
            $response = $webdavClient->post("/ocs/v2.php/apps/spreed/api/v1/chat/{$token}", [
                'json' => [
                    'message' => $message,
                    'replyTo' => 0,
                    'referenceId' => uniqid('file-', true),
                    'actorDisplayName' => $this->username,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                Log::info('Message sent successfully via API');

                // Now try to share the file object
                $shareResponse = $webdavClient->post("/ocs/v2.php/apps/files_sharing/api/v1/shares", [
                    'form_params' => [
                        'path' => $filePath,
                        'shareType' => 10, // TYPE_ROOM (Nextcloud Talk room)
                        'shareWith' => $token,
                    ],
                ]);

                if ($shareResponse->getStatusCode() >= 200 && $shareResponse->getStatusCode() < 300) {
                    Log::info('File shared successfully via API', ['file_path' => $filePath]);
                    return true;
                }

                Log::debug('File sharing failed, but message was sent', [
                    'share_status' => $shareResponse->getStatusCode(),
                ]);
            }

            return false;

        } catch (\Exception $e) {
            Log::debug('API share attempt failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file ID from Nextcloud for a given path
     *
     * @param string $filePath Path to file in Nextcloud
     * @return string|null File ID or null if not found
     */
    protected function getFileId(string $filePath): ?string
    {
        try {
            $webdavClient = new Client([
                'base_uri' => $this->baseUrl,
                'auth' => [$this->username, $this->password],
                'verify' => false,
                'http_errors' => false,
            ]);

            $normalizedPath = trim($filePath, '/');
            $response = $webdavClient->request('PROPFIND', "/remote.php/dav/files/{$this->username}/{$normalizedPath}", [
                'headers' => [
                    'Depth' => '0',
                ],
                'body' => '<?xml version="1.0"?>
                    <d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
                        <d:prop>
                            <oc:fileid />
                        </d:prop>
                    </d:propfind>',
            ]);

            if ($response->getStatusCode() == 207) {
                $body = $response->getBody()->getContents();

                // Parse XML response to get file ID
                if (preg_match('/<oc:fileid>(\d+)<\/oc:fileid>/', $body, $matches)) {
                    Log::debug('Found file ID', ['file_id' => $matches[1], 'path' => $filePath]);
                    return $matches[1];
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::debug('Error getting file ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Share a file by sending a message with download link (fallback method)
     *
     * @param string $token Chat token
     * @param string $filePath File path
     * @param string $message Optional message
     * @return bool Success status
     */
    protected function shareFileAsLink(string $token, string $filePath, string $message = ''): bool
    {
        try {
            // Normalize path
            $normalizedPath = trim($filePath, '/');

            // Create WebDAV download link
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $normalizedPath)));
            $downloadLink = "{$this->baseUrl}/remote.php/dav/files/{$this->username}/{$encodedPath}";

            // Get filename
            $filename = basename($filePath);

            // Construct message with file info and download link
            $fullMessage = $message . "\n\n\n⬇️ **Download:** {$downloadLink}";

            Log::debug('Sending file as link message', [
                'file_path' => $filePath,
                'download_link' => $downloadLink,
            ]);

            return $this->sendMessage($token, $fullMessage);

        } catch (\Exception $e) {
            Log::error('Error sharing file as link: ' . $e->getMessage(), [
                'file_path' => $filePath,
            ]);
            return false;
        }
    }

    /**
     * Create a directory in Nextcloud if it doesn't exist
     *
     * @param string $directoryPath Directory path in Nextcloud (e.g., '/Dienstpläne')
     * @return bool Success status
     */
    protected function ensureDirectoryExists(string $directoryPath): bool
    {
        try {
            $webdavClient = new Client([
                'base_uri' => $this->baseUrl,
                'auth' => [$this->username, $this->password],
                'verify' => false,
                'http_errors' => false, // Don't throw exceptions on HTTP errors
            ]);

            // Normalize path - remove leading/trailing slashes for consistency
            $directoryPath = trim($directoryPath, '/');

            // If empty, nothing to create
            if (empty($directoryPath)) {
                return true;
            }

            // First, verify user root exists
            $rootCheck = $webdavClient->request('PROPFIND', "/remote.php/dav/files/{$this->username}/", [
                'headers' => ['Depth' => '0'],
            ]);

            if ($rootCheck->getStatusCode() !== 207) {
                Log::error('User root directory not accessible', [
                    'username' => $this->username,
                    'status_code' => $rootCheck->getStatusCode(),
                ]);
                return false;
            }

            // Check if target directory already exists
            $response = $webdavClient->request('PROPFIND', "/remote.php/dav/files/{$this->username}/{$directoryPath}", [
                'headers' => ['Depth' => '0'],
            ]);

            if ($response->getStatusCode() == 207) {
                Log::debug('Directory already exists', ['path' => $directoryPath]);
                return true;
            }

            // Directory doesn't exist (404), create it recursively
            if ($response->getStatusCode() == 404) {
                Log::info('Directory not found, creating it recursively', ['path' => $directoryPath]);

                // Split path into parts and create each level
                $pathParts = explode('/', $directoryPath);
                $builtPath = '';

                foreach ($pathParts as $part) {
                    if (empty($part)) {
                        continue;
                    }

                    // Build cumulative path
                    $builtPath .= ($builtPath ? '/' : '') . $part;
                    $fullWebDavPath = "/remote.php/dav/files/{$this->username}/{$builtPath}";

                    // Check if this level exists
                    $checkResponse = $webdavClient->request('PROPFIND', $fullWebDavPath, [
                        'headers' => ['Depth' => '0'],
                    ]);

                    if ($checkResponse->getStatusCode() == 207) {
                        Log::debug('Path segment already exists', ['path' => $builtPath]);
                        continue;
                    }

                    // Doesn't exist, try to create it
                    if ($checkResponse->getStatusCode() == 404) {
                        $createResponse = $webdavClient->request('MKCOL', $fullWebDavPath);
                        $createStatus = $createResponse->getStatusCode();

                        if ($createStatus == 201) {
                            Log::info('Successfully created directory', ['path' => $builtPath]);
                        } elseif ($createStatus == 405) {
                            // 405 Method Not Allowed typically means it already exists
                            Log::debug('Directory already exists (405)', ['path' => $builtPath]);
                        } elseif ($createStatus == 409) {
                            // Conflict - parent doesn't exist (shouldn't happen with our approach)
                            Log::error('Conflict creating directory - parent missing', [
                                'path' => $builtPath,
                                'response' => $createResponse->getBody()->getContents(),
                            ]);
                            return false;
                        } else {
                            Log::error('Failed to create directory', [
                                'path' => $builtPath,
                                'status_code' => $createStatus,
                                'response' => $createResponse->getBody()->getContents(),
                            ]);
                            return false;
                        }
                    } else {
                        Log::error('Unexpected status when checking path segment', [
                            'path' => $builtPath,
                            'status_code' => $checkResponse->getStatusCode(),
                        ]);
                        return false;
                    }
                }

                // Verify final directory was created
                $finalCheck = $webdavClient->request('PROPFIND', "/remote.php/dav/files/{$this->username}/{$directoryPath}", [
                    'headers' => ['Depth' => '0'],
                ]);

                if ($finalCheck->getStatusCode() == 207) {
                    Log::info('Directory creation verified', ['path' => $directoryPath]);
                    return true;
                } else {
                    Log::error('Directory creation verification failed', [
                        'path' => $directoryPath,
                        'status_code' => $finalCheck->getStatusCode(),
                    ]);
                    return false;
                }
            }

            Log::error('Unexpected response when checking directory', [
                'path' => $directoryPath,
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getBody()->getContents(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Exception in ensureDirectoryExists', [
                'directory_path' => $directoryPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Upload a file to Nextcloud and share it in Talk
     *
     * @param string $token The chat room token
     * @param string $localFilePath Local file path
     * @param string $targetPath Target path in Nextcloud (e.g., '/Dienstpläne/plan.pdf')
     * @param string $message Optional message
     * @return bool Success status
     */
    public function uploadAndShare(string $token, string $localFilePath, string $targetPath, string $message = ''): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('Nextcloud Talk is not enabled or not properly configured');
            return false;
        }

        try {
            // Normalize target path (remove leading slash for internal use, keep it for sharing)
            $normalizedPath = trim($targetPath, '/');
            $shareablePath = '/' . $normalizedPath; // Path for sharing in Talk (with leading slash)

            // Extract directory from normalized path
            $directory = dirname($normalizedPath);

            // Ensure target directory exists (only if not root)
            if ($directory !== '.' && $directory !== '' && $directory !== '/') {
                Log::debug('Ensuring directory exists', ['directory' => $directory]);
                if (!$this->ensureDirectoryExists($directory)) {
                    Log::error('Failed to create target directory', ['directory' => $directory]);
                    return false;
                }
            }

            // Upload file to Nextcloud using WebDAV (use normalized path without leading slash)
            $webdavClient = new Client([
                'base_uri' => $this->baseUrl,
                'auth' => [$this->username, $this->password],
                'verify' => false,
            ]);

            if (!file_exists($localFilePath)) {
                Log::error('Local file does not exist', ['local_path' => $localFilePath]);
                return false;
            }

            $fileContent = file_get_contents($localFilePath);
            $uploadPath = "/remote.php/dav/files/{$this->username}/{$normalizedPath}";

            Log::debug('Uploading file to Nextcloud', [
                'local_path' => $localFilePath,
                'upload_path' => $uploadPath,
                'file_size' => strlen($fileContent),
            ]);

            $response = $webdavClient->put($uploadPath, [
                'body' => $fileContent,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                Log::info('File uploaded to Nextcloud successfully', [
                    'path' => $normalizedPath,
                    'status_code' => $statusCode,
                ]);

                // Share the file in Talk (use path with leading slash)
                return $this->shareFile($token, $shareablePath, $message);
            }

            Log::error('Failed to upload file to Nextcloud', [
                'status_code' => $statusCode,
                'target_path' => $normalizedPath,
                'upload_path' => $uploadPath,
            ]);
            return false;

        } catch (GuzzleException $e) {
            Log::error('Nextcloud file upload error: ' . $e->getMessage(), [
                'local_path' => $localFilePath,
                'target_path' => $targetPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Error in uploadAndShare: ' . $e->getMessage(), [
                'local_path' => $localFilePath,
                'target_path' => $targetPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
