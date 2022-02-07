# Repurpose resources conttentype plugin #

Create interactive content from existing Moodle activities by importing
media files and questions from the Moodle question bank into the content
bank as basic H5P content types.

Currently this plugin supports four basic Moodle question types: Essay,
Multiple Choice, Short Answer, and True/False.  They can be imported
individually as Essay, Multiple Choice, Guess the Answer, and True/False
H5P content types. Questions in a directory can be imported collectively
to create a Column, Crossword, Single Choice Set, Dialog Cards or Flash Cards
content types. Audio, video, and image files can also be imported into
the column content type. After creation the content types can be edited
in the content bank as normal H5P content and combined with other content
through copy and paste.

After installation, teachers may create interactive content in the content
bank by navigating to a course which has the Moodle questions to use,
clicking the _Add_ button and then choosing one of the _Repurpose resources_
content types from the drop down menu, to open the editor, and selecting questions
and other options in the editor and saving. The new content created from
the question will now appear inside the content bank.

## Installation ##

1. Install code in Moodle sub-directory _contentbank/contenttype/repurpose_
2. Go to admin settings page to complete installation
3. Go to Site administration / Plugins / Content bank / Manage content types
   and make sure Repurpose resources plugin shows in the order you would like
4. In Repurpose repurpose setting choose whether to create H5P immediately or
   also retain the data for editing.
5. Edit roles as necessary to give user permission
to access content bank and create content from Repurpose resources.
6. Install any needed content types either by running H5P schedule task
or  uploading them through the _H5P -> Manage content types_ settings.

## License ##

2020 onward Daniel Thies <dethies@gmail.com>

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the
Free Software Foundation, either version 3 of the License, or (at your
option) any later version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
for more details.

You should have received a copy of the GNU General Public License along
with this program.  If not, see <http://www.gnu.org/licenses/>.
