<?php

namespace App\Http\Controllers;

use App\Http\Requests\createWPRequest;
use App\Models\Group;
use App\Models\Klasse;
use App\Models\Wochenplan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WochenplanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        $wochenplaene = $group->wochenplaene()->paginate(6);

        return response()->view('wochenplan.index',[
            'wochenplaene' => $wochenplaene
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return response()->view('wochenplan.create', [
            'klassen' => Klasse::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store($groupname, createWPRequest $request)
    {
        $group = Group::where('name', $groupname)->first();

        $wochenplan = new Wochenplan($request->validated());
        $wochenplan->group_id = $group->id;
        $wochenplan->save();

        $klassen = $request->input('klassen');

        $klassen = Klasse::whereIn('id', $klassen)->get();
        $wochenplan->klassen()->attach($klassen);

        return redirect(url($groupname.'/wochenplan'))->with([
            'type' => 'success',
            'Meldung'   => 'Wochenplan wurde erstellt.'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\Response
     */
    public function show($groupname, Wochenplan $wochenplan)
    {
        return response()->view('wochenplan.editWP', [
            "wochenplan" => $wochenplan
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\Response
     */
    public function edit(Wochenplan $wochenplan)
    {
        //TODO
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Wochenplan $wochenplan)
    {
        //TODO
    }

    /**
     *
     */
    public function addFile(Request $request, Wochenplan $wochenplan)
    {
        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $wochenplan
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        return redirect()->back()->with([
           'type' => 'success',
           'Meldung'=> 'Datei angehangen'
        ]);
    }

    /**
     *
     */
    public function removeFile(Request $request, Media $media)
    {
        $media->delete();

        return redirect()->back()->with([
           'type' => 'success',
           'Meldung'=> 'Datei gelöscht'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Wochenplan  $wochenplan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Wochenplan $wochenplan)
    {
        $media = $wochenplan->getMedia();
        foreach ($media as $medium){
            $medium->delete();
        }

        $url = $wochenplan->group->name."/wochenplan";
        $wochenplan->delete();

        return redirect(url($url))->with([
            'type' => 'success',
            'Meldung'=> 'Wochenplan gelöscht'
        ]);
    }


    public function copy(){
        //TODO
    }

    public function export(Wochenplan $wochenplan)
    {


        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::DE_DE));
        $phpWord->setDefaultFontName('Comic Sans');

        //Klassen
        $klassen = "";
        foreach ($wochenplan->klassen as $key => $klasse) {
            $klassen .= $klasse->name;
            if ($key != array_key_last($wochenplan->klassen->toArray())){
                $klassen .= ", ";
            }
        }

        //Styles

        $fontStyle = [
           'size' => 15,
            'bold'  => false,
            'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE
        ];

        $page_margins=  array(
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
            'marginTop' => Converter::cmToTwip(2),
            'marginBottom' => Converter::cmToTwip(2));


        //Überschrift
        $section = $phpWord->addSection($page_margins);
        $section_style = $section->getStyle();

        $position =
            $section_style->getPageSizeW()
            - $section_style->getMarginRight()
            - $section_style->getMarginLeft();



        $phpWord->addParagraphStyle("leftRight", [
            "tabs" => [
                new \PhpOffice\PhpWord\Style\Tab("right", $position)
            ],
        ]);

        $section->addText($wochenplan->name. ' vom '.$wochenplan->gueltig_ab->format('d.m.').' bis '.$wochenplan->gueltig_bis->format('d.m.Y')."\t". $klassen, $fontStyle, "leftRight");

        //Name
        $section->addText('Name: ........................................................', ['size' => 14], ['spaceBefore' => 240]);
        $section->addText('');


        //Tabelle

        $table = $section->addTable([
            'borderSize' => 0,
            'borderColor' => '3D3D3D',

            'intend' => TblWidth::TWIP
        ]);
        $table->addRow();

        if ($wochenplan->hasDuration) {
            $columnWidth = [
                3, 9, 1.7, 0.75, 3
            ];
        } else {
            $columnWidth = [
                3, 8.5, 0, 1, 4.5
            ];
        }

            $table->addCell( Converter::cmToTwip($columnWidth[0]) )->addText('');
            $table->addCell(Converter::cmToTwip($columnWidth[1]))->addText('Aufgaben');
            if ($wochenplan->hasDuration) {
                $table->addCell(Converter::cmToTwip($columnWidth[2]))->addText('Dauer');
            }
            $table->addCell(Converter::cmToTwip($columnWidth[3]))->addImage(
                asset('img/check.png'),
                array(
                    'width'         => 10,
                    'height'        => 10,
                    'wrappingStyle' => 'behind',
                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                    'margin-top'    => 50
                ));

            $table->addCell(Converter::cmToTwip($columnWidth[4]))->addText('Unterschrift');


        foreach ($wochenplan->rows as $row){
            $table->addRow();
            $table->addCell(Converter::cmToTwip($columnWidth[0]), ['valign'=>'center'])->addText($row->name, ['bold' => true, 'size' => 9], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
            $cell1 = $table->addCell(Converter::cmToTwip($columnWidth[1]));

            if ($wochenplan->hasDuration) {
                $table->addCell(Converter::cmToTwip($columnWidth[2]))->addText('');
            }

            $table->addCell(Converter::cmToTwip($columnWidth[3]));



            $cell2 = $table->addCell(Converter::cmToTwip($columnWidth[4]));
            foreach ($row->tasks as $key => $task){

                //Aufgabe
                $string = str_replace(' & ', ' und ',$task->task);
                \PhpOffice\PhpWord\Shared\Html::addHtml($cell1, $string);

                    if ($key != array_key_last($row->tasks->toArray())){
                        $cell1->addText('_______________________________________________',['size' => 10],['spaceBefore'=>0, 'spaceAfter'=>150]);
                    }

                //Unterschrift
                $cell2->addText('..........', [], ['spaceBefore' => ((count($wochenplan->rows)-1) * 120)+240]);
            }

        }

        $section->addText('');

        if ($wochenplan->bewertung > 0){
            $section->addText('Wie hast du gearbeitet?');
            $table = $section->addTable(['borderSize' => 1, 'borderColor' => '3D3D3D', 'cellMargin'=>50]);
            $row = $table->addRow();

            //Smilie
            switch ($wochenplan->bewertung){
                case 1:
                    $row->addCell(3300, ['valign'=>'center'],)->addImage(
                        asset('img/smile0.png'),
                        array(
                            'width'         => 15,
                            'height'        => 15,
                            'wrappingStyle' => 'behind',
                            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                        ));
                    $row->addCell(3300, ['valign'=>'center'])->addImage(
                        asset('img/smile1.png'),
                        array(
                            'width'         => 15,
                            'height'        => 15,
                            'wrappingStyle' => 'behind',
                            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                            'margin-top'    => 50
                        ));
                    $row->addCell(3300, ['valign'=>'center'])->addImage(
                        asset('img/smile.png'),
                        array(
                            'width'         => 15,
                            'height'        => 15,
                            'wrappingStyle' => 'behind',
                            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                            'margin-top'    => 50
                        ));
                    break;
                case 2:
                    for ($x=1; $x<=10; $x++)
                    $row->addCell(1000, ['valign'=>'center'])->addText($x, [], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spacingBefore' => 240]);
                    break;
            }

        }


        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');

        $filename = 'Wochenplan.docx';
        $objWriter->save(storage_path($filename));

        return response()->download(storage_path($filename));
    }
}
