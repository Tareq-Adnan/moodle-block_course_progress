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

namespace block_itn_course_progress\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Main renderable and templatable class for the Course Progress block.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 ITN-BUET
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /** @var stdClass|null Block instance configuration */
    protected ?stdClass $config;

    /**
     * Constructor.
     *
     * @param stdClass|null $config Instance configuration.
     */
    public function __construct(?stdClass $config = null) {
        $this->config = $config;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output Renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output): array {
        $pluginconfig = get_config('block_itn_course_progress');

        $defaultload = !empty($this->config->defaultloadprogress ?? ($pluginconfig->defaultloadprogress ?? 1));
        $defaultperpage = (int)($this->config->defaultperpage ?? ($pluginconfig->defaultperpage ?? 10));
        $enablebatchsummary = !empty($pluginconfig->enablebatchsummary ?? 1);

        // Course columns.
        $configuredcoursecols = !empty($pluginconfig->course_columns) ? explode(',', $pluginconfig->course_columns) : [];
        if (empty($configuredcoursecols)) {
            $configuredcoursecols = ['index', 'coursename', 'startdate', 'enrolled', 'progress'];
        }

        // Student columns.
        $configuredstudentcols = !empty($pluginconfig->student_columns) ? explode(',', $pluginconfig->student_columns) : [];
        if (empty($configuredstudentcols)) {
            $configuredstudentcols = ['index', 'name', 'status', 'progress'];
        }

        $allcoursecols = [
            ['key' => 'index', 'label' => get_string('col_index', 'block_itn_course_progress'), 'enabled' => in_array('index', $configuredcoursecols, true)],
            ['key' => 'coursename', 'label' => get_string('col_courses', 'block_itn_course_progress'), 'enabled' => in_array('coursename', $configuredcoursecols, true)],
            ['key' => 'startdate', 'label' => get_string('col_startdate', 'block_itn_course_progress'), 'enabled' => in_array('startdate', $configuredcoursecols, true)],
            ['key' => 'enrolled', 'label' => get_string('col_students', 'block_itn_course_progress'), 'enabled' => in_array('enrolled', $configuredcoursecols, true)],
            ['key' => 'progress', 'label' => get_string('col_progress', 'block_itn_course_progress'), 'enabled' => in_array('progress', $configuredcoursecols, true)],
            ['key' => 'active', 'label' => get_string('col_active', 'block_itn_course_progress'), 'enabled' => in_array('active', $configuredcoursecols, true)],
            ['key' => 'completed', 'label' => get_string('col_completed', 'block_itn_course_progress'), 'enabled' => in_array('completed', $configuredcoursecols, true)],
            ['key' => 'visits', 'label' => get_string('col_visits', 'block_itn_course_progress'), 'enabled' => in_array('visits', $configuredcoursecols, true)],
            ['key' => 'completionrate', 'label' => get_string('col_completionrate', 'block_itn_course_progress'), 'enabled' => in_array('completionrate', $configuredcoursecols, true)],
            ['key' => 'notstarted', 'label' => get_string('col_notstarted', 'block_itn_course_progress'), 'enabled' => in_array('notstarted', $configuredcoursecols, true)],
            ['key' => 'inprogress', 'label' => get_string('col_inprogress', 'block_itn_course_progress'), 'enabled' => in_array('inprogress', $configuredcoursecols, true)],
            ['key' => 'enddate', 'label' => get_string('col_enddate', 'block_itn_course_progress'), 'enabled' => in_array('enddate', $configuredcoursecols, true)],
            ['key' => 'category', 'label' => get_string('col_category', 'block_itn_course_progress'), 'enabled' => in_array('category', $configuredcoursecols, true)],
            ['key' => 'groupscount', 'label' => get_string('col_groupscount', 'block_itn_course_progress'), 'enabled' => in_array('groupscount', $configuredcoursecols, true)],
        ];

        $allstudentcols = [
            ['key' => 'index', 'label' => get_string('col_index', 'block_itn_course_progress'), 'enabled' => in_array('index', $configuredstudentcols, true)],
            ['key' => 'name', 'label' => get_string('col_name', 'block_itn_course_progress'), 'enabled' => in_array('name', $configuredstudentcols, true)],
            ['key' => 'status', 'label' => get_string('col_status', 'block_itn_course_progress'), 'enabled' => in_array('status', $configuredstudentcols, true)],
            ['key' => 'progress', 'label' => get_string('col_progress', 'block_itn_course_progress'), 'enabled' => in_array('progress', $configuredstudentcols, true)],
            ['key' => 'idnumber', 'label' => get_string('col_idnumber', 'block_itn_course_progress'), 'enabled' => in_array('idnumber', $configuredstudentcols, true)],
            ['key' => 'email', 'label' => get_string('col_email', 'block_itn_course_progress'), 'enabled' => in_array('email', $configuredstudentcols, true)],
            ['key' => 'firstaccess', 'label' => get_string('col_firstaccess', 'block_itn_course_progress'), 'enabled' => in_array('firstaccess', $configuredstudentcols, true)],
            ['key' => 'groupname', 'label' => get_string('col_groupname', 'block_itn_course_progress'), 'enabled' => in_array('groupname', $configuredstudentcols, true)],
            ['key' => 'timecompleted', 'label' => get_string('col_timecompleted', 'block_itn_course_progress'), 'enabled' => in_array('timecompleted', $configuredstudentcols, true)],
        ];

        $uniqueid = 'itn_cp_' . uniqid();

        return [
            'uniqueid' => $uniqueid,
            'defaultload' => $defaultload,
            'defaultperpage' => $defaultperpage,
            'enablebatchsummary' => $enablebatchsummary,
            'coursecolumns' => $allcoursecols,
            'studentcolumns' => $allstudentcols,
            'coursecolumnsjson' => json_encode($allcoursecols),
            'studentcolumnsjson' => json_encode($allstudentcols),
            'perpageoptions' => [
                ['val' => 5, 'selected' => $defaultperpage === 5],
                ['val' => 10, 'selected' => $defaultperpage === 10],
                ['val' => 25, 'selected' => $defaultperpage === 25],
                ['val' => 50, 'selected' => $defaultperpage === 50],
            ],
        ];
    }
}
