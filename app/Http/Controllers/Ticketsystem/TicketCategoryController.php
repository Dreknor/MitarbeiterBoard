<?php

namespace App\Http\Controllers\Ticketsystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\createTicketCategoryRequest;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TicketCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit tickets');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $categories = Cache::remember('ticket_categories', 3600, function () {
            return TicketCategory::all();
        });

        return view('ticketsystem.categories', [
            'categories' => $categories,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  createTicketCategoryRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     */
    public function store(createTicketCategoryRequest $request)
    {

        $ticketCategory = new TicketCategory(
            $request->validated()
        );
        $ticketCategory->save();

        Cache::forget('ticket_categories');

       return redirect()->back()->with('success', 'Kategorie erstellt.');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketCategory $category)
    {
        Ticket::query()->where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();
        Cache::forget('ticket_categories');

        return redirect()->back()->with('success', 'Kategorie gelöscht.');
    }
}
