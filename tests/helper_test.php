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
use context_course;
use context_system;

/**
 * Unit tests for Course Progress block helper calculations.
 *
 * @package    block_itn_course_progress
 * @category   test
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_itn_course_progress\helper
 */
final class helper_test extends advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test permission and course view checks.
     */
    public function test_can_view_course(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $gen->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $gen->enrol_user($student->id, $course->id, $studentrole->id);

        // Site admin can view.
        $this->setAdminUser();
        $this->assertTrue(helper::can_view_course($course->id));

        // Teacher with view capability can view.
        $this->setUser($teacher);
        $this->assertTrue(helper::can_view_course($course->id));

        // Regular student without capability cannot view.
        $this->setUser($student);
        $this->assertFalse(helper::can_view_course($course->id));
    }

    /**
     * Test course list retrieval, pagination, and ampersand decoding.
     */
    public function test_get_courses(): void {
        $gen = $this->getDataGenerator();
        $course1 = $gen->create_course(['fullname' => 'Database Systems & SQL']);
        $gen->create_course(['fullname' => 'Machine Learning']);

        $this->setAdminUser();

        // Retrieve all courses.
        $result = helper::get_courses(0, '', 'coursename', 'ASC', 0, 10, false);
        $this->assertGreaterThanOrEqual(2, $result['total']);

        $coursenames = array_column($result['courses'], 'coursename');
        $this->assertContains('Database Systems & SQL', $coursenames);
        $this->assertContains('Machine Learning', $coursenames);

        // Ensure special entities are decoded as plain text (no literal &amp;).
        foreach ($result['courses'] as $c) {
            $this->assertStringNotContainsString('&amp;', $c['coursename']);
        }

        // Test search filter.
        $searchresult = helper::get_courses(0, 'SQL', 'coursename', 'ASC', 0, 10, false);
        $this->assertCount(1, $searchresult['courses']);
        $this->assertEquals($course1->id, $searchresult['courses'][0]['id']);
    }

    /**
     * Test student list retrieval, group filtering, and search.
     */
    public function test_get_students(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $group = $gen->create_group(['courseid' => $course->id, 'name' => 'Batch 2026']);

        $student1 = $gen->create_user(['firstname' => 'Alice', 'lastname' => 'Smith', 'email' => 'alice@example.com']);
        $student2 = $gen->create_user(['firstname' => 'Bob', 'lastname' => 'Jones', 'email' => 'bob@example.com']);
        $mixedroleteacher = $gen->create_user();
        $siteadmin = get_admin();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $gen->enrol_user($student1->id, $course->id, $studentrole->id);
        $gen->enrol_user($student2->id, $course->id, $studentrole->id);
        $gen->enrol_user($mixedroleteacher->id, $course->id, $teacherrole->id);
        $gen->enrol_user($siteadmin->id, $course->id, $studentrole->id);
        role_assign($studentrole->id, $mixedroleteacher->id, context_course::instance($course->id));

        groups_add_member($group->id, $student1->id);

        $this->setAdminUser();

        // All students in course.
        $all = helper::get_students($course->id, 0, '', 'name', 'ASC', 0, 10);
        $this->assertEquals(2, $all['total']);
        $this->assertNotContains($mixedroleteacher->id, array_column($all['students'], 'id'));
        $this->assertNotContains($siteadmin->id, array_column($all['students'], 'id'));

        // Group filtered students.
        $grouped = helper::get_students($course->id, $group->id, '', 'name', 'ASC', 0, 10);
        $this->assertEquals(1, $grouped['total']);
        $this->assertEquals($student1->id, $grouped['students'][0]['id']);

        // Search by name.
        $searched = helper::get_students($course->id, 0, 'Bob', 'name', 'ASC', 0, 10);
        $this->assertEquals(1, $searched['total']);
        $this->assertEquals($student2->id, $searched['students'][0]['id']);
    }

    /**
     * Test group list and comparative batch performance aggregation.
     */
    public function test_get_groups_and_batch_summary(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $group = $gen->create_group(['courseid' => $course->id, 'name' => 'Batch A & B']);

        $student = $gen->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $gen->enrol_user($student->id, $course->id, $studentrole->id);
        groups_add_member($group->id, $student->id);

        $this->setAdminUser();

        // Test get_groups.
        $groups = helper::get_groups($course->id);
        $this->assertCount(2, $groups);
        $this->assertEquals(0, $groups[0]['id']);
        $this->assertEquals('Batch A & B', $groups[1]['name']);
        $this->assertStringNotContainsString('&amp;', $groups[1]['name']);

        // Test get_batch_summary.
        $summary = helper::get_batch_summary($course->id);
        $this->assertCount(1, $summary);
        $this->assertEquals('Batch A & B', $summary[0]['batchname']);
        $this->assertEquals(1, $summary[0]['members']);
        $this->assertArrayHasKey('completionrate', $summary[0]);
        $this->assertArrayHasKey('avgprogress', $summary[0]);
    }

    /**
     * Test that separate-groups reporters only receive learners in their groups.
     */
    public function test_separate_groups_restrict_learner_data(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS]);
        $unenrolledcourse = $gen->create_course();
        $teacher = $gen->create_user();
        $visiblelearner = $gen->create_user();
        $hiddenlearner = $gen->create_user();
        $teachergroup = $gen->create_group(['courseid' => $course->id]);
        $othergroup = $gen->create_group(['courseid' => $course->id]);

        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $gen->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $gen->enrol_user($visiblelearner->id, $course->id, $studentrole->id);
        $gen->enrol_user($hiddenlearner->id, $course->id, $studentrole->id);
        groups_add_member($teachergroup->id, $teacher->id);
        groups_add_member($teachergroup->id, $visiblelearner->id);
        groups_add_member($othergroup->id, $hiddenlearner->id);

        $context = context_course::instance($course->id);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherrole->id, $context->id);
        $this->setUser($teacher);

        $students = helper::get_students($course->id, 0, '', 'name', 'ASC', 0, 10);
        $this->assertSame(1, $students['total']);
        $this->assertSame($visiblelearner->id, $students['students'][0]['id']);

        $courses = helper::get_courses($teacher->id, '', 'coursename', 'ASC', 0, 10, false);
        $reportedcourse = array_values(array_filter(
            $courses['courses'],
            static fn(array $candidate): bool => $candidate['id'] === $course->id
        ));
        $this->assertCount(1, $reportedcourse);
        $this->assertSame(1, $reportedcourse[0]['enrolled']);
        $this->assertNotContains($unenrolledcourse->id, array_column($courses['courses'], 'id'));

        $this->expectException(\invalid_parameter_exception::class);
        helper::get_student_progress($hiddenlearner->id, $course->id);
    }

    /**
     * Test that report paging is constrained to supported values.
     */
    public function test_invalid_paging_is_rejected(): void {
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        helper::get_courses(0, '', 'coursename', 'ASC', -1, 1000, false);
    }
}
