<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateEmploymentRequest;
use App\Models\personal\Employment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmploymentController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(CreateEmploymentRequest $request, User $employe)
    {
        $employe->employments()->create($request->validated());

        if (!is_null($request->replaced_employment_id)) {
            $replaced = Employment::findOrFail($request->replaced_employment_id);
            $replaced->update([
                'end' => Carbon::createFromFormat('Y-m-d', $request->start)->subDay()
            ]);
        }

        return redirectBack('success', 'Anstellung wurde erstellt');
    }


}
