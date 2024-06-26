<?php

namespace App\Http\Controllers;

use App\Exports\VertretungenExport;
use App\Http\Requests\CreateVertretungRequest;
use App\Http\Requests\exportVertretungenRequest;
use App\Models\Absence;
use App\Models\DailyNews;
use App\Models\Klasse;
use App\Models\User;
use App\Models\Vertretung;
use App\Models\VertretungsplanWeek;
//use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Barryvdh\DomPDF\Facade\Pdf AS PDF;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class VertretungController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $dateEnd = Carbon::today();
        $dateStart = config('config.schuljahresbeginn');

        return response()->view('vertretungsplan.edit', [
           'vertretungen_aktuell' => Vertretung::whereDate('date', '>=', Carbon::today())->orderBy('date')->orderBy('klassen_id')->orderBy('stunde')->get(),
            'klassen' => Klasse::all(),
            'lehrer'  => User::whereNotNull('kuerzel')->get(),
            'news'    => DailyNews::all(),
            'dateStart' => $dateStart->format('Y-m-d'),
            'dateEnd' => $dateEnd->format('Y-m-d')
        ]);
    }

    public function archiv($dateStart = null, $dateEnd =null){

        if (!is_null($dateStart) && !is_null($dateEnd)){

            try {
                $dateStart = Carbon::createFromFormat('Y-m-d', $dateStart);
                $dateEnd = Carbon::createFromFormat('Y-m-d', $dateEnd);

                $vertretungen = Vertretung::whereBetween('date', [$dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d')])
                    ->orderBy('klassen_id')
                    ->orderBy('stunde')
                    ->get();

            } catch (\Throwable $th) {
               return redirect()->back()->with([
                        'type'=>'danger',
                        'Meldung'=>'Falsches Datumsformat.'
                    ]);
            }




        } else {

            $dateEnd = Carbon::today();
            $dateStart = config('config.schuljahresbeginn');
            $vertretungen = Vertretung::whereBetween('date', [$dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d')])
                ->orderByDesc('date')
                ->orderBy('klassen_id')
                ->get();
        }

        $vertretungen->load('klasse', 'lehrer');
        $auswertung = [
            'Eintragungen' => $vertretungen->count(),
            'Anzahl fachgerechte Vertretungen' => $vertretungen->where('type', '==', 'Vertretung (fachgerecht)')->count(),
            'UE fachgerechte Vertretungen' => $vertretungen->where('type', '==', 'Vertretung (fachgerecht)')->count() + $vertretungen->where('type', '==', 'Vertretung (fachgerecht)')->where('Doppelstunde', 1)->count(),
            'fachfremde Vertretungen' => $vertretungen->where('type', '==', 'Vertretung (fachfremd)')->count(),
            'UE fachfremde Vertretungen' => $vertretungen->where('type', '==', 'Vertretung (fachfremd)')->count() + $vertretungen->where('type', '==', 'Vertretung (fachfremd)')->where('Doppelstunde', 1)->count(),
            'Ausfälle' => $vertretungen->where('type', '==', 'Ausfall')->count(),
            'UE Ausfälle' => $vertretungen->where('type', '==', 'Ausfall')->count() + $vertretungen->where('type', '==', 'Ausfall')->where('Doppelstunde', 1)->count(),
        ];

        return response()->view('vertretungsplan.archiv', [
             'vertretungen' => $vertretungen,
            'auswertung' => $auswertung,
            'dateStart' => $dateStart->format('Y-m-d'),
            'dateEnd' => $dateEnd->format('Y-m-d')
         ]);
    }

    /**
     * @param Vertretung $vertretung
     * @return Response
     */
    public function edit(Vertretung $vertretung)
    {
        return response()->view('vertretungsplan.edit', [
            'vertretungen_aktuell' => Vertretung::whereDate('date', '>=', Carbon::today())->orderBy('date')->orderBy('klassen_id')->orderBy('stunde')->get(),
            'vertretungen_alt' => Vertretung::whereDate('date', '<', Carbon::today())->orderByDesc('date')->orderBy('klassen_id')->get(),
            'klassen' => Klasse::all(),
            'lehrer'  => User::whereNotNull('kuerzel')->get(),
            'vertretung' => $vertretung
        ]);
    }

    public function update(CreateVertretungRequest $request, Vertretung $vertretung)
    {
        $vertretung->update($request->validated());

        return redirect(url('vertretungen'))->with([
            'type' => 'success',
            'Meldung' => 'Änderung gespeichert']);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateVertretungRequest $request)
    {
        Vertretung::create($request->validated());

        return redirect()->back()->with([
           'type'=>'success',
           'Meldung'=>'Vertretung wurde geplant.'
        ]);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  Vertretung $vertretung
     * @return \Illuminate\Http\Response
     */
    public function copy(Vertretung $vertretung)
    {

        $newVertretung = $vertretung->replicate();
        $newVertretung->stunde++;
        $newVertretung->save();

        return redirect()->back()->with([
           'type'=>'success',
           'Meldung'=>'Vertretung wurde geplant.'
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Vertretung  $vertretung
     * @return \Illuminate\Http\Response
     */
    public function destroy($vertretung)
    {

        if (!auth()->user()->can('edit vertretungen')){
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=>'Keine Berechtigung.'
            ]);
        }

        $vertretung = Vertretung::findOrFail($vertretung);

        $vertretung->delete();

        return redirect()->back()->with([
            'type'=>'warning',
            'Meldung'=>'Vertretung wurde gelöscht.'
        ]);
    }

    public function generateDoc($date){

        $date = Carbon::createFromFormat('Y-m-d', $date);

        //Hole Vertretungen
        $vertretungen = Vertretung::whereDate('date', $date)->orderBy('klassen_id')->orderBy('stunde')->get();


        $sectionStyle = array(
            'orientation' => 'landscape',
            'marginTop' => 600,
        );

        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        /* Note: any element you append to a document must reside inside of a Section. */

        // Adding an empty Section to the document...
        $section = $phpWord->addSection($sectionStyle);

        // Add first page header
        $header = $section->addHeader();
        $table = $header->addTable();
        $table->addRow();
        $cell = $table->addCell(12000);
        $table->addCell(12000)->addImage(asset('img/logo.png'), array('height' => 60, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END));

        // Add footer
        $footer = $section->addFooter();
        $footer->addPreserveText('Seite {PAGE} von {NUMPAGES}.', null, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));

        // Write some text

        $cols = 5;
        $section->addText('Vertretungsplan für '.$date->formatLocalized("%A %d %B %Y"), $header);


        $cellStyleHead = [
            'BorderSize' => 1,
            'bgColor' => 'B0CFFE',
            'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            'valign'=> 'center',
        ];

        $cellStyleBorderBottom = [
            'valign' => 'center',
          'borderBottomSize'=> 1,
          'borderColor'=> 'black',
            'bgColor' => ''
        ];

        $cellStyleLeftBottom = [
            'valign'=> 'center',
            'borderBottomSize'=> 1,
            'borderLeftSize'=> 1,
            'borderColor'=> 'black',
            'bgColor' => ''
        ];
        $cellStyleRightBottom = [
            'valign'=> 'center',
            'borderBottomSize'=> 1,
            'borderRightSize'=> 1,
            'borderColor'=> 'black',
            'bgColor' => ''
        ];

        $fontStyle = [
            'align' => 'center',
        ];

        $paragraphStyle = [
            'spacingLineRule'=> \PhpOffice\PhpWord\SimpleType\LineSpacingRule::AUTO,
            'lineHeight' => '1.0',
            'alignment'=> \PhpOffice\PhpWord\SimpleType\Jc::CENTER
        ];


        $TableStyleName = 'Table';
        $TableStyle = array('cellMargin' => 80, 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER);

        $phpWord->addTableStyle($TableStyleName, $TableStyle );
        $table = $section->addTable($TableStyle);
        //TableHeader
        $table->addRow();
        $table->addCell(1750, $cellStyleHead)->addText('Klasse', '', $fontStyle);
        $table->addCell(1750, $cellStyleHead)->addText('Stunde', '', $fontStyle);
        $table->addCell(1750, $cellStyleHead)->addText('Fächer', '', $fontStyle);
        $table->addCell(1750, $cellStyleHead)->addText('Lehrer', '', $fontStyle);
        $table->addCell(6750, $cellStyleHead)->addText('');


        //Vertretungen
        foreach ($vertretungen as $vertretung){
            $table->addRow();
            $table->addCell(1750, $cellStyleLeftBottom)->addText($vertretung->klasse->name, $paragraphStyle, $fontStyle);
            $table->addCell(1750, $cellStyleBorderBottom)->addText($vertretung->stunde, $paragraphStyle, $fontStyle);
            if ($vertretung->neuFach != ""){
                $table->addCell(1750, $cellStyleBorderBottom)->addText($vertretung->altFach." -> ".$vertretung->neuFach, $paragraphStyle, $fontStyle);
            } else {
                $table->addCell(1750, $cellStyleBorderBottom)->addText($vertretung->altFach, $paragraphStyle, $fontStyle);
            }
            if ($vertretung->users_id != ""){
                $table->addCell(1750, $cellStyleBorderBottom)->addText($vertretung->lehrer->kuerze, $paragraphStyle, $fontStyle);
            } else {
                $table->addCell(1750, $cellStyleBorderBottom)->addText("", $paragraphStyle, $fontStyle);
            }

            $table->addCell(6750, $cellStyleRightBottom)->addText($vertretung->comment, $paragraphStyle, $fontStyle);

        }

        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');

        $objWriter->save(storage_path('Vertretungsplan.docx'));

        return response()->download(storage_path('Vertretungsplan.docx'));
    }

    public function exportPDF (exportVertretungenRequest $request){

        return $this->generatePDF($request->startDate, $request->endDate);
    }
    public function generatePDF($startDate, $targetDate = null){

        $targetDate = Carbon::createFromFormat('Y-m-d', ($targetDate != null)? $targetDate : $startDate);
        $startDate = Carbon::createFromFormat('Y-m-d', $startDate);

        //Hole Vertretungen
        $vertretungen = Vertretung::whereBetween('date', [$startDate->startOfDay(), $targetDate->endOfDay()])
            ->orderBy('klassen_id')
            ->orderBy('stunde')
            ->get();

        //A/B Wochen
        $weeks = VertretungsplanWeek::where('week',  $startDate->copy()->startOfWeek()->format('Y-m-d'))
            ->orWhere('week', $targetDate->copy()->startOfWeek()->format('Y-m-d'))
            ->orderBy('week')
            ->get();
        //Absences
        $absences = Absence::whereDate('start', '<=', $targetDate)
            ->whereDate('end', '>=', $startDate)
            ->whereHas('user', function ($query){
                $query->whereNotNull('kuerzel');
            })
            ->where('showVertretungsplan',1)
            ->get()->unique('users_id')->sortBy('user.name');

        //News
        $news = DailyNews::query()
            ->where(function($query) use ($targetDate, $startDate){
                $query ->whereDate('date_start', '<=', $targetDate);
                $query->whereDate('date_end', '<=', $targetDate);
                $query->whereDate('date_end', '>=', $startDate);
            })
            ->orWhere(function($query) use ($targetDate, $startDate){
                $query ->whereDate('date_start', '<=', $targetDate);
                $query->whereDate('date_end', '>=', $startDate);
            })
            ->orWhere(function($query) use ($targetDate){
                $query ->whereDate('date_start', '<=', $targetDate);
                $query->whereNull('date_end');
            })
            ->orderBy('date_start')
            ->get();


        $pdf = PDF::loadView('vertretungsplan.pdf.pdf', [
            "startDate" => $startDate,
            'targetDate' => $targetDate,
            'vertretungen' => $vertretungen,
            'weeks' => $weeks,
            'absences' => $absences,
            'news'  => $news

        ]);

        return $pdf
            ->setPaper('a4', 'Landscape')
            ->setOption(
                "encoding", "utf-8"
            )
            ->download();

    }



    public function generateDay(Carbon $date){
        $vertretungen = Vertretung::whereDate('date', $date)->orderBy('klassen_id')->orderBy('stunde')->get();

    }

    public function export(exportVertretungenRequest $request){
        return Excel::download(new VertretungenExport($request->startDate, $request->endDate), 'Vertretungen.xlsx');
    }
}
