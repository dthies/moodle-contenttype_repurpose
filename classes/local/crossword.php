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
use moodle_url;
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
    /** @var $background Background image */
    public $background = null;

    /** @var $library Machine name for target type */
    public $library = 'H5P.Crossword 0.4';

    /**
     * Modify or create an repurpose content from the form data.
     *
     * @param stdClass $data Form data to create or modify an repurpose content.
     *
     * @return int The id of the edited or created content.
     */
    public function get_content(stdClass $data): stdClass {
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
        $context = \core\context\module::instance($this->cmid);
        foreach ($questions as $question) {
            if ($question->qtype == 'shortanswer' && empty($question->parent)) {
                $answers = array_column($question->options->answers, 'fraction', 'answer');
                arsort($answers);

                $word = (object) [
                    'fixWord' => false,
                    'clue' => strip_tags($question->questiontext, '<b><i><em><strong>'),
                    'answer' => array_keys($answers)[0],
                    'subContentId' => $this->create_subcontentid(),
                ];

                if ($files = $fs->get_area_files($context->id, 'question', 'questiontext', $question->id)) {
                    if (empty($this->files)) {
                        $this->files = [];
                    }

                    foreach ($files as $f) {
                        if ($f->is_valid_image()) {
                            $imageinfo = $f->get_imageinfo();
                            $filename = $this->getname('image', $f->get_filename());
                            $this->files['content/images/' . $filename] = $f;
                            $word->extraClue = (object) [
                                'params' => (object) [
                                    'contentName' => 'Image',
                                    'file' => (object) [
                                        'copyright' => (object) [
                                            'licensee' => 'U',
                                        ],
                                        'path' => 'images/' .  $filename,
                                        'mimetype' => $imageinfo['mimetype'],
                                        'height' => $imageinfo['height'],
                                        'width' => $imageinfo['width'],
                                    ],
                                ],
                                'library' => 'H5P.Image 1.1',
                                'metadata' => (object) [
                                    'contentName' => 'Image',
                                    'licensee' => 'U',
                                    'title' => 'Untitled image',
                                ],
                                'subContentId' => $this->create_subcontentid(),
                            ];
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
        $mform->addElement('hidden', 'background');
        $mform->setType('background', PARAM_RAW);
        $backgroundimage = [
            $mform->createElement('button', 'addfile', get_string('addfile', 'contenttype_repurpose')),
        ];
        $mform->addGroup($backgroundimage, 'backgroundimage', get_string('backgroundimage', 'contenttype_repurpose'));
        $mform->addElement('hidden', 'draftid', 0);
        $mform->setType('draftid', PARAM_INT);
    }

    /**
     * Change definitiion after supplied data.
     *
     * @param moodle_form $mform form with data to be modified
     * @return void
     */
    public function definition_after_data($mform) {
        global $DB, $OUTPUT, $PAGE, $USER;

        $fs = get_file_storage();

        // If there is an existing image display it.
        if (
            ($id = $mform->getElementValue('id'))
            && !$mform->getElementValue('draftid')
            && $record = $DB->get_record('contentbank_content', ['id' => $id])
        ) {
            $content = new \contenttype_repurpose\content($record);
            $configdata = json_decode($content->get_configdata());
            if (
                !empty($configdata)
            ) {
                $this->set_data($mform, $configdata);
                $mform->setDefault(
                    'background',
                    $configdata->background
                );
                $imagepath = json_decode($configdata->background)->params->file->path;
            }
        } else if ($mform->getElementValue('background')) {
            $imagepath = json_decode($mform->getElementValue('background'))->params->file->path ?? '';
        } else {
            $imagepath = '';
        }
        if (
            (!$draftid = $mform->getElementValue('draftid') ?? 0)
            || optional_param('library', '', PARAM_TEXT)
        ) {
            $PAGE->requires->js_call_amd(
                'contenttype_repurpose/editbackground',
                'init',
                [$this->context->id, 'crossword', $this->cmid]
            );
        }

        $image = file_prepare_draft_area(
            $draftid,
            $mform->getElementValue('contextid'),
            'contenttype_repurpose',
            'content',
            $id,
            ['subdirs' => true],
            $OUTPUT->render_from_template(
                'contenttype_repurpose/media',
                ['path' => $imagepath]
            )
        );
        $mform->setDefault('draftid', $draftid);
        if (!empty($imagepath)) {
            $mform->removeElement('backgroundimage');
            $mform->insertElementBefore(
                $mform->createElement('static', 'backgroundimage', get_string('backgroundimage', 'contenttype_repurpose'), $image),
                'background'
            );
        }
        $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id DESC', false);
        foreach ($files as $file) {
            if (
                ($file->get_filepath() . $file->get_filename() == '/content/' . $imagepath)
                && $imageinfo = $file->get_imageinfo()
            ) {
                $this->files['content/' . $imagepath] = $file;
                $this->background = (object) [
                    'license' => 'U',
                    'path' => 'images/' . $file->get_filename(),
                    'height' => $imageinfo['height'],
                    'width' => $imageinfo['width'],
                    'mime' => $imageinfo['mimetype'],
                ];
            }
        }
    }
}
