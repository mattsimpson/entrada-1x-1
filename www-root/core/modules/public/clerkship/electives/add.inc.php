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
 * Allows students to add electives to the system which still need to be approved.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Andrew Dos-Santos <andrew.dos-santos@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/

if ((!defined("PARENT_INCLUDED")) || (!defined("IN_CLERKSHIP"))) {
	exit;
} elseif ((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif (!$ENTRADA_ACL->amIAllowed('electives', 'read')) {
	$ONLOAD[]	= "setTimeout('window.location=\\'".ENTRADA_URL."/".$MODULE."\\'', 15000)";

	$ERROR++;
	$ERRORSTR[]	= "Your account does not have the permissions required to use this feature of this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.";

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"]."] and role [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["role"]."] do not have access to this module [".$MODULE."]");
} else {
	$BREADCRUMB[]	= array("url" => ENTRADA_URL."/public/clerkship/electives?section=add", "title" => "Adding Elective");

	echo "<h1>Adding Elective</h1>\n";
	
	// Error Checking
	switch ($STEP) {
		case 2 :
			/**
			 * Required field "geo_location" / Geographic Location.
			 */
			if ((isset($_POST["geo_location"])) && ($geo_location = clean_input($_POST["geo_location"], array("notags", "trim")))) {
				$PROCESSED["geo_location"] = $geo_location;
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Geographic Location</strong> field is required.";
			}
			/**
			 * Required field "category_id" / Elective Period.
			 */
			if ((isset($_POST["category_id_name"])) && ($category_id = clean_input($_POST["category_id_name"], "int"))) {
				$PROCESSED["category_id"] = $category_id;
				if ($_POST["category_id_name"] == 0) {
					$ERROR++;
					$ERRORSTR[] = "The <strong>Elective Period</strong> field is required.";
				}
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Elective Period</strong> field is required.";
			}

			/**
			 * Required field "department_id" / Department.
			 */
			if ((isset($_POST["department_id"])) && ($department_id = clean_input($_POST["department_id"], "int"))) {
				$PROCESSED["department_id"] = $department_id;
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Department</strong> field is required.";
			}

			/**
			 * Required field "discipline" / Discipline .
			 */
			if ((isset($_POST["discipline_id"])) && ($discipline_id = clean_input($_POST["discipline_id"], "int"))) {
				$PROCESSED["discipline_id"] = $discipline_id;
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Discipline</strong> field is required.";
			}

			/**
			 * Non-required field "sub_discipline" / Sub-Discipline .
			 */
			if ((isset($_POST["sub_discipline"])) && ($sub_discipline = clean_input($_POST["sub_discipline"], array("notags", "trim")))) {
				$PROCESSED["sub_discipline"] = $sub_discipline;
			}
			
			/**
			 * Required field "start_date" / Start Date .
			 */
			if ((isset($_POST["start_date"])) && $_POST["start_date"] > date("Y-m-d") && $_POST["start_date"] != "") {
				$startCleaned	= html_encode($_POST["start_date"]);
				$explodedDate 	= explode("-", $startCleaned);
				$year 			= $explodedDate[0];
				$month 			= $explodedDate[1];
				$day 			= $explodedDate[2];
				$start_stamp 	= mktime(9,0,0,$month,$day,$year);
				
				$PROCESSED["start_date"] 	= $start_stamp;
				$PROCESSED["end_date"] 		= strtotime($startCleaned . "+". clean_input($_POST["event_finish_name"], array("int")) . " weeks");
				$end_stamp 					= $PROCESSED["end_date"];
				
				$dateCheckQuery = "SELECT `event_title`, `event_start`, `event_finish`   
				FROM `".CLERKSHIP_DATABASE."`.`events`, `".CLERKSHIP_DATABASE."`.`electives`, `".CLERKSHIP_DATABASE."`.`event_contacts`
				WHERE `".CLERKSHIP_DATABASE."`.`events`.`event_id` = `".CLERKSHIP_DATABASE."`.`electives`.`event_id`
				AND `".CLERKSHIP_DATABASE."`.`events`.`event_id` = `".CLERKSHIP_DATABASE."`.`event_contacts`.`event_id`
				AND `".CLERKSHIP_DATABASE."`.`event_contacts`.`etype_id` = ".$db->qstr($_SESSION["details"]["id"])." 
				AND `".CLERKSHIP_DATABASE."`.`events`.`event_type` = \"elective\"
				AND `".CLERKSHIP_DATABASE."`.`events`.`event_status` != \"trash\"
				AND ((".$db->qstr($start_stamp)." > `".CLERKSHIP_DATABASE."`.`events`.`event_start` 
				AND ".$db->qstr($start_stamp)." < `".CLERKSHIP_DATABASE."`.`events`.`event_finish`)
				OR (".$db->qstr($end_stamp)." > `".CLERKSHIP_DATABASE."`.`events`.`event_start` 
				AND ".$db->qstr($end_stamp)." < `".CLERKSHIP_DATABASE."`.`events`.`event_finish`)
				OR (`".CLERKSHIP_DATABASE."`.`events`.`event_start` > ".$db->qstr($start_stamp)." 
				AND `".CLERKSHIP_DATABASE."`.`events`.`event_finish` < ".$db->qstr($end_stamp)."))";
				
				if ($dateCheck	= $db->GetAll($dateCheckQuery))  {
					$dateErrorCtr = 0;
					foreach ($dateCheck as $dateValue) {
						$dateErrorCtr++;
						$dateError .= "<br /><tt>" . $dateValue["event_title"] . "<br />  *  Starts: ". date("Y-m-d", $dateValue["event_start"]) . "<br />  * Finishes: " . date("Y-m-d", $dateValue["event_finish"])."</tt><br />";
					}
					$ERROR++;
					if ($dateErrorCtr == 1) {
						$ERRORSTR[] = "This elective conflicts with the following elective:<br />".$dateError;
					}  else {
						$ERRORSTR[] = "This elective conflicts with the following electives:<br />".$dateError;
					}
				} else {
					$weekTotals = clerkship_get_elective_weeks($_SESSION["details"]["id"]);
					$totalWeeks = $weekTotals["approval"] + $weekTotals["approved"];
					
					if ($totalWeeks + clean_input($_POST["event_finish_name"], array("int")) > $CLERKSHIP_REQUIRED_WEEKS) {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Weeks</strong> field contains too large a number as this combined with the other electives you have in the system
						(both approved and awaiting approval) exceeds the maximum number of weeks allowed (".$CLERKSHIP_REQUIRED_WEEKS."). Please use the Page Feedback link to 
						contact the undergraduate office if you need help resolving this issue.";
					}
				}
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Start Date</strong> field is required and must be greater than today.";
				
				if (isset($_POST["start_date"]) && $_POST["start_date"] != '') {
					$startCleaned	= html_encode($_POST["start_date"]);
					$explodedDate 	= explode("-", $startCleaned);
					$year 			= $explodedDate[0];
					$month 			= $explodedDate[1];
					$day 			= $explodedDate[2];
					$start_stamp 	= mktime(0,0,0,$month,$day,$year);
					
					$PROCESSED["start_date"] = $start_stamp;
					$PROCESSED["end_date"] = strtotime($startCleaned . "+". clean_input($_POST["event_finish_name"], array("int")) . " weeks");
				}
			}
			
			/**
			 * Required field "schools_id" / Host School.
			 */
			if ((isset($_POST["schools_id"])) && ($medical_school = clean_input($_POST["schools_id"], array("int")))) {
				$PROCESSED["schools_id"] = $medical_school;
				if ($medical_school == "99999") {
					if ((isset($_POST["other_medical_school"])) && ($other = clean_input($_POST["other_medical_school"], array("notags", "trim")))) {
						$PROCESSED["other_medical_school"] = $other;
					} else {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Other</strong> field is required.";
					}
				} else {
					$PROCESSED["other_medical_school"] = "";
				}
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Host School</strong> field is required.";
			}
			
			/**
			 * Required field "objective" / Objective.
			 */
			if ((isset($_POST["objective"])) && ($objective = clean_input($_POST["objective"], array("notags", "trim")))) {
				$PROCESSED["objective"] = $objective;
				if (strlen($objective) > 300)
				{
					$ERROR++;
					$ERRORSTR[] = "<strong>Objective</strong> can only contain a maximum of 300 characters.";
				}
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Objective</strong> field is required.";
			}
			
			/**
			 * Non-required field "preceptor_first_name" / Preceptor First Name.
			 */
			if ((isset($_POST["preceptor_first_name"])) && ($preceptor_first_name = clean_input($_POST["preceptor_first_name"], array("notags", "trim")))) {
				$PROCESSED["preceptor_first_name"] = $preceptor_first_name;
			}
			
			/**
			 * Required field "preceptor_last_name" / Preceptor Last Name.
			 */
			if ((isset($_POST["preceptor_last_name"])) && ($preceptor_last_name = clean_input($_POST["preceptor_last_name"], array("notags", "trim")))) {
				$PROCESSED["preceptor_last_name"] = $preceptor_last_name;
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Preceptor Last Name</strong> field is required.";
			}
			
			/**
			 * Required field "address" / Address.
			 */
			if ((isset($_POST["address"])) && ($address = clean_input($_POST["address"], array("notags", "trim")))) {
				$PROCESSED["address"] = $address;
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Address</strong> field is required.";
			}
			
			/**
			 * Required field "countries_id" / Country.
			 */
			if ((isset($_POST["countries_id"])) && ($countries_id = clean_input($_POST["countries_id"], "int"))) {
				$PROCESSED["countries_id"] = $countries_id;
				//Province is required if the `countries_id` has provinces related to it in the database.
				$query = "	SELECT count(`province`) FROM `global_lu_provinces`
							WHERE `country_id` = ".$db->qstr($PROCESSED["countries_id"])."
							GROUP BY `country_id`";
				$province_required = ($db->GetOne($query) ? true : false);
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Country</strong> field is required.";
				$province_required = false;
			}
			
			/**
			 * Required field "prov_state" / Prov / State.
			 */
			if ((isset($_POST["prov_state"])) && ($prov_state = clean_input($_POST["prov_state"], array("notags", "trim")))) {
				$PROCESSED["prov_state"] = htmlentities($prov_state);
				if (strlen($prov_state) > 100)
				{
					$ERROR++;
					$ERRORSTR[] = "The <strong>Prov / State</strong> can only contain a maximum of 100 characters.";
				}
			} elseif($province_required) {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Prov / State</strong> field is required.";
			}
			
			/**
			 * Required field "city" / City.
			 */
			if ((isset($_POST["city"])) && ($city = clean_input($_POST["city"], array("notags", "trim")))) {
				$PROCESSED["city"] = $city;
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>City</strong> field is required.";
			}
			
			/**
			 * Non-required field "postal_zip_code" / Postal / Zip Code.
			 */
			if ((isset($_POST["postal_zip_code"])) && ($postal_zip_code = clean_input($_POST["postal_zip_code"], array("notags", "trim")))) {
				$PROCESSED["postal_zip_code"] = strtoupper(str_replace(" ", "", $postal_zip_code));
			}
			
			/**
			 * Non-required field "fax" / Fax.
			 */
			if ((isset($_POST["fax"])) && ($fax = clean_input($_POST["fax"], array("notags", "trim")))) {
				$PROCESSED["fax"] = $fax;
			}
			
			/**
			 * Non-required field "phone" / Phone.
			 */
			if ((isset($_POST["phone"])) && ($phone = clean_input($_POST["phone"], array("notags", "trim")))) {
				$PROCESSED["phone"] = $phone;
			}
			
			/**
			 * Required field "email" /  Email.
			 */
			if ((isset($_POST["email"])) && ($email = clean_input($_POST["email"], array("notags", "trim", "emailcontent")))) {
				$PROCESSED["email"] = $email;
				if (!valid_address($email)) {
					$ERROR++;
					$ERRORSTR[] = "The <strong>Email</strong> you provided is not valid.";
				}
			} else {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Email</strong> field is required.";
			}
			
			if (!$ERROR) {
				$PROCESSED["updated_date"]			= time();
				$PROCESSED["updated_by"]			= $_SESSION["details"]["id"];
				
				$EVENT["category_id"]				= $PROCESSED["category_id"];
				$query = "	SELECT `region_id` FROM `".CLERKSHIP_LOGBOOK."`.`regions`
							WHERE `countries_id` = ".$db->qstr($PROCESSED["countries_id"])."
							AND `prov_state` = ".$db->qstr($PROCESSED["prov_state"])."
							AND `region_name` LIKE ".$db->qstr($PROCESSED["city"]);
				$region_id = $db->GetOne($query);
				
				if ($region_id) {
					$ELECTIVE["region_id"] = clean_input($region_id, "int");
					$EVENT["region_id"] = clean_input($region_id, "int");
				} else {
					$REGION = array();
					$REGION["countries_id"] = $PROCESSED["countries_id"];
					$REGION["prov_state"] = $PROCESSED["prov_state"];
					$REGION["region_name"] = $PROCESSED["city"];
					if ($db->AutoExecute(CLERKSHIP_DATABASE.".regions", $REGION, "INSERT") && ( $region_id = $db->Insert_Id())) {
						$ELECTIVE["region_id"] = clean_input($region_id, "int");
						$EVENT["region_id"] = clean_input($region_id, "int");
					} else {
						$ERROR++;
						$ERRORSTR[] = "A region could not be added to the system for this elective. The system administrator was informed of this error; please try again later.";

						application_log("error", "There was an error inserting a new region for a newly created elective. Database said: ".$db->ErrorMsg());
						$ELECTIVE["region_id"] = 0;
						$EVENT["region_id"] = 0;
					}
				}
				$EVENT["event_title"]				= clerkship_categories_title($PROCESSED["department_id"], $levels = 3);
				$EVENT["event_start"]				= $PROCESSED["start_date"];
				$EVENT["event_finish"]				= $PROCESSED["end_date"];
				$EVENT["event_type"]				= "elective";
				$EVENT["event_status"]				= "approval";
				$EVENT["modified_last"]				= $PROCESSED["updated_date"];
				$EVENT["modified_by"]				= $PROCESSED["updated_by"];
				
				if ($db->AutoExecute(CLERKSHIP_DATABASE.".events", $EVENT, "INSERT")) {
					if ($EVENT_ID = $db->Insert_Id()) {
						$url = ENTRADA_URL."/".$MODULE;
						
						$CONTACTS["event_id"]				= $EVENT_ID;
						$CONTACTS["econtact_type"]			= "student";
						$CONTACTS["etype_id"]				= $_SESSION["details"]["id"];
						
						if ($db->AutoExecute(CLERKSHIP_DATABASE.".event_contacts", $CONTACTS, "INSERT")) {
							
							$ELECTIVE["event_id"]				= $EVENT_ID;
							$ELECTIVE["geo_location"]			= $PROCESSED["geo_location"];
							$ELECTIVE["department_id"]			= $PROCESSED["department_id"];
							$ELECTIVE["discipline_id"]			= $PROCESSED["discipline_id"];
							$ELECTIVE["sub_discipline"]			= $PROCESSED["sub_discipline"];
							$ELECTIVE["schools_id"]				= $PROCESSED["schools_id"];
							$ELECTIVE["other_medical_school"]	= $PROCESSED["other_medical_school"];
							$ELECTIVE["objective"]				= $PROCESSED["objective"];
							$ELECTIVE["preceptor_first_name"]	= $PROCESSED["preceptor_first_name"];
							$ELECTIVE["preceptor_last_name"]	= $PROCESSED["preceptor_last_name"];
							$ELECTIVE["objective"]				= $PROCESSED["objective"];
							$ELECTIVE["address"]				= $PROCESSED["address"];
							$ELECTIVE["countries_id"]			= $PROCESSED["countries_id"];
							$ELECTIVE["city"]					= $PROCESSED["city"];
							$ELECTIVE["prov_state"]				= $PROCESSED["prov_state"];
							$ELECTIVE["postal_zip_code"]		= $PROCESSED["postal_zip_code"];
							$ELECTIVE["phone"]					= $PROCESSED["phone"];
							$ELECTIVE["fax"]					= $PROCESSED["fax"];
							$ELECTIVE["email"]					= $PROCESSED["email"];
							$ELECTIVE["updated_date"]			= $PROCESSED["updated_date"];
							$ELECTIVE["updated_by"]				= $PROCESSED["updated_by"];
							
							if ($db->AutoExecute(CLERKSHIP_DATABASE.".electives", $ELECTIVE, "INSERT")) {
								
								// If International email UGE now or wait for cronjob?
								$SUCCESS++;
								$SUCCESSSTR[]  	= "You have successfully added this <strong>".html_encode($PROCESSED["geo_location"])."</strong> elective to the system.<br /><br />Please <a href=\"".$url."\">click here</a> to proceed to the index page or you will be automatically forwarded in 5 seconds.";
								$ONLOAD[]		= "setTimeout('window.location=\\'".$url."\\'', 5000)";
			
								application_log("success", "New elective [".$EVENT["event_title"]."] added to the system.");
							} else {
								$ERROR++;
								$ERRORSTR[] = "There was a problem inserting this elective into the system. The MEdTech Unit was informed of this error; please try again later.";
			
								application_log("error", "There was an error inserting a clerkship elective. Database said: ".$db->ErrorMsg());
							}
						} else {
							$ERROR++;
							$ERRORSTR[] = "There was a problem inserting this elective into the system. The MEdTech Unit was informed of this error; please try again later.";
		
							application_log("error", "There was an error inserting a clerkship elective event contact. Database said: ".$db->ErrorMsg());
						}
					} else {
						$ERROR++;
						$ERRORSTR[] = "There was a problem inserting this elective into the system. The MEdTech Unit was informed of this error; please try again later.";
	
						application_log("error", "There was an error inserting a clerkship elective. Database said: ".$db->ErrorMsg());
					}
				} else {
					$ERROR++;
					$ERRORSTR[] = "There was a problem inserting this elective into the system. The MEdTech Unit was informed of this error; please try again later.";

					application_log("error", "There was an error inserting a clerkship elective. Database said: ".$db->ErrorMsg());
				}
			} else {
				$STEP = 1;
			}
		break;
		case 1 :
		default :
			continue;
		break;
	}

	// Display Content
	switch ($STEP) {
		case 2 :
			if ($SUCCESS) {
				echo display_success();
			}
					
			if ($NOTICE) {
				echo display_notice();
			}
					
			if ($ERROR) {
				echo display_error();
			}
		break;
		case 1 :
		default :
			$HEAD[] 		= "<link href=\"".ENTRADA_URL."/javascript/calendar/css/xc2_default.css\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />";
			$HEAD[] 		= "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/calendar/config/xc2_default.js\"></script>\n";
			$HEAD[] 		= "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/calendar/script/xc2_inpage.js\"></script>\n";
			$HEAD[]			= "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/picklist.js\"></script>\n";
			$HEAD[]			= "<script type=\"text/javascript\">
			function checkInternational (flag) {
				if (flag == \"true\") {
					$('international_notice').style.display = 'block';
				} else {
					$('international_notice').style.display = 'none';
				}
			}
			
			function showOther() {
				if (\$F('schools_id') == '99999') {
					$('other_host_school').show();
				} else {
					$('other_host_school').hide();
				}
			}
			
			function changeDurationMessage() {
				var value	= $('start_date').value;
				newDate		= toJSDate(value);

				switch (\$F('event_finish')) {
					case '2':
						var days = 14;
						break;
					case '3':
						var days = 21;
						break;
					case '4':
						var days = 28;
						break;
					default:
						var days = 14;
						break;
				}
				newDate.setDate(newDate.getDate() + days);
				newDate = toCalendarDate(newDate);
				$('auto_end_date').innerHTML = '&nbsp;&nbsp;&nbsp;Ending in '+\$F('event_finish')+' weeks on ' +newDate;
			}
			
			function setDateValue(field, date) {
				$('auto_end_date').style.display = 'inline';
				newDate = toJSDate(date);
				switch (\$F('event_finish')) {
					case '2':
						var days = 14;
						break;
					case '3':
						var days = 21;
						break;
					case '4':
						var days = 28;
						break;
					default:
						var days = 14;
						break;
				}
				newDate.setDate(newDate.getDate()+days);
				newDate = toCalendarDate(newDate);
				$('auto_end_date').innerHTML = '&nbsp;&nbsp;&nbsp;Ending in '+\$F('event_finish')+' weeks on ' +newDate;
				$('start_date').value = date;
			}
			
			function AjaxFunction(cat_id) {	
				var url='".webservice_url("clerkship_department")."?cat_id=' + cat_id + '&dept_id=".(int) $PROCESSED["department_id"]."';
		    	new Ajax.Updater($('department_category'), url, 
		    		{
		    			method : 'get'
		    		});
			}
			
			var updater = null;
			function provStateFunction(countries_id) {	
				var url='".webservice_url("clerkship_prov")."';
				url=url+'?countries_id='+countries_id+'&prov_state=".rawurlencode((isset($_POST["prov_state"]) ? clean_input($_POST["prov_state"], array("notags", "trim")) : $PROCESSED["prov_state"]))."';
				new Ajax.Updater($('prov_state_div'), url, 
					{ 
						method:'get',
						onComplete: function () {
							generateAutocomplete();
							if ($('prov_state').selectedIndex || $('prov_state').selectedIndex === 0) {
								$('prov_state_label').removeClassName('form-nrequired');
								$('prov_state_label').addClassName('form-required');
							} else {
								$('prov_state_label').removeClassName('form-required');
								$('prov_state_label').addClassName('form-nrequired');
							}
						}
					});
			}
					
			function generateAutocomplete() {
				if (updater != null) {
					updater.url = '".ENTRADA_URL."/api/cities-by-country.api.php?countries_id='+$('countries_id').options[$('countries_id').selectedIndex].value+'&prov_state='+($('prov_state') !== null ? ($('prov_state').selectedIndex || $('prov_state').selectedIndex === 0 ? $('prov_state').options[$('prov_state').selectedIndex].value : $('prov_state').value) : '');
				} else {
					updater = new Ajax.Autocompleter('city', 'city_auto_complete', 
						'".ENTRADA_URL."/api/cities-by-country.api.php?countries_id='+$('countries_id').options[$('countries_id').selectedIndex].value+'&prov_state='+($('prov_state') !== null ? ($('prov_state').selectedIndex || $('prov_state').selectedIndex === 0 ? $('prov_state').options[$('prov_state').selectedIndex].value : $('prov_state').value) : ''), 
						{
							frequency: 0.2, 
							minChars: 2
						});
				}
			}
			</script>\n";
			
			$ONLOAD[]		= "showOther()";
			$ONLOAD[]		= "AjaxFunction(\$F($('addElectiveForm')['category_id']))";
			$ONLOAD[]		= "provStateFunction(\$F($('addElectiveForm')['countries_id']))";
			$ONLOAD[]		= "setMaxLength()";
			$ONLOAD[]		= "changeDurationMessage()";
			
			$LASTUPDATED	= $result["updated_date"];
			
			if ($ERROR) {
				echo display_error();
			}
			?>
			<form id="addElectiveForm" action="<?php echo ENTRADA_URL; ?>/clerkship/electives?<?php echo replace_query(array("step" => 2)); ?>" method="post">
			<table style="width: 100%" cellspacing="0" cellpadding="2" border="0" summary="Adding Elective">
			<colgroup>
				<col style="width: 3%" />
				<col style="width: 20%" />
				<col style="width: 77%" />
			</colgroup>
			<tfoot>
				<tr>
					<td colspan="3" style="padding-top: 25px">
						<table style="width: 100%" cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td style="width: 25%; text-align: left">
								<input type="button" class="button" value="Cancel" onclick="window.location='<?php echo ENTRADA_URL; ?>/clerkship'" />
							</td>
							<td style="width: 75%; text-align: right; vertical-align: middle">
								<input type="submit" class="button" value="Submit" />
							</td>
						</tr>
						</table>
					</td>
				</tr>
			</tfoot>
			<tbody>
				<tr>
					<td colspan="3"><h2>Elective Details</h2></td>
				</tr>
				<tr>
					<td></td>
					<td style="vertical-align: top"><label for="geo_location" class="form-required">Geographic Location</label></td>
					<td style="vertical-align: top">
						<input type="radio" name="geo_location" id="geo_location_national" onclick="checkInternational('false');" value="National"<?php echo (((!isset($PROCESSED["geo_location"])) || ((isset($PROCESSED["geo_location"])) && ($PROCESSED["geo_location"]) == "National")) ? " checked=\"checked\"" : ""); ?> /> <label for="geo_location_national">National</label><br />
						<input type="radio" name="geo_location" id="geo_location_international" onclick="checkInternational('true');" value="International"<?php echo (((isset($PROCESSED["geo_location"])) && $PROCESSED["geo_location"] == "International") ? " checked=\"checked\"" : ""); ?> /> <label for="geo_location_international">International</label>
						<div id="international_notice" class="display-notice" style="display: none">
							<strong>Important Note:</strong> You must allow 12 weeks for processing of international electives.
						</div>
					</td>
				</tr>
				<tr>
					<td colspan="3">&nbsp;</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="category_id" class="form-required">Elective Period</label></td>
					<td>
						<select id="category_id" name="category_id_name" onchange="AjaxFunction(this.value);" style="width: 90%">
						<?php
						$query	= "SELECT * FROM `".CLERKSHIP_DATABASE."`.`categories` WHERE `category_type` = ".$db->qstr($CLERKSHIP_CATEGORY_TYPE_ID)." AND `category_name` = ".$db->qstr("Class of ".$_SESSION["details"]["grad_year"]);
						$result	= $db->GetRow($query);
						if ($result) {
							echo "<option value=\"0\"".((!isset($PROCESSED["category_id"])) ? " selected=\"selected\"" : "").">-- Elective Period --</option>\n";
							$query		= "SELECT * FROM `".CLERKSHIP_DATABASE."`.`categories` WHERE `category_parent` = ".$db->qstr($result["category_id"])." AND `category_type` = '22'";
							$results	= $db->GetAll($query);

							if ($results) {
								foreach ($results as $result) {
									echo "<option value=\"".(int) $result["category_id"]."\"".(isset($PROCESSED["category_id"]) && $PROCESSED["category_id"] == (int)$result["category_id"] ? " selected=\"selected\"" : "").">".clerkship_categories_title($result["category_id"])." (".date("Y-m-d", $result["category_start"])." &gt; ".date("Y-m-d", $result["category_finish"]).")</option>\n";
								}
							}
						} else {
							echo "<option value=\"0\"".((!isset($PROCESSED["category_id"])) ? " selected=\"selected\"" : "").">-- No Elective Periods to Choose From --</option>\n";
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td colspan="3">&nbsp;</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="department_id" class="form-required">Elective Department</label></td>
					<td>
						<div id="department_category">Please select an <strong>Elective Period</strong> from above first.</div>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="discipline_id" class="form-required">Elective Discipline</label></td>
					<td>
						<?php
						$discipline = clerkship_fetch_disciplines();
						if ((is_array($discipline)) && (count($discipline) > 0)) {
							echo "<select id=\"discipline_id\" name=\"discipline_id\" style=\"width: 256px\">\n";
							echo "<option value=\"0\"".((!isset($PROCESSED["discipline_id"])) ? " selected=\"selected\"" : "").">-- Select Discipline --</option>\n";
							foreach ($discipline as $value) {
								echo "<option value=\"".(int) $value["discipline_id"]."\"".(($PROCESSED["discipline_id"] == $value["discipline_id"]) ? " selected=\"selected\"" : "").">".html_encode($value["discipline"])."</option>\n";
							}
							echo "</select>\n";
						} else {
							echo "<input type=\"hidden\" id=\"discipline_id\" name=\"discipline_id\" value=\"0\" />\n";
							echo "Discipline Information Not Available\n";
						}
						?>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="sub_discipline" class="form-nrequired">Sub-Discipline</label></td>
					<td>
					<input type="text" id="sub_discipline" name="sub_discipline" value="<?php echo html_encode($PROCESSED["sub_discipline"]); ?>" maxlength="64" style="width: 250px" />
					</td>
				</tr>
				<tr>
					<td colspan="3">&nbsp;</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="schools_id" class="form-required">Host School</label></td>
					<td>
						<?php
						$clerkship_medical_schools = clerkship_fetch_schools();
						if ((is_array($clerkship_medical_schools)) && (count($clerkship_medical_schools) > 0)) {
							echo "<select id=\"schools_id\" name=\"schools_id\" style=\"width: 256px\" onchange=\"showOther();\">\n";
							echo "<option value=\"0\"".((!isset($PROCESSED["schools_id"])) ? " selected=\"selected\"" : "").">-- Select Host School --</option>\n";
							foreach ($clerkship_medical_schools as $value) {
								echo "<option value=\"".(int) $value["schools_id"]."\"".(($PROCESSED["schools_id"] == $value["schools_id"]) ? " selected=\"selected\"" : "").">".html_encode($value["school_title"])."</option>\n";
							}
							echo "<option value=\"99999\"".($PROCESSED["schools_id"] == "99999" ? " selected=\"selected\"" : "").">-- Other (Specify) --</option>\n";
							echo "</select>\n";
						} else {
							echo "<input type=\"hidden\" id=\"schools_id\" name=\"schools_id\" value=\"0\" />\n";
							echo "Host school information is not currently available.\n";
						}
						?>
					</td>
				</tr>
			</tbody>
			<tbody id="other_host_school" style="display: none">
				<tr>
					<td></td>
					<td style="vertical-align: top"><label id="other_label" for="other_medical_school" class="form-required">Other</label></td>
					<td style="vertical-align: top">
						<input type="text" id="other_medical_school" name="other_medical_school" value="<?php echo html_encode($PROCESSED["other_medical_school"]); ?>" maxlength="64" style="width: 250px;" />
						<span class="content-small">(<strong>Example:</strong> Stanford University)</span>
					</td>
				</tr>
			</tbody>
			<tbody>
				<tr>
					<td colspan="3">&nbsp;</td>
				</tr>
				<?php
					echo generate_calendar("start", "Start Date", true, ((isset($PROCESSED["start_date"])) ? $PROCESSED["start_date"] : 0), false, true);
				?>
				<tr>
					<td></td>
					<td style="vertical-align: top"><label for="event_finish" class="form-required">Elective Weeks</label></td>
					<td style="vertical-align: top">
						<?php
						$duration = ceil(($PROCESSED["end_date"] - $PROCESSED["start_date"]) / 604800);
						echo "<select id=\"event_finish\" name=\"event_finish_name\" style=\"width: 10%\" onchange=\"changeDurationMessage();\">\n";
						for($i = 2; $i <= 4; $i++)  {
							echo "<option value=\"".$i."\"".(($i == $duration) ? " selected=\"selected\"" : "").">".$i."</option>\n";
						}
						echo "</select>";
						echo "<span id=\"auto_end_date\" class=\"content-small\"></span>";
						?>
					</td>
				</tr>
				<tr>
					<td colspan="3">&nbsp;</td>
				</tr>
				<tr>
					<td></td>
					<td style="vertical-align: top">
						<label for="objective" class="form-required">Planned Experience</label>
						<div class="content-small" style="margin-top: 5px">
							<strong>Tip:</strong> Provide a narrative of your educational objectives (what you hope to achieve) for this elective.
						</div>
					</td>
					<td>
						<textarea id="objective" name="objective" class="expandable" style="width: 95%; height: 60px" cols="50" rows="5" maxlength="300"><?php echo ((isset($PROCESSED["objective"])) ? html_encode($PROCESSED["objective"]) : ""); ?></textarea>
						
					</td>
				</tr>
				<tr>
					<td colspan="3" style="padding-top: 15px">
						<h2>Location Details</h2>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="countries_id" class="form-required">Country</label></td>
					<td>
						<?php
						$countries = fetch_countries();
						if ((is_array($countries)) && (count($countries) > 0)) {
							echo "<select id=\"countries_id\" name=\"countries_id\" style=\"width: 256px\" onchange=\"provStateFunction(this.value);\">\n";
							echo "<option value=\"0\"".((!isset($PROCESSED["countries_id"])) ? " selected=\"selected\"" : "").">-- Select Country --</option>\n";
							foreach ($countries as $value) {
								echo "<option value=\"".(int) $value["countries_id"]."\"".(($PROCESSED["countries_id"] == $value["countries_id"]) ? " selected=\"selected\"" : (!isset($PROCESSED["countries_id"]) && $value["countries_id"] == DEFAULT_COUNTRY_ID) ? " selected=\"selected\"" : "").">".html_encode($value["country"])."</option>\n";
							}
							echo "</select>\n";
						} else {
							echo "<input type=\"hidden\" id=\"countries_id\" name=\"countries_id\" value=\"0\" />\n";
							echo "Country information not currently available.\n";
						}
						?>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label id="prov_state_label" for="prov_state_div" class="form-required">Province / State</label></td>
					<td>
						<div id="prov_state_div">Please select a <strong>Country</strong> from above first.</div>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="city" class="form-required">City</label></td>
					<td>
						<input type="text" id="city" name="city" size="100" autocomplete="off" style="width: 250px; vertical-align: middle" value="<?php echo $PROCESSED["city"]; ?>"/>
						<script type="text/javascript">
							$('city').observe('keypress', function(event){
								if(event.keyCode == Event.KEY_RETURN) {
									Event.stop(event);
								}
							});
						</script>
						<div class="autocomplete" id="city_auto_complete"></div>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="address" class="form-required">Address</label></td>
					<td>
					<input type="text" id="address" name="address" value="<?php echo html_encode($PROCESSED["address"]); ?>" maxlength="250" style="width: 250px" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="postal_zip_code" class="form-nrequired">Postal / Zip Code</label></td>
					<td>
					<input type="text" id="postal_zip_code" name="postal_zip_code" value="<?php echo html_encode($PROCESSED["postal_zip_code"]); ?>" maxlength="20" style="width: 250px"" />
					</td>
				</tr>
				<tr>
					<td colspan="3" style="padding-top: 15px">
						<h2>Preceptor Details</h2>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="preceptor_first_name" class="form-nrequired">Firstname</label></td>
					<td>
					<input type="text" id="preceptor_first_name" name="preceptor_first_name" value="<?php echo html_encode($PROCESSED["preceptor_first_name"]); ?>" maxlength="50" style="width: 250px" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="preceptor_last_name" class="form-required">Lastname</label></td>
					<td>
					<input type="text" id="preceptor_last_name" name="preceptor_last_name" value="<?php echo html_encode($PROCESSED["preceptor_last_name"]); ?>" maxlength="50" style="width: 250px" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="phone" class="form-nrequired">Telephone Number</label></td>
					<td>
					<input type="text" id="phone" name="phone" value="<?php echo html_encode($PROCESSED["phone"]); ?>" maxlength="25" style="width: 250px"" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="fax" class="form-nrequired">Fax Number</label></td>
					<td>
					<input type="text" id="fax" name="fax" value="<?php echo html_encode($PROCESSED["fax"]); ?>" maxlength="25" style="width: 250px"" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td><label for="email" class="form-required">E-Mail Address</label></td>
					<td>
					<input type="text" id="email" name="email" value="<?php echo html_encode($PROCESSED["email"]); ?>" maxlength="150" style="width: 250px"" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td colspan="2">
						<div id="disclosure" name="disclosure" class="content-small" style="padding-top: 15px">
							<strong>Disclosure:</strong> By clicking the submit button below I hereby certify that there is no conflict of interest that may result in the submission of a biased evaluation (i.e. family member, close personal friend, etc.). I also confirm that this elective has already been approved by the preceptor listed above.
						</div>
					</td>
				</tr>
			</tbody>
			</table>
			</form>
			<?php
		break;
	}
}