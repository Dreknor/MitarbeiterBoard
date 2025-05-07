<?php

namespace App\Http\Controllers;

use App\Http\Requests\createShareRequest;
use App\Models\Protocol;
use App\Models\Share;
use App\Models\Theme;
use Carbon\Carbon;
use danielme85\LaravelLogToDB\LogToDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    public function shareTheme(Theme $theme, createShareRequest $createShareRequest)
    {
        if ((! auth()->user()->groups()->contains($theme->group) and $theme->group->protected) or ! auth()->user()->can('share theme') or $theme->id != base64_decode($createShareRequest->input('theme'))) {
            return redirect()->back()->with([
                'type'   => 'danger',
                'Meldung'    => 'Keine Berechtigung',
            ]);
        }

        $share = new Share([
            'uuid'  => Str::uuid(),
            'creators_id'    => auth()->id(),
            'theme_id' => $theme->id,
            'readonly' => $createShareRequest->input('readonly'),
        ]);

        while (! $share->save()) {
            $share->uuid = Str::uuid();
            $share->save();
        }

        Log::info('Themes: Freigabe erstellt', [
            'user'  => auth()->user()->name,
            'theme' => $theme->name,
            'uuid'  => $share->uuid,
        ]);

        return redirect()->back()->with([
           'type' => 'warning',
           'Meldung' => 'Freigabelink wurde erstellt.',
        ]);
    }

    public function removeShare(Theme $theme, Request $request)
    {
        if (! auth()->user()->can('share theme') or $theme->id != base64_decode($request->input('theme'))) {
            return redirect()->back()->with([
                'type'   => 'danger',
                'Meldung'    => 'Keine Berechtigung',
            ]);
        }

        $theme->share()->delete();

        Log::info('Themes: Freigabe entfernt', [
            'user'  => auth()->user()->name,
            'theme' => $theme->name,
        ]);

        return redirect()->back()->with([
           'type' => 'success',
           'Meldung' => 'Freigabelink wurde entfernt.',
        ]);
    }

    public function getShare($uuid)
    {
        $share = Share::where('uuid', $uuid)->first();

        //Share nicht gefunden
        if ($share == null) {
            return redirect('/')->with([
               'type'   => 'danger',
               'Meldung'    => 'Keine Berechtigung',
            ]);
        }

        //Share abgelaufen
        if ($share->activ_until != null and $share->activ_until->lessThan(Carbon::now())) {

            Log::info('Themes: Freigabe abgelaufen und entfernt', [
                'user'  => (auth()->check()) ? auth()->user()->name : 'Gast: ' .request()->ip(),
                'theme' => $share->theme->name,
            ]);

            $share->delete();

            return redirect('/')->with([
                'type'   => 'danger',
                'Meldung'    => 'Freigabe abgelaufen',
            ]);
        }

        Log::info('Themes: Freigabe abgerufen', [
            'user'  => (auth()->check()) ? auth()->user()->name : 'Gast: ' .request()->ip(),
            'theme' => $share->theme->name,
        ]);



        //Umleitung zu eigener Gruppe
        if (auth()->check() and (auth()->user()->groups()->contains($share->theme->group) or ! $share->theme->group->protected)) {
            return redirect(url($share->theme->group->name.'/themes/'.$share->theme->id));
        }

        //Anzeige
        return view('themes.shared', [
            'theme' => $share->theme,
            'share' => $share,
        ]);
    }

    public function protocol($uuid, Request $request)
    {
        $share = Share::where('uuid', $uuid)->first();

        //Share nicht gefunden
        if ($share == null) {
            return redirect('/')->with([
                'type'   => 'danger',
                'Meldung'    => 'Keine Berechtigung',
            ]);
        }

        //Share abgelaufen
        if ($share->activ_until != null and $share->activ_until->lessThan(Carbon::now())) {

            Log::info('Themes: Freigabe abgelaufen und entfernt', [
                'user'  => (auth()->check()) ? auth()->user()->name : 'Gast: ' .request()->ip(),
                'theme' => $share->theme->name,
            ]);

            $share->delete();

            return redirect('/')->with([
                'type'   => 'danger',
                'Meldung'    => 'Freigabe abgelaufen',
            ]);
        }

        Log::info('Themes: Freigabe abgerufen', [
            'user'  => (auth()->check()) ? auth()->user()->name : 'Gast: ' .request()->ip(),
            'theme' => $share->theme->name,
        ]);

        $protocol = new Protocol([
            'theme_id'   => $share->theme_id,
            'protocol'   => 'Ersteller: '.$request->input('name').'<br><br>'.$request->protocol,
        ]);
        $protocol->save();

        return redirect()->back()->with([
            'type'   => 'success',
            'Meldung'    => 'gespeichert',
        ]);
    }
}
