<?php

namespace App\Http\Controllers\Personal;



use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateWorkingTimeRequest;
use App\Models\personal\WorkingTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Spatie\GoogleCalendar\Event;

class WorkingTimeController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateWorkingTimeRequest $request
     * @return RedirectResponse
     */
    public function store(CreateWorkingTimeRequest $request)
    {
        $working_time = WorkingTime::updateOrCreate([
            'roster_id' => $request->roster_id,
            'employe_id' => $request->employe_id,
            'date' => $request->date,
        ], [
            'start' => $request->start,
            'end' => $request->end,
            'function' => $request->function,
        ]);

        if ($working_time->employe->employe_data?->google_calendar_link != null ){
            if ($working_time->googleCalendarId != null){
                try {
                    $event = Event::find($working_time->googleCalendarId, $working_time->employe->google_calendar_link);

                    if (isset($request->start) and isset($request->end)){
                        $event->startDateTime = Carbon::createFromFormat('Y-m-d H:i', $request->date.' '.$request->start);
                        $event->endDateTime = Carbon::createFromFormat('Y-m-d H:i', $request->date.' '.$request->end);
                        $event->save();
                    } else {
                        $event->delete();
                        $working_time->update([
                            'googleCalendarId'=>null
                        ]);
                    }
                } catch (\Exception $exception){

                }

            } else {
                if (isset($request->start) and isset($request->end)){
                    $event = Event::create([
                        'name' => 'Dienst',
                        'startDateTime' => Carbon::createFromFormat('Y-m-d H:i', $request->date.' '.$request->start),
                        'endDateTime' => Carbon::createFromFormat('Y-m-d H:i', $request->date.' '.$request->end),
                    ],
                        $working_time->employe->employe_data->google_calendar_link
                    );
                    $event->save();

                    $working_time->googleCalendarId = $event->id;
                    $working_time->save();
                }
            }

        }

        return redirectBack('success', 'Arbeitszeit gespeichert', '#' . $request->date);
    }


}
