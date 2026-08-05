<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;

/**
 * Slices 03 + 04 closed every stub-501 controller:
 *   - ReminderController + ReminderTemplateController (slice 03) now implement
 *     full CRUD via ReminderService.
 *   - WaitingListController, InterconsultationController, WorkScheduleController,
 *     AppointmentBlockController, OdontogramController, RoleController and
 *     CalendarController (slice 04) were removed entirely — endpoints return
 *     404 instead of 501. Coverage lives in StubsRemovedEndpointsTest.
 *
 * This file is retained as a documentation anchor for the original NF-1
 * sprint; no active assertions remain.
 */
class StubsNotImplementedTest extends TestCase
{
    public function test_documentation_anchor(): void
    {
        $this->assertTrue(true);
    }
}
