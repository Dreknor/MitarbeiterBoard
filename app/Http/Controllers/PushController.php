<?php

namespace App\Http\Controllers;

use App\Notifications\PushTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verwaltet die Push-Abos (Geräte) der angemeldeten Person.
 * Aktiviert wird Push ausschließlich über die Benachrichtigungs-Einstellungen.
 */
class PushController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Push-Abo dieses Geräts speichern.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'endpoint'    => 'required|url|max:500',
            'keys.auth'   => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $request->user()->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth')
        );

        return response()->json(['success' => true]);
    }

    /**
     * Push-Abo dieses Geräts entfernen.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->validate($request, ['endpoint' => 'required|string']);

        $request->user()->deletePushSubscription($request->input('endpoint'));

        return response()->json(['success' => true]);
    }

    /**
     * Test-Push an die eigenen Geräte.
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->pushSubscriptions()->exists()) {
            return response()->json(['success' => false, 'message' => 'Auf keinem Gerät ist Push aktiviert.'], 422);
        }

        $user->notifyNow(new PushTest());

        return response()->json(['success' => true]);
    }
}
