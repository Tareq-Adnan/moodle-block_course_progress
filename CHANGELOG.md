# Changelog

All notable changes to the **Course & Learner Progress Block** (`block_itn_course_progress`) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.3] - 2026-09-24

### Security
- Enforced separate-groups isolation for learner lists, course aggregates, profiles, and activity details.
- Excluded users with teacher or reporting capabilities from learner progress populations, including mixed-role users.
- Restricted non-admin teachers to their active enrolled courses and bounded external-service pagination values.

### Fixed
- Corrected group-list PHPUnit expectations and added authorization regression coverage.
- Localized dynamically rendered JavaScript and Mustache interface text.
- Corrected Marketplace documentation, supported PHP versions, database requirements, and configuration values.
- Rebuilt AMD production assets from the current sources.

---

## [1.3.2] - 2026-09-24

### Fixed
- **HTML Entity Decoding**: Ensured proper UTF-8 decoding for course fullnames, categories, group names, and activities containing special characters (e.g. `&amp;` -> `&`) across both external service payloads and client-side DOM rendering.
- **Client Sanitization**: Implemented defensive `decodeHtml()` utility in AMD controller to prevent double-escaped literal entities.

### Added
- **Sliding Tab Indicator**: Smooth animated underline transition for student profile tab switches (`updateTabIndicator`).
- **Dynamic Accordion Height**: Height measurement transitions (`scrollHeight`) for activity detail panels.

---

## [1.3.1] - 2026-09-23

### Added
- **Activity Engagement & Logstore Telemetry**: Activity drill-down cards now display student logstore interaction counts, last access timestamps, and final graded values.
- **Branded & Filtered Activity Icons**: Support for core module purpose classes, custom activity icons, and SVG filter indicators.

---

## [1.3.0] - 2026-09-23

### Added
- **Learner Cross-Course Progress View**: Teachers and managers can drill down into an individual student to inspect cross-course enrollments, completion rates, and contact profiles.
- **Messaging Integration**: Direct messaging link and contact request button (`core_message_create_contact_request`).
- **Full-Screen Focus Mode**: Expandable overlay view for detailed analytics tracking.

---

## [1.2.0] - 2026-09-22

### Added
- **Batch Performance Comparison**: Modal view comparing group/cohort metrics including active learners, completions, completion rate, and average progress rings.
- **SVG Circular Progress Indicators**: Scalable vector donuts for courses, batches, and individual student progress with accessible percentage badges.

---

## [1.1.0] - 2026-09-20

### Added
- **Student Drill-Down View**: Clickable course rows displaying enrolled students with real-time status (Active, Inactive, Completed, Not Started).
- **Batch / Group Filter**: Live group dropdown filter for courses with configured groups.

---

## [1.0.0] - 2026-09-15

### Added
- **Initial Release**: Core course progress dashboard for Moodle 5.0+.
- **Configurable Column Picker**: Custom local storage persistence for selecting visible columns.
- **AJAX-Powered Search and Sorting**: Debounced real-time search, multi-column sorting, and pagination.
- **Role-Based Scoping**: Non-admin teachers only see courses where they possess grading/reporting capabilities.
- **Moodle Privacy API**: Full compliance with `core_privacy` null provider.
