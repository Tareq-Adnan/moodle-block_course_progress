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
 * AJAX repository service for Course Progress block.
 *
 * @module     block_itn_course_progress/repository
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {
    'use strict';

    return {
        /**
         * Fetch paginated courses with metrics.
         *
         * @param {Object} args Query arguments
         * @return {Promise}
         */
        getCourses: function(args) {
            var request = {
                methodname: 'block_itn_course_progress_get_courses',
                args: {
                    search: args.search || '',
                    sort: args.sort || 'coursename',
                    sortdir: args.sortdir || 'ASC',
                    page: args.page || 0,
                    perpage: args.perpage || 10,
                    loadprogress: args.loadprogress !== undefined ? args.loadprogress : true
                }
            };
            return Ajax.call([request])[0];
        },

        /**
         * Fetch paginated students for a course.
         *
         * @param {Object} args Query arguments
         * @return {Promise}
         */
        getStudents: function(args) {
            var request = {
                methodname: 'block_itn_course_progress_get_students',
                args: {
                    courseid: args.courseid,
                    groupid: args.groupid || 0,
                    search: args.search || '',
                    sort: args.sort || 'name',
                    sortdir: args.sortdir || 'ASC',
                    page: args.page || 0,
                    perpage: args.perpage || 10
                }
            };
            return Ajax.call([request])[0];
        },

        /**
         * Fetch one student's progress across visible enrolled courses.
         *
         * @param {Number} studentid Student user ID.
         * @param {Number} sourcecourseid Course from which the student was selected.
         * @return {Promise}
         */
        getStudentProgress: function(studentid, sourcecourseid) {
            const request = {
                methodname: 'block_itn_course_progress_get_student_progress',
                args: {
                    studentid: studentid,
                    sourcecourseid: sourcecourseid
                }
            };
            return Ajax.call([request])[0];
        },

        /**
         * Fetch one student's tracked activity details for a course.
         *
         * @param {Number} studentid Student user ID.
         * @param {Number} courseid Course containing the activities.
         * @param {Number} sourcecourseid Course from which the student was selected.
         * @return {Promise}
         */
        getStudentActivities: function(studentid, courseid, sourcecourseid) {
            const request = {
                methodname: 'block_itn_course_progress_get_student_activities',
                args: {
                    studentid: studentid,
                    courseid: courseid,
                    sourcecourseid: sourcecourseid
                }
            };
            return Ajax.call([request])[0];
        },

        /**
         * Send a Moodle contact request to the selected student.
         *
         * @param {Number} userid Current report viewer user ID.
         * @param {Number} requesteduserid Student user ID.
         * @return {Promise}
         */
        addContact: function(userid, requesteduserid) {
            const request = {
                methodname: 'core_message_create_contact_request',
                args: {
                    userid: userid,
                    requesteduserid: requesteduserid
                }
            };
            return Ajax.call([request])[0];
        },

        /**
         * Fetch course groups list.
         *
         * @param {Number} courseid
         * @return {Promise}
         */
        getGroups: function(courseid) {
            var request = {
                methodname: 'block_itn_course_progress_get_groups',
                args: {
                    courseid: courseid
                }
            };
            return Ajax.call([request])[0];
        },

        /**
         * Fetch comparative batch performance summary.
         *
         * @param {Number} courseid
         * @return {Promise}
         */
        getBatchSummary: function(courseid) {
            var request = {
                methodname: 'block_itn_course_progress_get_batch_summary',
                args: {
                    courseid: courseid
                }
            };
            return Ajax.call([request])[0];
        }
    };
});
