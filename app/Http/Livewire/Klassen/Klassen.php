<?php

namespace App\Http\Livewire\Klassen;

use App\Models\Klasse;
use Livewire\Component;

class Klassen extends Component
{
    public $klassen, $name, $klasse_id;
    public $updateMode = false;

    public function render()
    {
        $this->klassen = Klasse::all();

        return view('livewire.klassen.klassen');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    private function resetInputFields(){
        $this->name = '';
    }

    public function store()
    {
        $validatedDate = $this->validate([
            'name' => 'required',
        ]);

        Klasse::create($validatedDate);

        session()->flash('message', 'Klasse wurde erstellt.');

        $this->resetInputFields();
    }

    public function edit($id)
    {
        $klasse = Klasse::findOrFail($id);
        $this->klasse_id = $id;
        $this->name = $klasse->name;

        $this->updateMode = true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function cancel()
    {
        $this->updateMode = false;
        $this->resetInputFields();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function update()
    {
        $validatedDate = $this->validate([
            'name' => [
                'required',
                Rule::unique('users')->ignore($this->post_id)
                ]
        ]);

        $post = Klasse::find($this->post_id);
        $post->update([
            'name' => $this->name,
        ]);

        $this->updateMode = false;

        session()->flash('message', 'Klasse wurde bearbeitet.');
        $this->resetInputFields();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function delete($id)
    {
        Klasse::find($id)->delete();
        session()->flash('message', 'Klasse Deleted Successfully.');
    }
}
