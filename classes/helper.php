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

use stdClass;
use context_system;
use context_course;
use moodle_url;
use user_picture;
use core_completion\progress;

/**
 * Data helper service for the Course Progress block.
 *
 * Handles database querying, role-based isolation, metric calculation,
 * group filtering, and completion aggregations with strict error safety.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 ITN-BUET
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get IDs of users with teacher or manager roles in a course to exclude from student calculations.
     *
     * @param int $courseid Course ID.
     * @return array List of user IDs.
     */
    public static function get_teacher_ids(int $courseid): array {
        global $DB;
        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ctx.instanceid = :courseid
                   AND ctx.contextlevel = " . CONTEXT_COURSE . "
                   AND ra.roleid IN (1, 2, 3, 4)";
        return $DB->get_fieldset_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Verify whether the logged in user has access to view a course progress.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID (defaults to current logged-in user).
     * @return bool True if authorized.
     */
    public static function can_view_course(int $courseid, int $userid = 0): bool {
        global $USER;

        $targetuserid = $userid > 0 ? $userid : (int)$USER->id;
        if ($targetuserid <= 0) {
            return false;
        }

        // Site administrators and managers with system view rights have global access.
        $syscontext = context_system::instance();
        if (is_siteadmin($targetuserid) || has_capability('block/itn_course_progress:view', $syscontext, $targetuserid)) {
            return true;
        }

        // Check course context capability.
        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return false;
        }

        if (has_capability('block/itn_course_progress:view', $coursecontext, $targetuserid)) {
            return true;
        }

        // Also permit if user has editingteacher or teacher capability in this course.
        if (has_capability('moodle/course:update', $coursecontext, $targetuserid) ||
            has_capability('moodle/course:viewhiddensections', $coursecontext, $targetuserid)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user is authorized to view course progress in any context or for a specific context.
     *
     * @param int $userid User ID (defaults to current logged-in user).
     * @param \context|null $context Optional context (course or system).
     * @return bool True if authorized.
     */
    public static function can_user_view_any_progress(int $userid = 0, ?\context $context = null): bool {
        global $USER;

        $targetuserid = $userid > 0 ? $userid : (int)$USER->id;
        if ($targetuserid <= 0) {
            return false;
        }

        // Site administrators or site config managers.
        if (is_siteadmin($targetuserid) ||
            has_capability('moodle/site:config', context_system::instance(), $targetuserid) ||
            has_capability('block/itn_course_progress:view', context_system::instance(), $targetuserid)) {
            return true;
        }

        // If a course context is passed, check permission in that course directly.
        if ($context instanceof context_course) {
            return self::can_view_course((int)$context->instanceid, $targetuserid);
        }

        // On dashboard or system level, check if user is teacher/manager in at least one course.
        $mycourses = enrol_get_all_users_courses($targetuserid, true, null, 'visible DESC, sortorder ASC');
        foreach ($mycourses as $c) {
            if (self::can_view_course((int)$c->id, $targetuserid)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Get paginated courses list for the overview table (Requirement 3).
     *
     * @param int $userid User ID viewing the report.
     * @param string $search Search query for course name/shortname.
     * @param string $sort Column to sort by.
     * @param string $sortdir Sort direction ('ASC' or 'DESC').
     * @param int $page Page index (0-based).
     * @param int $perpage Records per page.
     * @param bool $loadprogress Whether to calculate progress metrics.
     * @return array Array containing 'courses', 'total', 'page', 'perpage', 'from', 'to'.
     */
    public static function get_courses(
        int $userid = 0,
        string $search = '',
        string $sort = 'coursename',
        string $sortdir = 'ASC',
        int $page = 0,
        int $perpage = 10,
        bool $loadprogress = true
    ): array {
        global $DB, $USER;

        $currentuserid = $userid > 0 ? $userid : (int)$USER->id;
        $isadmin = is_siteadmin($currentuserid) || has_capability('moodle/site:config', context_system::instance(), $currentuserid);

        // Build base courses SQL.
        $params = [
            'siteid' => SITEID,
        ];
        $where = ['c.id != :siteid', 'c.visible = 1'];

        // Role-based course restriction for non-admins.
        if (!$isadmin) {
            $mycourses = enrol_get_all_users_courses($currentuserid, true, null, 'visible DESC, sortorder ASC');
            $allowedids = [];
            foreach ($mycourses as $c) {
                if (self::can_view_course((int)$c->id, $currentuserid)) {
                    $allowedids[] = (int)$c->id;
                }
            }
            if (empty($allowedids)) {
                return [
                    'courses' => [],
                    'total' => 0,
                    'page' => $page,
                    'perpage' => $perpage,
                    'from' => 0,
                    'to' => 0,
                ];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($allowedids, SQL_PARAMS_NAMED, 'crs');
            $where[] = "c.id $insql";
            $params = array_merge($params, $inparams);
        }

        // Search filtering.
        if (!empty(trim($search))) {
            $searchclean = '%' . $DB->sql_like_escape(trim($search)) . '%';
            $params['search1'] = $searchclean;
            $params['search2'] = $searchclean;
            $where[] = '(' . $DB->sql_like('c.fullname', ':search1', false) . ' OR ' .
                             $DB->sql_like('c.shortname', ':search2', false) . ')';
        }

        $wherestr = implode(' AND ', $where);

        // Count total matching courses.
        $total = $DB->count_records_sql("SELECT COUNT(c.id) FROM {course} c WHERE $wherestr", $params);
        if ($total === 0) {
            return [
                'courses' => [],
                'total' => 0,
                'page' => $page,
                'perpage' => $perpage,
                'from' => 0,
                'to' => 0,
            ];
        }

        // Allowed sort columns whitelist.
        $sortcolumn = match ($sort) {
            'startdate' => 'c.startdate',
            'enddate' => 'c.enddate',
            'id' => 'c.id',
            default => 'c.fullname',
        };
        $sortdirection = strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';
        $orderstr = "$sortcolumn $sortdirection, c.id ASC";

        // Query course records with pagination.
        $limitfrom = $page * $perpage;
        $courserecords = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate, c.category, c.enablecompletion
               FROM {course} c
              WHERE $wherestr
           ORDER BY $orderstr",
            $params,
            $limitfrom,
            $perpage
        );

        $courses = [];
        $index = $limitfrom + 1;

        // Fetch category names for courses in batch.
        $categoryids = array_unique(array_filter(array_map(fn($c) => (int)$c->category, $courserecords)));
        $categorynames = [];
        if (!empty($categoryids)) {
            [$catinsql, $catinparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
            $catrecords = $DB->get_records_sql("SELECT id, name FROM {course_categories} WHERE id $catinsql", $catinparams);
            foreach ($catrecords as $cat) {
                $categorynames[(int)$cat->id] = format_string($cat->name);
            }
        }

        foreach ($courserecords as $course) {
            $courseid = (int)$course->id;

            // Exclude teachers/instructors from student counts.
            $teacherids = self::get_teacher_ids($courseid);
            $tchwhere = '';
            $tchparams = [];
            if (!empty($teacherids)) {
                [$tinsql, $tchparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, 'tch', false);
                $tchwhere = " AND u.id $tinsql";
            }

            // 1. Enrolled students count.
            $enrolledsql = "SELECT COUNT(DISTINCT ue.userid)
                              FROM {user_enrolments} ue
                              JOIN {enrol} e ON e.id = ue.enrolid
                              JOIN {user} u ON u.id = ue.userid
                             WHERE e.courseid = :courseid
                               AND ue.status = 0
                               AND u.deleted = 0
                               $tchwhere";
            $enrolled = (int)$DB->count_records_sql($enrolledsql, array_merge(['courseid' => $courseid], $tchparams));

            // 2. Active students count (accessed course at least once).
            $activesql = "SELECT COUNT(DISTINCT ula.userid)
                            FROM {user_lastaccess} ula
                            JOIN {user_enrolments} ue ON ue.userid = ula.userid
                            JOIN {enrol} e ON e.id = ue.enrolid
                            JOIN {user} u ON u.id = ue.userid
                           WHERE e.courseid = :courseid
                             AND ula.courseid = :courseid2
                             AND ue.status = 0
                             AND u.deleted = 0
                             $tchwhere";
            $active = (int)$DB->count_records_sql($activesql, array_merge(['courseid' => $courseid, 'courseid2' => $courseid], $tchparams));

            // 3. Completed students count.
            $completedsql = "SELECT COUNT(DISTINCT cc.userid)
                               FROM {course_completions} cc
                               JOIN {user_enrolments} ue ON ue.userid = cc.userid
                               JOIN {enrol} e ON e.id = ue.enrolid
                               JOIN {user} u ON u.id = ue.userid
                              WHERE e.courseid = :courseid
                                AND cc.course = :courseid2
                                AND cc.timecompleted IS NOT NULL
                                AND cc.timecompleted > 0
                                AND ue.status = 0
                                AND u.deleted = 0
                                $tchwhere";
            $completed = (int)$DB->count_records_sql($completedsql, array_merge(['courseid' => $courseid, 'courseid2' => $courseid], $tchparams));

            // 4. Total course visits (from standard logstore).
            $visitssql = "SELECT COUNT(id)
                            FROM {logstore_standard_log}
                           WHERE courseid = :courseid
                             AND anonymous = 0";
            $visits = (int)$DB->count_records_sql($visitssql, ['courseid' => $courseid]);

            // 5. Groups count.
            $groupscount = (int)$DB->count_records('groups', ['courseid' => $courseid]);

            // 6. Average completion progress calculation.
            $progressval = 0;
            $progressloaded = false;
            $hascompletion = !empty($course->enablecompletion);

            if ($loadprogress && $hascompletion && $enrolled > 0) {
                $progressval = self::calculate_course_average_progress($course);
                $progressloaded = true;
            } elseif ($loadprogress && !$hascompletion) {
                $progressloaded = true;
                $progressval = 0;
            }

            $completionrate = $enrolled > 0 ? (int)round(($completed / $enrolled) * 100) : 0;
            $notstarted = max(0, $enrolled - $active);
            $inprogress = max(0, $active - $completed);

            $courses[] = [
                'index' => $index++,
                'id' => $courseid,
                'coursename' => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
                'startdate' => $course->startdate > 0 ? userdate($course->startdate, '%d %b, %Y') : '-',
                'startdate_raw' => (int)$course->startdate,
                'enddate' => $course->enddate > 0 ? userdate($course->enddate, '%d %b, %Y') : '-',
                'category' => $categorynames[(int)$course->category] ?? '',
                'enrolled' => $enrolled,
                'active' => $active,
                'completed' => $completed,
                'visits' => $visits,
                'completionrate' => $completionrate,
                'notstarted' => $notstarted,
                'inprogress' => $inprogress,
                'groupscount' => $groupscount,
                'hascompletion' => $hascompletion,
                'progress' => $progressval,
                'progressloaded' => $progressloaded,
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            ];
        }

        $from = $limitfrom + 1;
        $to = min($limitfrom + $perpage, $total);

        return [
            'courses' => $courses,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Calculate average completion progress percentage for a course.
     *
     * @param stdClass $course Course object.
     * @return int Progress percentage (0 - 100).
     */
    public static function calculate_course_average_progress(stdClass $course): int {
        global $DB;

        if (empty($course->enablecompletion)) {
            return 0;
        }

        // Get all enrolled active student IDs in this course (excluding teachers).
        $teacherids = self::get_teacher_ids((int)$course->id);
        $tchwhere = '';
        $tchparams = ['courseid' => (int)$course->id];
        if (!empty($teacherids)) {
            [$tinsql, $tinparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, 'tch', false);
            $tchwhere = " AND u.id $tinsql";
            $tchparams = array_merge($tchparams, $tinparams);
        }

        $sql = "SELECT DISTINCT u.id
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                 WHERE e.courseid = :courseid
                   AND ue.status = 0
                   AND u.deleted = 0
                   $tchwhere";
        $students = $DB->get_records_sql($sql, $tchparams);
        if (empty($students)) {
            return 0;
        }

        $totalprogress = 0;
        $counted = 0;

        foreach ($students as $student) {
            $userprogress = progress::get_course_progress_percentage($course, (int)$student->id);
            if ($userprogress !== null) {
                $totalprogress += (float)$userprogress;
                $counted++;
            }
        }

        return $counted > 0 ? (int)round($totalprogress / $counted) : 0;
    }

    /**
     * Get paginated students list for a course with group filter (Requirements 4 & 5).
     *
     * @param int $courseid Course ID.
     * @param int $groupid Group ID (0 for all).
     * @param string $search Search query for student name/email.
     * @param string $sort Sort column.
     * @param string $sortdir Sort direction ('ASC' or 'DESC').
     * @param int $page Page index (0-based).
     * @param int $perpage Records per page.
     * @return array Array containing students list and pagination info.
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
        global $DB, $PAGE;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Security check.
        if (!self::can_view_course($courseid)) {
            throw new \required_capability_exception(
                context_course::instance($courseid),
                'block/itn_course_progress:view',
                'nopermissions',
                ''
            );
        }

        $params = [
            'courseid' => $courseid,
            'courseid2' => $courseid,
            'courseid3' => $courseid,
        ];
        $where = [
            'e.courseid = :courseid',
            'ue.status = 0',
            'u.deleted = 0',
        ];

        // Filter out users who have teacher/manager roles in this course.
        $teacherids = self::get_teacher_ids($courseid);
        if (!empty($teacherids)) {
            [$tinsql, $tinparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, 'tch', false);
            $where[] = "u.id $tinsql";
            $params = array_merge($params, $tinparams);
        }

        // Group filter (Requirement 4).
        $groupjoin = '';
        if ($groupid > 0) {
            $groupjoin = "JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid = :groupid";
            $params['groupid'] = $groupid;
        }

        // Search filter.
        if (!empty(trim($search))) {
            $searchclean = '%' . $DB->sql_like_escape(trim($search)) . '%';
            $params['search1'] = $searchclean;
            $params['search2'] = $searchclean;
            $fullnameconcat = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
            $where[] = '(' . $DB->sql_like($fullnameconcat, ':search1', false) . ' OR ' .
                             $DB->sql_like('u.email', ':search2', false) . ')';
        }

        $wherestr = implode(' AND ', $where);

        // Count total matching students.
        $countsql = "SELECT COUNT(DISTINCT u.id)
                       FROM {user} u
                       JOIN {user_enrolments} ue ON ue.userid = u.id
                       JOIN {enrol} e ON e.id = ue.enrolid
                       $groupjoin
                      WHERE $wherestr";
        $total = $DB->count_records_sql($countsql, $params);
        if ($total === 0) {
            return [
                'students' => [],
                'total' => 0,
                'page' => $page,
                'perpage' => $perpage,
                'from' => 0,
                'to' => 0,
                'course' => [
                    'id' => $course->id,
                    'fullname' => format_string($course->fullname),
                ],
            ];
        }

        // Sorting whitelist.
        $sortcolumn = match ($sort) {
            'status' => 'ula.timeaccess',
            'idnumber' => 'u.idnumber',
            'email' => 'u.email',
            default => 'u.firstname',
        };
        $sortdirection = strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';
        $orderstr = "$sortcolumn $sortdirection, u.lastname $sortdirection, u.id ASC";

        $limitfrom = $page * $perpage;
        $userfields = \core_user\fields::for_userpic()->including('idnumber')->get_sql('u');
        $selectsql = "SELECT DISTINCT " . ltrim($userfields->selects, ', ') . ",
                             ula.timeaccess AS courselastaccess,
                             ue.timestart AS firstaccess,
                             cc.timecompleted
                        FROM {user} u
                        JOIN {user_enrolments} ue ON ue.userid = u.id
                        JOIN {enrol} e ON e.id = ue.enrolid
                        LEFT JOIN {user_lastaccess} ula ON ula.userid = u.id AND ula.courseid = :courseid2
                        LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = :courseid3
                        $groupjoin
                       WHERE $wherestr
                    ORDER BY $orderstr";

        $studentrecords = $DB->get_records_sql($selectsql, $params, $limitfrom, $perpage);

        $students = [];
        $index = $limitfrom + 1;

        // Fetch groups for the students in this course.
        $userids = array_keys($studentrecords);
        $usergroups = [];
        if (!empty($userids)) {
            [$userinsql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
            $usergroupparams = array_merge(['courseid' => $courseid], $userinparams);
            $ugsql = "SELECT gm.id, gm.userid, g.name AS groupname
                        FROM {groups_members} gm
                        JOIN {groups} g ON g.id = gm.groupid
                       WHERE g.courseid = :courseid
                         AND gm.userid $userinsql";
            $ugrecords = $DB->get_records_sql($ugsql, $usergroupparams);
            foreach ($ugrecords as $ug) {
                $usergroups[(int)$ug->userid][] = format_string($ug->groupname);
            }
        }

        foreach ($studentrecords as $stu) {
            $stuid = (int)$stu->id;

            // User avatar URL.
            $userpicture = new user_picture($stu);
            $userpicture->size = 1; // Standard size.
            $avatarurl = $userpicture->get_url($PAGE)->out(false);

            // Status Badge calculation.
            if (!empty($stu->courselastaccess) && $stu->courselastaccess > 0) {
                $statustext = get_string('status_lastactive', 'block_itn_course_progress',
                    userdate($stu->courselastaccess, '%d %b, %Y'));
                $statusclass = 'active';
            } else {
                $statustext = get_string('status_never', 'block_itn_course_progress');
                $statusclass = 'never';
            }

            // Individual course progress percentage.
            $progressval = 0;
            if (!empty($course->enablecompletion)) {
                $progressval = (int)(progress::get_course_progress_percentage($course, $stuid) ?? 0);
            }

            // Groups string.
            $groupnames = $usergroups[$stuid] ?? [];
            $groupnamestr = !empty($groupnames) ? implode(', ', $groupnames) : '-';

            $students[] = [
                'index' => $index++,
                'id' => $stuid,
                'name' => fullname($stu),
                'email' => $stu->email,
                'idnumber' => !empty($stu->idnumber) ? $stu->idnumber : '-',
                'avatarurl' => $avatarurl,
                'status' => $statustext,
                'statusclass' => $statusclass,
                'progress' => $progressval,
                'groupname' => $groupnamestr,
                'firstaccess' => $stu->firstaccess > 0 ? userdate($stu->firstaccess, '%d %b, %Y') : '-',
                'timecompleted' => $stu->timecompleted > 0 ? userdate($stu->timecompleted, '%d %b, %Y') : '-',
                'messageurl' => (new moodle_url('/message/index.php', ['id' => $stuid]))->out(false),
            ];
        }

        $from = $limitfrom + 1;
        $to = min($limitfrom + $perpage, $total);

        return [
            'students' => $students,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'from' => $from,
            'to' => $to,
            'course' => [
                'id' => $course->id,
                'fullname' => format_string($course->fullname),
            ],
        ];
    }

    /**
     * Get available groups for a course (Requirement 4).
     *
     * @param int $courseid Course ID.
     * @return array List of groups with 'id' and 'name'.
     */
    public static function get_groups(int $courseid): array {
        global $DB;

        if (!self::can_view_course($courseid)) {
            throw new \required_capability_exception(
                context_course::instance($courseid),
                'block/itn_course_progress:view',
                'nopermissions',
                ''
            );
        }

        $groups = groups_get_all_groups($courseid);
        $result = [
            [
                'id' => 0,
                'name' => get_string('allgroups', 'block_itn_course_progress'),
            ]
        ];

        foreach ($groups as $g) {
            $result[] = [
                'id' => (int)$g->id,
                'name' => format_string($g->name),
            ];
        }

        return $result;
    }

    /**
     * Get batch-wise comparative performance summary for a course (Requirement 4 Extension).
     *
     * @param int $courseid Course ID.
     * @return array Comparative batches summary list.
     */
    public static function get_batch_summary(int $courseid): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        if (!self::can_view_course($courseid)) {
            return [];
        }

        $groups = groups_get_all_groups($courseid);
        if (empty($groups)) {
            return [];
        }

        $summary = [];
        $teacherids = self::get_teacher_ids($courseid);
        $tchwhere = '';
        $tchparams = [];
        if (!empty($teacherids)) {
            [$tinsql, $tchparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, 'tch', false);
            $tchwhere = " AND u.id $tinsql";
        }

        foreach ($groups as $g) {
            $groupid = (int)$g->id;

            // 1. Total members in group (excluding teachers).
            $membersql = "SELECT COUNT(DISTINCT gm.userid)
                            FROM {groups_members} gm
                            JOIN {user_enrolments} ue ON ue.userid = gm.userid
                            JOIN {enrol} e ON e.id = ue.enrolid
                            JOIN {user} u ON u.id = gm.userid
                           WHERE gm.groupid = :groupid
                             AND e.courseid = :courseid
                             AND ue.status = 0
                             AND u.deleted = 0
                             $tchwhere";
            $members = (int)$DB->count_records_sql($membersql, array_merge(['groupid' => $groupid, 'courseid' => $courseid], $tchparams));

            // 2. Active members in group (excluding teachers).
            $activesql = "SELECT COUNT(DISTINCT ula.userid)
                            FROM {groups_members} gm
                            JOIN {user_lastaccess} ula ON ula.userid = gm.userid
                            JOIN {user_enrolments} ue ON ue.userid = gm.userid
                            JOIN {enrol} e ON e.id = ue.enrolid
                            JOIN {user} u ON u.id = gm.userid
                           WHERE gm.groupid = :groupid
                             AND e.courseid = :courseid
                             AND ula.courseid = :courseid2
                             AND ue.status = 0
                             AND u.deleted = 0
                             $tchwhere";
            $active = (int)$DB->count_records_sql($activesql, array_merge(['groupid' => $groupid, 'courseid' => $courseid, 'courseid2' => $courseid], $tchparams));

            // 3. Completed members in group (excluding teachers).
            $completedsql = "SELECT COUNT(DISTINCT cc.userid)
                               FROM {groups_members} gm
                               JOIN {course_completions} cc ON cc.userid = gm.userid
                               JOIN {user_enrolments} ue ON ue.userid = gm.userid
                               JOIN {enrol} e ON e.id = ue.enrolid
                               JOIN {user} u ON u.id = gm.userid
                              WHERE gm.groupid = :groupid
                                AND e.courseid = :courseid
                                AND cc.course = :courseid2
                                AND cc.timecompleted IS NOT NULL
                                AND cc.timecompleted > 0
                                AND ue.status = 0
                                AND u.deleted = 0
                                $tchwhere";
            $completed = (int)$DB->count_records_sql($completedsql, array_merge(['groupid' => $groupid, 'courseid' => $courseid, 'courseid2' => $courseid], $tchparams));

            // 4. Batch average progress.
            $avgprogress = 0;
            if (!empty($course->enablecompletion) && $members > 0) {
                $userids = $DB->get_fieldset_sql(
                    "SELECT DISTINCT gm.userid
                       FROM {groups_members} gm
                       JOIN {user_enrolments} ue ON ue.userid = gm.userid
                       JOIN {enrol} e ON e.id = ue.enrolid
                       JOIN {user} u ON u.id = gm.userid
                      WHERE gm.groupid = :groupid
                        AND e.courseid = :courseid
                        AND ue.status = 0
                        AND u.deleted = 0
                        $tchwhere",
                    array_merge(['groupid' => $groupid, 'courseid' => $courseid], $tchparams)
                );

                $totalprog = 0;
                $progcount = 0;
                foreach ($userids as $uid) {
                    $p = progress::get_course_progress_percentage($course, (int)$uid);
                    if ($p !== null) {
                        $totalprog += (float)$p;
                        $progcount++;
                    }
                }
                $avgprogress = $progcount > 0 ? (int)round($totalprog / $progcount) : 0;
            }

            $rate = $members > 0 ? (int)round(($completed / $members) * 100) : 0;

            $summary[] = [
                'groupid' => $groupid,
                'batchname' => format_string($g->name),
                'members' => $members,
                'active' => $active,
                'completed' => $completed,
                'avgprogress' => $avgprogress,
                'completionrate' => $rate,
            ];
        }

        return $summary;
    }
}
