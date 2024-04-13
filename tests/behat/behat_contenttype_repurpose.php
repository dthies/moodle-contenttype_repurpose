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
 * Steps definitions related to conntenttype_repurpose.
 *
 * @package   contenttype_repurpose
 * @category  test
 * @copyright 2021 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

use Behat\Mink\Exception\ExpectationException;

/**
 * Steps definitions related to conntenttype_repurpose.
 *
 * @copyright 2021 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_contenttype_repurpose extends behat_base {
    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | pagetype          | description                                  |
     * | column            | Import category to colummn                   |
     * | question          | Import singgle question                      |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Admin notification'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        if (
            in_array(strtolower($page), [
            'column',
            'question',
            ])
        ) {
            return  new moodle_url('/contentbank/edit.php', [
                'contextid' => context_system::instance()->id,
                'library' => str_replace('contenttype_repurpose ', '', strtolower($page)),
                'plugin' => 'repurpose',
            ]);
        }
        throw new Exception('Unrecognised content type repurpose page type "' . $page . '."');
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype          | name meaning                                | description                                  |
     * | column            | Course name of cotext                       | Import category to colummn                   |
     * | question          | Course name of cotext                       | Import singgle question                      |
     *
     * @param string $type identifies which type of page this is, e.g. 'Attempt review'.
     * @param string $identifier identifies the particular page, e.g. 'Test quiz > student > Attempt 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        if (
            in_array(strtolower($type), [
            'column',
            'question',
            ])
        ) {
            return  new moodle_url('/contentbank/edit.php', [
                'contextid' => context_course::instance($this->get_course_id($identifier))->id,
                'library' => strtolower($type),
                'plugin' => 'repurpose',
            ]);
        }
        throw new Exception('Unrecognised content type repurpose page type "' . $type . '."');
    }

    /**
     * Get course id from its identifier (shortname or fullname or idnumber)
     *
     * @param string $identifier
     * @return int
     * @throws dml_exception
     */
    protected function get_course_id(string $identifier): int {
        global $DB;

        return $DB->get_field_select(
            'course',
            'id',
            "shortname = :shortname OR fullname = :fullname OR idnumber = :idnumber",
            [
                'shortname' => $identifier,
                'fullname' => $identifier,
                'idnumber' => $identifier,
            ],
            MUST_EXIST
        );
    }
}
