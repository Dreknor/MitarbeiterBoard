<?php

namespace App\Http\Controllers\Ticketsystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\createTicketCategoryRequest;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\Tickets\TicketService;
use Illuminate\Support\Facades\DB;

class TicketCategoryController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
        $this->middleware('permission:edit tickets');
    }

    public function index()
    {
        return view('ticketsystem.categories', [
            'categories' => TicketCategory::query()
                ->withCount(['tickets as open_tickets_count' => fn ($q) => $q->open()])
                ->withCount('tickets')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(createTicketCategoryRequest $request)
    {
        TicketCategory::create($request->validated());
        $this->tickets->forgetCategoryCache();

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Kategorie erstellt.',
        ]);
    }

    /**
     * Kategorie löschen; zugeordnete Tickets verlieren nur die Kategorie.
     */
    public function destroy(TicketCategory $category)
    {
        DB::transaction(function () use ($category) {
            Ticket::withTrashed()->where('category_id', $category->id)->update(['category_id' => null]);
            $category->delete();
        });

        $this->tickets->forgetCategoryCache();

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Kategorie gelöscht.',
        ]);
    }
}
