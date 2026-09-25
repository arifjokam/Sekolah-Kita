<?php

namespace Tests\Unit\Public;

use App\Enums\PublicationStatus;
use PHPUnit\Framework\TestCase;

class PublicationStatusTest extends TestCase
{
    public function test_publication_states_have_stable_values_and_labels(): void
    {
        $this->assertSame('draft', PublicationStatus::Draft->value);
        $this->assertSame('published', PublicationStatus::Published->value);
        $this->assertSame('archived', PublicationStatus::Archived->value);
        $this->assertSame('Terbit', PublicationStatus::Published->label());
    }
}
