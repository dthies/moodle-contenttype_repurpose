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
function contenttype_repurpose_output_fragment_addfile(array $args): string {
    global $USER;

    $context = $args['contextid'];
    $library = $args['library'];

    if ($library == 'crossword') {
        $formclass = '\\contenttype_repurpose\\form\\add_background';
    } else {
        $formclass = '\\contenttype_repurpose\\form\\add_file';
    }
    $form = new $formclass('#', [
        'contextid' => $context,
        'library' => $library,
        'plugin' => 'contenttype_repurpose',
    ]);

    if ($data = json_decode($args['jsonformdata'])) {
        $fs = get_file_storage();
        if (!empty($data->mediafile)) {
            $helper = new contenttype_repurpose\local\column(context::instance_by_id($context));
            foreach ($fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $data->mediafile) as $file) {
                if (!$file->is_directory()) {
                    switch ($file->get_mimetype()) {
                        case 'audio/mp3':
                        case 'audio/m4a':
                        case 'audio/wav':
                            $media = $helper->write_audio($file, $data->title);
                            break;
                        case 'image/gif':
                        case 'image/png':
                        case 'image/jpeg':
                            $media = $helper->write_image($file, $data->title);
                            break;
                        case 'video/mp4':
                        case 'video/webm':
                        case 'video/ogg':
                        case 'audio/ogg':
                            $media = $helper->write_video($file, $data->title);
                    }
                    if (!empty($media)) {
                        $match = [];
                        if (!empty($media->params->file)) {
                            preg_match('/(.*)\\/(.*)/', $media->params->file->path, $match);
                        } else if (!empty($media->params->files)) {
                            preg_match('/(.*)\\/(.*)/', reset($media->params->files)->path, $match);
                        } else {
                            preg_match('/(.*)\\/(.*)/', reset($media->params->sources)->path, $match);
                        }
                        $fileinfo = [
                            'contextid' => context_user::instance($USER->id)->id,
                            'component' => 'user',
                            'filearea' => 'draft',
                            'filepath' => '/content/' . $match[1] . '/',
                            'filename' => $match[2],
                            'itemid' => $data->draftid,
                        ];
                        $fs->create_file_from_storedfile($fileinfo, $file);
                        return json_encode(
                            $media
                        );
                    }
                }
            }
        }
        $form->set_data($data);
    }
    return '<div>' . $form->render() . '</div>';
}

/**
 * Return form content for various content types
 *
 * @param array $args The arguments area containing form data
 * @return string HTML of form to display
 */
function contenttype_repurpose_output_fragment_formupdate(array $args): string {
    $context = $args['contextid'];
    $library = $args['library'];

    $url = new moodle_url('/contentbank/edit.php', [
        'contextid' => $context,
        'library' => $library,
        'plugin' => 'repurpose',
    ]);

    $editor = new \contenttype_repurpose\form\editor($url, [
        'contextid' => $context,
        'id' => null,
        'library' => $library,
        'plugin' => 'contenttype_repurpose',
        'update' => true,
    ]);

    if ($data = json_decode($args['jsonformdata'])) {
        $editor->set_data($data);
        return $editor->render();
    }
    return '';
}

/**
 * Serve the files from the Repurpose content type file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function contenttype_repurpose_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel > CONTEXT_COURSE) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'content' && $filearea !== 'anotherexpectedfilearea') {
        return false;
    }

    // Make sure the user is logged in and has access to the module (plugins that are not course modules
    // should leave out the 'cm' part).
    require_login($course, true);

    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability('contenttype/repurpose:useeditor', $context)) {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.

    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'contenttype_repurpose', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
