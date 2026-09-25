<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Str;
use RuntimeException;

class StudentNumberAllocator
{
    public function allocate(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = 'SK-'.now()->format('Y').'-'.Str::upper(Str::random(8));

            if (! Student::query()->where('student_number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('A unique student number could not be allocated.');
    }
}
