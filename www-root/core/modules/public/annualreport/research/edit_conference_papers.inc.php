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
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Andrew Dos-Santos <andrew.dos-santos@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/
if((!defined("PARENT_INCLUDED")) || (!defined("IN_ANNUAL_REPORT"))) {
	exit;
} else if((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL.((isset($_SERVER["REQUEST_URI"])) ? "?url=".rawurlencode(clean_input($_SERVER["REQUEST_URI"], array("nows", "url"))) : ""));
	exit;
} elseif (!$ENTRADA_ACL->amIAllowed('annualreport', 'update')) {
	$ONLOAD[]	= "setTimeout('window.location=\\'".ENTRADA_URL."/".$MODULE."\\'', 15000)";

	$ERROR++;
	$ERRORSTR[]	= "You do not have the permissions required to use this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.";

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"]."] and role [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["role"]."] do not have access to this module [".$MODULE."]");
} else {
	$CONFERENCE_PAPERS_ID = $_GET["rid"];
	
	// This grid should be expanded upon redirecting back to the education index.
	$_SESSION["research_expand_grid"] = "conference_papers_grid";
	
	if($CONFERENCE_PAPERS_ID) {
		$query	= "SELECT * FROM `ar_conference_papers` WHERE `conference_papers_id`=".$db->qstr($CONFERENCE_PAPERS_ID);
		$result	= $db->GetRow($query);
		if($result) {
			$BREADCRUMB[]	= array("url" => ENTRADA_URL."/annualreport/research?section=edit_conference_papers", "title" => "Edit Invited Lectures / Conference Papers");
			
			echo "<h1>Edit Invited Lectures / Conference Papers</h1>\n";

			// Error Checking
			switch($STEP) {
				case 2 :					
					/**
					 * Required field "lectures_papers_list" / Invited Lectures / Conference Papers
					 */
					if((isset($_POST["lectures_papers_list"])) && ($lectures_papers_list = clean_input($_POST["lectures_papers_list"], array("notags", "trim")))) {
						$PROCESSED["lectures_papers_list"] = $lectures_papers_list;
					} else {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Invited Lectures / Conference Papers</strong> field is required.";
					}
					/**
					 * Required field "status" / Status
					 */
					if((isset($_POST["status"])) && ($status = clean_input($_POST["status"], array("notags", "trim")))) {
						$PROCESSED["status"] = $status;
					} else {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Status</strong> field is required.";
					}
					/**
					 * Required field "institution" / Institution.
					 */
					if((isset($_POST["institution"])) && ($institution = clean_input($_POST["institution"], array("notags", "trim")))) {
						$PROCESSED["institution"] = $institution;
					} else {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Institution</strong> field is required.";
					}			
					/**
					 * Required field "location" / Location
					 */
					if((isset($_POST["location"])) && ($location = clean_input($_POST["location"], array("notags", "trim")))) {
						$PROCESSED["location"] = $location;
					} else {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Location</strong> field is required.";
					}
					/**
					 * Required field "type" / Type.
					 */
					if((isset($_POST["type"])) && ($type = clean_input($_POST["type"], array("notags", "trim")))) {
						$PROCESSED["type"] = $type;
					} else {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Type</strong> field is required.";
					}		
					/**
					 * Required field "year_reported" / Year Reported.
					 */
					if((isset($_POST["year_reported"])) && ($year_reported = clean_input($_POST["year_reported"], array("int")))) {
						$PROCESSED["year_reported"] = $year_reported;
					} else {
						$ERROR++;
						$ERRORSTR[] = "The <strong>Year Reported</strong> field is required.";
					}
					
					if(isset($_POST["post_action"])) {
						switch($_POST["post_action"]) {							
							case "new" :
								$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "new";
							break;
							case "index" :
							default :
								$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "index";
							break;
						}
					} else {
						$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "index";
					}
					
					if(!$ERROR) {
						$PROCESSED["updated_date"]	= time();
						$PROCESSED["updated_by"]	= $_SESSION["details"]["id"];
						$PROCESSED["proxy_id"]		= $_SESSION[APPLICATION_IDENTIFIER]['tmp']['proxy_id'];
						
						if($db->AutoExecute("ar_conference_papers", $PROCESSED, "UPDATE", "`conference_papers_id`=".$db->qstr($CONFERENCE_PAPERS_ID))) {
								switch($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"]) {
									case "new" :
										$url = ENTRADA_URL."/annualreport/research?section=add_conference_papers";
										$msg	= "You will now be redirected to add more Invited Lectures / Conference Papers; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
									break;
									case "index" :
									default :
										$url = ENTRADA_URL."/annualreport/research";
										$msg	= "You will now be redirected to the research page; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
									break;
								}
		
								$SUCCESS++;
								$SUCCESSSTR[]  = "You have successfully edited <strong>".html_encode($PROCESSED["lectures_papers_list"])."</strong> in the system.<br /><br />".$msg;
								$ONLOAD[]		= "setTimeout('window.location=\\'".$url."\\'', 5000);";
		
								application_log("success", "Edited Invited Lectures / Conference Papers [".$CONFERENCE_PAPERS_ID."] in the system.");					
		
						} else {
							$ERROR++;
							$ERRORSTR[] = "There was a problem editing this Invited Lectures / Conference Papers record in the system. The MEdIT Unit was informed of this error; please try again later.";
		
							application_log("error", "There was an error editing the Invited Lectures / Conference Papers. Database said: ".$db->ErrorMsg());
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
			switch($STEP) {
				case 2 :
					if($SUCCESS) {
						echo display_success();
					}
					if($NOTICE) {
						echo display_notice();
					}
					if($ERROR) {
						echo display_error();
					}
				break;
				case 1 :
				default :
					if(!isset($PROCESSED) || count($PROCESSED) <= 0)
					{
						$conferencePaperQuery = "SELECT * FROM `ar_conference_papers` WHERE `conference_papers_id` =".$db->qstr($CONFERENCE_PAPERS_ID);						
						$conferencePaperResult = $db->GetRow($conferencePaperQuery);
					}
					
					if($ERROR) {
						echo display_error();
					}
					?>					
					<form action="<?php echo ENTRADA_URL; ?>/annualreport/research?section=edit_conference_papers&amp;step=2&amp;rid=<?php echo $CONFERENCE_PAPERS_ID;?>" method="post">
					<table style="width: 100%" cellspacing="0" cellpadding="2" border="0" summary="Editing Invited Lectures / Conference Papers">
					<colgroup>
						<col style="width: 3%" />
						<col style="width: 20%" />
						<col style="width: 77%" />
					</colgroup>
					<tr>
						<td colspan="3"><h2>Details</h2></td>
					</tr>
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="lectures_papers_list" class="form-required">Invited Lectures / Conference Papers</label></td>
						<td><textarea id="lectures_papers_list" name="lectures_papers_list" style="width: 95%" rows="4"><?php echo ((isset($conferencePaperResult["lectures_papers_list"])) ? html_encode($conferencePaperResult["lectures_papers_list"]) : html_encode($PROCESSED["lectures_papers_list"])); ?></textarea></td>
					</tr>
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="status" class="form-required">Status</label></td>				
						<td><select name="status" id="status" style="vertical-align: middle">
						<option value=""></option>
						<?php
							$statusArray = getPulicationStatuses();
							foreach($statusArray as $statusValue) {
								echo "<option value=\"".$statusValue["publication_status"]."\"".((($conferencePaperResult["status"] == $statusValue["publication_status"]) || ($PROCESSED["status"] == $statusValue["publication_status"])) ? " selected=\"selected\"" : "").">".$statusValue["publication_status"]."</option>\n";
							}
							echo "</select>";
						?>
						</td>				
					</tr>
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="institution" class="form-required">Institution</label></td>
						<td><input type="text" id="institution" name="institution" value="<?php echo ((isset($conferencePaperResult["institution"])) ? html_encode($conferencePaperResult["institution"]) : html_encode($PROCESSED["institution"])); ?>" maxlength="255" style="width: 95%" /></td>
					</tr>
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="location" class="form-required">Location</label></td>
						<td><input type="text" id="location" name="location" value="<?php echo ((isset($conferencePaperResult["location"])) ? html_encode($conferencePaperResult["location"]) : html_encode($PROCESSED["location"])); ?>" maxlength="255" style="width: 95%" /></td>
					</tr>
					<tr>
						<td></td>
						<td><label for="type" class="form-required">Type</label></td>
						<td><select name="type" id="type" style="vertical-align: middle">
						<option value=""></option>
						<?php
							$paperTypeArray = getConferencePaperTypes();
							foreach($paperTypeArray as $paperTypeValue) {
								echo "<option value=\"".$paperTypeValue["conference_paper_type"]."\"".((($conferencePaperResult["type"] == $paperTypeValue["conference_paper_type"]) || ($PROCESSED["type"] == $paperTypeValue["conference_paper_type"])) ? " selected=\"selected\"" : "").">".$paperTypeValue["conference_paper_type"]."</option>\n";
							}
							echo "</select>";
						?>
						</td>
					</tr>
					<tr>
						<td colspan="3">&nbsp;</td>
					</tr>
					<tr>
						<td></td>
						<td><label for="year_reported" class="form-required">Report Year</label></td>
						<td><select name="year_reported" id="year_reported" style="vertical-align: middle">
						<?php
							for($i=$AR_PAST_YEARS; $i<=$AR_FUTURE_YEARS; $i++)
							{
								if(isset($PROCESSED["year_reported"]) && $PROCESSED["year_reported"] != '')
								{
									$defaultYear = $PROCESSED["year_reported"];
								}
								else if(isset($conferencePaperResult["year_reported"]) && $conferencePaperResult["year_reported"] != '')
								{
									$defaultYear = $conferencePaperResult["year_reported"];
								}
								else 
								{
									$defaultYear = $AR_CUR_YEAR;
								}
								echo "<option value=\"".$i."\"".(($defaultYear == $i) ? " selected=\"selected\"" : "").">".$i."</option>\n";
							}
							echo "</select>";
						?>
						</td>
					</tr>
					<tr>
						<td colspan="3" style="padding-top: 25px">
							<table style="width: 100%" cellspacing="0" cellpadding="0" border="0">
							<tr>
								<td style="width: 25%; text-align: left">
									<input type="button" class="button" value="Cancel" onclick="window.location='<?php echo ENTRADA_URL; ?>/annualreport/research#conference_papers'" />
								</td>
								<td style="width: 75%; text-align: right; vertical-align: middle">
									<span class="content-small">After saving:</span>
									<select id="post_action" name="post_action">							
									<option value="new"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "add") ? " selected=\"selected\"" : ""); ?>>Add More Research</option>
									<option value="index"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "index") ? " selected=\"selected\"" : ""); ?>>Return to Research list</option>
									</select>
									<input type="submit" class="button" value="Save" />
								</td>
							</tr>
							</table>
						</td>
					</tr>
					</table>
					</form>
					<br /><br />
					<?php
				break;
			}
		} else {
			$ERROR++;
			$ERRORSTR[] = "In order to edit an Invited Lectures / Conference Papers record you must provide a valid Invited Lectures / Conference Papers record identifier. The provided ID does not exist in this system.";

			echo display_error();

			application_log("notice", "Failed to provide a valid Invited Lectures / Conference Papers record identifer when attempting to edit a Invited Lectures / Conference Papers record.");
		}
	} else {
		$ERROR++;
		$ERRORSTR[] = "In order to edit an Invited Lectures / Conference Papers record you must provide the Invited Lectures / Conference Papers record identifier.";

		echo display_error();

		application_log("notice", "Failed to provide Invited Lectures / Conference Papers record identifer when attempting to edit a Invited Lectures / Conference Papers record.");
	}
}
?>