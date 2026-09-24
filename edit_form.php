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
 * Block instance configuration form.
 *
 * @package    block_itn_course_progress
 * @copyright  2026 ITN-BUET
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course Progress block edit form.
 */
class block_itn_course_progress_edit_form extends block_edit_form {

    /**
     * Define form elements for block instance settings.
     *
     * @param moodleform $mform
     */
    protected function specific_definition($mform): void {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('selectyesno', 'config_defaultloadprogress',
            get_string('settings:defaultloadprogress', 'block_itn_course_progress'));
        $mform->setDefault('config_defaultloadprogress', 1);

        $mform->addElement('select', 'config_defaultperpage',
            get_string('settings:defaultperpage', 'block_itn_course_progress'),
            [5 => 5, 10 => 10, 25 => 25, 50 => 50]);
        $mform->setDefault('config_defaultperpage', 10);
    }
}
