<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CsvUserImport extends Component
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $csvFile = null;

    /** @var list<array{line: int, name: string, email: string, role: string}> */
    public array $rows = [];

    /** @var array<int, array{name?: string, email?: string, role?: string}> */
    public array $cellErrors = [];

    /** @var array<int, array{email?: string}> */
    public array $cellWarnings = [];

    /** @var list<string> */
    public array $missingColumns = [];

    /** @var list<string> */
    public array $rowErrors = [];

    public ?string $headerError = null;

    public int $imported = 0;

    public int $skipped = 0;

    public bool $done = false;

    private const MAX_ROWS = 500;

    private const MAX_FIELD_LENGTH = 255;

    public function mount(): void
    {
        $this->resetState();
    }

    public function updatedCsvFile(): void
    {
        $this->resetGridState(clearFile: false);
        $this->resetValidation();

        $this->validateFileUpload();

        $this->parseUploadedCsv();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'rows.')) {
            $this->validateRows();
        }
    }

    public function import(): void
    {
        $this->resetValidation();
        $this->rowErrors = [];

        $this->validateFileUpload();

        if ($this->rows === [] && $this->headerError === null) {
            $this->parseUploadedCsv();
        }

        $this->validateRows();

        if ($this->headerError !== null || $this->rows === [] || $this->hasBlockingErrors()) {
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->rows as $row) {
                $row = $this->normalizedRow($row);

                if (User::withTrashed()->where('email', $row['email'])->exists()) {
                    $this->rowErrors[] = "Row {$row['line']}: {$row['email']} already exists and was skipped.";
                    $this->skipped++;

                    continue;
                }

                User::create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => Role::from($row['role']),
                ]);

                $this->imported++;
            }
        });

        $this->done = true;
    }

    public function render(): View
    {
        return view('livewire.admin.csv-user-import');
    }

    public function hasBlockingErrors(): bool
    {
        return $this->issueCount() > 0;
    }

    public function issueCount(): int
    {
        return array_sum(array_map('count', $this->cellErrors));
    }

    public function warningCount(): int
    {
        return array_sum(array_map('count', $this->cellWarnings));
    }

    public function canImport(): bool
    {
        return $this->csvFile !== null
            && $this->rows !== []
            && $this->headerError === null
            && ! $this->hasBlockingErrors();
    }

    /**
     * @return list<string>
     */
    public function validRoleValues(): array
    {
        return array_values(array_map(fn (Role $role) => $role->value, Role::cases()));
    }

    private function validateFileUpload(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'csvFile.required' => 'Please choose a CSV file to upload.',
            'csvFile.mimes' => 'The file must be a CSV (.csv).',
            'csvFile.max' => 'The file must not exceed 1 MB.',
        ]);
    }

    private function parseUploadedCsv(): void
    {
        $handle = fopen($this->csvFile->getRealPath(), 'r');

        if ($handle === false) {
            $this->headerError = 'The file could not be opened.';

            return;
        }

        $rawHeaders = fgetcsv($handle);

        if ($rawHeaders === false) {
            $this->headerError = 'The file appears to be empty.';
            fclose($handle);

            return;
        }

        if (isset($rawHeaders[0]) && is_string($rawHeaders[0])) {
            $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeaders[0]) ?? $rawHeaders[0];
        }

        $headers = array_map(fn (mixed $header) => strtolower(trim((string) $header)), $rawHeaders);
        $this->missingColumns = array_values(array_diff(['name', 'email', 'role'], $headers));

        $nameIdx = array_search('name', $headers, true);
        $emailIdx = array_search('email', $headers, true);
        $roleIdx = array_search('role', $headers, true);

        $rowNumber = 1;

        while (($cols = fgetcsv($handle)) !== false) {
            $rowNumber++;

            $row = [
                'line' => $rowNumber,
                'name' => $this->csvColumnValue($cols, $nameIdx),
                'email' => strtolower($this->csvColumnValue($cols, $emailIdx)),
                'role' => strtolower($this->csvColumnValue($cols, $roleIdx)),
            ];

            if ($row['name'] === '' && $row['email'] === '' && $row['role'] === '') {
                continue;
            }

            $this->rows[] = $row;

            if (count($this->rows) >= self::MAX_ROWS) {
                break;
            }
        }

        fclose($handle);

        if ($this->rows === []) {
            $this->headerError = 'No user rows were found in the CSV.';

            return;
        }

        $this->validateRows();
    }

    /**
     * @param  array<int, string|null>  $cols
     */
    private function csvColumnValue(array $cols, int|false $index): string
    {
        if ($index === false) {
            return '';
        }

        return trim((string) ($cols[$index] ?? ''));
    }

    private function validateRows(): void
    {
        $this->cellErrors = [];
        $this->cellWarnings = [];

        $validRoles = $this->validRoleValues();
        $validRoleText = implode(', ', $validRoles);
        $seenEmails = [];

        foreach ($this->rows as $index => $row) {
            $row = $this->normalizedRow($row);

            if ($row['name'] === '') {
                $this->cellErrors[$index]['name'] = 'Name is required.';
            } elseif (mb_strlen($row['name']) > self::MAX_FIELD_LENGTH) {
                $this->cellErrors[$index]['name'] = 'Name must be 255 characters or fewer.';
            }

            if ($row['email'] === '') {
                $this->cellErrors[$index]['email'] = 'Email is required.';
            } elseif (mb_strlen($row['email']) > self::MAX_FIELD_LENGTH) {
                $this->cellErrors[$index]['email'] = 'Email must be 255 characters or fewer.';
            } elseif (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $this->cellErrors[$index]['email'] = 'Enter a valid email address.';
            } else {
                if (array_key_exists($row['email'], $seenEmails)) {
                    $previousIndex = $seenEmails[$row['email']];

                    $this->cellErrors[$index]['email'] = 'Email is duplicated in this CSV.';
                    $this->cellErrors[$previousIndex]['email'] = 'Email is duplicated in this CSV.';
                    unset($this->cellWarnings[$previousIndex]['email']);
                } else {
                    $seenEmails[$row['email']] = $index;
                }

                if (! isset($this->cellErrors[$index]['email']) && User::withTrashed()->where('email', $row['email'])->exists()) {
                    $this->cellWarnings[$index]['email'] = 'Existing user will be skipped.';
                }
            }

            if ($row['role'] === '') {
                $this->cellErrors[$index]['role'] = 'Role is required.';
            } elseif (! in_array($row['role'], $validRoles, true)) {
                $this->cellErrors[$index]['role'] = "Use {$validRoleText}.";
            }
        }
    }

    /**
     * @param  array{line?: int, name?: string, email?: string, role?: string}  $row
     * @return array{line: int, name: string, email: string, role: string}
     */
    private function normalizedRow(array $row): array
    {
        return [
            'line' => (int) ($row['line'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
            'email' => strtolower(trim((string) ($row['email'] ?? ''))),
            'role' => strtolower(trim((string) ($row['role'] ?? ''))),
        ];
    }

    private function resetState(): void
    {
        $this->resetGridState();
        $this->resetValidation();
    }

    private function resetGridState(bool $clearFile = true): void
    {
        if ($clearFile) {
            $this->csvFile = null;
        }

        $this->rows = [];
        $this->cellErrors = [];
        $this->cellWarnings = [];
        $this->missingColumns = [];
        $this->rowErrors = [];
        $this->headerError = null;
        $this->imported = 0;
        $this->skipped = 0;
        $this->done = false;
    }
}
