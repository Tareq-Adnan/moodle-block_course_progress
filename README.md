# Course & Learner Progress Block for Moodle

[![Moodle 5.0+](https://img.shields.io/badge/moodle-5.0+-orange.svg)](https://moodle.org)
[![PHP 8.2 - 8.4](https://img.shields.io/badge/php-8.2--8.4-blue.svg)](https://www.php.net)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

A high-performance, interactive dashboard block providing real-time course analytics, learner engagement tracking, batch performance comparisons, and activity-level completion reports for teachers and institutional managers.

Designed for higher education, corporate training, and institutional learning environments using Moodle 5.0+.

---

## 🌟 Key Features

1. **Course Overview Table (Level 1)**:
   - **Real-Time Progress & Engagement**: Live counts of enrolled, active, and completed learners, visit frequencies, and average completion rings.
   - **Column Visibility Customizer**: Personalize visible columns (Start Date, End Date, Category, Batches, Visits, Completion Rate) with client-side persistence in `localStorage`.
   - **Debounced Instant Search**: Search course full names and short names.
   - **Sorting & Pagination**: Sort by course title, start date, or end date and page through bounded result sets.

2. **Student & Cohort Drill-Down (Level 2)**:
   - Click any course to immediately view its roster without leaving the dashboard page.
   - Filter students by course groups / cohorts (**Batches**).
   - Search students by full name or email address.
   - Open Moodle messaging and send contact requests through Moodle's core messaging service.

3. **Comparative Batch Performance Analysis**:
   - Compare multiple course groups side-by-side.
   - Visual SVG progress indicators displaying average completion percentages, member counts, and active vs. completed ratios.

4. **Learner Engagement Deep Dive (Level 3)**:
   - Detailed cross-course analytics for any selected student.
   - Inspect activity completion statuses (**Passed**, **Failed**, **Completed**, **Incomplete**, **Not Started**).
   - Detailed engagement metrics grounded in Moodle's `logstore_standard_log` (interaction count, last access date, final graded score).
   - Animated smooth tab transitions and responsive accordion panel expand/collapse.

5. **Strict Role-Based Scoping & Security**:
   - Site administrators see all platform courses.
   - Non-admin teachers only see their active enrolled courses where they hold reporting access.
   - Users with teacher or reporting capabilities are excluded from learner progress populations, even when they also have a learner role.
   - Separate-groups mode limits lists, aggregates, profiles, and activity details to the reporter's accessible groups.
   - Cross-course learner data is scoped so teachers cannot discover progress in unauthorized courses or groups.

---

## 📋 System Requirements

* **Moodle**: 5.0 or later (Build 2025041400+)
* **PHP**: 8.2, 8.3, or 8.4, following the selected Moodle release requirements
* **Database**: A database version supported by the selected Moodle release; Moodle 5.0 requires MySQL 8.4+, MariaDB 10.11+, or PostgreSQL 14+
* **Plugin dependencies**: None
* **Core features used**: Completion tracking, Gradebook, groups, messaging, and Standard Logstore

---

## 🚀 Installation

### ZIP package

1. Download the latest release `.zip` package.
2. Install it through **Site administration > Plugins > Install plugins**, or unpack it into `blocks/itn_course_progress`.
3. Ensure the package has one top-level directory named `itn_course_progress`.

### Complete installation

Log into Moodle as an Administrator and navigate to:
**Site administration > Notifications** (or run `php admin/cli/upgrade.php` from your command line).

---

## ⚙️ Configuration

Site administrators can configure global defaults at:
**Site administration > Plugins > Blocks > Course Progress**:

* **Always Load Progress (`defaultloadprogress`)**: Determines whether completion rings calculate automatically on initial load or upon clicking "Load Progress" (recommended for high-concurrency sites).
* **Default Records Per Page (`defaultperpage`)**: Default rows per table page (5, 10, 25, or 50).
* **Enable Batch Summary (`enablebatchsummary`)**: Toggle visibility of the comparative batch performance button.
* **Default Course Columns (`course_columns`)**: Configure which columns appear enabled by default for all users.
* **Default Student Columns (`student_columns`)**: Configure default columns for the student roster drill-down.

---

## 🔒 Capabilities & Permissions

| Capability | Archetypes | Description |
| :--- | :--- | :--- |
| `block/itn_course_progress:myaddinstance` | Manager, Teacher, Editing Teacher | Allow adding the block to the Dashboard/My Moodle. |
| `block/itn_course_progress:addinstance` | Manager, Editing Teacher | Allow adding the block to any course or site page. |
| `block/itn_course_progress:view` | Manager, Teacher, Editing Teacher | Allow viewing course analytics in an authorized course context. |

Managers and administrators can report across all courses allowed by their Moodle permissions. Other teachers only receive actively enrolled courses in which they have reporting access. Moodle's separate-groups and access-all-groups rules are enforced throughout the report.

---

## 🌐 Web Services & AJAX Endpoints

The block operates seamlessly via Moodle's External Services API (`core/ajax`):
* `block_itn_course_progress_get_courses`: Paginated courses with real-time completion calculations.
* `block_itn_course_progress_get_students`: Roster with status, group filtering, and completion rates.
* `block_itn_course_progress_get_student_progress`: Individual student profile and authorized courses.
* `block_itn_course_progress_get_student_activities`: Activity-by-activity logs, grade values, and completion state.
* `block_itn_course_progress_get_groups`: Group/batch dropdown list.
* `block_itn_course_progress_get_batch_summary`: Cohort comparison statistics.

---

## 🛡️ Privacy & GDPR Compliance

This plugin implements the Moodle Privacy Subsystem (`\core_privacy\local\metadata\null_provider`).
* The block **does not store any personal data** in its own database tables.
* The block displays and calculates authorized reports from existing Moodle core data, including enrolment, profile, completion, grade, group, messaging, and standard-log records.
* Column preferences are stored in the viewer's browser `localStorage` and contain column identifiers only.
* The plugin does not transmit personal data to an external service.

---

## 🧪 Automated Testing

This block includes comprehensive PHPUnit tests covering helper calculations, permission authorization, and privacy provider contracts:

```bash
# Initialize PHPUnit test database
php admin/tool/phpunit/cli/init.php

# Run the block test suite
vendor/bin/phpunit blocks/itn_course_progress/tests
```

The suite covers course authorization, learner filtering, separate-groups isolation, external return structures, paging validation, and the Privacy API contract.

---

## 📄 License & Credits

* **Author**: Tarekul Islam, Software Engineer, Brain Station 23
* **License**: [GNU General Public License v3.0 or later (GPL-3.0-or-later)](LICENSE)
* **Copyright**: © 2026 Tarekul Islam
