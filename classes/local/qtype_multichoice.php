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
 * Question Import for H5P Quiz content type
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace contenttype_repurpose\local;

use stdClass;
use context_user;
use core_h5p\local\library\autoloader;
use core_h5p\editor_ajax;

/**
 * Question export helper for H5P Quiz content type
 *
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multichoice {
    /** @var $files files to include with content */
    public $files = null;

    /** @var $type Machine name for target type */
    public $library = 'H5P.MultiChoice 1.14';

    /** @var $type option for target content */
    public $type = 'multiplechoice';

    /** @var $question $question Question data */
    public $question = null;

    /**
     * Constructor
     *
     * @param stdClass $question Question data
     */
    public function __construct($question) {

        $this->files = [];

        $this->question = $question;

        $contenttype = $this->get_contenttype();
        if (!empty($contenttype)) {
            $this->library = "{$contenttype->machine_name} {$contenttype->major_version}.{$contenttype->minor_version}";
        } else {
            $this->library = '';
        }
    }

    /**
     * Rearrange the question data to expected H5P format
     *
     * @param stdClass $content
     * @return stdClass
     */
    public function process_question(stdClass $content): stdClass {

        $content->question = strip_tags($this->question->questiontext, '<b><i><em><strong>');

        $answers = [];

        foreach ($this->question->options->answers as $answer) {
            $answers[] = (object) [
                'text' => $answer->answer,
                'correct' => $answer->fraction == 1 || (empty($this->question->single) && $answer->fraction > 0),
                'tipAndFeedback' => (object) [
                    'tip' => '',
                    'chosenFeedback' => $answer->feedback,
                    'notChosenFeedback' => '',
                ],
            ];
        }
        $content->answers = $answers;

        return $content;
    }

    /**
     * Attach media and feedback to content and convert to H5P
     *
     * @param stdClass $content object represent desired content.json file
     */
    public function process(stdClass $content): stdClass {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->question->contextid, 'question', 'questiontext', $this->question->id);
        $content->media = new stdClass();
        foreach ($files as $f) {
            if (
                $f->is_valid_image()
                && strpos(rawurldecode($this->question->questiontext), $f->get_filepath() . $f->get_filename())
            ) {
                $filename = $this->getname('image', $f->get_filename());
                $this->files['content/images/' . $filename] = $f;
                $imageinfo = $f->get_imageinfo();

                $content->media = (object) [
                    "type" => (object) [
                        "params" => (object) [
                            "contentName" => "Image",
                            "alt" => '',
                            "file" => (object) [
                                "path" => "images/" . $filename,
                                "mime" => $imageinfo['mimetype'],
                                "width" => $imageinfo['width'],
                                "height" => $imageinfo['height'],
                                "copyright" => (object) ["license" => "U"],
                            ],
                        ],
                        'library' => 'H5P.Image 1.1',
                        'subContentId' => $this->create_subcontentid(),
                        'metadata' => (object) [
                            'title' => '',
                            'authors' => [
                            ],
                            'source' => '',
                            'license' => 'U',
                            'contentType' => 'Image',
                        ],
                    ],
                ];
            }
        }

        $content->overallFeedback = [];
        if (!empty($this->question->options->incorrectfeedback)) {
            $content->overallFeedback[] = (object) [
                'from' => '0',
                'to' => '0',
                'feedback' => $this->question->options->incorrectfeedback,
            ];
        }
        if (!empty($this->question->options->partiallycorrectfeedback)) {
            $content->overallFeedback[] = (object) [
                'from' => '1',
                'to' => '99',
                'feedback' => $this->question->options->partiallycorrectfeedback,
            ];
        }
        if (!empty($this->question->options->correctfeedback)) {
            $content->overallFeedback[] = (object) [
                'from' => '100',
                'to' => '100',
                'feedback' => $this->question->options->correctfeedback,
            ];
        }
        return $this->process_question($content);
    }

    /**
     * Generate a character string to be used as subcontent id
     * @return string
     */
    public function create_subcontentid() {
        $string = array_map(function () {
            return substr('0123456789abcdef', rand(0, 15), 1);
        }, array_fill(0, 31, 0));
        $string = implode($string);
        return preg_replace('/(........)(....)(...)(....)/', '$1-$2-4$3-$4-', $string);
    }

    /**
     * Get new name for the current file.
     *
     * @param string $field Prefix for name
     * @param string $originalname Name to use to determine extension
     * @return string New name
     */
    public function getname(string $field, string $originalname): string {

        $name = uniqid($field . '-');

        $matches = [];
        preg_match('/([a-z0-9]{1,})$/i', $originalname, $matches);
        if (isset($matches[0])) {
            $name .= '.' . $matches[0];
        }

        return $name;
    }

    /**
     * Get the availiable contenttype library for current machine name
     *
     * @return stdClass contenttype library info
     */
    public function get_contenttype(): ?stdClass {
        // Get the H5P content types available.
        autoloader::register();
        $editorajax = new editor_ajax();
        $h5pcontenttypes = $editorajax->getLatestLibraryVersions();

        foreach ($h5pcontenttypes as $h5pcontenttype) {
            if ($h5pcontenttype->machine_name == preg_replace('/ .*/', '', $this->library)) {
                return $h5pcontenttype;
            }
        }

        return null;
    }
}
