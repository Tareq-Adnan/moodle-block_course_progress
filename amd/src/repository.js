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
 * @copyright  2026 ITN-BUET
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
