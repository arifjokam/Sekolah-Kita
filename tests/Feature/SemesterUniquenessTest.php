<?php

namespace Tests\Feature;

use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Semester uniqueness inside one academic year.
 *
 * The domain rule is that a semester is identified by
 * (academic_year_id, sequence): Semester 1 and Semester 2 coexist in the same
 * year, a repeated sequence in the same year does not, and two different years
 * each get their own Semester 1 and 2.
 *
 * These cases exist because that rule was previously broken in the *update* path:
 * the composite uniqueness rule was attached to `academic_year_id` instead of
 * `sequence`, so editing the second semester of a year was rejected as a
 * duplicate academic year.
 */
class SemesterUniquenessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AcademicYear $year, int $sequence, string $starts, string $ends, TermStatus $status = TermStatus::Draft): array
    {
        return [
            'academic_year_id' => $year->getKey(),
            'sequence' => $sequence,
            'label' => 'Semester '.$sequence,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'status' => $status->value,
        ];
    }

    public function test_semester_one_and_semester_two_coexist_in_one_academic_year(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => TermStatus::Active->value === 'active' ? 'active' : 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.terms.store'), $this->payload($year, 1, '2026-07-01', '2026-12-31'))
            ->assertRedirect(route('admin.terms.index'))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.terms.store'), $this->payload($year, 2, '2027-01-01', '2027-06-30'))
            ->assertRedirect(route('admin.terms.index'))
            ->assertSessionHasNoErrors();

        $terms = Term::query()->where('academic_year_id', $year->getKey())->orderBy('sequence')->get();

        $this->assertCount(2, $terms);
        $this->assertSame([1, 2], $terms->pluck('sequence')->map(static fn ($value): int => (int) $value)->all());
        $this->assertSame('Semester 1', $terms->first()->semesterLabel());
        $this->assertSame('Semester 2', $terms->last()->semesterLabel());

        $this->actingAs($admin)->get(route('admin.terms.index'))->assertOk()->assertSee('Semester 1')->assertSee('Semester 2');
    }

    public function test_a_duplicate_sequence_in_the_same_year_is_rejected(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
        Term::factory()->create(['academic_year_id' => $year->getKey(), 'sequence' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.terms.store'), $this->payload($year, 1, '2026-07-01', '2026-12-31'))
            ->assertSessionHasErrors('sequence');

        $this->assertSame(1, Term::query()->where('academic_year_id', $year->getKey())->count());
    }

    public function test_editing_the_second_semester_of_a_year_is_accepted(): void
    {
        // The regression this covers: with the uniqueness rule on the wrong
        // field, this exact request failed with "academic year value already
        // used" whenever the year id matched a sequence already stored in it.
        $admin = $this->admin();
        $year = AcademicYear::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);

        $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 1, '2026-07-01', '2026-12-31', TermStatus::Active));
        $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 2, '2027-01-01', '2027-06-30'));

        $second = Term::query()
            ->where('academic_year_id', $year->getKey())
            ->where('sequence', 2)
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.terms.update', $second), $this->payload($year, 2, '2027-01-01', '2027-06-30'))
            ->assertRedirect(route('admin.terms.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Semester 2', $second->refresh()->label);
    }

    public function test_editing_a_semester_into_a_sequence_that_is_taken_is_rejected(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);

        $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 1, '2026-07-01', '2026-12-31'));
        $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 2, '2027-01-01', '2027-06-30'));

        $second = Term::query()->where('academic_year_id', $year->getKey())->where('sequence', 2)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.terms.update', $second), [
                ...$this->payload($year, 1, '2027-01-01', '2027-06-30'),
                'label' => 'Semester kembar',
            ])
            ->assertSessionHasErrors('sequence');

        $this->assertSame(2, (int) $second->refresh()->sequence);
    }

    public function test_two_academic_years_each_have_their_own_semester_one_and_two(): void
    {
        $admin = $this->admin();
        $first = AcademicYear::factory()->create(['starts_on' => '2025-07-01', 'ends_on' => '2026-06-30']);
        $second = AcademicYear::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);

        foreach ([$first, $second] as $year) {
            $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 1, $year->starts_on->toDateString(), $year->starts_on->copy()->addMonths(5)->toDateString()));
            $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 2, $year->starts_on->copy()->addMonths(6)->toDateString(), $year->ends_on->toDateString()));
        }

        $this->assertSame(2, Term::query()->where('academic_year_id', $first->getKey())->count());
        $this->assertSame(2, Term::query()->where('academic_year_id', $second->getKey())->count());
    }

    public function test_two_semesters_of_one_year_may_not_overlap(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);

        $this->actingAs($admin)
            ->post(route('admin.terms.store'), $this->payload($year, 1, '2026-07-01', '2026-12-31'))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.terms.store'), $this->payload($year, 2, '2026-12-15', '2027-06-30'))
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(1, Term::query()->where('academic_year_id', $year->getKey())->count());
    }

    public function test_a_semester_must_sit_inside_its_academic_year(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);

        $this->actingAs($admin)
            ->post(route('admin.terms.store'), $this->payload($year, 1, '2026-01-01', '2026-06-30'))
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(0, Term::query()->where('academic_year_id', $year->getKey())->count());
    }

    public function test_creating_an_active_semester_closes_the_other_semester_of_the_year(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);

        $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 1, '2026-07-01', '2026-12-31', TermStatus::Active));
        $this->actingAs($admin)->post(route('admin.terms.store'), $this->payload($year, 2, '2027-01-01', '2027-06-30', TermStatus::Active));

        $active = Term::query()
            ->where('academic_year_id', $year->getKey())
            ->where('status', TermStatus::Active->value)
            ->get();

        $this->assertCount(1, $active, 'Exactly one semester of a year may be active at a time.');
        $this->assertSame(2, (int) $active->first()->sequence);
    }

    public function test_the_semester_screen_uses_indonesian_terminology(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
        Term::factory()->create([
            'academic_year_id' => $year->getKey(),
            'sequence' => 1,
            'label' => 'Semester 1',
        ]);

        $this->actingAs($admin)->get(route('admin.terms.index'))
            ->assertOk()
            ->assertSee('Semester')
            ->assertDontSee('>Term<', false);

        $this->actingAs($admin)->get(route('admin.terms.create'))
            ->assertOk()
            ->assertSee('Tambah semester');

        $this->actingAs($admin)->get(route('admin.terms.show', Term::query()->where('academic_year_id', $year->getKey())->firstOrFail()))
            ->assertOk()
            ->assertSee('Semester 1');
    }

    /**
     * The create form must arrive pointing at the current academic year.
     *
     * The controller worked out the current year and derived the next free
     * sequence and a fitting date range from it, but the view read only the query
     * string, so the select sat on its "Pilih salah satu" placeholder. Required
     * validation then refused the save, and the sequence and dates on screen
     * described a year the form was not even pointed at.
     */
    public function test_the_create_form_preselects_the_current_academic_year(): void
    {
        $admin = $this->admin();

        $current = AcademicYear::factory()->active()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
        ]);
        $other = AcademicYear::factory()->create([
            'starts_on' => '2024-07-01',
            'ends_on' => '2025-06-30',
        ]);

        $this->actingAs($admin)->get(route('admin.terms.create'))
            ->assertOk()
            ->assertSee('name="academic_year_id"', false)
            ->assertSee('value="'.$current->getKey().'" selected', false);
    }

    public function test_the_create_form_preselects_the_year_asked_for_in_the_query_string(): void
    {
        $admin = $this->admin();

        AcademicYear::factory()->active()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
        $asked = AcademicYear::factory()->create(['starts_on' => '2024-07-01', 'ends_on' => '2025-06-30']);

        $this->actingAs($admin)->get(route('admin.terms.create', ['academic_year_id' => $asked->getKey()]))
            ->assertOk()
            ->assertSee('value="'.$asked->getKey().'" selected', false);
    }
}
