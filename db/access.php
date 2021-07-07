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
 * Plugin capabilities are defined here.
 *
 * @package     contenttype_repurpose
 * @category    access
 * @copyright   2020 onward Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'contenttype/repurpose:access' => [
        'captype' => 'view',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
        ],
        'clonepermissionsfrom' => 'contenttype/h5p:access',
    ],

    'contenttype/repurpose:upload' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
        ],
        'clonepermissionsfrom' => 'contenttype/h5p:upload',
    ],

    'contenttype/repurpose:useeditor' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
        ],
        'clonepermissionsfrom' => 'contenttype/h5p:useeditor',
    ],
];
