<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class CsvUserImport extends Component
{
    use WithFileUploads;

    public bool $modalOpen = false;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $csvFile = null;

    /** @var list<string> */
    public array $rowErrors = [];

    public ?string $headerError = null;

    public int $imported = 0;

    public int $skipped = 0;

    public bool $done = false;

    public function openModal(): void
    {
        $this->resetState();
        $this->modalOpen = true;
    }

    public function updatedModalOpen(bool $value): void
    {
        if (! $value) {
            $this->resetState();
        }
    }

    public function import(): void
    {
        $this->resetValidation();
        $this->rowErrors = [];
        $this->headerError = null;

        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'csvFile.required' => 'Please choose a CSV file to upload.',
            'csvFile.mimes'    => 'The file must be a CSV (.csv).',
            'csvFile.max'      => 'The file must not exceed 1 MB.',
        ]);

        $handle = fopen($this->csvFile->getRealPath(), 'r');

        $rawHeaders = fgetcsv($handle);

        if ($rawHeaders === false) {
            $this->headerError = 'The file appears to be empty.';
            fclose($handle);

            return;
        }

        $headers = array_map(fn (string $h) => strtolower(trim($h)), $rawHeaders);
        $missing = array_diff(['name', 'email', 'role'], $headers);

        if (! empty($missing)) {
            $this->headerError = 'Missing required column(s): '.implode(', ', $missing).'. Expected: name, email, role.';
            fclose($handle);

            return;
        }

        $nameIdx  = array_search('name', $headers);
        $emailIdx = array_search('email', $headers);
        $roleIdx  = array_search('role', $headers);

        $validRoles   = array_map(fn (Role $r) => $r->value, array_filter(Role::cases(), fn (Role $r) => $r !== Role::Service));
        $validRoleStr = implode(', ', $validRoles);

        $rows       = [];
        $rowNumber  = 1;
        $hasErrors  = false;

        while (($cols = fgetcsv($handle)) !== false) {
            $rowNumber++;

            $name  = trim((string) ($cols[$nameIdx]  ?? ''));
            $email = strtolower(trim((string) ($cols[$emailIdx] ?? '')));
            $role  = strtolower(trim((string) ($cols[$roleIdx]  ?? '')));

            if ($name === '' && $email === '' && $role === '') {
                continue;
            }

            $errors = [];

            if ($name === '') {
                $errors[] = 'name is required';
            }

            if ($email === '') {
                $errors[] = 'email is required';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "\"{$email}\" is not a valid email address";
            }

            if ($role === '') {
                $errors[] = 'role is required';
            } elseif (! in_array($role, $validRoles, true)) {
                $errors[] = "role \"{$role}\" is not valid — use {$validRoleStr}";
            }

            if (! empty($errors)) {
                $this->rowErrors[] = "Row {$rowNumber}: ".ucfirst(implode('; ', $errors)).'.';
                $hasErrors = true;

                continue;
            }

            if (User::withTrashed()->where('email', $email)->exists()) {
                $this->rowErrors[] = "Row {$rowNumber}: {$email} already exists and will be skipped.";
                $rows[]            = ['skip' => true];

                continue;
            }

            $rows[] = ['name' => $name, 'email' => $email, 'role' => Role::from($role), 'skip' => false];
        }

        fclose($handle);

        if ($hasErrors) {
            return;
        }

        foreach ($rows as $row) {
            if ($row['skip']) {
                $this->skipped++;

                continue;
            }

            User::create([
                'name'  => $row['name'],
                'email' => $row['email'],
                'role'  => $row['role'],
            ]);

            $this->imported++;
        }

        $this->done = true;
    }

    public function render(): View
    {
        return view('livewire.admin.csv-user-import');
    }

    private function resetState(): void
    {
        $this->csvFile     = null;
        $this->rowErrors   = [];
        $this->headerError = null;
        $this->imported    = 0;
        $this->skipped     = 0;
        $this->done        = false;
        $this->resetValidation();
    }
}
