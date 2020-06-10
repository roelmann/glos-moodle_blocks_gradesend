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

require_once(dirname(__FILE__) . '/../../../config.php');

if (isset($_POST["assmentcode"])) {
    $assmentcode = $_POST["assmentcode"];

    require_once("$CFG->dirroot/local/extdb/classes/task/extdb.php");

    // Fetch settings for external DB plugin.
    $externaldb = new \local_extdb\extdb();
    $name = $externaldb->get_name();

    $externaldbtype = $externaldb->get_config('dbtype');
    $externaldbhost = $externaldb->get_config('dbhost');
    $externaldbname = $externaldb->get_config('dbname');
    $externaldbencoding = $externaldb->get_config('dbencoding');
    $externaldbsetupsql = $externaldb->get_config('dbsetupsql');
    $externaldbsybasequoting = $externaldb->get_config('dbsybasequoting');
    $externaldbdebugdb = $externaldb->get_config('dbdebugdb');
    $externaldbuser = $externaldb->get_config('dbuser');
    $externaldbpassword = $externaldb->get_config('dbpass');
    $tablegrades = get_string('stuassesstable', 'block_gradesend');

    // Database connection and setup checks.
    // Check connection and label Db/Table in cron output for debugging if required.
    if (!$externaldbtype) {
        echo 'Database not defined.<br>';
        return 0;
    }
    // Check remote student grades table - usr_data_student_assessments.
    if (!$tablegrades) {
        echo 'Student Grades Table not defined.<br>';
        return 0;
    }

    // Report connection error if occurs.
    if (!$extdb = $externaldb->db_init(
        $externaldbtype,
        $externaldbhost,
        $externaldbuser,
        $externaldbpassword,
        $externaldbname)) {
            echo 'Error while communicating with external database <br>';
            return 1;
    }

    $stuassess = $sa = array(); // Maintain copy as per Integrations Db for writing back.
    // Read assessment data from external table and create $stuassess array.
    $sql = $externaldb->db_get_sql($tablegrades, array('assessment_idcode' => $assmentcode), array(), true);
    if ($rs = $extdb->Execute($sql)) {
        if (!$rs->EOF) {
            while ($fields = $rs->FetchRow()) {
                $fields = array_change_key_case($fields, CASE_LOWER);
                $fields = $externaldb->db_decode($fields);
                $stuassess[] = $fields;
            }
        }
        $rs->Close();
    } else {
        // Report error if required.
        $extdb->Close();
        echo 'Error reading data from the external course table<br>';
        return 4;
    }

    echo '<h3>Quick check of grades already submitted</h3>';
    echo '<p>Please click the Back button on your browser to return to the previous page</p>';
    echo '<br>';
    echo '<table>';
    echo '<tr>';
    echo '<th>Student ID</th>';
    echo '<th>Assessment ID</th>';
    echo '<th>Extension</th>';
    echo '<th>Feedback Due</th>';
    echo '<th>Submitted</th>';
    echo '<th>Mark</th>';
    echo '<th>Grade/Flag</th>';
    echo '<th>Feedback added</th>';
    echo '</tr>';
    foreach ($stuassess as $sa) {
        echo '<tr>';
        echo '<td>'.$sa['student_code'].'</td>';
        echo '<td>'.$sa['assessment_idcode'].'</td>';
        echo '<td>'.$sa['student_ext_duedate'].'</td>';
        echo '<td>'.$sa['student_fbdue_date'].'</td>';
        echo '<td>'.$sa['received_date'].':'.$sa['received_time'].'</td>';
        echo '<td>'.$sa['actual_mark'].'</td>';
        echo '<td>'.$sa['actual_grade'].'</td>';
        echo '<td>'.$sa['student_fbset_date'].'</td>';
        echo '</tr>';
    }
    echo '</table>';

} else {
    echo '<h4>No assessment ID code provided</h4>';
}
