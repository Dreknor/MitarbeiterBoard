<?php

namespace App\Http\Controllers;

use App\Http\Requests\createPostRequest;
use App\Mail\InvitationMail;
use App\Mail\newPostsMail;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class PostsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return redirect()->back();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('create posts')){
            return redirect()->back()->with([
               'type' => 'warning',
               'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        return view('posts.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(createPostRequest $request)
    {
        $post = new Post($request->validated());
        $post->author_id = auth()->id();
        $post->save();

        $post->groups()->attach($request->groups);

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                if(substr($file->getMimeType(), 0, 5) == 'image')
                    $collection = 'images';
                else {
                    $collection = 'files';
                }
                $post
                    ->addMedia($file)
                    ->toMediaCollection($collection);
            }
        }
        return redirect(url('/'))->with([
           'type' => 'success',
           'Meldung' => 'Nachricht gespeichert'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Post  $modelsPosts
     * @return \Illuminate\Http\Response
     */
    public function show(Post $modelsPosts)
    {
        return redirect()->back();
    }


    public function release(Post $post){
        if (! auth()->id() == $post->author_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $post->update([
            'released' => 1,
            'created_at' => Carbon::now()
        ]);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Nachricht veröffentlicht'
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return View
     */
    public function edit(Post $post)
    {
        if (! auth()->id() == $post->author_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        return view('posts.edit',[
            'post' => $post
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Post  $modelsPosts
     * @return \Illuminate\Http\Response
     */
    public function update(createPostRequest $request, Post $post)
    {
        if (! auth()->id() == $post->author_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $post->update($request->validated());
        $post->groups()->sync($request->groups);

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                if(substr($file->getMimeType(), 0, 5) == 'image')
                    $collection = 'images';
                else {
                    $collection = 'files';
                }
                $post
                    ->addMedia($file)
                    ->toMediaCollection($collection);
            }
        }


            return redirect(url('/'))->with([
                'type' => 'success',
                'Meldung' => 'Nachricht bearbeitet'
            ]);


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post)
    {
        if (! auth()->id() == $post->author_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $post->delete();

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => 'Nachricht gelöscht'
        ]);

    }

    public function dailyMail(){
        $users = User::whereHas('posts', function($q)
        {
            $q
                ->whereDate('posts.created_at', '>=', Carbon::today()->startOfDay())
                ->where('released', 1);

        })->get();

        foreach ($users as $user ){
            Mail::to($user)->queue(new newPostsMail($user->posts()
                ->whereDate('posts.created_at', '>=', Carbon::today()->startOfDay())
                ->where('released', 1)
                ->get())
            );
        }
    }
}
