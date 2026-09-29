<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\checkTimeRecordingPinRequest;
use App\Http\Requests\getTimeRecordingKeyRequest;
use App\Http\Requests\storeSecretKeyRequest;
use App\Models\personal\EmployeData;
use App\Services\Personal\Zeit\TimeRecordingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Zeiterfassung: Terminal (Chip + PIN) und Kommen/Gehen aus dem Dashboard.
 *
 * Der gescannte Chip wird ausschließlich in der Session des Terminals gehalten
 * (2 Minuten gültig) – nie global. PIN-Fehlversuche sperren den Chip für 15 Minuten.
 */
class TimeRecordingController extends Controller
{
    private const SESSION_KEY = 'time_recording.employe_data_id';
    private const SESSION_EXPIRES = 'time_recording.expires_at';
    private const MAX_PIN_ATTEMPTS = 5;

    public function __construct(private readonly TimeRecordingService $recording)
    {
    }

    /**
     * Kommen/Gehen für den angemeldeten Benutzer (Dashboard).
     */
    public function checkin_checkout(Request $request)
    {
        $user = $request->user();

        if (!$user?->can('has timesheet')) {
            return redirect()->route('home')->with(['type' => 'warning', 'Meldung' => 'Keine Berechtigung']);
        }

        [$day, $aktion] = $this->recording->stempeln($user);

        $meldung = $aktion === TimeRecordingService::KOMMEN
            ? 'Kommen um '.$day->start->format('H:i').' Uhr erfasst.'
            : 'Gehen um '.$day->end->format('H:i').' Uhr erfasst.';

        return redirect()->route('home')->with(['type' => 'success', 'Meldung' => $meldung]);
    }

    public function start()
    {
        $this->forgetTerminalSession();

        return view('personal.time_recording.start');
    }

    public function read_key(getTimeRecordingKeyRequest $request)
    {
        $data = EmployeData::query()->where('time_recording_key', $request->key)->first();

        if ($data === null || $data->user === null) {
            return redirect()->route('time_recording.start')->withErrors(['key' => 'Unbekannter Chip.']);
        }

        if (RateLimiter::tooManyAttempts($this->pinLimiterKey($data), self::MAX_PIN_ATTEMPTS)) {
            return redirect()->route('time_recording.start')->withErrors([
                'key' => 'Zu viele falsche PIN-Eingaben. Bitte in '.ceil(RateLimiter::availableIn($this->pinLimiterKey($data)) / 60).' Minuten erneut versuchen.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $data->id);
        $request->session()->put(self::SESSION_EXPIRES, now()->addMinutes(2)->timestamp);

        return view($data->hasPin() ? 'personal.time_recording.get_secret' : 'personal.time_recording.set_secret', [
            'user' => $data->user,
        ]);
    }

    public function storeSecret(storeSecretKeyRequest $request)
    {
        $data = $this->terminalEmployeData($request);

        if ($data === null) {
            return redirect()->route('time_recording.start')->with(['type' => 'warning', 'Meldung' => 'Sitzung abgelaufen. Bitte Chip erneut scannen.']);
        }

        // Eine bestehende PIN kann am Terminal nicht überschrieben werden (nur in der Personalverwaltung).
        if ($data->hasPin()) {
            $this->forgetTerminalSession();
            Log::warning('Zeiterfassung: Versuch, bestehende PIN am Terminal zu überschreiben', ['employe_data_id' => $data->id, 'ip' => $request->ip()]);

            return redirect()->route('time_recording.start')->with(['type' => 'danger', 'Meldung' => 'Für diesen Chip ist bereits eine PIN gesetzt.']);
        }

        $data->update(['secret_key' => $request->secret_key]);
        $this->forgetTerminalSession();

        return redirect()->route('time_recording.start')->with(['type' => 'success', 'Meldung' => 'PIN gespeichert. Bitte Chip erneut scannen.']);
    }

    public function login(checkTimeRecordingPinRequest $request)
    {
        $data = $this->terminalEmployeData($request);

        if ($data === null) {
            return redirect()->route('time_recording.start')->with(['type' => 'warning', 'Meldung' => 'Sitzung abgelaufen. Bitte Chip erneut scannen.']);
        }

        $limiterKey = $this->pinLimiterKey($data);

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PIN_ATTEMPTS)) {
            $this->forgetTerminalSession();

            return redirect()->route('time_recording.start')->withErrors(['key' => 'Zu viele falsche PIN-Eingaben. Der Chip ist vorübergehend gesperrt.']);
        }

        if (!$data->checkPin((string) $request->secret_key)) {
            RateLimiter::hit($limiterKey, 15 * 60);
            $this->forgetTerminalSession();

            return redirect()->route('time_recording.start')->withErrors(['key' => 'PIN falsch.']);
        }

        RateLimiter::clear($limiterKey);
        $this->forgetTerminalSession();

        $user = $data->user;
        [$timesheetDay, $aktion] = $this->recording->stempeln($user);

        return view('personal.time_recording.login', [
            'user' => $user,
            'timesheet_day' => $timesheetDay,
            'aktion' => $aktion,
            'timesheet' => $timesheetDay->timesheet->fresh(),
            'dayBefore' => $this->recording->offenVomVortag($user),
        ]);
    }

    public function logout()
    {
        $this->forgetTerminalSession();

        return redirect()->route('time_recording.start');
    }

    private function terminalEmployeData(Request $request): ?EmployeData
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $expires = (int) $request->session()->get(self::SESSION_EXPIRES, 0);

        if ($id === null || $expires < now()->timestamp) {
            $this->forgetTerminalSession();
            return null;
        }

        return EmployeData::find($id);
    }

    private function forgetTerminalSession(): void
    {
        session()->forget([self::SESSION_KEY, self::SESSION_EXPIRES]);
    }

    private function pinLimiterKey(EmployeData $data): string
    {
        return 'time-recording-pin:'.$data->id;
    }
}
