<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpFormatvorlageRequest;
use App\Models\Wochenplan\WpFormatvorlage;

class WpFormatvorlageController extends Controller
{
    public function index()
    {
        $formatvorlagen = WpFormatvorlage::orderBy('name')->get();
        return view('wochenplan.new.formatvorlagen.index', compact('formatvorlagen'));
    }

    public function create()
    {
        return view('wochenplan.new.formatvorlagen.create');
    }

    public function store(WpFormatvorlageRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        if (!empty($data['is_default'])) {
            WpFormatvorlage::where('is_default', true)->update(['is_default' => false]);
        }

        WpFormatvorlage::create($data);

        return redirect()->route('wp.formatvorlagen.index')
            ->with(['type' => 'success', 'Meldung' => 'Formatvorlage wurde erstellt.']);
    }

    public function edit(WpFormatvorlage $wpFormatvorlage)
    {
        return view('wochenplan.new.formatvorlagen.edit', compact('wpFormatvorlage'));
    }

    public function update(WpFormatvorlageRequest $request, WpFormatvorlage $wpFormatvorlage)
    {
        $data = $request->validated();

        if (!empty($data['is_default'])) {
            WpFormatvorlage::where('is_default', true)
                ->where('id', '!=', $wpFormatvorlage->id)
                ->update(['is_default' => false]);
        }

        $wpFormatvorlage->update($data);

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Formatvorlage wurde gespeichert.']);
    }

    public function destroy(WpFormatvorlage $wpFormatvorlage)
    {
        $wpFormatvorlage->delete();

        return redirect()->route('wp.formatvorlagen.index')
            ->with(['type' => 'success', 'Meldung' => 'Formatvorlage wurde gelöscht.']);
    }

    public function vorschau(WpFormatvorlage $wpFormatvorlage)
    {
        return view('wochenplan.new.formatvorlagen.vorschau', compact('wpFormatvorlage'));
    }
}
