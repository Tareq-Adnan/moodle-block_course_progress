<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_itn_course_progress;

use advanced_testcase;
use core_external\external_api;
use required_capability_exception;

/**
 * Web service external API tests for the Course Progress block.
 *
 * @package    block_itn_course_progress
 * @category   test
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_itn_course_progress\external
 */
final class external_test extends advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test get_courses external web service execution and return structure.
     */
    public function test_get_courses(): void {
        $gen = $this->getDataGenerator();
        $gen->create_course(['fullname' => 'Course Alpha & Beta']);

        $this->setAdminUser();

        $result = external::get_courses('', 'coursename', 'ASC', 0, 10, false);
        $cleaned = external_api::clean_returnvalue(external::get_courses_returns(), $result);

        $this->assertArrayHasKey('courses', $cleaned);
        $this->assertArrayHasKey('total', $cleaned);
        $this->assertGreaterThanOrEqual(1, $cleaned['total']);
    }

    /**
     * Test get_students external web service with permission check.
     */
    public function test_get_students(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_user();
        $unauthorized = $gen->create_user();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $gen->enrol_user($student->id, $course->id, $studentrole->id);

        $this->setAdminUser();
        $result = external::get_students($course->id, 0, '', 'name', 'ASC', 0, 10);
        $cleaned = external_api::clean_returnvalue(external::get_students_returns(), $result);

        $this->assertEquals(1, $cleaned['total']);
        $this->assertEquals($student->id, $cleaned['students'][0]['id']);

        // Unauthorized user without block view capability should throw exception.
        $this->setUser($unauthorized);
        $this->expectException(required_capability_exception::class);
        external::get_students($course->id, 0, '', 'name', 'ASC', 0, 10);
    }

    /**
     * Test get_groups and get_batch_summary external web services.
     */
    public function test_get_groups_and_batch_summary(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_group(['courseid' => $course->id, 'name' => 'Cohort 1']);

        $this->setAdminUser();

        $groups = external::get_groups($course->id);
        $cleanedgroups = external_api::clean_returnvalue(external::get_groups_returns(), $groups);
        $this->assertCount(2, $cleanedgroups);
        $this->assertEquals(0, $cleanedgroups[0]['id']);
        $this->assertEquals('Cohort 1', $cleanedgroups[1]['name']);

        $summary = external::get_batch_summary($course->id);
        $cleanedsummary = external_api::clean_returnvalue(external::get_batch_summary_returns(), $summary);
        $this->assertCount(1, $cleanedsummary);
        $this->assertEquals('Cohort 1', $cleanedsummary[0]['batchname']);
    }
}
