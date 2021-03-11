<?php

namespace App\Imports;

use App\Models\Protocol;
use App\Models\Theme;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ThemeImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            //dd($row);
            $users = [
                'Daniel'  => 1,
                'Dorothee' => 2,
                'Dorit'   => 3,
                'Falk'    => 4,
            ];

            $types = [
                'Information' => 1,
                'Entscheidung'  => 2,
                'Terminfindung' => 3,
                'Lösung'        => 4,
                'Diskussion'    => 5,
            ];

            if ($row[3] != '') {
                if (isset($row[0]) and $row[0] != '') {
                    $date = Date::excelToDateTimeObject($row[0]);
                    $date = date_format($date, 'Y-m-d H:i:s');
                } else {
                    $date = Carbon::parse('2018-01-01')->format('Y-m-d H:i:s');
                }

                //dd($date);
                $theme = Theme::create([
                    'created_at' => $date,
                    'updated_at' => $date,
                    'creator_id' => array_key_exists($row[1], $users) ? $users[$row[1]] : 1,
                    'theme'      => $row[3],
                    'goal'       => $row[4],
                    'type_id' => array_key_exists($row[2], $types) ? $types[$row[2]] : 5,
                    'duration'  => $row[5] != '' ? $row[5] : 15,
                    'completed' => 1,
                ]);

                if ($row[6] != '') {
                    $protocol = Protocol::create([
                        'creator_id'    => 1,
                        'theme_id'      => $theme->id,
                        'protocol'      => $row[6] != '' ? $row[6] : '',
                    ]);
                }
            }
        }
    }
}
