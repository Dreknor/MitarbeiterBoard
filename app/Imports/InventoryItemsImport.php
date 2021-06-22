<?php

namespace App\Imports;

use App\Models\Inventory\Category;
use App\Models\Inventory\Items;
use App\Models\Inventory\Lieferant;
use App\Models\Inventory\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InventoryItemsImport implements ToCollection, WithHeadingRow
{
    protected $header;
    protected $locations;
    protected $lieferanten;
    protected $category;

    public function __construct()
    {
        $this->locations = Location::all();
        $this->lieferanten = Lieferant::all();
        $this->category = Category::firstOrCreate(['name' =>'Unbekannt']);
    }

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {

        foreach ($collection as $row){
            if (!is_null($row['bezeichnung'])){
                if ($row['standort'] != ''){
                    $standort = $this->locations->where('kennzeichnung', $row['standort'])->first();
                    if (is_null($standort)){
                        $standort = $this->locations->where('name', 'Unbekannt')->first();
                    }
                } else {
                    $standort = $this->locations->where('name', 'Unbekannt')->first();

                }
                if ($row['bemerkungen'] != ''){
                    $lieferant = $this->lieferanten->where('name', $row['bemerkungen'])->first();
                    if (is_null($lieferant)){
                        $lieferant = new Lieferant([
                            'name' => $row['bemerkungen'],
                            'kuerzel' => substr($row['bemerkungen'], 0, 10),
                        ]);
                        $lieferant->save();
                    }
                    $lieferant = $lieferant->id;
                } else {
                    $lieferant =null;
                }

                $item = new Items([
                    'uuid' => uuid_create(),
                    'name'=> $row['bezeichnung'],
                    'oldInvNumber' => $row['nr'],
                    'number' => $row['anzahl'],
                    'date'  => $row['zugang']?:null,
                    'category_id' => $this->category->id,
                    'location_id' => $standort->id,
                    'lieferant_id' => $lieferant,
                    'price' => $row['gesamtpreis']?:null,
                ]);
                $item->save();
            }

        }

    }
}
