<?php

namespace App\Imports;

use App\Models\Inventory\Location;
use App\Models\Inventory\LocationType;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class LocationImport implements ToCollection, WithHeadingRow
{
    protected $header;

    public function __construct($header)
    {
        $this->header = $header;
    }
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        foreach ($collection as $row){
            if (isset($this->header['type']) and !is_null($this->header['type']) and !is_null($row[strtolower($this->header['type'])])){
                $type = LocationType::firstOrCreate([
                    'name' => $row[strtolower($this->header['type'])]
                ]);
            }
           //dd($row);

            $location=Location::where('name', $row[strtolower($this->header['name'])])->first();

            if (!isset($location) or is_null($location)){
                $location = new Location([
                    'name' => $row[strtolower($this->header['name'])],
                    'uuid' => str::uuid()
                    ]);

                $location->locationtype_id = $type->id;

                if (isset($this->header['kennzeichen']) and !is_null($this->header['kennzeichen'])) {
                    $location->kennzeichnung = $row[strtolower($this->header['kennzeichen'])];
                }
                if (isset($this->header['description']) and !is_null($this->header['description'])) {
                    $location->description = $row[strtolower($this->header['description'])];
                }


                if (isset($this->header['user']) and !is_null($this->header['user'])) {
                    $user  = User::where('kuerzel', $row[strtolower($this->header['user'])])->orWhere('name', '%LIKE%', $row[strtolower($this->header['user'])])->first();
                    $location->verantwortlicher_id = optional($user)->id;
                }

                $location->save();
            }
        }
    }
}
