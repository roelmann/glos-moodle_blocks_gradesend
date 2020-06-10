<?php
// This file is part of The Bootstrap 3 Moodle theme
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
 * GradeSend view grades on integration.
 *
 * @package    block_gradesend
 * @author     2019 Richard Oelmann
 * @copyright  2019 R. Oelmann

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Ref: http://docs.moodle.org/dev/Page_API.

$string['pluginname'] = 'Grade Send';

$string['blocktitle'] = 'Grade Send block';
$string['blockcontent'] = 'block to manually send grades to Integrations table for sending to SITS';

$string['stuassesstable'] = 'usr_data_student_assessments';

$string['instructions'] = 'When grades are ready to send to Student Records, please tick to confirm and click the send grades button.<br>Grades can be resent to overwrite previously sent grades where necessary (Late second marking, etc).<br>If an SB flag is entered, the button should also be clicked to send this immediately, subsequent grades for the cohort can be sent when completed.<br>Note: Grades are sent via several intermediate systems and may take some time to appear in SITS.';
$string['confirm'] = ' Confirm grade send.';
$string['send'] = 'Send Grades';
$string['view'] = 'View Integrations table';
