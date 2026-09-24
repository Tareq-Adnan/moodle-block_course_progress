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

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use context_system;
use context_course;

/**
 * External Web Service API for the Course Progress block.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 ITN-BUET
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Parameter description for get_courses.
     *
     * @return external_function_parameters
     */
    public static function get_courses_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_RAW_TRIMMED, 'Search term', VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort field', VALUE_DEFAULT, 'coursename'),
            'sortdir' => new external_value(PARAM_ALPHA, 'Sort direction ASC or DESC', VALUE_DEFAULT, 'ASC'),
            'page' => new external_value(PARAM_INT, 'Page index', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 10),
            'loadprogress' => new external_value(PARAM_BOOL, 'Whether to compute progress', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Get paginated list of courses with engagement metrics.
     *
     * @param string $search Search query
     * @param string $sort Sort field
     * @param string $sortdir Sort direction
     * @param int $page Page index
     * @param int $perpage Items per page
     * @param bool $loadprogress Compute progress
     * @return array
     */
    public static function get_courses(
        string $search = '',
        string $sort = 'coursename',
        string $sortdir = 'ASC',
        int $page = 0,
        int $perpage = 10,
        bool $loadprogress = true
    ): array {
        global $USER;

        $params = self::validate_parameters(self::get_courses_parameters(), [
            'search' => $search,
            'sort' => $sort,
            'sortdir' => $sortdir,
            'page' => $page,
            'perpage' => $perpage,
            'loadprogress' => $loadprogress,
        ]);

        self::validate_context(context_system::instance());

        return helper::get_courses(
            (int)$USER->id,
            $params['search'],
            $params['sort'],
            $params['sortdir'],
            $params['page'],
            $params['perpage'],
            $params['loadprogress']
        );
    }

    /**
     * Return structure for get_courses.
     *
     * @return external_single_structure
     */
    public static function get_courses_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total matching courses'),
            'page' => new external_value(PARAM_INT, 'Current page index'),
            'perpage' => new external_value(PARAM_INT, 'Records per page'),
            'from' => new external_value(PARAM_INT, 'Starting entry index'),
            'to' => new external_value(PARAM_INT, 'Ending entry index'),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'index' => new external_value(PARAM_INT, 'Sequential index'),
                    'id' => new external_value(PARAM_INT, 'Course ID'),
                    'coursename' => new external_value(PARAM_TEXT, 'Full course name'),
                    'shortname' => new external_value(PARAM_TEXT, 'Short course name'),
                    'startdate' => new external_value(PARAM_TEXT, 'Formatted start date'),
                    'startdate_raw' => new external_value(PARAM_INT, 'Raw timestamp start date'),
                    'enddate' => new external_value(PARAM_TEXT, 'Formatted end date'),
                    'category' => new external_value(PARAM_TEXT, 'Category name'),
                    'enrolled' => new external_value(PARAM_INT, 'Enrolled students count'),
                    'active' => new external_value(PARAM_INT, 'Active students count'),
                    'completed' => new external_value(PARAM_INT, 'Completed students count'),
                    'visits' => new external_value(PARAM_INT, 'Visits count'),
                    'completionrate' => new external_value(PARAM_INT, 'Completion percentage'),
                    'notstarted' => new external_value(PARAM_INT, 'Not started count'),
                    'inprogress' => new external_value(PARAM_INT, 'In progress count'),
                    'groupscount' => new external_value(PARAM_INT, 'Groups count'),
                    'hascompletion' => new external_value(PARAM_BOOL, 'Has completion enabled'),
                    'progress' => new external_value(PARAM_INT, 'Average progress percentage'),
                    'progressloaded' => new external_value(PARAM_BOOL, 'Is progress calculated'),
                    'courseurl' => new external_value(PARAM_URL, 'URL to course'),
                ])
            ),
        ]);
    }

    /**
     * Parameter description for get_students.
     *
     * @return external_function_parameters
     */
    public static function get_students_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'groupid' => new external_value(PARAM_INT, 'Group ID filter', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_RAW_TRIMMED, 'Search term', VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort field', VALUE_DEFAULT, 'name'),
            'sortdir' => new external_value(PARAM_ALPHA, 'Sort direction ASC or DESC', VALUE_DEFAULT, 'ASC'),
            'page' => new external_value(PARAM_INT, 'Page index', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Get paginated list of students in a course with group filter.
     *
     * @param int $courseid Course ID
     * @param int $groupid Group ID
     * @param string $search Search query
     * @param string $sort Sort field
     * @param string $sortdir Sort direction
     * @param int $page Page index
     * @param int $perpage Items per page
     * @return array
     */
    public static function get_students(
        int $courseid,
        int $groupid = 0,
        string $search = '',
        string $sort = 'name',
        string $sortdir = 'ASC',
        int $page = 0,
        int $perpage = 10
    ): array {
        $params = self::validate_parameters(self::get_students_parameters(), [
            'courseid' => $courseid,
            'groupid' => $groupid,
            'search' => $search,
            'sort' => $sort,
            'sortdir' => $sortdir,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        return helper::get_students(
            $params['courseid'],
            $params['groupid'],
            $params['search'],
            $params['sort'],
            $params['sortdir'],
            $params['page'],
            $params['perpage']
        );
    }

    /**
     * Return structure for get_students.
     *
     * @return external_single_structure
     */
    public static function get_students_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total matching students'),
            'page' => new external_value(PARAM_INT, 'Current page index'),
            'perpage' => new external_value(PARAM_INT, 'Records per page'),
            'from' => new external_value(PARAM_INT, 'Starting entry index'),
            'to' => new external_value(PARAM_INT, 'Ending entry index'),
            'course' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Full course name'),
            ]),
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'index' => new external_value(PARAM_INT, 'Sequential index'),
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'name' => new external_value(PARAM_TEXT, 'Full student name'),
                    'email' => new external_value(PARAM_EMAIL, 'Email address'),
                    'idnumber' => new external_value(PARAM_TEXT, 'Student ID Number'),
                    'avatarurl' => new external_value(PARAM_URL, 'Avatar picture URL'),
                    'status' => new external_value(PARAM_TEXT, 'Activity status badge text'),
                    'statusclass' => new external_value(PARAM_ALPHA, 'active or never status class'),
                    'progress' => new external_value(PARAM_INT, 'Course completion progress %'),
                    'groupname' => new external_value(PARAM_TEXT, 'Group/Batch name'),
                    'firstaccess' => new external_value(PARAM_TEXT, 'First access date'),
                    'timecompleted' => new external_value(PARAM_TEXT, 'Completed date'),
                    'messageurl' => new external_value(PARAM_URL, 'URL to message user'),
                ])
            ),
        ]);
    }

    /**
     * Parameter description for get_groups.
     *
     * @return external_function_parameters
     */
    public static function get_groups_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Get list of groups in a course.
     *
     * @param int $courseid Course ID
     * @return array
     */
    public static function get_groups(int $courseid): array {
        $params = self::validate_parameters(self::get_groups_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        return helper::get_groups($params['courseid']);
    }

    /**
     * Return structure for get_groups.
     *
     * @return external_multiple_structure
     */
    public static function get_groups_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group ID'),
                'name' => new external_value(PARAM_TEXT, 'Group name'),
            ])
        );
    }

    /**
     * Parameter description for get_batch_summary.
     *
     * @return external_function_parameters
     */
    public static function get_batch_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Get batch-wise comparative performance summary.
     *
     * @param int $courseid Course ID
     * @return array
     */
    public static function get_batch_summary(int $courseid): array {
        $params = self::validate_parameters(self::get_batch_summary_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        return helper::get_batch_summary($params['courseid']);
    }

    /**
     * Return structure for get_batch_summary.
     *
     * @return external_multiple_structure
     */
    public static function get_batch_summary_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'groupid' => new external_value(PARAM_INT, 'Group ID'),
                'batchname' => new external_value(PARAM_TEXT, 'Batch name'),
                'members' => new external_value(PARAM_INT, 'Total member count'),
                'active' => new external_value(PARAM_INT, 'Active member count'),
                'completed' => new external_value(PARAM_INT, 'Completed member count'),
                'avgprogress' => new external_value(PARAM_INT, 'Average progress %'),
                'completionrate' => new external_value(PARAM_INT, 'Completion rate %'),
            ])
        );
    }
}
