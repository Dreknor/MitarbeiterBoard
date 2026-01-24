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
            ]);

            // Check if directory exists using PROPFIND
            try {
                $response = $webdavClient->request('PROPFIND', "/remote.php/dav/files/{$this->username}{$directoryPath}", [
                    'headers' => [
                        'Depth' => '0',
                    ],
                ]);

                if ($response->getStatusCode() == 207) {
                    // Directory exists
                    Log::debug('Directory already exists', ['path' => $directoryPath]);
                    return true;
                }
            } catch (GuzzleException $e) {
                // Directory doesn't exist, create it
                if ($e->getCode() == 404 || strpos($e->getMessage(), '404') !== false) {
                    Log::info('Directory not found, creating it', ['path' => $directoryPath]);

                    // Create parent directories recursively if needed
                    $pathParts = array_filter(explode('/', $directoryPath));
                    $currentPath = '';

                    foreach ($pathParts as $part) {
                        $currentPath .= '/' . $part;

                        try {
                            $webdavClient->request('MKCOL', "/remote.php/dav/files/{$this->username}{$currentPath}");
                            Log::info('Created directory', ['path' => $currentPath]);
                        } catch (GuzzleException $mkcolException) {
                            // Ignore if directory already exists (405 Method Not Allowed)
                            if (strpos($mkcolException->getMessage(), '405') === false) {
                                throw $mkcolException;
                            }
                        }
                    }

                    return true;
                } else {
                    throw $e;
                }
            }

            return true;

        } catch (GuzzleException $e) {
            Log::error('Failed to ensure directory exists: ' . $e->getMessage(), [
                'directory_path' => $directoryPath,
                'error' => $e->getMessage(),
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
