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

/**
 * Web service function definitions for Course Progress block.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_itn_course_progress_get_courses' => [
        'classname' => 'block_itn_course_progress\external',
        'methodname' => 'get_courses',
        'description' => 'Retrieve paginated courses with progress and engagement metrics.',
        'type' => 'read',
        'ajax' => true,
    ],
    'block_itn_course_progress_get_students' => [
        'classname' => 'block_itn_course_progress\external',
        'methodname' => 'get_students',
        'description' => 'Retrieve paginated students in a course with group filter and completion progress.',
        'type' => 'read',
        'ajax' => true,
    ],
    'block_itn_course_progress_get_student_progress' => [
        'classname' => 'block_itn_course_progress\external',
        'methodname' => 'get_student_progress',
        'description' => 'Retrieve one student progress across courses visible to the current reporter.',
        'type' => 'read',
        'ajax' => true,
    ],
    'block_itn_course_progress_get_student_activities' => [
        'classname' => 'block_itn_course_progress\external',
        'methodname' => 'get_student_activities',
        'description' => 'Retrieve one student completion and engagement details for tracked course activities.',
        'type' => 'read',
        'ajax' => true,
    ],
    'block_itn_course_progress_get_groups' => [
        'classname' => 'block_itn_course_progress\external',
        'methodname' => 'get_groups',
        'description' => 'Retrieve available groups for a course.',
        'type' => 'read',
        'ajax' => true,
    ],
    'block_itn_course_progress_get_batch_summary' => [
        'classname' => 'block_itn_course_progress\external',
        'methodname' => 'get_batch_summary',
        'description' => 'Retrieve batch-wise comparative performance summary for a course.',
        'type' => 'read',
        'ajax' => true,
    ],
];
