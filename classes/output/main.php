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

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Main renderable and templatable class for the Course Progress block.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
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
            [
                'key' => 'index',
                'label' => get_string('col_index', 'block_itn_course_progress'),
                'enabled' => in_array('index', $configuredcoursecols, true),
                'sortable' => false,
            ],
            [
                'key' => 'coursename',
                'label' => get_string('col_courses', 'block_itn_course_progress'),
                'enabled' => in_array('coursename', $configuredcoursecols, true),
                'sortable' => true,
            ],
            [
                'key' => 'startdate',
                'label' => get_string('col_startdate', 'block_itn_course_progress'),
                'enabled' => in_array('startdate', $configuredcoursecols, true),
                'sortable' => true,
            ],
            [
                'key' => 'enrolled',
                'label' => get_string('col_students', 'block_itn_course_progress'),
                'enabled' => in_array('enrolled', $configuredcoursecols, true),
            ],
            [
                'key' => 'progress',
                'label' => get_string('col_progress', 'block_itn_course_progress'),
                'enabled' => in_array('progress', $configuredcoursecols, true),
            ],
            [
                'key' => 'active',
                'label' => get_string('col_active', 'block_itn_course_progress'),
                'enabled' => in_array('active', $configuredcoursecols, true),
            ],
            [
                'key' => 'completed',
                'label' => get_string('col_completed', 'block_itn_course_progress'),
                'enabled' => in_array('completed', $configuredcoursecols, true),
            ],
            [
                'key' => 'visits',
                'label' => get_string('col_visits', 'block_itn_course_progress'),
                'enabled' => in_array('visits', $configuredcoursecols, true),
            ],
            [
                'key' => 'completionrate',
                'label' => get_string('col_completionrate', 'block_itn_course_progress'),
                'enabled' => in_array('completionrate', $configuredcoursecols, true),
            ],
            [
                'key' => 'notstarted',
                'label' => get_string('col_notstarted', 'block_itn_course_progress'),
                'enabled' => in_array('notstarted', $configuredcoursecols, true),
            ],
            [
                'key' => 'inprogress',
                'label' => get_string('col_inprogress', 'block_itn_course_progress'),
                'enabled' => in_array('inprogress', $configuredcoursecols, true),
            ],
            [
                'key' => 'enddate',
                'label' => get_string('col_enddate', 'block_itn_course_progress'),
                'enabled' => in_array('enddate', $configuredcoursecols, true),
                'sortable' => true,
            ],
            [
                'key' => 'category',
                'label' => get_string('col_category', 'block_itn_course_progress'),
                'enabled' => in_array('category', $configuredcoursecols, true),
            ],
            [
                'key' => 'groupscount',
                'label' => get_string('col_groupscount', 'block_itn_course_progress'),
                'enabled' => in_array('groupscount', $configuredcoursecols, true),
            ],
        ];

        $allstudentcols = [
            [
                'key' => 'index',
                'label' => get_string('col_index', 'block_itn_course_progress'),
                'enabled' => in_array('index', $configuredstudentcols, true),
                'sortable' => false,
            ],
            [
                'key' => 'name',
                'label' => get_string('col_name', 'block_itn_course_progress'),
                'enabled' => in_array('name', $configuredstudentcols, true),
                'sortable' => true,
            ],
            [
                'key' => 'status',
                'label' => get_string('col_status', 'block_itn_course_progress'),
                'enabled' => in_array('status', $configuredstudentcols, true),
                'sortable' => true,
            ],
            [
                'key' => 'progress',
                'label' => get_string('col_progress', 'block_itn_course_progress'),
                'enabled' => in_array('progress', $configuredstudentcols, true),
            ],
            [
                'key' => 'idnumber',
                'label' => get_string('col_idnumber', 'block_itn_course_progress'),
                'enabled' => in_array('idnumber', $configuredstudentcols, true),
                'sortable' => true,
            ],
            [
                'key' => 'email',
                'label' => get_string('col_email', 'block_itn_course_progress'),
                'enabled' => in_array('email', $configuredstudentcols, true),
                'sortable' => true,
            ],
            [
                'key' => 'firstaccess',
                'label' => get_string('col_firstaccess', 'block_itn_course_progress'),
                'enabled' => in_array('firstaccess', $configuredstudentcols, true),
                'sortable' => true,
            ],
            [
                'key' => 'groupname',
                'label' => get_string('col_groupname', 'block_itn_course_progress'),
                'enabled' => in_array('groupname', $configuredstudentcols, true),
            ],
            [
                'key' => 'timecompleted',
                'label' => get_string('col_timecompleted', 'block_itn_course_progress'),
                'enabled' => in_array('timecompleted', $configuredstudentcols, true),
                'sortable' => true,
            ],
        ];

        $uniqueid = 'itn_cp_' . uniqid();
        $labels = [
            'allgroups' => get_string('allgroups', 'block_itn_course_progress'),
            'batchactive' => get_string('col_batchactive', 'block_itn_course_progress'),
            'batchclose' => get_string('batchsummaryclose', 'block_itn_course_progress'),
            'batchcompleted' => get_string('col_batchcompleted', 'block_itn_course_progress'),
            'batcherror' => get_string('batchsummaryerror', 'block_itn_course_progress'),
            'batchloading' => get_string('batchsummaryloading', 'block_itn_course_progress'),
            'batchmembers' => get_string('col_members', 'block_itn_course_progress'),
            'batchname' => get_string('col_batchname', 'block_itn_course_progress'),
            'batchprogress' => get_string('col_batchprogress', 'block_itn_course_progress'),
            'batchrate' => get_string('col_batchrate', 'block_itn_course_progress'),
            'batchtitle' => get_string('batchsummarytitle', 'block_itn_course_progress'),
            'sendmessage' => get_string('studentprogress_sendmessage', 'block_itn_course_progress'),
            'viewprofile' => get_string('studentprogress_viewprofile', 'block_itn_course_progress'),
            'email' => get_string('col_email', 'block_itn_course_progress'),
            'studentid' => get_string('col_idnumber', 'block_itn_course_progress'),
            'lastaccess' => get_string('studentprogress_lastaccess', 'block_itn_course_progress'),
            'teachers' => get_string('studentprogress_teachers', 'block_itn_course_progress'),
            'completion' => get_string('studentprogress_completion', 'block_itn_course_progress'),
            'completeddate' => get_string('col_timecompleted', 'block_itn_course_progress'),
            'lastcourseaccess' => get_string('studentprogress_lastcourseaccess', 'block_itn_course_progress'),
            'visits' => get_string('col_visits', 'block_itn_course_progress'),
            'viewcourse' => get_string('studentprogress_viewcourse', 'block_itn_course_progress'),
            'nocourses' => get_string('studentprogress_nocourses', 'block_itn_course_progress'),
            'loading' => get_string('studentprogress_loading', 'block_itn_course_progress'),
            'addcontact' => get_string('studentprogress_addcontact', 'block_itn_course_progress'),
            'contactpending' => get_string('studentprogress_contactpending', 'block_itn_course_progress'),
            'alreadycontact' => get_string('studentprogress_alreadycontact', 'block_itn_course_progress'),
            'contactsucceeded' => get_string('studentprogress_contactsucceeded', 'block_itn_course_progress'),
            'coursecompleted' => get_string('studentprogress_coursecompleted', 'block_itn_course_progress'),
            'activitydetails' => get_string('studentprogress_activitydetails', 'block_itn_course_progress'),
            'hideactivities' => get_string('studentprogress_hideactivities', 'block_itn_course_progress'),
            'loadingactivities' => get_string('studentprogress_loadingactivities', 'block_itn_course_progress'),
            'noactivities' => get_string('studentprogress_noactivities', 'block_itn_course_progress'),
            'trackedactivities' => get_string('studentprogress_trackedactivities', 'block_itn_course_progress'),
            'activity' => get_string('studentprogress_activity', 'block_itn_course_progress'),
            'status' => get_string('col_status', 'block_itn_course_progress'),
            'grade' => get_string('col_grade', 'block_itn_course_progress'),
            'lastinteraction' => get_string('studentprogress_lastinteraction', 'block_itn_course_progress'),
            'interactions' => get_string('studentprogress_interactions', 'block_itn_course_progress'),
            'openactivity' => get_string('studentprogress_openactivity', 'block_itn_course_progress'),
            'errorgeneric' => get_string('errorgeneric', 'block_itn_course_progress'),
            'loadingbatches' => get_string('loadingbatches', 'block_itn_course_progress'),
            'loadingprogressdata' => get_string('loadingprogressdata', 'block_itn_course_progress'),
            'nobatches' => get_string('nobatches', 'block_itn_course_progress'),
            'nocoursestable' => get_string('nocourses', 'block_itn_course_progress'),
            'nostudents' => get_string('nostudents', 'block_itn_course_progress'),
            'nostudentsingroup' => get_string('nostudentsingroup', 'block_itn_course_progress'),
            'next' => get_string('next', 'block_itn_course_progress'),
            'previous' => get_string('previous', 'block_itn_course_progress'),
            'showingentries' => get_string('showingentries', 'block_itn_course_progress', (object)[
                'from' => '__from__',
                'to' => '__to__',
                'total' => '__total__',
            ]),
        ];

        return [
            'uniqueid' => $uniqueid,
            'defaultload' => $defaultload,
            'defaultperpage' => $defaultperpage,
            'enablebatchsummary' => $enablebatchsummary,
            'defaultavatarurl' => $output->image_url('u/f1')->out(false),
            'coursecolumns' => $allcoursecols,
            'studentcolumns' => $allstudentcols,
            'coursecolumnsjson' => json_encode($allcoursecols),
            'studentcolumnsjson' => json_encode($allstudentcols),
            'labelsjson' => json_encode($labels),
            'perpageoptions' => [
                ['val' => 5, 'selected' => $defaultperpage === 5],
                ['val' => 10, 'selected' => $defaultperpage === 10],
                ['val' => 25, 'selected' => $defaultperpage === 25],
                ['val' => 50, 'selected' => $defaultperpage === 50],
            ],
        ];
    }
}
