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
 * Client-side controller for Course Progress block.
 *
 * @module     block_itn_course_progress/main
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['block_itn_course_progress/repository'], function(Repository) {
    'use strict';

    /**
     * Component constructor.
     *
     * @param {Object} config Block configuration object.
     */
    var CourseProgress = function(config) {
        this.root = document.getElementById(config.elementId);
        if (!this.root) {
            return;
        }

        this.config = config;
        this.activeView = 'courses';
        this.selectedCourse = null;
        this.selectedStudent = null;
        this.selectedGroupId = 0;
        this.labels = config.labels || {};

        // View 1 (Courses) State.
        this.courseSearch = '';
        this.courseSort = 'coursename';
        this.courseSortDir = 'ASC';
        this.coursePage = 0;
        this.coursePerPage = config.defaultPerPage || 10;
        this.alwaysLoad = config.defaultLoad !== undefined ? config.defaultLoad : true;

        // View 2 (Students) State.
        this.studentSearch = '';
        this.studentSort = 'name';
        this.studentSortDir = 'ASC';
        this.studentPage = 0;
        this.studentPerPage = config.defaultPerPage || 10;

        this.batchSummaryOpen = false;
        this.isFullscreen = false;
        this.progressExpandedView = false;

        // Column Configs.
        this.allCourseCols = config.courseColumns || [];
        this.allStudentCols = config.studentColumns || [];

        // Save default column configurations for reset.
        this.defaultCourseCols = (config.courseColumns || []).map(function(col) {
            return {key: col.key, enabled: col.enabled};
        });
        this.defaultStudentCols = (config.studentColumns || []).map(function(col) {
            return {key: col.key, enabled: col.enabled};
        });

        // Load local preferences if available.
        this.initColumnsState();

        this.initDOMElements();
        this.registerEventListeners();

        // Initial Data Load.
        this.loadCourses();
    };

    /**
     * Initialize saved column configurations.
     */
    CourseProgress.prototype.initColumnsState = function() {
        var savedCourseCols = localStorage.getItem('itn_cp_cols_courses');
        if (savedCourseCols) {
            try {
                var parsedCourse = JSON.parse(savedCourseCols);
                this.allCourseCols.forEach(function(col) {
                    col.enabled = parsedCourse.indexOf(col.key) !== -1;
                });
            } catch (e) {
                // Ignore parsing errors for stored preferences.
            }
        }

        var savedStudentCols = localStorage.getItem('itn_cp_cols_students');
        if (savedStudentCols) {
            try {
                var parsedStudent = JSON.parse(savedStudentCols);
                this.allStudentCols.forEach(function(col) {
                    col.enabled = parsedStudent.indexOf(col.key) !== -1;
                });
            } catch (e) {
                // Ignore parsing errors for stored preferences.
            }
        }
    };

    /**
     * Cache DOM references.
     */
    CourseProgress.prototype.initDOMElements = function() {
        this.dom = {
            viewCourses: this.root.querySelector('[data-region="view-courses"]'),
            viewStudents: this.root.querySelector('[data-region="view-students"]'),
            viewStudentProgress: this.root.querySelector('[data-region="view-student-progress"]'),
            alertContainer: this.root.querySelector('[data-region="alert-container"]'),
            alertText: this.root.querySelector('.itn-alert-text'),

            // Courses DOM.
            coursesSearchInput: this.root.querySelector('[data-action="search-courses"]'),
            alwaysLoadCheckbox: this.root.querySelector('[data-action="toggle-always-load"]'),
            loadProgressBtn: this.root.querySelector('[data-action="load-progress"]'),
            coursesPerPageSelect: this.root.querySelector('[data-action="change-perpage"]'),
            coursesThead: this.root.querySelector('[data-region="courses-thead"]'),
            coursesTbody: this.root.querySelector('[data-region="courses-tbody"]'),
            coursesCountInfo: this.root.querySelector('[data-region="courses-count-info"]'),
            coursesPages: this.root.querySelector('[data-region="courses-pages"]'),

            // Students DOM.
            selectedCourseTitle: this.root.querySelector('[data-region="selected-course-title"]'),
            groupFilterSelect: this.root.querySelector('[data-action="filter-group"]'),
            studentSearchInput: this.root.querySelector('[data-action="search-students"]'),
            studentPerPageSelect: this.root.querySelector('[data-action="change-student-perpage"]'),
            studentsThead: this.root.querySelector('[data-region="students-thead"]'),
            studentsTbody: this.root.querySelector('[data-region="students-tbody"]'),
            studentsCountInfo: this.root.querySelector('[data-region="students-count-info"]'),
            studentsPages: this.root.querySelector('[data-region="students-pages"]'),
            batchSummaryArea: this.root.querySelector('[data-region="batch-summary-area"]'),

            // Individual Student Progress DOM.
            studentProgressAvatar: this.root.querySelector('[data-region="student-progress-avatar"]'),
            studentProgressName: this.root.querySelector('[data-region="student-progress-name"]'),
            studentProgressLastAccess: this.root.querySelector('[data-region="student-progress-lastaccess"]'),
            studentProgressMessage: this.root.querySelector('[data-region="student-progress-message"]'),
            studentProgressLoading: this.root.querySelector('[data-region="student-progress-loading"]'),
            studentProgressCourses: this.root.querySelector('[data-region="student-progress-courses"]'),
            studentProfileTab: this.root.querySelector('[data-region="student-profile-tab"]'),
            studentProfileLink: this.root.querySelector('[data-region="student-profile-link"]'),
            studentDetailsTab: this.root.querySelector('[data-region="student-details-tab"]'),
            studentContactButton: this.root.querySelector('[data-action="add-student-contact"]'),
            studentContactLabel: this.root.querySelector('[data-region="student-contact-label"]'),
            studentStatContacts: this.root.querySelector('[data-region="student-stat-contacts"]'),
            studentStatDiscussions: this.root.querySelector('[data-region="student-stat-discussions"]'),
            studentStatBlogEntries: this.root.querySelector('[data-region="student-stat-blogentries"]'),
            studentStatBadges: this.root.querySelector('[data-region="student-stat-badges"]'),
            studentTabs: this.root.querySelectorAll('[data-action="switch-student-tab"]'),
            studentTabPanels: this.root.querySelectorAll('[data-region="student-progress-panel"]'),
            tabIndicator: this.root.querySelector('[data-region="tab-indicator"]'),
            profileTabsNav: this.root.querySelector('.itn-profile-tabs'),
            profileIdNumber: this.root.querySelector('[data-region="profile-idnumber"]'),
            profileEmail: this.root.querySelector('[data-region="profile-email"]'),
            profileDepartment: this.root.querySelector('[data-region="profile-department"]'),
            profileInstitution: this.root.querySelector('[data-region="profile-institution"]'),
            profileCity: this.root.querySelector('[data-region="profile-city"]'),
            profileCountry: this.root.querySelector('[data-region="profile-country"]'),
            profileFirstAccess: this.root.querySelector('[data-region="profile-firstaccess"]'),
            profileLastLogin: this.root.querySelector('[data-region="profile-lastlogin"]'),
            summaryTotalCourses: this.root.querySelector('[data-region="summary-totalcourses"]'),
            summaryCompletedCourses: this.root.querySelector('[data-region="summary-completedcourses"]'),
            summaryInProgressCourses: this.root.querySelector('[data-region="summary-inprogresscourses"]'),
            summaryNotStartedCourses: this.root.querySelector('[data-region="summary-notstartedcourses"]'),
            summaryNoTrackingCourses: this.root.querySelector('[data-region="summary-notrackingcourses"]'),
            summaryActivities: this.root.querySelector('[data-region="summary-activities"]'),
            summaryVisits: this.root.querySelector('[data-region="summary-visits"]'),

            // Column Picker.
            columnPickerMenu: this.root.querySelector('[data-region="column-picker-menu"]'),
            columnOptionsList: this.root.querySelector('[data-region="column-options-list"]')
        };
    };

    /**
     * Decode HTML entities in text (e.g. &amp; -> &) safely.
     *
     * @param {string} text Text to decode.
     * @returns {string} Decoded plain text.
     */
    CourseProgress.prototype.decodeHtml = function(text) {
        if (!text || typeof text !== 'string') {
            return text || '';
        }
        if (text.indexOf('&') === -1) {
            return text;
        }
        var parser = new DOMParser();
        var doc = parser.parseFromString(text, 'text/html');
        return doc.body.textContent || text;
    };

    /**
     * Escape plain text before inserting it into generated markup.
     *
     * @param {String} text Text to escape.
     * @return {String} HTML-safe text.
     */
    CourseProgress.prototype.escapeHtml = function(text) {
        return this.decodeHtml(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    /**
     * Bind UI event handlers.
     */
    CourseProgress.prototype.registerEventListeners = function() {
        var self = this;
        var searchDebounceTimer = null;

        // 1. Search Courses (Debounced 250ms).
        if (this.dom.coursesSearchInput) {
            this.dom.coursesSearchInput.addEventListener('keyup', function(e) {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(function() {
                    self.courseSearch = e.target.value.trim();
                    self.coursePage = 0;
                    self.loadCourses();
                }, 250);
            });
        }

        // 2. Always Load Progress Checkbox.
        if (this.dom.alwaysLoadCheckbox) {
            this.dom.alwaysLoadCheckbox.addEventListener('change', function(e) {
                self.alwaysLoad = e.target.checked;
                if (self.alwaysLoad) {
                    self.loadCourses(true);
                }
            });
        }

        // 3. Load Progress Button Click.
        if (this.dom.loadProgressBtn) {
            this.dom.loadProgressBtn.addEventListener('click', function() {
                self.loadCourses(true);
            });
        }

        // 4. Per Page Change (Courses).
        if (this.dom.coursesPerPageSelect) {
            this.dom.coursesPerPageSelect.addEventListener('change', function(e) {
                self.coursePerPage = parseInt(e.target.value, 10);
                self.coursePage = 0;
                self.loadCourses();
            });
        }

        // 5. Search Students (Debounced 250ms).
        if (this.dom.studentSearchInput) {
            this.dom.studentSearchInput.addEventListener('keyup', function(e) {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(function() {
                    self.studentSearch = e.target.value.trim();
                    self.studentPage = 0;
                    self.loadStudents();
                }, 250);
            });
        }

        // 6. Group Filter Dropdown (Requirement 4).
        if (this.dom.groupFilterSelect) {
            this.dom.groupFilterSelect.addEventListener('change', function(e) {
                self.selectedGroupId = parseInt(e.target.value, 10);
                self.studentPage = 0;
                self.loadStudents();
            });
        }

        // 7. Per Page Change (Students).
        if (this.dom.studentPerPageSelect) {
            this.dom.studentPerPageSelect.addEventListener('change', function(e) {
                self.studentPerPage = parseInt(e.target.value, 10);
                self.studentPage = 0;
                self.loadStudents();
            });
        }

        // 8. Close Students Drill-Down View (Back & [x] Buttons).
        var closeStudentsBtns = this.root.querySelectorAll('[data-action="close-students-view"]');
        closeStudentsBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                self.switchToCoursesView();
            });
        });

        var closeStudentProgressBtns = this.root.querySelectorAll('[data-action="close-student-progress"]');
        closeStudentProgressBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                self.switchToStudentsView();
            });
        });

        if (this.dom.studentContactButton) {
            this.dom.studentContactButton.addEventListener('click', () => this.addStudentContact());
        }

        this.dom.studentTabs.forEach((tab) => {
            tab.addEventListener('click', () => this.switchStudentTab(tab.dataset.tab));
        });

        window.addEventListener('resize', () => {
            if (self.activeView === 'studentprogress') {
                self.updateTabIndicator(null, true);
            }
        });

        // 9. Fullscreen / Expand Toggle ([+] Button).
        var fullscreenBtn = this.root.querySelector('[data-action="toggle-fullscreen"]');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', function() {
                self.isFullscreen = !self.isFullscreen;
                self.root.classList.toggle('is-fullscreen', self.isFullscreen);
                var icon = fullscreenBtn.querySelector('i');
                if (icon) {
                    icon.className = self.isFullscreen ? 'fa fa-compress' : 'fa fa-plus';
                }
            });
        }

        // 10. Toggle Batch Summary.
        var batchSummaryBtn = this.root.querySelector('[data-action="toggle-batch-summary"]');
        if (batchSummaryBtn) {
            batchSummaryBtn.addEventListener('click', function() {
                self.toggleBatchSummary();
            });
        }

        // 11. Toggle Column Picker Dropdown.
        var columnBtns = this.root.querySelectorAll('[data-action="toggle-column-picker"]');
        columnBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                self.toggleColumnPicker(btn);
            });
        });

        // Close column picker on outside click.
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.itn-column-picker-wrapper')) {
                self.root.querySelectorAll('[data-region="column-picker-menu"]').forEach(function(m) {
                    m.style.display = 'none';
                });
            }
        });

        // Close column picker on close button click.
        var closeColPickerBtns = this.root.querySelectorAll('[data-action="close-column-picker"]');
        closeColPickerBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var menu = btn.closest('[data-region="column-picker-menu"]');
                if (menu) {
                    menu.style.display = 'none';
                }
            });
        });

        // 12. Reset Columns to Defaults.
        var resetColBtns = this.root.querySelectorAll('[data-action="reset-columns"]');
        resetColBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.resetColumns();
            });
        });

        // 12. Retry Fetch on Error.
        var retryBtn = this.root.querySelector('[data-action="retry-fetch"]');
        if (retryBtn) {
            retryBtn.addEventListener('click', function() {
                self.dom.alertContainer.classList.add('d-none');
                if (self.activeView === 'courses') {
                    self.loadCourses();
                } else if (self.activeView === 'studentprogress') {
                    self.loadStudentProgress();
                } else {
                    self.loadStudents();
                }
            });
        }
    };

    /**
     * Load courses dataset via AJAX (Requirement 3).
     *
     * @param {Boolean} forceLoadProgress Force progress calculation.
     */
    CourseProgress.prototype.loadCourses = function(forceLoadProgress) {
        var self = this;
        var shouldLoadProg = forceLoadProgress !== undefined ? forceLoadProgress : this.alwaysLoad;

        var visibleColsCount = this.allCourseCols.filter(function(col) {
            return col.enabled;
        }).length || 5;

        this.renderSkeleton(this.dom.coursesTbody, visibleColsCount);
        this.dom.alertContainer.classList.add('d-none');

        Repository.getCourses({
            search: this.courseSearch,
            sort: this.courseSort,
            sortdir: this.courseSortDir,
            page: this.coursePage,
            perpage: this.coursePerPage,
            loadprogress: shouldLoadProg
        }).then(function(response) {
            self.renderCoursesTable(response);
            self.renderPagination(self.dom.coursesPages, self.dom.coursesCountInfo, response, function(newPage) {
                self.coursePage = newPage;
                self.loadCourses();
            });
            return response;
        }).catch(function(error) {
            self.showError(error);
        });
    };

    /**
     * Render Course Overview Table.
     *
     * @param {Object} data API response.
     */
    CourseProgress.prototype.renderCoursesTable = function(data) {
        var self = this;
        var thead = this.dom.coursesThead;
        var tbody = this.dom.coursesTbody;

        // Render Table Headers.
        thead.innerHTML = '';
        var visibleCols = this.allCourseCols.filter(function(col) {
            return col.enabled;
        });

        visibleCols.forEach(function(col) {
            var th = document.createElement('th');
            th.className = 'text-nowrap user-select-none';
            th.dataset.column = col.key;

            var sortIcon = '';
            if (col.sortable) {
                th.style.cursor = 'pointer';
                sortIcon = '<i class="fa fa-sort opacity-25"></i>';
            }
            if (col.sortable && self.courseSort === col.key) {
                sortIcon = self.courseSortDir === 'ASC' ?
                    '<i class="fa fa-sort-asc"></i>' :
                    '<i class="fa fa-sort-desc"></i>';
            }

            th.innerHTML = '<div class="d-flex align-items-center justify-content-between w-100">' +
                '<span class="itn-th-label">' + col.label + '</span>' +
                '<span class="itn-sort-icon ms-2">' + sortIcon + '</span>' +
                '</div>';

            if (col.sortable) {
                th.addEventListener('click', function() {
                    if (self.courseSort === col.key) {
                        self.courseSortDir = self.courseSortDir === 'ASC' ? 'DESC' : 'ASC';
                    } else {
                        self.courseSort = col.key;
                        self.courseSortDir = 'ASC';
                    }
                    self.loadCourses();
                });
            }
            thead.appendChild(th);
        });

        // Render Table Rows.
        tbody.innerHTML = '';
        if (!data.courses || data.courses.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyMsg = '<i class="fa fa-folder-open-o me-2"></i> ' + self.labels.nocoursestable;
            emptyRow.innerHTML = '<td colspan="' + visibleCols.length + '" class="text-center py-4 text-muted">' +
                emptyMsg + '</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        data.courses.forEach(function(course) {
            var tr = document.createElement('tr');

            visibleCols.forEach(function(col) {
                var td = document.createElement('td');
                switch (col.key) {
                    case 'index':
                        td.textContent = course.index;
                        break;
                    case 'coursename':
                        var link = document.createElement('a');
                        link.className = 'itn-course-name-link';
                        link.href = '#';
                        link.textContent = self.decodeHtml(course.coursename);
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            self.drillDownIntoCourse(course);
                        });
                        td.appendChild(link);
                        break;
                    case 'startdate':
                        td.textContent = course.startdate;
                        break;
                    case 'enrolled':
                        td.textContent = course.enrolled;
                        break;
                    case 'progress':
                        td.innerHTML = self.renderProgressDonut(course.progress);
                        break;
                    case 'active':
                        td.innerHTML = '<span class="text-success fw-semibold">' + course.active + '</span>';
                        break;
                    case 'completed':
                        td.innerHTML = '<span class="text-primary fw-semibold">' + course.completed + '</span>';
                        break;
                    case 'visits':
                        td.textContent = course.visits;
                        break;
                    case 'completionrate':
                        td.innerHTML = '<span class="badge rounded-pill text-white px-2 py-1" ' +
                            'style="background-color: #00b4d8; font-weight: 600; font-size: 0.75rem;">' +
                            course.completionrate + '%' +
                        '</span>';
                        break;
                    case 'notstarted':
                        td.textContent = course.notstarted;
                        break;
                    case 'inprogress':
                        td.textContent = course.inprogress;
                        break;
                    case 'enddate':
                        td.textContent = course.enddate;
                        break;
                    case 'category':
                        td.textContent = self.decodeHtml(course.category);
                        break;
                    case 'groupscount':
                        td.textContent = course.groupscount;
                        break;
                    default:
                        td.textContent = '-';
                        break;
                }
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });
    };

    /**
     * Drill down into a specific course (View 2 - Requirements 4 & 5).
     *
     * @param {Object} course Course object.
     */
    CourseProgress.prototype.drillDownIntoCourse = function(course) {
        this.selectedCourse = course;
        this.selectedGroupId = 0;
        this.studentPage = 0;
        this.studentSearch = '';
        if (this.dom.studentSearchInput) {
            this.dom.studentSearchInput.value = '';
        }

        this.dom.selectedCourseTitle.textContent = this.decodeHtml(course.coursename);
        this.activeView = 'students';

        this.dom.viewCourses.classList.add('d-none');
        this.dom.viewStudentProgress.classList.add('d-none');
        this.dom.viewStudents.classList.remove('d-none');

        // Fetch groups for the course.
        this.loadGroups(course.id);

        // Fetch student list.
        this.loadStudents();
    };

    /**
     * Switch back to Course Overview View 1.
     */
    CourseProgress.prototype.switchToCoursesView = function() {
        this.activeView = 'courses';
        this.dom.viewStudents.classList.add('d-none');
        this.dom.viewStudentProgress.classList.add('d-none');
        this.dom.viewCourses.classList.remove('d-none');
        if (this.dom.batchSummaryArea) {
            this.dom.batchSummaryArea.classList.add('d-none');
            this.batchSummaryOpen = false;
        }
        if (this.isFullscreen) {
            this.isFullscreen = false;
            this.root.classList.remove('is-fullscreen');
            var fullscreenBtn = this.root.querySelector('[data-action="toggle-fullscreen"]');
            if (fullscreenBtn) {
                var icon = fullscreenBtn.querySelector('i');
                if (icon) {
                    icon.className = 'fa fa-plus';
                }
            }
        }
    };

    /**
     * Return from individual progress to the selected course's student list.
     */
    CourseProgress.prototype.switchToStudentsView = function() {
        this.activeView = 'students';
        this.dom.viewStudentProgress.classList.add('d-none');
        this.dom.viewCourses.classList.add('d-none');
        this.dom.viewStudents.classList.remove('d-none');
        this.root.classList.remove('is-student-progress');
        if (this.progressExpandedView) {
            this.isFullscreen = false;
            this.progressExpandedView = false;
            this.root.classList.remove('is-fullscreen');
        }
    };

    /**
     * Load groups into the dropdown selector (Requirement 4).
     *
     * @param {Number} courseid Course ID.
     */
    CourseProgress.prototype.loadGroups = function(courseid) {
        var self = this;
        var select = this.dom.groupFilterSelect;
        select.innerHTML = '';
        var loadingOption = document.createElement('option');
        loadingOption.value = '0';
        loadingOption.textContent = self.labels.loadingbatches;
        select.appendChild(loadingOption);

        Repository.getGroups(courseid).then(function(groups) {
            select.innerHTML = '';
            groups.forEach(function(g) {
                var opt = document.createElement('option');
                opt.value = g.id;
                opt.textContent = self.decodeHtml(g.name);
                select.appendChild(opt);
            });
            select.value = self.selectedGroupId;
            return groups;
        }).catch(function() {
            select.innerHTML = '';
            var fallbackOption = document.createElement('option');
            fallbackOption.value = '0';
            fallbackOption.textContent = self.labels.allgroups;
            select.appendChild(fallbackOption);
        });
    };

    /**
     * Load students for the selected course with group filter (Requirements 4 & 5).
     */
    CourseProgress.prototype.loadStudents = function() {
        var self = this;
        if (!this.selectedCourse) {
            return;
        }

        var visibleStudentColsCount = this.allStudentCols.filter(function(col) {
            return col.enabled;
        }).length || 4;

        this.renderSkeleton(this.dom.studentsTbody, visibleStudentColsCount);

        Repository.getStudents({
            courseid: this.selectedCourse.id,
            groupid: this.selectedGroupId,
            search: this.studentSearch,
            sort: this.studentSort,
            sortdir: this.studentSortDir,
            page: this.studentPage,
            perpage: this.studentPerPage
        }).then(function(response) {
            self.renderStudentsTable(response);
            self.renderPagination(self.dom.studentsPages, self.dom.studentsCountInfo, response, function(newPage) {
                self.studentPage = newPage;
                self.loadStudents();
            });
            return response;
        }).catch(function(error) {
            self.showError(error);
        });
    };

    /**
     * Render Student Drill-Down Table.
     *
     * @param {Object} data API response.
     */
    CourseProgress.prototype.renderStudentsTable = function(data) {
        var self = this;
        var thead = this.dom.studentsThead;
        var tbody = this.dom.studentsTbody;

        // Render Table Headers.
        thead.innerHTML = '';
        var visibleCols = this.allStudentCols.filter(function(col) {
            return col.enabled;
        });

        visibleCols.forEach(function(col) {
            var th = document.createElement('th');
            var sortClass = self.studentSort === col.key ? ' text-primary fw-bold' : ' text-secondary';
            th.className = 'text-nowrap user-select-none' + sortClass;
            th.dataset.column = col.key;

            var sortIcon = '';
            if (col.sortable) {
                th.style.cursor = 'pointer';
                sortIcon = '<i class="fa fa-sort opacity-25"></i>';
            }
            if (col.sortable && self.studentSort === col.key) {
                sortIcon = self.studentSortDir === 'ASC' ?
                    '<i class="fa fa-sort-asc"></i>' :
                    '<i class="fa fa-sort-desc"></i>';
            }

            th.innerHTML = '<div class="d-flex align-items-center justify-content-between w-100">' +
                '<span class="itn-th-label">' + col.label + '</span>' +
                '<span class="itn-sort-icon ms-2">' + sortIcon + '</span>' +
                '</div>';

            if (col.sortable) {
                th.addEventListener('click', function() {
                    if (self.studentSort === col.key) {
                        self.studentSortDir = self.studentSortDir === 'ASC' ? 'DESC' : 'ASC';
                    } else {
                        self.studentSort = col.key;
                        self.studentSortDir = 'ASC';
                    }
                    self.loadStudents();
                });
            }
            thead.appendChild(th);
        });

        // Render Table Rows.
        tbody.innerHTML = '';
        if (!data.students || data.students.length === 0) {
            var emptyRow = document.createElement('tr');
            var msg = self.selectedGroupId > 0 ? self.labels.nostudentsingroup : self.labels.nostudents;
            emptyRow.innerHTML = '<td colspan="' + visibleCols.length + '" class="text-center py-4 text-muted">' +
                '<i class="fa fa-user-times me-2"></i> ' + msg +
                '</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        data.students.forEach(function(stu) {
            var tr = document.createElement('tr');

            visibleCols.forEach(function(col) {
                var td = document.createElement('td');
                switch (col.key) {
                    case 'index':
                        td.textContent = stu.index;
                        break;
                    case 'name':
                        var nameWrapper = document.createElement('div');
                        nameWrapper.className = 'd-flex align-items-center justify-content-between';
                        var nameButton = document.createElement('button');
                        nameButton.type = 'button';
                        nameButton.className = 'btn btn-link p-0 fw-medium text-start itn-student-progress-link';
                        nameButton.textContent = self.decodeHtml(stu.name);
                        nameButton.addEventListener('click', function() {
                            self.drillDownIntoStudent(stu);
                        });
                        var messageLink = document.createElement('a');
                        messageLink.href = stu.messageurl;
                        messageLink.className = 'itn-student-email-link ms-2';
                        messageLink.title = self.labels.sendmessage;
                        messageLink.target = '_blank';
                        messageLink.rel = 'noopener noreferrer';
                        messageLink.innerHTML = '<i class="fa fa-envelope" aria-hidden="true"></i>';
                        nameWrapper.appendChild(nameButton);
                        nameWrapper.appendChild(messageLink);
                        td.appendChild(nameWrapper);
                        break;
                    case 'status':
                        td.innerHTML = '<span class="itn-status-text">' + stu.status + '</span>';
                        break;
                    case 'progress':
                        td.innerHTML = self.renderProgressDonut(stu.progress);
                        break;
                    case 'idnumber':
                        td.textContent = stu.idnumber;
                        break;
                    case 'email':
                        td.textContent = stu.email;
                        break;
                    case 'firstaccess':
                        td.textContent = stu.firstaccess;
                        break;
                    case 'groupname':
                        td.textContent = self.decodeHtml(stu.groupname);
                        break;
                    case 'timecompleted':
                        td.textContent = stu.timecompleted;
                        break;
                    default:
                        td.textContent = '-';
                        break;
                }
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });
    };

    /**
     * Open View 3 for an individual learner.
     *
     * @param {Object} student Student row data.
     */
    CourseProgress.prototype.drillDownIntoStudent = function(student) {
        this.selectedStudent = student;
        this.activeView = 'studentprogress';
        if (!this.isFullscreen) {
            this.isFullscreen = true;
            this.progressExpandedView = true;
            this.root.classList.add('is-fullscreen');
        }
        this.root.classList.add('is-student-progress');
        this.dom.viewCourses.classList.add('d-none');
        this.dom.viewStudents.classList.add('d-none');
        this.dom.viewStudentProgress.classList.remove('d-none');
        this.switchStudentTab('courses');
        window.requestAnimationFrame(() => {
            this.updateTabIndicator(null, true);
        });
        this.loadStudentProgress();
    };

    /**
     * Load individual progress across visible enrolled courses.
     */
    CourseProgress.prototype.loadStudentProgress = function() {
        if (!this.selectedStudent || !this.selectedCourse) {
            return;
        }

        this.dom.alertContainer.classList.add('d-none');
        this.dom.studentProgressCourses.innerHTML = '';
        this.dom.studentProgressLoading.classList.remove('d-none');

        Repository.getStudentProgress(this.selectedStudent.id, this.selectedCourse.id).then((response) => {
            this.dom.studentProgressLoading.classList.add('d-none');
            this.renderStudentProgress(response);
            return response;
        }).catch((error) => {
            this.dom.studentProgressLoading.classList.add('d-none');
            this.showError(error);
        });
    };

    /**
     * Render the individual learner header and course cards.
     *
     * @param {Object} data API response.
     */
    CourseProgress.prototype.renderStudentProgress = function(data) {
        const student = data.student;
        const labels = this.labels;
        this.currentStudentProgress = student;

        this.dom.studentProgressAvatar.src = student.avatarurl;
        this.dom.studentProgressAvatar.alt = student.name;
        this.dom.studentProgressName.textContent = this.decodeHtml(student.name);
        this.dom.studentProgressLastAccess.textContent = student.lastaccess;
        this.dom.studentProgressMessage.href = student.messageurl;
        this.dom.studentProfileLink.href = student.profileurl;
        this.dom.studentProfileTab.textContent = this.decodeHtml(student.name);
        window.requestAnimationFrame(() => {
            this.updateTabIndicator(null, true);
        });
        this.dom.studentStatContacts.textContent = student.contacts;
        this.dom.studentStatDiscussions.textContent = student.discussions;
        this.dom.studentStatBlogEntries.textContent = student.blogentries;
        this.dom.studentStatBadges.textContent = student.badges;
        this.dom.profileIdNumber.textContent = student.idnumber;
        this.dom.profileEmail.textContent = student.email;
        this.dom.profileDepartment.textContent = this.decodeHtml(student.department);
        this.dom.profileInstitution.textContent = this.decodeHtml(student.institution);
        this.dom.profileCity.textContent = this.decodeHtml(student.city);
        this.dom.profileCountry.textContent = this.decodeHtml(student.country);
        this.dom.profileFirstAccess.textContent = student.firstaccess;
        this.dom.profileLastLogin.textContent = student.lastlogin;
        this.dom.summaryTotalCourses.textContent = data.totalcourses;
        this.dom.summaryCompletedCourses.textContent = data.summary.completedcourses;
        this.dom.summaryInProgressCourses.textContent = data.summary.inprogresscourses;
        this.dom.summaryNotStartedCourses.textContent = data.summary.notstartedcourses;
        this.dom.summaryNoTrackingCourses.textContent = data.summary.notrackingcourses;
        this.dom.summaryActivities.textContent = `${data.summary.completedactivities} / ${data.summary.totalactivities}`;
        this.dom.summaryVisits.textContent = data.summary.visits;
        this.renderContactAction(student);
        this.dom.studentProgressCourses.innerHTML = '';

        if (!data.courses || data.courses.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'alert alert-secondary text-center';
            empty.textContent = labels.nocourses;
            this.dom.studentProgressCourses.appendChild(empty);
            return;
        }

        data.courses.forEach((course) => {
            const card = document.createElement('article');
            card.className = 'itn-student-course-card mb-4';

            const layout = document.createElement('div');
            layout.className = 'row g-0 align-items-stretch';

            const imageColumn = document.createElement('div');
            imageColumn.className = 'col-md-5 itn-course-image-wrap';
            const image = document.createElement('img');
            image.className = 'itn-course-image';
            image.src = course.courseimageurl;
            image.alt = course.fullname;
            image.loading = 'lazy';
            imageColumn.appendChild(image);

            const content = document.createElement('div');
            content.className = 'col-md-7 itn-course-card-content';
            const category = document.createElement('span');
            category.className = 'itn-course-category';
            category.textContent = this.decodeHtml(course.category);

            const title = document.createElement('a');
            title.className = 'itn-course-card-title';
            title.href = course.courseurl;
            title.target = '_blank';
            title.rel = 'noopener noreferrer';
            title.textContent = this.decodeHtml(course.fullname);

            const teacher = document.createElement('div');
            teacher.className = 'itn-course-teacher';
            if (course.hasteacher) {
                const teacherImage = document.createElement('img');
                teacherImage.src = course.teacheravatarurl;
                teacherImage.alt = '';
                const teacherName = document.createElement('span');
                teacherName.textContent = this.decodeHtml(course.teachername);
                teacher.appendChild(teacherImage);
                teacher.appendChild(teacherName);
                if (course.additionalteachers > 0) {
                    const moreTeachers = document.createElement('span');
                    moreTeachers.className = 'itn-more-teachers';
                    moreTeachers.textContent = `+${course.additionalteachers}`;
                    moreTeachers.title = course.teachers;
                    teacher.appendChild(moreTeachers);
                }
            }

            const completion = document.createElement('div');
            completion.className = 'itn-course-completion';
            const activitySummary = document.createElement('div');
            activitySummary.className = 'itn-activity-summary';
            activitySummary.textContent = course.activitysummary;
            const progress = Math.max(0, Math.min(100, parseInt(course.progress, 10) || 0));
            const progressTrack = document.createElement('div');
            progressTrack.className = 'itn-linear-progress';
            progressTrack.setAttribute('role', 'progressbar');
            progressTrack.setAttribute('aria-valuenow', progress);
            progressTrack.setAttribute('aria-valuemin', '0');
            progressTrack.setAttribute('aria-valuemax', '100');
            const progressBar = document.createElement('span');
            progressBar.style.width = `${progress}%`;
            progressTrack.appendChild(progressBar);
            const completionLabel = document.createElement('strong');
            completionLabel.className = 'itn-course-completed-label';
            completionLabel.textContent = `${progress}% ${labels.coursecompleted}`;
            completion.appendChild(activitySummary);
            completion.appendChild(progressTrack);
            completion.appendChild(completionLabel);

            const courseMeta = document.createElement('div');
            courseMeta.className = 'itn-course-meta';
            if (course.lastaccess !== '-') {
                const lastAccess = document.createElement('span');
                lastAccess.textContent = `${labels.lastcourseaccess}: ${course.lastaccess}`;
                courseMeta.appendChild(lastAccess);
            }
            if (course.timecompleted !== '-') {
                const completed = document.createElement('span');
                completed.textContent = `${labels.completeddate}: ${course.timecompleted}`;
                courseMeta.appendChild(completed);
            }

            const courseLink = document.createElement('a');
            courseLink.className = 'btn btn-outline-primary itn-view-course-btn';
            courseLink.href = course.courseurl;
            courseLink.target = '_blank';
            courseLink.rel = 'noopener noreferrer';
            courseLink.textContent = labels.viewcourse;

            const activityPanel = document.createElement('div');
            activityPanel.className = 'itn-activity-panel';
            activityPanel.id = `${this.root.id}-activities-${course.id}`;
            activityPanel.dataset.loaded = 'false';
            activityPanel.hidden = true;

            const activityButton = document.createElement('button');
            activityButton.type = 'button';
            activityButton.className = 'btn btn-outline-secondary itn-activity-details-btn';
            activityButton.textContent = labels.activitydetails;
            activityButton.setAttribute('aria-expanded', 'false');
            activityButton.setAttribute('aria-controls', activityPanel.id);
            activityButton.addEventListener('click', () => {
                this.toggleStudentActivities(course, activityPanel, activityButton);
            });

            const actions = document.createElement('div');
            actions.className = 'itn-course-actions';
            actions.appendChild(activityButton);
            actions.appendChild(courseLink);

            content.appendChild(category);
            content.appendChild(title);
            content.appendChild(teacher);
            content.appendChild(completion);
            content.appendChild(courseMeta);
            content.appendChild(actions);
            layout.appendChild(imageColumn);
            layout.appendChild(content);
            card.appendChild(layout);
            card.appendChild(activityPanel);
            this.dom.studentProgressCourses.appendChild(card);
        });
    };

    /**
     * Toggle and lazily load a student's activity detail panel.
     *
     * @param {Object} course Course data.
     * @param {HTMLElement} panel Activity panel.
     * @param {HTMLButtonElement} button Toggle button.
     */
    CourseProgress.prototype.toggleStudentActivities = function(course, panel, button) {
        const labels = this.labels;
        if (panel.dataset.loaded === 'true') {
            const opening = panel.hidden;
            if (opening) {
                this.expandActivityPanel(panel);
            } else {
                this.collapseActivityPanel(panel);
            }
            button.setAttribute('aria-expanded', opening ? 'true' : 'false');
            button.textContent = opening ? labels.hideactivities : labels.activitydetails;
            return;
        }

        panel.textContent = labels.loadingactivities;
        this.expandActivityPanel(panel);
        button.disabled = true;
        button.setAttribute('aria-expanded', 'true');

        Repository.getStudentActivities(
            this.currentStudentProgress.id,
            course.id,
            this.selectedCourse.id
        ).then((response) => {
            this.renderStudentActivities(panel, response);
            panel.dataset.loaded = 'true';
            button.disabled = false;
            button.textContent = labels.hideactivities;
            return response;
        }).catch((error) => {
            this.collapseActivityPanel(panel);
            button.disabled = false;
            button.setAttribute('aria-expanded', 'false');
            button.textContent = labels.activitydetails;
            this.showError(error);
        });
    };

    /**
     * Expand an activity panel using its measured content height.
     *
     * @param {HTMLElement} panel Activity panel.
     */
    CourseProgress.prototype.expandActivityPanel = function(panel) {
        const sequence = Number(panel.dataset.animationSequence || '0') + 1;
        panel.dataset.animation = 'expand';
        panel.dataset.animationSequence = String(sequence);
        panel.hidden = false;
        panel.classList.add('is-animating');
        panel.style.height = '0px';
        panel.style.opacity = '0';

        window.requestAnimationFrame(() => {
            panel.style.height = `${panel.scrollHeight}px`;
            panel.style.opacity = '1';
        });

        let handleTransition;
        const finish = () => {
            panel.removeEventListener('transitionend', handleTransition);
            if (panel.dataset.animation === 'expand' &&
                    Number(panel.dataset.animationSequence) === sequence) {
                panel.style.height = 'auto';
                panel.style.opacity = '';
                panel.classList.remove('is-animating');
                delete panel.dataset.animation;
            }
        };
        handleTransition = (event) => {
            if (event.target === panel && event.propertyName === 'height') {
                finish();
            }
        };
        panel.addEventListener('transitionend', handleTransition);
        window.setTimeout(finish, 350);
    };

    /**
     * Collapse an activity panel using its measured content height.
     *
     * @param {HTMLElement} panel Activity panel.
     */
    CourseProgress.prototype.collapseActivityPanel = function(panel) {
        const sequence = Number(panel.dataset.animationSequence || '0') + 1;
        panel.dataset.animation = 'collapse';
        panel.dataset.animationSequence = String(sequence);
        panel.style.height = `${panel.scrollHeight}px`;
        panel.style.opacity = '1';
        panel.classList.add('is-animating');

        // Force the measured start height to render before transitioning to zero.
        panel.getBoundingClientRect();

        window.requestAnimationFrame(() => {
            panel.style.height = '0px';
            panel.style.opacity = '0';
        });

        let handleTransition;
        const finish = () => {
            panel.removeEventListener('transitionend', handleTransition);
            if (panel.dataset.animation === 'collapse' &&
                    Number(panel.dataset.animationSequence) === sequence) {
                panel.hidden = true;
                panel.style.height = '';
                panel.style.opacity = '';
                panel.classList.remove('is-animating');
                delete panel.dataset.animation;
            }
        };
        handleTransition = (event) => {
            if (event.target === panel && event.propertyName === 'height') {
                finish();
            }
        };
        panel.addEventListener('transitionend', handleTransition);
        window.setTimeout(finish, 350);
    };

    /**
     * Render the tracked activity detail cards for one course.
     *
     * @param {HTMLElement} panel Activity panel.
     * @param {Object} data Activity API response.
     */
    CourseProgress.prototype.renderStudentActivities = function(panel, data) {
        const labels = this.labels;
        panel.innerHTML = '';

        const inner = document.createElement('div');
        inner.className = 'itn-activity-panel-inner';
        panel.appendChild(inner);

        if (!data.activities || data.activities.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'itn-activity-empty';
            empty.textContent = labels.noactivities;
            inner.appendChild(empty);
            return;
        }

        const header = document.createElement('div');
        header.className = 'itn-activity-panel-header';
        const heading = document.createElement('h5');
        heading.textContent = labels.trackedactivities;
        const count = document.createElement('span');
        count.className = 'itn-activity-count';
        count.textContent = String(data.totalactivities);
        header.appendChild(heading);
        header.appendChild(count);
        inner.appendChild(header);

        const list = document.createElement('div');
        list.className = 'itn-activity-list';
        data.activities.forEach((activity) => {
            const item = document.createElement('article');
            item.className = 'itn-activity-item';

            const activityWrap = document.createElement('div');
            activityWrap.className = 'itn-activity-name';
            const iconWrap = document.createElement('span');
            iconWrap.classList.add('activity-icon', 'activityiconcontainer', 'smaller', 'itn-activity-icon');
            iconWrap.classList.add(activity.purpose || 'other');
            if (activity.isbranded) {
                iconWrap.classList.add('isbranded');
            }
            const icon = document.createElement('img');
            icon.src = activity.iconurl;
            icon.alt = '';
            icon.classList.add('activityicon', 'icon');
            if (!activity.filtericon) {
                icon.classList.add('nofilter');
            }
            iconWrap.appendChild(icon);
            const name = activity.activityurl ? document.createElement('a') : document.createElement('span');
            name.textContent = this.decodeHtml(activity.name);
            if (activity.activityurl) {
                name.href = activity.activityurl;
                name.target = '_blank';
                name.rel = 'noopener noreferrer';
            }
            const type = document.createElement('small');
            type.textContent = this.decodeHtml(activity.typename);
            const nameGroup = document.createElement('span');
            nameGroup.appendChild(name);
            nameGroup.appendChild(type);
            activityWrap.appendChild(iconWrap);
            activityWrap.appendChild(nameGroup);

            const status = document.createElement('span');
            status.className = `itn-activity-status itn-activity-status-${activity.statuskey}`;
            status.textContent = activity.status;

            const meta = document.createElement('dl');
            meta.className = 'itn-activity-meta';
            [
                [labels.grade, activity.isgraded ? activity.grade : '-'],
                [labels.completeddate, activity.completiondate],
                [labels.lastinteraction, activity.lastinteraction],
                [labels.interactions, String(activity.interactions)]
            ].forEach(([label, value]) => {
                const group = document.createElement('div');
                const term = document.createElement('dt');
                const description = document.createElement('dd');
                term.textContent = label;
                description.textContent = value;
                group.appendChild(term);
                group.appendChild(description);
                meta.appendChild(group);
            });

            const action = document.createElement('div');
            action.className = 'itn-activity-action';
            if (activity.activityurl) {
                const link = document.createElement('a');
                link.className = 'btn btn-sm btn-outline-primary';
                link.href = activity.activityurl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = labels.openactivity;
                action.appendChild(link);
            }

            item.appendChild(activityWrap);
            item.appendChild(status);
            item.appendChild(meta);
            item.appendChild(action);
            list.appendChild(item);
        });

        inner.appendChild(list);
        if (panel.classList.contains('is-animating')) {
            panel.style.height = `${panel.scrollHeight}px`;
        }
    };

    /**
     * Switch between student profile, courses, and engagement detail panels.
     *
     * @param {String} tabName Tab identifier.
     */
    CourseProgress.prototype.switchStudentTab = function(tabName) {
        let activeTabBtn = null;
        this.dom.studentTabs.forEach((tab) => {
            const active = tab.dataset.tab === tabName;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                activeTabBtn = tab;
            }
        });
        this.dom.studentTabPanels.forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.tabPanel !== tabName);
        });
        if (activeTabBtn) {
            this.updateTabIndicator(activeTabBtn);
        }
    };

    /**
     * Smoothly reposition the sliding active tab underline indicator.
     *
     * @param {HTMLElement|null} activeTabBtn The active tab button element.
     * @param {Boolean} immediate Whether to skip transition for instant initial placement.
     */
    CourseProgress.prototype.updateTabIndicator = function(activeTabBtn, immediate) {
        const indicator = this.dom.tabIndicator;
        if (!indicator) {
            return;
        }

        if (!activeTabBtn) {
            activeTabBtn = this.root.querySelector('.itn-profile-tabs button.active');
        }

        if (!activeTabBtn || activeTabBtn.offsetParent === null) {
            return;
        }

        const nav = this.dom.profileTabsNav || activeTabBtn.closest('.itn-profile-tabs');
        if (!nav) {
            return;
        }

        const navRect = nav.getBoundingClientRect();
        const tabRect = activeTabBtn.getBoundingClientRect();
        const left = tabRect.left - navRect.left + nav.scrollLeft;
        const width = tabRect.width;

        if (width > 0) {
            if (immediate) {
                indicator.style.transition = 'none';
            }
            indicator.style.left = `${left}px`;
            indicator.style.width = `${width}px`;
            indicator.classList.add('is-visible');
            if (immediate) {
                void indicator.offsetWidth;
                indicator.style.transition = '';
            }
        }
    };

    /**
     * Configure the add-to-contacts action for the selected student.
     *
     * @param {Object} student Student API data.
     */
    CourseProgress.prototype.renderContactAction = function(student) {
        const button = this.dom.studentContactButton;
        button.classList.toggle('d-none', student.contactstate === 'unavailable');
        button.disabled = student.contactstate !== 'available';

        const labels = this.labels;
        const stateLabels = {
            available: labels.addcontact,
            pending: labels.contactpending,
            contact: labels.alreadycontact
        };
        this.dom.studentContactLabel.textContent = stateLabels[student.contactstate] || '';
    };

    /**
     * Send a contact request using Moodle's messaging service.
     */
    CourseProgress.prototype.addStudentContact = function() {
        if (!this.currentStudentProgress || this.currentStudentProgress.contactstate !== 'available') {
            return;
        }

        const student = this.currentStudentProgress;
        this.dom.studentContactButton.disabled = true;
        Repository.addContact(student.viewerid, student.id).then((response) => {
            if (response.warnings && response.warnings.length) {
                throw new Error(response.warnings[0].message);
            }
            student.contactstate = 'pending';
            this.dom.studentContactLabel.textContent = this.labels.contactsucceeded;
            return response;
        }).catch((error) => {
            this.dom.studentContactButton.disabled = false;
            this.showError(error);
        });
    };

    /**
     * Toggle comparative batch performance summary (Requirement 4 Extension).
     */
    CourseProgress.prototype.toggleBatchSummary = function() {
        var self = this;
        var area = this.dom.batchSummaryArea;
        if (!area || !this.selectedCourse) {
            return;
        }

        this.batchSummaryOpen = !this.batchSummaryOpen;
        if (!this.batchSummaryOpen) {
            area.classList.add('d-none');
            return;
        }

        var loadingText = '<i class="fa fa-spinner fa-spin me-2"></i> ' +
            self.escapeHtml(self.labels.batchloading);
        area.innerHTML = '<div class="p-3 text-center text-muted">' + loadingText + '</div>';
        area.classList.remove('d-none');

        Repository.getBatchSummary(this.selectedCourse.id).then(function(batches) {
            if (!batches || batches.length === 0) {
                var noBatchesAlert = '<div class="alert alert-secondary mb-0 p-2 small">' +
                    self.escapeHtml(self.labels.nobatches) + '</div>';
                area.innerHTML = noBatchesAlert;
                return batches;
            }

            var html = '<div class="itn-batch-summary-card my-3 p-3">' +
                '<div class="d-flex justify-content-between align-items-center mb-3">' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<i class="fa fa-users text-primary fs-5" aria-hidden="true"></i>' +
                        '<h6 class="mb-0 fw-bold text-primary">' + self.escapeHtml(self.labels.batchtitle) + '</h6>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary itn-close-batch-btn">' +
                        '<i class="fa fa-times me-1"></i> ' + self.escapeHtml(self.labels.batchclose) +
                    '</button>' +
                '</div>' +
                '<div class="table-responsive">' +
                    '<table class="table itn-data-table align-middle mb-0">' +
                        '<thead>' +
                            '<tr>' +
                                '<th class="text-start">' + self.escapeHtml(self.labels.batchname) + '</th>' +
                                '<th class="text-center">' + self.escapeHtml(self.labels.batchmembers) + '</th>' +
                                '<th class="text-center">' + self.escapeHtml(self.labels.batchactive) + '</th>' +
                                '<th class="text-center">' + self.escapeHtml(self.labels.batchcompleted) + '</th>' +
                                '<th class="text-center">' + self.escapeHtml(self.labels.batchrate) + '</th>' +
                                '<th class="text-center">' + self.escapeHtml(self.labels.batchprogress) + '</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>';

            batches.forEach(function(b) {
                var safeBatchName = self.escapeHtml(b.batchname);
                html += '<tr>' +
                    '<td class="fw-bold text-dark text-start">' + safeBatchName + '</td>' +
                    '<td class="text-center fw-bold text-dark">' + b.members + '</td>' +
                    '<td class="text-center fw-bold" style="color: #00b894;">' + b.active + '</td>' +
                    '<td class="text-center fw-bold" style="color: #0066cc;">' + b.completed + '</td>' +
                    '<td class="text-center">' +
                        '<span class="badge rounded-pill text-white px-2 py-1" ' +
                            'style="background-color: #00b4d8; font-weight: 600; font-size: 0.75rem;">' +
                            b.completionrate + '%' +
                        '</span>' +
                    '</td>' +
                    '<td class="text-center">' + self.renderProgressDonut(b.avgprogress) + '</td>' +
                '</tr>';
            });

            html += '</tbody></table></div></div>';
            area.innerHTML = html;

            var closeBtn = area.querySelector('.itn-close-batch-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    area.classList.add('d-none');
                    self.batchSummaryOpen = false;
                });
            }
            return batches;
        }).catch(function() {
            var errAlert = '<div class="alert alert-warning mb-0 p-2 small">' +
                self.escapeHtml(self.labels.batcherror) + '</div>';
            area.innerHTML = errAlert;
        });
    };

    /**
     * Render SVG Donut Progress Circle.
     *
     * @param {Number} progress Percentage (0 - 100).
     * @return {String} HTML string.
     */
    CourseProgress.prototype.renderProgressDonut = function(progress) {
        var p = Math.max(0, Math.min(100, parseInt(progress, 10) || 0));
        var circumference = 87.96;
        var offset = (circumference - (circumference * p) / 100).toFixed(2);

        var arcSvg = '';
        if (p > 0) {
            arcSvg = '<circle cx="18" cy="18" r="14" fill="none" ' +
                'stroke="#00b894" stroke-width="4.8" stroke-linecap="round" ' +
                'stroke-dasharray="' + circumference + '" ' +
                'stroke-dashoffset="' + offset + '" ' +
                'transform="rotate(-90 18 18)"></circle>';
        }

        return '<div class="itn-progress-cell d-inline-flex align-items-center gap-2" ' +
            'role="progressbar" aria-valuenow="' + p + '" aria-valuemin="0" aria-valuemax="100" title="' + p + '%">' +
            '<svg width="38" height="38" viewBox="0 0 36 36" class="flex-shrink-0" style="display: block;">' +
                '<circle cx="18" cy="18" r="14" fill="none" stroke="#edf2f7" stroke-width="4.8"></circle>' +
                arcSvg +
            '</svg>' +
            '<span class="itn-progress-val small fw-normal">' + p + '%</span>' +
        '</div>';
    };

    /**
     * Render pagination controls.
     *
     * @param {HTMLElement} listContainer UL element for page links.
     * @param {HTMLElement} infoContainer Div for text info.
     * @param {Object} data Pagination data.
     * @param {Function} onPageClick Callback when page clicked.
     */
    CourseProgress.prototype.renderPagination = function(listContainer, infoContainer, data, onPageClick) {
        var startCount = data.total > 0 ? data.from : 0;
        infoContainer.textContent = this.labels.showingentries
            .replace('__from__', startCount)
            .replace('__to__', data.to)
            .replace('__total__', data.total);
        listContainer.innerHTML = '';

        var totalPages = Math.ceil(data.total / data.perpage);
        if (totalPages <= 1) {
            return;
        }

        // Previous button.
        var prevLi = document.createElement('li');
        prevLi.className = 'page-item' + (data.page === 0 ? ' disabled' : '');
        prevLi.innerHTML = '<a class="page-link" href="#">' + this.escapeHtml(this.labels.previous) + '</a>';
        prevLi.addEventListener('click', function(e) {
            e.preventDefault();
            if (data.page > 0) {
                onPageClick(data.page - 1);
            }
        });
        listContainer.appendChild(prevLi);

        // Page numbers.
        for (var i = 0; i < totalPages; i++) {
            (function(pageIdx) {
                var li = document.createElement('li');
                li.className = 'page-item' + (data.page === pageIdx ? ' active' : '');
                li.innerHTML = '<a class="page-link" href="#">' + (pageIdx + 1) + '</a>';
                li.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (data.page !== pageIdx) {
                        onPageClick(pageIdx);
                    }
                });
                listContainer.appendChild(li);
            })(i);
        }

        // Next button.
        var nextLi = document.createElement('li');
        nextLi.className = 'page-item' + (data.page >= totalPages - 1 ? ' disabled' : '');
        nextLi.innerHTML = '<a class="page-link" href="#">' + this.escapeHtml(this.labels.next) + '</a>';
        nextLi.addEventListener('click', function(e) {
            e.preventDefault();
            if (data.page < totalPages - 1) {
                onPageClick(data.page + 1);
            }
        });
        listContainer.appendChild(nextLi);
    };

    /**
     * Render clean loading spinner row while loading.
     *
     * @param {HTMLElement} tbody
     * @param {Number} count Number of columns for colspan.
     */
    CourseProgress.prototype.renderSkeleton = function(tbody, count) {
        var cols = count || 5;
        tbody.innerHTML = '<tr>' +
            '<td colspan="' + cols + '" class="text-center py-5">' +
                '<div class="d-inline-flex flex-column align-items-center justify-content-center text-muted">' +
                    '<div class="spinner-border text-primary mb-2" role="status" ' +
                        'style="width: 2rem; height: 2rem; border-width: 0.2em;">' +
                        '<span class="visually-hidden">' + this.escapeHtml(this.labels.loadingprogressdata) + '</span>' +
                    '</div>' +
                    '<span class="small fw-medium text-secondary">' +
                        this.escapeHtml(this.labels.loadingprogressdata) + '</span>' +
                '</div>' +
            '</td>' +
        '</tr>';
    };

    /**
     * Toggle Column Picker Popover.
     *
     * @param {HTMLElement} btn Clicked trigger button.
     */
    CourseProgress.prototype.toggleColumnPicker = function(btn) {
        var wrapper = btn ? btn.closest('.itn-column-picker-wrapper') : null;
        if (!wrapper) {
            return;
        }

        var menu = wrapper.querySelector('[data-region="column-picker-menu"]');
        if (!menu) {
            return;
        }

        var isVisible = menu.style.display === 'block';

        // Close all open column picker menus first.
        this.root.querySelectorAll('[data-region="column-picker-menu"]').forEach(function(m) {
            m.style.display = 'none';
        });

        if (!isVisible) {
            var optionsContainer = menu.querySelector('[data-region="column-options-list"]');
            if (optionsContainer) {
                this.renderColumnPickerOptions(optionsContainer);
            }
            menu.style.display = 'block';
        }
    };

    /**
     * Render Column Picker list items with checkboxes.
     *
     * @param {HTMLElement} container Target options container.
     */
    CourseProgress.prototype.renderColumnPickerOptions = function(container) {
        var self = this;
        if (!container) {
            return;
        }
        container.innerHTML = '';

        var activeCols = this.activeView === 'courses' ? this.allCourseCols : this.allStudentCols;

        activeCols.forEach(function(col) {
            if (col.key === 'index' || col.key === 'coursename' || col.key === 'name') {
                return; // Core columns cannot be hidden.
            }

            var div = document.createElement('div');
            div.className = 'form-check mb-1 d-flex align-items-center gap-2';

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.id = 'itn-col-' + self.activeView + '-' + col.key;
            cb.className = 'form-check-input itn-checkbox';
            cb.checked = col.enabled;

            cb.addEventListener('change', function(e) {
                col.enabled = e.target.checked;
                self.saveColumnPreferences();
                if (self.activeView === 'courses') {
                    self.loadCourses();
                } else {
                    self.loadStudents();
                }
            });

            var label = document.createElement('label');
            label.className = 'form-check-label small text-secondary user-select-none mb-0';
            label.htmlFor = 'itn-col-' + self.activeView + '-' + col.key;
            label.textContent = col.label;

            div.appendChild(cb);
            div.appendChild(label);
            container.appendChild(div);
        });
    };

    /**
     * Reset active view's columns to system defaults.
     */
    CourseProgress.prototype.resetColumns = function() {
        var self = this;
        if (this.activeView === 'courses') {
            localStorage.removeItem('itn_cp_cols_courses');
            var defaultCourseMap = {};
            this.defaultCourseCols.forEach(function(d) {
                defaultCourseMap[d.key] = d.enabled;
            });
            this.allCourseCols.forEach(function(col) {
                col.enabled = defaultCourseMap[col.key] !== undefined ? defaultCourseMap[col.key] : true;
            });
            this.loadCourses();
        } else {
            localStorage.removeItem('itn_cp_cols_students');
            var defaultStudentMap = {};
            this.defaultStudentCols.forEach(function(d) {
                defaultStudentMap[d.key] = d.enabled;
            });
            this.allStudentCols.forEach(function(col) {
                col.enabled = defaultStudentMap[col.key] !== undefined ? defaultStudentMap[col.key] : true;
            });
            this.loadStudents();
        }

        // Re-render open column picker checkbox lists.
        this.root.querySelectorAll('[data-region="column-options-list"]').forEach(function(container) {
            self.renderColumnPickerOptions(container);
        });
    };

    /**
     * Save column preferences to localStorage.
     */
    CourseProgress.prototype.saveColumnPreferences = function() {
        var activeCourseKeys = [];
        var activeStudentKeys = [];

        if (this.activeView === 'courses') {
            this.allCourseCols.forEach(function(c) {
                if (c.enabled) {
                    activeCourseKeys.push(c.key);
                }
            });
            localStorage.setItem('itn_cp_cols_courses', JSON.stringify(activeCourseKeys));
        } else {
            this.allStudentCols.forEach(function(c) {
                if (c.enabled) {
                    activeStudentKeys.push(c.key);
                }
            });
            localStorage.setItem('itn_cp_cols_students', JSON.stringify(activeStudentKeys));
        }
    };

    /**
     * Display error banner.
     *
     * @param {Object} error
     */
    CourseProgress.prototype.showError = function(error) {
        if (this.dom.alertContainer) {
            var msg = error && error.message ? error.message : this.labels.errorgeneric;
            this.dom.alertText.textContent = msg;
            this.dom.alertContainer.classList.remove('d-none');
        }
    };

    return {
        /**
         * Module entry point.
         *
         * @param {Object} config
         */
        init: function(config) {
            return new CourseProgress(config);
        }
    };
});
