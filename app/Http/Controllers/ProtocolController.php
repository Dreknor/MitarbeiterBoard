<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProtocolRequest;
use App\Mail\newProtocolForTask;
use App\Mail\newTaskMail;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\Theme;
use App\Notifications\Push;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table;

class ProtocolController extends Controller
{

    /**
     * @param Theme $theme
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create($groupname, Theme $theme)
    {
        if ($theme->completed ==1){
            return  redirect()->back()->with([
               'type' => 'warning',
               'Meldung'=> 'Thema bereits geschlossen'
            ]);
        }

        return view('protocol.create', [
           'theme'  => $theme,
       ]);
    }

    /**
     * @param Protocol $protocol
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($groupname, Protocol $protocol)
    {
        if ($protocol->theme->completed ==1){
            return  redirect()->back()->with([
                'type' => 'warning',
                'Meldung'=> 'Thema bereits geschlossen'
            ]);
        }

        return view('protocol.edit', [
           'theme'  => $protocol->theme,
           'protocol'  => $protocol,
       ]);
    }

    public function update($groupname, ProtocolRequest $request, Protocol $protocol)
    {
        if ($protocol->theme->completed ==1){
            return  redirect()->back()->with([
                'type' => 'warning',
                'Meldung'=> 'Thema bereits geschlossen'
            ]);
        }

        $changes = $request->validated();
        $changes['creator_id']=auth()->id();
        $protocol->update($changes);

        if ($request->completed == 1) {
            $protocol->theme->update([
                'completed' => 1,
            ]);

            $protocol = new Protocol([
                'creator_id' => auth()->id(),
                'theme_id'   => $protocol->theme->id,
                'protocol'   => 'Thema geschlossen',
            ]);
            $protocol->save();

            return redirect(url($groupname.'/themes'))->with([
                'type'  => 'success',
                'Meldung'=> 'Protokoll gespeichert und Thema geschlossen',
            ]);
        }

        return redirect(url($groupname.'/themes/'.$protocol->theme_id))->with([
            'type'  => 'success',
            'Meldung'   => 'Protokoll geändert',
        ]);
    }


    /**
     * @param ProtocolRequest $request
     * @param Theme $theme
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     */
    public function store( $groupname ,Theme $theme,ProtocolRequest $request,)
    {
        if ($theme->completed ==1){
            return  redirect()->back()->with([
                'type' => 'warning',
                'Meldung'=> 'Thema bereits geschlossen'
            ]);
        }

        $protocol = new Protocol([
           'creator_id' => auth()->id(),
           'theme_id'   => $theme->id,
           'protocol'   => $request->protocol,
        ]);
        $protocol->save();

        if ($theme->type->type == 'Aufgabe' and $theme->creator_id != auth()->id()) {
            $user = auth()->user()->name;
            $ersteller = $theme->ersteller;
            Notification::send($ersteller, new Push('neues Protokoll', 'Thema: '.$theme->theme));
            Mail::to($ersteller)->queue(new newProtocolForTask($user, $theme, $groupname, $protocol));
        }

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $protocol
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        if ($request->completed == 1) {
            $theme->update([
                'completed' => 1,
            ]);

            $protocol = new Protocol([
                'creator_id' => auth()->id(),
                'theme_id'   => $theme->id,
                'protocol'   => 'Thema geschlossen',
            ]);
            $protocol->save();

            return redirect(url($theme->group->name.'/themes'))->with([
                'type'  => 'success',
                'Meldung'=> 'Protokoll gespeichert und Thema geschlossen',
            ]);
        }

        return redirect(url($theme->group->name.'/themes/'.$theme->id))->with([
            'type'  => 'success',
            'Meldung'=> 'Protokoll gespeichert',
        ]);
    }


    /**
     * @param $groupname
     * @param $date
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function showDailyProtocol($groupname, $date = '')
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect(url('home'))->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($date != '') {
            $date = Carbon::createFromFormat('Y-m-d', $date);
        } else {
            $date = Carbon::now();
        }



        $themes = $group->themes()->WhereHas('protocols', function ($query) use ($date) {
            $query->whereDate('created_at', '=', $date);
        })->get();

        $dates = Protocol::query()->WhereHas('theme', function ($query) use ($group) {
            $query->where('group_id', '=', $group->id);
            })
            ->whereNot('protocol', 'LIKE', '%Verschoben zum%')
            ->whereBetween('created_at', [Carbon::now()->subMonths(2)->format('Y-m-d'), Carbon::now()])
            ->orderBy('created_at', 'DESC')->get();
        $dates = array_keys($dates->groupBy(function($item)
        {
            return $item->created_at->format('Y-m-d');
        })->toArray());


        $themes->load(['group', 'protocols']);

        $presences = $group->presences()->where('date', $date->format('Y-m-d'))->get();

        return view('protocol.export')->with([
            'themes'    => $themes,
            'date'  => $date,
            'dates' => $dates,
            'group' => $group,
            'presences' => $presences,
        ]);
    }


    /**
     * @param Request $request
     * @param $groupname
     * @param $date
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function createSheet(Request $request, $groupname, $date = '')
    {
        ($request->closed == "on")? $closed = true : $closed=false;
        ($request->changed == "on")? $changed = true : $changed=false;
        ($request->memory == "on")? $memory = true : $memory=false;

        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect(url('home'))->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($date != '') {
            $date = Carbon::createFromFormat('Y-m-d', $date);
        } else {
            $date = Carbon::now();
        }

        $themes = $group->themes()->WhereHas('protocols', function ($query) use ($date) {
            $query->whereDate('created_at', '=', $date);
        })->get();

        $themes->load(['group', 'protocols']);

        $presences = $group->presences()->where('date', $date->format('Y-m-d'))->get();


        $protocolCreator = $themes->first()->protocols->first()->ersteller;



        $sectionStyle = array(
            'marginTop' => Converter::cmToTwip(3),
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
            'marginBottom' => Converter::cmToTwip(1.5)
        );

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::DE_DE));
        $phpWord->setDefaultFontName('MetaPro-Normal');

        /* Note: any element you append to a document must reside inside of a Section. */

        // Adding an empty Section to the document...
        $section = $phpWord->addSection($sectionStyle);

        // Add first page header
        $header = $section->addHeader();
        $table = $header->addTable();
        $table->addRow();
        $table->addCell(8000)->addText('Protokoll', ['bold'=>true,'size'=>25]);
        if (file_exists(asset('img/'.config('app.logo'))) ){
            $table->addCell(1000)->addImage(asset('img/'.config('app.logo')), array('height' => 40, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END));
        } else {
            $table->addCell(1000)->addText('');
        }

        // Add footer
        $footer = $section->addFooter();
        $footer->addPreserveText('Seite {PAGE} von {NUMPAGES}.', null, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));

        $cellStyleHead = [
            'BorderSize' => 1,
            'bgColor' => 'B0CFFE',
            'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            'valign'=> 'center',
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
        $TableStyle = array(
            'cellMargin' => 80,
            'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            'width'=> Converter::cmToTwip(17),
            'borderSize' => 1,
        );

        //Kopftabelle
        $tableHead = $section->addTable($TableStyle);
        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Gremium');
        $tableHead->addCell(Converter::cmToTwip(12), ['gridSpan'=>2])->addText($group->name);

        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Datum:');
        $tableHead->addCell(Converter::cmToTwip(12), ['gridSpan'=>2])->addText($date->format('d.m.Y'));

        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Teilnehmer:');
        //Teilnehmertabelle
        $teilnehmer = "";

        foreach ($presences as $presence){
            if ($presence->user_id != null and $presence->presence){
                $teilnehmer .= $presence->user->name;
                if ($presence->online) {
                    $teilnehmer .= ' (online)';
                }
                $teilnehmer .= ', ';
            }
        }
        $tableHead->addCell(Converter::cmToTwip(12))->addText($teilnehmer);
        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Entschuldigt:');

        //Teilnehmertabelle
        $entschuldigt = "";

        foreach ($presences as $presence){
            if ($presence->user_id != null and $presence->excused){
                $entschuldigt .= $presence->user->name.', ';
            }
        }



        $tableHead->addCell(Converter::cmToTwip(12))->addText($entschuldigt);


        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Gäste:');

        //Gästetabelle
        $gaeste = "";

        foreach ($presences as $presence){
            if ($presence->user_id == null){
                $gaeste .= $presence->guest_name.', ';
            }
        }

        $tableHead->addCell(Converter::cmToTwip(12))->addText($gaeste);

        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Protokoll:');
        $tableHead->addCell(Converter::cmToTwip(12))->addText($protocolCreator->name);

        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Nächstes Treffen:');
        $tableHead->addCell(Converter::cmToTwip(12), ['gridSpan'=>2]);


        $section->addText('');

        //$Protokolltabelle
        //$phpWord->addTableStyle($TableStyleName, $TableStyle );
        $table = $section->addTable($TableStyle);
        //TableHeader
        $table->addRow();
        $table->addCell(Converter::cmToTwip(4), $cellStyleHead)->addText('Thema', '', $fontStyle);
        $table->addCell(Converter::cmToTwip(13), $cellStyleHead)->addText('Protokoll', '', $fontStyle);
        //$table->addCell(1750, $cellStyleHead)->addText('Aufgabe', '', $fontStyle);

        //Pthemen
        foreach ($themes as $theme){

            //Protokolle für Thema laden
            $protocols= $theme->protocols->filter(function ($protocol) use ($date, $closed, $memory, $changed){
                if ($protocol->created_at->format('Y-m-d') == $date->format('Y-m-d')){
                    if (
                        (!$protocol->isMemory() or $memory == true) and
                        (!$protocol->isClosed() or $closed == true) and
                        (!$protocol->isChanged() or $changed == true)
                    ){
                        return $protocol;
                    }
                }
            });

            #TODO add Tasks


            if ($protocols->count() >0 ){
                $table->addRow(null, ['cantSplit'=> true]);
                $table->addCell(Converter::cmToTwip(4), ['borderSize' => 1])->addText($theme->theme, $paragraphStyle, $fontStyle);
                $cell = $table->addCell(Converter::cmToTwip(13), ['borderSize' => 1]);
                foreach ($protocols as $protocol){
                    $string = str_replace(' & ', ' und ',$protocol->protocol);
                    \PhpOffice\PhpWord\Shared\Html::addHtml($cell, $string );
                }
                //$table->addCell(1750, $cellStyleRightBottom)->addText('');

            }

        }

        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');

        $filename=Carbon::now()->format('Ymd_Hi').'_Protokoll_'.$groupname.'.docx';

        $objWriter->save(storage_path($filename));

        return response()->download(storage_path($filename));
    }
}
