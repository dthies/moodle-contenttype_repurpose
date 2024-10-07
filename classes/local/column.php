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
use moodle_url;
use question_bank;
use stored_file;

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
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            $content->content[] = (object) [
                'content' => $this->write_question($question),
                'useSeparator' => 'auto',
            ];
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

        $context = context::instance_by_id($data->contextid, MUST_EXIST);

        $category = $DB->get_record('question_categories', ['id' => preg_replace('/,.*/', '', $data->category)]);

        $questions = get_questions_category($category, !empty($data->recurse));

        if (empty($data->recurse)) {
            foreach ($questions as $index => $question) {
                if ($question->category != $category->id) {
                    unset($questions[$index]);
                }
            }
        }

        $content = $this->create_params($questions);
        if (!empty($data->description)) {
            $content->description = $data->description;
        }

        // Image files will be added archive later, but add metadata to content.
        $this->mediafiles = $this->mediafiles ?? [];
        $this->mediafiles = array_merge(json_decode($data->mediafiles) ?? [], $this->mediafiles);
        foreach ($this->mediafiles as $key => $mediafile) {
            $content->content[] = (object) [
                'content' => $mediafile,
                'useSeparator' => 'auto',
            ];
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

        $errors = [];
        $category = $DB->get_record('question_categories', [
            'id' => preg_replace('/,.*/', '', $data['category']),
        ]);

        $questions = get_questions_category($category, !empty($data->recurse));
        if (
            empty(array_filter($questions, function ($question) use ($category, $data) {
                return empty($question->parent)
                && $this->get_writer($question)
                && (key_exists('recurse', $data) || $question->category == $category->id);
            }))
        ) {
            $fs = get_file_storage();
            $context = context_user::instance($USER->id);
            $count = 0;
            foreach ($fs->get_area_files($context->id, 'user', 'draft', $data['draftid'], 'id DESC', false) as $file) {
                if (key_exists(substr($file->get_filepath() . $file->get_filename(), 1), $this->files)) {
                    $count++;
                }
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
        global $OUTPUT, $PAGE;

        parent::add_form_fields($mform);
        $mform->removeElement('description');

        $mform->addElement('header', 'mediahdr', get_string('mediafiles', 'contenttype_repurpose', '{no}'));
        $mform->addElement('hidden', 'mediafiles');
        $mform->addElement('button', 'addfile', get_string('addfile', 'contenttype_repurpose'));
        $mform->addHelpButton('addfile', 'addfile', 'contenttype_repurpose');
        $mform->setType('mediafiles', PARAM_RAW);
        $mform->setDefault('mediafiles', '[]');
        $mform->addElement('hidden', 'draftid', 0);
        $mform->setType('draftid', PARAM_INT);
    }

    /**
     * Write audio subcontent
     *
     * @param stored_file $file audio file
     * @param string $title audio title
     * @return stdClass
     */
    public function write_audio(stored_file $file, string $title): stdClass {
        $filename = $this->getname('source', $file->get_filename());
        $this->files['content/audios/' . $filename] = $file;
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
        $content->params->files = [
            (object) [
                'license' => 'U',
                'path' => 'audios/' . $filename,
                'mime' => $file->get_mimetype(),
            ],
        ];
        $content->metadata->title = $title ?? 'audio';
        $content->subContentId = $this->create_subcontentid();

        return $content;
    }

    /**
     * Write image subcontent
     *
     * @param stored_file $file image file
     * @param string|null $title image title
     * @return stdClass
     */
    public function write_image(stored_file $file, ?string $title): stdClass {
        $filename = $this->getname('image', $file->get_filename());
        $this->files['content/images/' . $filename] = $file;
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
        $imageinfo = $file->get_imageinfo();
        $content->params->file = (object) [
            'license' => 'U',
            'path' => 'images/' . $filename,
            'height' => $imageinfo['height'],
            'width' => $imageinfo['width'],
            'mime' => $imageinfo['mimetype'],
            'title' => $title ?: $file->get_filename(),
        ];
        $content->params->alt = $title ?: 'image';
        $content->metadata->title = $title ?: 'image';
        $content->subContentId = $this->create_subcontentid();

        return $content;
    }

    /**
     * Write video subcontent
     *
     * @param stored_file $file video file
     * @param string $title video title
     * @return stdClass
     */
    public function write_video(stored_file $file, string $title): stdClass {
        $filename = $this->getname('video', $file->get_filename());
        $this->files['content/videos/' . $filename] = $file;
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
        $content->params->sources = [
            (object) [
                'license' => 'U',
                'path' => 'videos/' . $filename,
                'mime' => $file->get_mimetype(),
                'title' => $filename,
            ],
        ];
        $content->metadata->title = $title ?? 'video';
        $content->subContentId = $this->create_subcontentid();

        return $content;
    }

    /**
     * Change definitiion after supplied data.
     *
     * @param moodle_form $mform form with data to be modified
     * @return void
     */
    public function definition_after_data($mform) {
        global $DB, $OUTPUT, $PAGE, $USER;

        // If there is an existing image display it.
        if (
            !$mform->getElementValue('draftid' ?? 0)
            && ($id = $mform->getElementValue('id'))
            && $record = $DB->get_record('contentbank_content', ['id' => $id])
        ) {
            $content = new \contenttype_repurpose\content($record);
            $configdata = json_decode($content->get_configdata());
            if (
                !empty($configdata)
                && !empty($configdata->mediafiles)
            ) {
                    $mediafiles = json_decode($configdata->mediafiles);
                    $this->set_data($mform, $configdata);
                    $mform->setDefault('mediafiles', $configdata->mediafiles);
            }
        } else {
            $mediafiles = json_decode($mform->getElementValue('mediafiles'));
        }

        if (
            (!$draftid = $mform->getElementValue('draftid') ?? 0)
            || optional_param('library', '', PARAM_TEXT)
        ) {
            $PAGE->requires->js_call_amd('contenttype_repurpose/editcontent', 'init', [$this->context->id, 'column']);
        }

        $fs = get_file_storage();
        if ($draftid = ($mform->getElementValue('draftid') ?? 0)) {
            foreach ($mediafiles as $mediafile) {
                switch ($mediafile->metadata->contentType) {
                    case 'Audio':
                        $path = 'content/' . $mediafile->params->files[0]->path;
                        break;
                    case 'Image':
                        $path = 'content/' . $mediafile->params->file->path;
                        break;
                    case 'Video':
                        $path = 'content/' . $mediafile->params->sources[0]->path;
                        break;
                }
                $pathname = '/' . context_user::instance($USER->id)->id . '/user/draft/' .  $draftid . '/' . $path;
                if ($file = $fs->get_file_by_hash(sha1($pathname))) {
                    $this->files[$path] = $file;
                }
            }
        }
        if (count($mediafiles)) {
            reset($mediafiles)->first = true;
            end($mediafiles)->last = true;
        }
        foreach ($mediafiles as $mediafile) {
            switch ($mediafile->metadata->contentType) {
                case 'Audio':
                    $mediafile->audio = true;
                    break;
                case 'Image':
                    $mediafile->image = true;
                    break;
                case 'Video':
                    $mediafile->video = true;
                    break;
            }
        }
        $mform->insertElementBefore(
            $mform->createElement('static', 'contenteditor', '', file_prepare_draft_area(
                $draftid,
                $this->context->id,
                'contenttype_repurpose',
                'content',
                $id ?? null,
                ['subdirs' => true],
                $OUTPUT->render_from_template('contenttype_repurpose/media', ['media' => $mediafiles])
            )),
            'mediafiles'
        );
        $mform->setDefault('draftid', $draftid);
    }
}
