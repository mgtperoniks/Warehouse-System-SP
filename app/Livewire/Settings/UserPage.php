<?php

namespace App\Livewire\Settings;

use App\Models\Department;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UserPage extends Component
{
    use WithPagination;

    public $name;
    public $email;
    public $department_id;
    public $editingId = null;
    public $search = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'department_id' => 'required|exists:departments,id',
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function save()
    {
        $rules = $this->rules;
        if ($this->editingId) {
            $rules['email'] = 'required|email|unique:users,email,' . $this->editingId;
        }

        $this->validate($rules);

        if ($this->editingId) {
            User::find($this->editingId)->update([
                'name' => $this->name,
                'email' => $this->email,
                'department_id' => $this->department_id,
            ]);
            session()->flash('message', 'User updated successfully.');
        } else {
            // Default password for new PICs
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'department_id' => $this->department_id,
                'password' => bcrypt('password123'),
            ]);
            session()->flash('message', 'User created successfully.');
        }

        $this->reset(['name', 'email', 'department_id', 'editingId']);
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->department_id = $user->department_id;
    }

    public function cancelEdit()
    {
        $this->reset(['name', 'email', 'department_id', 'editingId']);
    }

    public function delete($id)
    {
        User::destroy($id);
        session()->flash('message', 'User deleted successfully.');
    }

    public function render()
    {
        return view('livewire.settings.user-page', [
            'users' => User::with('department')
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10),
            'departments' => Department::orderBy('name')->get()
        ])->layout('layouts.app');
    }
}
