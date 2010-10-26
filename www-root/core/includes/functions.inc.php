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
 * This file contains all of the functions used within Entrada.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Matt Simpson <matt.simpson@queensu.ca>
 * @author Developer: James Ellis <james.ellis@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/

/**
 * Returns username and password based matching employee / student number returned by CAS.
 *
 * @param string CAS cas_id string of numbers
 * @return string
 */
function cas_credentials($cas_id = '') {
	global $db;
	$s = 0;
	do {
		$number = intval(substr($cas_id, $s, $s+8));  //Chop the cas_id into 8 digit student id numbers
		if(!$number) {
			return 0;
		}
		$query = 'SELECT `username`, `password` FROM `'.AUTH_DATABASE.'`.`user_data` WHERE `number`='. $db->qstr($number);
		if ($result=$db->GetRow($query)) {
			return $result;
		}
		$s += 8;
	} while (strlen($cas_id) > $s) ;
	return 0;
}

/**
 * This is really just a controller function that calls a bunch of other functions. This function is called by ob_start().
 * @uses check_head()
 * @uses check_meta()
 * @uses check_body()
 * @uses check_sidebar()
 * @uses check_breadcrumb()
 * @param string $buffer
 * @return string buffer
 */
function on_checkout($buffer) {
	$buffer = check_head($buffer);
	$buffer = check_jquery($buffer);
	$buffer = check_meta($buffer);
	$buffer = check_body($buffer);
	$buffer = check_sidebar($buffer);
	$buffer = check_breadcrumb($buffer);
	$buffer = check_script($buffer);
	return $buffer;
}

/**
 * surrounds the supplied string with a javascript try/catch
 * @param string $element
 */
function add_try_catch_js($element) {
	return "try {".$element.";}catch(e){ clog(e); }";
}

/**
 * processes the supplied string to add script elements including onload and onunload blocks. called by on_checkout
 * @param string $buffer
 */
function check_script($buffer) {
	global $SCRIPT, $ONLOAD, $ONUNLOAD;

	$elements = array();
	if ((isset($ONLOAD)) && (count($ONLOAD))) {
		$ONLOAD = array_map('add_try_catch_js',$ONLOAD);
		$elements["load"] = "document.observe('dom:loaded', function() {\n".implode(";\n\t", $ONLOAD)."\n});\n";
	}

	if ((isset($ONUNLOAD)) && (count($ONUNLOAD))) {
		$elements["unload"] = "document.observe('unload', function() {\n".implode(";\n\t", $ONUNLOAD).";\n});\n";
	}

	if ($elements) {
		$SCRIPT[] = "\n<script type=\"text/javascript\">\n".implode("\n",$elements)."</script>";
	}

	$output = "";
	if (isset($SCRIPT) && (count($SCRIPT))) {
		$output .= implode("\n", $SCRIPT);
	}
	return str_replace("</body>", $output."\n</body>", $buffer);
}

/**
 * Function is called by on_checkout. Adds any head elements that are required, specified in the $HEAD array.
 *
 * @param string $buffer
 * @return string buffer
 */
function check_head($buffer) {
	global $HEAD;

	$output = "";

	if ((isset($HEAD)) && (count($HEAD))) {
		$output = implode("\n", $HEAD);
	}

	return str_replace("%HEAD%", $output, $buffer);
}

/**
 * Function is called by on_checkout. Adds any jquery elements that are required, specified in the $JQUERY array,
 * which loads above Prototype to prevent conflicts. This is a quick fix until we can rewrite all of the Javascript
 * to use jQuery.
 *
 * @param string $buffer
 * @return string buffer
 */
function check_jquery($buffer) {
	global $JQUERY;

	$output = "";

	if ((isset($JQUERY)) && (count($JQUERY))) {
		$output = implode("\n", $JQUERY);
	}

	return str_replace("%JQUERY%", $output, $buffer);
}

/**
 * Function is called by on_checkout function. It modifies the page meta information in the $PAGE_META array.
 *
 * @param string $buffer
 * @return string buffer
 */
function check_meta($buffer) {
	global $PAGE_META, $DEFAULT_META, $LASTUPDATED;

	$title			= ((isset($PAGE_META["title"])) ? $PAGE_META["title"] : $DEFAULT_META["title"]);
	$description	= ((isset($PAGE_META["description"])) ? $PAGE_META["description"] : $DEFAULT_META["description"]);
	$keywords		= ((isset($PAGE_META["keywords"])) ? $PAGE_META["keywords"] : $DEFAULT_META["keywords"]);

	if ((isset($LASTUPDATED)) && ((int) $LASTUPDATED)) {
		$LASTUPDATED = "Last updated: ".date("r", $LASTUPDATED).".<br />";
	} else {
		$LASTUPDATED = "";
	}

	return str_replace(array("%TITLE%", "%DESCRIPTION%", "%KEYWORDS%", "%LASTUPDATED%"), array($title, $description, $keywords, $LASTUPDATED), $buffer);
}

/**
 * Function is called by on_checkout function. Adds any events specified in the $ONLOAD or $ONUNLOAD array.
 *
 * @param string $buffer
 * @return string buffer
 */
function check_body($buffer) {
	return $buffer;
}

/**
 * Function is called by on_checkout. Adds any sidebar events specified in $SIDEBAR array.
 *
 * @param string $buffer
 * @return string buffer
 */
function check_sidebar($buffer) {
	global $SIDEBAR;

	$output = "";

	if(@count($SIDEBAR)) {
		@ksort($SIDEBAR);

		$output .= "<div class=\"sidebar\" id=\"sidebar\">\n";
		$output .= implode("\n\n", $SIDEBAR);
		$output .= "</div>\n";
	}
	return str_replace("%SIDEBAR%", $output, $buffer);
}

function load_system_navigator() {
	global $db, $HEAD, $ONLOAD, $USER_ACCESS;

	$output = "";

	if((isset($_SESSION["isAuthorized"])) && ((bool) $_SESSION["isAuthorized"]) && ($_SESSION['details']['group'] != 'guest')) {
	/**
	 * Important: Make sure Prototype is loaded, or this will error out.
	 *
	 */
		$HEAD[] = "<link href=\"".ENTRADA_URL."/css/navigator.css?release=".html_encode(APPLICATION_VERSION)."\" rel=\"stylesheet\" type=\"text/css\" />";

		$output .= "<div id=\"navigator-container\">\n";
		$output .= "	<div id=\"navigator\" style=\"display: none\">\n";
		$output .= "		<div id=\"navigator-interior\">\n";
		$output .= "			<table style=\"width: 98%; table-layout: fixed\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";
		$output .= "			<colgroup>\n";
		$output .= "				<col style=\"width: 25%\" />\n";
		$output .= "				<col style=\"width: 25%\" />\n";
		$output .= "				<col style=\"width: 30%\" />\n";
		$output .= "				<col style=\"width: 20%\" />\n";
		$output .= "			</colgroup>\n";
		$output .= "			<tbody>\n";
		$output .= "				<tr>\n";
		$output .= "					<td style=\"background-image: none\">\n";
		$output .= "						<h3>".APPLICATION_NAME."</h3>\n";
		$output .= "						<ul>\n";
		$output .= "							<li><a href=\"".ENTRADA_URL."/dashboard\">Dashboard</a></li>\n";
		$output .= "							<li><a href=\"".ENTRADA_URL."/communities\">Communities</a></li>\n";
		$output .= "							<li><a href=\"".ENTRADA_URL."/courses\">Courses</a></li>\n";
		$output .= "							<li><a href=\"".ENTRADA_URL."/events\">Learning Events</a></li>\n";
		$output .= "							<li><a href=\"".ENTRADA_URL."/search\">Curriculum Search</a></li>\n";
		$output .= "							<li><a href=\"".ENTRADA_URL."/people\">People Search</a></li>\n";
		$output .= "							<li><a href=\"".ENTRADA_URL."/library\">Library</a></li>\n";
		$output .= "						</ul>\n";
		$output .= "					</td>\n";
		$output .= "					<td>\n";
		$output .= "						<h3>My Communities</h3>\n";
		$query 	= "	SELECT b.`community_id`, b.`community_url`, b.`community_title`
					FROM `community_members` AS a
					LEFT JOIN `communities` AS b
					ON b.`community_id` = a.`community_id`
					WHERE a.`proxy_id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])."
					AND a.`member_active` = '1'
					AND b.`community_active` = '1'
					AND b.`community_template` <> 'course'
					ORDER BY b.`community_title` ASC
					LIMIT 0, 16";
		if($results = $db->CacheGetAll(CACHE_TIMEOUT, $query)) {
			$output .= "<ul>\n";
			foreach ($results as $key => $result) {
				if($key < 15) {
					$output .= "<li><a href=\"".ENTRADA_URL."/community".$result["community_url"]."\">".html_encode($result["community_title"])."</a></li>\n";
				} else {
					$output .= "<li><a href=\"".ENTRADA_URL."/communities\">...</a></li>\n";
					break;
				}
			}
			$output .= "</ul>\n";
		} else {
			$output .= "You are not yet a member of any communities.\n";
			$output .= "<ul>\n";
			$output .= "	<li><a href=\"".ENTRADA_URL."/communities\" style=\"font-size: 12px; font-weight: bold\">Click here to launch Communities</a></li>\n";
			$output .= "</ul>\n";
		}
		$output .= "					</td>\n";
		$output .= "					<td>\n";
		$output .= "						<h3>Community Announcements &amp; Events</h3>\n";
		$query		= "	SELECT a.`community_id`, a.`member_acl`, b.`community_url`, b.`community_title`
						FROM `community_members` AS a
						LEFT JOIN `communities` AS b
						ON a.`community_id` = b.`community_id`
						WHERE a.`proxy_id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])."
						AND a.`member_active` = '1'";
		$results	= $db->CacheGetAll(CACHE_TIMEOUT, $query);
		if($results) {
			$community_ids  = array();
			$page_ids		= array();

			foreach ($results as $result) {
				$community_ids[(int) $result["community_id"]] 	= array("url" => $result["community_url"], "title" => $result["community_title"]);
			}
			
			$query = "	SELECT a.`cpage_id` FROM `community_pages` AS a
						JOIN `communities` AS b
						ON a.`community_id` = b.`community_id`
						JOIN `community_members` AS c
						ON b.`community_id` = c.`community_id`
						AND c.`proxy_id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])."
						AND c.`member_active` = '1'
						WHERE (a.`allow_member_view` = '1')
						OR (c.`member_acl` = '1')";
			$cpage_ids = $db->GetAll($query);
			foreach ($cpage_ids as $page_id) {
				$community_pages[] = $page_id["cpage_id"];
			}
			foreach ($community_pages as $key => $page_id) {
				$page_ids[] = $page_id;
			}
				
			if(@count($community_ids)) {
				$query		= "	SELECT a.*, b.`page_url`
								FROM `community_announcements` as a
								LEFT JOIN `community_pages` as b
								ON a.`cpage_id` = b.`cpage_id`
								WHERE a.`community_id` IN ('".implode("', '", @array_keys($community_ids))."')
								AND a.`cpage_id` IN ('".implode("', '", $page_ids)."')
								AND a.`announcement_active` = '1'
								AND b.`page_active` = '1'
								AND (a.`release_date` = '0' OR a.`release_date` <= '".time()."')
								AND (a.`release_until` = '0' OR a.`release_until` > '".time()."')
								AND a.`pending_moderation` = '0'
								ORDER BY a.`release_date` DESC
								LIMIT 0, 3";
				$announcements	= $db->CacheGetAll(CACHE_TIMEOUT, $query);
				if($announcements) {
					$output .= "<ul class=\"history\" style=\"margin-left: 0px; margin-right: 5px; padding-left: 0px\">";
					$community_id = 0;
					foreach ($announcements as $key => $result) {
						if($result["community_id"] != $community_id) {
							$output .= "<li style=\"background-image: none; margin-left: 0px; padding-left: 0px\"><a href=\"".COMMUNITY_URL.$community_ids[$result["community_id"]]["url"]."\" style=\"font-weight: bold\">".html_encode($community_ids[$result["community_id"]]["title"])."</a></li>";
							$output .= "<li style=\"background-image: none; margin-left: 5px; padding-left: 5px\"><a href=\"".COMMUNITY_URL.$community_ids[$result["community_id"]]["url"].":".$result["page_url"]."\" style=\"font-weight: bold\">".html_encode($result["announcement_title"])."</a></li>";
							$community_id = $result["community_id"];
						} else {
							$output .= "<li style=\"background-image: none; margin-left: 5px; padding-left: 5px\"><a href=\"".COMMUNITY_URL.$community_ids[$result["community_id"]]["url"].":".$result["page_url"]."\" style=\"font-weight: bold\">".html_encode($result["announcement_title"])."</a></li>";
						}
					}
					$output .= "</ul>";
				}
			}

			if(@count($community_ids)) {
				$query		= "	SELECT a.*, b.`page_url`
								FROM `community_events` as a
								LEFT JOIN `community_pages` as b
								ON a.`cpage_id` = b.`cpage_id`
								WHERE a.`community_id` IN ('".implode("', '", @array_keys($community_ids))."')
								AND a.`event_active` = '1'
								AND b.`page_active` = '1'
								AND (a.`release_date` = '0' OR a.`release_date` <= '".time()."')
								AND (a.`release_until` = '0' OR a.`release_until` > '".time()."')
								AND (a.`event_finish` >= '".time()."')
								AND (a.`event_start` <= '".strtotime("+1 month")."')
								AND a.`pending_moderation` = '0'
								ORDER BY a.`event_start` DESC
								LIMIT 0, 3";
				$events	= $db->CacheGetAll(CACHE_TIMEOUT, $query);
				if($events) {
					$output .= "<ul class=\"history\" style=\"margin-left: 0px; margin-right: 5px; padding-left: 0px\">";
					$community_id = 0;
					foreach ($events as $key => $result) {
						if($result["community_id"] != $community_id) {
							$output .= "<li style=\"background-image: none; margin-left: 0px; padding-left: 0px\"><a href=\"".COMMUNITY_URL.$community_ids[$result["community_id"]]["url"]."\" style=\"font-weight: bold\">".html_encode($community_ids[$result["community_id"]]["title"])."</a></li>";
							$output .= "<li style=\"background-image: none; margin-left: 5px; padding-left: 5px\"><a href=\"".COMMUNITY_URL.$community_ids[$result["community_id"]]["url"].":".$result["page_url"]."\" style=\"font-weight: bold\">".html_encode($result["event_title"])."</a></li>";
							$community_id = $result["community_id"];
						} else {
							$output .= "<li style=\"background-image: none; margin-left: 5px; padding-left: 5px\"><a href=\"".COMMUNITY_URL.$community_ids[$result["community_id"]]["url"].":".$result["page_url"]."\" style=\"font-weight: bold\">".html_encode($result["event_title"])."</a></li>";
						}
					}
					$output .= "</ul>";
				}
			}

			if (!$events && !$announcements) {
				$output .= "There are no new announcements or events in any communities that you are a member of.\n";
			}
		} else {
			$output .= "You are not yet a member of any communities.\n";
			$output .= "<ul>\n";
			$output .= "	<li><a href=\"".ENTRADA_URL."/communities\" style=\"font-size: 12px; font-weight: bold\">Click here to launch Communities</a></li>\n";
			$output .= "</ul>\n";
		}
		$output .= "					</td>\n";
		$output .= "					<td>\n";
		$output .= "						<h3>My Profile</h3>\n";
		$uploaded_file_active = $db->GetOne("SELECT `photo_active` FROM `".AUTH_DATABASE."`.`user_photos` WHERE `photo_type` = 1 AND `proxy_id` = ".$db->qstr($_SESSION["details"]["id"]));
		$output .= "						<img src=\"".webservice_url("photo", array($_SESSION["details"]["id"], (isset($uploaded_file_active) && $uploaded_file_active ? "upload" : (!file_exists(STORAGE_USER_PHOTOS."/".$_SESSION["details"]["id"]."-official") && file_exists(STORAGE_USER_PHOTOS."/".$_SESSION["details"]["id"]."-upload") ? "upload" : "official"))))."\" width=\"72\" height=\"100\" alt=\"".html_encode($_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"])."\" title=\"".html_encode($_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"])."\" style=\"margin-top: 8px; background-color: #FFFFFF; border: 1px #EEEEEE solid\" />\n";
		$output .= "						<ul>\n";
		$output .= "							<li>".html_encode($_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"])."</li>\n";
		$output .= "							<li><a href=\"mailto:".html_encode($_SESSION["details"]["email"])."\">".html_encode($_SESSION["details"]["email"])."</a></li>\n";
		if($_SESSION['details']['group'] != 'guest') {
			$output .= "							<li><a href=\"".ENTRADA_URL."/profile\">Update Profile</a></li>\n";
		}
		$output .= "							<li style=\"margin-top: 15px\"><a href=\"".ENTRADA_URL."/?action=logout\" style=\"font-weight: bold\">Logout</a></li>\n";
		$output .= "						</ul>\n";
		$output .= "					</td>\n";
		$output .= "				</tr>\n";
		$output .= "			</tbody>\n";
		$output .= "			</table>\n";
		$output .= "		</div>\n";
		$output .= "	</div>\n";
		$output .= "	<div id=\"navigator-tab\">\n";
		$output .= "		<a href=\"javascript: void(0);\" onclick=\"new Effect.toggle('navigator', 'slide', { duration: 0.4 }); return false;\"><img src=\"".ENTRADA_RELATIVE."/images/nav_toggle.gif\" width=\"153\" height=\"29\" border=\"0\" alt=\"Toggle MEdTech Navigator\" title=\"Toggle MEdTech Navigator\" /></a>\n";
		$output .= "	</div>\n";
		$output .= "</div>\n";
	}

	return $output;
}

function navigator_tabs() {
	global $ENTRADA_ACL, $MODULE, $MODULES;

	if (!defined("MAX_NAV_TABS")) {
		$max_public = 9;
	} else {
		// Account for logout tab
		$max_public = MAX_NAV_TABS - 1;
	}

    //Add the admin stuff if needed
	$admin_priviledges = false;
	$admin_tabs	= array();

	//Check for the admin permission on each module
	foreach ($MODULES as $tab_name => $module_info) {
		if ($ENTRADA_ACL->amIAllowed($module_info["resource"], $module_info["permission"], false)) {
			$admin_tabs[] = "<li class=\"%".$tab_name."%\"><a href=\"".ENTRADA_URL."/admin/".$tab_name."\"><span>".html_encode(((isset($module_info["title"])) ? $module_info["title"] : ucwords(strtolower($tab_name))))."</span></a></li>\n";
			$admin_priviledges = true;
		}
	}

	if ($admin_priviledges) {
		$max_public--;
		if (defined("IN_ADMIN") && (IN_ADMIN == true)) {
			$tab_bold = " current";
			$admin_text = str_replace("%".$MODULE."%", "current", implode("\n", $admin_tabs));
		} else {
			$tab_bold = "";
			$admin_text = implode("\n", $admin_tabs);
		}
		
        $admin  = "<li class=\"admin staysput".$tab_bold."\" id=\"admin_tab\"><a href=\"#\" onclick=\"return false;\" id=\"admin_tab_link\"><span>Admin</span></a><ul class=\"drop_options\" id=\"admin_drop_options\">";
		$admin .= $admin_text;
		$admin .= "<li class=\"bottom\"><div>&nbsp;</div></li>";
		$admin .= "</ul><!--[if lte IE 6.5]><iframe src=\"".ENTRADA_RELATIVE."/blank.html\"></iframe><![endif]--></li>\n";
	}

	$PUBLIC_MODULES = array();
	$PUBLIC_MODULES[] = array("name" => "dashboard", "text" => "Dashboard");
	$PUBLIC_MODULES[] = array("name" => "communities", "text" => "Communities");
	$PUBLIC_MODULES[] = array("name" => "courses", "text" => "Courses");
	$PUBLIC_MODULES[] = array("name" => "tasks", "text" => "Tasks", "resource" => "tasktab", "permission" => "read");
	$PUBLIC_MODULES[] = array("name" => "events", "text" => "Learning Events");
	$PUBLIC_MODULES[] = array("name" => "clerkship", "text" => "Clerkship", "resource" => "clerkship", "permission" => "read");
	$PUBLIC_MODULES[] = array("name" => "objectives", "text" => "Curriculum Objectives", "resource" => "objectives", "permission" => "read");

	if (in_array($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"], array("student", "resident"))) {
		$PUBLIC_MODULES[] = array("name" => "regionaled", "text" => "Accommodations", "resource" => "regionaled_tab", "permission" => "read");
	}

	$PUBLIC_MODULES[] = array("name" => "search", "text" => "Curriculum Search");
	$PUBLIC_MODULES[] = array("name" => "people", "text" => "People Search");

	$PUBLIC_MODULES[] = array("name" => "annualreport", "text" => "Annual Report", "resource" => "annualreport", "permission" => "read");

    $PUBLIC_MODULES[] = array("name" => "profile", "text" => "My Profile");
	$PUBLIC_MODULES[] = array("name" => "library", "text" => "Library", "target" => "_blank");
	$PUBLIC_MODULES[] = array("name" => "help", "text" => "Help");

	$public_tabs = array();
	$more_tabs = array();
	$counter = 0;
	$more_bold = ""; // Keep track of bolding the more button when a tab within it is the current one
	$output = "";
	$extra = "";

	foreach ($PUBLIC_MODULES as $module) {
		$current = false;
		$class = array();

		if (isset($module["resource"]) && isset($module["permission"])) {
			if ($ENTRADA_ACL->amIAllowed($module["resource"], $module["permission"])) {
				$counter++;
			} else {
				continue;
			}
		} else {
			$counter++;
		}
		
		if ($counter == 1) {
			$class[] = "first";
		}

        if ($MODULE == $module["name"]) {
			$class[] = "current";
			$current = true;
		}
		
		$tab = "<li".(!empty($class) ? " class=\"".implode(" ", $class)."\"" : "")."><a href=\"".ENTRADA_URL."/".$module["name"]."\"><span>".$module["text"]."</span></a></li>\n";

		// Push excess public tabs into more
		if ($counter > $max_public) {
			$more_tabs[] = $tab;

			if ($current == true) {
				$more_bold = " current";
			}
		} else {
			$public_tabs[] = $tab;
		}
	}

	$public = implode("\n", $public_tabs);

	if (!empty($more_tabs)) {

		// Grab another tab into more to make space for the more tab within the max limit
		$more_tabs[] = array_pop($public_tabs);

		$more  = "";
		$more .= "<li class=\"more staysput".$more_bold."\" id=\"more_tab\"><a href=\"#\" onclick=\"return false;\"><span>More</span></a><ul class=\"drop_options\" id=\"more_drop_options\">";
		$more .= implode("\n", $more_tabs);
		$more .= "<li class=\"bottom\"><div></div></li>";
		$more .= "</ul><!--[if lte IE 6.5]><iframe src=\"".ENTRADA_RELATIVE."/blank.html\"></iframe><![endif]--></li>\n";
	}

	$public = implode("\n", $public_tabs);
	
	// Logout and button
	$extra .= "<li class=\"last staysput\"><a href=\"".ENTRADA_URL."?action=logout\"><span>Logout</span></a></li>\n";

	// Start output
	$output .= $public;
	
    if (isset($more)) {
		$output .= $more;
	}

	// Highlight current tab
	$output = str_replace("%".$MODULE."%", "current", $output);
	
    if ($admin_priviledges) {
		$output .= $admin;
	}

	$output .= $extra;
	
	// Get rid of place holders
	$output = preg_replace("/\%(.*)\%/", "", $output);

	return "<div id=\"screenTabs\"><div id=\"tabs\"><ul>".$output."</ul></div></div>";
}

/**
 * Function is called by on_checkout. Adds any breadcrumb menu items specified in $BREADCRUMB array.
 *
 * @param string $buffer
 * @return string buffer
 */
function check_breadcrumb($buffer) {
	global $BREADCRUMB;

	$i		= 1;
	$output	= "";

	if($total = @count($BREADCRUMB)) {
		@ksort($BREADCRUMB);

		$output .= "<div class=\"bread-crumb-trail\" id=\"bread-crumb-trail\">\n";
		$output .= "<ul>\n";
		foreach ($BREADCRUMB as $entry) {
			$output .= "<li>".((($i < $total) && ($entry["url"] != "")) ? "<a href=\"".$entry["url"]."\">" : "").html_encode($entry["title"]).((($i < $total) && ($entry["url"] != "")) ? "</a>" : "")."</li>\n";
			$i++;
		}
		$output .= "</ul>\n";
		$output .= "</div>\n";
	}
	return str_replace("%BREADCRUMB%", $output, $buffer);
}


/**
 * Constants for new_sidebar_item
 * SIDEBAR_APPEND - places the new item at the end of the *current* list of sidebar items
 * SIDEBAR_PREPEND - places the new item at the beginning of the *current* list of items 
 */
if(!defined("SIDEBAR_APPEND")) {
	define("SIDEBAR_APPEND", 0);
}

if(!defined("SIDEBAR_PREPEND")) {
	define("SIDEBAR_PREPEND", 1);
}

/**
 * Function that generates standard sidebar items. It adds them to the $SIDEBAR array which
 * will is processed by the check_sidebar() function through on_checkout() as a callback function.
 *
 * @example new_sidebar_item("Kingston Weather", "This is the content", "weather-widget", "open", SIDEBAR_APPEND);
 * @param string $title
 * @param string $html
 * @param string $id
 * @param string $state
 * @param int $position
 * @return true
 */
function new_sidebar_item($title = "", $html = "", $id = "", $state = "open", $position = SIDEBAR_APPEND) {
	global $SIDEBAR, $NOTICE, $NOTICESTR;


	$state	= (($state == "open") ? $state : "close");
	$id		= (($id == "") ? "sidebar-".$weight : $id);
/* //If moving to layout without tables
	$output = "<div class=\"sidebar\" id=\"".html_encode($id)."\">";
	$output .= "<span class=\"sidebar-head\">".html_encode($title)."</span>\n";
	$output .= "<div class=\"sidebar-body\">".$html."</div>\n";
	$output .= "</div><br />\n";*/
	
	$output  = "<table class=\"sidebar\" id=\"".html_encode($id)."\" cellspacing=\"0\" summary=\"".html_encode($title)."\">\n";
	$output .= "<thead>\n";
	$output .= "	<tr>\n";
	$output .= "		<td class=\"sidebar-head\">".html_encode($title)."</td>\n";
	$output .= "	</tr>\n";
	$output .= "</thead>\n";
	$output .= "<tbody>\n";
	$output .= "	<tr>\n";
	$output .= "		<td class=\"sidebar-body\">".$html."</td>\n";
	$output .= "	</tr>\n";
	$output .= "</tbody>\n";
	$output .= "</table>\n";
	$output .= "<br />\n";

	switch($position) {
		case SIDEBAR_PREPEND:
			array_unshift($SIDEBAR, $output);
			break;
		case SIDEBAR_APPEND:
		default:
			array_push($SIDEBAR, $output);
	}
	
	return true;
}


/**
 * Clears all open buffers so you can start with a clean page. This function is
 * primarily used as a method to handle AJAX requests cleanly.
 *
 * @return true
 */
function ob_clear_open_buffers() {
	$level = @ob_get_level();

	for ($i = 0; $i <= $level; $i++) {
		@ob_end_clean();
	}

	return true;
}

/**
 * Multi-dimensional array search which performs a similar task to that of
 * array_search; however, it allows for multi-dimensional arrays.
 *
 * @param string $needle
 * @param array $haystack
 * @return bool or array
 */
function dimensional_array_search($needle, $haystack) {
	$value	= false;
	$x		= 0;
	foreach ($haystack as $temp) {
		if(is_array($temp)) {
			$search = array_search($needle, $temp);
			if(strlen($search) > 0 && $search >= 0) {
				$value[0] = $x;
				$value[1] = $search;
			}
		}
		$x++;
	}
	return $value;
}

/**
 * Provides URL's to different internal web-services.
 *
 * @param string $service
 * @param array $options
 * @return string
 */
function webservice_url($service = "", $options = array()) {
	switch($service) {
		case "gender" :
			return ENTRADA_URL."/api/gender.api.php/".$_SESSION["details"]["group"]."/".$options["number"];
		break;
		case "photo" :
			return ENTRADA_URL."/api/photo.api.php/".implode("/", $options);
		break;
		case "clerkship_department" :
			return ENTRADA_URL."/api/clerkship_department.api.php";
		break;
		case "clerkship_prov" :
			return ENTRADA_URL."/api/clerkship_prov.api.php";
		break;
		case "province" :
			return ENTRADA_URL."/api/province.api.php";
		break;
		case "mspr-admin" :
			return ENTRADA_URL."/admin/users/manage/students?section=api-mspr";
		break;
		case "mspr-profile" :
			return ENTRADA_URL."/profile?section=api-mspr";
		break;
		case "awards" :
			return ENTRADA_URL."/admin/awards?section=api-awards";
		break;
		case "personnel" :
			return ENTRADA_URL."/api/personnel.api.php";
		break;
		default :
			return "";
		break;
	}
}

// Function that checks to see if magic_quotes_gpc is enabled or not.
function checkslashes($value="", $type = "insert") {
	switch($type) {
		case "insert" :
			if(!ini_get("magic_quotes_gpc")) {
				return addslashes($value);
			} else {
				return $value;
			}
			break;
		case "display" :
			if(!ini_get("magic_quotes_gpc")) {
				return htmlspecialchars($value);
			} else {
				return htmlspecialchars(stripslashes($value));
			}
			break;
		default :
			return false;
			break;
	}
}

/**
 * Function that returns data from the authentication database.
 *
 * @param string $type
 * @param int $id
 * @return string
 */
function get_account_data($type = "", $id = 0) {
	global $db;

	if($id = (int) trim($id)) {
		switch(strtolower($type)) {
			case "firstname" :
				$query = "SELECT `firstname` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=".$db->qstr($id);
			break;
			case "lastname" :
				$query = "SELECT `lastname` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=".$db->qstr($id);
			break;
			case "fullname" :
			case "lastfirst" :
				$query = "SELECT CONCAT_WS(', ', `lastname`, `firstname`) AS `fullname` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=".$db->qstr($id);
			break;
			case "wholename" :
			case "firstlast" :
				$query = "SELECT CONCAT_WS(' ', `firstname`, `lastname`) AS `firstlast` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=".$db->qstr($id);
			break;
			case "email" :
				$query = "SELECT `email` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=".$db->qstr($id);
			break;
			case "username" :
				$query = "SELECT `username` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=".$db->qstr($id);
			break;
			case "role" :
				$query = "SELECT `role` FROM `".AUTH_DATABASE."`.`user_access` WHERE `user_id`=".$db->qstr($id)." AND `app_id`=".$db->qstr(AUTH_APP_ID);
			break;
			case "group" :
				$query = "SELECT `group` FROM `".AUTH_DATABASE."`.`user_access` WHERE `user_id`=".$db->qstr($id)." AND `app_id`=".$db->qstr(AUTH_APP_ID);
			break;
			default :
				return "";
			break;
		}

		$result = ((USE_CACHE) ? $db->CacheGetRow(LONG_CACHE_TIMEOUT, $query) : $db->GetRow($query));
		if (!$result && strtolower($type) == "role") {
			$query = "SELECT `role` FROM `".AUTH_DATABASE."`.`user_access` WHERE `user_id` = ".$db->qstr($id)." AND `app_id` IN (".AUTH_APP_IDS_STRING.")";
			$result = ((USE_CACHE) ? $db->CacheGetRow(LONG_CACHE_TIMEOUT, $query) : $db->GetRow($query));
		} elseif (!$result && strtolower($type) == "group") {
			$query = "SELECT `group` FROM `".AUTH_DATABASE."`.`user_access` WHERE `user_id` = ".$db->qstr($id)." AND `app_id` IN (".AUTH_APP_IDS_STRING.")";
			$result = ((USE_CACHE) ? $db->CacheGetRow(LONG_CACHE_TIMEOUT, $query) : $db->GetRow($query));
		}
		return $result[$type];
	} else {
		return "";
	}
}

/**
 * Function will return the online status of a particular proxy_id. You have the choice of the output,
 * either text (default), image or an integer.
 * 0 = offline
 * 1 = away
 * 2 = online
 *
 * @param int $proxy_id
 * @param str $type
 * @return depends on the $output_type variable.
 */
function get_online_status($proxy_id = 0, $output_type = "text") {
	global $db;

	$output = 0;

	if($proxy_id = (int) trim($proxy_id)) {
		$query	= "
				SELECT MAX(`timestamp`) AS `timestamp`
				FROM `users_online`
				WHERE `proxy_id` = ".$db->qstr($proxy_id)."
				GROUP BY `proxy_id`";
		$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
		if(($result) && ((int) $result["timestamp"])) {
			if((int) $result["timestamp"] < (time() - 600)) {
				$output = 1;
			} else {
				$output = 2;
			}
		}
	}

	switch($output_type) {
		case "text" :
			switch($output) {
				case 1 :
					$output = "status-away";
					break;
				case 2 :
					$output = "status-online";
					break;
				default :
					$output = "status-offline";
					break;
			}
			break;
		case "image" :
			switch($output) {
				case 1 :
					$output = "<img src=\"".ENTRADA_RELATIVE."/images/list-status-away.gif\" width=\"12\" height=\"12\" alt=\"Online Status: Away\" title=\"Online Status: Away\" style=\"vertical-align: middle\" />";
					break;
				case 2 :
					$output = "<img src=\"".ENTRADA_RELATIVE."/images/list-status-online.gif\" width=\"12\" height=\"12\" alt=\"Online Status: Online\" title=\"Online Status: Online\" style=\"vertical-align: middle\" />";
					break;
				default :
					$output = "<img src=\"".ENTRADA_RELATIVE."/images/list-status-offline.gif\" width=\"12\" height=\"12\" alt=\"Online Status: Offline\" title=\"Online Status: Offline\" style=\"vertical-align: middle\" />";
					break;
			}
			break;
		case "int" :
		default :
			continue;
			break;
	}

	return $output;
}

/**
 * Handy function that takes the QUERY_STRING and adds / modifies / removes elements from it
 * based on the $modify array that is provided.
 *
 * @param array $modify
 * @return string
 * @example echo "index.php?".replace_query(array("action" => "add", "step" => 2));
 */
function replace_query($modify = array(), $html_encode_output = false) {
	$process	= array();
	$tmp_string	= array();
	$new_query	= "";

	// Checks to make sure there is something to modify, else just returns the string.
	if(count($modify) > 0) {
		$original	= explode("&", $_SERVER["QUERY_STRING"]);
		if(count($original) > 0) {
			foreach ($original as $value) {
				$pieces = explode("=", $value);
				// Gets rid of any unset variables for the URL.
				if(isset($pieces[0]) && isset($pieces[1])) {
					$process[$pieces[0]] = $pieces[1];
				}
			}
		}

		foreach ($modify as $key => $value) {
		// If the variable already exists, replace it, else add it.
			if(array_key_exists($key, $process)) {
				if(($value === 0) || (($value) && ($value !=""))) {
					$process[$key] = $value;
				} else {
					unset($process[$key]);
				}
			} else {
				if(($value === 0) || (($value) && ($value !=""))) {
					$process[$key] = $value;
				}
			}
		}
		if(count($process) > 0) {
			foreach ($process as $var => $value) {
				$tmp_string[] = $var."=".$value;
			}
			$new_query = implode("&", $tmp_string);
		} else {
			$new_query = "";
		}
	} else {
		$new_query = $_SERVER["QUERY_STRING"];
	}

	return (((bool) $html_encode_output) ? html_encode($new_query) : $new_query);
}

/**
 * Here for historical reasons.
 *
 */
function order_link($field, $name, $order, $sort, $location = "public") {
	switch($location) {
		case "admin" :
			return admin_order_link($field, $name);
			break;
		case "public" :
		default :
			return public_order_link($field, $name);
			break;
	}
}

/**
 * This function handles sorting and ordering for the public modules.
 *
 * @param string $field_id
 * @param string $field_name
 * @return string
 */
function public_order_link($field_id, $field_name) {
	global $MODULE;

	if(strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]) == strtolower($field_id)) {
		if(strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"]) == "desc") {
			return "<a href=\"".ENTRADA_URL."/".$MODULE."?".replace_query(array("so" => "asc"))."\" title=\"Order by ".$field_name.", Sort Ascending\">".$field_name."</a>";
		} else {
			return "<a href=\"".ENTRADA_URL."/".$MODULE."?".replace_query(array("so" => "desc"))."\" title=\"Order by ".$field_name.", Sort Decending\">".$field_name."</a>";
		}
	} else {
		return "<a href=\"".ENTRADA_URL."/".$MODULE."?".replace_query(array("sb" => $field_id))."\" title=\"Order by ".$field_name."\">".$field_name."</a>";
	}
}

/**
 * This function handles sorting and ordering for the public modules.
 *
 * @param string $field_id
 * @param string $field_name
 * @return string
 */
function community_public_order_link($field_id, $field_name, $url) {

	if(strtolower($_SESSION[APPLICATION_IDENTIFIER]["community_page"]["sb"]) == strtolower($field_id)) {
		if(strtolower($_SESSION[APPLICATION_IDENTIFIER]["community_page"]["so"]) == "desc") {
			return "<a href=\"".$url."?".replace_query(array("so" => "asc"))."\" title=\"Order by ".$field_name.", Sort Ascending\">".$field_name."</a>";
		} else {
			return "<a href=\"".$url."?".replace_query(array("so" => "desc"))."\" title=\"Order by ".$field_name.", Sort Decending\">".$field_name."</a>";
		}
	} else {
		return "<a href=\"".$url."?".replace_query(array("sb" => $field_id))."\" title=\"Order by ".$field_name."\">".$field_name."</a>";
	}
}

/**
 * This function handles sorting and ordering for the administration modules.
 *
 * @param string $field_id
 * @param string $field_name
 * @return string
 */
function admin_order_link($field_id, $field_name, $submodule = null) {
	global $MODULE;
	if(isset($submodule)) {
		$module_url = $MODULE . "/" . $submodule;
	} else {
		$module_url = $MODULE;
	}
	if(strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]) == strtolower($field_id)) {
		if(strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"]) == "desc") {
			return "<a href=\"".ENTRADA_URL."/admin/".$module_url."?".replace_query(array("so" => "asc"))."\" title=\"Order by ".$field_name.", Sort Ascending\">".$field_name."</a>";
		} else {
			return "<a href=\"".ENTRADA_URL."/admin/".$module_url."?".replace_query(array("so" => "desc"))."\" title=\"Order by ".$field_name.", Sort Decending\">".$field_name."</a>";
		}
	} else {
		return "<a href=\"".ENTRADA_URL."/admin/".$module_url."?".replace_query(array("sb" => $field_id))."\" title=\"Order by ".$field_name."\">".$field_name."</a>";
	}
}

/**
 * This function handles sorting and ordering for the public modules.
 *
 * @param string $field_id
 * @param string $field_name
 * @return string
 */
function community_order_link($field_id, $field_name) {
	global $PAGE_URL, $COMMUNITY_URL, $COMMUNITY_ID;

	if(strtolower($_SESSION[APPLICATION_IDENTIFIER]["cid_".$COMMUNITY_ID][$PAGE_URL]["sb"]) == strtolower($field_id)) {
		if(strtolower($_SESSION[APPLICATION_IDENTIFIER]["cid_".$COMMUNITY_ID][$PAGE_URL]["so"]) == "desc") {
			return "<a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":members?".replace_query(array("sb" => $field_id, "so" => "asc"))."\" title=\"Order by ".$field_name.", Sort Ascending\">".$field_name."</a>";
		} else {
			return "<a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":members?".replace_query(array("sb" => $field_id, "so" => "desc"))."\" title=\"Order by ".$field_name.", Sort Decending\">".$field_name."</a>";
		}
	} else {
		return "<a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":members?".replace_query(array("sb" => $field_id))."\" title=\"Order by ".$field_name."\">".$field_name."</a>";
	}
}

/**
 * Function will return the actual full title text of the filter key.
 *
 * @param string $filter_key
 * @return string
 */
function filter_name($filter_key) {
	switch($filter_key) {
		case "teacher":
			return "Teachers Involved";
		break;
		case "student":
			return "Students Involved";
		break;
		case "grad":
			return "Graduating Years Involved";
		break;
		case "phase":
			return "Phases Involved";
		break;
		case "course":
			return "Courses Involved";
		break;
		case "eventtype":
			return "Event Types";
		break;
		case "organisation":
			return "Organisations Involved";
		break;
		default :
			return false;
		break;
	}
}

/**
 * Returns the starting second's timestamp of the $type[day || week || month || year].
 *
 * @param string $type
 * @param integer $timestamp
 * @return integer
 */
function startof($type, $timestamp = 0) {
	if(!(int) $timestamp) {
		$timestamp = time();
	}

	switch($type) {
		case "all" :
			return false;
			break;
		case "day" :
			return mktime(0, 0, 0, date("n", $timestamp), date("j", $timestamp), date("Y", $timestamp));
			break;
		case "month" :
			return mktime(0, 0, 0, date("n", $timestamp), 1, date("Y", $timestamp));
			break;
		case "year" :
			return mktime(0, 0, 0, 1, 1, date("Y", $timestamp));
			break;
		case "week" :
		default :
			return mktime(0, 0, 0, date("n", $timestamp), (date("j", $timestamp) - date("w", $timestamp)), date("Y", $timestamp));
			break;
	}
}

/**
 * This function returns the provided template file usith the method passed.
 * 
 * @param string $template_file
 * @param string $fetch_style
 * @example $template_html = fetch_template("globa/external");
 * @return string
 */
function fetch_template($template_file = "", $fetch_style = "filesystem") {
	if (($template_file) && ($template_file = clean_input($template_file, "dir"))) {
		$template_file = TEMPLATE_ABSOLUTE."/".$template_file.".tpl.php";
		if (@file_exists($template_file)) {
			switch ($fetch_style) {
				case "url" :
					return @file_get_contents(TEMPLATE_URL."/".$template_file.".tpl.php");
				break;
				case "filesystem" :
				default :
					return @file_get_contents($template_file);
				break;
			}
		}
	}

	return false;
}

/**
 * This function returns the title of the event type based on the provided id.
 *
 * @param int $eventtype_id
 * @return string
 */
function fetch_eventtype_title($eventtype_id = 0) {
	global $db;

	if($eventtype_id = (int) $eventtype_id) {
		$query	= "SELECT `eventtype_title` FROM `events_lu_eventtypes` WHERE `eventtype_id` = ".$db->qstr($eventtype_id);
		$result	= $db->GetRow($query);
		if ($result) {
			return $result["eventtype_title"];
		}
	}

	return false;
}

/**
 * This function returns arrays of the requested resources from a learning event.
 * 
 * @global object $db
 * @param int $event_id
 * @param array $options
 * @return array
 */
function fetch_event_resources($event_id = 0, $options = array()) {
	global $db;

	$fetch_files = false;
	$fetch_links = false;
	$fetch_quizzes = false;
	$fetch_discussions = false;
	$fetch_types = false;
	$output = array();

	if ($event_id = (int) $event_id) {
		if (is_scalar($options)) {
			if (trim($options) != "") {
				$options = array($options);
			} else {
				$options = array();
			}
		}

		if (!count($options)) {
			$options = array("all");
		}

		if (in_array("all", $options)) {
			$fetch_files = true;
			$fetch_links = true;
			$fetch_quizzes = true;
			$fetch_discussions = true;
			$fetch_types = true;
		}

		if (in_array("files", $options)) {
			$fetch_files = true;
		}

		if (in_array("links", $options)) {
			$fetch_links = true;
		}

		if (in_array("quizzes", $options)) {
			$fetch_quizzes = true;
		}

		if (in_array("discussions", $options)) {
			$fetch_discussions = true;
		}

		if (in_array("types", $options)) {
			$fetch_types = true;
		}

		if ($fetch_files) {
			/**
			 * This query will get all of the files associated with this event.
			 */
			$query	= "	SELECT a.*, MAX(b.`timestamp`) AS `last_visited`
						FROM `event_files` AS a
						LEFT JOIN `statistics` AS b
						ON b.`module` = 'events'
						AND b.`proxy_id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])."
						AND b.`action` = 'file_download'
						AND b.`action_field` = 'file_id'
						AND b.`action_value` = a.`efile_id`
						WHERE a.`event_id` = ".$db->qstr($event_id)."
						GROUP BY a.`efile_id`
						ORDER BY a.`file_category` ASC, a.`file_title` ASC";
			$output["files"] = $db->GetAll($query);
		}

		if ($fetch_links) {
			/**
			 * This query will retrieve all of the links associated with this evevnt.
			 */
			$query	= "	SELECT a.*, MAX(b.`timestamp`) AS `last_visited`
						FROM `event_links` AS a
						LEFT JOIN `statistics` AS b
						ON b.`module` = 'events'
						AND b.`proxy_id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])."
						AND b.`action` = 'link_access'
						AND b.`action_field` = 'link_id'
						AND b.`action_value` = a.`elink_id`
						WHERE a.`event_id` = ".$db->qstr($event_id)."
						GROUP BY a.`elink_id`
						ORDER BY a.`link_title` ASC";
			$output["links"] = $db->GetAll($query);
		}

		if ($fetch_quizzes) {

			/**
			 * This query will retrieve all of the quizzes associated with this evevnt.
			 */
			$query	= "	SELECT a.*, b.`quiztype_code`, b.`quiztype_title`, MAX(c.`timestamp`) AS `last_visited`
						FROM `event_quizzes` AS a
						LEFT JOIN `quizzes_lu_quiztypes` AS b
						ON b.`quiztype_id` = a.`quiztype_id`
						LEFT JOIN `statistics` AS c
						ON c.`module` = 'events'
						AND c.`proxy_id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])."
						AND c.`action` = 'quiz_complete'
						AND c.`action_field` = 'equiz_id'
						AND c.`action_value` = a.`equiz_id`
						WHERE a.`event_id` = ".$db->qstr($event_id)."
						GROUP BY a.`equiz_id`
						ORDER BY a.`required` DESC, a.`quiz_title` ASC, a.`release_until` ASC";
			$output["quizzes"] = $db->GetAll($query);
		}

		if ($fetch_discussions) {
			/**
			 * This query will retrieve all discussions associated with this event.
			 */
			$query	= "	SELECT *
						FROM `event_discussions`
						WHERE `event_id` = ".$db->qstr($event_id)."
						AND `discussion_comment` <> ''
						AND `discussion_active` = '1'
						ORDER BY `ediscussion_id` ASC";
			$output["discussions"] = $db->GetAll($query);
		}

		if ($fetch_types) {
			$query	= "	SELECT *
						FROM `event_eventtypes` AS `types`
						LEFT JOIN `events_lu_eventtypes` AS `lu_types`
						ON `types`.`eventtype_id` = `lu_types`.`eventtype_id`
						WHERE `types`.`event_id` = ".$db->qstr($event_id)."
						ORDER BY `types`.`eeventtype_id` ASC";
			$output["types"] = $db->GetAll($query);
		}

		return $output;
	}

	return false;
}

function fetch_organisation_title($organisation_id = 0) {
	global $db;
	if($organisation_id = (int) $organisation_id) {
		$query	= "SELECT `organisation_title` FROM `".AUTH_DATABASE."`.`organisations` WHERE `organisation_id` = ".$db->qstr($organisation_id);
		$result	= $db->GetRow($query);
		if ($result) {
			return $result["organisation_title"];
		}
	}

	return false;
}

function fetch_objective_title($objective_id = 0) {
	global $db;
	if($objective_id = (int) $objective_id) {
		$query	= "SELECT `objective_name` FROM `global_lu_objectives` WHERE `objective_id` = ".$db->qstr($objective_id);
		$result	= $db->GetRow($query);
		if ($result) {
			return $result["objective_name"];
		}
	}

	return false;
}

function fetch_mcc_objectives($parent_id = 0, $objectives = array(), $course_id = 0, $objective_ids = false) {
	global $db;
	
	if ($parent_id) {
		$where = " WHERE `objective_parent` = ".$db->qstr($parent_id);
	} else {
		$where = " WHERE `objective_name` LIKE 'MCC Objectives'";
	}
	
	if ($course_id) {
		$query = "	SELECT `objective_id` 
					FROM `course_objectives`
					WHERE `course_id` = ".$db->qstr($course_id)."
					AND `objective_type` = 'event'";
		$allowed_objectives = $db->GetAll($query);
		$objective_ids = array();
		if (isset($allowed_objectives) && is_array($allowed_objectives) && count($allowed_objectives)) {
			foreach ($allowed_objectives as $objective) {
				$objective_ids[] = $objective["objective_id"];
			}
		}
	}
	
	$query = "SELECT * FROM `global_lu_objectives`".$where;
	$results = $db->GetAll($query);
	if ($results) {
		foreach ($results as $result) {
			if ($parent_id) {
				$objectives[] = $result;
			}
			$objectives = fetch_mcc_objectives($result["objective_id"], $objectives, 0, (isset($objective_ids) && $objective_ids ? $objective_ids : array()));
		}
	}
	if (!$parent_id && is_array($objective_ids)) {
		foreach ($objectives as $key => $objective) {
			if (array_search($objective["objective_id"], $objective_ids) === false) {
				unset($objectives[$key]);
			}
		}
	}

	return $objectives;
}

/**
 * Function returns the graduating year of the first year class. This year is
 * frequently used used as a default or fallback throughout Entrada.
 */
function fetch_first_year() {
	/**
	 * This is based on a 4 year program with a year cut-off of July 1.
	 * @todo This should be in the settings.inc.php file.
	 */
	return date("Y") + (date("m") < 7 ? 3 : 4);
}

/**
 * This function provides the unix timestamps of the start and end of the requested date type.
 *
 * @uses startof()
 * @param string $type
 * @param int $timestamp
 * @return array or false if $type = all
 */
function fetch_timestamps($type, $timestamp_start, $timestamp_finish = 0) {
	$start = startof($type, $timestamp_start);

	switch($type) {
		case "all" :
			return false;
		break;
		case "day" :
		case "week" :
		case "month" :
		case "year" :
			return array("start" => $start, "end" => (strtotime("+1 ".$type, $start) - 1));
		break;
		case "custom" :
			return array("start" => $timestamp_start, "end" => $timestamp_finish);
		break;
		default :
			return array("start" => $start, "end" => (strtotime("+1 week", $start) - 1));
		break;
	}
}

/**
 * Function will return the department title based on the provided department_id.
 * @param int $department_id
 * @return string or bool
 */
function fetch_department_title($department_id = 0) {
	global $db;

	if($department_id = (int) $department_id) {
		$query	= "SELECT `department_title` FROM `".AUTH_DATABASE."`.`departments` WHERE `department_id` = ".$db->qstr($department_id);
		$result	= $db->GetRow($query);

		if(($result) && ($department_title = trim($result["department_title"]))) {
			return $department_title;
		}
	}
	return false;
}

/**
 * Function will return the parent_id based on the provided department_id.
 * @param int $department_id
 * @return parent_id or bool
 */
function fetch_department_parent($department_id = 0) {
	global $db;

	if($department_id = (int) $department_id) {
		$query	= "SELECT `parent_id` FROM `".AUTH_DATABASE."`.`departments` WHERE `department_id` = ".$db->qstr($department_id);
		$result	= $db->GetRow($query);

		if(($result)) {
			return $result["parent_id"];
		}
	}
	return false;
}

/**
 * Function will return the children of a department (i.e. divisions) based on the provided department_id.
 * @param int $department_id
 * @return array(department IDs) or bool (false)
 */
function fetch_department_children($department_id = 0) {
	global $db;

	if($department_id = (int) $department_id) {
		$query	= "SELECT `department_id` FROM `".AUTH_DATABASE."`.`departments` WHERE `parent_id` = ".$db->qstr($department_id);
		$results = $db->GetAll($query);

		if(($results)) {
			return $results;
		} else {
			return false;
		}
	}
	return false;
}

/**
 * Function will return a list of Countries.
 * @param none
 * @return resultset(countries_id, country) or bool
 */
function fetch_countries() {
	global $db;
	
	$query = "	SELECT *
				FROM `".DATABASE_NAME."`.`global_lu_countries`
				ORDER BY `country` ASC";
	
	if ($results = $db->GetAll($query)) {
		return $results;
	} else {
		return false;
	}
}

/**
 * Function will return a specific Country from the list of Countries.
 * @param $countries_id
 * @return resultset(country) or bool
 */
function fetch_specific_country($countries_id) {
	global $db;
		
	$query	= "	SELECT `country`
				FROM `".DATABASE_NAME."`.`global_lu_countries`
				WHERE `countries_id` =".$db->qstr($countries_id);
	
	if ($result = $db->GetRow($query)) {
		return $result["country"];
	} else {
		return false;
	}
}

/**
 * Function will return the number of sub-categories under the ID you specify..
 * @param $category_parent
 * @return total number of children under category parent
 */
function clerkship_categories_children_count($category_parent = 0) {
	global $db;

	$query	= "SELECT COUNT(*) AS `total` FROM `categories` WHERE `category_parent`=".$db->qstr($category_parent);
	$result	= $db->GetOne($query);

	return $result;
}

/**
 * Function will return a list of Clerkship Disciplines.
 * @param none
 * @return resultset(discipline_id, discipline) or bool
 */
function clerkship_fetch_disciplines() {
	global $db;

	$query	= "SELECT *
		FROM `".DATABASE_NAME."`.`global_lu_disciplines`
		ORDER BY `discipline` ASC";

	if($results	= $db->GetAll($query)) {
		return $results;
	} else {
		return false;
	}
}

/**
 * Function will return a specific Clerkship Discipline.
 * @param $discipline_id
 * @return resultset(discipline) or bool
 */
function clerkship_fetch_specific_discipline($discipline_id) {
	global $db;

	$query	= "SELECT `discipline`
		FROM `".DATABASE_NAME."`.`global_lu_disciplines`
		WHERE `discipline_id` =".$db->qstr($discipline_id);

	if($result = $db->GetRow($query)) {
		return $result['discipline'];
	} else {
		return false;
	}
}

/**
 * Function will return a list of schools.
 * @param $discipline_id
 * @return resultset(disciplines) or bool
 */
function clerkship_fetch_schools() {
	global $db;
		
	$query		= "	SELECT *
					FROM `global_lu_schools`
					ORDER BY `school_title`";
	$results	= $db->GetAll($query);
	if ($results) {
		return $results;
	}

	return false;
}

/**
 * Function will return a specfic school school_title from global_lu_schools.
 * @param $schools_id
 * @return resultset(school_title) or bool
 */
function clerkship_fetch_specific_school($schools_id) {
	global $db;
		
	$query		= "	SELECT `school_title`
					FROM `global_lu_schools`
					WHERE `schools_id` =".$db->qstr($schools_id);
	$result	= $db->GetRow($query);
	if ($result) {
		return $result["school_title"];
	}

	return false;
}

/**
 * This function will load users module preferences into a session from the database table.
 * It also returns the preferences as an array, so they can be later compared to see if a
 * preferences_update() is required.
 *
 * @param string $module
 * @return array
 */
function preferences_load($module) {
	global $db;

	if(!isset($_SESSION[APPLICATION_IDENTIFIER][$module])) {
		$query	= "SELECT `preferences` FROM `".AUTH_DATABASE."`.`user_preferences` WHERE `app_id`=".$db->qstr(AUTH_APP_ID)." AND `proxy_id`=".$db->qstr($_SESSION["details"]["id"])." AND `module`=".$db->qstr($module);
		$result	= $db->GetRow($query);
		if($result) {
			if($result["preferences"]) {
				$preferences = @unserialize($result["preferences"]);
				if(@is_array($preferences)) {
					$_SESSION[APPLICATION_IDENTIFIER][$module] = $preferences;
				}
			}
		}
	}

	return ((isset($_SESSION[APPLICATION_IDENTIFIER][$module])) ? $_SESSION[APPLICATION_IDENTIFIER][$module] : array());
}

/**
 * This function will gather any associated permissions assigned by other individuals to this
 * user's account.
 *
 * @return array
 */
function permissions_load() {
	global $db;
	$permissions	= array();
	$permissions[$_SESSION["details"]["id"]] = array("permission_id" => 0, "group" => $_SESSION["details"]["group"], "role" => $_SESSION["details"]["role"], "organisation_id"=>$_SESSION["details"]["organisation_id"], "starts" => $_SESSION["details"]["access_starts"], "expires" => $_SESSION["details"]["access_expires"], "fullname" => ($_SESSION["details"]["lastname"].", ".$_SESSION["details"]["firstname"]), "firstname" => $_SESSION["details"]["firstname"], "lastname" => $_SESSION["details"]["lastname"]);
	$query		= "
				SELECT a.*, b.`id` AS `proxy_id`, CONCAT_WS(', ', b.`lastname`, b.`firstname`) AS `fullname`, b.`firstname`, b.`lastname`, b.`organisation_id`, c.`role`, c.`group`
				FROM `permissions` AS a
				LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
				ON b.`id` = a.`assigned_by`
				LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS c
				ON c.`user_id` = b.`id` AND c.`app_id`=".$db->qstr(AUTH_APP_ID)."
				AND c.`account_active`='true'
				AND (c.`access_starts`='0' OR c.`access_starts`<=".$db->qstr(time()).")
				AND (c.`access_expires`='0' OR c.`access_expires`>=".$db->qstr(time()).")
				WHERE a.`assigned_to`=".$db->qstr($_SESSION["details"]["id"])." AND a.`valid_from`<=".$db->qstr(time())." AND a.`valid_until`>=".$db->qstr(time())."
				ORDER BY `fullname` ASC";
	$results		= $db->GetAll($query);
	if($results) {
		foreach ($results as $result) {
			$permissions[$result["proxy_id"]] = array("permission_id" => $result["permission_id"], "group" => $result["group"], "role" => $result["role"], "organisation_id"=>$result['organisation_id'],  "starts" => $result["valid_from"], "expires" => $result["valid_until"], "fullname" => $result["fullname"], "firstname" => $result["firstname"], "lastname" => $result["lastname"]);
		}
	}
	return $permissions;
}

/**
 * medtech / staff wants in.
 * Page requires medtech / admin.
 *
 * @param array $requirements
 * @return bool
 * @example permissions_check(array("medtech" => "*", "faculty => array("faculty", "admin"), "staff" => "admin"));
 */
function permissions_check($requirements = array()) {
	if((is_array($requirements)) && (count($requirements)) && (is_array($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]))) {
		foreach ($requirements as $group => $roles) {
			if($group == "*") {
				if($roles == "*") {
					return true;
				} else {
					if(!@is_array($roles)) {
						$roles = array($roles);
					}

					if(@in_array($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["role"], $roles)) {
						return true;
					}
				}
			} else {
				if($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"] == $group) {
					if($roles == "*") {
						return true;
					} else {
						if(!@is_array($roles)) {
							$roles = array($roles);
						}

						if(@in_array($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["role"], $roles)) {
							return true;
						}
					}
				}
			}
		}
	}

	return false;
}

function permissions_fetch($identifier, $type = "event", $existing_allowed_ids = array()) {
	global $db;

	if((is_array($existing_allowed_ids)) && (count($existing_allowed_ids))) {
		$allowed_ids	= $existing_allowed_ids;
	} else {
		$allowed_ids	= array();
	}

	switch($type) {
		case "event" :
			$query		= "	SELECT a.`event_id`, b.`proxy_id` AS `teacher`, c.`pcoord_id` AS `coordinator`, d.`proxy_id` AS `director`, e.`proxy_id` AS `ccoordinator`
							FROM `events` AS a
							LEFT JOIN `event_contacts` AS b
							ON b.`event_id` = a.`event_id`
							LEFT JOIN `courses` AS c
							ON c.`course_id` = a.`course_id`
							LEFT JOIN `course_contacts` AS d
							ON d.`course_id` = c.`course_id`
							AND `contact_type` = 'director'
							LEFT JOIN `course_contacts` AS e
							ON e.`course_id` = c.`course_id`
							AND `contact_type` = 'ccoordinator'
							WHERE a.`event_id` = ".$db->qstr($identifier)."
							AND c.`course_active` = '1'";
			$results	= $db->GetAll($query);
			if($results) {
				foreach ($results as $result) {
					$allowed_ids[] = $result["teacher"];
					$allowed_ids[] = $result["coordinator"];
					$allowed_ids[] = $result["ccoordinator"];
					$allowed_ids[] = $result["director"];
					$allowed_ids[] = $result["other_director"];
				}
			}
		break;
		case "course" :
			$query		= "	SELECT a.`pcoord_id` AS `coordinator`, b.`proxy_id` AS `director`
							FROM `courses` AS a
							LEFT JOIN `course_contacts` AS b
							ON b.`course_id` = a.`course_id`
							AND b.`contact_type` = 'director'
							WHERE a.`course_id` = ".$db->qstr($identifier)."
							AND a.`course_active` = '1'";
			$results	= $db->GetAll($query);
			if($results) {
				foreach ($results as $result) {
					$allowed_ids[] = $result["director"];
					$allowed_ids[] = $result["coordinator"];
				}
			}
		break;
		case "quiz " :
			$query		= "	SELECT a.`proxy_id`
							FROM `quiz_contacts` AS a
							WHERE a.`quiz_id` = ".$db->qstr($identifier);
			$results	= $db->GetAll($query);
			if($results) {
				foreach ($results as $result) {
					$allowed_ids[] = $result["proxy_id"];
				}
			}
		break;
		default :
			continue;
		break;
	}

	return array_diff(array_unique($allowed_ids), array(""));
}

/**
 * This function controls the permission mask feature by ensuring validity of the mask id
 * and setting the tmp variable properly.
 *
 * @return true
 */
function permissions_mask() {
	global $db;

	if(isset($_GET["mask"])) {
		if(trim($_GET["mask"]) == "close") {
			unset($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]);
		} elseif((int) trim($_GET["mask"])) {
			$query	= "SELECT * FROM `permissions` WHERE `permission_id` = ".$db->qstr((int) trim($_GET["mask"]));
			$result	= $db->GetRow($query);
			if($result) {
				if($result["assigned_to"] == $_SESSION["details"]["id"]) {
					if($result["valid_from"] <= time()) {
						if($result["valid_until"] >= time()) {
							$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"] = (int) trim($result["assigned_by"]);
							$_SESSION["details"]["clinical_member"] = getClinicalFromProxy($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]);
						} else {
							application_log("notice", $_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." [".$_SESSION["details"]["id"]."] tried to masquerade as proxy id [".$result["assigned_by"]."], but their permission to this account has expired.");
						}
					} else {
						application_log("notice", $_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." [".$_SESSION["details"]["id"]."] tried to masquerade as proxy id [".$result["assigned_by"]."], but their permission to this account has not yet begun.");
					}
				} else {
					application_log("error", $_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." [".$_SESSION["details"]["id"]."] tried to masquerade as proxy id [".$result["assigned_by"]."], but they do not have permission_id [".$result["permission_id"]."] does not belong to them. Oooo. Bad news.");
				}
			} else {
				application_log("error", $_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." [".$_SESSION["details"]["id"]."] tried to masquerade as proxy id [".$result["assigned_by"]."], but the provided permission_id [".$result["permission_id"]."] does not exist in the database.");
			}
		}

		$_SERVER["QUERY_STRING"] = replace_query(array("mask" => false));
	}

	if(($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"] != $_SESSION["details"]["id"]) && ($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["expires"] <= time())) {
		unset($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]);
	}

	if(!isset($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])) {
		$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"] = $_SESSION["details"]["id"];
	}

	return true;
}

/**
 * This function will return an array of all groups and roles who have the
 * $module_name registered to them.
 *
 * @param string $module_name
 * @return array
 */
function permissions_by_module($module_name = "") {
	global $ADMINISTRATION;

	$output = array();

	if ((is_array($ADMINISTRATION)) && ($module_name = clean_input($module_name, "alpha"))) {
		foreach ($ADMINISTRATION as $group => $result) {
			foreach ($result as $role => $options) {
				if ((is_array($options["registered"])) && (in_array($module_name, $options["registered"]))) {
					$output[$group][] = $role;
				}
			}
		}
	}

	return $output;
}

/**
 * This function will check to see if we need to update the users module preferences in the
 * database table.
 *
 * @param string $module
 * @param array $preferences
 * @return bool
 */
function preferences_update($module, $preferences = array()) {
	global $db;

	if(!isset($_SESSION[APPLICATION_IDENTIFIER][$module]) || $_SESSION[APPLICATION_IDENTIFIER][$module] != $preferences) {
		$query	= "SELECT `preference_id` FROM `".AUTH_DATABASE."`.`user_preferences` WHERE `app_id`=".$db->qstr(AUTH_APP_ID)." AND `proxy_id`=".$db->qstr($_SESSION["details"]["id"])." AND `module`=".$db->qstr($module);

		$result	= $db->GetRow($query);
		if($result) {
			if(!$db->AutoExecute(AUTH_DATABASE.".user_preferences", array("preferences" => @serialize($_SESSION[APPLICATION_IDENTIFIER][$module]), "updated" => time()), "UPDATE", "preference_id = ".$db->qstr($result["preference_id"]))) {
				application_log("error", "Unable to update the users database preferences for this module. Database said: ".$db->ErrorMsg());

				return false;
			}
		} else {
			if(!$db->AutoExecute(AUTH_DATABASE.".user_preferences", array("app_id" => AUTH_APP_ID, "proxy_id" => $_SESSION["details"]["id"], "module" => $module, "preferences" => @serialize($_SESSION[APPLICATION_IDENTIFIER][$module]), "updated" => time()), "INSERT")) {
				application_log("error", "Unable to insert the users database preferences for this module. Database said: ".$db->ErrorMsg());

				return false;
			}
		}
	}

	return true;
}

/**
 * This function handles basic logging for the application. You provide it with the entry type and message
 * it will log it to the appropriate log file. You also have the option of notifying the application
 * administrator of error log entries.
 *
 * @param string $type
 * @param string $message
 * @return bool
 */
function application_log($type, $message) {
	global $AGENT_CONTACTS;
	$page_url = 'http';
	if ((isset($_SERVER["HTTPS"])) && $_SERVER["HTTPS"] == "on") {
		$page_url .= "s";
	}
	$page_url .= "://";
	$page_url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	
	$search		= array("\t", "\r", "\n");
	$log_entry	= date("r", time())."\t".str_replace($search, " ", $message)."\t".((isset($_SESSION["details"]["id"])) ? str_replace($search, " ", $_SESSION["details"]["id"]) : 0)."\t".((isset($page_url)) ? clean_input($page_url, array("nows")) : "")."\t".((isset($_SERVER["REMOTE_ADDR"])) ? str_replace($search, " ", $_SERVER["REMOTE_ADDR"]) : 0)."\t".((isset($_SERVER["HTTP_USER_AGENT"])) ? str_replace($search, " ", $_SERVER["HTTP_USER_AGENT"]) : false)."\n";

	switch($type) {
		case "access" :
			$log_file = "access_log.txt";
			break;
		case "cron" :
			$log_file = "cron_log.txt";
			break;
		case "reminder" :
			$log_file = "reminder_log.txt";
			break;
		case "success" :
			$log_file = "success_log.txt";
			break;
		case "notice" :
			$log_file = "notice_log.txt";
			break;
		case "error" :
			$log_file = "error_log.txt";

			if((defined("NOTIFY_ADMIN_ON_ERROR")) && (NOTIFY_ADMIN_ON_ERROR)) {
				@error_log($log_entry, 1, $AGENT_CONTACTS["administrator"]["email"], "Subject: ".APPLICATION_NAME.": Errorlog Entry\nFrom: \"".$AGENT_CONTACTS["administrator"]["name"]."\" <".$AGENT_CONTACTS["administrator"]["email"].">\n");
			}
			break;
		default :
			$log_file = "default_log.txt";
			break;
	}

	if(@error_log($log_entry, 3, LOG_DIRECTORY.DIRECTORY_SEPARATOR.$log_file)) {
		return true;
	} else {
		return false;
	}
}

/**
 * This function is only around for historical reasons.
 *
 * @param string $type
 * @param string $message
 * @return bool
 */
function system_log_data($type, $message) {
	return application_log($type, $message);
}

/**
 * This function simply counts the number of confirmed reads the specified
 * notice_id has recieved.
 *
 * @param int $notice_id
 * @return int
 */
function count_notice_reads($notice_id = 0) {
	global $db;

	if($notice_id = (int) $notice_id) {
		$query	= "
				SELECT COUNT(*) AS `total_reads`
				FROM `statistics`
				WHERE `module` = 'notices'
				AND `action` = 'read'
				AND `action_field` = 'notice_id'
				AND `action_value` = ".$db->qstr($notice_id);
		$result	= $db->CacheGetRow(LONG_CACHE_TIMEOUT, $query);
		if($result) {
			return $result["total_reads"];
		}
	}

	return 0;
}

/**
 * This function cleans a string with any valid rules that have been provided in the $rules array.
 * Note that $rules can also be a string if you only want to apply a single rule.
 * If no rules are provided, then the string will simply be trimmed using the trim() function.
 * @param string $string
 * @param array $rules
 * @return string
 * @example $variable = clean_input(" 1235\t\t", array("nows", "int")); // $variable will equal an integer value of 1235.
 */
function clean_input($string, $rules = array()) {
	if (is_scalar($rules)) {
		if (trim($rules) != "") {
			$rules = array($rules);
		} else {
			$rules = array();
		}
	}

	if (count($rules) > 0) {
		foreach ($rules as $rule) {
			switch ($rule) {
				case "page_url" :		// Acceptable characters for community page urls.
				case "module" :
					$string = preg_replace("/[^a-z0-9_\-]/i", "", $string);
				break;
				case "url" :			// Allows only a minimal number of characters
					$string = preg_replace(array("/[^a-z0-9_\-\.\/\~\?\&\:\#\=\+]/i", "/(\.)\.+/", "/(\/)\/+/"), "$1", $string);
				break;
				case "file" :
				case "dir" :			// Allows only a minimal number of characters
					$string = preg_replace(array("/[^a-z0-9_\-\.\/]/i", "/(\.)\.+/", "/(\/)\/+/"), "$1", $string);
				break;
				case "int" :			// Change string to an integer.
					$string = (int) $string;
				break;
				case "float" :			// Change string to a float.
					$string = (float) $string;
				break;
				case "bool" :			// Change string to a boolean.
					$string = (bool) $string;
				break;
				case "nows" :			// Trim all whitespace anywhere in the string.
					$string = str_replace(array(" ", "\t", "\n", "\r", "\0", "\x0B", "&nbsp;"), "", $string);
				break;
				case "trim" :			// Trim whitespace from ends of string.
					$string = trim($string);
				break;
				case "trimds" :			// Removes double spaces.
					$string = str_replace(array(" ", "\t", "\n", "\r", "\0", "\x0B", "&nbsp;", "\x7f", "\xff", "\x0", "\x1f"), " ", $string);
					$string = html_decode(str_replace("&nbsp;", "", html_encode($string)));
				break;
				case "nl2br" :
					$string = nl2br($string);
				break;
				case "underscores" :	// Trim all whitespace anywhere in the string.
					$string = str_replace(array(" ", "\t", "\n", "\r", "\0", "\x0B", "&nbsp;"), "_", $string);
				break;
				case "lower" :			// Change string to all lower case.
				case "lowercase" :
					$string = strtolower($string);
				break;
				case "upper" :			// Change string to all upper case.
				case "uppercase" :
					$string = strtoupper($string);
				break;
				case "ucwords" :		// Change string to correct word case.
					$string = ucwords(strtolower($string));
				break;
				case "boolops" :		// Removed recognized boolean operators.
					$string = str_replace(array("\"", "+", "-", "AND", "OR", "NOT", "(", ")", ",", "-"), "", $string);
				break;
				case "quotemeta" :		// Quote's meta characters
					$string = quotemeta($string);
				break;
				case "credentials" :	// Acceptable characters for login credentials.
					$string = preg_replace("/[^a-z0-9_\-\.]/i", "", $string);
				break;
				case "alphanumeric" :	// Remove anything that is not alphanumeric.
					$string = preg_replace("/[^a-z0-9]+/i", "", $string);
				break;
				case "alpha" :			// Remove anything that is not an alpha.
					$string = preg_replace("/[^a-z]+/i", "", $string);
				break;
				case "numeric" :		// Remove everything but numbers 0 - 9 for when int won't do.
					$string = preg_replace("/[^0-9]+/i", "", $string);
				break;
				case "name" :			// @todo jellis ?
					$string = preg_replace("/^([a-z]+(\'|-|\.\s|\s)?[a-z]*){1,2}$/i", "", $string);
				break;
				case "emailcontent" :	// Check for evil tags that could be used to spam.
					$string = str_ireplace(array("content-type:", "bcc:","to:", "cc:"), "", $string);
				break;
				case "postclean" :		// @todo jellis ?
					$string = preg_replace('/\<br\s*\/?\>/i', "\n", $string);
					$string = str_replace("&nbsp;", " ", $string);
				break;
				case "html_decode" :
				case "decode" :			// Returns the output of the html_decode() function.
					$string = html_decode($string);
				break;
				case "html_encode" :
				case "encode" :			// Returns the output of the html_encode() function.
					$string = html_encode($string);
				break;
				case "htmlspecialchars" : // Returns the output of the htmlspecialchars() function.
				case "specialchars" :
					$string = htmlspecialchars($string, ENT_QUOTES, DEFAULT_CHARSET);
				break;
				case "htmlbrackets" :	// Converts only brackets into entities.
					$string = str_replace(array("<", ">"), array("&lt;", "&gt;"), $string);
				break;
				case "notags" :			// Strips tags from the string.
				case "nohtml" :
				case "striptags" :
					$string = strip_tags($string);
				break;
				case "allowedtags" :	// Cleans and validates HTML, requires HTMLPurifier: http://htmlpurifier.org
				case "nicehtml" :
				case "html" :
					require_once("Entrada/htmlpurifier/HTMLPurifier.auto.php");

					$html = new HTMLPurifier();

					$config = HTMLPurifier_Config::createDefault();
					$config->set("Cache.SerializerPath", CACHE_DIRECTORY);
					$config->set("Core.Encoding", DEFAULT_CHARSET);
					$config->set("Core.EscapeNonASCIICharacters", true);
					$config->set("HTML.SafeObject", true);
					$config->set("Output.FlashCompat", true);
					$config->set("HTML.TidyLevel", "medium");
					$config->set("Test.ForceNoIconv", true);
					$config->set("Attr.AllowedFrameTargets", array("_blank", "_self", "_parent", "_top"));

					$string = $html->purify($string, $config);
				break;
				default :				// Unknown rule, log notice.
					application_log("notice", "Unknown clean_input function rule [".$rule."]");
				break;
			}
		}

		return $string;
	} else {
		return trim($string);
	}
}

/**
 * Function to properly format the success messages for consistency.
 *
 * @param array $success_messages
 * @return string containing the HTML of the message or false if there is no HTML.
 */
function display_success($success_messages = array()) {
	global $SUCCESS, $SUCCESSSTR;

	$output_html = "";

	if (is_scalar($success_messages)) {
		if (trim($success_messages) != "") {
			$success_messages = array($success_messages);
		} else {
			$success_messages = array();
		}
	}

	if (!$num_success = (int) @count($success_messages)) {
		if ($num_success = (int) @count($SUCCESSSTR)) {
			$success_messages = $SUCCESSSTR;
		}
	}

	if ($num_success) {
		$output_html .= "<div id=\"display-success-box\" class=\"display-success\">\n";
		$output_html .= "	<ul>\n";
		foreach ($success_messages as $success_message) {
			$output_html .= "	<li>".$success_message."</li>\n";
		}
		$output_html .= "	</ul>\n";
		$output_html .= "</div>\n";
	}

	return (($output_html) ? $output_html : false);
}

/**
 * Function to properly format the error messages for consistency.
 *
 * @param array $notice_messages
 * @return string containing the HTML of the message or false if there is no HTML.
 */
function display_notice($notice_messages = array()) {
	global $NOTICE, $NOTICESTR;

	$output_html = "";

	if (is_scalar($notice_messages)) {
		if (trim($notice_messages) != "") {
			$notice_messages = array($notice_messages);
		} else {
			$notice_messages = array();
		}
	}

	if (!$num_notices = (int) @count($notice_messages)) {
		if ($num_notices = (int) @count($NOTICESTR)) {
			$notice_messages = $NOTICESTR;
		}
	}

	if ($num_notices) {
		$output_html .= "<div id=\"display-notice-box\" class=\"display-notice\">\n";
		$output_html .= "	<ul>\n";
		foreach ($notice_messages as $notice_message) {
			$output_html .= "	<li>".$notice_message."</li>\n";
		}
		$output_html .= "	</ul>\n";
		$output_html .= "</div>\n";
	}

	return (($output_html) ? $output_html : false);
}

/**
 * Function to properly format the error messages for consistency.
 *
 * @param array $error_messages
 * @return string containing the HTML of the message or false if there is no HTML.
 */
function display_error($error_messages = array()) {
	global $ERROR, $ERRORSTR;

	$output_html = "";

	if (is_scalar($error_messages)) {
		if (trim($error_messages) != "") {
			$error_messages = array($error_messages);
		} else {
			$error_messages = array();
		}
	}

	if (!$num_errors = (int) @count($error_messages)) {
		if ($num_errors = (int) @count($ERRORSTR)) {
			$error_messages = $ERRORSTR;
		}
	}

	if($num_errors) {
		$output_html .= "<div id=\"display-error-box\" class=\"display-error\">\n";
		$output_html .= "	<ul>\n";
		foreach ($error_messages as $error_message) {
			$output_html .= "	<li>".$error_message."</li>\n";
		}
		$output_html .= "	</ul>\n";
		$output_html .= "</div>\n";
	}

	return (($output_html) ? $output_html : false);
}

/**
 * Simple function to return the gender.
 *
 * @param int $gender
 * @param string $format
 *
 * @return string
 *
 */
function display_gender($gender, $format = "default") {
	switch ($gender) {
		case 2 :
			if ($format == "short") {
				return "M";
			} else {
				return "Male";
			}
		break;
		case 1 :
			if ($format == "short") {
				return "F";
			} else {
				return "Female";
			}
		break;
		default :
		case 0 :
			if ($format == "short") {
				return "U";
			} else {
				return "Unknown";
			}
		break;
	}
}

/**
 * Returns a more human readable friendly filesize.
 *
 * @param int $bytes
 * @return string
 */
function readable_size($bytes) {
	$kb = 1024;				// Kilobyte
	$mb = 1048576;			// Megabyte
	$gb = 1073741824;		// Gigabyte
	$tb = 1099511627776;	// Terabyte

	if($bytes < $kb) {
		return $bytes." b";
	} else if($bytes < $mb) {
			return round($bytes / $kb, 2)." KB";
		} else if($size < $gb) {
				return round($bytes / $mb, 2)." MB";
			} else if($size < $tb) {
					return round($bytes / $gb, 2)." GB";
				} else {
					return round($bytes / $tb, 2)." TB";
				}
}

/**
 * Function will return a properly formatted filename.
 *
 * @param string $filename
 * @return string
 */
function useable_filename($filename) {
	return strtolower(preg_replace("/[^a-z0-9_\-\.]/i", "_", $filename));
}

/**
 * Returns a string with a maximum character length of the requested value.
 *
 * @param string $string
 * @param int $character_limit
 * @param bool $show_acronym
 * @param bool $encode_string
 * @return string
 */
function limit_chars($string = "", $character_limit = 0, $show_acronym = false, $encode_string = true) {
	if(($string = trim($string)) && ($character_limit = (int) $character_limit)) {
		if(strlen($string) > $character_limit) {
			return substr($string, 0, ($character_limit - 4))." ".(($show_acronym) ? "<acronym title=\"".(($encode_string) ? html_encode($string) : $string)."\" style=\"cursor: pointer\">...</acronym>" : "...");
		}
	}

	return $string;
}

/**
 * This function will check the provided event_id for event resources, such as files and links
 * then return the total number of attachments.
 *
 * @param integer $event_id
 * @param string $side
 * @return integer
 */
function attachment_check($event_id = 0, $side = "public") {
	global $db;

	$total_files	= 0;
	$total_links	= 0;
	$total_quizzes	= 0;
	$grand_total	= 0;

	if($event_id = (int) $event_id) {
		$query	= "SELECT COUNT(*) AS `total_files` FROM `event_files` WHERE `event_id` = ".$db->qstr($event_id).(($side == "public") ? " AND (`release_date` = '0' OR `release_date` <= '".time()."') AND (`release_until` = '0' OR `release_until` >= '".time()."')" : "");
		$result	= ((USE_CACHE) ? $db->CacheGetRow(LONG_CACHE_TIMEOUT, $query) : $db->GetRow($query));
		if($result) {
			$total_files = $result["total_files"];
			$grand_total += $total_files;
		}

		$query	= "SELECT COUNT(*) AS `total_links` FROM `event_links` WHERE `event_id` = ".$db->qstr($event_id).(($side == "public") ? " AND (`release_date` = '0' OR `release_date` <= '".time()."') AND (`release_until` = '0' OR `release_until` >= '".time()."')" : "");
		$result	= ((USE_CACHE) ? $db->CacheGetRow(LONG_CACHE_TIMEOUT, $query) : $db->GetRow($query));
		if($result) {
			$total_links = $result["total_links"];
			$grand_total += $total_links;
		}

		$query	= "SELECT COUNT(*) AS `total_quizzes` FROM `event_quizzes` WHERE `event_id` = ".$db->qstr($event_id).(($side == "public") ? " AND (`release_date` = '0' OR `release_date` <= '".time()."') AND (`release_until` = '0' OR `release_until` >= '".time()."')" : "");
		$result	= ((USE_CACHE) ? $db->CacheGetRow(LONG_CACHE_TIMEOUT, $query) : $db->GetRow($query));
		if($result) {
			$total_quizzes = $result["total_quizzes"];
			$grand_total += $total_quizzes;
		}
	}

	return $grand_total;
}

/**
 * This function is used as a micro-timer for the length of time for pages to execute.
 *
 * @return float
 */
function getmicrotime() {
	list($usec, $sec) = explode(" ", microtime());
	return ((float) $usec + (float) $sec);
}

/**
 * This function returns the number of events that are associated wtih the
 * provided course_id.
 *
 * @param int $course_id
 * @return int
 */

function courses_count_associated_events($course_id = 0) {
	global $db;

	if($course_id = (int) $course_id) {
		$query	= "SELECT COUNT(*) AS `total_events` FROM `events` WHERE `course_id` = ".$db->qstr($course_id);
		$result	= $db->GetRow($query);
		if($result) {
			return (int) $result["total_events"];
		}
	}

	return 0;
}

/**
 * This function returns the name of the course if it is found, otherwise false.
 *
 * @param int $id
 * @return string
 */
function course_name($course_id = 0, $return_course_name = true, $return_course_code = false) {
	global $db;

	if (($course_id = (int) $course_id) && ($return_course_name || $return_course_code)) {
		$output = array();
		$query	= "	SELECT `course_name`, `course_code` FROM `courses` 
					WHERE `course_id` = ".$db->qstr($course_id)."
					AND `course_active` = '1'";
		$result	= $db->GetRow($query);
		if ($result) {
			if (((bool) $return_course_name) && ($result["course_name"])) {
				$output[] = $result["course_name"];
			}

			if (((bool) $return_course_code) && ($result["course_code"])) {
				$output[] = $result["course_code"];
			}

			return implode(": ", $output);
		}
	}

	return false;
}

/**
 * This function returns an array of the hierarchal path to the provided
 * course_id.
 *
 * @example Semester 1 > Course Name
 *
 * @param int $course_id
 * @param bool $return_course_name
 * @param bool $return_course_code
 * @return array
 */
function curriculum_hierarchy($course_id = 0, $return_course_code = false) {
	global $db;

	if ($course_id = (int) $course_id) {
		$output	= array();
		$count	= 0;

		$query	= "	SELECT * FROM `courses` 
					WHERE `course_id` = ".$db->qstr($course_id)."
					AND `course_active` = '1'";
		$result	= $db->GetRow($query);

		if ($result) {
			$output[] = $result["course_name"].(($return_course_code) ? " (".$result["course_code"].")" : "");

			$query	= "SELECT * FROM `curriculum_lu_types` WHERE `curriculum_type_id` = ".$db->qstr($result["curriculum_type_id"]);
			$result	= $db->GetRow($query);

			if ($result) {
				$output[] = $result["curriculum_type_name"];
			}
		
			return array_reverse($output);
		}
	}

	return false;
}

function generate_password($len = 5) {
	$pass	= "";
	$lchar	= 0;
	$char	= 0;
	for($i = 0; $i < $len; $i++) {
		while($char == $lchar) {
			$char = rand(48, 109);
			if($char > 57) $char += 7;
			if($char > 90) $char += 6;
		}
		$pass .= chr($char);
		$lchar = $char;
	}
	return strtolower($pass);
}

/**
 * Determine wether or not the proxy server is required based on the start_block and end_block provided.
 *
 * @param string $start_block
 * @param unknown_type $end_block
 * @return unknown
 */
function require_proxy($start_block = "", $end_block = "") {
	if((isset($_SERVER["REMOTE_ADDR"])) && ($_SERVER["REMOTE_ADDR"] != "")) {
		if((ip2long($_SERVER["REMOTE_ADDR"]) >= ip2long($start_block)) && (ip2long($_SERVER["REMOTE_ADDR"]) <= ip2long($end_block))) {
			return false;
		} else {
			return true;
		}
	}

	return false;
}

/**
 * This function determines whether or not the remote address is in the
 * exceptions list (as defined in settings.inc.php).
 *
 * Note: Combine require_proxy and check_proxy functions.
 *
 * @param string $location
 * @return bool
 */
function check_proxy($location = "default") {
	global $PROXY_URLS, $PROXY_SUBNETS;

	if(!is_array($PROXY_SUBNETS[$location])) {
		$location = "default";
	}

	if((isset($PROXY_SUBNETS[$location]["exceptions"])) && (in_array($_SERVER["REMOTE_ADDR"], $PROXY_SUBNETS[$location]["exceptions"]))) {
		return true;
	} elseif(require_proxy($PROXY_SUBNETS[$location]["start"], $PROXY_SUBNETS[$location]["end"])) {
		return true;
	} else {
		return false;
	}
}

/**
 * Function is responsible for updating the last updated information.
 *
 * @param string $type
 * @param int $event_id
 * @return bool
 */
function last_updated($type = "event", $event_id = 0) {
	global $db;

	if($event_id = (int) $event_id) {
		switch($type) {
			case "lecture" :
			case "event" :
				if($db->AutoExecute("events", array("updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "UPDATE", "event_id = ".$db->qstr($event_id))) {
					return true;
				}
				break;
			default :
				continue;
				break;
		}
	}

	return false;
}

/**
 * This function will fetch the feeds out of the language customization file,
 * and return the feeds that are applicable to the users group.
 *
 * @global <type> $translate
 * @return <type> array
 */
function dashboard_fetch_feeds($default = false) {
	global $translate;

	if (!$default && isset($_SESSION[APPLICATION_IDENTIFIER]["dashboard"]["feeds"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["dashboard"]["feeds"])) {
		return $_SESSION[APPLICATION_IDENTIFIER]["dashboard"]["feeds"];
	} else {
		$feeds = $translate->_("public_dashboard_feeds");
		$group = $_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"];

		if (is_array($feeds[$group])) {
			$feeds = array_merge($feeds["global"], $feeds[$group]);
		} else {
			$feeds = $feeds["global"];
		}
		return $feeds;
	}
}

/**
 * This function will fetch the links out of the language customization file,
 * and return the links that are applicable to the users group.
 *
 * @global <type> $translate
 * @return <type> array
 */
function dashboard_fetch_links() {
	global $translate;

	$links = $translate->_("public_dashboard_links");
	$group = $_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"];

	if (is_array($links[$group])) {
		return array_merge($links["global"], $links[$group]);
	} else {
		return $links["global"];
	}
}

function unixstamp_to_iso8601($t) {
	$tz	= date("Z", $t)/60;
	$tm	= $tz % 60; $tz=$tz/60;
	if ($tz<0) {
		$ts="-";
		$tz=-$tz;
	} else {
		$ts="+";
	}

	$tz	= substr("0" . $tz, -2);
	$tm	= substr("0" . $tm, -2);
	return date("Y-m-d\TH:i:s", $t)."${ts}${tz}:${tm}";
}

/**
 * Function responsible for returning the number of times a poll has
 * been responded to.
 *
 * @param int $poll_id
 * @return int
 */
function poll_responses($poll_id = 0) {
	global $db;

	if($poll_id = (int) $poll_id) {
		$query	= "SELECT COUNT(*) AS `responses` FROM `poll_results` WHERE `poll_id`=".$db->qstr($poll_id);
		$result	= $db->GetRow($query);
		if($result) {
			return $result["responses"];
		}
	}
	return 0;
}

/**
 * Function used to output the poll current poll responses in hidden input elements
 *
 * @param array $responses
 * @return string
 */
function poll_responses_in_form($responses) {
	$output = "";
	if (isset($responses) && is_array($responses)) {
		foreach ($responses as $key => $response) {
			$output .= "<input id=\"response_".((int) $key)."\" name=\"response[".((int) $key)."]\" value=\"".$response."\" type=\"hidden\" />";
		}
	}
	return $output;
}

/**
 * Function responsible for displaying the number of times a response
 * was selected.
 *
 * @param int $poll_id
 * @param int $answer_id
 * @return int
 */
function poll_answer_responses($poll_id = 0, $answer_id = 0) {
	global $db;

	if(($poll_id = (int) $poll_id) && ($answer_id = (int) $answer_id)) {
		$query	= "
				SELECT COUNT(*) AS `responses`
				FROM `poll_results`
				WHERE `poll_id` = ".$db->qstr($poll_id)."
				AND `answer_id` = ".$db->qstr($answer_id);
		$result	= $db->GetRow($query);
		if($result) {
			return $result["responses"];
		}
	}
	return 0;
}

/**
 * Function responsible for checking to see whether or not the proxy_id
 * is eligible to take the poll.
 *
 * @param int $poll_id
 * @return bool
 */
function poll_prevote_check($poll_id = 0) {
	global $db;

	if($poll_id = (int) $poll_id) {
		$query	= "
				SELECT *
				FROM `poll_results`
				WHERE `poll_id` = ".$db->qstr($poll_id)."
				AND `proxy_id` = ".$db->qstr($_SESSION["details"]["id"]);
		$result	= $db->GetRow($query);
		if($result) {
			return false;
		} else {
			return true;
		}
	} else {
		return false;
	}
}

/**
 * Function responsible for actually displaying an uncompleted poll.
 *
 * @param int $poll_id
 * @return string
 */
function poll_display($poll_id = 0) {
	global $db;

	$output = "";

	if($poll_id = (int) $poll_id ) {
		$query		= "SELECT `poll_question` FROM `poll_questions` WHERE `poll_id`=".$db->qstr($poll_id);
		$poll_question	= $db->GetRow($query);

		if($poll_question) {
			if(!poll_prevote_check($poll_id)) {
				$output = poll_results($poll_id);
			} else {
				$query		= "SELECT `answer_id`, `answer_text` FROM `poll_answers` WHERE `poll_id`=".$db->qstr($poll_id)." ORDER BY `answer_order` ASC";
				$poll_answers	= $db->GetAll($query);
				$total_votes	= poll_responses($poll_id);

				$output .= "<div id=\"poll\">\n";
				$output .= "<form action=\"".ENTRADA_URL."/serve-polls.php?pollSend&nojs\" method=\"post\" id=\"pollForm\" onsubmit=\"return ReadVote();\">\n";
				$output .= html_encode($poll_question["poll_question"]);
				$output .= "	<div style=\"padding-top: 5px; padding-left: 3px; padding-bottom: 5px\">\n";
				foreach ($poll_answers as $poll_answer) {
					if(trim($poll_answer["answer_text"]) != "") {
						$output .=  "<label for=\"choice_".$poll_answer["answer_id"]."\" style=\"font-size: 11px\">\n";
						$output .=  "	<input type=\"radio\" id=\"choice_".$poll_answer["answer_id"]."\" value=\"".$poll_answer["answer_id"]."\" name=\"poll_answer_id\" />\n";
						$output .=  	html_encode($poll_answer["answer_text"]);
						$output .=  "</label><br />\n";
					}
				}
				$output .= "	</div>\n";
				$output .= "	<input type=\"hidden\" id=\"poll_id\" name=\"poll_id\" value=\"".$poll_id."\" />\n";
				$output .= "	<div style=\"text-align: right\"><input type=\"submit\" class=\"button-sm\" name=\"vote\" value=\"Vote\" /></div>\n";
				$output .= "</form>\n";
				$output .= "</div>\n";
			}
		}
	}
	return $output;
}

/**
 * Function responsible for displaying the results of a poll.
 *
 * @param int $poll_id
 * @return string
 */
function poll_results($poll_id = 0) {
	global $db;

	$output = "";

	if($poll_id = (int) $poll_id) {
		$query		= "SELECT `poll_question` FROM `poll_questions` WHERE `poll_id`=".$db->qstr($poll_id);
		$poll_question	= $db->GetRow($query);
		if($poll_question) {
			$query		= "SELECT `answer_id`, `answer_text`, `answer_order` FROM `poll_answers` WHERE `poll_id`=".$db->qstr($poll_id)." ORDER BY `answer_order` ASC";
			$poll_answers	= $db->GetAll($query);
			$total_votes	= poll_responses($poll_id);
			$answers		= array();
			$winner		= 0;
			$highest		= 0;

			$output .= "<div id=\"poll\">\n";
			$output .= "<div id=\"poll-question\" style=\"margin-bottom: 9px\">".html_encode($poll_question["poll_question"])."</div>";
			$output .= "<table style=\"width: 100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" summary=\"Quick Sidebar Poll\">\n";
			$output .= "<colgroup>\n";
			$output .= "	<col style=\"width: 80%\" />\n";
			$output .= "	<col style=\"width: 20%\" />\n";
			$output .= "</colgroup>\n";
			$output .= "<tbody>\n";

			foreach ($poll_answers as $poll_answer) {
				if(trim($poll_answer["answer_text"]) != "") {
					$answers[$poll_answer["answer_order"]]["answer_id"]	= $poll_answer["answer_id"];
					$answers[$poll_answer["answer_order"]]["answer_text"]	= $poll_answer["answer_text"];
					$answers[$poll_answer["answer_order"]]["votes"]		= poll_answer_responses($poll_id, $poll_answer["answer_id"]);

					if($answers[$poll_answer["answer_order"]]["votes"] > $highest) {
						$winner	= $answers[$poll_answer["answer_order"]]["answer_id"];
						$highest	= $answers[$poll_answer["answer_order"]]["votes"];
					}
				}
			}

			foreach ($answers as $result) {
				$percent = round($result["votes"] / ($total_votes + 0.0001) * 100);
				$output .= "<tr>";
				$output .= "	<td colspan=\"2\">\n";
				$output .= "		<span style=\"font-size: 11px\">".html_encode($result["answer_text"])."</span>\n";
				$output .= "	</td>\n";
				$output .= "</tr>\n";
				$output .= "<tr>";
				$output .= "	<td style=\"padding-bottom: 5px\">\n";
				$output .= "		<div style=\"width: 100%; height: 12px; background-color: #EEEEEE; border: 1px #333333 solid\">\n";
				$output .= "			<div style=\"width: ".((!$percent) ? "1" : $percent)."%; height: 12px; background-color: #666666\"></div>";
				$output .= "		</div>\n";
				$output .= "	</td>\n";
				$output .= "	<td style=\"padding-bottom: 5px; text-align: right; padding-right: 3px; font-size: 10px; color: #666666\">".$percent."%</td>\n";
				$output .= "</tr>";
			}
			$output .= "</tbody>\n";
			$output .= "</table>\n";
			$output .= "<div style=\"border-top: 1px #666666 dotted; font-size: 11px; color: #666666\"><b>Total Votes:</b> ".$total_votes."</div>\n";
			$output .= "</div>\n";
		}
	}

	return $output;
}

/**
 * Wrapper function for html_entities.
 *
 * @param string $string
 * @return string
 */
function html_encode($string = "") {
	if (!defined("DEFAULT_CHARSET")) {
		define("DEFAULT_CHARSET", "UTF-8");
	}

	if ($string) {
		return htmlentities($string, ENT_QUOTES, DEFAULT_CHARSET);
	}

	return "";
}

/**
 * Wrapper for PHP's html_entities_decode function.
 *
 * @param string $string
 * @return string
 */
function html_decode($string) {
	return html_entity_decode($string, ENT_QUOTES, DEFAULT_CHARSET);
}

/**
 * Wrapper function for htmlspecialchars. This is called xml_encode because unlike
 * HTML, XML only supports five named character entities.
 *
 * @param string $string
 * @return string
 */
function xml_encode($string) {
	return htmlspecialchars($string, ENT_QUOTES, DEFAULT_CHARSET);
}

/**
 * Wrapper for PHP's html_entities_decode function.
 *
 * @param string $string
 * @return string
 */
function xml_decode($string) {
	return html_entity_decode($string, ENT_QUOTES, DEFAULT_CHARSET);
}

/**
 * This function is used to generate a calendar with an optional time selector in a form.
 *
 * @param string $fieldname
 * @param string $display_name
 * @param bool $required
 * @param int $current_time
 * @param bool $use_times
 * @param bool $add_line_break
 * @param bool $auto_end_date
 * @param bool $disabled
 * @param bool $optional Indicates whether this date/time field is optional. Checkbox if true, date/time fields only if false. default: true
 * @return string
 */
function generate_calendar($fieldname, $display_name = "", $required = false, $current_time = 0, $use_times = true, $add_line_break = false, $auto_end_date = false, $disabled = false, $optional=true) {
	global $HEAD, $ONLOAD;

	if (!$display_name) {
		$display_name = ucwords(strtolower($fieldname));
	}

	$output		= "";

	if ($use_times) {
		$ONLOAD[]	= "updateTime('".$fieldname."')";
	}

	if ($optional) {
		$ONLOAD[]	= "dateLock('".$fieldname."')";
	}

	if ($current_time) {
		$time		= 1;
		$time_date	= date("Y-m-d", $current_time);
		$time_hour	= (int) date("G", $current_time);
		$time_min	= (int) date("i", $current_time);
	} else {
		$time		= (($required) ? 1 : 0);
		$time_date	= "";
		$time_hour	= 0;
		$time_min	= 0;
	}

	// @todo This should be disabled=\"disabled\" as readonly isn't a valid attribute.
	if ($auto_end_date) {
		$readonly = "readonly=\"readonly\"";
	} else {
		$readonly = "";
	}
	$output .= "<tr>\n";
	if ($optional) {
		$output .= "	<td style=\"vertical-align: top\"><input type=\"checkbox\" name=\"".$fieldname."\" id=\"".$fieldname."\" value=\"1\"".(($time) ? " checked=\"checked\"" : "").(($required) ? " readonly=\"readonly\"" : "")." onclick=\"".(($required) ? "this.checked = true" : "dateLock('".$fieldname."')")."\" style=\"vertical-align: middle\" /></td>\n";
	} else {
		$output .= "	<td style=\"vertical-align: top\">&nbsp;</td>\n";		
	}
	$output .= "	<td style=\"vertical-align: top; padding-top: 4px\"><label id=\"".$fieldname."_text\" for=\"".$fieldname."\" class=\"".($required ? "form-required" : "form-nrequired")."\">".html_encode($display_name)."</label></td>\n";
	$output .= "	<td style=\"vertical-align: top\">\n";
	$output .= "		<input type=\"text\" name=\"".$fieldname."_date\" id=\"".$fieldname."_date\" value=\"".$time_date."\" $readonly autocomplete=\"off\" ".(!$disabled ? "onfocus=\"showCalendar('', this, this, '', '".$fieldname."_date', 0, 20, 1)\"" : "")."style=\"width: 170px; vertical-align: middle\" />&nbsp;";

	if (!$disabled) {
		$output .= "	<a href=\"javascript: showCalendar('', document.getElementById('".$fieldname."_date'), document.getElementById('".$fieldname."_date'), '', '".$fieldname."_date', 0, 20, 1)\" title=\"Show Calendar\" onclick=\"if (!document.getElementById('".$fieldname."').checked) { return false; }\"><img src=\"".ENTRADA_URL."/images/cal-calendar.gif\" width=\"23\" height=\"23\" alt=\"Show Calendar\" title=\"Show Calendar\" border=\"0\" style=\"vertical-align: middle\" /></a>";
	}
	if ($use_times) {
		$output .= "		&nbsp;@&nbsp;".(((bool) $add_line_break) ? "<br />" : "");
		$output .= "		<select name=\"".$fieldname."_hour\" id=\"".$fieldname."_hour\" onchange=\"updateTime('".$fieldname."')\" style=\"vertical-align: middle\">\n";
		foreach (range(0, 23) as $hour) {
			$output .= "	<option value=\"".(($hour < 10) ? "0" : "").$hour."\"".(($hour == $time_hour) ? " selected=\"selected\"" : "").">".(($hour < 10) ? "0" : "").$hour."</option>\n";
		}

		$output .= "		</select>\n";
		$output .= "		:";
		$output .= "		<select name=\"".$fieldname."_min\" id=\"".$fieldname."_min\" onchange=\"updateTime('".$fieldname."')\" style=\"vertical-align: middle\">\n";
		foreach (range(0, 59) as $minute) {
			$output .= "	<option value=\"".(($minute < 10) ? "0" : "").$minute."\"".(($minute == $time_min) ? " selected=\"selected\"" : "").">".(($minute < 10) ? "0" : "").$minute."</option>\n";
		}
		$output .= "		</select>\n";
		$output .= "		&nbsp;( <span class=\"content-small\" id=\"".$fieldname."_display\"></span> )\n";
	}
	if($auto_end_date) {
		$output .= "<div id=\"auto_end_date\" class=\"content-small\" style=\"display: none\"></div>";
	}
	$output .= "	</td>\n";
	$output .= "</tr>\n";

	return $output;
}

/**
 * This function is used to generate the standard start / finish calendars
 * within forms.
 *
 * @param string $fieldname
 * @param string $display_name
 * @param bool $show_start
 * @param int $current_start
 * @param bool $show_finish
 * @param int $current_finish
 * @return string
 */
function generate_calendars($fieldname, $display_name = "", $show_start = false, $start_required = false, $current_start = 0, $show_finish = false, $finish_required = false, $current_finish = 0, $use_times = true, $add_line_break = false, $display_name_start_suffix = " Start", $display_name_finish_suffix = " Finish") {
	global $HEAD, $ONLOAD;

	if(!$display_name) {
		$display_name = ucwords(strtolower($fieldname));
	}

	$output = "";
	if($show_start) {
		$output .= generate_calendar($fieldname."_start", $display_name.$display_name_start_suffix, $start_required, $current_start, $use_times, $add_line_break);
	}

	if($show_finish) {
		$output .= generate_calendar($fieldname."_finish", $display_name.$display_name_finish_suffix, $finish_required, $current_finish, $use_times, $add_line_break);
	}

	return $output;
}

/**
 * Function will validate the calendar that is generated by generate_calendars().
 *
 * @param string $fieldname
 * @param int $require_start
 * @param int $require_finish
 * @return array
 */
function validate_calendars($fieldname, $require_start = true, $require_finish = true, $use_times = true) {
	global $ERROR, $ERRORSTR;

	$timestamp_start	= 0;
	$timestamp_finish	= 0;

	if(($require_start) && ((!isset($_POST[$fieldname."_start"])) || (!$_POST[$fieldname."_start_date"]))) {
		$ERROR++;
		$ERRORSTR[] = "You must select a start date for the ".$fieldname." calendar entry.";
	} elseif($_POST[$fieldname."_start"] == "1") {
		if((!isset($_POST[$fieldname."_start_date"])) || (!trim($_POST[$fieldname."_start_date"]))) {
			$ERROR++;
			$ERRORSTR[] = "You have checked <strong>".ucwords(strtolower($fieldname))." Start</strong> but not selected a calendar date.";
		} else {
			if(($use_times) && ((!isset($_POST[$fieldname."_start_hour"])))) {
				$ERROR++;
				$ERRORSTR[] = "You have checked <strong>".ucwords(strtolower($fieldname))." Start</strong> but not selected an hour of the day.";
			} else {
				if(($use_times) && ((!isset($_POST[$fieldname."_start_min"])))) {
					$ERROR++;
					$ERRORSTR[] = "You have checked <strong>".ucwords(strtolower($fieldname))." Start</strong> but not selected a minute of the hour.";
				} else {
					$pieces	= explode("-", $_POST[$fieldname."_start_date"]);
					$hour	= (($use_times) ? (int) trim($_POST[$fieldname."_start_hour"]) : 0);
					$minute	= (($use_times) ? (int) trim($_POST[$fieldname."_start_min"]) : 0);
					$second	= 0;
					$month	= (int) trim($pieces[1]);
					$day	= (int) trim($pieces[2]);
					$year	= (int) trim($pieces[0]);

					$timestamp_start = mktime($hour, $minute, $second, $month, $day, $year);
				}
			}
		}
	}

	if(($require_finish) && ((!isset($_POST[$fieldname."_finish"])) || (!$_POST[$fieldname."_finish_date"]))) {
		$ERROR++;
		$ERRORSTR[] = "You must select a finish date for the ".$fieldname." calendar entry.";
	} elseif(isset($_POST[$fieldname."_finish"]) && $_POST[$fieldname."_finish"] == "1") {
		if((!isset($_POST[$fieldname."_finish_date"])) || (!trim($_POST[$fieldname."_finish_date"]))) {
			$ERROR++;
			$ERRORSTR[] = "You have checked <strong>".ucwords(strtolower($fieldname))." Finish</strong> but not selected a calendar date.";
		} else {
			if(($use_times) && ((!isset($_POST[$fieldname."_finish_hour"])))) {
				$ERROR++;
				$ERRORSTR[] = "You have checked <strong>".ucwords(strtolower($fieldname))." Finish</strong> but not selected an hour of the day.";
			} else {
				if(($use_times) && ((!isset($_POST[$fieldname."_finish_min"])))) {
					$ERROR++;
					$ERRORSTR[] = "You have checked <strong>".ucwords(strtolower($fieldname))." Finish</strong> but not selected a minute of the hour.";
				} else {
					$pieces	= explode("-", trim($_POST[$fieldname."_finish_date"]));
					$hour	= (($use_times) ? (int) trim($_POST[$fieldname."_finish_hour"]) : 23);
					$minute	= (($use_times) ? (int) trim($_POST[$fieldname."_finish_min"]) : 59);
					$second	= ((($use_times) && ((int) trim($_POST[$fieldname."_finish_min"]))) ? 59 : 0);
					$month	= (int) trim($pieces[1]);
					$day	= (int) trim($pieces[2]);
					$year	= (int) trim($pieces[0]);

					$timestamp_finish = mktime($hour, $minute, $second, $month, $day, $year);
				}
			}
		}
	}

	if(($timestamp_start) && ($timestamp_finish) && ($timestamp_finish < $timestamp_start)) {
		$ERROR++;
		$ERRORSTR[] = "The <strong>".ucwords(strtolower($fieldname))." Finish</strong> date &amp; time you have selected is before the <strong>".ucwords(strtolower($fieldname))." Start</strong> date &amp; time you have selected.";
	}

	return array("start" => $timestamp_start, "finish" => $timestamp_finish);
}

/**
 * Function will validate the calendar that is generated by generate_calendar().
 *
 * @param string $fieldname
 * @param bool $use_times
 * @return int $timestamp
 */
function validate_calendar($label, $fieldname, $use_times = true, $required=true) {
	global $ERROR, $ERRORSTR;

	$timestamp_start	= 0;
	$timestamp_finish	= 0;

	if((!isset($_POST[$fieldname."_date"])) || (!trim($_POST[$fieldname."_date"]))) {
		if ($required) {
			add_error("<strong>".$label."</strong> date not entered.");
		} else {
			return;
		}
	} elseif (!checkDateFormat($_POST[$fieldname."_date"])) {
		add_error("Invalid format for <strong>".$label."</strong> date.");
	} else {
		if(($use_times) && ((!isset($_POST[$fieldname."_hour"])))) {
			add_error("<strong>".$label."</strong> hour not entered.");
		} else {
			if(($use_times) && ((!isset($_POST[$fieldname."_min"])))) {
				add_error("<strong>".$label."</strong> minute not entered.");
			} else {
				$pieces	= explode("-", $_POST[$fieldname."_date"]);
				$hour	= (($use_times) ? (int) trim($_POST[$fieldname."_hour"]) : 0);
				$minute	= (($use_times) ? (int) trim($_POST[$fieldname."_min"]) : 0);
				$second	= 0;
				$month	= (int) trim($pieces[1]);
				$day	= (int) trim($pieces[2]);
				$year	= (int) trim($pieces[0]);

				$timestamp = mktime($hour, $minute, $second, $month, $day, $year);
			}
		}
	}

	return $timestamp;
}

function generate_organisation_select() {
	global $db, $MODULE, $organisations_list, $ENTRADA_ACL;
	$return = '';
	$return .= '<tr>
				<td style="vertical-align: top;"><input id="organisation_checkbox" type="checkbox" disabled="disabled" checked="checked"></td>
				<td style="vertical-align: top; padding-top: 4px;"><label for="organisation_id" class="form-required">Organisation</label></td>
				<td style="vertical-align: top;">
					<select id="organisation_id" name="organisation_id" style="width: 177px">';
	if(!isset($ORGANISATION_LIST)) {
		$query		= "SELECT `organisation_id`, `organisation_title` FROM `".AUTH_DATABASE."`.`organisations`";
		$results	= $db->GetAll($query);
	} else {
		$results = $ORGANISATION_LIST;
	}

	$all_organisations = false;
	if($results) {
		$all_organisations = true;
		foreach($results as $result) {
			if($ENTRADA_ACL->amIAllowed('resourceorganisation'.$result["organisation_id"], 'read')) {
				$return .= "<option value=\"".(int) $result["organisation_id"]."\"".(((isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["organisation_id"])) && ($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["organisation_id"] == $result["organisation_id"])) ? " selected=\"selected\"" : "").">".html_encode($result["organisation_title"])."</option>\n";
			} else {
				$all_organisations = false;
			}
		}
	}

	if($all_organisations) {
		$return .= '<option value="-1"'.(isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["organisation_id"]) && ($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["organisation_id"] == -1) ? " selected=\"selected\"" : "").'>All organisations</option>';
	}

	$return .= '
					</select>
				</td>
			</tr>';

	return $return;
}
/**
 * Works like PHP function strip_tags, but it only removes selected tags.
 * @example strip_selected_tags('<b>Person:</b> <strong>Salavert</strong>', 'strong') => <b>Person:</b> Salavert
 */
function strip_selected_tags($string, $tags = array()) {
	$args	= func_get_args();
	$string	= array_shift($args);
	$tags	= ((func_num_args() > 2) ? array_diff($args, array($string)) : (array) $tags);

	foreach ($tags as $tag) {
		while(preg_match_all("/<".$tag."[^>]*>(.*)<\/".$tag.">/iU", $string, $found)) {
			$string = str_replace($found[0], $found[1], $string);
		}
	}

	return $string;
}

/**
 * Builds the description of the learning event from the Curriculum Search tool.
 *
 * @param string $page_content
 * @param string $search_query
 * @return unknown
 */
function search_description($description = "") {
	global $SEARCH_MODE, $SEARCH_QUERY;

	$search_term	= clean_input($SEARCH_QUERY, array("notags", "boolops", "quotemeta", "trim"));
	$description	= clean_input($description, array("notags", "trimds", "trim"));
	$description	= preg_replace("/(".$search_term.")/i", "<span class=\"highlight\">\\1</span>", $description);

	$wordpos		= strpos(strtolower($description), strtolower($search_term));
	$halfside		= intval($wordpos - 500 / 2 - strlen($search_term));

	if ($wordpos && $halfside > 0) {
		return "...".substr($description, $halfside, 500);
	} else {
		return substr($description, 0, 500);
	}
}

/**
 * Function is responsible for adding statistics to the database.
 *
 * @param string $module_name
 * @param string $action
 * @param string $action_field
 * @param string $action_value
 * @return bool
 */
function add_statistic($module_name = "", $action = "", $action_field = "", $action_value = "", $proxy_id = 0) {
	global $MODULE, $db;

	if(!$module_name) {
		if(!$MODULE) {
			$module_name	= "unknown";
		} else {
			$module_name	= $MODULE;
		}
	}

	if (((int) $proxy_id == 0) && isset($_SESSION["details"]["id"])) {
		$proxy_id = (int) $_SESSION["details"]["id"];
	}

	$stat					= array();
	$stat["proxy_id"]		= $proxy_id;
	$stat["timestamp"]		= time();
	$stat["module"]			= $module_name;
	$stat["action"]			= $action;
	$stat["action_field"]	= $action_field;
	$stat["action_value"]	= $action_value;
	$stat["prune_after"]	= mktime(0, 0, 0, 8, 15, (date("Y", time()) + 1));

	if(!$db->AutoExecute("statistics", $stat, "INSERT")) {
		application_log("error", "Unable to add entry to statistics table. Database said: ".$db->ErrorMsg());

		return false;
	} else {
		return true;
	}
}

/**
 * Function checks to ensure the e-mail address is valid.
 *
 * @param string $address
 * @return bool
 */
function valid_address($address = "", $mode = 0) {
	switch((int) $mode) {
		case 2 :	// Strict
			$regex = "/^([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i";
		break;
		case 1 :	// Promiscuous
			$regex = "/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i";
		break;
		default :	// Recommended
			$regex = "/^([*+!.&#$|0-9a-z^_=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i";
		break;
	}

	if(preg_match($regex, trim($address))) {
		return true;
	} else {
		return false;
	}
}

/**
 * Function adds the appropriate fade actions to the online event.
 *
 * @param string $direction
 * @param string $element_id
 * @param int $start_opacity
 * @param int $fade_timeout
 */
function fade_element($direction = "out", $element_id, $start_opacity = 100, $fade_timeout = 0) {
	global $ONLOAD;

	if(!$fade_timeout) {
		$fade_timeout = 6000;
	}

	$ONLOAD[] = "window.setTimeout('Effect.".(($direction != "out") ? "Appear" : "Fade")."(\'".addslashes($element_id)."\')', ".(int) $fade_timeout.")";

	return;
}

/**
 * The nl2bar function doesn't remove the \n, it merely adds <br /> to the front of it.
 * Well, I need it removed somtimes.
 *
 * @param string $text
 * @return string
 */
function nl2br_replace($text) {
	return str_replace(array("\r", "\n"), array("", "<br />"), $text);
}

/**
 * I also need to convert <br>'s back to \n's (since I removed them already).
 *
 * @param string $text
 * @return string
 */
function br2nl_replace($text) {
	return  preg_replace("/<br\\\\s*?\\/??>/i", "\\n", $text);
}

/**
 * This is ADOdb's function that is called after a logout has occurred.
 * I can't think of any current uses for it; however, it might be useful
 * in the future.
 *
 * @param string $expireref
 * @param string $sesskey
 */
function NotifyFn($expireref, $sesskey) {
	global $ADODB_SESS_CONN;
}

/**
 * This silly little function returns the feedback URL's $_GET["enc"] variable.
 *
 * @return unknown
 */
function feedback_enc() {
	return base64_encode(serialize(array("url" => $_SERVER["REQUEST_URI"])));
}

/**
 * Any time this function is called, the user will be required to authenticate using
 * HTTP Authentication.
 *
 */
function http_authenticate() {
	header("WWW-Authenticate: Basic realm=\"".APPLICATION_NAME."\"");
	header("HTTP/1.0 401 Unauthorized");
	echo "You must enter a valid username and password to access this resource.\n";
	exit;
}

/**
 * Basic function to create a help icon.
 *
 * @todo Actually make this work, not DomTT but something similar.
 * @param string $help_title
 * @param string $help_content
 * @return string
 */
function help_create_button($help_title = "", $help_content = "") {
	$output = "<img src=\"".ENTRADA_URL."/images/btn_help.gif\" width=\"16\" height=\"16\" alt=\"Help: ".html_encode($help_title)."\" title=\"Help: ".html_encode($help_title)."\" style=\"vertical-align: middle\" />";

	return $output;
}

/**
 * Function will load TinyMCE (WYSIWYG / Rich Text Editor) into the page <head></head>
 * causing all textareas on the page to be replaced with rte's.
 *
 * @param array $buttons
 * @return true
 */
function load_rte($buttons = array(), $plugins = array(), $other_options = array()) {
	global $HEAD;

	$rte_set = "basic";

	/**
	 * If $buttons is scalar then assign the set, and include the requested
	 * buttons.
	 */
	if ((!is_array($buttons)) || (!count($buttons))) {
	/**
	 * If you are specifying a button set, apply it, otherwise the default
	 * will be used.
	 */
		if ((is_scalar($buttons)) && ($tmp_input = clean_input($buttons, "alpha"))) {
			$rte_set = $tmp_input;
		}

		$buttons = array();

		switch ($rte_set) {
			case "advanced" :
				$buttons[1]	= array ("fullscreen", "styleprops", "|", "formatselect", "fontselect", "fontsizeselect", "|", "bold", "italic", "underline", "forecolor", "backcolor", "|", "justifyleft", "justifycenter", "justifyright", "justifyfull");
				$buttons[2]	= array ("replace", "pasteword", "pastetext", "|", "undo", "redo", "|", "tablecontrols", "|", "insertlayer", "moveforward", "movebackward", "absolute", "|", "visualaid");
				$buttons[3]	= array ("ltr", "rtl", "|", "outdent", "indent", "|", "bullist", "numlist", "|", "link", "unlink", "anchor", "image", "media", "|", "sub", "sup", "|", "charmap", "insertdate", "inserttime", "nonbreaking", "|", "cleanup", "code", "removeformat");
			break;
			case "community" :
				$buttons[1] = array("bold", "italic", "underline", "strikethrough", "|", "link", "unlink", "anchor", "image", "media", "|", "numlist", "bullist", "|", "outdent", "indent", "blockquote", "|", "undo", "redo", "|", "pasteword", "cleanup", "removeformat", "code");
			break;
			case "basic" :
			default :
				$buttons[1] = array("bold", "italic", "underline", "strikethrough", "|", "link", "|", "numlist", "bullist", "|", "outdent", "indent", "blockquote", "|", "undo", "redo", "|", "pasteword", "cleanup", "removeformat", "code", "save");
				$buttons[2] = array();
				$buttons[3] = array();
			break;
		}
	}

	/**
	 * If $plugins isn't an array or if it's empty, apply basic plugins,
	 * otherwise add the plugin set.
	 */
	if ((!is_array($plugins)) || (!count($plugins))) {
		switch ($rte_set) {
			case "advanced" :
				$plugins = array("preview", "inlinepopups", "style", "layer", "table", "advimage", "advlink", "media", "insertdatetime", "contextmenu", "paste", "directionality", "fullscreen", "noneditable", "visualchars", "nonbreaking", "xhtmlxtras", "tabfocus");
			break;
			case "community" :
				$plugins = array("autosave", "contextmenu", "advimage", "advlink", "media", "paste", "inlinepopups", "tabfocus");
			break;
			case "basic" :
			default :
				$plugins = array("autosave", "save", "contextmenu", "paste", "inlinepopups", "tabfocus");
			break;
		}
	}

	/**
	 * If $other_options isn't an array or if it's empty, don't do anything,
	 * otherwise add the extra options.
	 */
	if ((!is_array($other_options)) || (!count($other_options))) {
		$other_options = array();

		switch ($rte_set) {
			case "advanced" :
			case "community" :
			case "basic" :
			default :
				continue;
			break;
		}
	}

	$tinymce  = "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/tiny_mce/tiny_mce.js?release=".html_encode(APPLICATION_VERSION)."\"></script>\n";
	$tinymce .= "<script type=\"text/javascript\">\n";
	$tinymce .= "tinyMCE.init({\n";
	$tinymce .= "	mode : 'textareas',\n";
	$tinymce .= "	theme : 'advanced',\n";
	$tinymce .= "	plugins : '".implode(",", $plugins)."',\n";
	$tinymce .= "	editor_deselector : 'expandable',\n";
	$tinymce .= "	save_enablewhendirty : true,\n";
	$tinymce .= "	theme_advanced_layout_manager : 'SimpleLayout',\n";
	$tinymce .= "	theme_advanced_toolbar_location : 'top',\n";
	$tinymce .= "	theme_advanced_toolbar_align : 'left',\n";
	$tinymce .= "	theme_advanced_statusbar_location : 'bottom',\n";
	$tinymce .= "	theme_advanced_resizing : true,\n";
	$tinymce .= "	theme_advanced_resize_horizontal : false,\n";
	$tinymce .= "	theme_advanced_resizing_use_cookie : true,\n";
	$tinymce .= "	paste_auto_cleanup_on_paste : true,\n";
	$tinymce .= "	paste_convert_middot_lists : true,\n";
	$tinymce .= "	paste_convert_headers_to_strong : true,\n";
	$tinymce .= "	paste_remove_spans : true,\n";
	$tinymce .= "	paste_remove_styles : true,\n";
	$tinymce .= "	force_p_newlines : false,\n";
	$tinymce .= "	force_br_newlines : true,\n";
	$tinymce .= "	forced_root_block : false,\n";
	$tinymce .= "	relative_urls : false,\n";
	$tinymce .= "	remove_script_host : false,\n";
	$tinymce .= "	paste_strip_class_attributes : 'all',\n";
	$tinymce .= "	theme_advanced_buttons1 : '".(((is_array($buttons)) && (is_array($buttons[1])) && (count($buttons[1]))) ? implode(",", $buttons[1]) : "")."',\n";
	$tinymce .= "	theme_advanced_buttons2 : '".(((is_array($buttons)) && (is_array($buttons[2])) && (count($buttons[2]))) ? implode(",", $buttons[2]) : "")."',\n";
	$tinymce .= "	theme_advanced_buttons3 : '".(((is_array($buttons)) && (is_array($buttons[3])) && (count($buttons[3]))) ? implode(",", $buttons[3]) : "")."',\n";
	$tinymce .= "	tab_focus : ':prev,:next',\n";
	$tinymce .= "	extended_valid_elements : 'a[name|href|target|title|class],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style],object[classid|width|height|codebase|data|type|*]'";
	$tinymce .= 	((count($other_options)) ? ",\n\t".implode(",\n\t", $other_options) : "")."\n";
	$tinymce .= "});\n";
	$tinymce .= "function toggleEditor(id) {\n";
	$tinymce .= "	if(!tinyMCE.getInstanceById(id)) {\n";
	$tinymce .= "		tinyMCE.execCommand('mceAddControl', false, id);\n";
	$tinymce .= "	} else {\n";
	$tinymce .= "		tinyMCE.execCommand('mceRemoveControl', false, id);\n";
	$tinymce .= "	}\n";
	$tinymce .= "}\n";
	$tinymce .= "</script>\n";

	/**
	 * You must add this first in the $HEAD array because TinyMCE will
	 * not load if scriptaculous is loaded before it (PITA). Do you know
	 * how long it took me to figure this out? ARG.
	 * Ref: http://wiki.script.aculo.us/scriptaculous/show/TinyMCE
	 */
	if ((is_array($HEAD)) && (count($HEAD))) {
		array_unshift($HEAD, $tinymce);
	} else {
		$HEAD[] = $tinymce;
	}

	return true;
}

/**
 * COMMUNITY SYSTEM FUNCTIONS
 * These functions are specific to the commmunity system. All community modules
 * are prefixed with communities_.
 *
 */

/**
 * @todo Write this function and have it go to someone other than Matt Simpson.
 *
 * @return bool
 */
function communities_approval_notice() {
	return @mail($AGENT_CONTACTS["administrator"]["email"], "New Community To Approve", "Please review the latest community which has requested to be put in a sanctioned category.", "From: ".$AGENT_CONTACTS["administrator"]["name"]." <".$AGENT_CONTACTS["administrator"]["email"].">");
}

/**
 * Function will load TinyMCE (WYSIWYG / Rich Text Editor) into the page <head></head>
 * causing all textareas on the page to be replaced with rte's.
 *
 * @param array $buttons
 * @return true
 */
function communities_load_rte($buttons = array(), $plugins = array(), $other_options = array()) {
	if (!is_array($buttons) || !count($buttons)) {
		$buttons = "community";
	}

	return load_rte($buttons, $plugins, $other_options);
}

/**
 * This function handles data in the users_online table by inserting, updating or deleting
 * the details accordingly. This function is called every page load in the admin.php file.
 *
 * @param string $action
 */
function users_online($action = "default") {
	global $db;

	switch($action) {
		case "logout" :
			if((isset($_SESSION["details"]["id"])) && ((int) $_SESSION["details"]["id"])) {
			/**
			 * This query will delete only the exact session information, but it's probably better to delete
			 * everthing about this user is it not? I don't know.
			 * $query = "DELETE FROM `users_online` WHERE `session_id` = ".$db->qstr(session_id())." AND `proxy_id` = ".$db->qstr((int) $_SESSION["details"]["id"])." LIMIT 1";
			 */
				$query = "DELETE FROM `users_online` WHERE `proxy_id` = ".$db->qstr((int) $_SESSION["details"]["id"]);
				if(!$db->Execute($query)) {
					application_log("error", "Loggout: Failed to delete users_online entry for proxy id ".$_SESSION["details"]["id"].". Database said: ".$db->ErrorMsg());
				}
			}
			break;
		case "default" :
		default :
			if((isset($_SESSION["isAuthorized"])) && ((bool) $_SESSION["isAuthorized"])) {
				$query	= "SELECT * FROM `users_online` WHERE `session_id` = ".$db->qstr(session_id());
				$result	= $db->GetRow($query);
				if($result) {
					$query = "UPDATE `users_online` SET `timestamp` = ".$db->qstr(time())." WHERE `session_id` = ".$db->qstr(session_id());
					if(!$db->Execute($query)) {
						application_log("error", "Unable to update the users_online timestamp. Database said: ".$db->ErrorMsg());
					}
				} else {
					$query = "INSERT INTO `users_online` (`session_id`, `ip_address`, `proxy_id`, `username`, `firstname`, `lastname`, `timestamp`) VALUES (".$db->qstr(session_id()).", ".$db->qstr($_SERVER["REMOTE_ADDR"]).", ".$db->qstr($_SESSION["details"]["id"]).", ".$db->qstr($_SESSION["details"]["username"]).", ".$db->qstr($_SESSION["details"]["firstname"]).", ".$db->qstr($_SESSION["details"]["lastname"]).", ".$db->qstr(time()).")";
					if(!$db->Execute($query)) {
						application_log("error", "Unable to insert a users_online record. Database said: ".$db->ErrorMsg());
					}
				}
			}
			break;
	}

	return true;
}

/**
 * This function handles removing old entries from the users_online table after the sessions have expired.
 *
 * @param string $expireref
 * @param string $sesskey
 * @return true
 */
function expired_session($expireref, $sesskey) {
	global $db;
	$query = "DELETE FROM `users_online` WHERE `session_id` = ".$db->qstr($sesskey)." LIMIT 1";
	if(!$db->Execute($query)) {
		application_log("error", "Expired: Failed to delete users_online entry for proxy id ".$expireref.". Database said: ".$db->ErrorMsg());
	}
	return true;
}

function display_weather($city_code = "", $options = array(), $weather_source = "weather.com") {
	global $WEATHER_LOCATION_CODES;

	$output_html	= "";
	$weather		= array();
	$weather_codes	= array();

	if((isset($city_code)) && ($city_code)) {
		if(is_array($city_code)) {
			foreach ($city_code as $value) {
				if($value = clean_input($value, array("alphanumeric"))) {
					$weather_codes[$value] = ((isset($WEATHER_LOCATION_CODES[$value])) ? $WEATHER_LOCATION_CODES[$value] : "");
				}
			}
		} else {
			if($city_code = clean_input($city_code, array("alphanumeric"))) {
				$weather_codes[$city_code] = ((isset($WEATHER_LOCATION_CODES[$value])) ? $WEATHER_LOCATION_CODES[$value] : "");
			}
		}
	}

	if((!is_array($weather_codes)) || (count($weather_codes) < 1)) {
		$weather_codes = $WEATHER_LOCATION_CODES;
	}

	if(is_array($weather_codes)) {
		foreach ($weather_codes as $weather_code => $city_name) {
			if(@file_exists(CACHE_DIRECTORY."/weather-".$weather_code.".xml")) {
				$xml		= @simplexml_load_file(CACHE_DIRECTORY."/weather-".$weather_code.".xml");
				$weather	= array();

				if ($xml) {
					$weather["icon"]		= $xml->cc->icon;
					$weather["tmp"]			= $xml->cc->tmp;
					$weather["conditions"]	= $xml->cc->t;
					$weather["flik"]		= $xml->cc->flik;
					$weather["s"]			= $xml->cc->wind->s;
					$weather["windir"]		= $xml->cc->wind->t;
					$weather["sunr"]		= $xml->loc->sunr;
					$weather["suns"]		= $xml->loc->suns;
							} else {
					$weather["icon"]		= "0";
					$weather["tmp"]			= "?";
					$weather["conditions"]	= "Unknown";
					$weather["flik"]		= "?";
					$weather["s"]			= "calm";
					$weather["windir"]		= "";
					$weather["sunr"]		= "Unknown";
					$weather["suns"]		= "Unknown";
				}

				$output_html .= "<table style=\"width: 100%; margin-bottom: 15px\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";
				$output_html .= "<thead>\n";
				$output_html .= "	<tr>\n";
				$output_html .= "		<td colspan=\"2\"><h2>".html_encode($city_name)."</h2></td>\n";
				$output_html .= "	</tr>\n";
				$output_html .= "</thead>\n";
				$output_html .= "<tbody>\n";
				$output_html .= "	<tr>\n";
				$output_html .= "		<td style=\"text-align: center; vertical-align: middle\">\n";
				$output_html .= "			<a href=\"http://www.weather.com/weather/local/".$weather_code."\" target=\"_blank\"><img src=\"".ENTRADA_URL."/images/weather/".((!(int) $weather["icon"]) ? "na" : (int) $weather["icon"]).".png\" width=\"64\" height=\"64\" border=\"0\" alt=\"".html_encode($weather["conditions"]).": click for detailed forecast.\" title=\"".html_encode($weather["conditions"]).": click for detailed forecast.\" /></a>";
				$output_html .= "		</td>\n";
				$output_html .= "		<td style=\"text-align: center; vertical-align: middle\">\n";
				$output_html .= "			<h1 style=\"font-size: 28px; margin: 0px\">".((int) $weather["tmp"])."&#176;C</h1>";
				$output_html .= "			<div class=\"content-small\" style=\"font-weight: bold\">".$weather["conditions"]."</div>";
				$output_html .= "		</td>\n";
				$output_html .= "	</tr>\n";

				if($weather["tmp"] != $weather["flik"]) {
					$output_html .= "<tr>\n";
					$output_html .= "	<td class=\"content-small\" style=\"text-align: right; padding-right: 10px\">Feels like:</td>\n";
					$output_html .= "	<td class=\"content-small\">".((int) $weather["flik"])."&#176;C</td>";
					$output_html .= "</tr>\n";
				}

				$output_html .= "	<tr>\n";
				$output_html .= "		<td class=\"content-small\" style=\"text-align: right; padding-right: 10px\">Wind:</td>\n";
				$output_html .= "		<td class=\"content-small\">".(($weather["s"] == "calm") ? "Calm" : html_encode($weather["windir"])." @ ".html_encode($weather["s"])." km/h")."</td>";
				$output_html .= "	</tr>\n";
				$output_html .= "	<tr>\n";
				$output_html .= "		<td class=\"content-small\" style=\"text-align: right; padding-right: 10px\">Dawn:</td>\n";
				$output_html .= "		<td class=\"content-small\">".$weather["sunr"]."</td>";
				$output_html .= "	</tr>\n";
				$output_html .= "	<tr>\n";
				$output_html .= "		<td class=\"content-small\" style=\"text-align: right; padding-right: 10px\">Dusk:</td>\n";
				$output_html .= "		<td class=\"content-small\">".$weather["suns"]."</td>";
				$output_html .= "	</tr>\n";
				$output_html .= "</tbody>\n";
				$output_html .= "</table>\n";
			}
		}
	}

	return $output_html;
}

/**
 * This function will generate a fairly random hash code which
 * can be used in a number of situations.
 *
 * @param int $num_chars
 * @return string
 */
function generate_hash($num_chars = 32) {
	if(!$num_chars = (int) $num_chars) {
		$num_chars = 32;
	}

	return substr(hash("sha256", uniqid(rand(), 1)), 0, $num_chars);
}

/**
 * Community function responsible for logging every historical event that takes place in a community.
 * This is used to display commmunity history in the community (if display_message is 1) and it is
 * also used to get stats on the most active communities.
 *
 * @param int $community_id
 * @param int $page_id
 * @param int $record_id
 * @param string $message
 * @param int $display_message
 * @param int $parent_id
 * @return bool
 */
function communities_log_history($community_id = 0, $page_id = 0, $record_id = 0, $history_message = "", $display_message = 0, $parent_id = 0) {
	global $db;

	if(($community_id = (int) $community_id) && (strlen(trim($history_message)))) {
		$page_id			= (int) $page_id;
		$record_id			= (int) $record_id;
		$display_message	= (((int) $display_message) ? 1 : 0);

		$query = "INSERT INTO `community_history` (`community_id`, `cpage_id`, `record_id`, `record_parent`, `proxy_id`, `history_key`, `history_display`, `history_timestamp`) VALUES (".$db->qstr($community_id).", ".$db->qstr($page_id).", ".$db->qstr($record_id).", ".$db->qstr($parent_id).", ".$db->qstr((int) $_SESSION["details"]["id"]).", ".$db->qstr($history_message).", ".$db->qstr($display_message).", ".$db->qstr(time()).")";
		if($db->Execute($query)) {
			return true;
		} else {
			application_log("error", "Unable to insert historical community event. Database said: ".$db->ErrorMsg());
		}
	}

	return false;
}

/**
 * This function sets two variables used to generate community history log output lines.
 *
 * @param string $history_key
 * @param int $record_id
 * @param int $page_id
 * @param int $community_id
 */
function community_history_record_title($history_key = "", $record_id = 0, $page_id = 0, $community_id = 0, $proxy_id = 0) {
	global $db, $record_title, $parent_id;
	switch ($history_key) {
		case "community_history_add_announcement" :
		case "community_history_edit_announcement" :
			$query = "SELECT (`announcement_title`) as `record_title` FROM `community_announcements` WHERE `cannouncement_id` = ".$db->qstr($record_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_forum" :
		case "community_history_edit_forum" :
			$query = "SELECT (`forum_title`) as `record_title` FROM `community_discussions` WHERE `cdiscussion_id` = ".$db->qstr($record_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_post" :
		case "community_history_edit_post" :
			$query = "SELECT (`topic_title`) as `record_title` FROM `community_discussion_topics` WHERE `cdtopic_id` = ".$db->qstr($record_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_edit_reply" :
		case "community_history_add_reply" :
			$query = "SELECT (b.`topic_title`) as `record_title`, (a.`cdtopic_parent`) as `parent_id` FROM `community_discussion_topics` as a LEFT JOIN `community_discussion_topics` as b ON a.`cdtopic_parent` = b.`cdtopic_id` WHERE a.`cdtopic_id` = ".$db->qstr($record_id)." AND a.`community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_event" :
		case "community_history_edit_event" :
			$query = "SELECT (`event_title`) as `record_title` FROM `community_events` WHERE `cevent_id` = ".$db->qstr($record_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_photo_comment" :
		case "community_history_edit_photo_comment" :
			$query = "SELECT (`photo_title`) as `record_title` FROM `community_gallery_photos` WHERE `cgphoto_id` = ".$db->qstr($parent_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_photo" :
		case "community_history_edit_photo" :
		case "community_history_move_photo" :
			$query = "SELECT (`photo_title`) as `record_title` FROM `community_gallery_photos` WHERE `cgphoto_id` = ".$db->qstr($record_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_gallery" :
		case "community_history_edit_gallery" :
			$query = "SELECT (`gallery_title`) as `record_title` FROM `community_galleries` WHERE `cgallery_id` = ".$db->qstr($record_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_poll" :
		case "community_history_edit_poll" :
			$query = "SELECT (`poll_title`) as `record_title` FROM `community_polls` WHERE `cpolls_id` = ".$db->qstr($record_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_file_comment" :
		case "community_history_edit_file_comment" :
			$query = "SELECT (`file_title`) as `record_title` FROM `community_share_files` WHERE `csfile_id` = ".$db->qstr($parent_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_file" :
		case "community_history_edit_file" :
		case "community_history_move_file" :
			$query = "SELECT (`file_title`) as `record_title` FROM `community_share_files` WHERE `csfile_id` = ".$db->qstr($record_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_add_share" :
		case "community_history_edit_share" :
			$query = "SELECT (`folder_title`) as `record_title` FROM `community_shares` WHERE `cshare_id` = ".$db->qstr($record_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_create_moderated_community" :
		case "community_history_create_active_community" :
		case "community_history_rename_community" :
			$query = "SELECT (`community_title`) as `record_title` FROM `communities` WHERE `community_id` = ".$db->qstr($community_id);
			break;
		case "community_history_activate_module" :
			$query = "SELECT (`module_title`) as `record_title` FROM `communities_modules` WHERE `module_id` = ".$db->qstr($record_id);
			break;
		case "community_history_add_page" :
		case "community_history_edit_page" :
			$query = "SELECT (`menu_title`) as `record_title` FROM `community_pages` WHERE `cpage_id` = ".$db->qstr($page_id);
			break;
		case "community_history_edit_community" :
			$query = "SELECT CONCAT_WS(' ', `firstname`, `lastname`) as `record_title` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id` = ".$db->qstr($record_id);
			break;
		case "community_history_add_member" :
			$query = "SELECT CONCAT_WS(' ', `firstname`, `lastname`) as `record_title` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id` = ".$db->qstr($proxy_id);
			break;
	}
	$result = $db->GetRow($query);

	if ($result) {
		$record_title = $result["record_title"];
		if (isset($result["parent_id"]) && $result["parent_id"]) {
			$parent_id = $result["parent_id"];
		}
	}
}

/**
 * Community function responsible for deactivating community history logs
 * for items which have been deleted or deactivated.
 *
 * @param int $community_id
 * @param int $page_id
 * @param int $record_id
 * @return bool
 */
function communities_deactivate_history($community_id = 0, $page_id = 0, $record_id = 0) {
	global $db;

	if(($community_id = (int) $community_id)) {
		$page_id = (int) $page_id;
		$record_id = (int) $record_id;

		$query = "UPDATE `community_history` SET `history_display` = '0' WHERE `community_id` = ".$db->qstr($community_id)." AND `cpage_id` = ".$db->qstr($page_id).(((int)$record_id) > 0 ? " AND `record_id` = ".$db->qstr($record_id) : "");
		if($db->Execute($query)) {
			if ($record_id) {
				communities_deactivate_children($community_id, $page_id, $record_id);
			}
			return true;
		} else {
			application_log("error", "Unable to deactivate historical community event. Database said: ".$db->ErrorMsg());
		}
	}

	return false;
}

function communities_deactivate_children($community_id, $page_id, $parent_id) {
	global $db;
	$query = "SELECT `record_id` FROM `community_history` WHERE `community_id` = ".$db->qstr($community_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `record_parent` = ".$db->qstr($parent_id);

	$results = $db->GetAll($query);
	foreach ($results as $result) {
		communities_deactivate_children($community_id, $page_id, $result["record_id"]);
	}
	$db->Execute("UPDATE `community_history` SET `history_display` = '0' WHERE `community_id` = ".$db->qstr($community_id)." AND `cpage_id` = ".$db->qstr($page_id)." AND `record_parent` = ".$db->qstr($parent_id));

}

/**
 * Function is responsible for counting the total number of communities under
 * the specified category_id.
 *
 * @param int $category_id
 * @return int
 */
function communities_count($category_id = 0) {
	global $db;

	$query	= "SELECT COUNT(*) AS `total` FROM `communities` WHERE".(($category_id = (int) trim($category_id)) ? " `category_id` = ".$db->qstr($category_id)." AND" : "")." `community_active` = '1'";
	$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
	if($result) {
		return (int) $result["total"];
	}

	return 0;
}

/**
 * Function will return all information on the provided category_id from the database.
 *
 * @param int $category_id
 * @return array
 */
function communities_fetch_category($category_id = 0) {
	global $db;

	if($category_id = (int) $category_id) {
		$query	= "SELECT * FROM `communities_categories` WHERE `category_id` = ".$db->qstr($category_id);
		$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
		if($result) {
			return $result;
		}
	}

	return false;
}

/**
 * @todo Is this used? Does it work? It was previously undocumented.
 *
 * @param int $community_id
 * @return string
 */
function communities_generate_url($community_id = 0) {
	global $db;

	if(!$community_id = (int) $community_id) {
		return false;
	}

	$fetched = array();
	communities_fetch_parents($community_id, $fetched, 0, false);

	return $fetched;

	return "/".((@count($path)) ? implode("/", array_reverse($path))."/" : "");
}

/**
 * Function will return the communities above the specified community_id, as an array.
 *
 * @param int $community_id
 * @return array
 */
function communities_regenerate_url($community_parent = 0) {

	if(!$community_parent = (int) $community_parent) {
		return false;
	}

	$community_url	= "";
	$fetched		= array();

	communities_fetch_parents($community_parent, $fetched);

	if((is_array($fetched)) && (count($fetched))) {
		$communities = array_reverse($fetched);
		unset($fetched);

		foreach ($communities as $community) {
			$community_url .= "/".$community["community_shortname"];
		}
	}

	return $fetched;
}

/**
 * Recursive function will return all communities above the specified community_id, as an array.
 *
 * @uses This function can be used to generate breadcrumb trails, create maps / paths, etc.
 * @param int $community_id
 * @param array $fetched
 * @param int $level
 * @param bool $fetch_top
 * @return array
 */
function communities_fetch_parents($community_id = 0, &$fetched, $level = 0, $fetch_top = true) {
	global $db;

	if($level > 99) {
		return false;
	}

	if(!$community_id = (int) $community_id) {
		return false;
	}

	$query	= "SELECT `community_id`, `community_parent`, `community_url`, `community_shortname`, `community_title`, `community_active` FROM `communities` WHERE `community_id` = ".$db->qstr($community_id);
	$result	= $db->GetRow($query);
	if($result) {
		$fetched[$result["community_id"]] = $result;

		/**
		 * If you want to fetch to the top, this becomes a recursive function.
		 */
		if((bool) $fetch_top) {
			communities_fetch_parents($result["community_parent"], $fetched, $level + 1, true);
		}
	}
	return true;
}

/**
 * Function will return the communities above the specified community_id, as an array.
 *
 * @param int $community_id
 * @return array
 */
function communities_fetch_parent($community_id = 0) {

	if(!$community_id = (int) $community_id) {
		return false;
	}

	$fetched = array();
	communities_fetch_parents($community_id, $fetched, 0, false);

	return $fetched;
}

/**
 * Recursive function will return all communities below the specified community_id, as an array.
 *
 * @param int $community_id
 * @param array $requested_fields
 * @param int $max_generations
 * @param bool $show_inactive
 * @param int $level
 * @return array
 */
function communities_fetch_children($community_id = 0, $requested_fields = false, $max_generations = 0, $show_inactive = false, $output_type = false, $level = 0) {
	global $db, $COMMUNITIES_FETCH_CHILDREN;

	if($level > 99) {
		return false;
	}

	if(($output_type) && (!in_array($output_type, array("array", "select")))) {
		return false;
	}

	if((!is_array($requested_fields)) || (!count($requested_fields))) {
		$requested_fields = array("community_id", "community_parent", "community_url", "community_title", "community_active");
	}

	$fetched	= array();
	$query		= "
				SELECT `".implode("`, `", $requested_fields)."`
				FROM `communities`
				WHERE `community_parent` = ".$db->qstr((int) $community_id)."
				".((!(bool) $show_inactive) ? " AND `community_active` = '1'" : "")."
				ORDER BY `community_title` ASC";
	$results	= $db->GetAll($query);
	if($results) {
		foreach ($results as $result) {
			$fetched[$result["community_id"]] = $result;

			if($output_type) {
				$fetched[$result["community_id"]]["indent_level"]	= $indent;
			}

			if((!$max_generations) || ($level < $max_generations)) {
				$children = communities_fetch_children($result["community_id"], $requested_fields, $max_generations, $show_inactive, $output_type, $level + 1);

				if((is_array($children)) && (@count($children))) {
					$fetched[$result["community_id"]]["community_children"]	= $children;
				} else {
					$fetched[$result["community_id"]]["community_children"] = array();
				}
			} else {
				$fetched[$result["community_id"]]["community_children"] = array();
			}
		}
	}

	switch($output_type) {
		case "select" :
			$html = "";
			if((is_array($fetched)) && (count($fetched))) {
				foreach ($fetched as $result) {
					$html .= "<option value=\"".$result["community_id"]."\"".(((is_array($COMMUNITIES_FETCH_CHILDREN)) && (in_array($result["community_id"], $COMMUNITIES_FETCH_CHILDREN))) ? " selected=\"selected\"" : "").">".str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", $result["indent_level"]).html_encode($result["community_title"])."</option>\n";
				}
			}

			return $html;
			break;
		case "array" :
		default :
			return $fetched;
			break;
	}
}

/**
 * Function will return the title of the provided module_id.
 *
 * @param unknown_type $module_id
 * @return unknown
 */
function communities_title($community_id = 0) {
	global $db;

	if($community_id = (int) $community_id) {
		if($result = communities_details($community_id, array("community_title"))) {
			return $result["community_title"];
		}
	}

	return "Unknown Community";
}

/**
 * Function will return the requested information about the provided module_id.
 *
 * @param int $module_id
 * @param array $requested_info
 * @return array
 */
function communities_details($community_id = 0, $requested_info = array()) {
	global $db;

	if($community_id = (int) $community_id) {
		$field_names = array();

		if(!is_array($requested_info)) {
			$requested_info = array($requested_info);
		}

		if((@count($requested_info)) && ($module_columns = $db->MetaColumnNames("communities"))) {
			foreach ($requested_info as $field) {
				if(in_array($field, $module_columns)) {
					$field_names[] = $field;
				}
			}

			if(@count($field_names)) {
				$query	= "SELECT `".implode("`, `", $field_names)."` FROM `communities` WHERE `community_id` = ".$db->qstr($community_id);
				$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
				if($result) {
					return $result;
				}
			}
		}
	}

	return false;
}

/**
 * Function will return the title of the provided module_id.
 *
 * @param unknown_type $module_id
 * @return unknown
 */
function communities_module_title($module_id = 0) {
	global $db;

	if($module_id = (int) $module_id) {
		if($result = communities_module_details($module_id, array("module_title"))) {
			return $result["module_title"];
		}
	}

	return "Unknown Module";
}

/**
 * Function will return the requested information about the provided module_id.
 *
 * @param int $module_id
 * @param array $requested_info
 * @return array
 */
function communities_module_details($module_id = 0, $requested_info = array()) {
	global $db;

	if($module_id = (int) $module_id) {
		$field_names = array();

		if(!is_array($requested_info)) {
			$requested_info = array($requested_info);
		}

		if((@count($requested_info)) && ($module_columns = $db->MetaColumnNames("communities_modules"))) {
			foreach ($requested_info as $field) {
				if(in_array($field, $module_columns)) {
					$field_names[] = $field;
				}
			}

			if(@count($field_names)) {
				$query	= "SELECT `".implode("`, `", $field_names)."` FROM `communities_modules` WHERE `module_id` = ".$db->qstr($module_id);
				$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
				if($result) {
					return $result;
				}
			}
		}
	}

	return false;
}

/**
 * Wrapper function.
 *
 * @param unknown_type $community_id
 * @param unknown_type $module_id
 * @param unknown_type $action
 */
function communities_module_access($community_id = 0, $module_id = 0, $action = "index") {
	global $db;

	if(($community_id = (int) $community_id) && ($module_id = (int) $module_id) && ($action = trim($action))) {
		$query	= "SELECT * FROM `community_permissions` WHERE `community_id` = ".$db->qstr($community_id)." AND `module_id` = ".$db->qstr($module_id);
		$result	= $db->GetRow($query);
		if($result) {
			return communities_module_access_unique($community_id, $module_id, $action);
		} else {
			return communities_module_access_generic($module_id, $action);
		}
	}

	return false;
}
/**
 * Tells the module whether or not to load the specified action. This is the generic version which uses
 * the communities_modules table for overall results.
 *
 * @param int $module_id
 * @param string $action
 * @return bool
 */
function communities_module_access_generic($module_id = 0, $action = "index") {
	global $db, $LOGGED_IN, $COMMUNITY_MEMBER, $COMMUNITY_ADMIN, $PROXY_ID, $RECORD_AUTHOR, $PAGE_OPTIONS;

	$allow_to_load = false;

	if(((bool) $LOGGED_IN) && ((bool) $COMMUNITY_MEMBER) && ((bool) $COMMUNITY_ADMIN)) {
		$allow_to_load = true;
	} else {
		if($module_id = (int) $module_id) {
			$query	= "SELECT `module_permissions` FROM `communities_modules` WHERE `module_id` = ".$db->qstr($module_id);
			$result	= $db->GetRow($query);
			if($result) {
				if(($module_permissions = trim($result["module_permissions"])) && ($module_permissions = @unserialize($module_permissions)) && (@is_array($module_permissions))) {
					if(isset($module_permissions[$action])) {
						$query = "SELECT `module_shortname` FROM `communities_modules` WHERE `module_id` = ".$db->qstr($module_id);
						if((int) $module_permissions[$action] != 1) {
							$allow_to_load = true;
						} elseif ((($module_name = $db->GetOne($query)) && ($module_name == "events" || $module_name == "announcements")) && ($action == "edit" || $action == "add" || $action == "delete") && (($PAGE_OPTIONS["allow_member_posts"] && $COMMUNITY_MEMBER) || ($PAGE_OPTIONS["allow_troll_posts"] && $LOGGED_IN)) && ((!$RECORD_AUTHOR && ($action == "add" || $action == "delete")) || $RECORD_AUTHOR == $PROXY_ID)) {
							$allow_to_load = true;
						}
					}
				}
			}
		}
	}

	return $allow_to_load;
}

/**
 * Tells the module whether or not to load the specified action for the specified community.
 *
 * @param int $community_id
 * @param int $module_id
 * @param string $action
 * @return bool
 */
function communities_module_access_unique($community_id = 0, $module_id = 0, $action = "index") {
	global $db, $COMMUNITY_MEMBER, $COMMUNITY_ADMIN;

	$allow_to_load = false;

	if(($community_id = (int) $community_id) && ($module_id = (int) $module_id)) {
		$query		= "SELECT * FROM `community_permissions` WHERE `community_id` = ".$db->qstr($community_id)." AND `module_id` = ".$db->qstr($module_id)." AND (`action` = 'all' OR `action` = ".$db->qstr($action).")";
		$results	= $db->GetAll($query);
		if($results) {
			foreach ($results as $result) {
				if(($action == "index") || ((bool) $COMMUNITY_ADMIN) || ((int) $result["level"] === 0)) {
					$allow_to_load = true;
					break;
				}
			}
		}
	}

	return $allow_to_load;
}

/**
 * Activates speficied module for the specified community
 *
 * @param int $community_id
 * @param int $module_id
 * @return bool
 */
function communities_module_activate($community_id = 0, $module_id = 0) {
	global $db;

	if(($community_id = (int) $community_id) && ($module_id = (int) $module_id)) {
	/**
	 * Check that the requested module is present and active.
	 */
		$query			= "SELECT * FROM `communities_modules` WHERE `module_id` = ".$db->qstr($module_id)." AND `module_active` = '1'";
		$module_info	= $db->GetRow($query);
		if($module_info) {
			$query	= "SELECT * FROM `community_modules` WHERE `community_id` = ".$db->qstr($community_id)." AND `module_id` = ".$db->qstr($module_id);
			$result	= $db->GetRow($query);
			if($result) {
			/**
			 * If it is not already active, active it.
			 */
				if(!(int) $result["module_active"]) {
					if(!$db->AutoExecute("community_modules", array("module_active" => 1), "UPDATE", "`community_id` = ".$db->qstr($community_id)." AND `module_id` = ".$db->qstr($module_id))) {
						application_log("error", "Unable to active module ".(int) $module_id." (updating existing record) for updated community id ".(int) $COMMUNITY_ID.". Database said: ".$db->ErrorMsg());
					}
				}
			} else {
				if(!$db->AutoExecute("community_modules", array("community_id" => $community_id, "module_id" => $module_id, "module_active" => 1), "INSERT")) {
					application_log("error", "Unable to active module ".(int) $module_id." (inserting new record) for updated community id ".(int) $COMMUNITY_ID.". Database said: ".$db->ErrorMsg());
				}
			}

			$query	= "SELECT * FROM `community_pages` WHERE `community_id` = ".$db->qstr($community_id)." AND `page_active` = '1' AND `page_type` = ".$db->qstr($module_info["module_shortname"]);
			$result	= $db->GetRow($query);
			if(!$result) {
				$query		= "SELECT (MAX(`page_order`) + 1) as `order` FROM `community_pages` WHERE `community_id` = ".$db->qstr($community_id)." AND `page_active` = '1' AND `parent_id` = '0' AND `page_url` != ''";
				$result		= $db->GetRow($query);
				if($result) {
					$page_order = (int) $result["order"];
				} else {
					$page_order = 0;
				}

				if(($db->AutoExecute("community_pages", array("community_id" => $community_id, "page_order" => $page_order, "page_type" => $module_info["module_shortname"], "menu_title" => $module_info["module_title"], "page_title" => $module_info["module_title"], "page_url" => $module_info["module_shortname"], "page_content" => "", "updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "INSERT")) && ($cpage_id = $db->Insert_Id())) {

					communities_log_history($community_id, $cpage_id, 0, "community_history_add_page", 1);

				} else {
					application_log("error", "Unable to create page for module ".(int) $module_id." for new community id ".(int) $community_id.". Database said: ".$db->ErrorMsg());
				}
			}
		} else {
			application_log("error", "Module_id [".$module_id."] requested activation in community_id [".$community_id."] but the module is either missing or inactive.");
		}
	} else {
		application_log("error", "There was no community_id [".$community_id."] or module_id [".$module_id."] provided to active a module.");
	}

	return true;
}

/**
 * Deactivates speficied module for the specified community
 *
 * @param int $community_id
 * @param int $module_id
 * @return bool
 */
function communities_module_deactivate($community_id = 0, $module_id = 0) {
	global $db;

	if(($community_id = (int) $community_id) && ($module_id = (int) $module_id)) {
	/**
	 * Check that the requested module is present and active.
	 */
		$query			= "SELECT * FROM `communities_modules` WHERE `module_id` = ".$db->qstr($module_id);
		$module_info	= $db->GetRow($query);
		if($module_info) {
			$query		= "SELECT * FROM `community_pages` WHERE `community_id` = ".$db->qstr($community_id)." AND `page_active` = '1' AND `page_type` = ".$db->qstr($module_info["module_shortname"]);
			$results	= $db->GetAll($query);
			if($results) {
				foreach ($results as $result) {
					communities_pages_delete($result["cpage_id"]);
				}
			}
		}

		if($db->AutoExecute("community_modules", array("module_active" => 0), "UPDATE", "`community_id` = ".$db->qstr($community_id)." AND `module_id` = ".$db->qstr($module_id))) {
			return true;
		}
	}

	application_log("error", "Can't deactive module_id [".$module_id."] for community_id [".$community_id."]");

	return false;
}

/**
 * Responsible for fetching the modules which are enabled for a specific community_id.
 *
 * @param int $community_id
 * @return multi-dimensional array
 */
function communities_fetch_modules($community_id = 0) {
	global $db;

	$available	= array();

	if($community_id = (int) $community_id) {
		$query		= "
					SELECT b.`module_id`, b.`module_shortname`, b.`module_title`
					FROM `community_modules` AS a
					LEFT JOIN `communities_modules` AS b
					ON b.`module_id` = a.`module_id`
					WHERE b.`module_active` = '1'
					AND a.`module_active` = '1'
					AND a.`community_id` = ".$db->qstr($community_id);
		$results	= $db->GetAll($query);
		if($results) {
			$i = 1;
			foreach ($results as $result) {
				$available[$result["module_shortname"]]	= $result["module_title"];
				// Extra module information included here.
				switch($result["module_shortname"]) {
					case "announcements" :
						$i++;
						$available["calendar"]			= "Calendar";
						break;
					default:
						continue;
						break;
				}
				$i++;
			}
		}
	}

	return array("enabled" => $available);
}

/**
 * Responsible for fetching the modules which are enabled for a specific community_id.
 *
 * @param int $community_id
 * @return multi-dimensional array
 */
function communities_fetch_pages($community_id = 0, $user_access = 0) {
	global $db, $PAGE_URL;

	$navigation			= array();
	$available			= array();
	$details			= array();
	$available_ids		= array();

	$access_query_condition = array(	" `allow_public_view` = 1 ",
		" `allow_troll_view` = 1 ",
		" `allow_member_view` = 1 ",
		" 1 ");
		
	$community_access = 1;
	if ($user_access < 2) {
		$community_access = (int) $db->GetOne("SELECT `community_registration` from `communities` WHERE `community_id` =".$db->qstr($community_id)." AND `community_protected` = '1'");
	}
	if ($user_access == 1 && ((int) $db->GetOne("SELECT `community_registration` from `communities` WHERE `community_id` =".$db->qstr($community_id)." AND `community_protected` = '0'"))) {
		$user_access = 0;
	}

	$module_availability = $db->GetAll("SELECT a.*, b.`module_shortname` FROM `community_modules` AS a LEFT JOIN `communities_modules` AS b ON b.`module_id` = a.`module_id` WHERE a.`community_id` = ".$db->qstr($community_id));
	if ($module_availability) {
		foreach ($module_availability as $module_record) {
			$module_enabled[$module_record["module_shortname"]] = (((int) $module_record["module_active"]) == 1 ? true : false);
		}
	}
	$module_enabled["default"] = true;
	$module_enabled["url"] = true;
	$module_enabled["course"] = true;

	if(($community_id = (int) $community_id) && ($community_access < 4 || $user_access > 1)) {
		$home_title = $db->GetOne("SELECT `menu_title` FROM `community_pages` WHERE `community_id` =".$db->qstr($community_id)." AND `page_url` = ''");
		$navigation[0]	= array(	"link_order"	=> 0,
									"link_parent"	=> 0,
									"link_url"		=> "",
									"link_title"	=> (isset($home_title) && ($home_title != "") ? $home_title : "Home"),
									"link_selected" => (isset($result) && $result["page_url"] == $PAGE_URL ? true : false),
									"link_type"		=> "dashboard");

		$full_query		= "SELECT `cpage_id`, `page_url`, `menu_title`, `page_order`, `page_type` FROM `community_pages` WHERE `community_id` = ".$db->qstr($community_id)." AND `page_url` != '' AND `page_active` = '1' ORDER BY `page_order` ASC";
		$full_results	= $db->GetAll($full_query);
		if($full_results) {
			foreach ($full_results as $result) {
				$exists[$result["page_url"]] = $result["menu_title"];
			}
		}

		$available_query	= "SELECT `cpage_id`, `page_url`, `menu_title`, `page_order`, `page_type`, `page_content`, `page_visible` FROM `community_pages` WHERE `parent_id`='0' AND `community_id` =".$db->qstr($community_id)." AND ".$access_query_condition[$user_access]." AND `page_url` != '' AND `page_active` = '1' ORDER BY `page_order` ASC";
		$available_results	= $db->GetAll($available_query);
		if($available_results) {
			$i = 1;
			foreach ($available_results as $result) {
				if ($module_enabled[$result["page_type"]]) {
					if (((int)$result["page_visible"]) == 1) {
						if ($result["page_type"] == "url") {
							$query = "SELECT `option_value` FROM `community_page_options` WHERE `cpage_id` = ".$db->qstr($result["cpage_id"])." AND `option_title` = 'new_window'";
							$new_window = $db->GetOne($query);
						} else {
							$new_window = false;
						}
						$navigation[$i]	= array(	"link_order"	=> (int) $result["page_order"],
													"link_parent"	=> 0,
													"link_url"		=> ":".$result["page_url"],
													"link_title"	=> $result["menu_title"],
													"link_selected" => ($result["page_url"] == $PAGE_URL ? true : false),
													"link_new_window" => ($new_window ? true : false),
													"link_type"		=> $result["page_type"]);
						$visible = true;
					} else {
						$visible = false;
					}
					$available[$result["page_url"]]		= $result["menu_title"];
					$available_ids[$result["page_url"]]	= $result["cpage_id"];
					$details[$result["page_url"]]		= $result;
					$i++;
					$children = communities_fetch_child_pages("", $community_id, $user_access, $result["cpage_id"], $access_query_condition, $i, $navigation, $available, $details, $available_ids, $module_enabled, $visible);
					if ($i < $children["count"]) {
						$i = $children["count"];
						$available = $children["available"];
						$available_ids = $children["available_ids"];
						$details = $children["details"];
						$navigation = $children["navigation"];
					}
				}
			}
		}
	}

	return array("enabled" => $available, "navigation" => $navigation, "details" => $details, "exists" => $exists, "available_ids" => $available_ids);
}

function communities_fetch_child_pages($indent = "", $community_id = 0, $user_access = 0, $parent_id = 0, $access_query_condition = array(), $i, $navigation, $available, $details, $available_ids, $module_enabled = array(), $visible) {
	global $db, $PAGE_URL;
	
	$cquery		= "SELECT `cpage_id`, `page_url`, `menu_title`, `page_type`, `page_order`, `page_content`, `page_visible` FROM `community_pages` WHERE `parent_id` = ".$db->qstr($parent_id)." AND `community_id` =".$db->qstr($community_id)." AND ".$access_query_condition[$user_access]." AND `page_active` = '1' ORDER BY `page_order` ASC";
	$cresults	= $db->GetAll($cquery);
	if($cresults) {
		foreach ($cresults as $cresult) {
			if ($module_enabled[$cresult["page_type"]]) {
				if ((((int)$cresult["page_visible"]) == 1) && ($visible)) {
					if ($cresult["page_type"] == "url") {
						$query = "SELECT `option_value` FROM `community_page_options` WHERE `cpage_id` = ".$db->qstr($cresult["cpage_id"])." AND `option_title` = 'new_window'";
						$new_window = $db->GetOne($query);
					} else {
						$new_window = false;
					}
					$navigation[$i]	= array(
						"link_order"	=> (int) $cresult["page_order"],
						"link_parent"	=> $parent_id,
						"link_url"		=> ":".$cresult["page_url"],
						"link_title"	=> $cresult["menu_title"],
						"link_selected" => ($cresult["page_url"] == $PAGE_URL ? true : false),
						"link_new_window" => ($new_window ? true : false),
						"link_type"		=> $cresult["page_type"]);
				}
				$available[$cresult["page_url"]]		= $cresult["menu_title"];
				$available_ids[$cresult["page_url"]]	= $cresult["cpage_id"];
				$details[$cresult["page_url"]]			= $cresult;
				$i++;


				$children = communities_fetch_child_pages($indent . "&nbsp;&nbsp;", $community_id, $user_access, $cresult["cpage_id"], $access_query_condition, $i, $navigation, $available, $details, $available_ids, $module_enabled, $visible);
				if ($i < $children["count"]) {
					$i = $children["count"];
					$available = $children["available"];
					$available_ids = $children["available_ids"];
					$details = $children["details"];
				}
			}
		}
	}
	return array("count" => $i, "available" => $available, "details" => $details, "navigation" => $navigation, "available_ids" => $available_ids);
}


/**
 * Will count the number of members and optionally specified ACL level.
 * e.g. communities_count_members(3, 1); will return all admins in community 3.
 *
 * @param int $community_id
 * @param int $acl_level
 * @return int
 */
function communities_count_members($community_id = 0, $acl_level = "all") {
	global $db;

	$output = 0;

	if($community_id = (int) $community_id) {
		$query	= "SELECT COUNT(*) AS `total_members` FROM `community_members` WHERE `community_id` = ".$db->qstr($community_id).(($acl_level != "all") ? " AND `member_acl` = ".$db->qstr((int) $acl_level) : "");
		$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
		if($result) {
			$output = (int) $result["total_members"];
		}
	}

	return $output;
}

/**
 * This function is used by the discussions module to pull the latest posting details
 * from the database to display on the index, etc.
 *
 * @param int $cdiscussion_id
 * @return array
 */
function communities_discussions_latest($cdiscussion_id = 0) {
	global $db, $COMMUNITY_ID;

	$output				= array();
	$output["posts"]	= 0;
	$output["replies"]	= 0;

	if($cdiscussion_id = (int) $cdiscussion_id) {
		$query	= "
				SELECT IF(a.`cdtopic_parent` = '0', a.`cdtopic_id`, b.`cdtopic_id`) AS `cdtopic_id`, IF(a.`cdtopic_parent` = '0', a.`topic_title`, b.`topic_title`) AS `topic_title`, a.`updated_date`, a.`proxy_id`, c.`username`, CONCAT_WS(' ', c.`firstname`, c.`lastname`) AS `poster_fullname`
				FROM `community_discussion_topics` AS a
				LEFT JOIN `community_discussion_topics` AS b
				ON a.`cdtopic_parent` = b.`cdtopic_id`
				LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS c
				ON a.`proxy_id` = c.`id`
				WHERE a.`cdiscussion_id` = ".$db->qstr($cdiscussion_id)."
				AND a.`community_id` = ".$db->qstr((int) $COMMUNITY_ID)."
				AND a.`topic_active` = '1'
				AND (b.`topic_active` IS NULL OR b.`topic_active`='1')
				ORDER BY a.`updated_date` DESC
				LIMIT 0, 1";
		$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
		if($result) {
			$output["username"]		= $result["username"];
			$output["fullname"]		= $result["poster_fullname"];
			$output["proxy_id"]		= (int) $result["proxy_id"];
			$output["updated_date"]	= $result["updated_date"];
			$output["cdtopic_id"]	= $result["cdtopic_id"];
			$output["topic_title"]	= $result["topic_title"];

			/**
			 * Fetch the total number of posts.
			 * This could prolly be done with one query, but at what cost? I'm not sure.
			 */
			$query	= "SELECT COUNT(*) AS `total_posts` FROM `community_discussion_topics` WHERE `cdtopic_parent` = '0' AND `cdiscussion_id` = ".$db->qstr($cdiscussion_id)." AND `community_id` = ".$db->qstr((int) $COMMUNITY_ID)." AND `topic_active` ='1'";
			$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
			if(($result) && ((int) $result["total_posts"])) {
				$output["posts"] = (int) $result["total_posts"];

				/**
				 * Fetch the total number of replies to posts.
				 */
				$query	= "SELECT COUNT(*) AS `total_replies` FROM `community_discussion_topics` WHERE `cdtopic_parent` <> '0' AND `cdiscussion_id` = ".$db->qstr($cdiscussion_id)." AND `community_id` = ".$db->qstr((int) $COMMUNITY_ID)." AND `topic_active` ='1'";
				$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
				if(($result) && ((int) $result["total_replies"])) {
					$output["replies"] = (int) $result["total_replies"];
				}
			}
		}
	}

	return $output;
}

/**
 * This function is used by the shares module to pull the latest file details
 * from the database to display on the index, etc.
 *
 * @param int $cshare_id
 * @return array
 */
function communities_shares_latest($cshare_id = 0) {
	global $db, $COMMUNITY_ID;

	$output					= array();
	$output["total_files"]	= 0;
	$output["total_bytes"]	= 0;

	if($cshare_id = (int) $cshare_id) {
		$query	= "
				SELECT COUNT(*) AS `total_files`
				FROM `community_share_files` AS a
				WHERE a.`cshare_id` = ".$db->qstr($cshare_id)."
				AND a.`community_id` = ".$db->qstr((int) $COMMUNITY_ID)."
				AND a.`file_active` = '1'";
		$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
		if($result) {
			$output["total_files"] = (int) $result["total_files"];
		}
	}

	return $output;
}

function communities_galleries_fetch_thumbnail($cgphoto_id, $photo_title = "") {
	global $COMMUNITY_URL, $PAGE_URL, $COMMUNITY_TEMPLATE;

	if ($cgphoto_id = (int) $cgphoto_id) {
		$photo_url = COMMUNITY_URL.$COMMUNITY_URL.":".$PAGE_URL."?action=view-photo&amp;id=".$cgphoto_id."&amp;render=thumbnail";
	} else {
		$photo_url = COMMUNITY_RELATIVE."/templates/".$COMMUNITY_TEMPLATE."/images/galleries-no-photo.gif";
	}
	return "<img src=\"".$photo_url."\" width=\"150\" height=\"150\" style=\"border: 1px #CCCCCC solid\" alt=\"".html_encode($photo_title)."\" title=\"".html_encode($photo_title)."\" onmouseover=\"this.style.borderColor='#666666'\" onmouseout=\"this.style.borderColor='#CCCCCC'\" />\n";
}

function community_galleries_in_select($gallery_id = 0) {
	global $COMMUNITY_ID, $db;

	$output = "";

	$query	= "	SELECT a.`cgallery_id`, a.`gallery_title`, a.`cpage_id`, b.`menu_title`
				FROM `community_galleries` AS a
				LEFT JOIN `community_pages` AS b
				ON b.`cpage_id` = a.`cpage_id`
				WHERE a.`cgallery_id` != ".$db->qstr($gallery_id)."
				AND a.`community_id` = ".$db->qstr($COMMUNITY_ID)."
				AND a.`gallery_active` = '1'
				AND b.`page_active` = '1'
				ORDER BY b.`page_order` ASC, a.`gallery_order` ASC";
	$results = $db->GetAll($query);
	if ($results) {
		$cpage_id = 0;
		$output = "<select id=\"gallery_id\" name=\"gallery_id\" style=\"width: 300px\">";
		
		foreach ($results as $key => $result) {
			if ($cpage_id != $result["cpage_id"]) {
				$cpage_id = $result["cpage_id"];

				if ($key) {
					$output .= "</optgroup>";
				}

				$output .= "<optgroup label=\"".html_encode($result["menu_title"])."\">";
			}
			$output .= "<option value=\"".(int) $result["cgallery_id"]."\">".html_encode($result["gallery_title"])."</option>";

		}

		$output .= "</optgroup>";
		$output .= "</select>";

	}

	return $output;
}

function community_shares_in_select($share_id) {
	global $COMMUNITY_ID, $db;

	$output	= "";

	$query	= "	SELECT a.`cshare_id`, a.`folder_title`, a.`cpage_id`, b.`menu_title`
				FROM `community_shares` AS a
				LEFT JOIN `community_pages` AS b
				ON b.`cpage_id` = a.`cpage_id`
				WHERE a.`cshare_id` != ".$db->qstr($share_id)."
				AND a.`community_id` = ".$db->qstr($COMMUNITY_ID)."
				AND a.`folder_active` = '1'
				AND b.`page_active` = '1'
				ORDER BY b.`page_order` ASC, a.`folder_order` ASC";
	$shares	= $db->GetAll($query);
	if ($shares) {
		$cpage_id = 0;
		$output = "<select id=\"share_id\" name=\"share_id\" style=\"width: 300px\">";

		foreach ($shares as $key => $result) {
			if ($cpage_id != $result["cpage_id"]) {
				$cpage_id = $result["cpage_id"];

				if ($key) {
					$output .= "</optgroup>";
				}

				$output .= "<optgroup label=\"".html_encode($result["menu_title"])."\">";
			}

			$output .= "<option value=\"".(int) $result["cshare_id"]."\">".html_encode($result["folder_title"])."</option>";

		}

		$output .= "</optgroup>";
		$output .= "</select>";

	}

	return $output;
}

/**
 * Processes / resizes and creates properly sized image and thumbnail image
 * for images uploaded to the galleries module.
 *
 * @param string $original_file
 * @param int $photo_id
 * @return bool
 */
function communities_galleries_process_photo($original_file, $photo_id = 0) {
	global $VALID_MAX_DIMENSIONS, $COMMUNITY_ID;

	if(!@function_exists("gd_info")) {
		return false;
	}

	if((!@file_exists($original_file)) || (!@is_readable($original_file))) {
		return false;
	}

	if(!$photo_id = (int) $photo_id) {
		return false;
	}

	$new_file		= COMMUNITY_STORAGE_GALLERIES."/".$photo_id;
	$img_quality	= 85;

	if($original_file_details = @getimagesize($original_file)) {
		$original_file_width	= $original_file_details[0];
		$original_file_height	= $original_file_details[1];

		/**
		 * Check if the original_file needs to be resized or not.
		 */
		if(($original_file_width > $VALID_MAX_DIMENSIONS["photo"]) || ($original_file_height > $VALID_MAX_DIMENSIONS["photo"])) {
			switch($original_file_details["mime"]) {
				case "image/pjpeg":
				case "image/jpeg":
				case "image/jpg":
					$original_img_resource = @imagecreatefromjpeg($original_file);
					break;
				case "image/png":
					$original_img_resource = @imagecreatefrompng($original_file);
					break;
				case "image/gif":
					$original_img_resource = @imagecreatefromgif($original_file);
					break;
				default :
					return false;
					break;
			}

			if($original_img_resource) {
			/**
			 * Determine whether it's a horizontal / vertical image and calculate the new smaller size.
			 */
				if($original_file_width > $original_file_height) {
					$new_file_width		= $VALID_MAX_DIMENSIONS["photo"];
					$new_file_height	= (int) (($VALID_MAX_DIMENSIONS["photo"] * $original_file_height) / $original_file_width);
				} else {
					$new_file_width		= (int) (($VALID_MAX_DIMENSIONS["photo"] * $original_file_width) / $original_file_height);
					$new_file_height	= $VALID_MAX_DIMENSIONS["photo"];
				}

				if($original_file_details["mime"] == "image/gif") {
					$new_img_resource = @imagecreate($new_file_width, $new_file_height);
				} else {
					$new_img_resource = @imagecreatetruecolor($new_file_width, $new_file_height);
				}

				if($new_img_resource) {
					if(@imagecopyresampled($new_img_resource, $original_img_resource, 0, 0, 0, 0, $new_file_width, $new_file_height, $original_file_width, $original_file_height)) {
						switch($original_file_details["mime"]) {
							case "image/pjpeg":
							case "image/jpeg":
							case "image/jpg":
								if(!@imagejpeg($new_img_resource, $new_file, $img_quality)) {
									return false;
								}
								break;
							case "image/png":
								if(!@imagepng($new_img_resource, $new_file)) {
									return false;
								}
								break;
							case "image/gif":
								if(!@imagegif($new_img_resource, $new_file)) {
									return false;
								}
								break;
							default :
								return false;
								break;
						}

						@chmod($new_file, 0644);

						/**
						 * Frees the memory this used, so it can be used again for the thumbnail.
						 */
						@imagedestroy($original_img_resource);
						@imagedestroy($new_img_resource);
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			if(@move_uploaded_file($original_file, $new_file)) {
				@chmod($new_file, 0644);

				/**
				 * Create the new width / height so we can use the same variables
				 * below for thumbnail generation.
				 */
				$new_file_width		= $original_file_width;
				$new_file_height	= $original_file_height;
			} else {
				return false;
			}
		}

		/**
		 * Check that the new_file exists, and can be used, then proceed
		 * with Thumbnail generation ($new_file-thumbnail).
		 */
		if((@file_exists($new_file)) && (@is_readable($new_file))) {
			$cropped_size = $VALID_MAX_DIMENSIONS["thumb"];

			switch($original_file_details["mime"]) {
				case "image/pjpeg":
				case "image/jpeg":
				case "image/jpg":
					$original_img_resource = @imagecreatefromjpeg($new_file);
					break;
				case "image/png":
					$original_img_resource = @imagecreatefrompng($new_file);
					break;
				case "image/gif":
					$original_img_resource = @imagecreatefromgif($new_file);
					break;
				default :
					return false;
					break;
			}

			if(($new_file_width > $VALID_MAX_DIMENSIONS["thumb"]) || ($new_file_height > $VALID_MAX_DIMENSIONS["thumb"])) {
				$dest_x			= 0;
				$dest_y			= 0;
				$ratio_orig		= ($new_file_width / $new_file_height);
				$cropped_width	= $VALID_MAX_DIMENSIONS["thumb"];
				$cropped_height	= $VALID_MAX_DIMENSIONS["thumb"];

				if($ratio_orig > 1) {
					$cropped_width	= ($cropped_height * $ratio_orig);
				} else {
					$cropped_height	= ($cropped_width / $ratio_orig);
				}
			} else {
				$cropped_width	= $new_file_width;
				$cropped_height	= $new_file_height;

				$dest_x			= ($VALID_MAX_DIMENSIONS["thumb"] / 2) - ($cropped_width / 2);
				$dest_y			= ($VALID_MAX_DIMENSIONS["thumb"] / 2) - ($cropped_height / 2 );
			}

			if($original_file_details["mime"] == "image/gif") {
				$new_img_resource = @imagecreate($VALID_MAX_DIMENSIONS["thumb"], $VALID_MAX_DIMENSIONS["thumb"]);
			} else {
				$new_img_resource = @imagecreatetruecolor($VALID_MAX_DIMENSIONS["thumb"], $VALID_MAX_DIMENSIONS["thumb"]);
			}

			if($new_img_resource) {
				if(@imagecopyresampled($new_img_resource, $original_img_resource, $dest_x, $dest_y, 0, 0, $cropped_width, $cropped_height, $new_file_width, $new_file_height)) {
					switch($original_file_details["mime"]) {
						case "image/pjpeg":
						case "image/jpeg":
						case "image/jpg":
							if(!@imagejpeg($new_img_resource, $new_file."-thumbnail", $img_quality)) {
								return false;
							}
							break;
						case "image/png":
							if(!@imagepng($new_img_resource, $new_file."-thumbnail")) {
								return false;
							}
							break;
						case "image/gif":
							if(!@imagegif($new_img_resource, $new_file."-thumbnail")) {
								return false;
							}
							break;
						default :
							return false;
							break;
					}

					@chmod($new_file."-thumbnail", 0644);

					/**
					 * Frees the memory this used, so it can be used again.
					 */
					@imagedestroy($original_img_resource);
					@imagedestroy($new_img_resource);

					return true;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	} else {
		return false;
	}
}

/**
 * Function takes the original file that someone uploads in the shares
 * module and moves it to the correct storage location.
 *
 * Note: It _will not_ overwrite existing files, because it shouldn't
 * every file should be a unique ID a la version control.
 *
 * @param string $original_file
 * @param int $csfversion_id
 * @return bool
 */
function communities_shares_process_file($original_file, $csfversion_id = 0) {
	global $COMMUNITY_ID;

	if((!@file_exists($original_file)) || (!@is_readable($original_file))) {
		return false;
	}

	if(!$csfversion_id = (int) $csfversion_id) {
		return false;
	}

	if(!@file_exists($new_file = COMMUNITY_STORAGE_DOCUMENTS."/".$csfversion_id)) {
		if(@move_uploaded_file($original_file, $new_file)) {
			@chmod($new_file, 0644);
			return true;
		}
	}

	return false;
}

/**
 * This function handles sorting and ordering for the community modules.
 *
 * @param string $field_id
 * @param string $field_name
 * @return string
 */
function communities_order_link($field_id, $field_name) {
	global $COMMUNITY_ID, $COMMUNITY_URL, $PAGE_URL;

	if(strtolower($_SESSION[APPLICATION_IDENTIFIER]["cid_".$COMMUNITY_ID][$PAGE_URL]["sb"]) == strtolower($field_id)) {
		if(strtolower($_SESSION[APPLICATION_IDENTIFIER]["cid_".$COMMUNITY_ID][$PAGE_URL]["so"]) == "desc") {
			return "<a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":".$PAGE_URL."?".replace_query(array("so" => "asc"), false)."\" title=\"Order by ".$field_name.", Sort Ascending\">".$field_name."</a>";
		} else {
			return "<a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":".$PAGE_URL."?".replace_query(array("so" => "desc"), false)."\" title=\"Order by ".$field_name.", Sort Decending\">".$field_name."</a>";
		}
	} else {
		return "<a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":".$PAGE_URL."?".replace_query(array("sb" => $field_id), false)."\" title=\"Order by ".$field_name."\">".$field_name."</a>";
	}
}
/**
 * This function recieves a page URL and ID from a community page that has been
 * moved from its' current location and sets the children of that page to have
 * the correct URL based on their new location.
 *
 * @param int $parent_id
 * @param string $parent_url
 */
function communities_set_children_urls($parent_id, $parent_url) {
	global $ERROR, $ERRORSTR, $COMMUNITY_RESERVED_PAGES, $db;

	$child_data = array();
	$query = "SELECT * FROM `community_pages` WHERE `parent_id` = ".$db->qstr($parent_id)." AND `page_active` = '1'";
	$child_records = $db->GetAll($query);
	if ($child_records) {
		foreach ($child_records as $child_record) {
			$page_url = clean_input($child_record["menu_title"], array("lower","underscores","page_url"));
			$page_url = $parent_url . DIRECTORY_SEPARATOR . $page_url;
			if(in_array($page_url, $COMMUNITY_RESERVED_PAGES)) {
				$ERROR++;
				$ERRORSTR[] = "The <strong>Menu Title</strong> you have chosen is reserved, please try again.";
			} else {
				$child_data["page_url"] = $page_url;
				$query	= "SELECT * FROM `community_pages` WHERE `page_url` = ".$db->qstr($child_data["page_url"], get_magic_quotes_gpc())." AND `page_active` = '1' AND `community_id` = ".$db->qstr($child_record["community_id"])." AND `cpage_id` != ".$db->qstr($child_record["cpage_id"]);
				$result	= $db->GetRow($query);
				if($result) {
					$ERROR++;
					$ERRORSTR[] = "The new <strong>Page URL</strong> already exists in this community; Page URLs must be unique.";
				} else {
					if ($db->AutoExecute("community_pages", $child_data, "UPDATE", "cpage_id = ".$child_record["cpage_id"])) {
						if ($db->GetRow("SELECT * FROM `community_pages` WHERE `parent_id` = ".$db->qstr($child_record["cpage_id"]))) {
							communities_set_children_urls($child_record["cpage_id"], $child_data["page_url"]);
						}
					} else {
						$ERROR++;
						$ERRORSTR[] = "There was an error changing a child page URL. Database said: ".$db->ErrorMsg();
					}
				}
			}
		}
	}
}

/**
 * Functions used by the clerkship module are below.
 *
 */

/**
 * This function generates a formatted category title based on the hierarchical
 * child / parent / grandparent relationship of the categories table.
 *
 * @param unknown_type $category_id
 * @param unknown_type $levels
 * @return unknown
 */
function clerkship_categories_title($category_id = 0, $levels = 3) {
	global $db;

	$output	= array();
	$level	= 1;

	if((!$levels = (int) $levels) || ($levels > 15)) {
		$levels = 3;
	}

	if($category_id = (int) $category_id) {
		for($level = 1; $level <= $levels; $level++) {
			if($level == 1) {
				$query = "SELECT `category_name`, `category_parent` FROM `".CLERKSHIP_DATABASE."`.`categories` WHERE `category_id` = ".$db->qstr($category_id);
			} else {
				$query = "SELECT `category_name`, `category_parent` FROM `".CLERKSHIP_DATABASE."`.`categories` WHERE `category_id` = ".$db->qstr((int) $result["category_parent"]);
			}

			$result	= $db->GetRow($query);
			if(($result) && (trim($result["category_name"]))) {
				$output[] = $result["category_name"];
			}
		}

		if((is_array($output)) && (count($output))) {
			return html_entity_decode(implode(" &gt; ", array_reverse($output)));
		}
	}

	return false;
}

/**
 * This function will return the name of a region based on it's ID.
 *
 * @param int $region_id
 * @return string
 */
function clerkship_region_name($region_id = 0) {
	global $db;

	if($region_id = (int) $region_id) {
		$query	= "SELECT `region_name` FROM `".CLERKSHIP_DATABASE."`.`regions` WHERE `region_id` = ".$db->qstr($region_id);
		$result	= $db->GetRow($query);
		if($result) {
			return $result["region_name"];
		}
	}

	return false;
}

/**
 * This function returns all rotation ids which the current user has access to.
 *
 * @return array of integers
 */
function clerkship_rotations_access() {
	global $db, $ENTRADA_ACL;
	$query = "	SELECT a.`course_id`, a.`rotation_id`, b.`organisation_id` 
				FROM `".CLERKSHIP_DATABASE."`.`global_lu_rotations` AS a
				LEFT JOIN `courses` AS b
				ON a.`course_id` = b.`course_id`
				WHERE b.`course_active` = '1'";
	$courses = $db->GetAll($query);
	$rotation_ids = array();
	if (is_array($courses) && count($courses)) {
		foreach ($courses as $course) {
			if ($ENTRADA_ACL->amIAllowed(new CourseContentResource($course["course_id"], $course["organisation_id"]), 'update')) {
				$rotation_ids[] = $course["rotation_id"];
			}
		}
	}
	return $rotation_ids;
}
/**
 * Function will return all pages below the specified parent_id, the current user has access to.
 *
 * @param int $identifier
 * @param int $indent
 * @return string
 */
function communities_pages_inlists($identifier = 0, $indent = 0, $options = array()) {
	global $db, $MODULE, $COMMUNITY_ID, $COMMUNITY_URL;

	if($indent > 99) {
		die("Preventing infinite loop");
	}

	$selected				= 0;
	$selectable_children	= true;

	if(is_array($options)) {
		if((isset($options["selected"])) && ($tmp_input = clean_input($options["selected"], array("nows", "int")))) {
			$selected = $tmp_input;
		}

		if(isset($options["selectable_children"])) {
			$selectable_children = (bool) $options["selectable_children"];
		}

		if(isset($options["id"])) {
			$ul_id = $options["id"];
			$options["id"] = null;
		}
	}

	$identifier	= (int) $identifier;
	$output		= "";

	if(($identifier) && ($indent === 0)) {
		$query	= "SELECT `cpage_id`, `page_url`, `menu_title`, `parent_id`, `page_visible`, `page_type` FROM `community_pages` WHERE `community_id` = ".$COMMUNITY_ID." AND `cpage_id` = ".$db->qstr((int) $identifier)." AND `page_url` != '0' AND `page_active` = '1' ORDER BY `page_order` ASC";
	} else {
		$query	= "SELECT `cpage_id`, `page_url`, `menu_title`, `parent_id`, `page_visible`, `page_type` FROM `community_pages` WHERE `community_id` = ".$COMMUNITY_ID." AND `parent_id` = ".$db->qstr((int) $identifier)." AND `page_url` != '' AND `page_active` = '1' ORDER BY `page_order` ASC";
	}

	$results	= $db->GetAll($query);
	if($results) {
		$output .= "<ul class=\"community-page-list\" ".(isset($ul_id) ? "id = \"".$ul_id."\"" : "").">";
		foreach ($results as $result) {
			$output .= "<li id=\"content_".$result["cpage_id"]."\">\n";
			$output .= "<div class=\"community-page-container\">";
			if(($indent > 0) && (!$selectable_children)) {
				$output .= "	<span class=\"delete\">&nbsp;</span>\n";
				$output .= "	<span class=\"".(((int) $result["page_visible"]) == 0 ? "hidden-page " : "")."next off\">".
								html_encode($result["menu_title"])."</span>\n";
			} else {
				$output .= "	<span class=\"delete\">".($result["page_type"] != "course" ? "<input type=\"radio\" id=\"delete_".$result["cpage_id"]."\" name=\"delete\" value=\"".$result["cpage_id"]."\"".(($selected == $result["cpage_id"]) ? " checked=\"checked\"" : "")." />" : "<div class=\"course-spacer\">&nbsp;</div>")."</span>\n";
				$output .= "	<span class=\"".(((int) $result["page_visible"]) == 0 ? "hidden-page " : "")."next\">
								<a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":pages?".replace_query(array("action" => "edit", "step" => 1, "page" => $result["cpage_id"]))."\">".
								html_encode($result["menu_title"])."</a></span>\n";
			}
			$output .= "</div>";
			$output .= communities_pages_inlists($result["cpage_id"], $indent + 1, $options);
			$output .= "</li>\n";

		}
		$output .= "</ul>";
	} else {
		$output .= "<ul class=\"community-page-list empty\"></ul>";
	}

	return $output;
}

/**
 * Function will return all pages below the specified parent_id, the current user has access to.
 *
 * @param int $identifier
 * @param int $indent
 * @return string
 */
function communities_pages_intable($identifier = 0, $indent = 0, $options = array()) {
	global $db, $MODULE, $COMMUNITY_ID, $COMMUNITY_URL;

	if($indent > 99) {
		die("Preventing infinite loop");
	}
	
	$selected				= 0;
	$selectable_children	= true;
	
	if(is_array($options)) {
		if((isset($options["selected"])) && ($tmp_input = clean_input($options["selected"], array("nows", "int")))) {
			$selected = $tmp_input;
		}
		
		if(isset($options["selectable_children"])) {
			$selectable_children = (bool) $options["selectable_children"];
		}
	}
	
	
	$identifier	= (int) $identifier;
	$output		= "";
	
	if(($identifier) && ($indent === 0)) {
		$query	= "SELECT `cpage_id`, `page_url`, `menu_title`, `parent_id`, `page_visible`, `page_type` FROM `community_pages` WHERE `community_id` = ".$COMMUNITY_ID." AND `cpage_id` = ".$db->qstr((int) $identifier)." AND `page_url` != '0' AND `page_active` = '1' ORDER BY `page_order` ASC";
	} else {
		$query	= "SELECT `cpage_id`, `page_url`, `menu_title`, `parent_id`, `page_visible`, `page_type` FROM `community_pages` WHERE `community_id` = ".$COMMUNITY_ID." AND `parent_id` = ".$db->qstr((int) $identifier)." AND `page_url` != '' AND `page_active` = '1' ORDER BY `page_order` ASC";
	}

	$results	= $db->GetAll($query);
	if($results) {
		foreach ($results as $result) {
			if(($indent > 0) && (!$selectable_children)) {
				$output .= "<tr id=\"content_".$result["cpage_id"]."\">\n";
				$output .= "	<td>&nbsp;</td>\n";
				$output .= "	<td ".(((int) $result["page_visible"]) == 0 ? " class=\"hidden-page\"" : "")."style=\"padding-left: ".($indent * 25)."px; vertical-align: middle\"><img src=\"".ENTRADA_URL."/images/record-next-off.gif\" width=\"11\" height=\"11\" border=\"0\" alt=\"\" title=\"\" style=\"vertical-align: middle; margin-right: 5px\" />".html_encode($result["menu_title"])."</td>\n";
				$output .= "</tr>\n";
			} else {
				$output .= "<tr id=\"content_".$result["cpage_id"]."\">\n";
				$output .= "	<td>".($result["page_type"] != "course" ? "<input type=\"radio\" id=\"delete_".$result["cpage_id"]."\" name=\"delete\" value=\"".$result["cpage_id"]."\" style=\"vertical-align: middle\"".(($selected == $result["cpage_id"]) ? " checked=\"checked\"" : "")." />" : "&nbsp;")."</td>\n";
				$output .= "	<td ".(((int) $result["page_visible"]) == 0 ? " class=\"hidden-page\"" : "")."style=\"padding-left: ".($indent * 25)."px; vertical-align: middle\"><img src=\"".ENTRADA_URL."/images/record-next-on.gif\" width=\"11\" height=\"11\" border=\"0\" alt=\"\" title=\"\" style=\"vertical-align: middle; margin-right: 5px\" /><a href=\"".COMMUNITY_URL.$COMMUNITY_URL.":pages?".replace_query(array("action" => "edit", "step" => 1, "page" => $result["cpage_id"]))."\"".(($result["parent_id"] == 0) ? " style=\"font-weight: bold\"" : "").">".html_encode($result["menu_title"])."</a></td>\n";
				$output .= "</tr>\n";
			}
			
			$output .= communities_pages_intable($result["cpage_id"], $indent + 1, $options);
		}
	}
	
	return $output;
}

/**
 * Function will return all pages below the specified parent_id, as option elements of an input select.
 * This is a recursive function that has a fall-out of 99 runs.
 *
 * @param int $parent_id
 * @param array $current_selected
 * @param int $indent
 * @param array $exclude
 * @return string
 */
function communities_pages_inselect($parent_id = 0, &$current_selected, $indent = 0, &$exclude = array()) {
	global $db, $MODULE, $COMMUNITY_ID;

	if($indent > 99) {
		die("Preventing infinite loop");
	}

	if(!is_array($current_selected)) {
		$current_selected = array($current_selected);
	}

	$output	= "";
	$query	= "SELECT `cpage_id`, `menu_title`, `parent_id` FROM `community_pages` WHERE `community_id` = ".$db->qstr($COMMUNITY_ID)." AND `page_active` = '1' AND `parent_id` = ".$db->qstr($parent_id)." AND `page_url` != '' ORDER BY `page_order` ASC";
	$results	= $db->GetAll($query);
	if($results) {
		foreach ($results as $result) {
			if((!@in_array($result["cpage_id"], $exclude)) && (!@in_array($parent_id, $exclude))) {
				$output .= "<option value=\"".(int) $result["cpage_id"]."\"".((@in_array($result["cpage_id"], $current_selected)) ? " selected=\"selected\"" : "").">".str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", $indent).(($indent > 0) ? "&rarr;&nbsp;" : "").html_encode($result["menu_title"])."</option>\n";
			} else {
				$exclude[] = (int) $result["cpage_id"];
			}
			$output .= communities_pages_inselect($result["cpage_id"], $current_selected, $indent + 1, $exclude, $community_id);
		}
	}

	return $output;
}

/**
 * This function is used to return the children of the current page in an unordered
 * list used to navigate to children from parents.
 *
 * @param integer $page_id
 * @return string
 */
function communities_page_children_in_list($page_id = 0) {
	global $db, $COMMUNITY_ID, $COMMUNITY_URL, $USER_ACCESS;

	$output = "";

	if($page_id = (int) $page_id) {
		$access_query_condition = array();
		$access_query_condition[0] = "AND a.`allow_public_view` = '1'";
		$access_query_condition[1] = "AND a.`allow_troll_view` = '1'";
		$access_query_condition[2] = "AND a.`allow_member_view` = '1'";
	
		if ($USER_ACCESS == 1 && ((int) $db->GetOne("SELECT `community_registration` from `communities` WHERE `community_id` =".$db->qstr($community_id)." AND `community_protected` = '0'"))) {
			$USER_ACCESS = 0;
		}

		$query	= "	SELECT a.*
					FROM `community_pages` AS a
					WHERE a.`parent_id` = ".$db->qstr($page_id)."
					AND `page_active` = '1'
					AND `page_visible` = '1'
					AND a.`community_id` = ".$db->qstr($COMMUNITY_ID). "
					".((isset($access_query_condition[$USER_ACCESS])) ? $access_query_condition[$USER_ACCESS] : "")."
					ORDER BY a.`page_order` ASC";
		$results	= $db->GetAll($query);
		if($results) {
			$output = "\n<ul class=\"child-nav\">";
			foreach ($results as $result) {
				if ($result["page_type"] == "url") {
					$query = "SELECT `option_value` FROM `community_page_options` WHERE `cpage_id` = ".$db->qstr($result["cpage_id"])." AND `option_title` = 'new_window'";
					$new_window = $db->GetOne($query);
				} else {
					$new_window = false;
				}
				$output .= "\n<li><a href=\"".ENTRADA_URL."/community".$COMMUNITY_URL.":".$result["page_url"]."\"".($new_window ? " target=\"_blank\"" : "")."> ".(strlen($result["menu_title"]) > 18 ? substr($result["menu_title"],0,15)."..." : $result["menu_title"] ) ." </a></li>";
			}
			$output .= "\n</ul>\n";
		} else {
			/*
			@todo I think that this just adds some unnecessary confusion to the page navigation.
			determine whether this is in fact the case. Basically what this code does is if
			the page has no children, it displays this $page_id's brothers and sisters.
			if($parent_id = communities_pages_fetch_parent_id($page_id)) {
				$query		= "	SELECT a.*
								FROM `community_pages` AS a
								WHERE a.`parent_id` = ".$db->qstr($parent_id)."
								AND `page_active` = '1'
								AND `page_visible` = '1'
								AND a.`community_id` = ".$db->qstr($COMMUNITY_ID). "
								".((isset($access_query_condition[$USER_ACCESS])) ? $access_query_condition[$USER_ACCESS] : "")."
								ORDER BY a.`page_order` ASC";
				$results	= $db->GetAll($query);
				if(($results) && (count($results) > 1)) {
					$output .= "\n<ul class=\"child-nav\">";
					foreach ($results as $result) {
						$output .= "\n<li".(($page_id == $result["cpage_id"]) ? " class=\"live\"" : "")."><a href=\"".ENTRADA_URL."/community".$COMMUNITY_URL.":".$result["page_url"]."\"> ".(strlen($result["menu_title"]) > 18 ? substr($result["menu_title"],0,15)."..." : $result["menu_title"] ) ." </a></li>";
					}
					$output .= "\n</ul>\n";
				}
			}
			*/
		}
	}

	return ((trim($output) != "") ? "\n<div class=\"child-menu\">\n".$output."\n</div>\n" : "");
}

/**
 * This recursive function will return all cpage_id's above the specified cpage_id as an array.
 *
 * @param int $parent_id
 * @return array
 */
function communities_pages_fetch_parents($parent_id = 0) {
	global $db, $COMMUNITY_ID;

	static $level	= 0;
	static $pages	= array();

	if($level > 99) {
		application_log("error", "Stopped an infinite loop in the communities_pages_fetch_parents() function.");

		return $pages;
	}

	if($parent_id = (int) $parent_id) {
		$query		= "SELECT `cpage_id`, `parent_id`, `menu_title`, `page_url` FROM `community_pages` WHERE `cpage_id` = ".$db->qstr($parent_id)." AND `page_active` = '1' AND `community_id` = ".$db->qstr($COMMUNITY_ID);
		$results	= $db->GetAll($query);
		if($results) {
			foreach ($results as $result) {
				$pages[$result["cpage_id"]]["url"] = $result["page_url"];
				$pages[$result["cpage_id"]]["title"] = $result["menu_title"];

				$level++;

				communities_pages_fetch_parents($result["parent_id"]);
			}
		}
	}

	return $pages;
}

/**
 * This function returns the parent_id of the provided page_id.
 *
 * @param int $cpage_id
 * @return int
 */
function communities_pages_fetch_parent_id($cpage_id = 0) {
	global $db, $COMMUNITY_ID;

	if($cpage_id = (int) $cpage_id) {
		$query	= "SELECT `parent_id` FROM `community_pages` WHERE `cpage_id` = ".$db->qstr($cpage_id)." AND `community_id` = ".$db->qstr($COMMUNITY_ID);
		$result	= $db->GetRow($query);
		if($result) {
			return $result["parent_id"];
		}
	}

	return 0;
}

/**
 * Function will return the number of sub-pages under the page_id you specify.
 *
 * @param int $parent_id
 * @param int $page_count
 * @param int $level
 * @return int
 */
function communities_pages_count($parent_id = 0, &$page_count, $level = 0) {
	global $db, $COMMUNITY_ID;

	if($level > 99) {
		die("Preventing infinite loop");
	}

	$query	= "SELECT `cpage_id` FROM `community_pages` WHERE `parent_id` = ".$db->qstr($parent_id)." AND `community_id` = ".$db->qstr($COMMUNITY_ID)." AND `page_active` = '1' AND `page_url` != ''";
	$results	= $db->GetAll($query);
	if($results) {
		foreach ($results as $result) {
			$page_count = $page_count + 1;
			communities_pages_count($result["cpage_id"], $page_count, $level + 1);
		}
	}
	return $page_count;
}

/**
 * Function will move the groups with the $from_id, to the $to_id.
 *
 * @param int $from_id
 * @param int $to_id
 * @return bool
 */
function communities_pages_move($from_id = 0, $to_id = 0) {
	global $db, $COMMUNITY_ID;

	$result = false;

	if(($from_id = (int) $from_id) && ($to_id == 0 || $to_id = (int) $to_id)) {

		$query = "SELECT `cpage_id` FROM `community_pages` WHERE `parent_id` = ".$db->qstr($from_id)." AND `community_id` = ".$db->qstr($COMMUNITY_ID)." AND `page_active` = '1' ORDER BY `page_order`";

		if($page_ids = $db->GetAll($query)) {

			if (!$new_order = $db->GetOne("SELECT MAX(`page_order`) as `new_order` FROM `community_pages` WHERE `parent_id` = ".$db->qstr($to_id)." AND `page_active` = '1' AND `community_id` = ".$db->qstr($COMMUNITY_ID)." AND `page_url` != ''")) {
				$new_order = 1;
			}

			foreach ($page_ids as $page_id) {
				$query = "UPDATE `community_pages` SET `parent_id` = ".$db->qstr($to_id).", `page_order` = ".$db->qstr($new_order)." WHERE `cpage_id` = ".$db->qstr($page_id["cpage_id"])." AND `page_active` = '1'";
				if($db->Execute($query)) {
					$result = true;
				}else {
					$result = false;
					break;
				}
				$new_order++;
			}
		}
	}
	return $result;
}

/**
 * Function will delete all pages below the specified parent_id.
 *
 * @param int $parent_id
 * @return true
 */
function communities_pages_delete($cpage_id = 0, $exclude_ids = array()) {
	global $db, $COMMUNITY_ID;

	static $level = 0;

	if($level > 99) {
		application_log("error", "Stopped an infinite loop in the communities_pages_delete() function.");

		return false;
	}

	if($cpage_id = (int) $cpage_id) {
		if((!is_array($exclude_ids)) || (!in_array($cpage_id, $exclude_ids))) {
			$query		= "SELECT `cpage_id` FROM `community_pages` WHERE `community_id` = ".$db->qstr($COMMUNITY_ID)." AND `page_active` = '1' AND `parent_id` = ".$db->qstr($cpage_id);
			$results	= $db->GetAll($query);
			if($results) {
				foreach ($results as $result) {
					if((!is_array($exclude_ids)) || (!in_array($result["cpage_id"], $exclude_ids))) {
						$level++;

						communities_pages_delete($result["cpage_id"], $exclude_ids);
					}
				}
			}

			$query = "UPDATE `community_pages` SET `page_active` = '0', `page_url` = CONCAT(`page_url`, '.trash') WHERE `cpage_id` = ".$db->qstr($cpage_id)." AND `community_id` = ".$db->qstr($COMMUNITY_ID)." AND `page_url` != ''";
			if(!$db->Execute($query)) {
				application_log("error", "Unable to deactivate cpage_id [".$cpage_id."] from community_id [".$COMMUNITY_ID."]. Database said: ".$db->ErrorMsg());
			} else {
				communities_deactivate_history($COMMUNITY_ID, $cpage_id, 0);
			}
		}
	}

	return true;
}

/**
 * This function adds the parent pages of the current page to the breadcrumb list,
 * as long as the current page has a parent id other than 0.
 *
 */
function communities_build_parent_breadcrumbs() {
	global $db, $COMMUNITY_ID, $PAGE_URL, $COMMUNITY_URL, $BREADCRUMB;

	$query	= "SELECT `cpage_id`, `parent_id` FROM `community_pages` WHERE `community_id` = ".$db->qstr($COMMUNITY_ID)." AND `page_url` = ".$db->qstr($PAGE_URL)." AND `page_active` = '1' AND `page_url` != ''";
	$result	= $db->GetRow($query);

	if($result) {
		$pages = communities_pages_fetch_parents($result["parent_id"]);
		if((is_array($pages)) && (count($pages))) {
			$pages = array_reverse($pages, true);
			foreach ($pages as $page) {
				$BREADCRUMB[] = array("url" => COMMUNITY_URL.$COMMUNITY_URL.":".$page["url"], "title" => $page["title"]);
			}
		}
	}
}

/**
 * This function is used by the polls module to pull the latest polling details
 * from the database to display on the index, etc.
 *
 * @param int $cpolls_id
 * @return array
 */
function communities_polls_latest($cpolls_id = 0) {
	global $db, $COMMUNITY_ID;

	$output					= array();
	$output["voters"]		= 0;
	$output["votes_cast"]	= 0;

	if($cpolls_id = (int) $cpolls_id) {
	// Get Count of admins since they can vote
		$query 	= "SELECT DISTINCT COUNT(`proxy_id`) AS counted_admins FROM `community_members` WHERE `community_id` = ".$db->qstr((int) $COMMUNITY_ID)." AND `member_acl` = '1'";

		if($newResult = $db->CacheGetRow(CACHE_TIMEOUT, $query)) {
			$output["voters"] = (int)$newResult["counted_admins"];
		}

		if($permissions = communities_polls_permissions($cpolls_id)) {
			if((int)$permissions['allow_member_vote'] == 1) {
			// Check to see if this poll has specific members voting only
			// If so count them and the admins only, otherwise count them all
				$query	= "SELECT DISTINCT COUNT(`proxy_id`) AS counted_members FROM `community_polls_access` WHERE `cpolls_id` = ".$db->qstr((int) $cpolls_id);

				$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
				if(is_array($result) && (int)$result["counted_members"] != 0) {
					$output["voters"] += (int)$result["counted_members"];
				} else {
				// No specific members so count them all
					$query	= "SELECT DISTINCT COUNT(`proxy_id`) AS counted_members FROM `community_members` WHERE `community_id` = ".$db->qstr((int) $COMMUNITY_ID). " AND `member_acl` = '0'";
					$result	= $db->CacheGetRow(CACHE_TIMEOUT, $query);
					if($result) {
						$output["voters"] += (int)$result["counted_members"];
					}
				}
			}
		}
		/**
		 * Fetch the total number of votes cast so far.
		 * This is done by concatenating the proxy id of
		 * the voter and the time the vote was submitted and
		 * counting the number of distinct entries because
		 * all responses in one "vote" will have the same
		 * updated_date value.
		 */
		$query	= "SELECT COUNT(*) AS `total_votes`
		FROM `community_polls_results`, `community_polls_responses`
		WHERE `community_polls_responses`.`cpolls_id` = ".$db->qstr($cpolls_id)." 
		AND `community_polls_responses`.`cpresponses_id` = `community_polls_results`.`cpresponses_id`";
		$query	= "SELECT DISTINCT (CONCAT_WS(' ', a.`proxy_id`, a.`updated_date`)) AS `record`
		FROM `community_polls_results` AS a
		LEFT JOIN `community_polls_responses` AS b
		ON a.`cpresponses_id` = b.`cpresponses_id`
		WHERE b.`cpolls_id` = ".$db->qstr($cpolls_id);
		$result	= $db->GetAll($query);
		if(($result)) {
			$output["votes_cast"] = (int) count($result);
		}
	}

	return $output;
}

/**
 * This function is used by the polls module to determine if specific community member access is set for a specific poll
 *
 * @param int $cpolls_id
 * @return array
 */
function communities_polls_specific_access($cpolls_id = 0) {
	global $db, $COMMUNITY_ID;

	$members				= array();

	if($cpolls_id = (int) $cpolls_id) {
		$query	= "SELECT `proxy_id` FROM `community_polls_access` WHERE `cpolls_id` = ".$db->qstr((int) $cpolls_id);
		$results	= $db->CacheGetAll(CACHE_TIMEOUT, $query);
		if($results) {
			foreach ($results as $result) {
				$members[]	= $result["proxy_id"];
			}
		}
	}

	return $members;
}

/**
 * This function is used by the polls module to gather member permissions masks
 *
 * @param int $cpolls_id
 * @return array
 */
function communities_polls_permissions($cpolls_id = 0) {
	global $db, $COMMUNITY_ID;

	$query = "SELECT `allow_member_read`, `allow_member_vote`, `allow_member_results`, `allow_member_results_after` FROM `community_polls`
	WHERE `cpolls_id` = ".$db->qstr($cpolls_id)."
	AND (`allow_member_read` = '1' OR `allow_member_vote` = '1' OR `allow_member_results` = '1' OR `allow_member_results_after` = '1')";

	$results	= $db->CacheGetRow(CACHE_TIMEOUT, $query);

	return $results;
}

/**
 * This function is used by the polls module to gather number of votes for a specific member
 *
 * @param int $cpolls_id
 * @return array
 */
function communities_polls_votes_cast_by_member($cpolls_id = 0, $proxy_id = 0) {
	global $db, $COMMUNITY_ID;

	$query = "SELECT COUNT(proxy_id) as `votes`
	FROM `community_polls_results`, `community_polls_responses`
	WHERE `community_polls_responses`.`cpolls_id` = ".$db->qstr($cpolls_id)."
	AND `community_polls_responses`.`cpresponses_id` = `community_polls_results`.`cpresponses_id`
	AND `proxy_id` = ".$db->qstr($proxy_id);

	$vote_record = $db->GetRow($query);

	return $vote_record;
}

/**
 * These are functions related to the scorm module.
 */

/**
 * Delete a file or a directory (and its whole content)
 *
 * @param  - $filePath (String) - the path of file or directory to delete
 * @return - boolean - true if the delete succeed
 *		   boolean - false otherwise.
 */
function recursive_delete_file($filename) {
	if(is_file($filename)) {
		return unlink($filename);
	} elseif(is_dir($filename)) {
		if(!$handle = @opendir($filename)) {
			return false;
		}

		$filelist = array();

		while(false !== ($file = readdir($handle))) {
			if($file == "." || $file == "..") continue;

			$filelist[] = $filename."/".$file;
		}

		closedir($handle);

		if(count($filelist)) {
			foreach ($filelist as $remove) {
				if(!recursive_delete_file($remove)) {
					return false;
				}
			}
		}

		clearstatcache();

		if(is_writable($filename)) {
			return @rmdir($filename);
		} else {
			return false;
		}
	}
}

function fetch_mime_type($filename) {
	preg_match("|\.([a-z0-9]{2,4})$|i", $filename, $fileSuffix);

	switch(strtolower($fileSuffix[1])) {
		case "js" :
			return "application/x-javascript";

		case "json" :
			return "application/json";

		case "jpg" :
		case "jpeg" :
		case "jpe" :
			return "image/jpg";

		case "png" :
		case "gif" :
		case "bmp" :
		case "tiff" :
			return "image/".strtolower($fileSuffix[1]);

		case "css" :
			return "text/css";

		case "xml" :
			return "application/xml";

		case "doc" :
		case "docx" :
			return "application/msword";

		case "xls" :
		case "xlt" :
		case "xlm" :
		case "xld" :
		case "xla" :
		case "xlc" :
		case "xlw" :
		case "xll" :
			return "application/vnd.ms-excel";

		case "ppt" :
		case "pps" :
			return "application/vnd.ms-powerpoint";

		case "rtf" :
			return "application/rtf";

		case "pdf" :
			return "application/pdf";

		case "html" :
		case "htm" :
		case "php" :
			return "text/html";

		case "txt" :
			return "text/plain";

		case "mpeg" :
		case "mpg" :
		case "mpe" :
			return "video/mpeg";

		case "mp3" :
			return "audio/mpeg3";

		case "wav" :
			return "audio/wav";

		case "aiff" :
		case "aif" :
			return "audio/aiff";

		case "avi" :
			return "video/msvideo";

		case "wmv" :
			return "video/x-ms-wmv";

		case "mov" :
			return "video/quicktime";

		case "zip" :
			return "application/zip";

		case "tar" :
			return "application/x-tar";

		case "swf" :
			return "application/x-shockwave-flash";

		default :
			if(function_exists("mime_content_type")) {
				$fileSuffix = mime_content_type($filename);
			}

			return "unknown/" . trim($fileSuffix[0], ".");
	}
}

/**
 * Processes / resizes and creates properly sized image and thumbnail image
 * for images uploaded to the galleries module.
 *
 * @param string $original_file
 * @param int $photo_id
 * @return bool
 */
function process_user_photo($original_file, $photo_id = 0) {
	global $VALID_MAX_DIMENSIONS, $_SESSION;

	if(!@function_exists("gd_info")) {
		return false;
	}

	if((!@file_exists($original_file)) || (!@is_readable($original_file))) {
		return false;
	}

	if(!$photo_id = (int) $photo_id) {
		return false;
	}
	
	$new_file = STORAGE_USER_PHOTOS."/".$_SESSION["details"]["id"]."-upload";
	$img_quality = 85;

	if($original_file_details = @getimagesize($original_file)) {
		$original_file_width = $original_file_details[0];
		$original_file_height = $original_file_details[1];

		/**
		 * Check if the original_file needs to be resized or not.
		 */
		if(($original_file_width > $VALID_MAX_DIMENSIONS["photo-width"]) || ($original_file_height > $VALID_MAX_DIMENSIONS["photo-height"])) {
			switch($original_file_details["mime"]) {
				case "image/pjpeg":
				case "image/jpeg":
				case "image/jpg":
					$original_img_resource = @imagecreatefromjpeg($original_file);
					break;
				case "image/png":
					$original_img_resource = @imagecreatefrompng($original_file);
					break;
				case "image/gif":
					$original_img_resource = @imagecreatefromgif($original_file);
					break;
				default :
					return false;
					break;
			}
			if($original_img_resource) {
			/**
			 * Determine whether it's a horizontal / vertical image and calculate the new smaller size.
			 */
				if($original_file_width > $original_file_height) {
					$new_file_width		= $VALID_MAX_DIMENSIONS["photo-width"];
					$new_file_height	= (int) (($VALID_MAX_DIMENSIONS["photo-width"] * $original_file_height) / $original_file_width);
				} else {
					$new_file_width		= (int) (($VALID_MAX_DIMENSIONS["photo-height"] * $original_file_width) / $original_file_height);
					$new_file_height	= $VALID_MAX_DIMENSIONS["photo-height"];
				}

				if($original_file_details["mime"] == "image/gif") {
					$new_img_resource = @imagecreate($new_file_width, $new_file_height);
				} else {
					$new_img_resource = @imagecreatetruecolor($new_file_width, $new_file_height);
				}

				if($new_img_resource) {
					if(@imagecopyresampled($new_img_resource, $original_img_resource, 0, 0, 0, 0, $new_file_width, $new_file_height, $original_file_width, $original_file_height)) {
						switch($original_file_details["mime"]) {
							case "image/pjpeg":
							case "image/jpeg":
							case "image/jpg":
								if(!@imagejpeg($new_img_resource, $new_file, $img_quality)) {
									return false;
								}
							break;
							case "image/png":
								if(!@imagepng($new_img_resource, $new_file)) {
									return false;
								}
							break;
							case "image/gif":
								if(!@imagegif($new_img_resource, $new_file)) {
									return false;
								}
							break;
							default :
								return false;
							break;
						}

						@chmod($new_file, 0644);

						/**
						 * Frees the memory this used, so it can be used again for the thumbnail.
						 */
						@imagedestroy($original_img_resource);
						@imagedestroy($new_img_resource);
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			if(@move_uploaded_file($original_file, $new_file)) {
				@chmod($new_file, 0644);

				/**
				 * Create the new width / height so we can use the same variables
				 * below for thumbnail generation.
				 */
				$new_file_width		= $original_file_width;
				$new_file_height	= $original_file_height;
			} else {
				return false;
			}
		}

		/**
		 * Check that the new_file exists, and can be used, then proceed
		 * with Thumbnail generation ($new_file-thumbnail).
		 */
		if((@file_exists($new_file)) && (@is_readable($new_file))) {

			switch($original_file_details["mime"]) {
				case "image/pjpeg":
				case "image/jpeg":
				case "image/jpg":
					$original_img_resource = @imagecreatefromjpeg($new_file);
					break;
				case "image/png":
					$original_img_resource = @imagecreatefrompng($new_file);
					break;
				case "image/gif":
					$original_img_resource = @imagecreatefromgif($new_file);
					break;
				default :
					return false;
					break;
			}

			if(($new_file_width > $VALID_MAX_DIMENSIONS["thumb-width"]) || ($new_file_height > $VALID_MAX_DIMENSIONS["thumb-height"])) {
				$dest_x			= 0;
				$dest_y			= 0;
				$ratio_orig		= ($new_file_width / $new_file_height);
				$cropped_width	= $VALID_MAX_DIMENSIONS["thumb-width"];
				$cropped_height	= $VALID_MAX_DIMENSIONS["thumb-height"];

				if($ratio_orig > 1) {
					$cropped_width	= ($cropped_height * $ratio_orig);
				} else {
					$cropped_height	= ($cropped_width / $ratio_orig);
				}
			} else {
				$cropped_width	= $new_file_width;
				$cropped_height	= $new_file_height;

				$dest_x			= ($VALID_MAX_DIMENSIONS["thumb-width"] / 2) - ($cropped_width / 2);
				$dest_y			= ($VALID_MAX_DIMENSIONS["thumb-height"] / 2) - ($cropped_height / 2 );
			}

			if($original_file_details["mime"] == "image/gif") {
				$new_img_resource = @imagecreate($VALID_MAX_DIMENSIONS["thumb-width"], $VALID_MAX_DIMENSIONS["thumb-height"]);
			} else {
				$new_img_resource = @imagecreatetruecolor($VALID_MAX_DIMENSIONS["thumb-width"], $VALID_MAX_DIMENSIONS["thumb-height"]);
			}

			if($new_img_resource) {
				if(@imagecopyresampled($new_img_resource, $original_img_resource, $dest_x, $dest_y, 0, 0, $cropped_width, $cropped_height, $new_file_width, $new_file_height)) {
					switch($original_file_details["mime"]) {
						case "image/pjpeg":
						case "image/jpeg":
						case "image/jpg":
							if(!@imagejpeg($new_img_resource, $new_file."-thumbnail", $img_quality)) {
								return false;
							}
						break;
						case "image/png":
							if(!@imagepng($new_img_resource, $new_file."-thumbnail")) {
								return false;
							}
						break;
						case "image/gif":
							if(!@imagegif($new_img_resource, $new_file."-thumbnail")) {
								return false;
							}
						break;
						default :
							return false;
						break;
					}

					@chmod($new_file."-thumbnail", 0644);

					/**
					 * Frees the memory this used, so it can be used again.
					 */
					@imagedestroy($original_img_resource);
					@imagedestroy($new_img_resource);
					
					return true;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function google_generate_id($firstname = "", $lastname = "") {
	global $db;

	$google_id	= false;

	$firstname	= clean_input($firstname, array("alpha", "lowercase"));
	$lastname	= clean_input($lastname, array("alpha", "lowercase"));

	if(($firstname) && ($lastname)) {
		$result	= true;
		$i		= 1;

		while (($result) && ($i <= strlen($firstname))) {
			$google_id = substr($firstname, 0, $i).$lastname;

			$query	= "SELECT `id` FROM `".AUTH_DATABASE."`.`user_data` WHERE `google_id` = ".$db->qstr($google_id);
			$result	= $db->GetRow($query);

			$i++;
		}

		if ($result) {
			$google_id = $firstname.".".$lastname;

			$query	= "SELECT `id` FROM `".AUTH_DATABASE."`.`user_data` WHERE `google_id` = ".$db->qstr($google_id);
			$result	= $db->GetRow($query);
		}

		$i = 1;
		while (($result) && ($i <= 100)) {
			$google_id = substr($firstname, 0, 1).$lastname.$i;

			$query	= "SELECT `id` FROM `".AUTH_DATABASE."`.`user_data` WHERE `google_id` = ".$db->qstr($google_id);
			$result	= $db->GetRow($query);

			$i++;
		}

		if ($result) {
			$google_id = false;
		}
	}

	return $google_id;
}

function google_create_id() {
	global $db, $GOOGLE_APPS, $AGENT_CONTACTS, $ERROR, $ERRORSTR;

	if ((isset($GOOGLE_APPS)) && (is_array($GOOGLE_APPS)) && (isset($GOOGLE_APPS["active"])) && ((bool) $GOOGLE_APPS["active"])) {
		$query	= "	SELECT a.*, b.`group`, b.`role`
					FROM `".AUTH_DATABASE."`.`user_data` AS a
					LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS b
					ON a.`id` = b.`user_id`
					WHERE a.`id` = ".$db->qstr($_SESSION["details"]["id"]);
		$result	= $db->GetRow($query);
		if ($result) {
			if ((isset($GOOGLE_APPS["groups"])) && (is_array($GOOGLE_APPS["groups"])) && (in_array($_SESSION["details"]["group"], $GOOGLE_APPS["groups"]))) {
				if (($result["google_id"] == "opt-out") || ($result["google_id"] == "opt-in") || ($result["google_id"] == "")) {
					if ($google_id = google_generate_id($result["firstname"], $result["lastname"])) {
						require_once "Zend/Loader.php";

						Zend_Loader::loadClass("Zend_Gdata_ClientLogin");
						Zend_Loader::loadClass("Zend_Gdata_Gapps");

						$firstname	= $result["firstname"];
						$lastname	= $result["lastname"];
						$password	= $result["password"];

						try {
							$client		= Zend_Gdata_ClientLogin::getHttpClient($GOOGLE_APPS["admin_username"], $GOOGLE_APPS["admin_password"], Zend_Gdata_Gapps::AUTH_SERVICE_NAME);
							$service	= new Zend_Gdata_Gapps($client, $GOOGLE_APPS["domain"]);
							$service->createUser($google_id, $firstname, $lastname, $password, "MD5");

							$search		= array("%FIRSTNAME%", "%LASTNAME%", "%GOOGLE_APPS_DOMAIN%", "%GOOGLE_ID%", "%GOOGLE_APPS_QUOTA%", "%APPLICATION_NAME%", "%ADMINISTRATOR_NAME%", "%ADMINISTRATOR_EMAIL%");
							$replace	= array($firstname, $lastname, $GOOGLE_APPS["domain"], $google_id, $GOOGLE_APPS["quota"], APPLICATION_NAME, $AGENT_CONTACTS["administrator"]["name"], $AGENT_CONTACTS["administrator"]["email"]);

							$subject	= str_replace($search, $replace, $GOOGLE_APPS["new_account_subject"]);
							$message	= str_replace($search, $replace, $GOOGLE_APPS["new_account_msg"]);

							$query = "UPDATE `".AUTH_DATABASE."`.`user_data` SET `google_id` = ".$db->qstr($google_id)." WHERE `id` = ".$db->qstr($_SESSION["details"]["id"]);
							if ($db->Execute($query)) {
								if(@mail($_SESSION["details"]["email"], $subject, $message, "From: \"".$AGENT_CONTACTS["administrator"]["name"]."\" <".$AGENT_CONTACTS["administrator"]["email"].">\nReply-To: \"".$AGENT_CONTACTS["administrator"]["name"]."\" <".$AGENT_CONTACTS["administrator"]["email"].">")) {
									$_SESSION["details"]["google_id"] = $google_id;
									
									application_log("success", "Successfully sent new Google account notice to ".$_SESSION["details"]["email"]);

									return true;
								} else {
									applicaiton_log("error", "Unable to send new Google account notification to ".$_SESSION["details"]["email"]);

									throw new Exception();
								}
							} else {
								application_log("error", "Unable to update the google_id [".$google_id."] field for proxy_id [".$_SESSION["details"]["id"]."].");

								throw new Exception();
							}
						} catch (Zend_Gdata_Gapps_ServiceException $e) {
							if (is_array($e->getErrors())) {
								foreach ($e->getErrors() as $error) {
									application_log("error", "Unable to create google_id [".$google_id."] for username [".$_SESSION["details"]["username"]."]. Error details: [".$error->getErrorCode()."] ".$error->getReason().".");
								}
							}
						}
					} else {
						application_log("error", "google_generate_id() function returned false out of firstname [".$result["firstname"]." and lastname [".$result["lastname"]."].");
					}
				}
			} else {
				application_log("error", "google_create_id() failed because users group [".$_SESSION["details"]["group"]."] was not in the GOOGLE_APPS[groups].");
			}
		} else {
			application_log("error", "google_create_id() failed because we were unable to generate information on proxy_id [".$_SESSION["details"]["id"]."]. Database said: ".$db->ErrorMsg());
		}
	}

	$ERROR++;
	$ERRORSTR[] = "We apologize, but we were unable to create your <strong>".$GOOGLE_APPS["domain"]."</strong> account for you at this time.<br /><br />The system administrator has been notified of the error; please try again later.";

	return false;
}

function google_reset_password($password = "") {
	global $db, $GOOGLE_APPS;

	if ((isset($GOOGLE_APPS)) && (is_array($GOOGLE_APPS)) && (isset($GOOGLE_APPS["active"])) && ((bool) $GOOGLE_APPS["active"]) && ($password)) {
		$query = "	SELECT a.*, b.`group`, b.`role`
					FROM `".AUTH_DATABASE."`.`user_data` AS a
					LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS b
					ON a.`id` = b.`user_id`
					WHERE a.`id` = ".$db->qstr($_SESSION["details"]["id"])."
					AND b.`app_id` = ".$db->qstr(AUTH_APP_ID);
		$result	= $db->GetRow($query);
		if ($result) {
			if (!in_array($result["google_id"], array("", "opt-out", "opt-in"))) {
				try {
					$client = Zend_Gdata_ClientLogin::getHttpClient($GOOGLE_APPS["admin_username"], $GOOGLE_APPS["admin_password"], Zend_Gdata_Gapps::AUTH_SERVICE_NAME);
					$service = new Zend_Gdata_Gapps($client, $GOOGLE_APPS["domain"]);

					$account = $service->retrieveUser($result["google_id"]);
					$account->login->password = $password;
					$account->save();

					application_log("success", "Successfully updated Google account password for google_id [".$result["google_id"]."] and proxy_id [".$_SESSION["details"]["id"]."].");

					return true;
				} catch (Zend_Gdata_Gapps_ServiceException $e) {
					application_log("error", "Unable to change password for google_id [".$google_id."] for proxy_id [".$_SESSION["details"]["id"]."]. Error details: [".$error->getErrorCode()."] ".$error->getReason().".");
					if (is_array($e->getErrors())) {
						foreach ($e->getErrors() as $error) {
							application_log("error", "Unable to change password for google_id [".$google_id."] for proxy_id [".$_SESSION["details"]["id"]."]. Error details: [".$error->getErrorCode()."] ".$error->getReason().".");
						}
					}
				}
			}
		} else {
			application_log("error", "google_reset_password() failed because we were unable to fetch information on proxy_id [".$_SESSION["details"]["id"]."]. Database said: ".$db->ErrorMsg());
		}
	}

	return false;
}

/**
 * Function takes minutes and converts them to hours.
 *
 * @param int $minutes
 * @return int
 */
function display_hours($minutes = 0) {
	if($minutes = (int) $minutes) {
		return round(($minutes / 60), 2);
	}

	return 0;
}

/**
 * This function generates the javascript for PlotKit's y axis labels.
 *
 * @param unknown_type $labels
 * @return unknown
 */
function plotkit_statistics_lables($labels = array()) {
	$output = array();

	if(is_array($labels)) {
		foreach ($labels as $key => $label) {
			$output[] = "{label: '".$label."', v: ".(int) $key."}";
		}
	}

	return implode(", ", $output);
}

/**
 * This function generates the javascript for PlotKit's chart data.
 *
 * @param unknown_type $values
 * @return unknown
 */
function plotkit_statistics_values($values = array()) {
	$output = array();

	if(is_array($values)) {
		foreach ($values as $key => $value) {
			$output[] = "[".(int) $key.", ".(int) $value."]";
		}
	}

	return implode(", ", $output);
}

/**
 * Insert notification and recipients into cron notification tables.
 *
 * @param array $user_ids
 * @param string $community
 * @param string $type
 * @param string $subject
 * @param string $message
 * @param string $url
 * @param bigint $release_time
 */
function post_notify($user_ids, $community, $type, $subject, $message, $url='', $release_time=0, $record_id=0, $author_id=0) {
	global $db;
	
	if(($db->AutoExecute("community_notifications", array("release_time" => ($release_time?$release_time:time()), "community" => $community,
		"type" => $type, "subject" => $subject, "body" => $message, "url" => $url, "record_id" => $record_id, "author_id" => $author_id), "INSERT")) && ($cnotification_id = $db->Insert_Id())) {
		foreach($user_ids as $user_id) {
			if(!$db->AutoExecute("cron_community_notifications", array("cnotification_id" => $cnotification_id, "proxy_id" => $user_id['proxy_id']), "INSERT")) {
				application_log("error", "Unable to insert the recipient for this post. Database said: ".$db->ErrorMsg());
				return ;
			}
		}
	} else {
		application_log("error", "Unable to insert this notification. Database said: ".$db->ErrorMsg());
	}
}

/**
 * Delete notification and recipients from cron notification tables.
 *
 * @param int    $cnotification_id
 */
function delete_notify($cnotification_id) {
	global $db;

	$query  = "DELETE FROM `cron_community_notifications` WHERE `cnotification_id` = ".$db->qstr($cnotification_id);
	if($db->Execute($query)) {
		$query  = "DELETE FROM `community_notifications` WHERE `cnotification_id` = ".$db->qstr($cnotification_id);
		if(!$db->Execute($query)) {
			application_log("error", "Failed to delete Post $cnotification_id from table `community_notifications`. Database said: ".$db->ErrorMsg());
		}
	} else
		application_log("error", "Failed to delete records with `cnotification_id` of $cnotification_id from table `cron_community_notifications`. Database said: ".$db->ErrorMsg());
}

/**
 * Delete individual notification.
 *
 * @param string $type
 */
function delete_notifications($types) {
	global $db;

	$query  = "SELECT `cnotification_id` FROM `community_notifications` WHERE `type` = ".$db->qstr($types);
	$result = $db->GetRow($query);
	if ($result) {
		delete_notify($result['cnotification_id']);
	}
}

/**
 * This function selects the group of users from the database who should be
 * receiving a notification for the chosen 'notify type', then adds a queued
 * notification to the database which will be sent out by a cron-job to each
 * user.
 *
 * @param int $community_id
 * @param int $record_id
 * @param string $notify_type
 * @return boolean
 */
function community_notify($community_id, $record_id, $content_type, $url, $permission_id = 0, $release_time = 0) {
	global $db;
	
	/**
	 * Select the user permission level required to access the content which
	 * is the basis of the notification. Administrators of the community will
	 * always have access however - so they can sign up for notifications for
	 * any piece of content/page.
	*/
	switch ($content_type) {
		case "poll" :
			$query = "	SELECT a.`allow_member_read`, b.`allow_member_view`
						FROM `community_polls` AS a
						LEFT JOIN `community_pages` AS b
						ON a.`cpage_id` = b.`cpage_id`
						WHERE a.`cpolls_id` = ".$db->qstr($record_id);
			$result = $db->GetRow($query);
			if ($result["allow_member_read"] && $result["allow_member_view"]) {
				$permission_required = 0;
			} else {
				$permission_required = 1;
			}
			break;
		case "file" :
		case "file-revision" :
		case "file-comment" :
			$query = "	SELECT a.`allow_member_read`, b.`allow_member_view`
						FROM `community_shares` AS a
						LEFT JOIN `community_pages` AS b
						ON a.`cpage_id` = b.`cpage_id`
						WHERE a.`cshare_id` = ".$db->qstr($record_id);
			$result = $db->GetRow($query);
			if ($result["allow_member_read"] && $result["allow_member_view"]) {
				$permission_required = 0;
			} else {
				$permission_required = 1;
			}
			break;
		case "photo" :
		case "photo-comment" :
			$query = "	SELECT a.`allow_member_read`, b.`allow_member_view`
						FROM `community_galleries` AS a
						LEFT JOIN `community_pages` AS b
						ON a.`cpage_id` = b.`cpage_id`
						WHERE a.`cgallery_id` = ".$db->qstr($record_id);
			$result = $db->GetRow($query);
			if ($result["allow_member_read"] && $result["allow_member_view"]) {
				$permission_required = 0;
			} else {
				$permission_required = 1;
			}
			break;
		case "announcement" :
			$query = "	SELECT b.`allow_member_view`
						FROM `community_announcements` AS a
						LEFT JOIN `community_pages` AS b
						ON a.`cpage_id` = b.`cpage_id`
						WHERE a.`cannouncement_id` = ".$db->qstr($record_id);
			$result = $db->GetRow($query);
			if ($result["allow_member_view"]) {
				$permission_required = 0;
			} else {
				$permission_required = 1;
			}
			break;
		case "event" :
			$query = "	SELECT b.`allow_member_view`
						FROM `community_events` AS a
						LEFT JOIN `community_pages` AS b
						ON a.`cpage_id` = b.`cpage_id`
						WHERE a.`cevent_id` = ".$db->qstr($record_id);
			$result = $db->GetRow($query);
			if ($result["allow_member_view"]) {
				$permission_required = 0;
			} else {
				$permission_required = 1;
			}
			break;
		case "post" :
		case "reply" :
			$query = "	SELECT a.`allow_member_read`, b.`allow_member_view`
						FROM `community_discussions` AS a
						LEFT JOIN `community_pages` AS b
						ON a.`cpage_id` = b.`cpage_id`
						WHERE a.`cdiscussion_id` = ".$db->qstr($record_id);
			$result = $db->GetRow($query);
			if ($result["allow_member_read"] && $result["allow_member_view"]) {
				$permission_required = 0;
			} else {
				$permission_required = 1;
			}
			break;
		default :
			$permission_required = 1;
			break;
	}
	
	/**
	 * Select which users will be sent a notification based on the
	 * type of notification and the user's notification setting for
	 * the selected piece of content in the selected community.
	 */
	switch ($content_type) {
		case "announcement" :
		case "event" :
			$query = "	SELECT a.`proxy_id` FROM `community_members` AS a
						LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
						ON a.`proxy_id` = b.`id`
						WHERE a.`proxy_id` NOT IN (
													SELECT `proxy_id` FROM `community_notify_members` 
													WHERE `community_id` = ".$db->qstr($community_id)." 
													AND `record_id` = ".$db->qstr($permission_id)." 
													AND `notify_type` = ".$db->qstr($content_type)." 
													AND `notify_active` = '0'
												) 
						AND a.`community_id` = ".$db->qstr($community_id)."
						AND b.`notifications` = '1'
						AND a.`member_acl` >= ".$db->qstr($permission_required);
			break;
		case "poll" :
			$query = "	SELECT COUNT(`proxy_id`) AS `members_count`
						FROM `community_polls_access`
						WHERE `cpolls_id` = ".$db->qstr($record_id);
			$members_count	= $db->GetOne($query);
			if (isset($members_count) && $members_count) {
				$query = "	SELECT a.`proxy_id` FROM `community_members` AS a
							LEFT JOIN `community_notify_members` AS b
							ON b.`proxy_id` = a.`proxy_id`
							AND b.`notify_type` = ".$db->qstr($content_type)."
							AND b.`community_id` = a.`community_id`
							AND b.`record_id` = ".$db->qstr($permission_id)."
							LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS c
							ON a.`proxy_id` = c.`id`
							WHERE a.`proxy_id` IN ( 
													SELECT `proxy_id`
													FROM `community_polls_access`
													WHERE `cpolls_id` = ".$db->qstr($record_id)."
												) 
							AND b.`notify_active` != '0'
							AND a.`community_id` = ".$db->qstr($community_id)."
							AND c.`notifications` = '1'
							AND a.`member_acl` >= ".$db->qstr($permission_required);
			} else {
				$query = "	SELECT `proxy_id` FROM `community_members` AS a
							LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
							ON a.`proxy_id` = b.`id`
							WHERE a.`proxy_id` NOT IN (
														SELECT `proxy_id` FROM `community_notify_members` 
														WHERE `community_id` = ".$db->qstr($community_id)." 
														AND `record_id` = ".$db->qstr($permission_id)." 
														AND `notify_type` = ".$db->qstr($content_type)." 
														AND `notify_active` = '0'
													) 
							AND a.`community_id` = ".$db->qstr($community_id)."
							AND b.`notifications` = '1'
							AND a.`member_acl` >= ".$db->qstr($permission_required);
			}
			break;
		case "join" :
		case "leave" :
			$query = "	SELECT `proxy_id` FROM `community_members` AS a
						LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
						ON a.`proxy_id` = b.`id`
						WHERE a.`proxy_id` IN ( 
												SELECT `proxy_id` FROM `community_notify_members` 
												WHERE `community_id` = ".$db->qstr($community_id)." 
												AND `record_id` = ".$db->qstr($community_id)." 
												AND `notify_type` = 'members' 
												AND `notify_active` = '1'
											) 
						AND a.`member_acl` = '1' 
						AND b.`notifications` = '1'
						AND a.`community_id` = ".$db->qstr($community_id);
			break;
		case "reply" :
			$query = "	SELECT DISTINCT(`proxy_id`) FROM `community_members` AS a
						LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
						ON a.`proxy_id` = b.`id`
						WHERE a.`proxy_id` IN (
												SELECT `proxy_id` FROM `community_notify_members` 
												WHERE `community_id` = ".$db->qstr($community_id)." 
												AND `record_id` = ".$db->qstr($permission_id)." 
												AND `notify_type` = ".$db->qstr($content_type)." 
												AND `notify_active` = '1'
											) 
						OR a.`proxy_id` IN (
													SELECT a.`proxy_id` FROM `community_notify_members` AS a
													LEFT JOIN `community_discussion_topics` AS b
													ON b.`cdiscussion_id` = a.`record_id`
													WHERE a.`community_id` = ".$db->qstr($community_id)." 
													AND a.`notify_type` = 'post' 
													AND a.`notify_active` = '1'
													AND b.`cdtopic_id` = ".$db->qstr($permission_id)." 
												)
						AND a.`community_id` = ".$db->qstr($community_id)."
						AND b.`notifications` = '1'
						AND a.`member_acl` >= ".$db->qstr($permission_required);
			break;
		case "file-revision" :
		case "file-comment" :
			$query = "	SELECT `proxy_id` FROM `community_members` AS a
						LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
						ON a.`proxy_id` = b.`id`
						WHERE a.`proxy_id` IN (
												SELECT `proxy_id` FROM `community_notify_members` 
												WHERE `community_id` = ".$db->qstr($community_id)." 
												AND `record_id` = ".$db->qstr($permission_id)." 
												AND `notify_type` = 'file-notify' 
												AND `notify_active` = '1'
											) 
						AND a.`community_id` = ".$db->qstr($community_id)."
						AND b.`notifications` = '1'
						AND a.`member_acl` >= ".$db->qstr($permission_required);
			break;
		default :
			$query = "	SELECT `proxy_id` FROM `community_members` AS a
						LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
						ON a.`proxy_id` = b.`id`
						WHERE a.`proxy_id` IN (
												SELECT `proxy_id` FROM `community_notify_members` 
												WHERE `community_id` = ".$db->qstr($community_id)." 
												AND `record_id` = ".$db->qstr($permission_id)." 
												AND `notify_type` = ".$db->qstr($content_type)." 
												AND `notify_active` = '1'
											) 
						AND a.`community_id` = ".$db->qstr($community_id)."
						AND b.`notifications` = '1'
						AND a.`member_acl` >= ".$db->qstr($permission_required);
			break;
	}
	$proxy_ids = $db->GetAll($query);
	
	if($proxy_ids && count($proxy_ids)) {
		/**
		 * Select which type of message should be sent - then generate the message
		 * and subject in accordance with that.
		 */
		switch ($content_type) {
			case "poll" :
				$query	 = "SELECT a.`poll_title`, b.`community_title` 
							FROM `community_polls` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`cpolls_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["poll_title"];
				$subject = "New poll started";
				break;
			case "file" :
				$query	 = "SELECT a.`file_title`, b.`community_title` 
							FROM `community_share_files` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`csfile_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["file_title"];
				$subject = "New file added";
				break;
			case "file-revision" :
				$query	 = "SELECT a.`file_title`, b.`community_title` 
							FROM `community_share_files` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`csfile_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["file_title"];
				$subject = "New version of file added";
				break;
			case "file-comment" :
				$query	 = "SELECT a.`file_title`, b.`community_title` 
							FROM `community_share_files` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`csfile_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["file_title"];
				$subject = "New file comment added";
				break;
			case "photo" :
				$query	 = "SELECT a.`photo_title`, b.`community_title` 
							FROM `community_gallery_photos` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`cgphoto_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["photo_title"];
				$subject = "New photo added";
				break;
			case "photo-comment" :
				$query	 = "SELECT a.`photo_title`, b.`community_title` 
							FROM `community_gallery_photos` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`cgphoto_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["photo_title"];
				$subject = "New photo comment added";
				break;
			case "announcement" :
				$query	 = "SELECT a.`announcement_title`, b.`community_title` 
							FROM `community_announcements` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`cannouncement_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["announcement_title"];
				$subject = "New announcement added";
				break;
			case "event" :
				$query	 = "SELECT a.`event_title`, b.`community_title` 
							FROM `community_events` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`cevent_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["event_title"];
				$subject = "New event added";
				break;
			case "post" :
				$query	 = "SELECT a.`topic_title`, b.`community_title` 
							FROM `community_discussion_topics` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`cdtopic_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["topic_title"];
				$subject = "New discussion topic added";
				break;
			case "reply" :
				$query	 = "SELECT a.`topic_title`, b.`community_title` 
							FROM `community_discussion_topics` AS a
							LEFT JOIN `communities` AS b
							ON a.`community_id` = b.`community_id`
							WHERE b.`community_id` = ".$db->qstr($community_id)."
							AND a.`cdtopic_id` = ".$db->qstr($record_id);
				$result = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["topic_title"];
				$subject = "New discussion reply added";
				break;
			case "join"  :
			case "leave" :
				$query   = "SELECT a.`community_title`, CONCAT_WS(' ', b.`firstname`, b.`lastname`) AS `fullname`
							FROM `communities` AS a, `".AUTH_DATABASE."`.`user_data` AS b
							WHERE a.`community_id` =  ".$db->qstr($community_id)." and b.`id` = ". $db->qstr($record_id);
				$result  = $db->GetRow($query);
				$community_title = $result["community_title"];
				$content_title = $result["fullname"];
				$record_id = $permission_id;
				if ($content_type = "join") {
					$subject = "New member joined community";
				} else {
					$subject = "Member left community";
				}
				break;
			default :
				return false;
				break;
		}
		$message = "community-".$content_type."-notification.txt";
		post_notify($proxy_ids, $community_title, $content_type, $subject, $message, $url, $release_time, $record_id, $_SESSION["details"]["id"]);
	} else {
		return false;
	}
	
	return true;
}

function quiz_generate_description($required = 0, $quiztype_code = "delayed", $quiz_timeout = 0, $quiz_questions = 1, $quiz_attempts = 0, $timeframe = "") {
	global $db, $RESOURCE_TIMEFRAMES;

	$output	= "This is %s quiz to be completed %s. You will have %s and %s to answer the %s in this quiz, and your results will be presented %s.";

	$string_1 = (((int) $required) ? "a required" : "an optional");
	$string_2 = ((($timeframe) && ($timeframe != "none")) ? strtolower($RESOURCE_TIMEFRAMES["event"][$timeframe]) : "when you see fit");
	$string_3 = (((int) $quiz_timeout) ? $quiz_timeout." minute".(($quiz_timeout != 1) ? "s" :"") : "no time limitation");
	$string_4 = (((int) $quiz_attempts) ? $quiz_attempts." attempt".(($quiz_attempts != 1) ? "s" : "") : "unlimited attempts");
	$string_5 = $quiz_questions." question".(($quiz_questions != 1) ? "s" : "");
	$string_6 = (($quiztype_code == "delayed") ? "only after the quiz expires" : "immediately after completion");

	return sprintf($output, $string_1, $string_2, $string_3, $string_4, $string_5, $string_6);
}

/**
 * This function loads the current progress based on an eqprogress_id.
 *
 * @global object $db
 * @param int $eqprogress_id
 * @return array Returns the users currently progress or returns false if there
 * is an error.
 */
function quiz_load_progress($eqprogress_id = 0) {
	global $db;

	$output = array();

	if ($eqprogress_id = (int) $eqprogress_id) {
	/**
		 * Grab the specified progress identifier, but you better be sure this
		 * is the correct one, and the results are being returned to the proper
		 * user.
	 */
		$query		= "	SELECT *
						FROM `event_quiz_progress`
						WHERE `eqprogress_id` = ".$db->qstr($eqprogress_id);
		$progress	= $db->GetRow($query);
		if ($progress) {
		/**
		 * Add all of the qquestion_ids to the $output array so they're set.
		 */
			$query		= "SELECT * FROM `quiz_questions` WHERE `quiz_id` = ".$db->qstr($progress["quiz_id"])." ORDER BY `question_order` ASC";
			$questions	= $db->GetAll($query);
			if ($questions) {
				foreach ($questions as $question) {
					$output[$question["qquestion_id"]] = 0;
				}
			} else {
				return false;
			}

			/**
			 * Update the $output array with any currently selected responses.
			 */
			$query		= "	SELECT *
							FROM `event_quiz_responses`
							WHERE `eqprogress_id` = ".$db->qstr($eqprogress_id);
			$responses	= $db->GetAll($query);
			if ($responses) {
				foreach ($responses as $response) {
					$output[$response["qquestion_id"]] = $response["qqresponse_id"];
				}
			}
		} else {
			return false;
		}
	}

	return $output;
}

function quiz_save_response($eqprogress_id, $equiz_id, $event_id, $quiz_id, $qquestion_id, $qqresponse_id) {
	global $db;

	/**
	 * Check to ensure that this response is associated with this question.
	 */
	$query	= "SELECT * FROM `quiz_question_responses` WHERE `qqresponse_id` = ".$db->qstr($qqresponse_id)." AND `qquestion_id` = ".$db->qstr($qquestion_id);
	$result	= $db->GetRow($query);
	if ($result) {
	/**
	 * See if they have already responded to this question or not as this
	 * determines whether an INSERT or an UPDATE is required.
	 */
		$query = "	SELECT `eqresponse_id`, `qqresponse_id`
					FROM `event_quiz_responses`
					WHERE `eqprogress_id` = ".$db->qstr($eqprogress_id)."
					AND `equiz_id` = ".$db->qstr($equiz_id)."
					AND `event_id` = ".$db->qstr($event_id)."
					AND `quiz_id` = ".$db->qstr($quiz_id)."
					AND `proxy_id` = ".$db->qstr($_SESSION["details"]["id"])."
					AND `qquestion_id` = ".$db->qstr($qquestion_id);
		$result	= $db->GetRow($query);
		if ($result) {
		/**
		 * Checks to see if the response is different from what was previously
		 * stored in the event_quiz_responses table.
		 */
			if ($qqresponse_id != $result["qqresponse_id"]) {
				$quiz_response_array	= array (
					"qqresponse_id" => $qqresponse_id,
					"updated_date" => time(),
					"updated_by" => $_SESSION["details"]["id"]
				);

				if ($db->AutoExecute("event_quiz_responses", $quiz_response_array, "UPDATE", "`eqresponse_id` = ".$db->qstr($result["eqresponse_id"]))) {
					return true;
				} else {
					application_log("error", "Unable to update a response to a question that has already been recorded. Database said: ".$db->ErrorMsg());
				}
			} else {
				return true;
			}
		} else {
			$quiz_response_array	= array (
				"eqprogress_id" => $eqprogress_id,
				"equiz_id" => $equiz_id,
				"event_id" => $event_id,
				"quiz_id" => $quiz_id,
				"proxy_id" => $_SESSION["details"]["id"],
				"qquestion_id" => $qquestion_id,
				"qqresponse_id" => $qqresponse_id,
				"updated_date" => time(),
				"updated_by" => $_SESSION["details"]["id"]
			);

			if ($db->AutoExecute("event_quiz_responses", $quiz_response_array, "INSERT")) {
				return true;
			} else {
				application_log("error", "Unable to record a response to a question that was submitted. Database said: ".$db->ErrorMsg());
			}
		}
	} else {
		application_log("error", "A submitted qqresponse_id was not a valid response for the qquestion_id that was provided when attempting to submit a response to a question.");
	}

	return false;
}

function lp_multiple_select_popup($id, $checkboxes, $options) {

	if(!is_array($checkboxes) || $id == null) {
		return null;
	}

	$default_options = array(
		'title'			=>	'Select Multiple',
		'cancel'		=>	false,
		'cancel_text'	=>	'Cancel',
		'submit'		=>	false,
		'submit_text'	=>	'Submit',
		'filter'		=>	true,
		'class'			=>	'',
		'width'			=>	'300px',
		'hidden'		=>	true
	);

	$options = array_merge($default_options, $options);
	$classes = array("select_multiple_container");

	if(is_array($options['class'])) {
		foreach($options['class'] as $class) {
			$classes[] = $class;
		}
	} else {
		if($options['class'] != '') {
			$classes[] = $options['class'];
		}
	}

	$class_string = implode($classes, ' ');

	$return = '<div id="'.$id.'_options" class="'.$class_string.'" style="'.($options['hidden'] ? 'display:none; ' : '').'width: '.$options['width'].';"><div class="select_multiple_header">'.$options['title'].'</div><div id="'.$id.'_scroll" class="select_multiple_scroll"><table cellspacing="0" cellpadding="0" class="select_multiple_table" width="100%">';
	$return .= lp_multiple_select_table($checkboxes, 0, 0).'</table><div style="clear: both;"></div></div><div class="select_multiple_submit">';
	if($options['filter']) {
		$return .= '<div class="select_multiple_filter"><input id="'.$id.'_select_filter" type="text" value="Search..."><span class="select_filter_clear" onclick="$(\''.$id.'_select_filter\').value = \'\'; $$(\'.filter-hidden\').invoke(\'show\');"></span></div>';
	}

	$return .= ($options['cancel'] == true ? '<input type="button" class="button-sm" value="'.$options['cancel_text'].'" id="'.$id.'_cancel"/>&nbsp;' : '');
	$return .= ($options['submit'] == true ? '<input type="button" class="button-sm" value="'.$options['submit_text'].'" id="'.$id.'_close"/>' : '').'</div></div>';
	return $return;
}

function lp_multiple_select_inline($id, $checkboxes, $options) {

	if(!is_array($checkboxes) || $id == null) {
		return null;
	}

	$default_options = array(
		'filterbar'			=>	true,
		'filter'			=>	true,
		'class'				=>	'',
		'width'				=>	'300px',
		'hidden'			=>	false,
		'ajax'				=>	false,
		'selectboxname'		=>	'category',
		'category_check_all'=>  false
	);

	$options = array_merge($default_options, $options);
	$classes = array("select_multiple_container", "inline");

	if(is_array($options['class'])) {
		foreach($options['class'] as $class) {
			$classes[] = $class;
		}
	} else {
		if($options['class'] != '') {
			$classes[] = $options['class'];
		}
	}

	$class_string = implode($classes, ' ');

	$return = '<div id="'.$id.'_options" class="'.$class_string.'"
	  style="'.($options['hidden'] ? 'display:none; ' : '').
	'width: '.$options['width'].';">';

	if($options['filterbar']) {
		$return .= '<div class="select_multiple_submit">';
		if($options['ajax']) {
			$return .= lp_multiple_select_category_select($id, $checkboxes, $options);
		}

		if($options['filter']) {
			$return .= '<div class="select_multiple_filter '.($options['ajax'] ? 'ajax' : '').'">
		  <input id="'.$id.'_select_filter" type="text" value="Search...">
			<span class="select_filter_clear" onclick="$(\''.$id.'_select_filter\').value = \'\'; $$(\'.filter-hidden\').invoke(\'show\');"></span>
		</div>';
		}

		$return .= '</div>';
	}
	

	$return .= '<div id="'.$id.'_scroll" class="select_multiple_scroll" style="'.
	(isset($options['height']) ? 'height: '.$options['height'].';' : '' ).'"><table cellspacing="0" cellpadding="0" class="select_multiple_table" width="100%">';

	if($options['ajax']) {
		$return .= '<tr><td colspan="2" style="text-align: center;">Please select a '.$options['selectboxname'].' from above.</td></tr>';
	} else {
		$return .= lp_multiple_select_table($checkboxes, 0, 0);
	}

	$return .='</table><div style="clear: both;"></div></div></div>';

	return $return;
}



function lp_multiple_select_table($checkboxes, $indent, $i, $category_select_all = false) {
	$return = "";
	$input_class = 'select_multiple_checkbox';
	foreach($checkboxes as $checkbox) {
		if($i%2 == 0) {
			$class = 'even';
		} else {
			$class = 'odd';
		}

		if(isset($checkbox['category']) && $checkbox['category'] == true) {
			if($category_select_all) {
				$input = '<input type="checkbox" id="'.$checkbox['value'].'_category"/>';
			} else {
				$input = "&nbsp;";
			}
			$class .= ' category';
			$name_class = "select_multiple_name_category";
			$input_class = "select_multiple_checkbox_category";
		} else if(isset($checkbox['disabled']) && $checkbox['disabled'] == true) {
				$input = "&nbsp;";
				$class .= ' disabled';
				$name_class = "select_multiple_name_disabled";
			} else {
				$input = '<input type="checkbox" id="'.$checkbox['value'].'" value="'.$checkbox['value'].'" '.$checkbox['checked'].'/>';
				$name_class = "select_multiple_name";
			}

		if(isset($checkbox['name_class'])) {
			$name_class = $checkbox['name_class'];
		}

		$i++;

		$return .= '<tr class="'.$class.'"><td class="'.$name_class.' indent_'.$indent.'"><label for="'.$checkbox['value'].'">'.$checkbox['text'].'</label></td><td class="'.$input_class.'">'.$input.'</td></tr>';

		if(isset($checkbox['options'])) {
			$return .= lp_multiple_select_table($checkbox['options'], $indent+1, $i);
		}
	}
	return $return;
}


function lp_multiple_select_category_select($id, $checkboxes, $options) {
	$return = '<select name="'.$id.'_category_select" id="'.$id.'_category_select">';
	if ($options["default-option"]) {
		$return .= '<option id="default_drop_option" class="select_multiple_category_drop" value="0">'.$options['default-option'].'</option>';
	}
	foreach($checkboxes as $checkbox) {

		if(isset($checkbox['class'])) {
			$class = $checkbox['class'];
		} else {
			$class = "select_multiple_category_drop";
		}

		$return .= '<optgroup class="'.$class.'" label="'.$checkbox['text'].'">';
		if(isset($checkbox['options']) && is_array($checkbox['options']) && !empty($checkbox['options'])) {
			foreach($checkbox['options'] as $select_option) {
				if(isset($select_option['class'])) {
					$class = $select_option['class'];
				} else {
					$class = "select_multiple_category_drop";
				}
				$return .= '<option id="'.$select_option['value'].'_drop_option" class="'.$class.'" value="'.$select_option['value'].'">'.$select_option['text'].'</option>';

			}
		}
		$return .= '</optgroup>';

	}
	$return .= '</select>';
	return $return;
}

/**
 * This function returns the total number of attempts the user
 * has made on the provided equiz_id, completed, expired or otherwise.

 * @param int $equiz_id
 * @return int
 */
function quiz_fetch_attempts($equiz_id = 0) {
	global $db;

	if ($equiz_id = (int) $equiz_id) {
		$query		= "	SELECT COUNT(*) AS `total`
						FROM `event_quiz_progress`
						WHERE `equiz_id` = ".$db->qstr($equiz_id)."
						AND `proxy_id` = ".$db->qstr($_SESSION["details"]["id"])."
						AND `progress_value` <> 'inprogress'";
		$attempts	= $db->GetRow($query);
		if ($attempts) {
			return $attempts["total"];
		}
	}

	return 0;
}

/**
 * This function returns the total number of completed attempts the user
 * has made on the provided equiz_id.

 * @param int $equiz_id
 * @return int
 */
function quiz_completed_attempts($equiz_id = 0) {
	global $db;

	if ($equiz_id = (int) $equiz_id) {
		$query		= "	SELECT COUNT(*) AS `total`
						FROM `event_quiz_progress`
						WHERE `equiz_id` = ".$db->qstr($equiz_id)."
						AND `proxy_id` = ".$db->qstr($_SESSION["details"]["id"])."
						AND `progress_value` = 'complete'";
		$completed	= $db->GetRow($query);
		if ($completed) {
			return $completed["total"];
		}
	}

	return 0;
}

/**
 * This function quite simply returns the number of questions in a quiz.
 *
 * @param int $quiz_id
 * @return int
 */
function quiz_count_questions($quiz_id = 0) {
	global $db;

	if ($quiz_id = (int) $quiz_id) {
		$query	= "SELECT COUNT(*) AS `total` FROM `quiz_questions` WHERE `quiz_id` = ".$db->qstr($quiz_id);
		$result	= $db->GetRow($query);
		if ($result) {
			return $result["total"];
		}
	}

	return 0;
}

/**
 * Function takes event_id and gets the location of the elective.
 *
 * @param int $event_id
 * @return array element
 */
function clerkship_get_elective_location($event_id) {
	global $db;

	$query	= "	SELECT a.`geo_location`, a.`city`, b.`region_name` 
				FROM `".CLERKSHIP_DATABASE."`.`electives` AS a 
				LEFT JOIN `".CLERKSHIP_DATABASE."`.`regions` AS b
				ON a.`region_id` = b.`region_id` 
				WHERE a.`event_id` = ".$db->qstr($event_id);
	$result = $db->GetRow($query);
	if ($result && $result["region_name"]) {
		$result["city"] = $result["region_name"];
	}
	return $result;
}

/**
 * Function takes $proxy_id and determines the number of weeks of electives in the system.
 *
 * @param int $proxy_id
 * @return array
 */
function clerkship_get_elective_weeks($proxy_id, $event_id = 0) {
	global $db;

	$draft_amount		= 0;
	$approved_amount	= 0;
	$trash_amount		= 0;

	$query		= "	SELECT `event_start`, `event_finish`, `event_status`,  `".CLERKSHIP_DATABASE."`.`electives`.`discipline_id`, `".DATABASE_NAME."`.`global_lu_disciplines`.`discipline`
					FROM `".CLERKSHIP_DATABASE."`.`events`, `".CLERKSHIP_DATABASE."`.`event_contacts`, `".CLERKSHIP_DATABASE."`.`electives`, `".DATABASE_NAME."`.`global_lu_disciplines`
					WHERE `event_type` = 'elective'
					AND `etype_id` = ".$db->qstr($proxy_id)."
					AND `".CLERKSHIP_DATABASE."`.`events`.`event_id` != $event_id
					AND `".CLERKSHIP_DATABASE."`.`events`.`event_id` = `".CLERKSHIP_DATABASE."`.`event_contacts`.`event_id`
					AND `".CLERKSHIP_DATABASE."`.`events`.`event_id` = `".CLERKSHIP_DATABASE."`.`electives`.`event_id`
					AND `".DATABASE_NAME."`.`global_lu_disciplines`.`discipline_id` = `".CLERKSHIP_DATABASE."`.`electives`.`discipline_id`
					ORDER BY `".CLERKSHIP_DATABASE."`.`electives`.`discipline_id`";
	$results	= $db->GetAll($query);
	if ($results) {
		foreach ($results as $result) {
			$difference	= ($result["event_finish"] - $result["event_start"]) / 604800;
			$weeks		= ceil($difference);

			switch ($result["event_status"]) {
				case "published" :
					$approved_amount += $weeks;
				break;
				case "trash" :
					$trash_amount += $weeks;
				break;
				case "approval" :
				default :
					$draft_amount += $weeks;
				break;
			}
		}
	}

	return array("approval" => $draft_amount, "approved" => $approved_amount, "trash" => $trash_amount);
}

/**
 * Returns the first and last name of a contact for an event_id.
 *
 * @param int $event_id
 * @param int $proxy_id
 * @return int
 */
function clerkship_student_name($event_id = 0) {
	global $db;

	if($event_id = (int) $event_id) {
		$query	= "SELECT `firstname`, `lastname`, `role`, `user_data`.`id`
		FROM `".CLERKSHIP_DATABASE."`.`event_contacts`, `".AUTH_DATABASE."`.`user_data`, `".AUTH_DATABASE."`.`user_access`
		WHERE `event_id` = ".$db->qstr($event_id)." 
		AND `etype_id` = `".AUTH_DATABASE."`.`user_data`.`id`
		AND `".AUTH_DATABASE."`.`user_data`.`id` = `".AUTH_DATABASE."`.`user_access`.`user_id`";

		$result	= $db->GetRow($query);
		if($result) {
			return $result;
		}
	}

	return 0;
}

/**
 * Helper function ( clerkship ) return the value or a blank if zero
 *
 * @param int
 * @return int
 */

 function blank_zero($value = 0) {

    return ($value) ? $value : '';
 }

/**
 * Function takes $rotation_id and determines the number of entries, clinical presentations, mandatory cps,
 *	procedures.
 *
 * @param int $rotation
 * @param int $proxy_id
 * @return array
 */

function clerkship_get_rotation_overview($rotation_id, $proxy_id = 0) {
    global $db;

    if (!$rotation_id) {
		$rotation_id = MAX_ROTATION;
    }

    if (!$proxy_id) {
		$proxy_id = $_SESSION["details"]["id"];
    }

    // Count of entries entered in this rotation
    $query  = "	SELECT COUNT(*) FROM `".CLERKSHIP_DATABASE."`.`logbook_entries` l 
    			WHERE l.`proxy_id` = ".$db->qstr($proxy_id)." 
    			AND l.`entry_active` = 1 
    			AND	l.`rotation_id` IN 
    			(
    				SELECT e.`event_id` FROM `".CLERKSHIP_DATABASE."`.`events` as e
					WHERE e.`rotation_id` = ".$db->qstr($rotation_id)."
				)";
    $entries = $db->GetOne($query);

    // Count of objectives entered in this rotation
    $query  = "	SELECT COUNT(*) FROM `".CLERKSHIP_DATABASE."`.`logbook_entry_objectives` AS a
    			INNER JOIN `".CLERKSHIP_DATABASE."`.`logbook_entries` AS b
    			ON a.`lentry_id` = b.`lentry_id`
			    WHERE b.`proxy_id` = ".$db->qstr($proxy_id)." 
			    AND b.`entry_active` = 1 
			    AND b.`rotation_id` IN 
			    (
			    	SELECT e.`event_id` FROM `".CLERKSHIP_DATABASE."`.`events` AS e
					WHERE e.`rotation_id` = ".$db->qstr($rotation_id)."
				)";
    $objectives = $db->GetOne($query);

    // Count of Mandatory objectives entered in this rotation
    $query  = "	SELECT  DISTINCT(a.`objective_id`) FROM `".CLERKSHIP_DATABASE."`.`logbook_entry_objectives` AS a
  				INNER JOIN `".CLERKSHIP_DATABASE."`.`logbook_entries` AS b
  				ON a.`lentry_id` = b.`lentry_id`
	        	WHERE b.`proxy_id` = ".$db->qstr($proxy_id)." 
	        	AND b.`entry_active` = 1 
	        	AND b.`rotation_id` IN 
	        	(
	        		SELECT e.`event_id` FROM `".CLERKSHIP_DATABASE."`.`events` AS e
	        		WHERE e.`rotation_id` = ".$db->qstr($rotation_id)."
	        	)
				AND a.`objective_id` IN 
				(
					SELECT `objective_id` FROM `".CLERKSHIP_DATABASE."`.`logbook_mandatory_objectives`
					WHERE `rotation_id` = ".$db->qstr($rotation_id)."
				)";
    $result = $db->GetAll($query);
    $mandatories = (int) ($result) ? count($result) : 0;

    // Get count of all Mandatory clinical presentations for this rotation
    $query  = " SELECT  COUNT(*) FROM `".CLERKSHIP_DATABASE."`.`logbook_mandatory_objectives`
				WHERE `rotation_id` = ".$db->qstr($rotation_id);
    $all_mandatories = $db->GetOne($query);
 
    $query  = "	SELECT COUNT(*) FROM `".CLERKSHIP_DATABASE."`.`logbook_entry_procedures` AS a
    			INNER JOIN `".CLERKSHIP_DATABASE."`.`logbook_entries` AS b
    			ON a.`lentry_id` = b.`lentry_id`
				WHERE b.`proxy_id` = ".$db->qstr($proxy_id)." 
				AND b.`entry_active` = 1 
				AND b.`rotation_id` IN 
				(
					SELECT e.`event_id` FROM `".CLERKSHIP_DATABASE."`.`events` AS e
					WHERE e.`rotation_id` = ".$db->qstr($rotation_id)."
				)";
    $procedures = $db->GetOne($query);

    return array("entries" => $entries, "objectives" => $objectives, "mandatories" => $mandatories, "all_mandatories" => $all_mandatories, "procedures" => $procedures);
}

/**
 * Function takes rotation--or gets the current rotation--and returns the rotation of interest and rotation title.
 *
 * @param int $rotation_id
 * @param int $proxy_id
 * @return array
 */

function clerkship_get_rotation($rotation_id, $proxy_id = 0) {
    global $db;

    if (!$proxy_id) {
		$proxy_id = $_SESSION["details"]["id"];
    }

    if (!$rotation_id) {  // Get current rotation
		$query	= "	SELECT *
					FROM `".CLERKSHIP_DATABASE."`.`events` AS a
					LEFT JOIN `".CLERKSHIP_DATABASE."`.`event_contacts` AS b
					ON b.`event_id` = a.`event_id`
					LEFT JOIN `".CLERKSHIP_DATABASE."`.`regions` AS c
					ON c.`region_id` = a.`region_id`
					WHERE a.`event_finish` >= ".$db->qstr(strtotime("00:00:00", time()))."
					AND (a.`event_status` = 'published' OR a.`event_status` = 'approval')
					AND b.`econtact_type` = 'student'
					AND b.`etype_id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])."
					ORDER BY a.`event_start` ASC";
		if ($clerkship_schedule	= $db->GetAll($query)) {
			$rotation_id =  (isset($clerkship_schedule["rotation_id"]) && $clerkship_schedule["rotation_id"]) ? $clerkship_schedule["rotation_id"] : (MAX_ROTATION - 1); // Select Overview / Elective if not a mandatory rotation
		}
    }

    // Get Rotation name
    $query  = "	SELECT `rotation_title` FROM `".CLERKSHIP_DATABASE."`.`global_lu_rotations`
    			WHERE `rotation_id` = ".$db->qstr($rotation_id);
    $result = $db->GetOne($query);
    return array("id" => $rotation_id, "title" => $result);
}

/**
 * Function takes rotation and determines the total number of weeks and weeks remaining.
 *
 * @param int $rotation_id
 * @param int $proxy_id
 * @return array
 */

function clerkship_get_rotation_schedule ($rotation, $proxy_id = 0) {
    global $db;

   if (!$proxy_id) {
	$proxy_id = $_SESSION["details"]["id"];
    }

    if ($rotation && $rotation < MAX_ROTATION) {
	$query = "	SELECT ROUND(DATEDIFF(t2.f,t1.s) / 7) total, ROUND(DATEDIFF(t2.f,current_date) / 7) yet, DATEDIFF(t1.s,current_date) test1, DATEDIFF(t2.f,current_date) test2
				FROM
	    		(
					SELECT FROM_UNIXTIME(b.`event_start`) AS s
					FROM `".CLERKSHIP_DATABASE."`.`event_contacts` AS a
					INNER JOIN `".CLERKSHIP_DATABASE."`.`events` AS b 
					ON a.`event_id` = b.`event_id`
					WHERE a.`etype_id` = ".$db->qstr($proxy_id)." 
					AND b.`rotation_id` = ".$db->qstr($rotation)."
					ORDER BY b.`event_start` 
					LIMIT 1
				)  t1,
	    		(
	    			SELECT FROM_UNIXTIME(b.`event_finish`) AS f
					FROM `".CLERKSHIP_DATABASE."`.`event_contacts` AS a
					INNER JOIN `".CLERKSHIP_DATABASE."`.`events` AS b 
					ON a.`event_id` = b.`event_id`
					WHERE a.`etype_id` = ".$db->qstr($proxy_id)." 
					AND b.`rotation_id` = ".$db->qstr($rotation)."
					ORDER BY b.`event_finish` DESC 
					LIMIT 1
				) t2";
	$result = $db->GetRow($query);
	if ($result["test1"] > 0) {  // Starting in the future
	    $result["yet"] = $result["total"];
	} else if ($result["test2"] <= 0) { // Finished in the past
	    $result["yet"] = 0;
	}
	return $result;
    } else {
	return array("total" => 0, "yet" => 0);
    }
}

/**
 * Function takes an agerange index and rotation id  and returns the age range for that rotation or the default range if applicable.
 *
 * @param int $agerange_id
 * @param int $rotation_id
 * @return string
 */

function clerkship_get_agerange ($agerange_id, $rotation_id) {
    global $db;

    $query = "	SELECT `age` FROM `".CLERKSHIP_DATABASE."`.`logbook_lu_agerange`
		where `agerange_id` = ".$db->qstr($agerange_id)." and (`rotation_id` = ".$db->qstr($rotation_id)." or `rotation_id` = 0)
		order by `rotation_id` desc limit 1";
    return $db->GetOne($query);
}

function clerkship_deficiency_notifications($clerk_id, $rotation_id, $administrator = false, $completed = false, $comments = false) {
	global $AGENT_CONTACTS, $db;
	if (defined("CLERKSHIP_EMAIL_NOTIFICATIONS") && CLERKSHIP_EMAIL_NOTIFICATIONS) {
		$mail = new Zend_Mail();
		$mail->addHeader("X-Originating-IP", $_SERVER["REMOTE_ADDR"]);
		$mail->addHeader("X-Section", "Clerkship Notify System", true);
		$mail->clearFrom();
		$mail->clearSubject();
		$mail->setFrom($AGENT_CONTACTS["agent-notifications"]["email"], APPLICATION_NAME." Clerkship System");
		$mail->setSubject("Clerkship Logbook Deficiency Notification");
		$NOTIFICATION_MESSAGE	= array();
						
		$query	 				= "	SELECT CONCAT_WS(' ', `firstname`, `lastname`) as `fullname`, `email`, `id`
									FROM `".AUTH_DATABASE."`.`user_data`
									WHERE `id` = ".$db->quote($clerk_id);
		$clerk					= $db->GetRow($query);
		
		$query 					= "	SELECT a.`rotation_title`, c.`email`, CONCAT_WS(' ', c.`firstname`, c.`lastname`) as `fullname`, b.`pcoord_id`
									FROM `".CLERKSHIP_DATABASE."`.`global_lu_rotations` AS a
									LEFT JOIN `courses` AS b
									ON a.`course_id` = b.`course_id`
									LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS c
									ON c.`id` = ".($administrator ? "b.`pcoord_id`" : $db->qstr($clerk_id))."
									WHERE a.`rotation_id` = ".$db->quote($rotation_id)."
									AND b.`course_active` = '1'";
		$rotation				= $db->GetRow($query);
		
		if ($rotation) {
												
			$search	= array(
								"%CLERK_FULLNAME%",
								"%ROTATION_TITLE%",
								"%ADMIN_COMMENTS%",
								"%PROFILE_URL%",
								"%DEFICIENCY_PLAN_URL%",
								"%CLERK_MANAGEMENT_URL%",
								"%APPLICATION_NAME%",
								"%ENTRADA_URL%"
							);
			$replace	= array(
								$clerk["fullname"],
								$rotation["rotation_title"],
								(!$administrator && $comments ? "However, the administrator left comments which you are meant to review, these can be found on the deficiency plan page, which is linked below." : ""),
								ENTRADA_URL."/people?id=".$clerk_id,
								ENTRADA_URL."/clerkship/logbook?section=deficiency-plan&rotation=".$rotation_id.($administrator ? "&id=".$clerk_id : ""),
								ENTRADA_URL."/clerkship".($administrator ? "?section=clerk&ids=".$clerk_id : "/logbook"),
								APPLICATION_NAME,
								ENTRADA_URL
							);
			if ($administrator) {
				$NOTIFICATION_MESSAGE["textbody"] = file_get_contents(ENTRADA_ABSOLUTE."/templates/".DEFAULT_TEMPLATE."/email/clerk-deficiency-plan-admin-notification.txt");					
			} else {
				$NOTIFICATION_MESSAGE["textbody"] = file_get_contents(ENTRADA_ABSOLUTE."/templates/".DEFAULT_TEMPLATE."/email/clerk-deficiency-plan-reviewed-".($completed ? "complete" : "incomplete")."-notification.txt");
			}
			$mail->setBodyText(clean_input(str_replace($search, $replace, $NOTIFICATION_MESSAGE["textbody"]), array("postclean")));
			
			if (($rotation["pcoord_id"] && $administrator) || !$administrator) {
				if ($administrator) {
					$notice_message = "The clerk [%CLERK_FULLNAME%] has completed a plan to attain deficiencies for a rotation [%ROTATION_TITLE%] after the allotted time. Please review their <a href=\"%DEFICIENCY_PLAN_URL%\">Deficiency Plan</a> now to ensure it meets all requirements.";
				} else {
					if ($completed) {
						$notice_message = "An administrator has reviewed your rotation [%ROTATION_TITLE%] deficiency plan. Your plan was accepted to attain all deficiencies in the timeline which you outlined".($comments ? ", but you are asked to review the comments the administrator left on your <a href=\"%DEFICIENCY_PLAN_URL%\">Deficiency Plan</a>" : "").".";
					} else {
						$notice_message = "An administrator has reviewed your rotation [%ROTATION_TITLE%] deficiency plan. Unfortunately, your plan was was not accepted. More information can be found in the comments from the administrator on the <a href=\"%DEFICIENCY_PLAN_URL%\">Deficiency Plan</a> page, which we ask you to review before modifying and resubmitting your plan.";
					}
				}
				$NOTICE = Array(
									"target" => "proxy_id:".($administrator ? $rotation["pcoord_id"] : $clerk_id),
									"notice_summary" => clean_input(str_replace($search, $replace, $notice_message), array("postclean")),
									"display_from" => time(),
									"display_until" => strtotime("+2 weeks"),
									"updated_date" => time(),
									"updated_by" => 3499,
									"organisation_id" => 1
								);
				if($db->AutoExecute("notices", $NOTICE, "INSERT")) {
					if($NOTICE_ID = $db->Insert_Id()) {
						application_log("success", "Successfully added notice ID [".$NOTICE_ID."]");
					} else {
						application_log("error", "Unable to fetch the newly inserted notice identifier for this notice.");
					}
				} else {
					application_log("error", "Unable to insert new notice into the system. Database said: ".$db->ErrorMsg());
				}
				$sent = true;
				$mail->clearRecipients();
				if (strlen($rotation['email'])) {
					$mail->addTo($rotation['email'], $rotation['fullname']);
					try {
						$mail->send();
					}
					catch (Exception $e) {
						$sent = false;
					}
					if($sent && $administrator) {
						application_log("success", "Sent overdue logging notification to Program Coordinator ID [".$rotation["pcoord_id"]."].");
					} elseif ($administrator) {
						application_log("error", "Unable to send overdue logging notification to Program Coordinator ID [".$rotation["pcoord_id"]."].");
					} elseif (!$administrator && $sent) {
						application_log("success", "Sent overdue logging notification to Clerk ID [".$clerk_id."].");
					} else {
						application_log("error", "Unable to send overdue logging notification to Clerk ID [".$clerk_id."].");
					}
					$NOTICE_HISTORY = Array(
											"clerk_id" => $clerk_id,
											"proxy_id" => ($administrator ? $rotation["pcoord_id"] : $clerk_id),
											"rotation_id" => $rotation_id,
											"notified_date" => time()
											);
					if($db->AutoExecute(CLERKSHIP_DATABASE.".logbook_notification_history", $NOTICE_HISTORY, "INSERT")) {
						if($HISTORY_ID = $db->Insert_Id()) {
							application_log("success", "Successfully added notification history ID [".$HISTORY_ID."]");
						} else {
							application_log("error", "Unable to fetch the newly inserted notification history identifier for this notice.");
						}
					} else {
						application_log("error", "Unable to insert new notification history record into the system. Database said: ".$db->ErrorMsg());
					}
				}
			}
		}
	}
}

function courses_subnavigation($course_details) {
	global $ENTRADA_ACL;
	echo "<div class=\"no-printing\">\n";
	echo "	<div style=\"float: right\">\n";
	if($ENTRADA_ACL->amIAllowed(new CourseResource($course_details["course_id"], $course_details["organisation_id"]), "update")) {
		echo "<a href=\"".ENTRADA_URL."/admin/courses?".replace_query(array("section" => "edit", "id" => $course_details["course_id"], "step" => false))."\"><img src=\"".ENTRADA_URL."/images/event-details.gif\" width=\"16\" height=\"16\" alt=\"Edit course details\" title=\"Edit course details\" border=\"0\" style=\"vertical-align: middle; margin-bottom: 2px;\" /></a> <a href=\"".ENTRADA_URL."/admin/courses?".replace_query(array("section" => "edit", "id" => $course_details["course_id"], "step" => false))."\" style=\"font-size: 10px; margin-right: 8px\">Edit course details</a>\n";
	}
	if($ENTRADA_ACL->amIAllowed(new CourseContentResource($course_details["course_id"], $course_details["organisation_id"]), "read")) {
		echo "<a href=\"".ENTRADA_URL."/admin/courses?".replace_query(array("section" => "content", "id" => $course_details["course_id"], "step" => false))."\"><img src=\"".ENTRADA_URL."/images/event-contents.gif\" width=\"16\" height=\"16\" alt=\"Manage course content\" title=\"Manage course content\" border=\"0\" style=\"vertical-align: middle; margin-bottom: 2px;\" /></a> <a href=\"".ENTRADA_URL."/admin/courses?".replace_query(array("section" => "content", "id" => $course_details["course_id"], "step" => false))."\" style=\"font-size: 10px; margin-right: 8px;\">Manage course content</a>\n";
	}
	if($ENTRADA_ACL->amIAllowed(new GradebookResource($course_details["course_id"], $course_details["organisation_id"]), "read")) {
		echo "<a href=\"".ENTRADA_URL."/admin/gradebook?section=view&amp;id=".$course_details["course_id"]."\" style=\"font-size: 10px;\"><img src=\"".ENTRADA_URL."/images/book_go.png\" width=\"16\" height=\"16\" alt=\"Manage course content\" title=\"Manage course content\" border=\"0\" style=\"vertical-align: middle\" />&nbsp;Manage course gradebook</a>";				
	}
	echo "	</div>\n";
	echo "</div>\n";
	echo "<br/>";
}

function courses_fetch_objectives($course_ids, $parent_id = 1, $objectives = false, $objective_ids = false, $event_id = 0, $fetch_all_text = false) {
	global $db;
	
	if (!$objectives && is_array($course_ids)) {
		$objectives = array(	
							"used" => array(), 
							"unused" => array(), 
							"objectives" => array(), 
							"used_ids" => array(), 
							"primary_ids" => array(), 
							"secondary_ids" => array(), 
							"tertiary_ids" => array());
		$escaped_course_ids = "";
		for ($i = 0; $i < (count($course_ids) - 1); $i++) {
			$escaped_course_ids .= $db->qstr($course_ids[$i]).",";
		}
		$escaped_course_ids .= $db->qstr($course_ids[(count($course_ids) - 1)]);
		$query		= "	SELECT a.`objective_id`, a.`importance`, a.`objective_details`, a.`course_id`, b.`objective_parent`, b.`objective_order`
						FROM `course_objectives` AS a
						JOIN `global_lu_objectives` AS b
						ON a.`objective_id` = b.`objective_id`
						WHERE ".($fetch_all_text ? "" : "`importance` != '0'
						AND ")."`course_id` IN (".$escaped_course_ids.")
						AND a.`objective_type` = 'course'
						UNION
						SELECT b.`objective_id`, a.`importance`, a.`objective_details`, a.`course_id`, b.`objective_parent`, b.`objective_order`
						FROM `course_objectives` AS a
						JOIN `global_lu_objectives` AS b
						ON a.`objective_id` = b.`objective_parent`
						AND `course_id` IN (".$escaped_course_ids.")
						WHERE ".($fetch_all_text ? "" : "`importance` != '0'
						AND a.`objective_type` = 'course'
						AND ")."a.`objective_type` = 'course'
						AND b.`objective_id` NOT IN (
							SELECT a.`objective_id`
							FROM `course_objectives` AS a
							JOIN `global_lu_objectives` AS b
							ON a.`objective_id` = b.`objective_id`
							WHERE ".($fetch_all_text ? "" : "`importance` != '0'
							AND ")."`course_id` IN (".$escaped_course_ids.")
							AND `objective_type` = 'course'
						)
						ORDER BY `objective_parent`, `objective_order` ASC";
		$results	= $db->GetAll($query);
		if($results && !is_array($objective_ids)) {
			foreach($results as $result) {
				if ($result["importance"] == 1) {
					$objectives["primary_ids"][$result["objective_id"]] = $result["objective_id"];
				} elseif ($result["importance"] == 2) {
					$objectives["secondary_ids"][$result["objective_id"]] = $result["objective_id"];
				} elseif ($result["importance"] == 3) {
					$objectives["tertiary_ids"][$result["objective_id"]] = $result["objective_id"];
				}
				$objectives["used_ids"][$result["objective_id"]] = $result["objective_id"];
				$objectives["objectives"][$result["objective_id"]] = array();
				$objectives["objectives"][$result["objective_id"]]["objective_details"] = $result["objective_details"];
			}
		}
		if (is_array($objective_ids)) {
			if (isset($objective_ids["primary"]) && is_array($objective_ids["primary"])) {
				foreach ($objective_ids["primary"] as $objective_id) {
					if (array_search($objective_id, $objectives["used_ids"]) === false) {
						$objectives["primary_ids"][$objective_id] = $objective_id;
						$objectives["used_ids"][$objective_id] = $objective_id;
					}
				}
			}
			if (isset($objective_ids["secondary"]) && is_array($objective_ids["secondary"])) {
				foreach ($objective_ids["secondary"] as $objective_id) {
					if (array_search($objective_id, $objectives["used_ids"]) === false) {
						$objectives["secondary_ids"][$objective_id] = $objective_id;
						$objectives["used_ids"][$objective_id] = $objective_id;
					}
				}
			}
			if (isset($objective_ids["tertiary"]) && is_array($objective_ids["tertiary"])) {
				foreach ($objective_ids["tertiary"] as $objective_id) {
					if (array_search($objective_id, $objectives["used_ids"]) === false) {
						$objectives["tertiary_ids"][$objective_id] = $objective_id;
						$objectives["used_ids"][$objective_id] = $objective_id;
					}
				}
			}
		}
	}
	
	$query	= "	SELECT * FROM `global_lu_objectives` 
				WHERE `objective_parent` = ".$db->qstr($parent_id)."
				AND `objective_active` = '1'
				ORDER BY `objective_order` ASC";
	
	$results	= $db->GetAll($query);
	if($results) {
		foreach($results as $result) {
			if ($parent_id == 1) {
				$objectives["objectives"][$result["objective_id"]]["objective_children"] = 0;
				$objectives["objectives"][$result["objective_id"]]["children_primary"] = 0;
				$objectives["objectives"][$result["objective_id"]]["children_secondary"] = 0;
				$objectives["objectives"][$result["objective_id"]]["children_tertiary"] = 0;
				$objectives["objectives"][$result["objective_id"]]["name"] = $result["objective_name"];
				$objectives["objectives"][$result["objective_id"]]["description"] = (isset($objectives["objectives"][$result["objective_id"]]["objective_details"]) && $objectives["objectives"][$result["objective_id"]]["objective_details"] ? $objectives["objectives"][$result["objective_id"]]["objective_details"] : $result["objective_description"]);
				$objectives["objectives"][$result["objective_id"]]["parent"] = 1;
				$objectives["objectives"][$result["objective_id"]]["parent_ids"] = array();
			} else {
				$objectives["objectives"][$result["objective_id"]]["objective_children"] = 0;
				$objectives["objectives"][$result["objective_id"]]["name"] = $result["objective_name"];
				$objectives["objectives"][$result["objective_id"]]["description"] = (isset($objectives["objectives"][$result["objective_id"]]["objective_details"]) && $objectives["objectives"][$result["objective_id"]]["objective_details"] ? $objectives["objectives"][$result["objective_id"]]["objective_details"] : $result["objective_description"]);
				$objectives["objectives"][$result["objective_id"]]["parent"] = $parent_id;
				$objectives["objectives"][$result["objective_id"]]["parent_ids"] = $objectives["objectives"][$parent_id]["parent_ids"];
				$objectives["objectives"][$result["objective_id"]]["parent_ids"][] = $parent_id;
				if (is_array($objectives["primary_ids"]) && array_search($result["objective_id"], $objectives["primary_ids"]) !== false) {
					$objectives["objectives"][$result["objective_id"]]["primary"] = true;
				} else {
					$objectives["objectives"][$result["objective_id"]]["primary"] = false;
				}
				if (is_array($objectives["secondary_ids"]) && array_search($result["objective_id"], $objectives["secondary_ids"]) !== false) {
					$objectives["objectives"][$result["objective_id"]]["secondary"] = true;
				} else {
					$objectives["objectives"][$result["objective_id"]]["secondary"] = false;
				}
				if (is_array($objectives["tertiary_ids"]) && array_search($result["objective_id"], $objectives["tertiary_ids"]) !== false) {
					$objectives["objectives"][$result["objective_id"]]["tertiary"] = true;
				} else {
					$objectives["objectives"][$result["objective_id"]]["tertiary"] = false;
				}
				foreach ($objectives["objectives"][$result["objective_id"]]["parent_ids"] as $parent_id) {
					if ($parent_id != 1 && ($objectives["objectives"][$result["objective_id"]]["primary"] || $objectives["objectives"][$result["objective_id"]]["secondary"]) || $objectives["objectives"][$result["objective_id"]]["tertiary"]) {
						$objectives["objectives"][$parent_id]["objective_children"]++;
					}
				}
			}
			$objectives = courses_fetch_objectives($course_ids, $result["objective_id"], $objectives);
		}
	}
	if ($parent_id == 1) {
		foreach ($objectives["primary_ids"] as $primary_id) {
			if (is_array($objectives["objectives"][$primary_id]["parent_ids"])) {
				foreach ($objectives["objectives"][$primary_id]["parent_ids"] as $parent_id) {
					if (array_search($parent_id, $objectives["used_ids"]) !== false) {
						unset($objectives["used_ids"][$primary_id]);
						unset($objectives["primary_ids"][$primary_id]);
						$objectives["objectives"][$primary_id]["primary"] = false;
					}
				}
			}
		}
		foreach ($objectives["secondary_ids"] as $secondary_id) {
			if (is_array($objectives["objectives"][$secondary_id]["parent_ids"])) {
				foreach ($objectives["objectives"][$secondary_id]["parent_ids"] as $parent_id) {
					if (array_search($parent_id, $objectives["used_ids"]) !== false) {
						unset($objectives["used_ids"][$secondary_id]);
						unset($objectives["secondary_ids"][$secondary_id]);
						$objectives["objectives"][$secondary_id]["secondary"] = false;
					}
				}
			}
		}
		foreach ($objectives["tertiary_ids"] as $tertiary_id) {
			if (is_array($objectives["objectives"][$tertiary_id]["parent_ids"])) {
				foreach ($objectives["objectives"][$tertiary_id]["parent_ids"] as $parent_id) {
					if (array_search($parent_id, $objectives["used_ids"]) !== false) {
						unset($objectives["used_ids"][$tertiary_id]);
						unset($objectives["tertiary_ids"][$tertiary_id]);
						$objectives["objectives"][$tertiary_id]["tertiary"] = false;
					}
				}
			}
		}
	}
	if ($event_id) {
		foreach ($objectives["objectives"] as $objective_id => $objective) {
			if (isset($event_objectives_string) && $event_objectives_string) {
				$event_objectives_string .= ", ".$db->qstr($objective_id);
			} else {
				$event_objectives_string = $db->qstr($objective_id);
			}
		}
		$event_objectives = $db->GetAll("	SELECT a.* FROM `event_objectives` AS a
											JOIN `global_lu_objectives` AS b
											ON a.`objective_id` = b.`objective_id`
											WHERE a.`event_id` = ".$db->qstr($event_id)."
											AND a.`objective_type` = 'course'
											AND a.`objective_id` IN (".$event_objectives_string.")
											ORDER BY b.`objective_order` ASC");
		if ($event_objectives) {
			foreach ($event_objectives as $objective) {
				$objectives["objectives"][$objective["objective_id"]]["event_objective_details"] = $objective["objective_details"];
				$objectives["objectives"][$objective["objective_id"]]["event_objective"] = true;
			}
		}
	}
	
	return $objectives;
}

function course_objectives_in_list($objectives, $parent_id, $edit_importance = false, $parent_active = false, $importance = 1, $hierarchical = true, $selected_only = false) {
	$output = "";

	if ((is_array($objectives)) && (count($objectives))) {
		if (((isset($objectives[$parent_id]) && count($objectives[$parent_id]["parent_ids"]) > 1) || $hierarchical) && count($objectives[$parent_id]["parent_ids"]) < 3) {
//			$output .= "\n<ul class=\"objective-list\" id=\"objective_".$parent_id."_list\"".((($parent_id == 1) && (count($objectives[$parent_id]["parent_ids"]) > 2) || !$hierarchical) ? " style=\"padding-left: 0; margin-top: 0\"" : " style=\"padding-left: 15px;\"")." >";
			$output .= "\n<ul class=\"objective-list\" id=\"objective_".$parent_id."_list\"".((($parent_id == 1) || !$hierarchical) ? " style=\"padding-left: 0; margin-top: 0\"" : "").">\n";
		}
		foreach ($objectives as $objective_id => $objective) {
			if (($objective["parent"] == $parent_id) && (($objective["objective_children"]) || (isset($objective["primary"]) && $objective["primary"]) || (isset($objective["secondary"]) && $objective["secondary"]) || (isset($objective["tertiary"]) && $objective["tertiary"]) || ($parent_active && count($objective["parent_ids"]) > 2 && !$selected_only) || ($selected_only && isset($objective["event_objective"]) && $objective["event_objective"]))) {

				$importance = ((isset($objective["primary"]) && $objective["primary"]) ? 1 : ((isset($objective["secondary"]) && $objective["secondary"]) ? 2 : ((isset($objective["tertiary"]) && $objective["tertiary"]) ? 3 : $importance)));
				if ((count($objective["parent_ids"]) > 2) || $hierarchical) {
					$output .= "<li".((($parent_active) || ($objective["primary"]) || $objective["secondary"] || $objective["tertiary"]) && (count($objective["parent_ids"]) > 2) ? " class=\"".($importance == 1 ? "primary" : ($importance == 2 ? "secondary" : "tertiary"))."\"" : "")." id=\"objective_".$objective_id."_row\">\n";
				}
				if (($edit_importance) && (($objective["primary"]) || ($objective["secondary"]) || ($objective["tertiary"]))) {
					if ((count($objective["parent_ids"]) > 2) || $hierarchical) {
						$output .= "<select onchange=\"javascript: moveObjective('".$objective_id."', this.value);\" style=\"float: right; margin: 5px\">\n";
						$output .= "	<option value=\"primary\"".(($objective["primary"]) ? " selected=\"selected\"" : "").">Primary</option>\n";
						$output .= "	<option value=\"secondary\"".(($objective["secondary"]) ? " selected=\"selected\"" : "").">Secondary</option>\n";
						$output .= "	<option value=\"tertiary\"".(($objective["tertiary"]) ? " selected=\"selected\"" : "").">Tertiary</option>\n";
						$output .= "</select>";
					}
				}
				if ((count($objective["parent_ids"]) > 2) || $hierarchical) {
					$output .= "	<h3 id=\"objective_".$objective_id."\">".$objective["name"]."</h3>\n";
					$output .= "	<div>".(isset($objective["objective_details"]) && $objective["objective_details"] ? $objective["objective_details"] : $objective["description"]);
					if (isset($objective["event_objective_details"]) && $objective["event_objective_details"]) {
						$output .= "		<br/><br/><em>".$objective["event_objective_details"]."</em>";
					}
					$output .= "	</div>";
					$output .= "</li>";
				}
				$output .= course_objectives_in_list($objectives, $objective_id, $edit_importance, (((isset($objective["primary"]) && $objective["primary"]) || (isset($objective["secondary"]) && $objective["secondary"]) || (isset($objective["tertiary"]) && $objective["tertiary"])) ? true : false), $importance, $hierarchical, $selected_only);
			}
		}
		if (((isset($objectives[$parent_id]) && count($objectives[$parent_id]["parent_ids"]) > 1) || $hierarchical) && count($objectives[$parent_id]["parent_ids"]) < 3) {
			$output .= "</ul>";
		}
	}
	return $output;
}

/**
 * Function used by public events and admin events index to setup and process the selected sort ordering
 * and pagination.
 */
function events_process_sorting() {
	/**
	 * Update requested length of time to display.
	 * Valid: day, week, month, year
	 */
	if (isset($_GET["dtype"])) {
		if (in_array(trim($_GET["dtype"]), array("day", "week", "month", "year"))) {
			$_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"] = trim($_GET["dtype"]);
		}

		$_SERVER["QUERY_STRING"] = replace_query(array("dtype" => false));
	} else {
		if (!isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"])) {
			$_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"] = "week";
		}
	}

	/**
	 * Update requested timestamp to display.
	 * Valid: Unix timestamp
	 */
	if (isset($_GET["dstamp"])) {
		$integer = (int) trim($_GET["dstamp"]);
		if ($integer) {
			$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dstamp"] = $integer;
		}

		$_SERVER["QUERY_STRING"] = replace_query(array("dstamp" => false));
	} else {
		if (!isset($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dstamp"])) {
			$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dstamp"] = time();
		}
	}

	/**
	 * Update requested column to sort by.
	 * Valid: date, teacher, title, phase
	 */
	if (isset($_GET["sb"])) {
		if (in_array(trim($_GET["sb"]), array("date" , "teacher", "title", "phase"))) {
			$_SESSION[APPLICATION_IDENTIFIER]["events"]["sb"]	= trim($_GET["sb"]);
		}

		$_SERVER["QUERY_STRING"] = replace_query(array("sb" => false));
	} else {
		if (!isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["sb"])) {
			$_SESSION[APPLICATION_IDENTIFIER]["events"]["sb"] = "date";
		}
	}

	/**
	 * Update requested order to sort by.
	 * Valid: asc, desc
	 */
	if (isset($_GET["so"])) {
		$_SESSION[APPLICATION_IDENTIFIER]["events"]["so"] = ((strtolower($_GET["so"]) == "desc") ? "desc" : "asc");

		$_SERVER["QUERY_STRING"] = replace_query(array("so" => false));
	} else {
		if (!isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["so"])) {
			$_SESSION[APPLICATION_IDENTIFIER]["events"]["so"] = "asc";
		}
	}

	/**
	 * Update requsted number of rows per page.
	 * Valid: any integer really.
	 */
	if ((isset($_GET["pp"])) && ((int) trim($_GET["pp"]))) {
		$integer = (int) trim($_GET["pp"]);

		if (($integer > 0) && ($integer <= 250)) {
			$_SESSION[APPLICATION_IDENTIFIER]["events"]["pp"] = $integer;
		}

		$_SERVER["QUERY_STRING"] = replace_query(array("pp" => false));
	} else {
		if (!isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["pp"])) {
			$_SESSION[APPLICATION_IDENTIFIER]["events"]["pp"] = DEFAULT_ROWS_PER_PAGE;
		}
	}
}

/**
 * Function used by public events and admin events index to output the HTML for both the filter
 * controls and current filter status (Showing Events That Include:) box.
 */
function events_output_filter_controls($module_type = "") {
	global $db, $ENTRADA_ACL, $ORGANISATION_ID;

	if (!isset($ORGANISATION_ID) || !$ORGANISATION_ID) {
		if (isset($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["events"]["organisation_id"]) && $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["events"]["organisation_id"]) {
			$ORGANISATION_ID = $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["events"]["organisation_id"];
		} else {
			$ORGANISATION_ID = $_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["organisation_id"];
			$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["events"]["organisation_id"] = $ORGANISATION_ID;
		}
	}

	/**
	 * Determine whether or not this is being called from the admin section.
	 */
	if ($module_type == "admin") {
		$module_type = "/admin";
	} else {
		$module_type = "";
	}
	?>
	<table id="filterList" style="clear: both; width: 100%" cellspacing="0" cellpadding="0" border="0" summary="Event Filters">
		<tr>
			<td style="width: 53%; vertical-align: top">
				<form action="<?php echo ENTRADA_URL.$module_type; ?>/events" method="get" id="filter_edit" name="filter_edit" style="position: relative;">
				<input type="hidden" name="action" value="filter_edit" />
				<input type="hidden" id="filter_edit_type" name="filter_type" value="" />
				<input type="hidden" id="multifilter" name="filter" value="" />
				<label for="filter_select" class="content-subheading" style="vertical-align: middle">Apply Filter:</label>
				<select id="filter_select" onchange="showMultiSelect();" style="width: 184px; vertical-align: middle">
					<option>Select Filter</option>
					<option value="teacher">Teacher Filters</option>
					<option value="student">Student Filters</option>
					<option value="grad">Graduating Year Filters</option>
					<option value="course">Course Filters</option>
					<option value="phase">Phase / Term Filters</option>
					<option value="eventtype">Event Type Filters</option>
					<option value="clinical_presentation">Clinical Presentation Filters</option>
				</select>
				<?php

				$query = "SELECT `organisation_id`,`organisation_title` FROM `".AUTH_DATABASE."`.`organisations` ORDER BY `organisation_title` ASC";
				$organisation_results = $db->CacheGetAll(LONG_CACHE_TIMEOUT, $query);
				$organisation_ids_string = "";
				if ($organisation_results) {
					$organisations = array();
					foreach ($organisation_results as $result) {
						if($ENTRADA_ACL->amIAllowed("resourceorganisation".$result["organisation_id"], "read")) {
							if (!$organisation_ids_string) {
								$organisation_ids_string = $db->qstr($result["organisation_id"]);
							} else {
								$organisation_ids_string .= ", ".$db->qstr($result["organisation_id"]);
							}
							if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["organisation"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["organisation"]) && (in_array($result["organisation_id"], $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["organisation"]))) {
								$checked = 'checked="checked"';
							} else {
								$checked = '';
							}
							$organisations[$result["organisation_id"]] = array('text' => $result["organisation_title"], 'value' => 'organisation_'.$result["organisation_id"], 'checked' => $checked);
							$organisation_categories[$result["organisation_id"]] = array('text' => $result["organisation_title"], 'value' => 'organisation_'.$result["organisation_id"], 'category'=>true);
						}
					}
				}
				if (!$organisation_ids_string) {
					$organisation_ids_string = $db->qstr($ORGANISATION_ID);
				}

				// Get the possible teacher filters
				$query = "	SELECT a.`id` AS `proxy_id`, a.`organisation_id`, CONCAT_WS(', ', a.`lastname`, a.`firstname`) AS `fullname`
							FROM `".AUTH_DATABASE."`.`user_data` AS a
							LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS b
							ON b.`user_id` = a.`id`
							LEFT JOIN `event_contacts` AS c
							ON c.`proxy_id` = a.`id`
							WHERE b.`app_id` IN (".AUTH_APP_IDS_STRING.")
							AND a.`organisation_id` IN (".$organisation_ids_string.")
							AND (b.`group` = 'faculty' OR (b.`group` = 'resident' AND b.`role` = 'lecturer'))
							AND c.`econtact_id` IS NOT NULL
							GROUP BY a.`id`
							ORDER BY `fullname` ASC";
				$teacher_results = $db->CacheGetAll(LONG_CACHE_TIMEOUT, $query);
				if ($teacher_results) {
					$teachers = $organisation_categories;
					foreach ($teacher_results as $r) {
						if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["teacher"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["teacher"]) && (in_array($r['proxy_id'], $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]['teacher']))) {
							$checked = 'checked="checked"';
						} else {
							$checked = '';
						}

						$teachers[$r["organisation_id"]]['options'][] = array('text' => $r['fullname'], 'value' => 'teacher_'.$r['proxy_id'], 'checked' => $checked);
					}
					echo lp_multiple_select_popup('teacher', $teachers, array('title'=>'Select Teachers:', 'submit_text'=>'Apply', 'cancel'=>true, 'submit'=>true));
				}

				// Get the possible Student filters
				$query = "	SELECT a.`id` AS `proxy_id`, a.`organisation_id`, b.`role`, CONCAT_WS(', ', a.`lastname`, a.`firstname`) AS `fullname`
							FROM `".AUTH_DATABASE."`.`user_data` AS a
							LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS b
							ON a.`id` = b.`user_id`
							WHERE b.`app_id` IN (".AUTH_APP_IDS_STRING.")
							AND a.`organisation_id` = ".$db->qstr($ORGANISATION_ID)."
							AND b.`account_active` = 'true'
							AND (b.`access_starts` = '0' OR b.`access_starts` <= ".$db->qstr(time()).")
							AND (b.`access_expires` = '0' OR b.`access_expires` > ".$db->qstr(time()).")
							AND b.`group` = 'student'
							AND b.`role` >= ".$db->qstr((fetch_first_year() - 4)).
							(($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"] == "student") ? " AND a.`id` = ".$db->qstr($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]) : "")."
							GROUP BY a.`id`
							ORDER BY b.`role` DESC, a.`lastname` ASC, a.`firstname` ASC";
				$student_results = $db->CacheGetAll(LONG_CACHE_TIMEOUT, $query);
				if ($student_results) {
					$students = $organisation_categories;
					foreach ($student_results as $r) {
						if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["student"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["student"]) && (in_array($r['proxy_id'], $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["student"]))) {
							$checked = 'checked="checked"';
						} else {
							$checked = '';
						}
						$students[$r["organisation_id"]]['options'][] = array('text' => $r['fullname'], 'value' => 'student_'.$r['proxy_id'], 'checked' => $checked);
					}

					echo lp_multiple_select_popup('student', $students, array('title'=>'Select Students:', 'submit_text'=>'Apply', 'cancel'=>true, 'submit'=>true));
				}

				// Get the possible courses filters
				$query = "	SELECT `course_id`, `course_name` 
							FROM `courses` 
							WHERE `organisation_id` = ".$db->qstr($ORGANISATION_ID)."
							ORDER BY `course_name` ASC";
				$courses_results = $db->CacheGetAll(LONG_CACHE_TIMEOUT, $query);
				if ($courses_results) {
					$courses = array();
					foreach ($courses_results as $c) {
						if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["course"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["course"]) && (in_array($c['course_id'], $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["course"]))) {
							$checked = 'checked="checked"';
						} else {
							$checked = '';
						}

						$courses[] = array('text' => $c['course_name'], 'value' => 'course_'.$c['course_id'], 'checked' => $checked);
					}

					echo lp_multiple_select_popup('course', $courses, array('title'=>'Select Courses:', 'submit_text'=>'Apply', 'cancel'=>true, 'submit'=>true));
				}

				// Get the possible event type filters
				$query = "SELECT `eventtype_id`, `eventtype_title` FROM `events_lu_eventtypes` WHERE `eventtype_active` = '1' ORDER BY `eventtype_order` ASC";
				$eventtype_results = $db->CacheGetAll(LONG_CACHE_TIMEOUT, $query);
				if ($eventtype_results) {
					$eventtypes = array();
					foreach ($eventtype_results as $result) {
						if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["eventtype"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["eventtype"]) && (in_array($result["eventtype_id"], $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["eventtype"]))) {
							$checked = 'checked="checked"';
						} else {
							$checked = '';
						}
						$eventtypes[] = array('text' => $result["eventtype_title"], 'value' => 'eventtype_'.$result["eventtype_id"], 'checked' => $checked);
					}

					echo lp_multiple_select_popup('eventtype', $eventtypes, array('title'=>'Select Event Types:', 'submit_text'=>'Apply', 'cancel'=>true, 'submit'=>true));
				}

				$syear		= (date("Y", time()) - 1);
				$eyear		= (date("Y", time()) + 4);
				$gradyears = array();
				for ($year = $syear; $year <= $eyear; $year++) {
					if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["grad"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["grad"]) && (in_array($year, $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["grad"]))) {
						$checked = 'checked="checked"';
					} else {
						$checked = '';
					}
					$gradyears[] = array('text' => "Graduating in $year", 'value' => "grad_".$year, 'checked' => $checked);
				}

				echo lp_multiple_select_popup('grad', $gradyears, array('title'=>'Select Gradutating Years:', 'submit_text'=>'Apply', 'cancel'=>true, 'submit'=>true));

				$phases = array(
					array('text'=>'Term 1', 'value'=>'phase_1', 'checked'=>''),
					array('text'=>'Term 2', 'value'=>'phase_2', 'checked'=>''),
					array('text'=>'Term 3', 'value'=>'phase_t3', 'checked'=>''),
					array('text'=>'Phase 2A', 'value'=>'phase_2a', 'checked'=>''),
					array('text'=>'Phase 2B', 'value'=>'phase_2b', 'checked'=>''),
					array('text'=>'Phase 2C', 'value'=>'phase_2c', 'checked'=>''),
					array('text'=>'Phase 2E', 'value'=>'phase_2e', 'checked'=>''),
					array('text'=>'Phase 3', 'value'=>'phase_3', 'checked'=>'')
				);

				for ($i = 0; $i < 6; $i++) {
					if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["phase"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["phase"])) {
						$pieces = explode('_', $phases[$i]['value']);
						if (in_array($pieces[1], $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]['phase'])) {
							$phases[$i]['checked'] = 'checked="checked"';
						}
					}
				}

				echo lp_multiple_select_popup('phase', $phases, array('title'=>'Select Phases / Terms:', 'submit_text'=>'Apply', 'cancel'=>true, 'submit'=>true));
				
				$clinical_presentations = fetch_mcc_objectives();
				foreach ($clinical_presentations as &$clinical_presentation) {
					$clinical_presentation["value"] = "objective_".$clinical_presentation["objective_id"];
					$clinical_presentation["text"] = $clinical_presentation["objective_name"];
					$clinical_presentation["checked"] = "";
					if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["clinical_presentations"]) && is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["clinical_presentations"])) {						
						if (in_array($clinical_presentation["value"], $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]["clinical_presentations"])) {
							$clinical_presentation["checked"] = "checked=\"checked\"";
						}
					}
				}

				echo lp_multiple_select_popup('clinical_presentation', $clinical_presentations, array('title'=>'Select Clinical Presentations:', 'submit_text'=>'Apply', 'cancel'=>true, 'submit'=>true));
				?>
				</form>
				<script type="text/javascript">
				var multiselect = [];
				var id;
				function showMultiSelect() {
					$$('select_multiple_container').invoke('hide');
					id = $F('filter_select');
					if (multiselect[id]) {
						multiselect[id].container.show();
					} else {
						if ($(id+'_options')) {
							$('filter_edit_type').value = id;
							$(id+'_options').addClassName('multiselect-processed');
							multiselect[id] = new Control.SelectMultiple('multifilter',id+'_options',{
								checkboxSelector: 'table.select_multiple_table tr td input[type=checkbox]',
								nameSelector: 'table.select_multiple_table tr td.select_multiple_name label',
								filter: id+'_select_filter',
								resize: id+'_scroll',
								afterCheck: function(element) {
									var tr = $(element.parentNode.parentNode);
									tr.removeClassName('selected');
									if (element.checked) {
										tr.addClassName('selected');
									}
								}
							});

							$(id+'_cancel').observe('click',function(event){
								this.container.hide();
								$('filter_select').options.selectedIndex = 0;
									$('filter_select').show();
								return false;
							}.bindAsEventListener(multiselect[id]));

							$(id+'_close').observe('click',function(event){
								this.container.hide();
								$('filter_edit').submit();
								return false;
							}.bindAsEventListener(multiselect[id]));

							multiselect[id].container.show();
						}
					}
					return false;
				}
				function setDateValue(field, date) {
					timestamp = getMSFromDate(date);
					if (field.value != timestamp) {
						window.location = '<?php echo ENTRADA_URL.$module_type."/events?".(($_SERVER["QUERY_STRING"] != "") ? replace_query(array("dstamp" => false))."&" : ""); ?>dstamp='+timestamp;
					}
					return;
				}
				</script>
			</td>
			<td style="width: 47%; vertical-align: top">
				<?php
				if ((is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"])) && (count($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]))) {
					echo "<table class=\"inner-content-box\" id=\"filter-list\" cellspacing=\"0\" summary=\"Selected Filter List\">\n";
					echo "<thead>\n";
					echo "	<tr>\n";
					echo "		<td class=\"inner-content-box-head\">Showing Events That Include:</td>\n";
					echo "	</tr>\n";
					echo "</thead>\n";
					echo "<tbody>\n";
					echo "	<tr>\n";
					echo "		<td class=\"inner-content-box-body\">";
					echo "		<div id=\"filter-list-resize-handle\" style=\"margin:0px -6px -6px -7px;\">";
					echo "		<div id=\"filter-list-resize\" style=\"height: 60px; overflow: auto;  padding: 0px 6px 6px 6px;\">\n";
					foreach ($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"] as $filter_type => $filter_contents) {
						if (is_array($filter_contents)) {
							echo 	$filter_name = filter_name($filter_type);
							echo "	<div style=\"margin: 2px 0px 10px 3px\">\n";
							foreach ($filter_contents as $filter_key => $filter_value) {
								echo "	<div id=\"".$filter_type."_".$filter_key."\">";
								echo "		<a href=\"".ENTRADA_URL.$module_type."/events?action=filter_remove&amp;filter=".$filter_type."_".$filter_key."\" title=\"Remove this filter\">";
								echo "		<img src=\"".ENTRADA_URL."/images/checkbox-on.gif\" width=\"14\" height=\"14\" alt=\"\" title=\"\" />";
								switch ($filter_type) {
									case "teacher" :
									case "student" :
										echo get_account_data("fullname", $filter_value);
									break;
									case "grad" :
										echo "Class of ".$filter_value;
									break;
									case "course" :
										echo course_name($filter_value);
									break;
									case "phase" :
										echo "Phase / Term ".strtoupper($filter_value);
									break;
									case "eventtype" :
										echo fetch_eventtype_title($filter_value);
									break;
									case "organisation":
										echo fetch_organisation_title($filter_value);
									break;
									case "objective":
										echo fetch_objective_title($filter_value);
									break;
									default :
										echo strtoupper($filter_value);
									break;
								}
								echo "		</a>";
								echo "	</div>\n";
							}
							echo "	</div>\n";
						}
					}
					echo "		</div>\n";
					echo "		</div>\n";
					echo "		</td>\n";
					echo "	</tr>\n";
					echo "</tbody>\n";
					echo "</table>\n";
					echo "<br />\n";
					echo "<script type=\"text/javascript\">";
					echo "	new ElementResizer($('filter-list-resize'), {handleElement: $('filter-list-resize-handle'), min: 40});";
					echo "</script>";
				}
				?>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Function used by public events and admin events index to output the HTML for the calendar controls.
 */
function events_output_calendar_controls($module_type = "") {
	global $learning_events;
	
	/**
	 * Determine whether or not this is being called from the admin section.
	 */
	if ($module_type == "admin") {
		$module_type = "/admin";
	} else {
		$module_type = "";
	}
	?>
	<table style="width: 100%; margin: 10px 0px 10px 0px" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td style="width: 53%; vertical-align: top; text-align: left">
				<table style="width: 298px; height: 23px" cellspacing="0" cellpadding="0" border="0" summary="Display Duration Type">
					<tr>
						<td style="width: 22px; height: 23px"><a href="<?php echo ENTRADA_URL.$module_type."/events?".replace_query(array("dstamp" => ($learning_events["duration_start"] - 2))); ?>" title="Previous <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]); ?>"><img src="<?php echo ENTRADA_URL; ?>/images/cal-back.gif" border="0" width="22" height="23" alt="Previous <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]); ?>" title="Previous <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]); ?>" /></a></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"] == "day") ? "<img src=\"".ENTRADA_URL."/images/cal-day-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Day View\" title=\"Day View\" />" : "<a href=\"".ENTRADA_URL.$module_type."/events?".replace_query(array("dtype" => "day"))."\"><img src=\"".ENTRADA_URL."/images/cal-day-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Day View\" title=\"Day View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"] == "week") ? "<img src=\"".ENTRADA_URL."/images/cal-week-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Week View\" title=\"Week View\" />" : "<a href=\"".ENTRADA_URL.$module_type."/events?".replace_query(array("dtype" => "week"))."\"><img src=\"".ENTRADA_URL."/images/cal-week-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Week View\" title=\"Week View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"] == "month") ? "<img src=\"".ENTRADA_URL."/images/cal-month-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Month View\" title=\"Month View\" />" : "<a href=\"".ENTRADA_URL.$module_type."/events?".replace_query(array("dtype" => "month"))."\"><img src=\"".ENTRADA_URL."/images/cal-month-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Month View\" title=\"Month View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"] == "year") ? "<img src=\"".ENTRADA_URL."/images/cal-year-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Year View\" title=\"Year View\" />" : "<a href=\"".ENTRADA_URL.$module_type."/events?".replace_query(array("dtype" => "year"))."\"><img src=\"".ENTRADA_URL."/images/cal-year-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Year View\" title=\"Year View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px; border-left: 1px #9D9D9D solid"><a href="<?php echo ENTRADA_URL.$module_type."/events?".replace_query(array("dstamp" => ($learning_events["duration_end"] + 1))); ?>" title="Following <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]); ?>"><img src="<?php echo ENTRADA_URL; ?>/images/cal-next.gif" border="0" width="22" height="23" alt="Following <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]); ?>" title="Following <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]); ?>" /></a></td>
						<td style="width: 33px; height: 23px; text-align: right"><a href="<?php echo ENTRADA_URL.$module_type; ?>/events?<?php echo replace_query(array("dstamp" => time())); ?>"><img src="<?php echo ENTRADA_URL; ?>/images/cal-home.gif" width="23" height="23" alt="Reset to display current calendar <?php echo $_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]; ?>." title="Reset to display current calendar <?php echo $_SESSION[APPLICATION_IDENTIFIER]["events"]["dtype"]; ?>." border="0" /></a></td>
						<td style="width: 33px; height: 23px; text-align: right"><img src="<?php echo ENTRADA_URL; ?>/images/cal-calendar.gif" width="23" height="23" alt="Show Calendar" title="Show Calendar" onclick="showCalendar('', document.getElementById('dstamp'), document.getElementById('dstamp'), '<?php echo html_encode($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dstamp"]); ?>', 'calendar-holder', 8, 8, 1)" style="cursor: pointer" id="calendar-holder" /></td>
					</tr>
				</table>
			</td>
			<td style="width: 47%; vertical-align: top; text-align: right">
				<?php
				if ($learning_events["total_pages"] > 1) {
					echo "<form action=\"".ENTRADA_URL.$module_type."/events\" method=\"get\" id=\"pageSelector\">\n";
					echo "<div style=\"white-space: nowrap\">\n";
					echo "<span style=\"width: 20px; vertical-align: middle; margin-right: 3px; text-align: left\">\n";
					if ($learning_events["page_previous"]) {
						echo "<a href=\"".ENTRADA_URL.$module_type."/events?".replace_query(array("pv" => $learning_events["page_previous"]))."\"><img src=\"".ENTRADA_URL."/images/record-previous-on.gif\" border=\"0\" width=\"11\" height=\"11\" alt=\"Back to page ".$learning_events["page_previous"].".\" title=\"Back to page ".$learning_events["page_previous"].".\" style=\"vertical-align: middle\" /></a>\n";
					} else {
						echo "<img src=\"".ENTRADA_URL."/images/record-previous-off.gif\" width=\"11\" height=\"11\" alt=\"\" title=\"\" style=\"vertical-align: middle\" />";
					}
					echo "</span>";
					echo "<span style=\"vertical-align: middle\">\n";
					echo "<select name=\"pv\" onchange=\"$('pageSelector').submit();\"".(($learning_events["total_pages"] <= 1) ? " disabled=\"disabled\"" : "").">\n";
					for ($i = 1; $i <= $learning_events["total_pages"]; $i++) {
						echo "<option value=\"".$i."\"".(($i == $learning_events["page_current"]) ? " selected=\"selected\"" : "").">".(($i == $learning_events["page_current"]) ? " Viewing" : "Jump To")." Page ".$i."</option>\n";
					}
					echo "</select>\n";
					echo "</span>\n";
					echo "<span style=\"width: 20px; vertical-align: middle; margin-left: 3px; text-align: right\">\n";
					if ($learning_events["page_current"] < $learning_events["total_pages"]) {
						echo "<a href=\"".ENTRADA_URL.$module_type."/events?".replace_query(array("pv" => $learning_events["page_next"]))."\"><img src=\"".ENTRADA_URL."/images/record-next-on.gif\" border=\"0\" width=\"11\" height=\"11\" alt=\"Forward to page ".$learning_events["page_next"].".\" title=\"Forward to page ".$learning_events["page_next"].".\" style=\"vertical-align: middle\" /></a>";
					} else {
						echo "<img src=\"".ENTRADA_URL."/images/record-next-off.gif\" width=\"11\" height=\"11\" alt=\"\" title=\"\" style=\"vertical-align: middle\" />";
					}
					echo "</span>\n";
					echo "</div>\n";
					echo "</form>\n";
				}
				?>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Function used to create the default filter settings for Learning Events
 * 
 * @param int $proxy_id
 * @param string $group
 * @param string $role
 * @return array Containing the default filters.
 */
function events_filters_defaults($proxy_id = 0, $group = "", $role = "") {
	$filters = array();

	switch ($group) {
		case "resident" :
		case "faculty" :
			/**
			 * Teaching faculty see events which they are involved with by default.
			 */
			if (in_array($role, array("director", "lecturer", "teacher"))) {
				$filters["teacher"][0] = (int) $proxy_id;
			}
		break;
		case "student" :
			/**
			 * Students see events they are involved with by default.
			 */
			$filters["student"][0] = (int) $proxy_id;
		break;
		case "medtech" :
		case "staff" :
		default :
			$filters["grad"][0] = (int) fetch_first_year();
		break;
	}

	if (!empty($filters)) {
		ksort($filters);
	}

	return $filters;
}

/**
 * Function used by public events and admin events index to process the provided filter settings.
 */
function events_process_filters($action = "", $module_type = "") {
	/**
	 * Determine whether or not this is being called from the admin section.
	 */
	if ($module_type == "admin") {
		$module_type = "/admin";
	} else {
		$module_type = "";
	}

	/**
	 * Handles any page actions for this module.
	 */
	switch ($action) {
		case "filter_add" :
			if (isset($_GET["filter"])) {
				$pieces = explode("_", clean_input($_GET["filter"], array("nows", "lower", "notags")));
				$filter_key = $pieces[0];
				$filter_value = $pieces[1];
				if (($filter_key) && ($filter_value)) {
					$key = 0;

					if ((!is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_key])) || (!in_array($filter_value, $_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_key]))) {
						/**
						 * Check to see if this is a student attempting to view the calendar of another student.
						 */
						if (($filter_key != "student") || ($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"] != "student") || ($filter_value == $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])) {
							$_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_key][] = $filter_value;

							ksort($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]);
						}
					}
				}
			}

			$_SERVER["QUERY_STRING"] = replace_query(array("action" => false, "filter" => false));
		break;
		case "filter_edit" :
			if (isset($_GET["filter"])) {
				$filters = explode(",", clean_input($_GET["filter"], array("nows", "lower", "notags")));
				if (isset($filters[1])) {
					$pieces = explode("_", $filters[0]);
					$filter_key	= $pieces[0];
					unset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_key]);

					foreach ($filters as $filter) {
						$pieces = explode("_", $filter);
						$filter_value = $pieces[1];
						if (($filter_key != "student") || ($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"] != "student") || ($filter_value == $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])) {
							$_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_key][] = $filter_value;
							ksort($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]);
						}
					}
				} else {
					$pieces = explode("_", $filters[0]);
					$filter_key = $pieces[0];
					$filter_value = $pieces[1];
					if ($filter_value && $filter_key) {
						//This is an actual filter, cool dude. Erase everything else since we only got one and add this one if its not a student looking at another student
						if (($filter_key != "student") || ($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"] != "student") || ($filter_value == $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"])) {
							unset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_key]);
							$_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_key][] = $filter_value;
							ksort($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]);
						}
					} else {
						// This is coming from the select box and nothing was selected, so erase.
						$filter_type = clean_input($_GET["filter_type"], array("nows", "lower", "notags"));
						if (is_array($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_type])) {
							unset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_type]);
						}
					}
				}

				ksort($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]);
			}

			$_SERVER["QUERY_STRING"] = replace_query(array("action" => false, "filter" => false));
		break;
		case "filter_remove" :
			if (isset($_GET["filter"])) {
				$pieces = explode("_", clean_input($_GET["filter"], array("nows", "lower", "notags")));
				$filter_type = $pieces[0];
				$filter_key	= $pieces[1];
				if (($filter_type) && ($filter_key != "") && (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_type][$filter_key]))) {

					unset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_type][$filter_key]);

					if (!@count($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_type])) {
						unset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"][$filter_type]);
					}

					ksort($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]);
				}
			}

			$_SERVER["QUERY_STRING"] = replace_query(array("action" => false, "filter" => false));
		break;
		case "filter_removeall" :
			if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"])) {
				unset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]);
			}

			$_SERVER["QUERY_STRING"] = replace_query(array("action" => false, "filter" => false));
		break;
		case "filter_defaults" :
			/**
			 * If this is the first time this page has been loaded, lets setup the default filters.
			 */
			if (!isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filter_defaults_set"])) {
				$_SESSION[APPLICATION_IDENTIFIER]["events"]["filter_defaults_set"] = true;
			}

			/**
			 * First unset any previous filters if they exist.
			 */
			if (isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"])) {
				unset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"]);
			}

			$_SESSION[APPLICATION_IDENTIFIER]["events"]["filters"] = events_filters_defaults(
				$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"],
				$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"],
				$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["role"]
			);

			$_SERVER["QUERY_STRING"] = replace_query(array("action" => false, "filter" => false));
		break;
		default :
			continue;
		break;
	}

	if (!isset($_SESSION[APPLICATION_IDENTIFIER]["events"]["filter_defaults_set"])) {
		header("Location: ".ENTRADA_URL.$module_type."/events?action=filter_defaults");
		exit;
	}
}

/**
 * Function used by public events and admin events index to generate the SQL queries based on the users
 * filter settings and results that can be iterated through by these views.
 */
function events_fetch_filtered_events($proxy_id = 0, $user_group = "", $user_role = "", $organisation_id = 0, $sort_by = "", $sort_order = "", $date_type = "", $timestamp_start = 0, $timestamp_finish = 0, $filters = array(), $pagination = true, $current_page = 1, $results_per_page = 15) {
	global $db;

	$output = array(
				"duration_start" => 0,
				"duration_end" => 0,
				"total_rows" => 0,
				"total_pages" => 0,
				"page_current" => 0,
				"page_previous" => 0,
				"page_next" => 0,
				"events" => array()
			);

	if (!$proxy_id = (int) $proxy_id) {
		return false;
	}

	$user_group = clean_input($user_group);
	$user_role = clean_input($user_role);

	if (!$organisation_id = (int) $organisation_id) {
		return false;
	}

	$sort_by = clean_input($sort_by);
	$sort_order = ((strtoupper($sort_order) == "ASC") ? "ASC" : "DESC");
	$date_type = clean_input($date_type);

	if (!$timestamp_start = (int) $timestamp_start) {
		return false;
	}

	$timestamp_finish = (int) $timestamp_finish;

	if (!is_array($filters)) {
		$filters = array();
	}

	$pagination = (bool) $pagination;

	if (!$current_page = (int) $current_page) {
		$current_page = 1;
	}

	if (!$results_per_page = (int) $results_per_page) {
		$results_per_page = 15;
	}

	/**
	 * Provide the queries with the columns to order by.
	 */
	switch ($sort_by) {
		case "teacher" :
			$sort_by = "`fullname` ".strtoupper($sort_order).", `events`.`event_start` ASC";
		break;
		case "title" :
			$sort_by = "`events`.`event_title` ".strtoupper($sort_order).", `events`.`event_start` ASC";
		break;
		case "phase" :
			$sort_by = "`events`.`event_phase` ".strtoupper($sort_order).", `events`.`event_start` ASC";
		break;
		case "date" :
		default :
			$sort_by = "`events`.`event_start` ".strtoupper($sort_order);
		break;
	}

	/**
	 * This fetches the unix timestamps from the first and last second of the day, week, month, year, etc.
	 */
	$display_duration = fetch_timestamps($date_type, $timestamp_start, $timestamp_finish);
	
	$output["duration_start"] = $display_duration["start"];
	$output["duration_end"] = $display_duration["end"];

	$query_events = "	SELECT `events`.`event_id`,
						`events`.`course_id`,
						`events`.`event_phase`,
						`events`.`event_title`,
						`events`.`event_message`,
						`events`.`event_location`,
						`events`.`event_start`,
						`events`.`event_finish`,
						`events`.`release_date`,
						`events`.`release_until`,
						`events`.`updated_date`,
						`event_audience`.`audience_type`,
						`courses`.`organisation_id`,
						`courses`.`course_name`,
						CONCAT_WS(', ', `".AUTH_DATABASE."`.`user_data`.`lastname`, `".AUTH_DATABASE."`.`user_data`.`firstname`) AS `fullname`,
						MAX(`statistics`.`timestamp`) AS `last_visited`
						FROM `events`";

	/**
	 * If there are filters set by the user, build the SQL to reflect the filters.
	 */
	if (is_array($filters) && !empty($filters)) {
		$tmp_query = array();
		$where_teacher = array();
		$where_course = array();
		$where_grad_year = array();
		$where_phase = array();
		$where_type = array();
		$where_clinical_presentation = array();
		$join_event_contacts = array();
		$contact_sql = "";

		$query_count = "	SELECT COUNT(DISTINCT `events`.`event_id`) AS `total_rows`
							FROM `events`
							LEFT JOIN `event_contacts` AS `primary_teacher`
							ON `primary_teacher`.`event_id` = `events`.`event_id`
							AND `primary_teacher`.`contact_order` = '0'
							LEFT JOIN `event_eventtypes` AS `types`
							ON `types`.`event_id` = `events`.`event_id`
							LEFT JOIN `event_audience`
							ON `event_audience`.`event_id` = `events`.`event_id`
							%CONTACT_JOIN%
							LEFT JOIN `".AUTH_DATABASE."`.`user_data`
							ON `".AUTH_DATABASE."`.`user_data`.`id` = `primary_teacher`.`proxy_id`
							LEFT JOIN `courses`
							ON `courses`.`course_id` = `events`.`course_id`
							LEFT JOIN `event_objectives`
							ON `event_objectives`.`event_id` = `events`.`event_id`
							AND `event_objectives`.`objective_type` = 'course'
							WHERE `courses`.`course_active` = '1'
							AND (`events`.`release_date` <= ".$db->qstr(time())." OR `events`.`release_date` = 0)
							AND (`events`.`release_until` >= ".$db->qstr(time())." OR `events`.`release_until` = 0)
							AND `courses`.`organisation_id` = ".$db->qstr($organisation_id);

		$query_events .= "	LEFT JOIN `event_contacts` AS `primary_teacher`
							ON `primary_teacher`.`event_id` = `events`.`event_id`
							AND `primary_teacher`.`contact_order` = '0'
							LEFT JOIN `event_eventtypes` AS `types`
							ON `types`.`event_id` = `events`.`event_id`
							LEFT JOIN `event_audience`
							ON `event_audience`.`event_id` = `events`.`event_id`
							%CONTACT_JOIN%
							LEFT JOIN `".AUTH_DATABASE."`.`user_data`
							ON `".AUTH_DATABASE."`.`user_data`.`id` = `primary_teacher`.`proxy_id`
							LEFT JOIN `courses`
							ON  `courses`.`course_id` = `events`.`course_id`
							LEFT JOIN `event_objectives`
							ON `event_objectives`.`event_id` = `events`.`event_id`
							AND `event_objectives`.`objective_type` = 'course'
							LEFT JOIN `statistics`
							ON `statistics`.`action_value` = `events`.`event_id`
							AND `statistics`.`module` = 'events'
							AND `statistics`.`proxy_id` = ".$db->qstr($proxy_id)."
							AND `statistics`.`action` = 'view'
							AND `statistics`.`action_field` = 'event_id'
							WHERE `courses`.`course_active` = '1'
							AND `courses`.`organisation_id` = ".$db->qstr($organisation_id);

		if ($display_duration) {
			$tmp_query[] = "(`events`.`event_start` BETWEEN ".$db->qstr($display_duration["start"])." AND ".$db->qstr($display_duration["end"]).")";
		}

		if (!is_array($filters) || empty($filters)) {
			// Apply default filters.
		}

		if (!empty($filters)) {
			foreach ($filters as $filter_type => $filter_contents) {
				if ((is_array($filter_contents)) && (count($filter_contents))) {
					foreach ($filter_contents as $filter_key => $filter_value) {
						switch ($filter_type) {
							case "teacher" :
								$where_teacher[] = "(`primary_teacher`.`proxy_id` = ".$db->qstr($filter_value)." OR `event_contacts`.`proxy_id` = ".$db->qstr($filter_value).")";

								$join_event_contacts[] = "(`event_contacts`.`proxy_id` = ".$db->qstr($filter_value).")";
							break;
							case "student" :
								if (($user_group != "student") || ($filter_value == $proxy_id)) {
									$student_grad_year = "";
									$student_proxy_id = (int) $filter_value;

									/**
									 * Get the grad_year of the proxy_id.
									 */
									$query = "	SELECT `role` AS `grad_year`
												FROM `".AUTH_DATABASE."`.`user_access`
												WHERE `user_id` = ".$db->qstr($student_proxy_id)."
												AND `app_id` = ".$db->qstr(AUTH_APP_ID)."
												AND `group` = 'student'";
									$result = $db->GetRow($query);
									if (($result) && ($tmp_input = (int) $result["grad_year"])) {
										$student_grad_year = "(`event_audience`.`audience_type` = 'grad_year' AND `event_audience`.`audience_value` = ".$db->qstr($tmp_input).") OR ";
									}

									$where_student[] = "(".$student_grad_year."(`event_audience`.`audience_type` = 'proxy_id' AND `event_audience`.`audience_value` = ".$db->qstr($student_proxy_id)."))";
								}
							break;
							case "grad" :
								$where_grad_year[] = "(`event_audience`.`audience_type` = 'grad_year' AND `event_audience`.`audience_value` = ".$db->qstr((int) $filter_value).")";
							break;
							case "course" :
								$where_course[] = "(`events`.`course_id` = ".$db->qstr($filter_value).")";
							break;
							case "phase" :
								$where_phase[] = "(`events`.`event_phase` LIKE ".$db->qstr($filter_value).")";
							break;
							case "eventtype" :
								$where_type[] = "(`types`.`eventtype_id` = ".$db->qstr((int) $filter_value).")";
							break;
							case "objective" :
								$where_clinical_presentation[] = "(`event_objectives`.`objective_id` = ".$db->qstr((int) $filter_value).")";
							break;
							default :
								continue;
							break;
						}
					}
				}
			}
		}

		if (isset($where_teacher) && count($where_teacher)) {
			$tmp_query[] = implode(" OR ", $where_teacher);
		}
		if (isset($where_student) && count($where_student)) {
			$tmp_query[] = implode(" OR ", $where_student);
		}
		if (isset($where_grad_year) && count($where_grad_year)) {
			$tmp_query[] = implode(" OR ", $where_grad_year);
		}
		if (isset($where_course) && count($where_course)) {
			$tmp_query[] = implode(" OR ", $where_course);
		}
		if (isset($where_phase) && count($where_phase)) {
			$tmp_query[] = implode(" OR ", $where_phase);
		}
		if (isset($where_type) && count($where_type)) {
			$tmp_query[] = implode(" OR ", $where_type);
		}
		if (isset($where_clinical_presentation) && count($where_clinical_presentation)) {
			$tmp_query[] = implode(" OR ", $where_clinical_presentation);
		}

		if (isset($tmp_query) && count($tmp_query)) {
			$query_count .= " AND (".implode(") AND (", $tmp_query).")";
			$query_events .= " AND (".implode(") AND (", $tmp_query).")";
		}

		if (isset($join_event_contacts) && count($join_event_contacts)) {
			$contact_sql = "	LEFT JOIN `event_contacts`
								ON `event_contacts`.`event_id` = `events`.`event_id`
								AND (".implode(" OR ", $join_event_contacts).")";
		}

	 	$query_count = str_replace("%CONTACT_JOIN%", $contact_sql, $query_count);
		$query_events = str_replace("%CONTACT_JOIN%", $contact_sql, $query_events)." GROUP BY `events`.`event_id` ORDER BY %s".($pagination ? " LIMIT %s, %s" : "");
	} else {
		$query_count = "	SELECT COUNT(DISTINCT `events`.`event_id`) AS `total_rows`
							FROM `events`
							LEFT JOIN `courses`
							ON `events`.`course_id` = `courses`.`course_id`
							WHERE `courses`.`organisation_id` = ".$db->qstr($organisation_id)."
							AND (`events`.`release_date` <= ".$db->qstr(time())." OR `events`.`release_date` = 0)
							AND (`events`.`release_until` >= ".$db->qstr(time())." OR `events`.`release_until` = 0)
							".(($display_duration) ? " AND `events`.`event_start` BETWEEN ".$db->qstr($display_duration["start"])." AND ".$db->qstr($display_duration["end"]) : "");

		$query_events .= "	LEFT JOIN `event_contacts`
							ON `event_contacts`.`event_id` = `events`.`event_id`
							AND `event_contacts`.`contact_order` = '0'
							LEFT JOIN `event_audience`
							ON `event_audience`.`event_id` = `events`.`event_id`
							LEFT JOIN `".AUTH_DATABASE."`.`user_data`
							ON `".AUTH_DATABASE."`.`user_data`.`id` = `event_contacts`.`proxy_id`
							LEFT JOIN `courses`
							ON  (`courses`.`course_id` = `events`.`course_id`)
							LEFT JOIN `statistics`
							ON `statistics`.`action_value` = `events`.`event_id`
							AND `statistics`.`module` = ".$db->qstr("events")."
							AND `statistics`.`proxy_id` = ".$db->qstr($proxy_id)."
							AND `statistics`.`action` = 'view'
							AND `statistics`.`action_field` = 'event_id'
							WHERE`courses`.`course_active` = '1'
							AND `courses`.`organisation_id` = ".$db->qstr($organisation_id)."
							".(($display_duration) ? "AND `events`.`event_start` BETWEEN ".$db->qstr($display_duration["start"])." AND ".$db->qstr($display_duration["end"]) : "")."
							GROUP BY `events`.`event_id`
							ORDER BY %s".($pagination ? " LIMIT %s, %s" : "");
	}

	/**
	 * Get the total number of results using the generated queries above and calculate the total number
	 * of pages that are available based on the results per page preferences.
	 */
	$result_count = $db->GetRow($query_count);
	if ($result_count) {
		$output["total_rows"] = (int) $result_count["total_rows"];

		if ($output["total_rows"] <= $results_per_page) {
			$output["total_pages"] = 1;
		} elseif (($output["total_rows"] % $results_per_page) == 0) {
			$output["total_pages"] = (int) ($output["total_rows"] / $results_per_page);
		} else {
			$output["total_pages"] = (int) ($output["total_rows"] / $results_per_page) + 1;
		}
	} else {
		$output["total_rows"] = 0;
		$output["total_pages"] = 1;
	}

	/**
	 * Check if pv variable is set and see if it's a valid page, other wise page 1 it is.
	 */
	if ($current_page) {
		$output["page_current"] = (int) trim($current_page);

		if (($output["page_current"] < 1) || ($output["page_current"] > $output["total_pages"])) {
			$output["page_current"] = 1;
		}
	} else {
		$output["page_current"] = 1;
	}

	$output["page_previous"] = (($output["page_current"] > 1) ? ($output["page_current"] - 1) : false);
	$output["page_next"] = (($output["page_current"] < $output["total_pages"]) ? ($output["page_current"] + 1) : false);

	/**
	 * Provides the first parameter of MySQLs LIMIT statement by calculating which row to start results from.
	 */
	$limit_parameter = (int) (($results_per_page * $output["page_current"]) - $results_per_page);

	/**
	 * Save the result ID so it can be used when displaying events.
	 */
	if ($limit_parameter) {
		$output["rid"] = $limit_parameter;
	} else {
		$output["rid"] = 0;
	}

	/**
	 * Provide the previous query so we can have previous / next event links on the details page.
	 */
	if (session_id()) {
		$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dashboard"]["previous_query"]["query"] = $query_events;
		$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dashboard"]["previous_query"]["total_rows"] = $output["total_rows"];

		$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["events"]["previous_query"]["query"] = $query_events;
		$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["events"]["previous_query"]["total_rows"] = $output["total_rows"];
	}

	$query_events = sprintf($query_events, $sort_by, $limit_parameter, $results_per_page);
	$learning_events = $db->GetAll($query_events);

	if ($learning_events) {
		$output["events"] = $learning_events;
	}

	return $output;
}

function event_objectives_in_list($objectives, $parent_id, $edit_text = false, $parent_active = false, $importance = 1, $course = true, $top = true) {
	global $edit_ajax;
	$output = "";

	if (!is_array($edit_ajax)) {
		$edit_ajax = array();
	}
	
	if ((is_array($objectives)) && ($total = count($objectives))) {
		$count	= 0;
		if ($top) {
			$output	= "\n<ul class=\"objective-list\" id=\"objective_".$parent_id."_list\"".($parent_id == 1 ? " style=\"padding-left: 0; margin-top: 0\"" : "").">\n";
		}
		foreach ($objectives as $objective_id => $objective) {
			$count++;

			if (($objective["parent"] == $parent_id) && (($objective["objective_children"]) || ($objective["primary"]) || ($objective["secondary"]) || ($objective["tertiary"]) || ($parent_active))) {
				$importance = (($objective["primary"]) ? 1 : ($objective["secondary"] ? 2 : ($objective["tertiary"] ? 3 : $importance)));

				if (((($objective["primary"]) || ($objective["secondary"]) || ($objective["tertiary"]) || ($parent_active)) && (count($objective["parent_ids"]) > 2))) {
					$output .= "<li".((($edit_text) || (isset($objective["event_objective"]) && $objective["event_objective"])) && (count($objective["parent_ids"]) > 2) ? " class=\"".($importance == 2 ? "secondary" : ($importance == 3 ? "tertiary" : "primary"))."\"" : "").">\n";
					if ($edit_text && !$course) {
						$output .= "<div id=\"objective_table_".$objective_id."\">\n";
						$output .= "	<input type=\"checkbox\" name=\"checked_objectives[".$objective_id."]\" id=\"objective_checkbox_".$objective_id."\"".($course ? " disabled=\"true\" checked=\"checked\"" : " onclick=\"if (this.checked) { $('c_objective_".$objective_id."').enable(); } else { $('c_objective_".$objective_id."').disable(); }\"".($objective["event_objective"] ? " checked=\"checked\"" : ""))." style=\"float: left;\" value=\"1\" />\n";
						$output .= "	<label for=\"objective_checkbox_".$objective_id."\" class=\"heading\">".$objective["name"]."</label>\n";
						$output .= "	<div class=\"content-small\" style=\"padding-left: 25px;\">".$objective["description"]."</div>\n";
						$output .= "</div>\n";
						$output .= "<div style=\"padding-left: 25px; margin: 5px 0 5px 0\">\n";
						$output .= "	<input type=\"checkbox\" id=\"c_objective_".$objective_id."\" name=\"course_objectives[".$objective_id."]\" onclick=\"objectiveClick(this, '".$objective_id."', '".str_replace("'", "\'", $objective["objective_details"])."')\"".((isset($objective["objective_details"]) && $objective["objective_details"] && $course) || (isset($objective["event_objective_details"]) && $objective["event_objective_details"] && !$course && $objective["event_objective"]) ? "checked=\"checked\"" : (!$course && !$objective["event_objective"] ? " disabled=\"disabled\"" : ""))." value=\"1\" />\n";
						$output .= "	<label for=\"c_objective_".$objective_id."\" class=\"content-small\" id=\"objective_".$objective_id."_append\" style=\"vertical-align: middle;\">Add additional detail to this curriculum objective.</label>\n";
						if (isset($objective["event_objective_details"]) && $objective["event_objective_details"] && $objective["event_objective"]) {
							$output .= "<textarea name=\"objective_text[".$objective_id."]\" id=\"objective_text_".$objective_id."\" class=\"expandable objective\">".html_encode($objective["event_objective_details"])."</textarea>";
						}
						$output .= "</div>\n";
					} elseif ($edit_text) {
						$edit_ajax[] = $objective_id;
						$output .= "<div id=\"objective_table_".$objective_id."\">\n";
						$output .= "	<label for=\"objective_checkbox_".$objective_id."\" class=\"heading\">".$objective["name"]."</label> ( <span id=\"edit_mode_".$objective_id."\" class=\"content-small\" style=\"cursor: pointer\">edit</span> )\n";
						$output .= "	<div class=\"content-small\" style=\"padding-left: 25px;\" id=\"objective_description_".$objective_id."\">".(isset($objective["objective_details"]) && $objective["objective_details"] ? $objective["objective_details"] : $objective["description"])."</div>\n";
						$output .= "</div>\n";
					} else {
						$output .= "<input type=\"checkbox\" id=\"objective_checkbox_".$objective_id."\ name=\"course_objectives[".$objective_id."]\"".(isset($objective["event_objective"]) && $objective["event_objective"] ? " checked=\"checked\"" : "")." onclick=\"if (this.checked) { this.parentNode.addClassName('".($importance == 2 ? "secondary" : ($importance == 3 ? "tertiary" : "primary"))."'); } else { this.parentNode.removeClassName('".($importance == 2 ? "secondary" : ($importance == 3 ? "tertiary" : "primary"))."'); }\" style=\"float: left;\" value=\"1\" />\n";
						$output .= "<label for=\"objective_checkbox_".$objective_id."\" class=\"heading\">".$objective["name"]."</label>\n";
						$output .= "<div style=\"padding-left: 25px;\">\n";
						$output .=		$objective["description"]."\n";
						if (isset($objective["objective_details"]) && $objective["objective_details"]) {
							$output .= "<br/><br/>\n";
							$output .= "<em>".$objective["objective_details"]."</em>";
						}
						$output .= "</div>\n";
					}
					$output .= "</li>\n";

				} else {
					$output .= event_objectives_in_list($objectives, $objective_id, $edit_text, ((($objective["primary"]) || ($objective["secondary"]) || ($objective["tertiary"])) ? true : false), $importance, $course, false);
				}
			}
		}
		if ($top) {
			$output .= "</ul>\n";
		}
	}

	return $output;
}

/**
 * Returns the apartment schedule ID of the accommodation if there
 * is one for the provided event and proxy ID.
 *
 * @param int $event_id
 * @param int $proxy_id
 * @return int
 */
function regionaled_apartment_check($event_id = 0, $proxy_id = 0) {
	global $db;

	if (($event_id = (int) $event_id) && ($proxy_id = (int) $proxy_id)) {
		$query = "SELECT `aschedule_id` FROM `".CLERKSHIP_DATABASE."`.`apartment_schedule` WHERE `event_id` = ".$db->qstr($event_id)." AND `proxy_id` = ".$db->qstr($proxy_id);
		$aschedule_id = $db->GetOne($query);
		if ($aschedule_id) {
			return (int) $aschedule_id;
		}
	}

	return 0;
}

function regionaled_apartment_notification($type, $to = array(), $keywords = array()) {
	global $ERROR, $NOTICE, $SUCCESS, $ERRORSTR, $NOTICESTR, $SUCCESSSTR, $AGENT_CONTACTS;

	if (!is_array($to) || !isset($to["email"]) || !valid_address($to["email"]) || !isset($to["firstname"]) || !isset($to["lastname"])) {
		application_log("error", "Attempting to send a regionaled_apartment_notification() how the recipient information was not complete.");
		
		return false;
	}
	
	if (!in_array($type, array("delete", "confirmation", "rejected"))) {
		application_log("error", "Encountered an unrecognized notification type [".$type."] when attempting to send a regionaled_apartment_notification().");

		return false;
	}

	$xml_file = TEMPLATE_ABSOLUTE."/email/regionaled-learner-accommodation-".$type.".xml";
	$xml = @simplexml_load_file($xml_file);
	if ($xml && isset($xml->lang->{DEFAULT_LANGUAGE})) {
		$subject = trim($xml->lang->{DEFAULT_LANGUAGE}->subject);
		$message = trim($xml->lang->{DEFAULT_LANGUAGE}->body);

		foreach ($keywords as $keyword => $value) {
			$subject = str_ireplace("%".strtoupper($keyword)."%", $value, $subject);
			$message = str_ireplace("%".strtoupper($keyword)."%", $value, $message);
		}

		/**
		 * Notify the learner they have been removed from this apartment.
		 */
		$mail = new Zend_Mail();
		$mail->addHeader("X-Originating-IP", $_SERVER["REMOTE_ADDR"]);
		$mail->addHeader("X-Section", "Regional Education Module", true);
		$mail->clearFrom();
		$mail->clearSubject();
		$mail->setFrom($AGENT_CONTACTS["agent-regionaled"]["email"], APPLICATION_NAME." Regional Education System");
		$mail->setSubject($subject);
		$mail->setBodyText(clean_input($message, "emailcontent"));

		$mail->clearRecipients();
		$mail->addTo($to["email"], $to["firstname"]." ".$to["lastname"]);

		if ($mail->send()) {
			return true;
		} else {
			$NOTICE++;
			$NOTICESTR[] = "We were unable to e-mail an e-mail notification <strong>".$to["email"]."</strong>.<br /><br />A system administrator was notified of this issue, but you may wish to contact this learner manually and let them know their accommodation has ben removed.";

			application_log("error", "Unable to send accommodation notification to [".$to["email"]."] / type [".$type."]. Zend_Mail said: ".$mail->ErrorInfo);
		}
	} else {
		application_log("error", "Unable to load the XML file [".$xml_file."] or the XML file did not contain the language requested [".DEFAULT_LANGUAGE."], when attempting to send a regional education notification.");
	}

	return false;
}

function regionaled_apartment_availability($apartment_ids = array(), $event_start = 0, $event_finish = 0) {
	global $db;

	if (is_scalar($apartment_ids)) {
		if ((int) $apartment_ids) {
			$apartment_ids = array($apartment_ids);
		} else {
			$apartment_ids = array();
		}
	}

	$output = array();
	$output["openings"] = 0;
	$output["apartments"] = array();

	if (count($apartment_ids) && ($event_start = (int) $event_start) && ($event_finish = (int) $event_finish)) {

		$query = "	SELECT a.*, b.`country`, c.`province`
					FROM `".CLERKSHIP_DATABASE."`.`apartments` AS a
					LEFT JOIN `global_lu_countries` AS b
					ON b.`countries_id` = a.`countries_id`
					LEFT JOIN `global_lu_provinces` AS c
					ON c.`province_id` = a.`province_id`
					WHERE a.`apartment_id` IN (".implode(", ", $apartment_ids).")
					AND (a.`available_start` = '0' OR a.`available_start` <= ".$db->qstr(time()).")
					AND (a.`available_finish` = '0' OR a.`available_finish` > ".$db->qstr(time()).")";
		$apartments = $db->GetAll($query);
		if ($apartments) {
			foreach ($apartments as $apartment) {
				$occupants = regionaled_apartment_occupants($apartment["apartment_id"], $event_start, $event_finish);
				$occupants_tmp = array();
				$occupancy_totals = array();
				$concurrent_occupants = 0;

				if ($occupants && is_array($occupants)) {
					foreach ($occupants as $occupant) {
						$concurrent_occupants = 1;

						if (count($occupants_tmp)) {
							foreach ($occupants_tmp as $tmp_occupant) {
								if ((($occupant["inhabiting_start"] >= $tmp_occupant["inhabiting_start"]) || ($occupant["inhabiting_finish"] >= $tmp_occupant["inhabiting_start"])) && ($occupant["inhabiting_start"] <= $tmp_occupant["inhabiting_finish"])) {
									$concurrent_occupants++;
								}
							}
						}

						$occupants_tmp[] = $occupant;
						$occupancy_totals[] = $concurrent_occupants;
					}
				}

				if (count($occupancy_totals)) {
					$concurrent_occupants = max($occupancy_totals);
				} else {
					$concurrent_occupants = 0;
				}

				if ($concurrent_occupants < $apartment["max_occupants"]) {
					$openings = ($apartment["max_occupants"] - $concurrent_occupants);
					$output["openings"] += $openings;
					$output["apartments"][$apartment["apartment_id"]] = array (
																			"openings" => $openings,
																			"occupants" => $occupants,
																			"details" => $apartment
																		);
				}
			}
		}
	}

	return $output;
}

function regionaled_apartment_occupants($apartment_id = 0, $event_start = 0, $event_finish = 0) {
	global $db;

	if (($apartment_id = (int) $apartment_id) && ($event_start = (int) $event_start) && ($event_finish = (int) $event_finish)) {
		$query = "	SELECT a.*, b.`username`, CONCAT(b.`firstname`, ' ', b.`lastname`) AS `fullname`, b.`gender`, b.`notes`, c.`group`
					FROM `".CLERKSHIP_DATABASE."`.`apartment_schedule` AS a
					LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS b
					ON b.`id` = a.`proxy_id`
					LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS c
					ON c.`user_id` = b.`id`
					AND c.`app_id` = ".$db->qstr(AUTH_APP_ID)."
					WHERE a.`apartment_id` = ".$db->qstr($apartment_id)."
					AND (".$db->qstr($event_start)." BETWEEN a.`inhabiting_start` AND a.`inhabiting_finish` OR
					".$db->qstr($event_finish)." BETWEEN a.`inhabiting_start` AND a.`inhabiting_finish` OR
					a.`inhabiting_start` BETWEEN ".$db->qstr($event_start)." AND ".$db->qstr($event_finish)." OR
					a.`inhabiting_finish` BETWEEN ".$db->qstr($event_start)." AND ".$db->qstr($event_finish).")
					ORDER BY a.`inhabiting_start` ASC";
		$results = $db->GetAll($query);
		if ($results) {
			return $results;
		}
	}
	
	return false;
}

function course_objectives_multiple_select_options_checked($id, $checkboxes, $options) {
	if ((!is_array($checkboxes)) || ($id == null)) {
		return null;
	}

	$default_options = array(
		"title"			=>"Select Multiple",
		"cancel"		=> false,
		"cancel_text"	=> "Close",
		"submit"		=> false,
		"submit_text"	=> "Submit",
		"class"			=> "",
		"width"			=> "350px",
		"hidden"		=> true
	);

	$options = array_merge($default_options, $options);
	$classes = array("select_multiple_container");

	if(is_array($options["class"])) {
		foreach($options["class"] as $class) {
			$classes[] = $class;
		}
	} else {
		if($options["class"] != "") {
			$classes[] = $options["class"];
		}
	}

	$class_string = implode(" ", $classes);

	$output  = "<div style=\"position: relative;\">\n";
	$output .= "	<div id=\"".$id."_options\" class=\"".$class_string."\" style=\"".($options["hidden"] ? "display: none; " : "")."width: ".$options["width"]."; position: absolute; background-color: #FFFFFF; top: -80px; left: -10px;\">\n";
	$output .= "		<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">\n";
	$output .= "			<thead>\n";
	$output .= "				<tr>\n";
	$output .= "					<td class=\"inner-content-box-head\">".$options["title"]."</td>\n";
	$output .= "				</tr>\n";
	$output .= "			</thead>\n";
	$output .= "		</table>\n";
	$output .= "		<div id=\"".$id."_scroll\" class=\"select_multiple_scroll\">\n";
	$output .= "			<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">\n";
	$output .= "			<tbody>\n";
	$output .= "				<tr>\n";
	$output .= "					<td class=\"inner-content-box-body\">\n";
	$output .= "						<div class=\"inner-content-box-body-content\" style=\"overflow: auto;\">\n";
	$output .= "							<table style=\"width: 95%\" cellspacing=\"0\" cellpadding=\"0\" class=\"select_multiple_table\">\n";
	$output .= "							<colgroup>\n";
	$output .= "								<col style=\"width: 95%\" />\n";
	$output .= "								<col style=\"width: 5%\" />\n";
	$output .= "							</colgroup>\n";
	$output .= "							<tbody>\n";
	$output .=								course_objectives_multiple_select_table($checkboxes, 0, 0);
	$output .= "							</tbody>\n";
	$output .= "							</table>\n";
	$output .= "						</div>\n";
	$output .= "						<div style=\"clear: both;\"></div>\n";
	$output .= "					</td>\n";
	$output .= "				</tr>\n";
	$output .= "			</tbody>\n";
	$output .= "			</table>\n";
	$output .= "		</div>\n";
	$output .= "						<div class=\"select_multiple_submit\">\n";
	$output .= "							<div class=\"select_multiple_filter\"></div>\n";
	$output .=								(($options['cancel'] == true) ? "<input type=\"button\" class=\"button-sm\" value=\"".$options["cancel_text"]."\" id=\"".$id."_cancel\" />" : "");
	$output .=								(($options['submit'] == true) ? "<input type=\"button\" class=\"button-sm\" value=\"".$options["submit_text"]."\" id=\"".$id."_submit\" />" : "");
	$output .= "						</div>\n";
	$output .= "	</div>";
	$output .= "</div>";

	return $output;
}

function course_objectives_multiple_select_table($checkboxes, $indent = 0, $i = 0) {
	$output			= "";
	$parent_id		= 0;
	$parent_checked	= false;

	if ($indent > 99) {
		return false;
	}
	
	foreach ($checkboxes as $checkbox) {
		$is_category = false;

		if ((!isset($checkbox["category"])) || ($checkbox["category"] == false)) {
			if ($parent_id) {
				$class = "parent".$parent_id;
				if ($parent_checked) {
					$class .= " disabled";
				}
			}
		} else {
			$is_category = true;
			$class = "category";
			$parent_id = $checkbox["value"];
			$parent_checked = ($checkbox["checked"] == "checked=\"checked\"");
		}
		
		$output .= "<tr class=\"".$class."\" id=\"row_".$checkbox["value"]."\">\n";
		$output .= "	<td class=\"select_multiple_name indent_".$indent." description\">\n";
		$output .= "		<label for=\"".$checkbox["value"]."\">".$checkbox["text"]."</label>\n";
		$output .= "	</td>\n";
		$output .= "	<td class=\"select_multiple_checkbox\">\n";
		$output .= "		<input type=\"checkbox\" id=\"".$checkbox["value"]."\" value=\"".$checkbox["value"]."\" ".$checkbox["checked"]." />\n";
		$output .= "	</td>\n";
		$output .= "</tr>";

		if (isset($checkbox["options"])) {
			$output .= course_objectives_multiple_select_table($checkbox["options"], ($indent + 1), ($i + 1));
		}
	}

	return $output;
}

function community_module_permissions_check($proxy_id, $module, $module_section, $record_id) {
	global $db, $COMMUNITY_ID, $LOGGED_IN, $COMMUNITY_MEMBER, $COMMUNITY_ADMIN, $NOTICE, $NOTICESTR, $ERROR, $ERRORSTR, $PAGE_ID;
	switch($module) {
		case "discussions" :
			require_once(COMMUNITY_ABSOLUTE."/modules/discussions.inc.php");
			return discussion_module_access($record_id, "view-post");
			break;
		case "galleries" :
			require_once(COMMUNITY_ABSOLUTE."/modules/galleries.inc.php");
			return galleries_module_access($record_id, "view-photo");
			break;
		case "shares" :
			require_once(COMMUNITY_ABSOLUTE."/modules/shares.inc.php");
			return shares_module_access($record_id, "view-file");
			break;
		case "polls" :
			require_once(COMMUNITY_ABSOLUTE."/modules/polls.inc.php");
			return polls_module_access($record_id, "view-poll");
			break;
		default :
			return true;
			break;
	}
}

if(!function_exists("get_hash")) {
	function get_hash() {
		global $db;

		do {
			$hash = md5(uniqid(rand(), 1));
		} while($db->GetRow("SELECT `id` FROM `".AUTH_DATABASE."`.`password_reset` WHERE `hash` = ".$db->qstr($hash)));

		return $hash;
	}
}

/**
 * This function merely returns the region_name associated with the region_id passed in
 *
 * @param int $region_id
 * @return string $region_name
 */
function get_region_name($region_id = 0) {
	global $db;

	if ($region_id = (int) $region_id) {
		$query = "	SELECT `region_name`
					FROM `".CLERKSHIP_DATABASE."`.`regions`
					WHERE `region_id` = ".$db->qstr($region_id);
		$region_name = $db->GetOne($query);
		if ($region_name) {
			return $region_name;
		}
	}

	return false;
}

/**
 * This function will notify the regional education office of updates / deletes to affected apartment events.
 *
 * @param string $action
 * @param int $event_id
 * @return bool $success
 */
function notify_regional_education($action, $event_id) {
	global $db, $AGENT_CONTACTS, $event_info;

	$query	= "	SELECT * FROM `".CLERKSHIP_DATABASE."`.`events` AS a
				LEFT JOIN `".CLERKSHIP_DATABASE."`.`regions` AS b
				ON a.`region_id` = b.`region_id`
				LEFT JOIN `".CLERKSHIP_DATABASE."`.`event_contacts` AS c
				ON a.`event_id` = c.`event_id`
				WHERE a.`event_id` = ".$db->qstr($event_id);
	$result	= $db->GetRow($query);
	if($result) {
		if (isset($result["manage_apartments"]) && ((int)$result["manage_apartments"]) == 1) {
			/**
			 * Don't process this if the event has already ended as there's not need for notifications.
			 */
			if($result["event_finish"] > time()) {
				$whole_name	= get_account_data("firstlast", $result["etype_id"]);
	
				$query		= "	SELECT a.`inhabiting_start`, a.`inhabiting_finish`, b.`apartment_title`
								FROM `".CLERKSHIP_DATABASE."`.`apartment_schedule` AS a
								LEFT JOIN `".CLERKSHIP_DATABASE."`.`apartments` AS b
								ON b.`apartment_id` = a.`apartment_id`
								WHERE a.`event_id` = ".$db->qstr($event_id);
				$apartments	= $db->GetAll($query);
				if ($apartments) {
					switch($action) {
						case "deleted" : 
							$message  = "Attention ".$AGENT_CONTACTS["agent-regionaled"]["name"].",\n\n";
							$message .= $_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." has removed an event from ".$whole_name."'s ";
							$message .= "clerkship schedule, to which you had previously assigned housing. Due to the removal of this event from the system, ";
							$message .= "the housing associated with it has also been removed.\n\n";
							$message .= "Information For Reference:\n\n";
							$message .= "Event Information:\n";
							$message .= "Event Title:\t".html_decode($result["event_title"])."\n";
							$message .= "Region:\t\t".$result["region_name"]."\n";
							$message .= "Start Date:\t".date(DEFAULT_DATE_FORMAT, $result["event_start"])."\n";
							$message .= "Finish Date:\t".date(DEFAULT_DATE_FORMAT, $result["event_finish"])."\n\n";
							if(($apartments) && ($assigned_apartments = @count($apartments))) {
								$message .= "Apartment".(($assigned_apartments != 1) ? "s" : "")." ".$whole_name." was removed from:\n";
								foreach($apartments as $apartment) {
									$message .= "Apartment Title:\t".$apartment["apartment_title"]."\n";
									$message .= "Inhabiting Start:\t".date(DEFAULT_DATE_FORMAT, $apartment["inhabiting_start"])."\n";
									$message .= "Inhabiting Finish:\t".date(DEFAULT_DATE_FORMAT, $apartment["inhabiting_finish"])."\n\n";
								}
							}
							$message .= "=======================================================\n\n";
							$message .= "Deletion Date:\t".date("r", time())."\n";
							$message .= "Deleted By:\t".$_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." (".$_SESSION["details"]["id"].")\n";
						break;
						case "change-critical" :
							$message  = "Attention ".$AGENT_CONTACTS["agent-regionaled"]["name"].",\n\n";
							$message .= $_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." has updated an event in ".$whole_name."'s ";
							$message .= "clerkship schedule, to which you had previously assigned housing. This update involves a change to the region or the ";
							$message .= "dates that the event took place in. Due to this critical change taking place, the housing for this event for this ";
							$message .= "student has been removed.\n\n";
							if($result["manage_apartments"]) {
								$message .= "Please log into the clerkship system and re-assign housing to this student for this event.\n\n";
							} else {
								$message .= "Since this event no longer is taking place in a region which is managed by Regional Education, \n";
								$message .= "no further action is required on your part in the system.\n\n";
							}
							$message .= "Information For Reference:\n\n";
							$message .= "OLD Event Information:\n";
							$message .= "Event Title:\t".$event_info["event_title"]."\n";
							$message .= "Region:\t\t".get_region_name($event_info["region_id"])."\n";
							$message .= "Start Date:\t".date(DEFAULT_DATE_FORMAT, $event_info["event_start"])."\n";
							$message .= "Finish Date:\t".date(DEFAULT_DATE_FORMAT, $event_info["event_finish"])."\n\n";
							$message .= "NEW Event Information:\n";
							$message .= "Event Title:\t".html_decode($result["event_title"])."\n";
							$message .= "Region:\t\t".$result["region_name"]."\n";
							$message .= "Start Date:\t".date(DEFAULT_DATE_FORMAT, $result["event_start"])."\n";
							$message .= "Finish Date:\t".date(DEFAULT_DATE_FORMAT, $result["event_finish"])."\n\n";
							if(($apartments) && ($assigned_apartments = @count($apartments))) {
								$message .= "Apartment".(($assigned_apartments != 1) ? "s" : "")." ".$whole_name." was removed from:\n";
								foreach($apartments as $apartment) {
									$message .= "Apartment Title:\t".$apartment["apartment_title"]."\n";
									$message .= "Inhabiting Start:\t".date(DEFAULT_DATE_FORMAT, $apartment["inhabiting_start"])."\n";
									$message .= "Inhabiting Finish:\t".date(DEFAULT_DATE_FORMAT, $apartment["inhabiting_finish"])."\n\n";
								}
							}
							$message .= "=======================================================\n\n";
							$message .= "Deletion Date:\t".date("r", time())."\n";
							$message .= "Deleted By:\t".$_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." (".$_SESSION["details"]["id"].")\n";
						break;
						case "change-non-critical" :
						case "updated" :
						default :
							$message  = "Attention ".$AGENT_CONTACTS["agent-regionaled"]["name"].",\n\n";
							$message .= $_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." has updated an event in ".$whole_name."'s ";
							$message .= "clerkship schedule, to which you had previously assigned housing.\n\n";
							$message .= "Important:\n";
							$message .= "This update does not affect the date or region of this event, as such this change is considered non-critical ";
							$message .= "and no action is required on your part.\n\n";
							$message .= "Information For Reference:\n\n";
							$message .= "OLD Event Information:\n";
							$message .= "Event Title:\t".$event_info["event_title"]."\n";
							$message .= "Region:\t\t".get_region_name($event_info["region_id"])."\n";
							$message .= "Start Date:\t".date(DEFAULT_DATE_FORMAT, $event_info["event_start"])."\n";
							$message .= "Finish Date:\t".date(DEFAULT_DATE_FORMAT, $event_info["event_finish"])."\n\n";
							$message .= "NEW Event Information:\n";
							$message .= "Event Title:\t".html_decode($result["event_title"])."\n";
							$message .= "Region:\t\t".$result["region_name"]."\n";
							$message .= "Start Date:\t".date(DEFAULT_DATE_FORMAT, $result["event_start"])."\n";
							$message .= "Finish Date:\t".date(DEFAULT_DATE_FORMAT, $result["event_finish"])."\n\n";
							if(($apartments) && ($assigned_apartments = @count($apartments))) {
								$message .= "Apartment".(($assigned_apartments != 1) ? "s" : "")." ".$whole_name." is assigned to:\n";
								foreach($apartments as $apartment) {
									$message .= "Apartment Title:\t".$apartment["apartment_title"]."\n";
									$message .= "Inhabiting Start:\t".date(DEFAULT_DATE_FORMAT, $apartment["inhabiting_start"])."\n";
									$message .= "Inhabiting Finish:\t".date(DEFAULT_DATE_FORMAT, $apartment["inhabiting_finish"])."\n\n";
								}
							}
							$message .= "=======================================================\n\n";
							$message .= "Updated Date:\t".date("r", time())."\n";
							$message .= "Update By:\t".$_SESSION["details"]["firstname"]." ".$_SESSION["details"]["lastname"]." (".$_SESSION["details"]["id"].")\n";
						break;
					}
		
					$mail = new Zend_Mail();
					$mail->addHeader("X-Originating-IP", $_SERVER["REMOTE_ADDR"]);
					$mail->addHeader("X-Section", "Clerkship Notify System",true);
					$mail->clearFrom();
					$mail->clearSubject();
					$mail->setFrom($AGENT_CONTACTS["agent-notifications"]["email"], APPLICATION_NAME.' Clerkship System');
					$mail->setSubject("MEdTech Clerkship System - ".ucwords($action)." Event");
					$mail->setBodyText($message);
					$mail->clearRecipients();
					$mail->addTo($AGENT_CONTACTS["agent-regionaled"]["email"], $AGENT_CONTACTS["agent-regionaled"]["name"]);
					$sent = true;
					try {
						$mail->send();
					}
					catch (Exception $e) {
						$sent = false;
					}
					if($sent) {
						return true;
					} else {
						system_log_data("error", "Unable to send ".$action." notification to regional education. PHPMailer said: ".$mail->ErrorInfo);
		
						return false;
					}
				} else {
					return true;
				}
			} else {
				// No need to notify Regional Education because the event is already over, just return true.
				return true;
			}
		} else {
			return true;
		}
	} else {
		system_log_data("error", "The notify_regional_education() function returned false with no results from the database query. Database said: ".$db->ErrorMsg());

		return false;
	}
}

function number_suffix($number) {
	switch ( $number % 10 ){
		case '1': return $number . 'st';
		case '2': return $number . 'nd';
		case '3': return $number . 'rd';
		default:  return $number . 'th';
	}
}

/**
 * This function gets lookup data from the global_lu_roles table
 *
 * @return array $results
 */
function getPublicationRoles() {
    global $db;

    $query = "SELECT *
	FROM `global_lu_roles`
	ORDER BY `role_description`";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the global_lu_roles table
 *
 * @return array $result
 */
function getPublicationRoleSpecificFromID($roleID) {
    global $db;

    $query = "SELECT `role_description`
	FROM `global_lu_roles`
	WHERE `role_id` = '$roleID'";
	
    $result = $db->GetRow($query);
	
	return $result["role_description"];
}

/**
 * This function gets lookup data from the ar_lu_activity_types table
 *
 * @return array $results
 */
function getActivityTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_activity_types`
	ORDER BY `activity_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_clinical_locations table
 *
 * @return array $results
 */
function getClinicalLoactions() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_clinical_locations`
	ORDER BY `clinical_location` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_conference_paper_types table
 *
 * @return array $results
 */
function getConferencePaperTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_conference_paper_types`
	ORDER BY `conference_paper_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_consult_locations table
 *
 * @return array $results
 */
function getConsultLoactions() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_consult_locations`
	ORDER BY `consult_location` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_contribution_types table
 *
 * @return array $results
 */
function getContributionTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_contribution_types`
	ORDER BY `contribution_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_contribution_roles table
 *
 * @return array $results
 */
function getContributionRoles() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_contribution_roles`
	ORDER BY `contribution_role` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_degree_types table
 *
 * @return array $results
 */
function getDegreeTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_degree_types`
	ORDER BY `degree_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_education_locations table
 *
 * @return array $results
 */
function getEducationLocations() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_education_locations`
	ORDER BY `education_location` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_focus_groups table
 *
 * @return array $results
 */
function getPublicationGroups() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_focus_groups`
	ORDER BY `focus_group` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_hospital_location table
 *
 * @return array $results
 */
function getPublicationHospitals() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_hospital_location`
	ORDER BY `hosp_desc` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_innovation_types table
 *
 * @return array $results
 */
function getInnovationTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_innovation_types`
	ORDER BY `innovation_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_membership_roles table
 *
 * @return array $results
 */
function getMembershipRoles() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_membership_roles`
	ORDER BY `membership_role` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_on_call_locations table
 *
 * @return array $results
 */
function getOnCallLocations() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_on_call_locations`
	ORDER BY `on_call_location` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_other_locations table
 *
 * @return array $results
 */
function getOtherLocations() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_other_locations`
	ORDER BY `other_location` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_patent_types table
 *
 * @return array $results
 */
function getPatentTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_patent_types`
	ORDER BY `patent_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_prize_categories table
 *
 * @return array $results
 */
function getPrizeCategories() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_prize_categories`
	ORDER BY `prize_category` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_prize_types table
 *
 * @return array $results
 */
function getPrizeTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_prize_types`
	ORDER BY `prize_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_profile_roles table
 *
 * @return array $results
 */
function getProfileRoles() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_profile_roles`
	ORDER BY `profile_role` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_publication_statuses table
 *
 * @return array $results
 */
function getPulicationStatuses() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_publication_statuses`
	ORDER BY `publication_status` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_publication_type table
 *
 * @return array $results
 */
function getPublicationTypesSpecific($type) {
    global $db;
	
    if(is_array($type)) {
    	foreach($type as $typeDesc) {
    		if(isset($where)) {
    			$where .= " OR `type_description` = '".$typeDesc."'";
    		} else {
    			$where = " `type_description` = '".$typeDesc."'";	
    		}
    	}
    	$query = "SELECT *
		FROM `ar_lu_publication_type`
		WHERE ".$where."
		ORDER BY `type_description`";
    } else {
	    $query = "SELECT *
		FROM `ar_lu_publication_type`
		WHERE `type_description` LIKE '$type%'
		ORDER BY `type_description`";
    }
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_publication_type table
 *
 * @return array $result
 */
function getPublicationTypesSpecificFromID($type_id) {
    global $db;
	    
    $query = "SELECT `type_description`
	FROM `ar_lu_publication_type`
	WHERE `type_id`= '$type_id'";
    
    $result = $db->GetRow($query);
	
	return $result["type_description"];
}

/**
 * This function gets lookup data from the ar_lu_publication_type table
 *
 * @return array $results
 */
function getPublicationTypes() {
    global $db;
	
    $query = "SELECT *
	FROM `ar_lu_publication_type`
	ORDER BY `type_description`";

    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_research_types table
 *
 * @return array $results
 */
function getResearchTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_research_types`
	ORDER BY `research_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_scholarly_types table
 *
 * @return array $results
 */
function getScholarlyTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_scholarly_types`
	ORDER BY `scholarly_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_self_education_types table
 *
 * @return array $results
 */
function getSelfEducationTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_self_education_types`
	ORDER BY `self_education_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_supervision_types table
 *
 * @return array $results
 */
function getSupervisionTypes() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_supervision_types`
	ORDER BY `supervision_type` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_trainee_levels table
 *
 * @return array $results
 */
function getTraineeLevels() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_trainee_levels`
	ORDER BY `trainee_level` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets lookup data from the ar_lu_undergraduate_supervision_courses table
 *
 * @return array $results
 */
function getUndergraduateSupervisionCourses() {
    global $db;

    $query = "SELECT *
	FROM `ar_lu_undergraduate_supervision_courses`
	ORDER BY `undergarduate_supervision_course` ASC";
	
    $results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function builds an array of default enrollment numbers using lookup data from the events_lu_eventtypes table
 *
 * @return array $defaultEnrollmentArray
 */
function getDefaultEnrollment() {
    global $db;

    $query = "SELECT `eventtype_id`, `eventtype_title`, `eventtype_default_enrollment`
	FROM `events_lu_eventtypes`
	WHERE `eventtype_default_enrollment` IS NOT NULL
	ORDER BY `eventtype_default_enrollment` DESC";
    
    $results = $db->GetAll($query);
    
    $defaultEnrollmentArray = array();
	
    foreach($results as $result) {
    	$defaultEnrollmentArray[$result["eventtype_id"]] = array("title" => $result["eventtype_title"], "default_enrollment" => $result["eventtype_default_enrollment"]);
    }
	return $defaultEnrollmentArray;
}

/**
 * This function gets number from the user_data table
 *
 * @param int $proxy_id
 * @return array result["number"]
 */
function getNumberFromProxy($proxy_id) {
    global $db;

    $query = "SELECT `number`
	FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=". $db->qstr($proxy_id);
    
    $result = $db->GetRow($query);
    
	return $result["number"];
}

function userMKDir($dir)
{
	// may just need to be chmoded		
	if(@is_dir($dir))
	{
		chmod($dir, 0777);
	}
	else 
	{
		$oldumask = @umask(0);
		!@mkdir($dir, 0777);
		@umask($oldumask);
	}
}

/**
 * Function to display blurb on default enrollment numbers in education.
 *
 * @return string containing the HTML of the message or false if there is no HTML due to database connectivity problems.
 */
function display_default_enrollment($reportMode = false) {
	global $db;

	$output_html = "";
	
	$query = "SELECT `eventtype_title`, `eventtype_default_enrollment` FROM `events_lu_eventtypes` WHERE `eventtype_active` = '1' ORDER BY `eventtype_default_enrollment`";	
	
	if($results = $db->GetAll($query)) {
		$previous = "";
		$outputLine = array();
		
		$output_html .= "<div id=\"display-error-box\" class=\"display-generic\">\n";
		$output_html .= "The following average enrollment numbers are implied";
		if(!$reportMode) {
			$output_html .= ". If yours differ substantially then please note this in the comments.";
		} else {
			$output_html .= ":";
		}
		$output_html .= "	<ul>\n";
		foreach($results as $result) {
			if($previous != "" && $previous != $result["eventtype_default_enrollment"]) {
				$output = implode(", ", $outputLine);
				
				$output_html .= "	<li>".$previous. " - " .$output."</li>\n";
				$outputLine = array();
				
				$outputLine[] = $result["eventtype_title"];
			} else {
				$outputLine[] = $result["eventtype_title"];
			}
			$previous = $result["eventtype_default_enrollment"];
		}
		$output = implode(", ", $outputLine);
				
		$output_html .= "	<li>".$previous. " - " .$output."</li>\n";
		$output_html .= "	</ul>\n";
		$output_html .= "</div>\n";
	}

	return (($output_html) ? $output_html : false);
}

/**
 * Function will return all pages below the specified parent_id, the current user has access to.
 *
 * @param int $identifier
 * @param int $indent
 * @return string
 */
function objectives_inlists($identifier = 0, $indent = 0) {
	global $db, $MODULE;

	if($indent > 99) {
		die("Preventing infinite loop");
	}

	$selected				= 0;

	$identifier	= (int) $identifier;
	$output		= "";

	if(($identifier) && ($indent === 0)) {
		$query	= "	SELECT * FROM `global_lu_objectives` 
					WHERE `objective_parent` = '0' 
					AND `objective_active` = '1' 
					ORDER BY `objective_order` ASC";
	} else {
		$query	= "	SELECT * FROM `global_lu_objectives` 
					WHERE `objective_parent` = ".$db->qstr($identifier)." 
					AND `objective_active` = '1' 
					ORDER BY `objective_order` ASC";
	}
	if ($indent < 1) {
		?>
		<script type="text/javascript">
		function showObjectiveChildren(objective_id) {
			if (!$(objective_id+'-children').visible()) {
				$('objective-'+objective_id+'-arrow').src = '<?php echo ENTRADA_URL; ?>/images/arrow-asc.gif';
				Effect.BlindDown(objective_id+'-children'); 
			} else { 
				$('objective-'+objective_id+'-arrow').src = '<?php echo ENTRADA_URL; ?>/images/arrow-right.gif';
				Effect.BlindUp(objective_id+'-children');
			}
		}
		</script>
		<?php
	}
	$results	= $db->GetAll($query);
	if($results) {
		$output .= "<ul class=\"objectives-list\" id=\"".$identifier."-children\" ".($indent > 0 ? "style=\"display: none;\" " : "").">";
		foreach ($results as $result) {
			$output .= "<li id=\"content_".$result["objective_id"]."\">\n";
			$output .= "<div class=\"objective-container\">";
			$output .= "	<span class=\"delete\"><input type=\"checkbox\" id=\"delete_".$result["objective_id"]."\" name=\"delete[".$result["objective_id"]."][objective_id]\" value=\"".$result["objective_id"]."\"".(($selected == $result["objective_id"]) ? " checked=\"checked\"" : "")." onclick=\"$$('#".$result["objective_id"]."-children input[type=checkbox]').each(function(e){e.checked = $('delete_".$result["objective_id"]."').checked; if (e.checked) e.disable(); else e.enable();});\"/></span>\n";
			$output .= "	<span class=\"next\">";
			$query = "	SELECT * FROM `global_lu_objectives` 
						WHERE `objective_parent` = ".$db->qstr($result["objective_id"])." 
						AND `objective_active` = '1' 
						ORDER BY `objective_order` ASC";
			if ($db->GetAll($query)) {
				$has_children = true;
			} else {
				$has_children = false;
			}
			if ($has_children) {
				$output .= "	<a class=\"objective-expand\" onclick=\"showObjectiveChildren('".$result["objective_id"]."')\"><img id=\"objective-".$result["objective_id"]."-arrow\" src=\"".ENTRADA_URL."/images/arrow-right.gif\" style=\"border: none; text-decoration: none;\" /></a>";
			}
			$output .= "	&nbsp;<a href=\"".ENTRADA_URL."/admin/objectives?".replace_query(array("section" => "edit", "step" => 1, "id" => $result["objective_id"]))."\">";
			$output .= html_encode($result["objective_name"])."</a></span>\n";
			$output .= "</div>";
			$output .= objectives_inlists($result["objective_id"], $indent + 1);
			$output .= "</li>\n";

		}
		$output .= "</ul>";
	}

	return $output;
}

/**
 * Function will return all objectives below the specified parent_id, as option elements of an input select.
 * This is a recursive function that has a fall-out of 99 runs.
 *
 * @param int $parent_id
 * @param array $current_selected
 * @param int $indent
 * @param array $exclude
 * @return string
 */
function objectives_inselect($parent_id = 0, &$current_selected, $indent = 0, &$exclude = array()) {
	global $db, $MODULE, $COMMUNITY_ID;

	if($indent > 99) {
		die("Preventing infinite loop");
	}

	if(!is_array($current_selected)) {
		$current_selected = array($current_selected);
	}

	$output	= "";
	$query	= "	SELECT * FROM `global_lu_objectives` 
				WHERE `objective_active` = '1' 
				AND `objective_parent` = ".$db->qstr($parent_id)." 
				ORDER BY `objective_id` ASC";
	$results	= $db->GetAll($query);
	if($results) {
		foreach ($results as $result) {
			if((!@in_array($result["objective_id"], $exclude)) && (!@in_array($parent_id, $exclude))) {
				$output .= "<option value=\"".(int) $result["objective_id"]."\"".((@in_array($result["objective_id"], $current_selected)) ? " selected=\"selected\"" : "").">".str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", $indent).(($indent > 0) ? "&rarr;&nbsp;" : "").html_encode($result["objective_name"])."</option>\n";
			} else {
				$exclude[] = (int) $result["objective_id"];
			}
			$output .= objectives_inselect($result["objective_id"], $current_selected, $indent + 1, $exclude, $community_id);
		}
	}

	return $output;
}

/**
 * Function will delete all pages below the specified parent_id.
 *
 * @param int $parent_id
 * @return true
 */
function objectives_delete($objective_id = 0, $children_move_target = 0, $level = 0) {
	global $db, $deleted_count;

	if($level > 99) {
		application_log("error", "Stopped an infinite loop in the objectives_delete() function.");

		return false;
	}
	
	if($objective_id = (int) $objective_id) {
		$query = "	UPDATE `global_lu_objectives` 
					SET `objective_active` = '0' 
					WHERE `objective_id` = ".$db->qstr($objective_id);
		if(!$db->Execute($query)) {
			application_log("error", "Unable to deactivate objective_id [".$objective_id."]. Database said: ".$db->ErrorMsg());
			$success = false;
		} else {
			$success = true;
			if ($level) {
				$deleted_count++;
			}
		}
		if($children_move_target === false) {
			$query		= "	SELECT `objective_id` FROM `global_lu_objectives` 
							WHERE `objective_active` = '1' 
							AND `objective_parent` = ".$db->qstr($objective_id);
			$results	= $db->GetAll($query);
			if($results) {
				foreach ($results as $result) {
					$success = objectives_delete($result["objective_id"], 0, $level+1);
				}
			}
		}
	}
	return $success;
}

/**
 * Function will return all objectives below the specified objective_parent.
 *
 * @param int $identifier
 * @param int $indent
 * @return string
 */
function objectives_intable($identifier = 0, $indent = 0, $excluded_objectives = false) {
	global $db, $ONLOAD;

	if($indent > 99) {
		die("Preventing infinite loop");
	}
	
	$selected				= 0;
	$selectable_children	= true;
	
	
	$identifier	= (int) $identifier;
	$output		= "";
	
	if(($identifier)) {
		$query	= "	SELECT * FROM `global_lu_objectives` 
					WHERE `objective_id` = ".$db->qstr((int)$identifier)." 
					AND `objective_active` = '1' 
					ORDER BY `objective_order` ASC";
	}

	$result	= $db->GetRow($query);
	if($result) {
		$output .= "<tr id=\"content_".$result["objective_id"]."\">\n";
		$output .= "	<td>&nbsp;</td>\n";
		$output .= "	<td style=\"padding-left: ".($indent * 25)."px; vertical-align: middle\">";
		$output .= "		<img src=\"".ENTRADA_URL."/images/record-next-off.gif\" width=\"11\" height=\"11\" border=\"0\" alt=\"\" title=\"\" style=\"vertical-align: middle; margin-right: 5px\" />";
		$output .= "		".html_encode($result["objective_name"]);
		$output .= "		<input type=\"hidden\" name=\"delete[".((int)$identifier)."][objective_id]\" value=\"".((int)$identifier)."\" />";
		$output .= "</td>\n";
		$output .= "</tr>\n";
		$query = "	SELECT COUNT(`objective_id`) FROM `global_lu_objectives` 
					WHERE `objective_active` = '1'
					GROUP BY `objective_parent`
					HAVING `objective_parent` = ".$db->qstr((int)$identifier);
		$children = $db->GetOne($query);
		if ($children) {
			$output .= "<tbody id=\"delete-".((int)$identifier)."-children\">";
			$output .= "</tbody>";
			$output .= "	<tr>";
			$output .= "		<td>&nbsp;</td>\n";
			$output .= "		<td style=\"vertical-align: top;\">";
			$output .= "		<div style=\"padding-left: 30px\">";
			$output .= "		<span class=\"content-small\">There are children residing under <strong>".$result["objective_name"]."</strong>.</span>";
			$output .= "		</div>";
			$output .= "		<div style=\"padding-left: 30px\">";
			$output .= "			<input type=\"radio\" name=\"delete[".((int)$identifier)."][move]\" id=\"delete_".((int)$identifier)."_children\" value=\"0\" onclick=\"$('move-".((int)$identifier)."-children').hide();\" checked=\"checked\"/>";
			$output .= "			<label for=\"delete_".((int)$identifier)."_children\" class=\"form-nrequired\"><strong>Deactivate</strong> all children</label>";
			$output .= "			<br/>";
			$output .= "			<input type=\"radio\" name=\"delete[".((int)$identifier)."][move]\" id=\"move_".((int)$identifier)."_children\" value=\"1\" onclick=\"$('move-".((int)$identifier)."-children').show();\" />";
			$output .= "			<label for=\"move_".((int)$identifier)."_children\" class=\"form-nrequired\"><strong>Move</strong> all children</label>";
			$output .= "			<br/><br/>";
			$output .= "		</div>";
			$output .= "		</td>";
			$output .= "	</tr>";
			$output .= "<tbody id=\"move-".((int)$identifier)."-children\" style=\"display: none;\">";
			$output .= "	<tr>";
			$output .= "		<td>&nbsp;</td>\n";
			$output .= "		<td style=\"vertical-align: top; padding: 0px 0px 0px 30px\">";
			$output .= "			<div id=\"selectParent".(int)$identifier."Field\"></div>";
			$output .= "		</td>";
			$output .= "	</tr>";
			$output .= "	<tr>";
			$output .= "		<td colspan=\"2\">&nbsp;</td>";
			$output .= "	</tr>";
			$output .= "</tbody>";
			$ONLOAD[]	= "selectObjective(0, ".$identifier.", '".$excluded_objectives."')";

		}
	}
	
	return $output;
}


/**
 * Produces an option tag with the values filled in
 * @param unknown_type $value
 * @param unknown_type $label
 * @param unknown_type $selected
 * @return String Returns an html string for an option tag.
 */
function build_option($value, $label, $selected = false) {
	return "<option value='".$value."'". ($selected ? "selected='selected'" : "") .">".$label."</option>\n";
}



/**
 * routine to display standard status messages, Error, Notice, and Success
 * @param bool $fade true if the messages should fade out 
 */
function display_status_messages($fade = false) {
	echo "<div class=\"status_messages\">";
	if (has_error()) {
		if ($fade) fade_element("out", "display-error-box");
		echo display_error();
	}

	if (has_success()) {
		if ($fade) fade_element("out", "display-success-box");
		echo display_success();
	}

	if (has_notice()) {
		if ($fade) fade_element("out", "display-notice-box");
		echo display_notice();
	}
	echo "</div>";
}

/**
 * Returns formatted mspr data supporting getDetails(), at this time only Leaves of absence, formal remdiation, and disciplinary actions 
 */
function display_mspr_details($data) {
	ob_start();
	?>
	<ul class="mspr-list">
	<?php
	if ($data && ($data->count() > 0)) {
		foreach($data as $datum) {
		?>
		<li class="entry">
			<?php echo clean_input($datum->getDetails(), array("notags", "specialchars")) ?>
		</li>	
		<?php 
		}
	} else {
		?>
		<li>
		None
		</li>	
		<?php
	}
	?>
	</ul>	
	<?php
	return ob_get_clean();
}

/**
 * Adds require_onces for all of the models needed for MSPRs
 */
function require_mspr_models() {
	
	require_once("Models/users/User.class.php");
	
	require_once("Models/utility/Approvable.interface.php");
	require_once("Models/utility/AttentionRequirable.interface.php");
	
	require_once("Models/awards/InternalAwardReceipts.class.php");
	
	require_once("Models/mspr/ExternalAwardReceipts.class.php");
	require_once("Models/mspr/Studentships.class.php");
	require_once("Models/mspr/ClinicalPerformanceEvaluations.class.php");
	require_once("Models/mspr/Contributions.class.php");
	require_once("Models/mspr/DisciplinaryActions.class.php");
	require_once("Models/mspr/LeavesOfAbsence.class.php");
	require_once("Models/mspr/FormalRemediations.class.php");
	require_once("Models/mspr/ClerkshipRotations.class.php");
	require_once("Models/mspr/StudentRunElectives.class.php");
	require_once("Models/mspr/Observerships.class.php");
	require_once("Models/mspr/InternationalActivities.class.php");
	require_once("Models/mspr/CriticalEnquiry.class.php");
	require_once("Models/mspr/CommunityHealthAndEpidemiology.class.php");
	require_once("Models/mspr/ResearchCitations.class.php");
}

/**
 * converts a month number (1-12) into a month name (January-December)
 * @param int $month_number
 */
function getMonthName($month_number) {
	static $months;

	//initialization of static if not done
	if (!$months) {
		$months=array();
		for($month_num = 1; $month_num <= 12; $month_num++) {
			$time = mktime(0,0,0,$month_num,1);
			$month_name= date("F", $time); 
			$months[$month_num] = $month_name;
		}
	}
	//the -1 and +1 are to ensure the month num is from 1 to 12, not 0 to 11. The mod is done to  ensure the value is ithin bounds
	$month_number = (($month_number - 1) % 12) + 1;
	
	$month_name = $months[$month_number];
	return $month_name;
}

/**
 * Given two dates, this function will return a human-readable range  
 * @param array $start_date {"d" => day, "m" => month, "y" => year}
 * @param array $end_date {"d" => day, "m" => month, "y" => year}
 */
function formatDateRange($start_date, $end_date) {

	$ds = $start_date["d"];
	$ms = $start_date["m"];
	$ys = $start_date["y"];
	
	$de = $end_date['d'];
	$me = $end_date['m'];
	$ye = $end_date['y'];

	//first determine if the range should be 
	//year - year, or month year - month year
	//month month year or just year
	if ($ye && $ye != $ys) {
		if ($ms || $me){
			//if one of them is mising assume they are the same
			if (!$me) {
				$me = $ms;
			} elseif(!$ms) {
				$ms = $me;
			}
			if ($ds || $de) {
				//if one of them is mising assume they are the same
				if (!$de) {
					$de = $ds;
				} elseif(!$ds) {
					$ds = $de;
				}
				//full case: month day, year - month day, year
				$start = getMonthName($ms) . " " . $ds . ", " . $ys;
				$end = getMonthName($me) .   " " . $de . ", " . $ye;
			} else {
				//no day info: month year - month year
				$start = getMonthName($ms) . " " . $ys;
				$end = getMonthName($me) .   " " . $ye;
			}
			$period = $start . " - " . $end;
		} else {
			//year range without months at all...
			$period = $ys . " - " . $ye;
			//no check for days because days without months would be meaningless.
		}
		
	} else {
		//there is either no end year, or the end year is the same as the start year (equivalent)
		if ($ms || $me){
			if (!$me) {
				$me = $ms;
			} elseif(!$ms) {
				$ms = $me;
			}
			
			if ($me == $ms) {
				$month_name = getMonthName($ms);
				if ($ds || $de) {
					if ($ds && $de && $ds != $de) {
						$period = $month_name . " " . $ds . " - " . $de . ", " . $ys;
					} else {
						$period = $month_name . " " . ($ds ? $ds : $de) . ", " . $ys;
					}
				} else {
					$period = $month_name . " " . $ys;
				}
			} else {	
				//months are different.		
				if ($de || $ds) {
					//we already have a range 
					
					//assume same start and end day if only one exists
					if (!$de) {
						$de = $ds;
					} elseif(!$ds) {
						$ds = $de;
					}
					$start = getMonthName($ms) . " " . $ds;
					$end = getMonthName($me) . " " . $de .  ", " . $ys;
				} else {
					//no day info...
					$start = getMonthName($ms);
					$end = getMonthName($me) .   " " . $ys;
				}
				$period = $start . " - " . $end;
			}
			
			
		} else {
			//single year entry
			$period = $ys;
		}
	}
	return $period;
}

/**
 * This function gets all of the departments a user is in
 * @param string $user_id
 * @return array $results
 */
function get_user_departments($user_id) {
	global $db;
	
	$query = "	SELECT `department_title`, `department_id` 
				FROM `".AUTH_DATABASE."`.`user_departments`, `".AUTH_DATABASE."`.`departments` 
				WHERE `user_id`=".$db->qstr($user_id)."
				AND `dep_id` = `department_id`";
	
	$results = $db->GetAll($query);
	
	return $results;
}

/**
 * This function gets determines if a user is a department head
 * @param int $user_id
 * @return int $department_id, bool returns false otherwise
 */
function is_department_head($user_id) {
	global $db;
	
	$query = "	SELECT `department_id` 
				FROM `".AUTH_DATABASE."`.`department_heads`
				WHERE `user_id`=".$db->qstr($user_id);
	
	if($result = $db->GetRow($query)) {	
		return $result["department_id"];
	} else {
		return false;
	}
}

/**
 * This function generates a 2 dimensional array of the competencies
 * and the courses which they are associated with, used for building
 * a table to display the aforementioned matrix.
 * 
 * @return array $obectives
 */
function objectives_build_course_competencies_array() {
	global $db;
	$courses_array = array();
	$query = "	SELECT a.*, b.`curriculum_type_name` FROM `courses` AS a
				LEFT JOIN `curriculum_lu_types` AS b
				ON a.`curriculum_type_id` = b.`curriculum_type_id`
				WHERE (
					a.`course_id` IN (
						SELECT DISTINCT(`course_id`) FROM `course_objectives`
						WHERE `objective_type` = 'course'
					)
					OR b.`curriculum_type_active` = '1'
				)
				AND a.`course_active` = 1
				AND a.`organisation_id` = ".$db->qstr($_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["organisation_id"])."
				ORDER BY a.`curriculum_type_id` ASC, a.`course_code` ASC";
	$courses = $db->GetAll($query);
	if ($courses) {
		$last_term_name = "";
		$term_course_id = 0;
		$count = 0;
		foreach ($courses as $course) {
			$courses_array["courses"][$course["course_id"]]["competencies"] = array();
			$courses_array["competencies"] = array();
			$courses_array["courses"][$course["course_id"]]["course_name"] = $course["course_name"];
			$courses_array["courses"][$course["course_id"]]["term_name"] = (isset($course["curriculum_type_name"]) && $course["curriculum_type_name"] ? $course["curriculum_type_name"] : "Other courses");
		}
		$reorder_courses = $courses_array["courses"];
		$courses_array["courses"] = array();
		foreach ($reorder_courses as $course_id => $course) {
			if (isset($course["term_name"]) && $course["term_name"] != "Other courses") {
				$courses_array["courses"][$course_id] = $course;
			}
		}
		foreach ($reorder_courses as $course_id => $course) {
			if (!isset($course["term_name"]) || $course["term_name"] == "Other courses") {
				$courses_array["courses"][$course_id] = $course;
			}
		}
		
		foreach ($courses_array["courses"] as $course_id => &$course) {
			$course["new_term"] = ((isset($last_term_name) && $last_term_name && $last_term_name != $course["term_name"]) ? true : false);
			if ($last_term_name != $course["term_name"]) {
				$last_term_name = (isset($course["term_name"]) && $course["term_name"] ? $course["term_name"] : "Other courses");
				if ($term_course_id) {
					$courses_array["courses"][$term_course_id]["total_in_term"] = $count;
				}
				$term_course_id = $course_id;
				$count = 1;
			} else {
				$count++;
			}
		}
		if ($term_course_id) {
			$courses_array["courses"][$term_course_id]["total_in_term"] = $count;
		}
		
		$query = "	SELECT * FROM `global_lu_objectives`
					WHERE `objective_parent` IN (
						SELECT `objective_id` FROM `global_lu_objectives`
						WHERE `objective_parent` = ".$db->qstr(CURRICULAR_OBJECTIVES_PARENT_ID)."
					)";
		$competencies = $db->GetAll($query);
		if ($competencies && count($competencies)) {
			foreach ($competencies as $competency) {
				$courses_array["competencies"][$competency["objective_id"]] = $competency["objective_name"]; 
				$objective_ids_string = objectives_build_objective_descendants_id_string($competency["objective_id"], $db->qstr($competency["objective_id"]));
				if ($objective_ids_string) {
					foreach ($courses_array["courses"] as $course_id => &$course) {
						$query = "	SELECT MIN(`importance`) as `importance` FROM `course_objectives`
									WHERE `objective_type` = 'course'
									AND `course_id` = ".$db->qstr($course_id)."
									AND `objective_id` IN (".$objective_ids_string.")";
						$found = $db->GetRow($query);
						if ($found) {
							$course["competencies"][$competency["objective_id"]] = $found["importance"];
						} else {
							$course["competencies"][$competency["objective_id"]] = false;
						}
					}
				}
			}
		}
	}
	return $courses_array;
}

/**
 * This function returns a string containing all of the objectives which
 * are descendants of the objective_id received.
 * 
 * @param $objective_id
 * @param $objective_ids_string
 * @return $objective_ids_string
 */
function objectives_build_objective_descendants_id_string($objective_id = 0, $objective_ids_string = "") {
	global $db;
	$query = "	SELECT `objective_id` FROM `global_lu_objectives`
				WHERE `objective_parent` = ".$db->qstr($objective_id);
	$objective_ids = $db->GetAll($query);
	if ($objective_ids) {
		foreach ($objective_ids as $objective_id) {
			if ($objective_ids_string) {
				$objective_ids_string .= ", ".$db->qstr($objective_id["objective_id"]);
			} else {
				$objective_ids_string = $db->qstr($objective_id["objective_id"]);
			}
			$objective_ids_string = objectives_build_objective_descendants_id_string($objective_id["objective_id"], $objective_ids_string);
		}
	}
	return $objective_ids_string;
}

/**
 * This function returns a string containing all of the objectives which
 * are attached to the selected course.
 * 
 * @param $objective_id
 * @param $objective_ids_string
 * @return $objective_ids_string
 */
function objectives_build_course_objectives_id_string($course_id = 0) {
	global $db;
	$query = "	SELECT `objective_id` FROM `course_objectives`
				WHERE `course_id` = ".$db->qstr($course_id);
	$objective_ids = $db->GetAll($query);
	if ($objective_ids) {
		$objective_ids_string = false;
		foreach ($objective_ids as $objective_id) {
			if ($objective_ids_string) {
				$objective_ids_string .= ", ".$db->qstr($objective_id["objective_id"]);
			} else {
				$objective_ids_string = $db->qstr($objective_id["objective_id"]);
			}
		}
		return $objective_ids_string;
	}
	return false;
}

/**
 * This function returns a string containing all of the courses which
 * are attached to the selected competency.
 * 
 * @param $objective_id
 * @param $objective_ids_string
 * @return $objective_ids_string
 */
function objectives_competency_courses($competency_id = 0) {
	global $db;
	$query = "	SELECT a.*, MIN(b.`importance`) AS `importance` 
				FROM `courses` AS a
				JOIN `course_objectives` AS b
				ON a.`course_id` = b.`course_id`
				AND `objective_id` IN (".objectives_build_objective_descendants_id_string($competency_id).")
				GROUP BY a.`course_id`";
	$courses = $db->GetAll($query);
	if ($courses) {
		$courses_array = false;
		foreach ($courses as $course) {
			$courses_array[$course["course_id"]] = $course;
		}
	}
	return $courses_array;
}

/**
 * @author http://roshanbh.com.np/2008/05/date-format-validation-php.html
 * @param string $date
 * @return bool
 */
function checkDateFormat($date) {
  //match the format of the date
  if (preg_match ("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", $date, $parts))
  {
    //check weather the date is valid of not
        if(checkdate($parts[2],$parts[3],$parts[1]))
          return true;
        else
         return false;
  }
  else
    return false;
}

/**
 * Easier method for writing a file.
 * @param string $filename
 * @param string $contents
 * @return bool Returns false on error; true otherwise.
 */
function writeFile($filename, $contents) {
	if (!($res = fopen($filename, "w"))) {
		return false;
	}
	if (!fwrite($res,$contents)) {
		return false;
	}
	if(!fclose($res)) {
		return false;
	}		
	return true;
}

/**
 * Generates a PDF file from the string of html provided. If a filename is supplied, it will be written to the file; otherwise it will be returned from the function
 * @param unknown_type $html
 * @param unknown_type $output_filename
 */
function generatePDF($html,$output_filename=null) {
	global $APPLICATION_PATH;
	@set_time_limit(0);
	if((is_array($APPLICATION_PATH)) && (isset($APPLICATION_PATH["htmldoc"])) && (@is_executable($APPLICATION_PATH["htmldoc"]))) {

		//This used to have every option separated by a backslash and newline. In testing it was discovered that there was a magical limit of 4 backslashes -- beyond which it would barf.
		$exec_command	= $APPLICATION_PATH["htmldoc"]." \
		--format pdf14 --charset ".DEFAULT_CHARSET." --size Letter --pagemode document --no-duplex --encryption --compression=6 --permissions print,no-modify \
		--header ... --footer ... --headfootsize 0 --browserwidth 800 --top 1cm --bottom 1cm --left 2cm --right 2cm --embedfonts --bodyfont Times --headfootsize 8 \
		--headfootfont Times --headingfont Times --firstpage p1 --quiet --book --color --no-toc --no-title --no-links --textfont Times - ";
		
		if ($output_filename) {
			@exec($exec_command);
			@exec("chmod 644 ".$output_filename);
		} else {
			/**
			 * This section needs a little explanation.
			 * 
			 * exec and shell_exec were not used because they cannot receive standard input.
			 * proc_open allows the specification of pipes (or files) for standard input/output/error
			 * hence the descriptorsepc array specifiying pipes for all three
			 * and writing to pipe[0] for standard input
			 * and reading the stream from pipe[1] for standard output.
			 */
			
			$descriptorspec = array(
			   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			   2 => array("pipe", "w")   // stderr is a pipe that the child will write to
			);
			
			$proc = proc_open($exec_command, $descriptorspec, $pipes);
			
			fwrite($pipes[0], $html);
			fclose($pipes[0]);

			$pdf_string = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			
			//$err_string = stream_get_contents($pipes[2]);
			fclose($pipes[2]); //just close we're not interested in the error info
			
			$return_val = proc_close($proc);
			
			return $pdf_string;
		}
	}
}

/**
 * Function used by public events and admin events index to output the HTML for the calendar controls.
 */
function objectives_output_calendar_controls() {
	global $display_duration, $page_current, $total_pages, $OBJECTIVE_ID, $COURSE_ID;
	?>
	<table style="width: 100%; margin: 10px 0px 10px 0px" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td style="width: 53%; vertical-align: top; text-align: left">
				<table style="width: 298px; height: 23px" cellspacing="0" cellpadding="0" border="0" summary="Display Duration Type">
					<tr>
						<td style="width: 22px; height: 23px"><a href="<?php echo ENTRADA_URL."/courses/objectives?".replace_query(array("dstamp" => ($display_duration["start"] - 2))); ?>" title="Previous <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]); ?>"><img src="<?php echo ENTRADA_URL; ?>/images/cal-back.gif" border="0" width="22" height="23" alt="Previous <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]); ?>" title="Previous <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]); ?>" /></a></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"] == "day") ? "<img src=\"".ENTRADA_URL."/images/cal-day-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Day View\" title=\"Day View\" />" : "<a href=\"".ENTRADA_URL."/courses/objectives?".replace_query(array("dtype" => "day"))."\"><img src=\"".ENTRADA_URL."/images/cal-day-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Day View\" title=\"Day View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"] == "week") ? "<img src=\"".ENTRADA_URL."/images/cal-week-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Week View\" title=\"Week View\" />" : "<a href=\"".ENTRADA_URL."/courses/objectives?".replace_query(array("dtype" => "week"))."\"><img src=\"".ENTRADA_URL."/images/cal-week-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Week View\" title=\"Week View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"] == "month") ? "<img src=\"".ENTRADA_URL."/images/cal-month-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Month View\" title=\"Month View\" />" : "<a href=\"".ENTRADA_URL."/courses/objectives?".replace_query(array("dtype" => "month"))."\"><img src=\"".ENTRADA_URL."/images/cal-month-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Month View\" title=\"Month View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px"><?php echo (($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"] == "year") ? "<img src=\"".ENTRADA_URL."/images/cal-year-on.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Year View\" title=\"Year View\" />" : "<a href=\"".ENTRADA_URL."/courses/objectives?".replace_query(array("dtype" => "year"))."\"><img src=\"".ENTRADA_URL."/images/cal-year-off.gif\" width=\"47\" height=\"23\" border=\"0\" alt=\"Year View\" title=\"Year View\" /></a>"); ?></td>
						<td style="width: 47px; height: 23px; border-left: 1px #9D9D9D solid"><a href="<?php echo ENTRADA_URL."/courses/objectives?".replace_query(array("dstamp" => ($display_duration["end"] + 1))); ?>" title="Following <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]); ?>"><img src="<?php echo ENTRADA_URL; ?>/images/cal-next.gif" border="0" width="22" height="23" alt="Following <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]); ?>" title="Following <?php echo ucwords($_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]); ?>" /></a></td>
						<td style="width: 33px; height: 23px; text-align: right"><a href="<?php echo ENTRADA_URL.$module_type; ?>/events?<?php echo replace_query(array("dstamp" => time())); ?>"><img src="<?php echo ENTRADA_URL; ?>/images/cal-home.gif" width="23" height="23" alt="Reset to display current calendar <?php echo $_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]; ?>." title="Reset to display current calendar <?php echo $_SESSION[APPLICATION_IDENTIFIER]["objectives"]["dtype"]; ?>." border="0" /></a></td>
						<td style="width: 33px; height: 23px; text-align: right"><img src="<?php echo ENTRADA_URL; ?>/images/cal-calendar.gif" width="23" height="23" alt="Show Calendar" title="Show Calendar" onclick="showCalendar('', document.getElementById('dstamp'), document.getElementById('dstamp'), '<?php echo html_encode($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dstamp"]); ?>', 'calendar-holder', 8, 8, 1)" style="cursor: pointer" id="calendar-holder" /></td>
					</tr>
				</table>
			</td>
			<td style="width: 47%; vertical-align: top; text-align: right">
				<?php
				if ($total_pages > 1) {
					echo "<form action=\"".ENTRADA_URL."/courses/objectives\" method=\"get\" id=\"pageSelector\">\n";
					echo "<input type=\"hidden\" name=\"oid\" value=\"".$OBJECTIVE_ID."\" />\n";
					echo "<input type=\"hidden\" name=\"cid\" value=\"".$COURSE_ID."\" />\n";
					echo "<div style=\"white-space: nowrap\">\n";
					echo "<span style=\"width: 20px; vertical-align: middle; margin-right: 3px; text-align: left\">\n";
					if (($page_current - 1)) {
						echo "<a href=\"".ENTRADA_URL."/courses/objectives?".replace_query(array("pv" => ($page_current - 1)))."\"><img src=\"".ENTRADA_URL."/images/record-previous-on.gif\" border=\"0\" width=\"11\" height=\"11\" alt=\"Back to page ".($page_current - 1).".\" title=\"Back to page ".($page_current - 1).".\" style=\"vertical-align: middle\" /></a>\n";
					} else {
						echo "<img src=\"".ENTRADA_URL."/images/record-previous-off.gif\" width=\"11\" height=\"11\" alt=\"\" title=\"\" style=\"vertical-align: middle\" />";
					}
					echo "</span>";
					echo "<span style=\"vertical-align: middle\">\n";
					echo "<select name=\"pv\" onchange=\"$('pageSelector').submit();\"".(($total_pages <= 1) ? " disabled=\"disabled\"" : "").">\n";
					for ($i = 1; $i <= $total_pages; $i++) {
						echo "<option value=\"".$i."\"".(($i == $page_current) ? " selected=\"selected\"" : "").">".(($i == $page_current) ? " Viewing" : "Jump To")." Page ".$i."</option>\n";
					}
					echo "</select>\n";
					echo "</span>\n";
					echo "<span style=\"width: 20px; vertical-align: middle; margin-left: 3px; text-align: right\">\n";
					if ($page_current < $total_pages) {
						echo "<a href=\"".ENTRADA_URL."/courses/objectives?".replace_query(array("pv" => ($page_current + 1)))."\"><img src=\"".ENTRADA_URL."/images/record-next-on.gif\" border=\"0\" width=\"11\" height=\"11\" alt=\"Forward to page ".($page_current + 1).".\" title=\"Forward to page ".($page_current + 1).".\" style=\"vertical-align: middle\" /></a>";
					} else {
						echo "<img src=\"".ENTRADA_URL."/images/record-next-off.gif\" width=\"11\" height=\"11\" alt=\"\" title=\"\" style=\"vertical-align: middle\" />";
					}
					echo "</span>\n";
					echo "</div>\n";
					echo "</form>\n";
				}
				?>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * This function gets clinical flag from the user_data table
 *
 * @param int $proxy_id
 * @return array result["clinical"]
 */
function getClinicalFromProxy($proxy_id) {
    global $db;

    $query = "SELECT `clinical`
	FROM `".AUTH_DATABASE."`.`user_data` WHERE `id`=". $db->qstr($proxy_id);
    
    $result = $db->GetRow($query);
    
	return $result["clinical"];
}

/**
 * This function determines whether to allow users to edit the Year Reported value within the Annual
 * Reporting module. If the uer is attemtping to edit a previous year then the year reported field cannot
 * be changed. If they are editing a current year and changing it to a previous year then they should
 * be allowed to change the value after a submit containing an error (uses $allowEdit for this).
 *
 * @param int $year_reported, $AR_CUR_YEAR, $AR_PAST_YEARS, $AR_FUTURE_YEARS, $allowEdit
 * @return displays appropriate HTML
 */
function displayARYearReported($year_reported, $AR_CUR_YEAR, $AR_PAST_YEARS, $AR_FUTURE_YEARS, $allowEdit = false) {
	if(isset($year_reported) && $year_reported < $AR_CUR_YEAR && !$allowEdit) {
		echo "<td>".$year_reported." - <span class=\"content-small\"><strong>Note: </strong>Previous Reporting Years cannot be changed.</span>
		<input type=\"hidden\" name=\"year_reported\" value=\"".$year_reported."\" />";
	} else {
	?>
	<td><select name="year_reported" id="year_reported" style="vertical-align: middle">
	<?php
		for($i=$AR_PAST_YEARS; $i<=$AR_FUTURE_YEARS; $i++) {
			if(isset($year_reported) && $year_reported != '') {
				$defaultYear = $year_reported;
			} else if(isset($year_reported) && $year_reported != '') {
				$defaultYear = $year_reported;
			} else  {
				$defaultYear = $AR_CUR_YEAR;
			}
			echo "<option value=\"".$i."\"".(($defaultYear == $i) ? " selected=\"selected\"" : "").">".$i."</option>\n";
		}
		?>
		</select>
		<?php
		}
		
	?>
	</td>
	<?php
}

/**
 * Adds the task sidebar, and populates it, only if there are tasks to be completed
 */
function add_task_sidebar () {
	require_once("Models/users/User.class.php");
	require_once("Models/tasks/TaskCompletions.class.php");
	global $ENTRADA_ACL;

	$proxy_id = $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"];
	$user = User::get($proxy_id);
	
	
	$tasks_completions = TaskCompletions::getByRecipient($user, array('order_by'=>array(array('deadline', 'asc')), 'limit' => 5, 'where' => 'completed_date IS NULL'));
	
	foreach ($tasks_completions as $completion) {
		$tasks[] = $completion->getTask();
	}
	if ($tasks) {
		
		$sidebar_html = "<ul>";
		foreach ($tasks as $task) {
			$sidebar_html .= "
			<li>
				<a href='".ENTRADA_URL."/tasks?section=details&id=".$task->getID()."'>".html_encode($task->getTitle())."</a>
				<span class='content-small'>".(($task->getDeadline()) ? date(DEFAULT_DATE_FORMAT,$task->getDeadline()) : "")."</span>
			</li>";
		}
		$sidebar_html .= "</ul>";
		
		$sidebar_html .= "<a class='see-all' href='".ENTRADA_URL."/tasks'>See all tasks</a>"; 
		
		new_sidebar_item("Upcoming Tasks", $sidebar_html, "task-list", "open");
	}
}

/**
 * Adds an error message
 * @param string $message
 */
function add_error($message) {
	add_message("error",$message);
}

/**
 * Adds a notice message
 * @param string $message
 */
function add_notice($message) {
	add_message("notice",$message);
}

/**
 * Adds a success message
 * @param string $message
 */
function add_success($message) {
	add_message("success",$message);
}

/**
 * Adds the supplied message to the type-specified collection of messages 
 * @param string $type At this time, one of "success","error",or "notice"
 * @param string $message
 */
function add_message($type,$message) {
	$type = strtoupper($type);
	$strings = $type."STR";
	global ${$type}, ${$strings};
	${$type}++;
	${$strings}[] = $message;
}

/**
 * Returns true if there are any messages of the specified type 
 * @param string $type At this time, one of "success","error",or "notice"
 * @return bool
 */
function has_message($type) {
	switch ($type) {
		case "success":
		case "error":
		case "notice":
			$type = strtoupper($type);
			$strings = $type."STR";
			global ${$type}, ${$strings};
			return (${$type} || ${$strings});
	}
}

/**
 * Returns true if there are any error messages 
 * @return bool
 */
function has_error() {
	return has_message("error");
}

/**
 * Returns true if there are any notice messages 
 * @return bool
 */
function has_notice() {
	return has_message("notice");
}

/**
 * Returns true if there are any success messages 
 * @return bool
 */
function has_success() {
	return has_message("success");
}

/**
 * Clears error messages
 */
function clear_error(){
	clear_message("error");
}

/**
 * Clears success messages
 */
function clear_success() {
	clear_message("success");
}

/**
 * Clears notice messages
 */
function clear_notice() {
	clear_message("notice");
}

/**
 * Empties the the specified message type
 * @param string $type At this time, one of "success","error",or "notice"
 */
function clear_message($type) {
	switch ($type) {
		case "success":
		case "error":
		case "notice":
			$type = strtoupper($type);
			$strings = $type."STR";
			global ${$type}, ${$strings};
			${$type} = 0;
			${$strings} = array();
	}
}

/**
 * This function gets the min and max years that are in the Annual Reporting Module for report generation purposes
 *
 * @param null
 * @return array(start, end)
 */
function getMinMaxARYears() {
    global $db;

    $query = "SELECT MIN(year_reported) AS `start_year`, MAX(year_reported) AS `end_year`
	FROM `ar_profile`";
    
    $result = $db->GetRow($query);
    
	return $result;
}
