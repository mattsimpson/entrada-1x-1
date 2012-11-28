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
 * @author Unit: MEdTech Unit
 * @author Developer: Brandon Thorn <brandon.thorn@queensu.ca>
 * @copyright Copyright 2011 Queen's University. All Rights Reserved.
 *
*/

if ((!defined("PARENT_INCLUDED")) || (!defined("IN_CONFIGURATION"))) {
	exit;
} elseif ((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif (!$ENTRADA_ACL->amIAllowed("configuration", "read", false)) {
	add_error("Your account does not have the permissions required to use this feature of this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.");

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["group"]."] and role [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["role"]."] do not have access to this module [".$MODULE."]");
} else {
	?>
	<?php
		if($ENTRADA_ACL->amIAllowed("configuration", "create")){?>
		<div class="pull-right">
				<a href="<?php echo ENTRADA_URL; ?>/admin/settings/organisations?section=add" class="btn btn-primary">Add New Organisation</a>
		</div>	
	<?php
		}
	$query = "	SELECT * FROM `".AUTH_DATABASE."`.`organisations`
				ORDER BY `organisation_title` ASC";
	$results = $db->GetAll($query);
	if ($results) {
		$organisations = array();

		foreach($results as $result) {
			if ($ENTRADA_ACL->amIAllowed(new ConfigurationResource($result["organisation_id"]), "update")) {
				$organisations[] = $result;
			}
		}
		
		if (!empty($organisations)) {
			?>
			<h2>Organisations</h2>
			<div id="organisations-section">
					<table class="tableList" cellspacing="0" cellpadding="1" border="0" summary="List of Organisations">
						<colgroup>
							<col class="title" />
						</colgroup>
						<thead>
							<tr>
								<td class="title borderl">Organisation Title</td>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach($organisations as $result) {
								$url = ENTRADA_URL."/admin/settings/organisations/manage?org=".(int) $result["organisation_id"];

								echo "<tr>\n";
								echo "	<td><a href=\"".$url."\">".html_encode($result["organisation_title"])."</a></td>\n";
								echo "</tr>\n";
							}
							?>
						</tbody>
					</table>
			</div>
			<?php
		} else {
			add_notice("You don't appear to have access to change any organisations. If you feel you are seeing this in error, please contact your system administrator.");
			echo display_notice();
		}
	} else {
		add_notice("You don't appear to have access to change any organisations. If you feel you are seeing this in error, please contact your system administrator.");
		echo display_notice();
	}
}