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
 * Main block class for Course Progress.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Course Progress block definition.
 */
class block_itn_course_progress extends block_base {
    /**
     * Initialize block.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('blocktitle', 'block_itn_course_progress');
    }

    /**
     * Places where this block is allowed to be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'my' => true,
            'site' => true,
            'course-view' => true,
        ];
    }

    /**
     * Enable global configuration page.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Allow multiple instances on the same page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Determine whether the user can add this block to the given page.
     *
     * @param moodle_page $page
     * @return bool
     */
    public function user_can_addto($page): bool {
        global $USER;

        if (!parent::user_can_addto($page)) {
            return false;
        }

        return \block_itn_course_progress\helper::can_user_view_any_progress((int)$USER->id, $page->context);
    }

    /**
     * Build and return block content.
     *
     * @return stdClass|null
     */
    public function get_content(): ?stdClass {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        // Must be logged in and not guest.
        if (!isloggedin() || isguestuser()) {
            return null;
        }

        // Check if user is authorized to view course progress in this context.
        $context = $this->context ?? ($this->page->context ?? context_system::instance());
        if (!\block_itn_course_progress\helper::can_user_view_any_progress((int)$USER->id, $context)) {
            return null;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Prepare renderable.
        $renderable = new \block_itn_course_progress\output\main($this->config);
        $renderer = $this->page->get_renderer('block_itn_course_progress');

        $this->content->text = $renderer->render($renderable);

        return $this->content;
    }
}
