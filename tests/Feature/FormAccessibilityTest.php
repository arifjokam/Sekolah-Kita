<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every form control has to be programmatically labelled.
 *
 * The shared x-form.* components accepted a `label` prop and rendered no
 * `<label>` element at all, so every master data form shipped unlabelled
 * controls: unusable with a screen reader and unclickable by label. A response
 * status check cannot see this, which is why it survived; the browser suite in
 * tests/Browser/smoke.mjs asserts the same thing against a real DOM.
 */
class FormAccessibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Screens that render the shared form components.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function formProvider(): array
    {
        return [
            'teacher create' => ['/admin/teachers/create', 'admin.teachers.create'],
            'student create' => ['/admin/students/create', 'admin.students.create'],
            'class create' => ['/admin/classes/create', 'admin.classes.create'],
            'subject create' => ['/admin/subjects/create', 'admin.subjects.create'],
            'academic year create' => ['/admin/academic-years/create', 'admin.academic-years.create'],
            'term create' => ['/admin/terms/create', 'admin.terms.create'],
            'teaching assignment create' => ['/admin/teaching-assignments/create', 'admin.teaching-assignments.create'],
            'class teacher assignment create' => ['/admin/class-teacher-assignments/create', 'admin.class-teacher-assignments.create'],
            'enrollment create' => ['/admin/enrollments/create', 'admin.enrollments.create'],
            'ppdb period create' => ['/admin/ppdb/periods/create', 'admin.ppdb.periods.create'],
            'news create' => ['/admin/news/create', 'admin.news.create'],
        ];
    }

    #[DataProvider('formProvider')]
    public function test_every_control_on_a_form_screen_is_labelled(string $path, string $routeName): void
    {
        $admin = User::factory()->administrator()->create();

        $html = $this->actingAs($admin)->get(route($routeName))->assertOk()->getContent();

        $unlabelled = $this->unlabelledControls($html);

        $this->assertSame(
            [],
            $unlabelled,
            "Unlabelled controls on {$path}: ".implode(', ', $unlabelled)
        );
    }

    /**
     * Collect the name of every control that has no accessible name.
     *
     * A control qualifies when a `<label for>` points at its id, when it carries
     * aria-label or aria-labelledby, or when it is nested inside a `<label>`,
     * which is how the checkbox callouts on the student and news forms are built.
     *
     * @return list<string>
     */
    private function unlabelledControls(string $html): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $found = [];

        /** @var \DOMElement $control */
        foreach ($xpath->query('//input | //select | //textarea') as $control) {
            $type = strtolower($control->getAttribute('type'));

            if (in_array($type, ['hidden', 'submit', 'button', 'reset'], true)) {
                continue;
            }

            if ($this->hasAccessibleName($xpath, $control)) {
                continue;
            }

            $found[] = $control->nodeName.'['.($control->getAttribute('name') ?: '?').']';
        }

        return $found;
    }

    private function hasAccessibleName(\DOMXPath $xpath, \DOMElement $control): bool
    {
        if ($control->hasAttribute('aria-label') || $control->hasAttribute('aria-labelledby')) {
            return true;
        }

        if ($control->parentNode instanceof \DOMElement && strtolower($control->parentNode->nodeName) === 'label') {
            return true;
        }

        $id = $control->getAttribute('id');

        if ($id === '') {
            return false;
        }

        return $xpath->query(sprintf('//label[@for=%s]', json_encode($id)))->length > 0;
    }
}
