<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Questionnairres;
use App\Services\QuestionnaireServices;
use Illuminate\Support\Facades\Auth;
use QuestionnaireComponents;

class QuestionnaireController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $questionnaires = $user->questionnaires;

        return Inertia::render('Questionnaire/Index', [
            'questionnaires' => $questionnaires
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $questionnaire = Questionnairres::create([
            'name' => $request->name,
            'description' => $request->description,
            'band_id' => $request->band_id
        ]);

        return redirect('/questionnaire/' . $questionnaire->slug);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Questionnairres $questionnaire)
    {
        // dd($questionnaire);
        return Inertia::render('Questionnaire/Edit', ['questionnaire' => $questionnaire, 'questionnaireData' => $questionnaire->components]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addQuestion(Questionnairres $questionnaire, Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'type' => 'required'
        ]);

        $service = new QuestionnaireServices($questionnaire);

        $service->addNewQuestion($request->name, $request->type);

        return redirect()->back()->with('successMessage', 'Question Added');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
