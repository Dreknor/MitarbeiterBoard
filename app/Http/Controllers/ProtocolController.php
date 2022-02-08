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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table;

class ProtocolController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
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

        $protocol->update($request->validated());

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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store($groupname, ProtocolRequest $request, Theme $theme)
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
            Notification::send(auth()->user(), new Push('neues Protokoll', 'Thema: '.$theme->theme));
            Mail::to($ersteller)->queue(new newProtocolForTask($user, $theme->theme));
        }

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $theme
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

        $themes->load(['group', 'protocols']);

        return view('protocol.export')->with([
            'themes'    => $themes,
            'date'  => $date,
        ]);
    }
    /**
     * make paper protocol
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
        $table->addCell(1000)->addImage(asset('img/'.config('app.logo')), array('height' => 40, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END));

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
        $tableHead->addCell(Converter::cmToTwip(12));
        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Es fehlen:');
        $tableHead->addCell(Converter::cmToTwip(12));


        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Gäste:');
        $tableHead->addCell(Converter::cmToTwip(12), ['gridSpan'=>2]);

        $tableHead->addRow();
        $tableHead->addCell(Converter::cmToTwip(5))->addText('Protokoll:');
        $tableHead->addCell(Converter::cmToTwip(12), ['gridSpan'=>2])->addText($protocolCreator->name);

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

        //Vertretungen
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

            if ($protocols->count()>0){
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

        $filename='Protocol.docx';

        $objWriter->save(storage_path($filename));

        return response()->download(storage_path($filename));
    }
}
