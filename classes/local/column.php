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
 * @license    http://www.gnu.org/copyleft/gpl.repurpose GNU GPL v3 or later
 */

namespace contenttype_repurpose\local;

use contenttype_h5p\content;
use contenttype_h5p\contenttype;
use core_contentbank\form\edit_content;
use core_h5p\editor as h5peditor;
use core_h5p\factory;
use core_h5p\helper;
use contenttype_repurpose\local\dialogcards;
use context_user;
use context;
use stdClass;
use moodle_exception;
use question_bank;

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
 * @license   http://www.gnu.org/copyleft/gpl.repurpose GNU GPL v3 or later
 */
class column extends dialogcards {

    /** @var $type Machine name for target type */
    public $library = 'H5P.Column 1.12';

    /**
     * Process data from an array of questions
     *
     * @param array $questions questions to process
     * @return stdClass The content file object
     */
    public function create_params(array $questions): stdClass {
        $content = json_decode('{
            "content": []
        }');

        foreach ($questions as $question) {
            $question->type = 'fillblanks';
            $content->content[] = (object) array(
                'content' => $this->write_question($question),
                'useSeparator' => 'auto',
            );
        }

        return $content;
    }

    /**
     * Modify or create an repurpose content from the form data.
     *
     * @param stdClass $data Form data to create or modify an repurpose content.
     *
     * @return int The id of the edited or created content.
     */
    public function get_content(stdClass $data): ?stdClass {
        global $DB;

        $content = parent::get_content($data);

        // Image files will be added archive later, but add metadata to content.
        $this->mediafiles = $this->mediafiles ?? [];
        foreach ($this->mediafiles as $key => $file) {
            switch (preg_replace('/\\/.*/', '', $file->path)) {
                case 'audios':
                    $content->content[] = (object) array(
                        'content' => $this->write_audio($file, $data->mediatitle[$key]),
                        'useSeparator' => 'auto',
                    );
                    break;
                case 'images':
                    $content->content[] = (object) array(
                        'content' => $this->write_image($file, $data->mediatitle[$key]),
                        'useSeparator' => 'auto',
                    );
                    break;
                case 'videos':
                    $content->content[] = (object) array(
                        'content' => $this->write_video($file, $data->mediatitle[$key]),
                        'useSeparator' => 'auto',
                    );
                    break;
            }

        }

        return $content;
    }

    /**
     * Turns question into an object structure for h5p content
     *
     * @param stdClass $question the question data.
     * @return stdClass data to add to content file object
     */
    public function write_question(stdClass $question): ?stdClass {
        global $CFG, $OUTPUT;

        if (!$writer = $this->get_writer($question)) {
            return null;
        }

        $content = new stdClass();
        $content = (object) array(
            'params' => $writer->process($content),
            'subContentId' => $writer->create_subcontentid(),
            'library' => $writer->library,
            'metadata' => (object) array(
                'license' => 'U',
                'authors' => [],
                'changes' => [],
                'title' => $question->name,
                'extraTitle' => $question->name,
            ),
        );

        if (!empty($writer->files)) {
            if (empty($this->files)) {
                $this->files = array();
            }
            $this->files = $this->files + $writer->files;
        }

        return $content;
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        global $DB, $USER;

        $errors = array();
        $category = $DB->get_record('question_categories', array(
            'id' => preg_replace('/,.*/', '', $data['category']),
        ));

        $questions = get_questions_category($category, !empty($data->recurse));
        if (empty(array_filter($questions, function($question) use ($category, $data) {
            return empty($question->parent)
                && $this->get_writer($question)
                && (key_exists('recurse', $data) || $question->category == $category->id);
        }))) {
            $fs = get_file_storage();
            $context = context_user::instance($USER->id);
            $count = 0;
            foreach ($data['mediafile'] as $draftid) {
                $count += count($fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false));
            }
            if (empty($count)) {
                $errors['category'] = get_string('noquestionsselected', 'contenttype_repurpose');
            }
        }

        return $errors;
    }

    /**
     * Defines the additional form fields.
     *
     * @param moodle_form $mform form to modify
     */
    public function add_form_fields($mform) {
        parent::add_form_fields($mform);

        $mform->removeElement('description');

        $mform->addElement('header', 'mediafiles', get_string('mediafiles', 'contenttype_repurpose', '{no}'));
        $mform->setExpanded('mediafiles', false);

        $groupelements[] = $mform->createElement(
            'filepicker',
            'mediafile',
            get_string('mediafile', 'contenttype_repurpose', '{no}'),
            null,
            array(
                'maxbytes' => 0,
                'accepted_types' => array(
                    'gif',
                    'png',
                    'jpg',
                    'mp4',
                    'webm',
                )
            )
        );
        $groupelements[] = $mform->createElement(
            'text',
            'mediatitle',
            get_string('mediatitle', 'contenttype_repurpose'),
            array(
                'size' => 20,
            )
        );

        $this->repeatelements[] = $mform->createElement(
            'group',
            'mediagroup',
            get_string('mediafile', 'contenttype_repurpose', '{no}'),
            $groupelements,
            get_string('mediatitle', 'contenttype_repurpose') . ' ',
            false
        );

        $this->repeatoptions = array(
            'mediatitle' => array(
                'type' => PARAM_TEXT,
            ),
        );
    }

    /**
     * Return helper to write question
     *
     * @param question $question question
     * @return stdClass
     */
    public function get_writer($question) {
        $question->contextid = $this->context->id;
        if (empty($question->parent)) {
            foreach (array('repurposeplus', 'repurpose') as $plugin) {
                $writerclass = "contenttype_$plugin\\local\\qtype_" . $question->qtype;

                if (class_exists($writerclass) && empty($question->parent)) {
                    return new $writerclass($question);
                }
            }
        }
        return null;
    }

    /**
     * Process files attachded to form.
     *
     * @param moodle_form $form form that is submitted
     * @return stdClass
     */
    public function process_files($form): void {
        global $USER;

        $data = $form->get_data();
        $fs = get_file_storage();
        $context = context_user::instance($USER->id);
        foreach ($data->mediafile ?? array() as $key => $draftid) {
            if (
                ($files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false))
                && $file = reset($files)
            ) {
                switch ($file->get_mimetype()) {
                    case 'audio/mp3':
                    case 'audio/m4a':
                    case 'audio/wav':
                        $filename = $this->getname('source', $file->get_filename());
                        $this->files['content/audios/' . $filename] = $file;
                        $this->mediafiles[$key] = (object) array(
                            'license' => 'U',
                            'path' => 'audios/' . $filename,
                            'mime' => $file->get_mimetype(),
                        );
                        break;
                    case 'image/gif':
                    case 'image/png':
                    case 'image/jpeg':
                        $filename = $this->getname('image', $file->get_filename());
                        $this->files['content/images/' . $filename] = $file;
                        $imageinfo = $file->get_imageinfo();
                        $this->mediafiles[$key] = (object) array(
                            'license' => 'U',
                            'path' => 'images/' . $filename,
                            'height' => $imageinfo['height'],
                            'width' => $imageinfo['width'],
                            'mime' => $imageinfo['mimetype'],
                        );
                        break;
                    case 'video/mp4':
                    case 'video/webm':
                    case 'video/ogg':
                    case 'audio/ogg':
                        $filename = $this->getname('source', $file->get_filename());
                        $this->files['content/videos/' . $filename] = $file;
                        $this->mediafiles[$key] = (object) array(
                            'license' => 'U',
                            'path' => 'videos/' . $filename,
                            'mime' => $file->get_mimetype(),
                        );
                        break;
                }
            }
        }
    }

    /**
     * Write audio subcontent
     *
     * @param stdClass $file audio file
     * @param string $title audio title
     * @return stdClass
     */
    public function write_audio(stdClass $file, string $title): stdClass {
        $content = json_decode('{
            "params": {
              "playerMode": "minimalistic",
              "fitToWrapper": false,
              "controls": true,
              "autoplay": false,
              "playAudio": "Play audio",
              "pauseAudio": "Pause audio",
              "contentName": "Audio",
              "audioNotSupported": "Your browser does not support this audio",
              "files": []
            },
            "library": "H5P.Audio 1.4",
            "metadata": {
              "contentType": "Audio",
              "license": "U",
              "title": "Untitled Audio"
            }
        }');
        $content->params->files = array($file);
        $content->metadata->title = $title ?? 'audio';
        $content->subContentId = $this->create_subcontentid();

        return $content;
    }

    /**
     * Write image subcontent
     *
     * @param stdClass $file image file
     * @param string $title image title
     * @return stdClass
     */
    public function write_image(stdClass $file, string $title): stdClass {
        $content = json_decode('{
            "params": {
              "contentName": "Image",
              "file": {
              },
              "alt": "logo"
            },
            "library": "H5P.Image 1.1",
            "metadata": {
              "contentType": "Image",
              "license": "U",
              "title": "Untitled Image"
            }
        }');
        $content->params->file = $file;
        $content->params->alt = $title ?? 'image';
        $content->metadata->title = $title ?? 'image';
        $content->subContentId = $this->create_subcontentid();

        return $content;
    }

    /**
     * Write video subcontent
     *
     * @param stdClass $file video file
     * @param string $title video title
     * @return stdClass
     */
    public function write_video(stdClass $file, string $title): stdClass {
        $content = json_decode('{
            "params": {
              "visuals": { "fit": true, "controls": true },
              "playback": { "autoplay": false, "loop": false },
              "sources": []
            },
            "library": "H5P.Video 1.5",
            "metadata": {
              "contentType": "Video",
              "license": "U",
              "title": "Untitled Video"
            }
        }');
        $content->params->sources = array($file);
        $content->metadata->title = $title ?? 'video';
        $content->subContentId = $this->create_subcontentid();

        return $content;
    }
}
