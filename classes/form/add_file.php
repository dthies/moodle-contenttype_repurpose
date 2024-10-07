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
 * Provides the class that defines the form for the repurpose authoring tool.
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_repurpose\form;

use core_contentbank\form\edit_content;
use context_user;
use context;
use stdClass;
use moodle_exception;
use moodleform;
use question_bank;
use license_manager;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once($CFG->libdir . '/licenselib.php');

/**
 * Defines the form for editing an repurpose content.
 *
 * This file is the integration between a content type editor and the content
 * bank creation form.
 *
 * @copyright 2020 onward Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_file extends moodleform {
    /**
     * Defines the form fields.
     */
    protected function definition() {
        global $CFG;

        $mform = $this->_form;

        $library = $this->_customdata['library'] ?? optional_param('library', 'singlechoiceset', PARAM_TEXT);

        // Title used for hover text.
        $mform->addElement('text', 'title', get_string('title', 'contenttype_repurpose'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'title', 'contenttype_repurpose');

        // Add license selector.
        $licenses = [];
        foreach (array_column(license_manager::get_active_licenses(), 'fullname', 'shortname') as $shortname => $fullname) {
            $licenses[editor::get_h5p_license($shortname)] = $fullname;
        }
        $mform->addElement('select', 'license', get_string('license'), $licenses, '');
        $mform->setDefault('license', $CFG->sitedefaultlicense);
        $mform->addHelpButton('license', 'license', 'contenttype_repurpose');

        // Add context type specific form fields.
        $mform->addElement('hidden', 'library', $library);
        $mform->setType('library', PARAM_TEXT);

        $mform->addElement('hidden', 'draftid', 0);
        $mform->addElement('hidden', 'key', 0);

        $mform->addElement('hidden', 'mediafile', 0);
    }

    /**
     * Allow helper to define form with supplied data.
     * @return void
     */
    public function definition_after_data() {
        $mform = $this->_form;

        if ($mform->getElementValue('draftid')) {
            $mform->removeElement('mediafile');

            $mform->addElement(
                'filepicker',
                'mediafile',
                get_string('newfile', 'contenttype_repurpose'),
                null,
                [
                    'maxbytes' => 0,
                    'accepted_types' => [
                        'gif',
                        'png',
                        'jpg',
                        'm4a',
                        'mp3',
                        'mp4',
                        'wav',
                        'webm',
                    ],
                ]
            );
        }
    }
}
