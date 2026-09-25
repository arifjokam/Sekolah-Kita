<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function isMariaDB(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb']);
    }

    public function test_teachers_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('teachers'));
        $columns = Schema::getColumnListing('teachers');

        $expectedColumns = [
            'id', 'user_id', 'legal_name', 'preferred_name', 'employee_number',
            'employment_status', 'employment_start_date', 'employment_end_date',
            'position_title', 'qualification_summary', 'biography',
            'birth_date', 'birth_place', 'gender', 'national_id',
            'address', 'phone', 'email', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' missing from teachers table");
        }
    }

    public function test_students_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('students'));
        $columns = Schema::getColumnListing('students');

        $expectedColumns = [
            'id', 'user_id', 'legal_name', 'preferred_name', 'student_number',
            'status', 'admission_date', 'graduation_date', 'leaving_date', 'leaving_reason',
            'birth_date', 'birth_place', 'gender', 'national_id', 'nisn',
            'address', 'phone', 'email', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' missing from students table");
        }
    }

    public function test_guardians_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('guardians'));
        $columns = Schema::getColumnListing('guardians');

        $expectedColumns = [
            'id', 'legal_name', 'phone', 'email', 'address', 'national_id', 'status', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' missing from guardians table");
        }
    }

    public function test_student_guardians_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('student_guardians'));
        $columns = Schema::getColumnListing('student_guardians');

        $expectedColumns = [
            'id', 'student_id', 'guardian_id', 'relationship_type',
            'is_primary_contact', 'is_emergency_contact', 'priority_order',
            'starts_on', 'ends_on', 'relationship_note', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' missing from student_guardians table");
        }
    }

    public function test_academic_years_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('academic_years'));
    }

    public function test_terms_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('terms'));
    }

    public function test_classes_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('classes'));
    }

    public function test_subjects_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('subjects'));
    }

    public function test_class_teacher_assignments_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('class_teacher_assignments'));
    }

    public function test_teaching_assignments_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('teaching_assignments'));
    }

    public function test_enrollments_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('enrollments'));
    }

    public function test_teachers_user_id_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM teachers WHERE Key_name = "teachers_user_id_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_students_user_id_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM students WHERE Key_name = "students_user_id_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_teachers_employee_number_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM teachers WHERE Key_name = "teachers_employee_number_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_students_student_number_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM students WHERE Key_name = "students_student_number_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_academic_years_code_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM academic_years WHERE Key_name = "academic_years_code_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_terms_academic_year_sequence_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM terms WHERE Key_name = "terms_academic_year_id_sequence_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_classes_academic_year_grade_name_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM classes WHERE Key_name = "classes_academic_year_id_grade_level_name_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_subjects_code_unique(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific index test');
        }
        $indexes = DB::select('SHOW INDEX FROM subjects WHERE Key_name = "subjects_code_unique"');
        $this->assertNotEmpty($indexes);
    }

    public function test_foreign_keys_exist(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific foreign key test');
        }

        // Test teachers -> users
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "teachers" AND COLUMN_NAME = "user_id" AND REFERENCED_TABLE_NAME = "users"');
        $this->assertNotEmpty($fk);

        // Test students -> users
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "students" AND COLUMN_NAME = "user_id" AND REFERENCED_TABLE_NAME = "users"');
        $this->assertNotEmpty($fk);

        // Test student_guardians -> students
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "student_guardians" AND COLUMN_NAME = "student_id" AND REFERENCED_TABLE_NAME = "students"');
        $this->assertNotEmpty($fk);

        // Test student_guardians -> guardians
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "student_guardians" AND COLUMN_NAME = "guardian_id" AND REFERENCED_TABLE_NAME = "guardians"');
        $this->assertNotEmpty($fk);

        // Test terms -> academic_years
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "terms" AND COLUMN_NAME = "academic_year_id" AND REFERENCED_TABLE_NAME = "academic_years"');
        $this->assertNotEmpty($fk);

        // Test classes -> academic_years
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "classes" AND COLUMN_NAME = "academic_year_id" AND REFERENCED_TABLE_NAME = "academic_years"');
        $this->assertNotEmpty($fk);

        // Test class_teacher_assignments -> classes
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "class_teacher_assignments" AND COLUMN_NAME = "class_id" AND REFERENCED_TABLE_NAME = "classes"');
        $this->assertNotEmpty($fk);

        // Test class_teacher_assignments -> teachers
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "class_teacher_assignments" AND COLUMN_NAME = "teacher_id" AND REFERENCED_TABLE_NAME = "teachers"');
        $this->assertNotEmpty($fk);

        // Test teaching_assignments -> teachers
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "teaching_assignments" AND COLUMN_NAME = "teacher_id" AND REFERENCED_TABLE_NAME = "teachers"');
        $this->assertNotEmpty($fk);

        // Test teaching_assignments -> classes
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "teaching_assignments" AND COLUMN_NAME = "class_id" AND REFERENCED_TABLE_NAME = "classes"');
        $this->assertNotEmpty($fk);

        // Test teaching_assignments -> subjects
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "teaching_assignments" AND COLUMN_NAME = "subject_id" AND REFERENCED_TABLE_NAME = "subjects"');
        $this->assertNotEmpty($fk);

        // Test teaching_assignments -> academic_years
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "teaching_assignments" AND COLUMN_NAME = "academic_year_id" AND REFERENCED_TABLE_NAME = "academic_years"');
        $this->assertNotEmpty($fk);

        // Test enrollments -> students
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "enrollments" AND COLUMN_NAME = "student_id" AND REFERENCED_TABLE_NAME = "students"');
        $this->assertNotEmpty($fk);

        // Test enrollments -> classes
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "enrollments" AND COLUMN_NAME = "class_id" AND REFERENCED_TABLE_NAME = "classes"');
        $this->assertNotEmpty($fk);

        // Test enrollments -> academic_years
        $fk = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "enrollments" AND COLUMN_NAME = "academic_year_id" AND REFERENCED_TABLE_NAME = "academic_years"');
        $this->assertNotEmpty($fk);
    }

    public function test_foreign_keys_restrict_delete(): void
    {
        if (! $this->isMariaDB()) {
            $this->markTestSkipped('MariaDB-specific foreign key test');
        }

        $constraints = DB::select('
            SELECT CONSTRAINT_NAME, DELETE_RULE
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
        ');

        foreach ($constraints as $constraint) {
            if (in_array($constraint->CONSTRAINT_NAME, [
                'teachers_user_id_foreign',
                'students_user_id_foreign',
                'student_guardians_student_id_foreign',
                'student_guardians_guardian_id_foreign',
                'terms_academic_year_id_foreign',
                'classes_academic_year_id_foreign',
                'class_teacher_assignments_class_id_foreign',
                'class_teacher_assignments_teacher_id_foreign',
                'teaching_assignments_teacher_id_foreign',
                'teaching_assignments_class_id_foreign',
                'teaching_assignments_subject_id_foreign',
                'teaching_assignments_academic_year_id_foreign',
                'enrollments_student_id_foreign',
                'enrollments_class_id_foreign',
                'enrollments_academic_year_id_foreign',
            ])) {
                $this->assertEquals('RESTRICT', $constraint->DELETE_RULE, "Constraint {$constraint->CONSTRAINT_NAME} should RESTRICT delete");
            }
        }
    }
}
