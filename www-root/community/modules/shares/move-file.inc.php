<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 * 
 * Used to delete a particular file within a folder of a community.
 * 
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Matt Simpson <matt.simpson@queensu.ca>
 * @author Developer: James Ellis <james.ellis@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 * 
*/

if ((!defined("COMMUNITY_INCLUDED")) || (!defined("IN_SHARES"))) {
	exit;
} elseif (!$COMMUNITY_LOAD) {
	exit;
}

if ($RECORD_ID) {
	if (isset($_GET["share_id"]) && ($share_id = ((int) $_GET["share_id"]))) {
		$query			= "	SELECT a.*
							FROM `community_share_files` AS a
							LEFT JOIN `community_shares` AS b
							ON a.`cshare_id` = b.`cshare_id`
							WHERE a.`csfile_id` = ".$db->qstr($RECORD_ID)."
							AND a.`community_id` = ".$db->qstr($COMMUNITY_ID)."
							AND a.`cshare_id` != ".$db->qstr($share_id)."
							AND b.`cpage_id` = ".$db->qstr($PAGE_ID)."
							AND b.`folder_active` = '1'";
		$file_record	= $db->GetRow($query);
		if ($file_record) {
			$query			= "	SELECT b.`page_url`
								FROM `community_shares` AS a
								LEFT JOIN `community_pages` AS b
								ON b.`cpage_id` = a.`cpage_id`
								WHERE a.`cshare_id` = ".$db->qstr($share_id)."
								AND a.`community_id` = ".$db->qstr($COMMUNITY_ID)."
								AND a.`folder_active` = '1'";
			$share_record	= $db->GetRow($query);
			if ($share_record) {
				if ((int) $file_record["file_active"]) {
					if (shares_file_module_access($RECORD_ID, "move-file")) {
						if ($db->AutoExecute("community_share_files", array("cshare_id" => $share_id, "updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "UPDATE", "`csfile_id` = ".$db->qstr($RECORD_ID)." AND `cshare_id` = ".$db->qstr($file_record["cshare_id"])." AND `community_id` = ".$db->qstr($COMMUNITY_ID))) {
							@$db->AutoExecute("community_share_file_versions", array("cshare_id" => $share_id, "updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "UPDATE", "`csfile_id` = ".$db->qstr($RECORD_ID)." AND `cshare_id` = ".$db->qstr($file_record["cshare_id"])." AND `community_id` = ".$db->qstr($COMMUNITY_ID));
							@$db->AutoExecute("community_share_comments", array("cshare_id" => $share_id, "updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "UPDATE", "`csfile_id` = ".$db->qstr($RECORD_ID)." AND `cshare_id` = ".$db->qstr($file_record["cshare_id"])." AND `community_id` = ".$db->qstr($COMMUNITY_ID));

							communities_log_history($COMMUNITY_ID, $PAGE_ID, $RECORD_ID, "community_history_move_file", true, $share_id);
							add_statistic("community_shares", "file_move", "csfile_id", $RECORD_ID);
							$db->AutoExecute("community_history", array("history_display" => 0), "UPDATE", "`community_id` = ".$db->qstr($COMMUNITY_ID)." AND `module_id` = ".$db->qstr($MODULE_ID)." AND `record_id` = ".$db->qstr($RECORD_ID));
						} else {
							application_log("error", "Failed to move [".$RECORD_ID."] file to folder. Database said: ".$db->ErrorMsg());
						}
					}
				} else {
					application_log("error", "The provided file id [".$RECORD_ID."] is deactivated.");
				}

				header("Location: ".COMMUNITY_URL.$COMMUNITY_URL.":".$share_record["page_url"]."?section=view-folder&id=".$share_id);
				exit;
			}
		} else {
			application_log("error", "The provided file id [".$RECORD_ID."] was invalid.");
		}
	} else {

	}
} else {
	application_log("error", "No file id was provided for moving.");
}

header("Location: ".COMMUNITY_URL.$COMMUNITY_URL.":".$PAGE_URL);
exit;
?>