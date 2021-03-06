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
 * This file is used to delete categories from the entrada_clerkship.categories table.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer:James Ellis <james.ellis@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/

if((!defined("PARENT_INCLUDED")) || (!defined("IN_CATEGORIES"))) {
	exit;
} elseif((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif(!$ENTRADA_ACL->amIAllowed('categories', 'delete', false)) {
	$ONLOAD[]	= "setTimeout('window.location=\\'".ENTRADA_URL."/admin/settings/manage/".$MODULE."\\'', 15000)";

	$ERROR++;
	$ERRORSTR[]	= "Your account does not have the permissions required to use this feature of this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.";

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["group"]."] and role [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["role"]."] does not have access to this module [".$MODULE."]");
} else {
	
	if (isset($_GET["category_id"]) && ($id = clean_input($_GET["category_id"], array("notags", "trim")))) {
		$CATEGORY_ID = $id;
	}
	
	if (isset($_GET["mode"]) && $_GET["mode"] == "ajax") {
		$MODE = "ajax";
	}
	
	if ($MODE == "ajax" && $CATEGORY_ID) {
		
		ob_clear_open_buffers();
		
		switch($STEP) {
			case "2" :
				if ($_POST["confirm"] == "on") {
					$query = "	SELECT * FROM `".CLERKSHIP_DATABASE."`.`categories`
								WHERE `category_id` = ".$db->qstr($CATEGORY_ID)."
								AND `category_status` != 'trash'
								GROUP BY `category_id`";
					$categories = $db->GetAll($query);
					if ($categories) {
						foreach ($categories as $category) {
                            $query = "UPDATE `".CLERKSHIP_DATABASE."`.`categories` SET `category_status` = 'trash', `updated_date` = " . $db->qstr(time()) . ", `updated_by` = " . $db->qstr($ENTRADA_USER->getID()) . " WHERE `category_id` = ".$db->qstr($category["category_id"]);
                            if (!$db->Execute($query)) {
                                application_log("Failed to update [".CLERKSHIP_DATABASE.".categories], DB said: ".$db->ErrorMsg());
                            }
						}
						categories_deactivate_children($CATEGORY_ID);
						echo json_encode(array("status" => "success"));
					}
				} else {
					echo json_encode(array("status" => "error"));
				}

			break;
			case 1 :
			default :
			
				$query	= "	SELECT * FROM `".CLERKSHIP_DATABASE."`.`categories`
							WHERE `category_id` = ".$db->qstr($CATEGORY_ID)."
							AND `category_status` != 'trash'";
				$category	= $db->GetRow($query);
				
				if ($category) {
					?>
					<div class="display-generic">
						<p>You are about to delete the category <strong><?php echo $category["category_name"]; ?></strong>. Please click the <strong>delete</strong> button below to remove it from the system.</p>
						<p><strong>Please note:</strong> Any children of this category will be removed as well.</p>
					</div>
					<form id="category-form" action="<?php echo ENTRADA_URL."/admin/settings/manage/categories"."?".replace_query(array("step" => "2")); ?>" method="post">
						<input type="checkbox" name="confirm" /> Please check this box to confirm you wish to remove the category and its children.
					</form>
					<?php
				} else {
					echo $db->ErrorMsg();
				}
				
			break;
		}
		
		exit;
	}
	
	$category_ids	= array();
	
	$BREADCRUMB[]	= array("url" => "", "title" => "Delete Categories");

	echo "<h1>Delete Categories</h1>\n";

	// Error Checking
	switch($STEP) {
		case 2 :
		case 1 :
		default :
			if (isset($_POST["delete"]) && count($_POST["delete"]) && ($tmp_input = $_POST["delete"])) {
				$category_ids_string = "";
				foreach ($tmp_input as $category) {
					if ((int)$category["category_id"]) {
						$category["category_id"] = clean_input($category["category_id"], "int");
					}
					$query	= "SELECT * FROM `".CLERKSHIP_DATABASE."`.`categories`
								WHERE `category_id` = ".$db->qstr($category["category_id"])."
								AND (`organisation_id` = ".$db->qstr($ORGANISATION_ID)." OR `organisation_id` IS NULL)
								AND `category_status` != 'trash'";
					$result	= $db->GetRow($query);
					if ($result) {
						if (((int)$result["category_status"]) == "trash") {
							$ERROR++;
							$ERRORSTR[] = "The category [".html_encode($result["category_name"])."] you have tried to delete does not exist.";
						} else {
							$categories[]	= array("category_id" => $category["category_id"],
													"category_children_target" => ($category["move"] ? $category["category_parent"] : false),
													"category_order" => $result["category_order"],
													"category_parent" => $result["category_parent"]);
							$category_ids_string .= ($category_ids_string ? ",".$category["category_id"] : $category["category_id"]);
						}
					}
				}
			}
			if (!count($categories)) {
				header("Location: ".ENTRADA_URL."/admin/settings/manage/categories?org=".$ORGANISATION_ID);
				exit;
			}
		break;
	}
	
	// Display Page
	switch($STEP) {
		case 2 :
			$success_count = 0;
			$moved_count = 0;
			$deleted_count = 0;
			foreach ($categories as $category) {
				if (categories_delete_for_org($category["category_id"], $category["category_children_target"])) {
					$query				= "SELECT `category_id`, `category_order` 
											FROM `".CLERKSHIP_DATABASE."`.`categories` 
											WHERE `category_parent` = ".$db->qstr($category["category_parent"])." 
											AND `category_status` != 'trash'
											AND `category_order` > ".$db->qstr($category["category_order"]);
					$moving_siblings	= $db->GetAll($query);
					if ($moving_siblings) {
						foreach($moving_siblings as $moving_sibling) {
							$query = "	UPDATE `".CLERKSHIP_DATABASE."`.`categories` 
										SET `category_order` = ".$db->qstr($moving_sibling["objective_order"] - 1)." 
										WHERE `objective_id` = ".$db->qstr($moving_sibling["objective_id"])."
										AND `category_status` != 'trash'";
							$db->Execute($query);
						}
					}
					if ($objective["objective_children_target"] !== false) {
						$query				= "	SELECT `objective_id`, `objective_name` 
												FROM `".CLERKSHIP_DATABASE."`.`categories` 
												WHERE `objective_parent` = ".$db->qstr($objective["objective_id"])."
												AND `category_status` != 'trash'";
						$moving_children	= $db->GetAll($query);
						if ($moving_children) {
							$query = "	SELECT MAX(`objective_order`)
										FROM `".CLERKSHIP_DATABASE."`.`categories`
										WHERE `category_status` != 'trash'
										GROUP BY `objective_parent`
										HAVING `objective_parent` = ".$db->qstr($objective["objective_children_target"]);
							$count = $db->GetOne($query);
							if (!$count) {
								$count = 0;
							}
							$moved = true;
							foreach($moving_children as $moving_child) {
								$count++;
								$query = "	UPDATE `".CLERKSHIP_DATABASE."`.`categories` 
											SET `objective_order` = ".$db->qstr($count).", 
											`objective_parent` = ".$db->qstr($objective["objective_children_target"])." 
											WHERE `objective_id` = ".$db->qstr($moving_child["objective_id"])."
											AND `category_status` != 'trash'";
								if (!$db->Execute($query)) {
									$moved = false;
									$ERROR++;
									$ERRORSTR[] = "There was a problem trying to place the child Objective [".html_encode($moving_children["objective_name"])."] under another parent.";
									application_log("error", "There was an issue while trying to move an objective [".$moving_child["objective_id"]."] under a new parent. Database said: ".$db->ErrorMsg());
								} else {
									$moved_count++;
								}
							}
						}
					}
					$success_count++;
				} else {
					$ERROR++;
					$ERRORSTR[] = "A problem occurred while attempting to delete a selected Objective [".html_encode($moving_children["objective_name"])."].";
					application_log("error", "There was an issue while trying to move an objective [".$moving_child["objective_id"]."] under a new parent. Database said: ".$db->ErrorMsg());
				}
			}
			if ($ERROR) {
				echo display_error();
			}
			if ($NOTICE) {
				echo display_notice();
			}
			if ($success_count) {
				$url = ENTRADA_URL."/admin/settings/manage/categories?org=".$ORGANISATION_ID;
				$SUCCESS++;
				$SUCCESSSTR[] = "You have successfully deactivated ".$success_count." categories from the system.".($moved_count && $deleted_count ? " <br /><br />Additionally, ".$moved_count." of these categories' children were placed under a new parent and ".$deleted_count." of the categories' children were deactivated along with their parent objective." : ($moved_count && !$deleted_count ? " <br /><br />Additionally, ".$moved_count." of these categories' children were placed under a new parent." : ($deleted_count ? " <br /><br />Additionally, ".$deleted_count." of these categories' children were deactivated along with under a new parent." : "")))."<br /><br />You will now be redirected to the index; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
				$ONLOAD[]		= "setTimeout('window.location=\\'".$url."\\'', 5000)";
				
				application_log("success", "Categories successfully deactivated in the system.");
			}
			if ($SUCCESS) {
				echo display_success();
			}
		break;
		case 1 :
		default :
			if ($ERROR) {
				echo display_error();
			} else {
				echo display_notice(array("Please review the following objective or categories to ensure that you wish to permanently delete them."));
				$HEAD[]	= "	<script type=\"text/javascript\">
								function selectObjective(parent_id, objective_id, excluded_categories) {
									new Ajax.Updater('selectParent'+objective_id+'Field', '".ENTRADA_URL."/api/categories-list.api.php', {parameters: {'pid': parent_id, 'id': objective_id, 'excluded': excluded_categories}});
									return;
								}
								function selectOrder(parent_id, objective_id) {
									return;
								}
							</script>";
				?>
				<form action="<?php echo ENTRADA_URL."/admin/settings/manage/categories?".replace_query(array("action" => "delete", "step" => 2)); ?>" method="post">
				<table class="tableList" cellspacing="0" summary="List of categories to be removed">
				<colgroup>
					<col class="modified" />
					<col class="title" />
				</colgroup>
				<thead>
					<tr>
						<td class="modified">&nbsp;</td>
						<td class="title">Categories</td>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<td>&nbsp;</td>
						<td style="padding-top: 10px">
							<input type="submit" class="btn btn-danger" value="Delete Selected" />
						</td>
					</tr>
				</tfoot>
				<tbody>
				<?php
				foreach ($categories as $objective) {
					echo categories_intable($objective["objective_id"], 0, $objective_ids_string);
				}
				?>
				</tbody>
				</table>
				</form>
				<?php
			}
		break;
	}
}
?>