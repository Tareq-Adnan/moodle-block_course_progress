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
 * Language strings for the ITN-BUET Course Progress block.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 ITN-BUET
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course Progress';
$string['itn_course_progress:addinstance'] = 'Add a new Course Progress block';
$string['itn_course_progress:myaddinstance'] = 'Add a new Course Progress block to Dashboard';
$string['itn_course_progress:view'] = 'View Course Progress dashboard';
$string['privacy:metadata'] = 'The Course Progress block does not store personal data. It visualizes existing course enrolments and completion records.';

// Headings and UI Controls.
$string['blocktitle'] = 'Course Progress';
$string['searchcourses'] = 'Search For Courses';
$string['alwaysloadprogress'] = 'Always load progress';
$string['loadprogress'] = 'Load progress';
$string['showentries'] = 'Show {$a} Entries';
$string['showingentries'] = 'Showing {$a->from} to {$a->to} of {$a->total} entries';
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['close'] = 'Back to courses';
$string['expand'] = 'Expand view';
$string['collapse'] = 'Collapse view';
$string['loading'] = 'Loading data...';
$string['retry'] = 'Retry';
$string['errorloading'] = 'Unable to load progress data. Please try again.';
$string['columns'] = 'Columns';
$string['resetcolumns'] = 'Reset';
$string['selectgroup'] = 'Select Group';
$string['allgroups'] = 'All';
$string['searchstudents'] = 'Search';
$string['togglebatchsummary'] = 'Batch Summary';
$string['batchsummarytitle'] = 'Batch Performance Comparison';

// Course Table Column Headers.
$string['col_index'] = '#';
$string['col_courses'] = 'Courses';
$string['col_startdate'] = 'Start Date';
$string['col_students'] = 'Students';
$string['col_progress'] = 'Progress';
$string['col_active'] = 'Active Students';
$string['col_completed'] = 'Completed';
$string['col_visits'] = 'Visits';
$string['col_completionrate'] = 'Completion Rate %';
$string['col_notstarted'] = 'Not Started';
$string['col_inprogress'] = 'In Progress';
$string['col_enddate'] = 'End Date';
$string['col_category'] = 'Category';
$string['col_groupscount'] = 'Groups Count';

// Student Table Column Headers.
$string['col_name'] = 'Name';
$string['col_status'] = 'Status';
$string['col_idnumber'] = 'Student ID';
$string['col_email'] = 'Email';
$string['col_firstaccess'] = 'First Access';
$string['col_groupname'] = 'Group / Batch';
$string['col_timecompleted'] = 'Date Completed';
$string['col_activitiesratio'] = 'Activities Completed';
$string['col_grade'] = 'Grade';
$string['status_lastactive'] = 'Last Active on {$a}';
$string['status_inactive'] = 'Inactive since {$a}';
$string['status_never'] = 'Never Accessed';
$string['status_notracking'] = 'No tracking';
$string['sendmessage'] = 'Send message to {$a}';

// Individual Student Progress.
$string['studentprogress_title'] = 'Individual Student Progress';
$string['studentprogress_back'] = 'Back to students';
$string['studentprogress_loading'] = 'Loading student progress...';
$string['studentprogress_sendmessage'] = 'Send message';
$string['studentprogress_viewprofile'] = 'View profile';
$string['studentprogress_lastaccess'] = 'Last site access';
$string['studentprogress_teachers'] = 'Teacher(s)';
$string['studentprogress_completion'] = 'Activity completion';
$string['studentprogress_viewcourse'] = 'View course';
$string['studentprogress_nocourses'] = 'No enrolled courses are available within your reporting access.';
$string['studentprogress_status_completed'] = 'Completed';
$string['studentprogress_status_inprogress'] = 'In progress';
$string['studentprogress_status_notstarted'] = 'Not started';
$string['studentprogress_activitysummary'] = '{$a->completed} of {$a->total} activities';
$string['studentprogress_addcontact'] = 'Add to contacts';
$string['studentprogress_contactpending'] = 'Contact request pending';
$string['studentprogress_alreadycontact'] = 'Already a contact';
$string['studentprogress_contactsucceeded'] = 'Contact request sent';
$string['studentprogress_contacts'] = 'Contacts';
$string['studentprogress_discussions'] = 'Discussions';
$string['studentprogress_blogentries'] = 'Blog Entries';
$string['studentprogress_badges'] = 'Badges';
$string['studentprogress_stats'] = 'Student activity summary';
$string['studentprogress_sections'] = 'Student profile sections';
$string['studentprogress_courses'] = 'Courses';
$string['studentprogress_moredetails'] = 'More Details';
$string['studentprogress_coursecompleted'] = 'Course Completed';

// Batch Table Columns.
$string['col_batchname'] = 'Batch Name';
$string['col_members'] = 'Members';
$string['col_batchactive'] = 'Active';
$string['col_batchcompleted'] = 'Completed';
$string['col_batchprogress'] = 'Average Progress';
$string['col_batchrate'] = 'Completion Rate';

// Empty States.
$string['nocourses'] = 'No courses found';
$string['nostudents'] = 'No students enrolled in this course yet';
$string['nostudentsingroup'] = 'No students found in this group';
$string['nobatches'] = 'No groups or batches created in this course';

// Admin Settings.
$string['settings:defaultloadprogress'] = 'Always load progress by default';
$string['settings:defaultloadprogress_desc'] = 'If enabled, course progress percentages and circular rings calculate immediately upon dashboard load. If disabled, users click "Load progress" on demand.';
$string['settings:defaultperpage'] = 'Default entries per page';
$string['settings:defaultperpage_desc'] = 'Number of entries displayed per page in course and student tables.';
$string['settings:course_columns'] = 'Default Course Overview Columns';
$string['settings:course_columns_desc'] = 'Select the columns visible by default in the Course Engagement Overview table.';
$string['settings:student_columns'] = 'Default Student Drill-down Columns';
$string['settings:student_columns_desc'] = 'Select the columns visible by default in the Student Engagement Drill-Down table.';
$string['settings:enablebatchsummary'] = 'Enable Batch Summary Comparison';
$string['settings:enablebatchsummary_desc'] = 'Allow teachers and managers to view a comparative batch summary table inside the student drill-down view.';
$string['settings:activedays'] = 'Active student period (days)';
$string['settings:activedays_desc'] = 'A learner is counted as active when they accessed the course within this many days. The default is 30 days.';
