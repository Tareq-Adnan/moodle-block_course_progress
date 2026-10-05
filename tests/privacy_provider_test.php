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

use advanced_testcase;
use block_itn_course_progress\privacy\provider;
use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider tests for the Course Progress block.
 *
 * @package    block_itn_course_progress
 * @category   test
 * @copyright  2026 Tarekul Islam
 * @author     Tarekul Islam, Software Engineer, Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_itn_course_progress\privacy\provider
 */
final class privacy_provider_test extends advanced_testcase {
    /**
     * Test that the privacy provider implements null_provider.
     */
    public function test_implements_null_provider(): void {
        $this->assertTrue(is_subclass_of(provider::class, null_provider::class));
    }

    /**
     * Test that the privacy provider returns the expected language string key.
     */
    public function test_get_reason(): void {
        $this->assertEquals('privacy:metadata', provider::get_reason());
    }
}
