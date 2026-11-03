<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Anschrift eines Mitarbeitenden (Teil der Stammdaten).
 */
class AddressController extends Controller
{
    /**
     * Anschrift speichern – je Person genau ein Datensatz; Änderungen protokolliert das Activity-Log des Modells.
     */
    public function update(Request $request, User $employe): RedirectResponse
    {
        $data = $request->validate([
            'strasse' => ['nullable', 'string', 'max:255'],
            'nr'      => ['nullable', 'string', 'max:20'],
            'plz'     => ['nullable', 'string', 'max:10'],
            'ort'     => ['nullable', 'string', 'max:255'],
            'land'    => ['nullable', 'string', 'max:255'],
        ]);

        $address = $employe->address()->firstOrNew();
        $address->fill($data);
        $address->employe_id = $employe->id;
        $address->save();

        return redirectBack('success', 'Anschrift wurde gespeichert.');
    }
}
