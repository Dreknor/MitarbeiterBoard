<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSurveyRequest;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyUserAnswer;
use App\Models\Theme;
use Illuminate\Http\Request;

class SurveyController extends Controller
{

    /**
     * Show the form for creating a new resource.
     */
    public function create($groupname, Theme $theme)
    {
        return view('surveys.create', compact('theme'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateSurveyRequest $request, $groupname, Theme $theme)
    {
        $group = auth()->user()->groups()->where('name', $groupname)->first();
        if (!$group) {
            return redirect('/')->with([
                'Meldung' => 'Kein Gruppenmitglied',
                'type' => 'danger'
            ]);
        }

        if (!$group->themes->contains($theme)) {
            return redirect()->route('themes.index', $groupname)->with([
                'Meldung' => 'Kein Gruppenmitglied',
                'type' => 'danger'
            ]);
        }

        if ($theme->completed) {
            return redirect()->route('themes.index', $groupname)->with([
                'Meldung' => 'Thema ist bereits abgeschlossen',
                'type' => 'danger'
            ]);
        }



        $survey = new Survey(
            $request->validated()
        );

        $survey->theme_id = $theme->id;
        $survey->created_by = auth()->id();

        $survey->save();



        return redirect()->route('survey.show', [
            'groupname' => $group->name,
            'theme' => $theme->id,
            'survey' => $survey->id
        ])->with([
            'Meldung' => 'Umfrage wurde erstellt. Bitte Fragen hinzufügen.',
            'type' => 'success']);
    }

    /**
     * Display the specified resource.
     */
    public function show($groupname, Theme $theme, Survey $survey)
    {
        $group = auth()->user()->groups()->where('name', $groupname)->first();
        if (!$group) {
            return redirect('/')->with([
                'Meldung' => 'Kein Gruppenmitglied',
                'type' => 'danger'
            ]);
        }

        if (!$group->themes->contains($theme)) {
            return redirect()->route('themes.index', $groupname)->with([
                'Meldung' => 'Kein Gruppenmitglied',
                'type' => 'danger'
            ]);
        }

        if ($theme->completed) {
            return redirect()->route('themes.index', $groupname)->with([
                'Meldung' => 'Thema ist bereits abgeschlossen',
                'type' => 'danger'
            ]);
        }

        if ($survey->end_date < now()) {
            return redirect()->back()->with([
                'Meldung' => 'Umfrage ist nicht aktiv',
                'type' => 'danger'
            ]);
        }


        if ($theme->id != $survey->theme_id) {
            return redirect()->route('themes.index', $groupname)->with([
                'Meldung' => 'Umfrage gehört nicht zum Thema',
                'type' => 'danger'
            ]);
        }

        return view('surveys.show', [
            'group' => $group,
            'theme' => $theme,
            'survey' => $survey
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Survey $survey)
    {

        return view('surveys.update', [
            'survey' => $survey,
            'theme' => $survey->theme
        ]);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateSurveyRequest $request, Survey $survey)
    {
        $survey->update($request->validated());
        return redirect()->route('survey.show', [
            'groupname' => $survey->theme->group->name,
            'theme' => $survey->theme->id,
            'survey' => $survey->id
        ])->with([
            'Meldung' => 'Umfrage wurde aktualisiert',
            'type' => 'success'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Survey $survey)
    {
        $theme = $survey->theme;
        $survey->delete();
        return redirect(url($theme->group->name.'/themes/'.$theme->id))->with([
            'Meldung' => 'Umfrage wurde gelöscht',
            'type' => 'success'
        ]);
    }

    public function storeQuestion(Request $request)
    {
        $request->validate([
            'question' => 'required',
            'type' => 'required'
        ]);

        $question = new SurveyQuestion([
            'question' => $request->question,
            'type' => $request->type,
            'survey_id' => $request->survey_id,
        ]);
        $question->save();

        return redirect()->back()->with([
            'Meldung' => 'Frage wurde hinzugefügt',
            'type' => 'success'
        ]);
    }

    public function destroyQuestion(Survey $survey, SurveyQuestion $question)
    {
        $question->delete();
        return redirect()->back()->with([
            'Meldung' => 'Frage wurde gelöscht',
            'type' => 'success'
        ]);
    }

    public function storeAnswer(Request $request, Survey $survey, SurveyQuestion $question)
    {
        $request->validate([
            'answer' => 'required',
            'question_id' => 'required',
        ]);

        if ($question->survey_id != $survey->id) {
            return redirect()->back()->with([
                'Meldung' => 'Frage gehört nicht zur Umfrage',
                'type' => 'danger'
            ]);
        }

        if ( $survey->end_date < now()) {
            return redirect()->back()->with([
                'Meldung' => 'Umfrage ist nicht aktiv',
                'type' => 'danger'
            ]);
        }

        if ($question->survey_id != $survey->id) {
            return redirect()->back()->with([
                'Meldung' => 'Frage gehört nicht zur Umfrage',
                'type' => 'danger'
            ]);
        }



        $answer = new SurveyAnswer([
            'answer' => $request->answer,
            'question_id' => $question->id,
            'survey_id' => $survey->id,
            'user_id' => auth()->id()
        ]);
        $answer->save();

        return redirect()->back()->with([
            'Meldung' => 'Antwort wurde hinzugefügt',
            'type' => 'success'
        ]);
    }

    public function destroyAnswer(SurveyAnswer $answer)
    {
        $answer->delete();
        return redirect()->back()->with([
            'Meldung' => 'Antwort wurde gelöscht',
            'type' => 'success'
        ]);
    }

    public function questionAddAnswer(SurveyQuestion $question)
    {
        return view('surveys.addAnswer', compact('question'));
    }

    public function answer(Survey $survey, Request $request)
    {

$answers = $request->all();
        $answers = array_slice($answers, 1);
        $user_answers = [];
        foreach ($answers as $key => $value) {
            $key = explode('_', $key)[1];

            if (is_array($value)) {
                foreach ($value as $v) {
                    $user_answers[] = [
                        'answer' => $v,
                        'question_id' => $key,
                        'survey_id' => $survey->id,
                        'user_id' => auth()->id()
                    ];
                }
            } else {
                if ($value != null) {
                    $user_answers[] = [
                        'answer' => $value,
                        'question_id' => $key,
                        'survey_id' => $survey->id,
                        'user_id' => auth()->id()
                    ];
                }
            }
        }

        SurveyUserAnswer::insert($user_answers);
        return redirect()->back()->with([
            'Meldung' => 'Umfrage wurde abgeschlossen',
            'type' => 'success'
        ]);


    }
}
