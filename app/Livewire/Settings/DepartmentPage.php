<?php

namespace App\Livewire\Settings;

use App\Models\Department;
use Livewire\Component;
use Livewire\WithPagination;

class DepartmentPage extends Component
{
    use WithPagination;

    public $name;
    public $code;
    public $editingId = null;
    public $search = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'code' => 'required|string|max:50|unique:departments,code',
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function save()
    {
        $rules = $this->rules;
        if ($this->editingId) {
            $rules['code'] = 'required|string|max:50|unique:departments,code,' . $this->editingId;
        }

        $this->validate($rules);

        if ($this->editingId) {
            Department::find($this->editingId)->update([
                'name' => $this->name,
                'code' => $this->code,
            ]);
            session()->flash('message', 'Department updated successfully.');
        } else {
            Department::create([
                'name' => $this->name,
                'code' => $this->code,
            ]);
            session()->flash('message', 'Department created successfully.');
        }

        $this->reset(['name', 'code', 'editingId']);
    }

    public function edit($id)
    {
        $dept = Department::findOrFail($id);
        $this->editingId = $dept->id;
        $this->name = $dept->name;
        $this->code = $dept->code;
    }

    public function cancelEdit()
    {
        $this->reset(['name', 'code', 'editingId']);
    }

    public function delete($id)
    {
        Department::destroy($id);
        session()->flash('message', 'Department deleted successfully.');
    }

    public function render()
    {
        return view('livewire.settings.department-page', [
            'departments' => Department::where('name', 'like', '%' . $this->search . '%')
                ->orWhere('code', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10)
        ])->layout('layouts.app');
    }
}
