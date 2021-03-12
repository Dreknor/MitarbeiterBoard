<?php

namespace App\Http\Controllers;

use App\Models\Vertretungsplan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VertretungsplanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->view('vertretungsplan.index')->header('Content-Security-Policy', 'frame-ancestors self https://*.esz-radebeul.de');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Vertretungsplan  $vertretungsplan
     * @return \Illuminate\Http\Response
     */
    public function show(Vertretungsplan $vertretungsplan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Vertretungsplan  $vertretungsplan
     * @return \Illuminate\Http\Response
     */
    public function edit(Vertretungsplan $vertretungsplan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Vertretungsplan  $vertretungsplan
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Vertretungsplan $vertretungsplan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Vertretungsplan  $vertretungsplan
     * @return \Illuminate\Http\Response
     */
    public function destroy(Vertretungsplan $vertretungsplan)
    {
        //
    }
}
