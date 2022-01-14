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
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_repurpose\local;

use contenttype_h5p\content;
use contenttype_h5p\contenttype;
use core_contentbank\form\edit_content;
use core_h5p\editor as h5peditor;
use core_h5p\factory;
use core_h5p\helper;
use context_user;
use context;
use stdClass;
use moodle_exception;
use moodle_form;
use question_bank;
use contenttype_repurpose\local\dialogcards;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/questionlib.php');

/**
 * Defines the form for editing an repurpose content.
 *
 * This file is the integration between a content type editor and the content
 * bank creation form.
 *
 * @copyright 2020 onward Daniel Thies <dethies@gmail.com>
 */
class crossword extends dialogcards {

    /** @var $type Machine name for target type */
    public $library = 'H5P.Crossword 0.4';

    /**
     * Modify or create an repurpose content from the form data.
     *
     * @param stdClass $data Form data to create or modify an repurpose content.
     *
     * @return int The id of the edited or created content.
     */
    public function get_content(stdClass $data): stdClass {
        global $DB;

        $content = parent::get_content($data);

        $content->taskDescription = $data->description;

        return $content;
    }

    /**
     * Process data from an array of questions
     *
     * @param array $questions questions to process
     * @return stdClass The content file object
     */
    public function create_params($questions): stdClass {
        $content = json_decode('{
            "theme": {},
            "words": []
        }');

        $fs = get_file_storage();
        foreach ($questions as $question) {
            if ($question->qtype == 'shortanswer' && empty($question->parent)) {
                $answers = array_column($question->options->answers, 'fraction', 'answer');
                arsort($answers);

                $word = (object) array(
                    'fixWord' => false,
                    'clue' => strip_tags($question->questiontext, '<b><i><em><strong>'),
                    'answer' => array_keys($answers)[0],
                    'subContentId' => $this->create_subcontentid(),
                );

                if ($files = $fs->get_area_files($this->context->id, 'question', 'questiontext', $question->id)) {
                    if (empty($this->files)) {
                        $this->files = array();
                    }

                    foreach ($files as $f) {
                        if ($f->is_valid_image()) {
                            $imageinfo = $f->get_imageinfo();
                            $filename = $this->getname('image', $f->get_filename());
                            $this->files['content/images/' . $filename] = $f;
                            $word->extraClue = (object) array(
                                'params' => (object) array(
                                    'contentName' => 'Image',
                                    'file' => (object) array(
                                        'copyright' => (object) array(
                                            'licensee' => 'U',
                                        ),
                                        'path' => 'images/' .  $filename,
                                        'mimetype' => $imageinfo['mimetype'],
                                        'height' => $imageinfo['height'],
                                        'width' => $imageinfo['width'],
                                    ),
                                ),
                                'library' => 'H5P.Image 1.1',
                                'metadata' => (object) array(
                                    'contentName' => 'Image',
                                    'licensee' => 'U',
                                    'title' => 'Untitled image',
                                ),
                                'subContentId' => $this->create_subcontentid(),
                            );
                            break;
                        }
                    }
                }
                $content->words[] = $word;
            }
        }
        if (!empty($this->background)) {
            $content->theme->backgroundImage = $this->background;
        }

        return $content;
    }

    /**
     * Defines the additional form fields.
     *
     * @param moodle_form $mform form to modify
     */
    public function add_form_fields($mform) {
        parent::add_form_fields($mform);
        $this->repeatelements = [];
        $this->repeatoptions = [];

        $mform->addElement(
            'filepicker',
            'backgroundimage',
            get_string('backgroundimage', 'contenttype_repurpose'),
            null,
            array(
                'maxbytes' => 0,
                'accepted_types' => array('png', 'jpg', 'gif'),
            )
        );
    }

    /**
     * Process files attachded to form.
     *
     * @param moodleform $form form that is submitted
     * @return void
     */
    public function process_files($form): void {
        if ($imagecontent = $form->get_file_content('backgroundimage')) {
            $filename = $this->getname('backgroundImage', $form->get_new_filename('backgroundimage'));
            $this->files['content/images/' . $filename] = array($form->get_file_content('backgroundimage'));
            $imageinfo = @getimagesizefromstring($imagecontent);
            $this->background = (object) array(
                'license' => 'U',
                'path' => 'images/' . $filename,
                'height' => $imageinfo[1],
                'width' => $imageinfo[0],
                'mime' => $imageinfo['mime'],
            );
        }
    }
}
