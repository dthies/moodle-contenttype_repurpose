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
 * repurpose Content manager class
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_repurpose;

use stdClass;
use stored_file;

/**
 * repurpose Content manager class.
 *
 * This class represents individual content. The main content_contenbank\content
 * class has all the basic methods to read and write content sotred in the
 * content bank tables. Nvertheless, some methods has to be overridden in scenarios
 * where content need extra tables or remote contents.
 *
 * In this case, we will use the standard content bank tables so for now we don't need
 * to override any method.
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends \core_contentbank\content {
    /**
     * Save the public file
     *
     * @param stored_file $file File to make publice
     * @return stored_file|null
     */
    public function save_public(stored_file $file): ?stored_file {
        return parent::import_file($file);
    }
}
