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
 * repurpose content type manager class
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_repurpose;

use core\event\contentbank_content_viewed;
use core_contentbank\content;
use core_h5p\file_storage;
use stored_file;
use core_h5p\editor_ajax;
use core_h5p\local\library\autoloader;
use context;
use pix_icon;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * repurpose content bank manager class.
 *
 * This class is the general manager for content type HTML. This class have
 * all the methods to create, delete, update and view HTML contents.
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contenttype extends \core_contentbank\contenttype {
    /**
     * Returns the HTML content to add to view.php visualizer.
     *
     * @param  content $content The content to be displayed.
     * @return string            HTML code to include in view.php.
     */
    public function get_view_content(\core_contentbank\content $content): string {
        // Trigger an event for viewing this content.
        $event = contentbank_content_viewed::create_from_record($content->get_content());
        $event->trigger();

        $fileurl = $content->get_file_url();
        $html = \core_h5p\player::display($fileurl, new \stdClass(), true);
        return $html;
    }

    /**
     * Returns the HTML code to render the icon for repurpose content types.
     *
     * Note that every content can define it's own icon. This mean that if your plugin
     * handle more thant one content format it is possible to assign a different icon to
     * each one of them.
     *
     * @param  content $content The content to be displayed.
     * @return string            HTML code to render the icon
     */
    public function get_icon(content $content): string {
        global $OUTPUT;
        $iconurl = $OUTPUT->image_url('f/folder-64', 'moodle')->out(false);
        return $iconurl;
    }

    /**
     * Return an array of implemented features by this plugin.
     *
     * For now, the two main features are:
     * - self::CAN_UPLOAD: if the content can be created via a file
     * - self::CAN_EDIT: if the content can be edited online when is created
     *
     * This features list will increase in future versions to enable
     * embeding and other nice features.
     *
     * @return array
     */
    protected function get_implemented_features(): array {
        return property_exists('\\core_contentbank\\contenttype', 'CAN_DOWNLOAD') ? [
            self::CAN_DOWNLOAD,
            self::CAN_EDIT,
            self::CAN_UPLOAD,
        ] : [
            self::CAN_EDIT,
            self::CAN_UPLOAD,
        ];
    }

    /**
     * Return an array of extensions this contenttype could manage.
     *
     * Note that on plugin can handle as many extensions as it wants.
     *
     * @return array
     */
    public function get_manageable_extensions(): array {
        return [
        ];
    }

    /**
     * Returns user has access capability for the content itself.
     *
     * @return bool     True if content could be accessed. False otherwise.
     */
    protected function is_access_allowed(): bool {
        return true;
    }

    /**
     * Returns the list of different repurpose content types the user can create.
     *
     * @return array An object for each repurpose content type:
     *     - string typename: descriptive name of the repurpose content type.
     *     - string typeeditorparams: params required by the repurpose editor.
     *          If no editor params are generated, the Content Bank will consider it
     *          a simple title or group of options.
     *     - url typeicon: h5p content type icon.
     */
    public function get_contenttype_types(): array {
        global $OUTPUT;
        // Get the H5P content types available.
        autoloader::register();
        $editorajax = new editor_ajax();
        $h5pcontenttypes = $editorajax->getLatestLibraryVersions();
        $machinenames = array_column($h5pcontenttypes, 'machine_name');

        $types = [];
        if (
            array_intersect([
                'H5P.MultiplueChoice',
                'H5P.FillBlanks',
                'H5P.Essay',
                'H5P.TrueFalse',
                'H5P.GuessAnswer',
            ], $machinenames)
        ) {
            $types[] = (object) [
                'typename' => get_string('importquestion', 'contenttype_repurpose'),
                'typeeditorparams' => 'library=question',
                'typeicon' => $OUTPUT->image_url('e/question', 'core'),
            ];
        }
        $h5pfilestorage = new file_storage();
        foreach ($h5pcontenttypes as $h5pcontenttype) {
            $typeicon = $h5pfilestorage->get_icon_url(
                $h5pcontenttype->id,
                $h5pcontenttype->machine_name,
                $h5pcontenttype->major_version,
                $h5pcontenttype->minor_version
            );
            switch ($h5pcontenttype->machine_name) {
                case 'H5P.Column':
                    $types[] = (object) [
                        'typename' => get_string('importcolumn', 'contenttype_repurpose'),
                        'typeeditorparams' => 'library=column',
                        'typeicon' => $typeicon,
                    ];
                    break;
                case 'H5P.Crossword':
                    $types[] = (object) [
                        'typename' => get_string('importcrossword', 'contenttype_repurpose'),
                        'typeeditorparams' => 'library=crossword',
                        'typeicon' => $typeicon,
                    ];
                    break;
                case 'H5P.Dialogcards':
                    $types[] = (object) [
                        'typename' => get_string('importdialogcards', 'contenttype_repurpose'),
                        'typeeditorparams' => 'library=dialogcards',
                        'typeicon' => $typeicon,
                    ];
                    break;
                case 'H5P.Flashcards':
                    $types[] = (object) [
                        'typename' => get_string('importflashcards', 'contenttype_repurpose'),
                        'typeeditorparams' => 'library=flashcards',
                        'typeicon' => $typeicon,
                    ];
                    break;
                case 'H5P.SingleChoiceSet':
                    $types[] = (object) [
                        'typename' => get_string('importsinglechoiceset', 'contenttype_repurpose'),
                        'typeeditorparams' => 'library=singlechoiceset',
                        'typeicon' => $typeicon,
                    ];
                    break;
            }
        };

        return array_filter($types);
    }

    /**
     * Delete this content from the content_bank.
     * This method can be overwritten by the plugins if they need to delete specific information.
     *
     * @param  content $content The content to delete.
     * @return boolean true if the content has been deleted; false otherwise.
     */
    public function delete_content(content $content): bool {
        $fs = get_file_storage();

        // First delete stored files for content.
        $files = $fs->get_area_files(
            $content->get_contextid(),
            'contentype_repurpose',
            'content',
            $content->get_id(),
            'id DESC',
            false
        );
        foreach ($files as $file) {
            $file->delete();
        }

        return parent::delete_content($content);
    }
}
