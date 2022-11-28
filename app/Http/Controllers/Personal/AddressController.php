<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\address  $address
     * @return RedirectResponse
     */
    public function update(CreateAddressRequest $request,  Employe $employe)
    {
        $employe->address()->create($request->validated());

        activity('employe')->performedOn($employe)->causedBy(auth()->user())->log('Anschrift geändert.');


        return redirectBack('success', 'Anschrift wurde gespeichert.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\address  $address
     * @return \Illuminate\Http\Response
     */
    public function destroy(address $address)
    {
        //todo
    }
}
