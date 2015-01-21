<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 * 
 * Entrada is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Entrada is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Entrada.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Entrada upgrade helper.
 * 
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Sam Payne <spayne@mednet.ucla.edu>
 * @copyright Copyright 2014 David Geffen School fo Medicine at UCLA
 * 
*/

@set_time_limit(0);
@set_include_path(implode(PATH_SEPARATOR, array(
    dirname(__FILE__) . "/../../../core",
    dirname(__FILE__) . "/../../../core/includes",
    dirname(__FILE__) . "/../../../core/library",
    get_include_path(),
)));

require_once("config/config.inc.php");
require_once "Zend/Loader/Autoloader.php";
$loader = Zend_Loader_Autoloader::getInstance();
require_once("config/settings.inc.php");
require_once("Entrada/adodb/adodb.inc.php");
require_once("functions.inc.php");
require_once("dbconnection.inc.php");

if((!isset($_SERVER["argv"])) || (@count($_SERVER["argv"]) < 1)) {
	echo "<html>\n";
	echo "<head>\n";
	echo "	<title>Processing Error</title>\n";
	echo "</head>\n";
	echo "<body>\n";
	echo "This file should be run by command line only:";
	echo "<div style=\"font-family: monospace\">/usr/bin/php -f ".__FILE__."</div>";
	echo "</body>\n";
	echo "</html>\n";
	exit;
}

echo "\n\n";

$query = "UPDATE `evaluations` AS a SET a.`organisation_id` = (SELECT `organisation_id` FROM `" . AUTH_DATABASE . "`.`user_data` WHERE `id` = a.`updated_by`)";
if ($db->Execute($query)) {
	echo "Successfully updated evaluations.\n";
} else {
	echo "Error while updated evaluations.\n";
}

$query = "UPDATE `evaluation_forms` AS a SET a.`organisation_id` = (SELECT `organisation_id` FROM `" . AUTH_DATABASE . "`.`user_data` WHERE `id` = a.`updated_by`)";
if ($db->Execute($query)) {
	echo "Successfully updated evaluations forms.\n";
} else {
	echo "Error while updated evaluations forms.\n";
}

$query = "UPDATE `evaluations_lu_questions` AS a 
        JOIN `evaluation_form_questions` AS b
        ON a.`equestion_id` = b.`equestion_id`
        JOIN `evaluation_forms` AS c
        ON b.`eform_id` = c.`eform_id`
        SET a.`organisation_id` = (SELECT `organisation_id` FROM `" . AUTH_DATABASE . "`.`user_data` WHERE `id` = c.`updated_by`)";
if ($db->Execute($query)) {
	echo "Successfully updated evaluations_lu_questions.\n";
} else {
	echo "Error while updated evaluations_lu_questions.\n";
}

echo "\n\n";