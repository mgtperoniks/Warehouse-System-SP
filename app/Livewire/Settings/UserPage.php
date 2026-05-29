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
    public $is_active = 1;
    public $editingId = null;
    public $search = '';    public $isEditingSystem = false;

    public function isSystemAccount($user)
    {
        if (!$user) return false;
        return in_array($user->name, ['Admin Sparepart', 'Manager PPIC', 'Auditor']) 
            || in_array($user->email, ['adminsp@peroniks.com', 'managerppic@peroniks.com', 'auditor@peroniks.com']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function save()
    {
        $isSystem = false;
        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $isSystem = $this->isSystemAccount($user);
        }

        // Dynamic validation rules
        $rules = [
            'name' => 'required|string|max:255',
        ];

        if ($isSystem) {
            $rules['email'] = 'required|email|unique:users,email,' . $this->editingId;
        } else {
            $rules['department_id'] = 'required|exists:departments,id';
            $rules['is_active'] = 'required|boolean';
        }

        $this->validate($rules);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            if ($this->isSystemAccount($user)) {
                $user->update([
                    'name' => $this->name,
                    'email' => $this->email,
                ]);
                session()->flash('message', 'System Account updated successfully.');
            } else {
                $user->update([
                    'name' => $this->name,
                    'department_id' => $this->department_id,
                    'is_active' => $this->is_active,
                ]);
                session()->flash('message', 'PIC Position updated successfully.');
            }
        } else {
            // Generate a unique email based on position name to fulfill DB constraints
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $this->name));
            $email = $slug . '@sparepart.local';
            
            $count = 1;
            while (User::where('email', $email)->exists()) {
                $email = $slug . $count . '@sparepart.local';
                $count++;
            }

            User::create([
                'name' => $this->name,
                'email' => $email,
                'department_id' => $this->department_id,
                'password' => bcrypt('password123'),
                'is_active' => $this->is_active,
            ]);
            session()->flash('message', 'PIC Position created successfully.');
        }

        $this->reset(['name', 'email', 'department_id', 'is_active', 'editingId', 'isEditingSystem']);
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->department_id = $user->department_id;
        $this->is_active = $user->is_active;
        $this->isEditingSystem = $this->isSystemAccount($user);
    }

    public function cancelEdit()
    {
        $this->reset(['name', 'email', 'department_id', 'is_active', 'editingId', 'isEditingSystem']);
    }

    public function deactivate($id)
    {
        $user = User::findOrFail($id);
        if ($this->isSystemAccount($user)) {
            session()->flash('error', 'System Accounts cannot be deactivated.');
            return;
        }
        $user->update(['is_active' => false]);
        session()->flash('message', 'PIC Position deactivated successfully.');
    }

    public function render()
    {
        return view('livewire.settings.user-page', [
            'users' => User::with('department')
                ->where(function($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                          ->orWhereHas('department', function($q) {
                              $q->where('name', 'like', '%' . $this->search . '%');
                          });
                })
                ->latest()
                ->paginate(10),
            'departments' => Department::orderBy('name')->get()
        ])->layout('layouts.app');
    }
}
