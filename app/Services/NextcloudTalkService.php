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
     * @param string $filePath The path to the file in Nextcloud
     * @param string $message Optional message to send with the file
     * @return bool Success status
     */
    public function shareFile(string $token, string $filePath, string $message = ''): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('Nextcloud Talk is not enabled or not properly configured');
            return false;
        }

        try {
            // First, share the file reference
            $response = $this->client->post("/ocs/v2.php/apps/spreed/api/v1/chat/{$token}/share", [
                'json' => [
                    'path' => $filePath,
                    'message' => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                Log::info('File successfully shared to Nextcloud Talk', [
                    'token' => $token,
                    'file_path' => $filePath,
                ]);
                return true;
            }

            Log::error('Failed to share file to Nextcloud Talk', [
                'status_code' => $statusCode,
                'token' => $token,
                'file_path' => $filePath,
            ]);
            return false;

        } catch (GuzzleException $e) {
            Log::error('Nextcloud Talk file share error: ' . $e->getMessage(), [
                'token' => $token,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
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
            // Ensure target directory exists
            $directory = dirname($targetPath);
            if ($directory !== '/' && $directory !== '.') {
                if (!$this->ensureDirectoryExists($directory)) {
                    Log::error('Failed to create target directory', ['directory' => $directory]);
                    return false;
                }
            }

            // Upload file to Nextcloud using WebDAV
            $webdavClient = new Client([
                'base_uri' => $this->baseUrl,
                'auth' => [$this->username, $this->password],
                'verify' => false,
            ]);

            $fileContent = file_get_contents($localFilePath);
            $response = $webdavClient->put("/remote.php/dav/files/{$this->username}{$targetPath}", [
                'body' => $fileContent,
            ]);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                Log::info('File uploaded to Nextcloud', ['path' => $targetPath]);

                // Now share the file in Talk
                return $this->shareFile($token, $targetPath, $message);
            }

            Log::error('Failed to upload file to Nextcloud', [
                'status_code' => $response->getStatusCode(),
                'target_path' => $targetPath,
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
            Log::error('Error reading local file: ' . $e->getMessage(), [
                'local_path' => $localFilePath,
            ]);
            return false;
        }
    }
}
