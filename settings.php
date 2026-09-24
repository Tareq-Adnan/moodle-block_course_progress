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
 * Global administration settings for the Course Progress block.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 ITN-BUET
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // 1. Default Progress Loading Mode.
    $settings->add(new admin_setting_configcheckbox(
        'block_itn_course_progress/defaultloadprogress',
        get_string('settings:defaultloadprogress', 'block_itn_course_progress'),
        get_string('settings:defaultloadprogress_desc', 'block_itn_course_progress'),
        1
    ));

    // 2. Default Rows Per Page.
    $settings->add(new admin_setting_configselect(
        'block_itn_course_progress/defaultperpage',
        get_string('settings:defaultperpage', 'block_itn_course_progress'),
        get_string('settings:defaultperpage_desc', 'block_itn_course_progress'),
        10,
        [5 => 5, 10 => 10, 25 => 25, 50 => 50]
    ));

    // 3. Multi-checkbox: Default Course Overview Columns (Req 3).
    $coursecolumns = [
        'index' => get_string('col_index', 'block_itn_course_progress'),
        'coursename' => get_string('col_courses', 'block_itn_course_progress'),
        'startdate' => get_string('col_startdate', 'block_itn_course_progress'),
        'enrolled' => get_string('col_students', 'block_itn_course_progress'),
        'progress' => get_string('col_progress', 'block_itn_course_progress'),
        'active' => get_string('col_active', 'block_itn_course_progress'),
        'completed' => get_string('col_completed', 'block_itn_course_progress'),
        'visits' => get_string('col_visits', 'block_itn_course_progress'),
        'completionrate' => get_string('col_completionrate', 'block_itn_course_progress'),
        'notstarted' => get_string('col_notstarted', 'block_itn_course_progress'),
        'inprogress' => get_string('col_inprogress', 'block_itn_course_progress'),
        'enddate' => get_string('col_enddate', 'block_itn_course_progress'),
        'category' => get_string('col_category', 'block_itn_course_progress'),
        'groupscount' => get_string('col_groupscount', 'block_itn_course_progress'),
    ];
    $settings->add(new admin_setting_configmulticheckbox(
        'block_itn_course_progress/course_columns',
        get_string('settings:course_columns', 'block_itn_course_progress'),
        get_string('settings:course_columns_desc', 'block_itn_course_progress'),
        [
            'index' => 1,
            'coursename' => 1,
            'startdate' => 1,
            'enrolled' => 1,
            'progress' => 1,
        ],
        $coursecolumns
    ));

    // 4. Multi-checkbox: Default Student Drill-down Columns (Req 5).
    $studentcolumns = [
        'index' => get_string('col_index', 'block_itn_course_progress'),
        'name' => get_string('col_name', 'block_itn_course_progress'),
        'status' => get_string('col_status', 'block_itn_course_progress'),
        'progress' => get_string('col_progress', 'block_itn_course_progress'),
        'idnumber' => get_string('col_idnumber', 'block_itn_course_progress'),
        'email' => get_string('col_email', 'block_itn_course_progress'),
        'firstaccess' => get_string('col_firstaccess', 'block_itn_course_progress'),
        'groupname' => get_string('col_groupname', 'block_itn_course_progress'),
        'timecompleted' => get_string('col_timecompleted', 'block_itn_course_progress'),
        'activitiesratio' => get_string('col_activitiesratio', 'block_itn_course_progress'),
        'grade' => get_string('col_grade', 'block_itn_course_progress'),
    ];
    $settings->add(new admin_setting_configmulticheckbox(
        'block_itn_course_progress/student_columns',
        get_string('settings:student_columns', 'block_itn_course_progress'),
        get_string('settings:student_columns_desc', 'block_itn_course_progress'),
        [
            'index' => 1,
            'name' => 1,
            'status' => 1,
            'progress' => 1,
        ],
        $studentcolumns
    ));

    // 5. Batch Summary Comparison Toggle (Req 4).
    $settings->add(new admin_setting_configcheckbox(
        'block_itn_course_progress/enablebatchsummary',
        get_string('settings:enablebatchsummary', 'block_itn_course_progress'),
        get_string('settings:enablebatchsummary_desc', 'block_itn_course_progress'),
        1
    ));

    // 6. Recent activity window used by the Active Students metric.
    $settings->add(new admin_setting_configtext(
        'block_itn_course_progress/activedays',
        get_string('settings:activedays', 'block_itn_course_progress'),
        get_string('settings:activedays_desc', 'block_itn_course_progress'),
        30,
        PARAM_INT
    ));
}
