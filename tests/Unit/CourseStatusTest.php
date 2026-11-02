<?php

namespace Tests\Unit;

use App\Models\Course;
use PHPUnit\Framework\TestCase;

class CourseStatusTest extends TestCase
{
    public function test_status_aliases_are_normalised_to_canonical_values(): void
    {
        $this->assertSame('Published', Course::normalizeStatus('PUBLISHED'));
        $this->assertSame('Published', Course::normalizeStatus('approved'));
        $this->assertSame('Published', Course::normalizeStatus(' active '));
        $this->assertSame('Draft', Course::normalizeStatus('Draft'));
        $this->assertSame('Draft', Course::normalizeStatus('PENDING'));
        $this->assertSame('Draft', Course::normalizeStatus(null));
        $this->assertSame('Closed', Course::normalizeStatus('Closed'));
        $this->assertSame('Closed', Course::normalizeStatus('SUSPENDED'));
        $this->assertSame('Closed', Course::normalizeStatus('Rejected'));
        $this->assertSame('Closed', Course::normalizeStatus('Archived'));
        $this->assertSame('Draft', Course::normalizeStatus('some legacy label'));
    }
}
