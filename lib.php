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
 * Library functions called by system
 *
 * @package     contenttype_repurpose
 * @copyright   2020 onward Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return form content for various content types
 *
 * @param array $args The arguments area containing form data
 * @return string HTML of form to display
 */
function contenttype_repurpose_output_fragment_formupdate(array $args): string {
    $context = $args['contextid'];
    $library = $args['library'];

    $url = new moodle_url('/contentbank/edit.php', array(
        'contextid' => $context,
        'library' => $library,
        'plugin' => 'repurpose',
    ));

    $editor = new \contenttype_repurpose\form\editor($url, array(
        'contextid' => $context,
        'id' => null,
        'library' => $library,
        'plugin' => 'contenttype_repurpose',
    ));

    if ($data = json_decode($args['jsonformdata'])) {
        $editor->set_data($data);
        return $editor->render();
    }
    return '';
}
