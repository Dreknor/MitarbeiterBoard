<?php

namespace App\Exceptions;

/**
 * Wird geworfen wenn der CalDAV-Server einen sync-token als ungültig/abgelaufen ablehnt.
 * (RFC 6578 DAV:valid-sync-token Precondition – HTTP 403)
 *
 * Unterschied zu SyncTokenNotSupportedException (HTTP 501):
 * - SyncTokenNotSupportedException: Server unterstützt sync-token grundsätzlich NICHT
 * - SyncTokenExpiredException:      Server unterstützt sync-token, aber der konkrete Token ist abgelaufen
 *
 * In beiden Fällen muss auf Full-Sync (ctag/etag) zurückgefallen werden.
 * Der gespeicherte sync_token muss gelöscht werden.
 */
class SyncTokenExpiredException extends SyncTokenNotSupportedException
{
}

