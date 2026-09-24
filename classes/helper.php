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

    /** Capability used by Moodle core to identify users tracked in completion reports. */
    private const TRACKED_USER_CAPABILITY = 'moodle/course:isincompletionreports';

    /** Standard Moodle event emitted when a course is viewed. */
    private const COURSE_VIEWED_EVENT = '\\core\\event\\course_viewed';

    /**
     * Return the configured activity window in seconds.
     *
     * @return int Activity window in seconds.
     */
    private static function get_active_window(): int {
        $days = max(1, (int)(get_config('block_itn_course_progress', 'activedays') ?: 30));
        return $days * DAYSECS;
    }

    /**
     * Get the groups the current user may report on in a course.
     *
     * Separate-groups mode restricts users without access-all-groups to their own
     * groups. Other modes permit reporting across all course groups.
     *
     * @param stdClass $course Course record.
     * @return array Groups keyed by group ID.
     */
    private static function get_accessible_groups(stdClass $course): array {
        global $USER;

        $context = context_course::instance((int)$course->id);
        $groupmode = groups_get_course_groupmode($course);
        if ($groupmode === SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $context)) {
            return groups_get_all_groups((int)$course->id, (int)$USER->id);
        }

        return groups_get_all_groups((int)$course->id);
    }

    /**
     * Validate and return an accessible group.
     *
     * @param stdClass $course Course record.
     * @param int $groupid Group ID.
     * @return stdClass|null Group record, or null for all groups.
     */
    private static function validate_group(stdClass $course, int $groupid): ?stdClass {
        if ($groupid === 0) {
            return null;
        }

        $groups = self::get_accessible_groups($course);
        if (!isset($groups[$groupid])) {
            throw new \invalid_parameter_exception('The selected group is not available in this course.');
        }

        return $groups[$groupid];
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
            $coursecontext = context_course::instance($courseid);
            [$trackeduserssql, $trackedparams] = get_enrolled_sql(
                $coursecontext,
                self::TRACKED_USER_CAPABILITY,
                0,
                true
            );

            // Moodle completion reports define the tracked learner population.
            $enrolled = (int)$DB->count_records_sql(
                "SELECT COUNT(tracked.id) FROM ($trackeduserssql) tracked",
                $trackedparams
            );

            // Active means the learner accessed this course within the configured window.
            $activeparams = array_merge($trackedparams, [
                'activecourseid' => $courseid,
                'activethreshold' => time() - self::get_active_window(),
            ]);
            $active = (int)$DB->count_records_sql(
                "SELECT COUNT(tracked.id)
                   FROM ($trackeduserssql) tracked
                   JOIN {user_lastaccess} ula ON ula.userid = tracked.id
                  WHERE ula.courseid = :activecourseid
                    AND ula.timeaccess >= :activethreshold",
                $activeparams
            );

            // Started means the learner has accessed the course at least once.
            $startedparams = array_merge($trackedparams, ['startedcourseid' => $courseid]);
            $started = (int)$DB->count_records_sql(
                "SELECT COUNT(tracked.id)
                   FROM ($trackeduserssql) tracked
                   JOIN {user_lastaccess} ula ON ula.userid = tracked.id
                  WHERE ula.courseid = :startedcourseid
                    AND ula.timeaccess > 0",
                $startedparams
            );

            $completedparams = array_merge($trackedparams, ['completedcourseid' => $courseid]);
            $completed = (int)$DB->count_records_sql(
                "SELECT COUNT(tracked.id)
                   FROM ($trackeduserssql) tracked
                   JOIN {course_completions} cc ON cc.userid = tracked.id
                  WHERE cc.course = :completedcourseid
                    AND cc.timecompleted IS NOT NULL
                    AND cc.timecompleted > 0",
                $completedparams
            );

            // A visit is a core course_viewed event generated by a tracked learner.
            $visitsparams = array_merge($trackedparams, [
                'visitcourseid' => $courseid,
                'courseviewedevent' => self::COURSE_VIEWED_EVENT,
            ]);
            $visits = (int)$DB->count_records_sql(
                "SELECT COUNT(log.id)
                   FROM {logstore_standard_log} log
                   JOIN ($trackeduserssql) tracked ON tracked.id = log.userid
                  WHERE log.courseid = :visitcourseid
                    AND log.eventname = :courseviewedevent
                    AND log.anonymous = 0",
                $visitsparams
            );

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
            $notstarted = max(0, $enrolled - $started);
            $inprogress = max(0, $started - $completed);

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

        [$trackeduserssql, $trackedparams] = get_enrolled_sql(
            context_course::instance((int)$course->id),
            self::TRACKED_USER_CAPABILITY,
            0,
            true
        );
        $students = $DB->get_records_sql(
            "SELECT tracked.id FROM ($trackeduserssql) tracked",
            $trackedparams
        );
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

        self::validate_group($course, $groupid);
        $coursecontext = context_course::instance($courseid);
        [$trackeduserssql, $params] = get_enrolled_sql(
            $coursecontext,
            self::TRACKED_USER_CAPABILITY,
            $groupid,
            true
        );
        $where = [];

        // Search filter.
        if (!empty(trim($search))) {
            $searchclean = '%' . $DB->sql_like_escape(trim($search)) . '%';
            $params['search1'] = $searchclean;
            $params['search2'] = $searchclean;
            $fullnameconcat = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
            $where[] = '(' . $DB->sql_like($fullnameconcat, ':search1', false) . ' OR ' .
                             $DB->sql_like('u.email', ':search2', false) . ')';
        }

        $wherestr = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

        // Count total matching students.
        $countsql = "SELECT COUNT(DISTINCT u.id)
                       FROM ($trackeduserssql) tracked
                       JOIN {user} u ON u.id = tracked.id
                       $wherestr";
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
            'firstaccess' => 'firstaccess',
            'timecompleted' => 'cc.timecompleted',
            default => 'u.firstname',
        };
        $sortdirection = strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';
        $orderstr = "$sortcolumn $sortdirection, u.lastname $sortdirection, u.id ASC";

        $limitfrom = $page * $perpage;
        $userfields = \core_user\fields::for_userpic()->including('idnumber', 'email')->get_sql('u');
        $selectparams = array_merge($params, [
            'lastaccesscourseid' => $courseid,
            'completioncourseid' => $courseid,
            'firstaccesscourseid' => $courseid,
            'firstaccessevent' => self::COURSE_VIEWED_EVENT,
        ]);
        $selectsql = "SELECT DISTINCT " . ltrim($userfields->selects, ', ') . ",
                             ula.timeaccess AS courselastaccess,
                             (SELECT MIN(firstlog.timecreated)
                                FROM {logstore_standard_log} firstlog
                               WHERE firstlog.userid = u.id
                                 AND firstlog.courseid = :firstaccesscourseid
                                 AND firstlog.eventname = :firstaccessevent
                                 AND firstlog.anonymous = 0) AS firstaccess,
                             cc.timecompleted
                        FROM ($trackeduserssql) tracked
                        JOIN {user} u ON u.id = tracked.id
                        LEFT JOIN {user_lastaccess} ula
                               ON ula.userid = u.id AND ula.courseid = :lastaccesscourseid
                        LEFT JOIN {course_completions} cc
                               ON cc.userid = u.id AND cc.course = :completioncourseid
                        $wherestr
                    ORDER BY $orderstr";

        $studentrecords = $DB->get_records_sql($selectsql, $selectparams, $limitfrom, $perpage);

        $students = [];
        $index = $limitfrom + 1;

        // Fetch groups for the students in this course.
        $userids = array_keys($studentrecords);
        $usergroups = [];
        $accessiblegroups = self::get_accessible_groups($course);
        if (!empty($userids) && !empty($accessiblegroups)) {
            [$userinsql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
            [$groupinsql, $groupinparams] = $DB->get_in_or_equal(
                array_keys($accessiblegroups),
                SQL_PARAMS_NAMED,
                'visiblegrp'
            );
            $usergroupparams = array_merge(['courseid' => $courseid], $userinparams, $groupinparams);
            $ugsql = "SELECT gm.id, gm.userid, g.name AS groupname
                        FROM {groups_members} gm
                        JOIN {groups} g ON g.id = gm.groupid
                       WHERE g.courseid = :courseid
                         AND g.id $groupinsql
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
            if (!empty($stu->courselastaccess) &&
                    $stu->courselastaccess >= time() - self::get_active_window()) {
                $statustext = get_string('status_lastactive', 'block_itn_course_progress',
                    userdate($stu->courselastaccess, '%d %b, %Y'));
                $statusclass = 'active';
            } else if (!empty($stu->courselastaccess)) {
                $statustext = get_string('status_inactive', 'block_itn_course_progress',
                    userdate($stu->courselastaccess, '%d %b, %Y'));
                $statusclass = 'inactive';
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
     * Get one learner's progress across courses visible to the current reporter.
     *
     * The source course is used as the authorization boundary for selecting the
     * learner. Each returned course is independently checked so a teacher cannot
     * discover progress from a course they are not allowed to report on.
     *
     * @param int $studentid Student user ID.
     * @param int $sourcecourseid Course from which the student was selected.
     * @return array Student details and visible course progress records.
     */
    public static function get_student_progress(int $studentid, int $sourcecourseid): array {
        global $DB, $PAGE, $USER;

        $DB->get_record('course', ['id' => $sourcecourseid], 'id', MUST_EXIST);
        $sourcecontext = context_course::instance($sourcecourseid);
        if (!self::can_view_course($sourcecourseid)) {
            throw new \required_capability_exception(
                $sourcecontext,
                'block/itn_course_progress:view',
                'nopermissions',
                ''
            );
        }

        $student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
        if (!empty($student->suspended) ||
                !is_enrolled($sourcecontext, $studentid, self::TRACKED_USER_CAPABILITY, true)) {
            throw new \invalid_parameter_exception('The selected user is not an active learner in this course.');
        }

        $userpicture = new user_picture($student);
        $userpicture->size = 1;

        $contactcount = (int)$DB->count_records_select(
            'message_contacts',
            'userid = :contactuserid OR contactid = :contactid',
            ['contactuserid' => $studentid, 'contactid' => $studentid]
        );
        $discussioncount = (int)$DB->count_records('forum_posts', ['userid' => $studentid]);
        $blogcount = (int)$DB->count_records('post', ['userid' => $studentid, 'module' => 'blog']);
        $badgecount = (int)$DB->count_records('badge_issued', ['userid' => $studentid]);

        if ((int)$USER->id === $studentid) {
            $contactstate = 'unavailable';
        } else if (\core_message\api::is_contact((int)$USER->id, $studentid)) {
            $contactstate = 'contact';
        } else if (!empty(\core_message\api::get_contact_requests_between_users((int)$USER->id, $studentid))) {
            $contactstate = 'pending';
        } else if (\core_message\api::can_create_contact((int)$USER->id, $studentid)) {
            $contactstate = 'available';
        } else {
            $contactstate = 'unavailable';
        }

        $courses = [];
        $summary = [
            'completedcourses' => 0,
            'inprogresscourses' => 0,
            'notstartedcourses' => 0,
            'notrackingcourses' => 0,
            'completedactivities' => 0,
            'totalactivities' => 0,
            'visits' => 0,
        ];
        $courserenderer = $PAGE->get_renderer('core');
        $enrolledcourses = enrol_get_all_users_courses(
            $studentid,
            true,
            'enablecompletion,enddate,visible,category',
            'sortorder ASC'
        );
        foreach ($enrolledcourses as $enrolledcourse) {
            $courseid = (int)$enrolledcourse->id;
            if (!self::can_view_course($courseid)) {
                continue;
            }

            $coursecontext = context_course::instance($courseid);
            if (!is_enrolled($coursecontext, $studentid, self::TRACKED_USER_CAPABILITY, true)) {
                continue;
            }
            if (empty($enrolledcourse->visible) &&
                    !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
                continue;
            }

            $totalactivities = (int)$DB->count_records_select(
                'course_modules',
                'course = :courseid AND completion <> :nottracked AND deletioninprogress = 0',
                [
                    'courseid' => $courseid,
                    'nottracked' => COMPLETION_TRACKING_NONE,
                ]
            );
            $completedactivities = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT cm.id)
                   FROM {course_modules} cm
                   JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                  WHERE cm.course = :courseid
                    AND cm.completion <> :nottracked
                    AND cm.deletioninprogress = 0
                    AND cmc.userid = :userid
                    AND cmc.completionstate > :incomplete",
                [
                    'courseid' => $courseid,
                    'nottracked' => COMPLETION_TRACKING_NONE,
                    'userid' => $studentid,
                    'incomplete' => COMPLETION_INCOMPLETE,
                ]
            );

            $completion = $DB->get_record(
                'course_completions',
                ['course' => $courseid, 'userid' => $studentid],
                'id, timecompleted'
            );
            $lastaccess = (int)$DB->get_field(
                'user_lastaccess',
                'timeaccess',
                ['courseid' => $courseid, 'userid' => $studentid]
            );

            $progressvalue = 0;
            if (!empty($enrolledcourse->enablecompletion)) {
                $progressvalue = (int)round(progress::get_course_progress_percentage(
                    $enrolledcourse,
                    $studentid
                ) ?? 0);
            }

            if (!empty($completion->timecompleted)) {
                $statuskey = 'completed';
                $status = get_string('studentprogress_status_completed', 'block_itn_course_progress');
            } else if (empty($enrolledcourse->enablecompletion) || $totalactivities === 0) {
                $statuskey = 'notracking';
                $status = get_string('status_notracking', 'block_itn_course_progress');
            } else if ($completedactivities > 0 || $lastaccess > 0) {
                $statuskey = 'inprogress';
                $status = get_string('studentprogress_status_inprogress', 'block_itn_course_progress');
            } else {
                $statuskey = 'notstarted';
                $status = get_string('studentprogress_status_notstarted', 'block_itn_course_progress');
            }

            $categoryname = $DB->get_field('course_categories', 'name', ['id' => $enrolledcourse->category]);
            $courseimageurl = \core_course\external\course_summary_exporter::get_course_image($enrolledcourse);
            if (!$courseimageurl) {
                $courseimageurl = $courserenderer->get_generated_image_for_id($courseid);
            }
            $teacherdetails = self::get_course_teacher_details($coursecontext);
            $visits = (int)$DB->count_records('logstore_standard_log', [
                'courseid' => $courseid,
                'userid' => $studentid,
                'eventname' => self::COURSE_VIEWED_EVENT,
                'anonymous' => 0,
            ]);

            $summary[$statuskey . 'courses']++;
            $summary['completedactivities'] += $completedactivities;
            $summary['totalactivities'] += $totalactivities;
            $summary['visits'] += $visits;

            $courses[] = [
                'id' => $courseid,
                'fullname' => html_to_text(
                    format_string($enrolledcourse->fullname, true, ['context' => $coursecontext]),
                    0,
                    false
                ),
                'category' => $categoryname ? html_to_text(format_string($categoryname), 0, false) : '-',
                'courseimageurl' => $courseimageurl,
                'teachers' => $teacherdetails['names'],
                'teachername' => $teacherdetails['primaryname'],
                'teacheravatarurl' => $teacherdetails['primaryavatarurl'],
                'additionalteachers' => $teacherdetails['additionalcount'],
                'hasteacher' => $teacherdetails['hasteacher'],
                'completedactivities' => $completedactivities,
                'totalactivities' => $totalactivities,
                'activitysummary' => get_string('studentprogress_activitysummary', 'block_itn_course_progress', (object)[
                    'completed' => $completedactivities,
                    'total' => $totalactivities,
                ]),
                'progress' => min(100, max(0, $progressvalue)),
                'hascompletion' => !empty($enrolledcourse->enablecompletion),
                'status' => $status,
                'statuskey' => $statuskey,
                'timecompleted' => !empty($completion->timecompleted) ?
                    userdate($completion->timecompleted, '%d %b, %Y') : '-',
                'lastaccess' => $lastaccess > 0 ? userdate($lastaccess, '%d %b, %Y') : '-',
                'visits' => $visits,
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            ];
        }

        return [
            'student' => [
                'id' => $studentid,
                'name' => fullname($student),
                'email' => $student->email,
                'idnumber' => !empty($student->idnumber) ? $student->idnumber : '-',
                'avatarurl' => $userpicture->get_url($PAGE)->out(false),
                'lastaccess' => !empty($student->lastaccess) ?
                    userdate($student->lastaccess, '%d %b, %Y') : '-',
                'profileurl' => (new moodle_url('/user/view.php', [
                    'id' => $studentid,
                    'course' => $sourcecourseid,
                ]))->out(false),
                'messageurl' => (new moodle_url('/message/index.php', ['id' => $studentid]))->out(false),
                'contacts' => $contactcount,
                'discussions' => $discussioncount,
                'blogentries' => $blogcount,
                'badges' => $badgecount,
                'viewerid' => (int)$USER->id,
                'contactstate' => $contactstate,
                'firstaccess' => !empty($student->firstaccess) ?
                    userdate($student->firstaccess, '%d %b, %Y') : '-',
                'lastlogin' => !empty($student->lastlogin) ?
                    userdate($student->lastlogin, '%d %b, %Y') : '-',
                'institution' => !empty($student->institution) ?
                    html_to_text(format_string($student->institution), 0, false) : '-',
                'department' => !empty($student->department) ?
                    html_to_text(format_string($student->department), 0, false) : '-',
                'city' => !empty($student->city) ?
                    html_to_text(format_string($student->city), 0, false) : '-',
                'country' => !empty($student->country) ?
                    get_string($student->country, 'core_countries') : '-',
            ],
            'totalcourses' => count($courses),
            'summary' => $summary,
            'courses' => $courses,
        ];
    }

    /**
     * Get configured course contact names.
     *
     * @param context_course $context Course context.
     * @return string Comma-separated contact names or a dash.
     */
    private static function get_course_teacher_details(context_course $context): array {
        global $CFG, $PAGE;

        $roleids = array_filter(array_map('intval', explode(',', (string)$CFG->coursecontact)));
        $contacts = [];
        foreach ($roleids as $roleid) {
            $roleusers = get_role_users(
                $roleid,
                $context,
                false,
                '',
                null,
                true
            );
            foreach ($roleusers as $roleuser) {
                $contacts[(int)$roleuser->id] = $roleuser;
            }
        }

        if (empty($contacts)) {
            return [
                'names' => '-',
                'primaryname' => '-',
                'primaryavatarurl' => '',
                'additionalcount' => 0,
                'hasteacher' => false,
            ];
        }

        $names = array_map(fn($contact) => fullname($contact), $contacts);
        $primarycontact = reset($contacts);
        if (!property_exists($primarycontact, 'imagealt')) {
            $primarycontact->imagealt = null;
        }
        $primarypicture = new user_picture($primarycontact);
        $primarypicture->size = 0;

        return [
            'names' => implode(', ', $names),
            'primaryname' => fullname($primarycontact),
            'primaryavatarurl' => $primarypicture->get_url($PAGE)->out(false),
            'additionalcount' => max(0, count($contacts) - 1),
            'hasteacher' => true,
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

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $groups = self::get_accessible_groups($course);
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

        $groups = self::get_accessible_groups($course);
        if (empty($groups)) {
            return [];
        }

        $summary = [];
        $coursecontext = context_course::instance($courseid);

        foreach ($groups as $g) {
            $groupid = (int)$g->id;
            [$trackeduserssql, $trackedparams] = get_enrolled_sql(
                $coursecontext,
                self::TRACKED_USER_CAPABILITY,
                $groupid,
                true
            );

            $members = (int)$DB->count_records_sql(
                "SELECT COUNT(tracked.id) FROM ($trackeduserssql) tracked",
                $trackedparams
            );

            $activeparams = array_merge($trackedparams, [
                'batchcourseid' => $courseid,
                'batchthreshold' => time() - self::get_active_window(),
            ]);
            $active = (int)$DB->count_records_sql(
                "SELECT COUNT(tracked.id)
                   FROM ($trackeduserssql) tracked
                   JOIN {user_lastaccess} ula ON ula.userid = tracked.id
                  WHERE ula.courseid = :batchcourseid
                    AND ula.timeaccess >= :batchthreshold",
                $activeparams
            );

            $completedparams = array_merge($trackedparams, ['batchcompletedcourseid' => $courseid]);
            $completed = (int)$DB->count_records_sql(
                "SELECT COUNT(tracked.id)
                   FROM ($trackeduserssql) tracked
                   JOIN {course_completions} cc ON cc.userid = tracked.id
                  WHERE cc.course = :batchcompletedcourseid
                    AND cc.timecompleted IS NOT NULL
                    AND cc.timecompleted > 0",
                $completedparams
            );

            // 4. Batch average progress.
            $avgprogress = 0;
            if (!empty($course->enablecompletion) && $members > 0) {
                $userids = $DB->get_fieldset_sql(
                    "SELECT tracked.id FROM ($trackeduserssql) tracked",
                    $trackedparams
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
