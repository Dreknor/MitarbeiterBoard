<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Http\Requests\moveThemesRequest;
use App\Mail\newThemeAssignMail;
use App\Mail\RemindAssignedThemes;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\Subscription;
use App\Models\Theme;
use App\Models\Type;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ThemeController extends Controller
{

    public function change_group(Theme $theme, Group $group){
        if (! auth()->user()->groups()->contains($theme->group) or (! auth()->user()->groups()->contains($group) and $group->protected)) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if (! auth()->user()->can('move themes')) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Berechtigung fehlt',
            ]);
        }

        $oldGroup = $theme->group;

        $theme->update([
           'group_id' => $group->id
        ]);

        $protocol = new Protocol([
            'theme_id' => $theme->id,
            'creator_id' => auth()->id(),
            'protocol' =>  auth()->user()->name.' hat das Thema aus der Gruppe '.$oldGroup->name.' nach '.$group->name.' verschoben.'
        ]);
        $protocol->save();

        return redirect(url($oldGroup->name.'/themes#'.$theme->date->format('Ymd')))->with([
            'type'    => 'success',
            'Meldung' => 'Thema zur Gruppe '.$group->name.' verschoben.',
        ]);
    }

    public function assgin_to(Theme $theme, User $user){
        if (! auth()->user()->groups()->contains($theme->group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if (! $user->groups()->contains($theme->group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Benutzer ist nicht in dieser Gruppe',
            ]);
        }

        $theme->update([
           'assigned_to' => $user->id
        ]);


        //Subscription erstellen
        $type = Theme::class;
        $subscription = $user->subscriptions()->where('subscriptionable_type', $type)->where('subscriptionable_id', $theme->id)->first();
        if ($subscription == null) {
            $subscription = new Subscription([
                'users_id' => $user->id,
                'subscriptionable_type' => $type,
                'subscriptionable_id'=>$theme->id,
            ]);

            $subscription->save();
        }

        //Benachrichtigen
        Mail::to($user->email)->queue(new newThemeAssignMail($theme, $user));

        //Log erstellen
        $protocol = new Protocol([
           'theme_id' => $theme->id,
           'creator_id' => auth()->id(),
           'protocol' =>  auth()->user()->name.' hat das Thema '.$user->name.' zugewiesen.'
        ]);
        $protocol->save();


        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Thema zugewiesen',
        ]);

    }

    public function remind_assigned_themes(){
        $users = User::whereHas('assigned_themes', function ($query){
            return $query->where('completed', '!=', 1);
        })
            ->where('remind_assign_themes', 1)
            ->with('assigned_themes')->get();

        foreach ($users as $user){
            Mail::to($user->email)->queue(new RemindAssignedThemes($user, $user->assigned_themes->where('completed', 0)));
        }
    }
    public function setView($groupname, $viewType)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($viewType != null) {
            Cache::forever('viewType_'.$groupname.'_'.auth()->id(), $viewType);
        }

        return redirect(url($groupname.'/themes'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return View|RedirectResponse
     */
    public function index($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
              'type'    => 'warning',
              'Meldung' => 'Kein Zugriff auf diese Gruppe',
           ]);
        }

        $themes = $group->themes()->where('completed', 0)->where('memory', 0)->get();
        $themes->load('priorities', 'ersteller', 'type', 'protocols');

        $viewType = Cache::get('viewType_'.$groupname.'_'.auth()->id(), $group->viewType);

        switch ($viewType) {
            case 'date':
                if ($group->stack_themes == true) {

                    $themes = $themes->sortBy('date')->groupBy(function ($item) {
                        return  $item->date->lessThan(Carbon::today()) ? 'offen' : $item->date->format('d.m.Y');
                    });


                } else {
                    $themes = $themes->sortBy('date')->groupBy(function ($item) {
                        return  $item->date->format('d.m.Y');
                    });
                }
                break;
            case 'type':
                $themes = $themes->sortBy('date')->groupBy(function ($item) {
                    return  $item->type->type;
                });
                break;
            case 'priority':
                $themes = $themes->sortByDesc('priority');
                break;
        }

        $views = [
            'date'  => 'index',
            'type'  => 'indexType',
            'priority' => 'indexPriority',
        ];

        $subscription = auth()->user()->subscriptions->where('subscriptionable_type', Group::class)->where('subscriptionable_id', $group->id)->first();

        return view('themes.'.$views[$viewType], [
           'themes' => $themes,
            'viewType' => $viewType,
            'subscription'  => $subscription,
            'group' => $group,
            'anwesenheiten' => $group->presences()->groupBy('date')->get(),
        ]);
    }
    public function memory($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
              'type'    => 'warning',
              'Meldung' => 'Kein Zugriff auf diese Gruppe',
           ]);
        }

        $themes = $group->themes()->where('completed', 0)->where('memory', 1)->get();
        $themes->load('priorities', 'ersteller');

        $themes = $themes->sortByDesc('priority');

        return view('themes.memory', [
           'themes' => $themes,
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View|RedirectResponse
     */
    public function archive($groupname, $month = null)
    {

        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($month != null) {
            $month = Carbon::createFromFormat('Y-m', $month);
        } else {
            $month = Carbon::now();
        }

        $oldest_theme = $group->themes()->where('completed', 1)->orderBy('date')->first();
        $oldest_theme = $oldest_theme->date;

        $themes = $group->themes()
            ->where('completed', 1)
            ->where('date', '>=', $month->copy()->startOfMonth())
            ->where('date', '<=', $month->copy()->endOfMonth())
            ->orderByDesc('date')->get();
        $themes->load('ersteller', 'type', 'priorities');

        $themes = $themes->groupBy(function ($item) {
            return $item->date->format('d.m.Y');
        });

        $themes = new \Illuminate\Support\Collection($themes);

        return view('themes.archive', [
           'themes' => $themes->paginate(5),
            'oldest' => $oldest_theme,
        ]);
    }

    public function unArchive(Theme $theme)
    {

        if (! auth()->user()->can('unarchive theme')) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme->update(['completed' => 0]);

        $protocol = Protocol::create([
            'creator_id' => auth()->id(),
            'theme_id' => $theme->id,
            'protocol'  => 'Thema wieder aktiviert',
        ]);
        $protocol->save();


        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Thema erneut geöffnet'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create($groupname, $speicher=null)
    {
        $group = Group::where([
            'name'  => $groupname,
        ])->first();

        return view('themes.create', [
            'types' => Type::all(),
            'group' => $group,
            'speicher' => $speicher
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createThemeRequest $request, $groupname)
    {
        if (! auth()->user()->can('create themes')) {
            return redirect(url('/'))->with([
                'type'    => 'danger',
                'Meldung' => 'Berechtigung fehlt',
            ]);
        }

        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->proteced) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $date = Carbon::createFromFormat('Y-m-d', $request->date);
        if ($date->lessThan(Carbon::today()->startOfDay())) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema kann für diesen Tag nicht mehr erstellt werden',
            ]);
        }

        $theme = new Theme($request->validated());
        $theme->group_id = $group->id;
        $theme->creator_id = auth()->id();
        $theme->type_id = $request->type;
        $theme->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        if ($group->protected == null){
            $subscription = new Subscription([
                'users_id' => auth()->id(),
                'subscriptionable_type' => Theme::class,
                'subscriptionable_id'=>$theme->id,
            ]);
            $subscription->save();
        }

        return redirect(url($groupname.'/themes#'.$theme->date->format('Ymd')))->with([
           'type'   => 'success',
           'Meldung'    => 'Thema erstellt',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|RedirectResponse
     */
    public function show($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($group->id != $theme->group_id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema nicht gefunden',
            ]);
        }

        if ($theme->memory == true and $theme->completed == false) {
            return redirect(url($groupname.'/memory'))->with([
                'type'    => 'warning',
                'Meldung' => 'Thema ist im Themenspeicher',
            ]);
        }

        $subscription = auth()->user()->subscriptions->where('subscriptionable_type', Theme::class)->where('subscriptionable_id', $theme->id)->first();

        return view('themes.show', [
            'theme' => $theme->load(['protocols', 'tasks', 'type', 'priorities', 'tasks.taskable']),
            'subscription' => $subscription,
            'group' => $group,
        ]);
    }

    public function memoryTheme($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme->update([
            'memory' => true
        ]);

        $protocol = Protocol::create([
            'creator_id' => auth()->id(),
            'theme_id' => $theme->id,
            'protocol'  => 'Thema in Themenspeicher verschoben',
        ]);
        $protocol->save();

        return redirect(url($groupname."/themes#".$theme->date->format('Ymd')))->with([
            'type'  => 'success',
            'Meldung' => 'Thema wurde aktiviert.'
        ]);
    }
    public function activate($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme->update([
            'memory' => false
        ]);

        $protocol = Protocol::create([
            'creator_id' => auth()->id(),
            'theme_id' => $theme->id,
            'protocol'  => 'Thema aktiviert',
        ]);
        $protocol->save();

        return redirect(url($groupname."/themes/".$theme->id))->with([
            'type'  => 'success',
            'Meldung' => 'Thema wurde aktiviert.'
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return View|RedirectResponse
     */
    public function edit($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        return view('themes.edit', [
            'theme' => $theme,
            'types' => Type::all(),
            'group' => $group,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Theme  $theme
     * @return RedirectResponse
     */
    public function update($groupname, createThemeRequest $request, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $date = Carbon::createFromFormat('Y-m-d', $request->date)->startOfDay();

        (!$date->eq($theme->date))? $redirectDate = $date->format('Ymd') : $redirectDate = $theme->date->format('Ymd');

        if ((!$date->eq($theme->date->startOfDay()) and $date->lessThan(Carbon::now()->addDays($group->InvationDays)->startOfDay()) and !$date->isSameDay(Carbon::today()))) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema kann für diesen Tag nicht mehr erstellt werden',
            ]);
        }

        if ($request->memory == true) {
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'In Themenspeicher verschoben'
            ]);
            $protocol->save();
        }
        if (!$date->eq($theme->date)) {
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Verschoben zum '.$date->format('d.m.Y'),
            ]);
            $protocol->save();
        }

        $theme->update($request->validated());

        if ($theme->wasChanged('information')){
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Informationen geändert',
            ]);
            $protocol->save();
        }

        if ($theme->wasChanged('type_id')){
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Typ geändert',
            ]);
            $protocol->save();
        }

        if ($theme->wasChanged('theme')){
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Thema geändert',
            ]);
            $protocol->save();
        }


        $theme->type_id = $request->type;
        $theme->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }

            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Dateien hinzugefügt',
            ]);
            $protocol->save();
        }

        return redirect(url($groupname."/themes#$redirectDate"))->with([
            'type'  => 'success',
            'Meldung'=> 'Änderungen gespeichert.',
        ]);
    }

    public function destroy($groupname, Theme $theme)
    {
        $date = $theme->date->format('Ymd');
        if (auth()->user()->id == $theme->creator_id and $theme->protocols->count() == 0 and $theme->priority == null and $theme->date->startOfDay()->greaterThan(Carbon::now()->startOfDay()->addDays(config('config.themes.addDays')))) {
            $theme->delete();

            return redirect(url($groupname.'/themes#'.$date))->with([
                'type'  => 'info',
                'Meldung'   => 'Thema wurde gelöscht.',
            ]);
        }

        return redirect()->back()->with([
            'type'  => 'warning',
            'Meldung'   => 'Thema kann nicht gelöscht werden',
        ]);
    }

    /**
     * Soft-Löschen einer an ein Thema angehängten Datei.
     *
     * Die Datei wird NICHT physisch gelöscht, sondern nur als "archiviert"
     * markiert (Spatie Custom Property). Dadurch bleibt der bestehende
     * Download-Link (/image/{id}) gültig – Verweise in bereits erstellten
     * Protokollen brechen also nicht. Zusätzlich wird ein Protokolleintrag
     * angelegt, der auf die entfernte (archivierte) Datei hinweist.
     */
    public function archiveFile($groupname, Theme $theme, Media $media)
    {
        // Zugriff auf die Gruppe des Themas prüfen
        if (! auth()->user()->groups()->contains($theme->group)) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        // Die Datei muss tatsächlich zu diesem Thema gehören (Schutz vor ID-Manipulation)
        if ($media->model_type !== $theme->getMorphClass() || (int) $media->model_id !== (int) $theme->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Datei gehört nicht zu diesem Thema',
            ]);
        }

        // Bereits archiviert? Dann nichts tun.
        if ($media->getCustomProperty('archiviert')) {
            return redirect()->back()->with([
                'type'    => 'info',
                'Meldung' => 'Datei wurde bereits entfernt',
            ]);
        }

        // Soft-Löschen: archivieren statt physisch löschen, damit bestehende
        // Protokoll-Verweise (/image/{id}) weiterhin funktionieren.
        $media->setCustomProperty('archiviert', true)
            ->setCustomProperty('archiviert_von', auth()->id())
            ->setCustomProperty('archiviert_am', now()->toDateTimeString())
            ->save();

        // Protokolleintrag anlegen, der auf die entfernte (archivierte) Datei hinweist.
        $protocol = new Protocol([
            'theme_id'   => $theme->id,
            'creator_id' => auth()->id(),
            'protocol'   => auth()->user()->name . ' hat die Datei „' . e($media->name) . '" entfernt. '
                . 'Die Datei bleibt archiviert abrufbar: '
                . '<a href="' . url('/image/' . $media->id) . '" target="_blank">' . e($media->name) . '</a>',
        ]);
        $protocol->save();

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Datei wurde entfernt (archiviert) und im Protokoll vermerkt.',
        ]);
    }

    /**
     * Admin-Übersicht aller archivierten (soft-gelöschten) Theme-Dateien.
     */
    public function archivedFiles()
    {
        $userGroupIds = auth()->user()->groups()->pluck('groups.id');

        $media = Media::query()
            ->where('model_type', (new Theme)->getMorphClass())
            ->where('custom_properties->archiviert', true)
            ->with('model.group')
            ->orderByDesc('updated_at')
            ->get()
            // Nur Dateien aus Gruppen, in denen der Nutzer Mitglied ist
            ->filter(fn ($m) => $m->model && $userGroupIds->contains($m->model->group_id))
            ->map(function ($m) {
                $theme = $m->model;
                // Direkter Link im Protokolltext (/image/{id})
                $linkRef = Protocol::where('protocol', 'like', '%/image/' . $m->id . '%')->exists();
                // Hat das Thema überhaupt Protokolle? Dann kann die Datei auch rein
                // textuell referenziert sein (z. B. "siehe angehängte Datei") – ohne Link.
                $hasProtocols = $theme ? $theme->protocols()->exists() : false;

                $m->geschuetzt  = $linkRef || $hasProtocols;
                $m->schutzgrund = $linkRef
                    ? 'In einem Protokoll direkt verlinkt'
                    : ($hasProtocols ? 'Thema hat Protokolle – möglicher Verweis im Text' : null);
                $m->archiviert_von_name = optional(User::find($m->getCustomProperty('archiviert_von')))->name;
                return $m;
            });

        return view('themes.archived_files', [
            'medien' => $media,
        ]);
    }

    /**
     * Stellt eine archivierte Theme-Datei wieder her.
     */
    public function restoreFile(Media $media)
    {
        $theme = $media->model;

        if (! $theme instanceof Theme || ! auth()->user()->groups()->contains($theme->group)) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Kein Zugriff auf diese Datei',
            ]);
        }

        if (! $media->getCustomProperty('archiviert')) {
            return redirect()->back()->with([
                'type'    => 'info',
                'Meldung' => 'Datei ist nicht archiviert',
            ]);
        }

        $media->forgetCustomProperty('archiviert')
            ->forgetCustomProperty('archiviert_von')
            ->forgetCustomProperty('archiviert_am')
            ->save();

        $protocol = new Protocol([
            'theme_id'   => $theme->id,
            'creator_id' => auth()->id(),
            'protocol'   => auth()->user()->name . ' hat die Datei „' . e($media->name) . '" wiederhergestellt.',
        ]);
        $protocol->save();

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Datei wurde wiederhergestellt.',
        ]);
    }

    /**
     * Löscht eine archivierte Theme-Datei endgültig – aber nur, wenn sie in
     * keinem Protokoll referenziert wird (sonst würde der Verweis brechen).
     */
    public function forceDeleteFile(Media $media)
    {
        $theme = $media->model;

        if (! $theme instanceof Theme || ! auth()->user()->groups()->contains($theme->group)) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Kein Zugriff auf diese Datei',
            ]);
        }

        // Referenzprüfung: Eine Datei darf nur endgültig gelöscht werden, wenn das
        // Thema gar keine Protokolle hat. Sobald Protokolle existieren, kann die
        // Datei dort verlinkt ODER nur textuell erwähnt sein ("siehe angehängte
        // Datei"). Beides lässt sich nicht zuverlässig automatisch erkennen, daher
        // bleibt die Datei in diesem Fall sicherheitshalber archiviert erhalten.
        $linkRef      = Protocol::where('protocol', 'like', '%/image/' . $media->id . '%')->exists();
        $hasProtocols = $theme->protocols()->exists();

        if ($linkRef || $hasProtocols) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Datei kann nicht endgültig gelöscht werden: Das Thema enthält Protokolle, '
                    . 'die auf die Datei verweisen könnten (auch ohne direkten Link, z. B. „siehe angehängte Datei"). '
                    . 'Die Datei bleibt archiviert.',
            ]);
        }

        $themeId   = $theme->id;
        $dateiName = $media->name;
        $media->delete(); // physisches Löschen inkl. Datei

        $protocol = new Protocol([
            'theme_id'   => $themeId,
            'creator_id' => auth()->id(),
            'protocol'   => auth()->user()->name . ' hat die archivierte Datei „' . e($dateiName) . '" endgültig gelöscht.',
        ]);
        $protocol->save();

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Datei wurde endgültig gelöscht.',
        ]);
    }

    public function closeTheme($groupname, Theme $theme)
    {
        if (! auth()->user()->can('complete theme')) {
            return redirect()->back()->with([
                'type'  => 'danger',
               'Meldung'=> 'Berechtigung fehlt',
            ]);
        }

        $theme->update([
               'completed' => 1,
           ]);

        $protocol = new Protocol([
               'creator_id' => auth()->id(),
               'theme_id'   => $theme->id,
               'protocol'   => 'Thema geschlossen',
           ]);
        $protocol->save();

        return redirect(url($groupname.'/themes#'.$theme->date->format('Ymd')))->with([
               'type'  => 'success',
               'Meldung'=> 'Thema geschlossen',
           ]);
    }

    public function move($groupname, Theme $theme, $newDate, $redirect = false){

        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $oldDate = $theme->date;
        $date = Carbon::createFromFormat('Y-m-d', $newDate);

            $protocol = new Protocol([
                'creator_id' => auth()->id(),
                'theme_id'   => $theme->id,
                'protocol'   => 'Thema verschoben von '.$oldDate->format('d.m.Y').' auf '.$date->format('d.m.Y'),
            ]);
            $protocol->save();

        $theme->update([
            'date' => $date->format("Y-m-d")
        ]);



        if ($redirect == true) {
            return redirect(url($groupname . "/themes#" . $oldDate->format('Ymd')))->with([
                'type' => 'success',
                'Meldung' => 'Thema wurde verschoben',
            ]);
        }
    }

    public function moveAllThemes($groupname, moveThemesRequest $request)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $oldDate = Carbon::createFromFormat('Y-m-d', $request->oldDate);
        $date = Carbon::createFromFormat('Y-m-d', $request->date);

        foreach ($group->themes()->where('date', $oldDate->format('Y-m-d'))->get() as $theme) {
            $this->move($groupname, $theme, $date->format('Y-m-d'), false);

        }

        return redirect(url($groupname . "/themes#" . $oldDate->format('Ymd')))->with([
            'type' => 'success',
            'Meldung' => 'Themen wurden verschoben',
        ]);
    }
}
