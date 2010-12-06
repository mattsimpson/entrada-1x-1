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
 * This section is loaded when an individual wants to attempt to fill out an evaluation.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Matt Simpson <matt.simpson@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/

if((!defined("PARENT_INCLUDED")) || (!defined("IN_PUBLIC_EVALUATIONS"))) {
	exit;
} elseif((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
}

if ($RECORD_ID) {
	$query			= "	SELECT a.*, c.`eprogress_id`
						FROM `evaluations` AS a
						LEFT JOIN `evaluation_evaluators` AS b
						ON a.`evaluation_id` = b.`evaluation_id`
						LEFT JOIN `evaluation_progress` AS c
						ON a.`evaluation_id` = c.`evaluation_id`
						AND c.`progress_value` = 'inprogress'
						AND c.`proxy_id` = ".$db->qstr($_SESSION["details"]["id"])."
						LEFT JOIN `evaluation_responses` AS cr
						ON c.`eprogress_id` = cr.`eprogress_id`
						LEFT JOIN `evaluation_targets` AS d
						ON a.`evaluation_id` = d.`evaluation_id`
						WHERE a.`evaluation_id` = ".$db->qstr($RECORD_ID)."
						AND
						(
							(
								b.`evaluator_type` = 'proxy_id'
								AND b.`evaluator_value` = ".$db->qstr($_SESSION["details"]["id"])."
							)
							".( $_SESSION["details"]["group"] == "student" ?
							"OR
							(
								b.`evaluator_type` = 'grad_year'
								AND b.`evaluator_value` = ".$db->qstr($_SESSION["details"]["role"])."
							)"
							: "")."
						)
						AND a.`evaluation_active` = '1'
						GROUP BY cr.`eprogress_id`";
	$evaluation_record	= $db->GetRow($query);
	if ($evaluation_record) {
		$BREADCRUMB[]	= array("url" => ENTRADA_URL."/".$MODULE."?section=attempt&id=".$RECORD_ID, "title" => limit_chars($evaluation_record["evaluation_title"], 32));

		/**
		 * Providing there is no release date, or the release date is in the past
		 * on the evaluation, allow them to continue.
		 */
		if ((((int) $evaluation_record["release_date"] === 0) || ($evaluation_record["release_date"] <= time()))) {
			/**
			 * Providing there is no expiry date, or the expiry date is in the
			 * future on the evaluation, allow them to continue.
			 */
			if ((((int) $evaluation_record["release_until"] === 0) || ($evaluation_record["release_until"] > time()))) {
				/**
				 * Get the number of completed attempts this user has made.
				 */
				$completed_attempts = evaluations_fetch_attempts($RECORD_ID);

				/**
				 * Providing they can still still make attempts at this evaluation, allow them to continue.
				 */
				if (((int) $evaluation_record["max_submittable"] === 0) || ($completed_attempts < $evaluation_record["max_submittable"])) {
					$problem_questions = array();

					echo "<h1>".html_encode($evaluation_record["evaluation_title"])."</h1>";

					// Error checking
					switch ($STEP) {
						case 2 :
							if ((isset($_POST["etarget_id"])) && ($etarget_id = clean_input($_POST["etarget_id"], array("trim", "int")))) {
								$query = "	SELECT * FROM `evaluation_targets` AS a 
											JOIN `evaluations_lu_targets` AS b 
											ON a.`target_id` = b.`target_id` 
											WHERE a.`evaluation_id` = ".$db->qstr($evaluation_record["evaluation_id"])." 
											AND a.`etarget_id` = ".$db->qstr($etarget_id);
								$target_record = $db->GetRow($query);
								if ($target_record) {
									if ($target_record["target_shortname"] != "course") {
										$query = "	SELECT `etarget_id` FROM `evaluations_progress`
													WHERE `evaluation_id` = ".$db->qstr($evaluation_record["evaluation_id"])."
													AND `progress_value` = 'complete'
													AND `etarget_id` = ".$db->qstr($etarget_id);
										if ($db->GetOne($query)) {
											$ERROR++;
											$ERRORSTR[] = "You have already evaluated this ".$target_record["target_shortname"].". Please choose a new target to evaluate.";
										} else {
											$PROCESSED["etarget_id"] = $etarget_id;
										}
									} else {
										$PROCESSED["etarget_id"] = $etarget_id;
									}
								} else {
									$ERROR++;
									$ERRRORSTR[] = "There was an issue with the target you have selected to evaluate. An administrator has ";
								}
							}
							/**
							 * Check to see if they currently have any evaluation attempts underway, if they do then
							 * restart their session, otherwise start them a new session.
							 */
							$query				= "	SELECT *
													FROM `evaluation_progress` AS a
													JOIN `evaluations` AS b
													ON a.`evaluation_id` = b.`evaluation_id`
													WHERE a.`evaluation_id` = ".$db->qstr($RECORD_ID)."
													AND a.`proxy_id` = ".$db->qstr($_SESSION["details"]["id"])."
													AND a.`progress_value` = 'inprogress'
													ORDER BY a.`updated_date` ASC";
							$progress_record	= $db->GetRow($query);
							if ($progress_record) {
								$eprogress_id		= $progress_record["eprogress_id"];

								if ((isset($_POST["responses"])) && (is_array($_POST["responses"])) && (count($_POST["responses"]) > 0)) {
									/**
									 * Get a list of all of the questions in this evaluation so we
									 * can run through a clean set of questions.
									 */
									$query		= "	SELECT a.*
													FROM `evaluation_form_questions` AS a
													WHERE a.`eform_id` = ".$db->qstr($evaluation_record["eform_id"])."
													ORDER BY a.`question_order` ASC";
									$questions	= $db->GetAll($query);
									if ($questions) {
										if ((count($_POST["responses"])) != (count($questions))) {
											$ERROR++;
											$ERRORSTR[] = "In order to submit your evaluation, you must first answer all of the questions.";
										}

										foreach ($questions as $question) {
											/**
											 * Checking to see if the efquestion_id was submitted with the
											 * response $_POST, and if they've actually answered the question.
											 */
											if ((isset($_POST["responses"][$question["efquestion_id"]])) && ($efresponse_id = clean_input($_POST["responses"][$question["efquestion_id"]], "int"))) {
												if (!evaluation_save_response($eprogress_id, $progress_record["eform_id"], $question["efquestion_id"], $efresponse_id)) {
													$ERROR++;
													$ERRORSTR[] = "A problem was found storing a question response, please verify your responses and try again.";

													$problem_questions[] = $question["efquestion_id"];
												}
											} else {
												$ERROR++;
												
												$problem_questions[] = $question["efquestion_id"];
											}
										}
										if ($ERROR && empty($ERRORSTR)) {
											$ERRORSTR[] = "A problem was found storing a question response, please verify your responses and try again.";
										}
									} else {
										$ERROR++;
										$ERRORSTR[] = "An error occurred while attempting to save your evaluation responses. The system administrator has been notified of this error; please try again later.";

										application_log("error", "Unable to find any evaluation questions for evaluation_id [".$progress_record["evaluation_id"]."]. Database said: ".$db->ErrorMsg());
									}

									/**
									 * We can now safely say that all questions have valid responses
									 * and that we have stored those responses evaluation_responses table.
									 */
									if (!$ERROR) {
										$PROCESSED = evaluation_load_progress($eprogress_id);
										$evaluation_progress_array	= array (
																	"progress_value" => "complete",
																	"evaluation_id" => $evaluation_record["evaluation_id"],
																	"etarget_id" => $evaluation_record["etarget_id"],
																	"updated_date" => time(),
																	"updated_by" => $_SESSION["details"]["id"]
																);

										if ($db->AutoExecute("evaluation_progress", $evaluation_progress_array, "UPDATE", "eprogress_id = ".$db->qstr($eprogress_id))) {
											/**
											 * Add a completed evaluation statistic.
											 */
											add_statistic("evaluations", "evaluation_complete", "evaluation_id", $RECORD_ID);

											application_log("success", "Proxy_id [".$_SESSION["details"]["id"]."] has completed evaluation_id [".$RECORD_ID."].");
											
											$url = ENTRADA_URL."/evaluations";

											$SUCCESS++;
											$SUCCESSSTR[] = "Thank-you for completing the <strong>".html_encode($evaluation_record["evaluation_title"])."</strong> evaluation.<br /><br />You will now be redirected back to the learning event; this will happen <strong>automatically</strong> in 15 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";

											$ONLOAD[] = "setTimeout('window.location=\\'".$url."\\'', 15000)";
										} else {
											application_log("error", "Unable to record the final evaluation results for evaluation_id [".$RECORD_ID."] in the evaluation_progress table. Database said: ".$db->ErrorMsg());

											$ERROR++;
											$ERRORSTR[] = "We were unable to record the final results for this evaluation at this time. Please be assured that your responses are saved, but you will need to come back to this evaluation to re-submit it. This problem has been reported to a system administrator; please try again later.";

											echo display_error();
										}
									}
								} else {
									$ERROR++;
									$ERRORSTR[] = "In order to submit your evaluation for marking, you must first answer some of the questions.";
								}
							} else {
								$ERROR++;
								$ERRORSTR[] = "We were unable to locate an evaluation that is currently in progress.<br /><br />If you pressed your web-browsers back button, please refrain from doing this when you are posting evaluation information.";
								
								application_log("error", "Unable to locate an evaluation currently in progress when attempting to save an evaluation.");
							}

							if ($ERROR) {
								$STEP = 1;
							}
						break;
						case 1 :
						default :
							continue;
						break;
					}

					if (((int) $evaluation_record["max_submittable"] === 0) || ($completed_attempts < $evaluation_record["max_submittable"])) {
						// Display Content
						switch ($STEP) {
							case 2 :
								if ($SUCCESS) {
									echo display_success();
								}
							break;
							case 1 :
							default :
								/**
								 * Check to see if they currently have any evaluation attempts underway, if they do then
								 * restart their session, otherwise start them a new session.
								 */
								$query				= "	SELECT *
														FROM `evaluation_progress`
														WHERE `evaluation_id` = ".$db->qstr($RECORD_ID)."
														AND `proxy_id` = ".$db->qstr($_SESSION["details"]["id"])."
														AND `progress_value` = 'inprogress'
														ORDER BY `updated_date` ASC";
								$progress_record	= $db->GetRow($query);
								if ($progress_record) {
									$eprogress_id		= $progress_record["eprogress_id"];
									$evaluation_start_time	= $progress_record["updated_date"];
								} else {
									$evaluation_start_time	= time();
									$evaluation_progress_array	= array (
																"evaluation_id" => $RECORD_ID,
																"proxy_id" => $_SESSION["details"]["id"],
																"progress_value" => "inprogress",
																"updated_date" => $evaluation_start_time,
																"updated_by" => $_SESSION["details"]["id"]
															);
									if ($db->AutoExecute("evaluation_progress", $evaluation_progress_array, "INSERT"))  {
										$eprogress_id = $db->Insert_Id();
									} else {
										$ERROR++;
										$ERRORSTR[] = "Unable to create a progress entry for this evaluation, it is not advisable to continue at this time. The system administrator was notified of this error; please try again later.";

										application_log("error", "Unable to create an evaluation_progress entery when attempting complete an evaluation. Database said: ".$db->ErrorMsg());
									}
								}

								if ($eprogress_id) {
									add_statistic("evaluation", "evaluation_view", "evaluation_id", $RECORD_ID);

									$ajax_load_progress		= evaluation_load_progress($eprogress_id);
									
									$query = "	SELECT * FROM `evaluation_targets` AS a
												JOIN `evaluations_lu_targets` AS b
												ON a.`target_id` = b.`target_id`
												LEFT JOIN `evaluation_progress` AS c
												ON a.`etarget_id` = c.`etarget_id`
												AND c.`proxy_id` = ".$db->qstr($_SESSION["details"]["id"])."
												AND c.`evaluation_id` = a.`evaluation_id`
												AND c.`progress_value` = 'complete'
												WHERE a.`evaluation_id` = ".$db->qstr($RECORD_ID);
									$evaluation_targets = $db->GetAll($query);
									if ($evaluation_targets) {
										if (count($evaluation_targets) == 1 || $evaluation_targets[0]["target_shortname"] != "teacher") {
											if ($evaluation_targets[0]["target_shortname"] == "teacher") {
												$target_name = $db->GetOne("SELECT CONCAT_WS(' ', `firstname`, `lastname`) AS `fullname` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id` = ".$db->qstr($evaluation_targets[0]["target_value"]));
											} elseif ($evaluation_targets[0]["target_shortname"] == "course") {
												$target_name = $db->GetOne("SELECT `course_name` FROM `courses` WHERE `course_id` = ".$db->qstr($evaluation_targets[0]["target_value"]));
											}
											if ($target_name) {
												echo "<div class=\"content-small\">Evaluating <strong>".$target_name."</strong>.</div>";
											}
										} else {
											echo "<div class=\"content-small\">Please choose a teacher to evaluate: \n";
											echo "<select id=\"evaluation_target\" name=\"evaluation_target\">";
											echo "<option value=\"0\">-- Select a teacher --</option>\n";
											foreach ($evaluation_targets as $evaluation_target) {
												if (!isset($evaluation_target["eprogress_id"]) || !$evaluation_target["eprogress_id"]) {
													$target_name = $db->GetOne("SELECT CONCAT_WS(' ', `firstname`, `lastname`) AS `fullname` FROM `".AUTH_DATABASE."`.`user_data` WHERE `id` = ".$db->qstr($evaluation_target["target_value"]));
													if ($target_name) {
														echo "<option value=\"".$evaluation_target["etarget_id"]."\">".$fullname."</option>\n";
													}
												}
											}
											echo "</select>";
											echo "</div>";
										}
									}

									?>
									<div id="display-unsaved-warning" class="display-notice" style="display: none">
										<ul>
											<li><strong>Warning Unsaved Response:</strong><br />Your response to the question indicated by a yellow background was not automatically saved.</li>
										</ul>
									</div>
									<?php
									if ($ERROR) {
										echo display_error();
									}
									if ($NOTICE) {
										echo display_notice();
									}
									?>
									<form action="<?php echo ENTRADA_URL."/".$MODULE; ?>?section=attempt&id=<?php echo $RECORD_ID; ?>" method="post">
									<input type="hidden" name="step" value="2" />
									<?php
									$query				= "	SELECT a.*
															FROM `evaluation_form_questions` AS a
															WHERE a.`eform_id` = ".$db->qstr($evaluation_record["eform_id"])."
															ORDER BY a.`question_order` ASC";
									$questions			= $db->GetAll($query);
									$total_questions	= 0;
									if ($questions) {
										$total_questions = count($questions);
										?>
										<div class="evaluation-questions" id="evaluation-content-questions-holder">
											<ol class="questions" id="evaluation-questions-list">
											<?php
											foreach ($questions as $question) {
												echo "<li id=\"question_".$question["efquestion_id"]."\"".((in_array($question["efquestion_id"], $problem_questions)) ? " class=\"notice\"" : "").">";
												echo "	<div class=\"question noneditable\">\n";
												echo "		<span id=\"question_text_".$question["efquestion_id"]."\" class=\"question\">".clean_input($question["question_text"], "trim")."</span>";
												echo "	</div>\n";
												echo "	<ul class=\"responses\">\n";
												$query		= "	SELECT a.*
																FROM `evaluation_form_responses` AS a
																WHERE a.`efquestion_id` = ".$db->qstr($question["efquestion_id"])."
																ORDER BY a.`response_order` ASC";
												$responses	= $db->GetAll($query);
												if ($responses) {
													foreach ($responses as $response) {
														echo "<li>";
														echo "	<input type=\"radio\" id=\"response_".$question["efquestion_id"]."_".$response["efresponse_id"]."\" name=\"responses[".$question["efquestion_id"]."]\" value=\"".$response["efresponse_id"]."\"".(($ajax_load_progress[$question["efquestion_id"]] == $response["efresponse_id"]) ? " checked=\"checked\"" : "")." onclick=\"((this.checked == true) ? storeResponse('".$question["efquestion_id"]."', '".$response["efresponse_id"]."') : false)\" />";
														echo "	<label for=\"response_".$question["efquestion_id"]."_".$response["efresponse_id"]."\">".clean_input($response["response_text"], (($response["response_is_html"] == 1) ? "trim" : "encode"))."</label>";
														echo "</li>\n";
													}
												}
												echo "	</ul>\n";
												echo "</li>\n";
											}
											?>
											</ol>
										</div>
										<?php
									} else {
										$ERROR++;
										$ERRORSTR[] = "There are no questions currently available for this evaluation. This problem has been reported to a system administrator; please try again later.";

										application_log("error", "Unable to locate any questions for evaluation [".$evaluation_record["evaluation_id"]."]. Database said: ".$db->ErrorMsg());
									}
									?>
									<div style="border-top: 2px #CCCCCC solid; margin-top: 10px; padding-top: 10px">
										<input type="button" style="float: left; margin-right: 10px" onclick="window.location = '<?php echo ENTRADA_URL; ?>/evaluations'" value="Exit Evaluation" />
										<input type="submit" style="float: right" value="Submit Evaluation" />
									</div>
									<div class="clear"></div>
									</form>
									<script type="text/javascript">
									function storeResponse(qid, rid) {
										new Ajax.Request('<?php echo ENTRADA_URL."/".$MODULE; ?>', {
											method: 'post',
											parameters: { 'section' : 'save-response', 'id' : '<?php echo $RECORD_ID; ?>', 'qid' : qid, 'rid' : rid},
											onSuccess: function(transport) {
												if (transport.responseText.match(200)) {
													$('question_' + qid).removeClassName('notice');

													if ($$('#evaluation-questions-list li.notice').length <= 0) {
														$('display-unsaved-warning').fade({ duration: 0.5 });
													}
												} else {
													$('question_' + qid).addClassName('notice');

													if ($('display-unsaved-warning').style.display == 'none') {
														$('display-unsaved-warning').appear({ duration: 0.5 });
													}
												}
											},
											onError: function() {
													$('question_' + qid).addClassName('notice');

													if ($('display-unsaved-warning').style.display == 'none') {
														$('display-unsaved-warning').appear({ duration: 0.5 });
													}
											}
										});
									}
									</script>
									<?php
									$sidebar_html = evaluation_generate_description($evaluation_record["min_submittable"], $total_questions, $evaluation_record["max_submittable"], $evaluation_record["evaluation_finish"]);
									new_sidebar_item("Evaluation Statement", $sidebar_html, "page-anchors", "open", "1.9");
								} else {
									$ERROR++;
									$ERRORSTR[] = "Unable to locate your progress information for this evaluation at this time. The system administrator has been notified of this error; please try again later.";

									echo display_error();

									application_log("error", "Failed to locate a eprogress_id [".$eprogress_id."] (either existing or created) when attempting to complete evaluation_id [".$RECORD_ID."] (eform_id [".$evaluation_record["eform_id"]."]).");
								}
							break;
						}
					} else {
						$ERROR++;
						$ERRORSTR[] = "You were only able to attempt this evaluation a total of <strong>".(int) $evaluation_record["max_submittable"]." time".(($evaluation_record["max_submittable"] != 1) ? "s" : "")."</strong>, and the time limit for your final attempt expired before completion.<br /><br />Please contact a teacher if you require further assistance.";

						echo display_error();

						application_log("notice", "Someone attempted to complete evaluation_id [".$RECORD_ID."] (eform_id [".$evaluation_record["eform_id"]."]) more than the total number of possible attempts [".$evaluation_record["max_submittable"]."] after their final attempt expired.");
					}
				} else {
					$NOTICE++;
					$NOTICESTR[] = "You were only able to attempt this evaluation a total of <strong>".(int) $evaluation_record["max_submittable"]." time".(($evaluation_record["max_submittable"] != 1) ? "s" : "")."</strong>.<br /><br />Please contact a teacher if you require further assistance.";

					echo display_notice();

					application_log("notice", "Someone attempted to complete evaluation_id [".$RECORD_ID."] (eform_id [".$evaluation_record["eform_id"]."]) more than the total number of possible attempts [".$evaluation_record["max_submittable"]."].");
				}
			} else {
				$NOTICE++;
				$NOTICESTR[] = "You were only able to attempt this evaluation until <strong>".date(DEFAULT_DATE_FORMAT, $evaluation_record["release_until"])."</strong>.<br /><br />Please contact a teacher if you require further assistance.";

				echo display_notice();

				application_log("error", "Someone attempted to complete evaluation_id [".$RECORD_ID."] (eform_id [".$evaluation_record["eform_id"]."]) after the release date.");
			}
		} else {
			$NOTICE++;
			$NOTICESTR[] = "You cannot attempt this evaluation until <strong>".date(DEFAULT_DATE_FORMAT, $evaluation_record["release_date"])."</strong>.<br /><br />Please contact a teacher if you require further assistance.";

			echo display_notice();

			application_log("error", "Someone attempted to complete evaluation_id [".$RECORD_ID."] (eform_id [".$evaluation_record["eform_id"]."]) prior to the release date.");
		}
	} else {
		$ERROR++;
		$ERRORSTR[] = "In order to attempt an evaluation, you must provide a valid evaluation identifier.";

		echo display_error();

		application_log("error", "Failed to provide a valid evaluation_id identifer [".$RECORD_ID."] when attempting to take an evaluation.");
	}
} else {
	$ERROR++;
	$ERRORSTR[] = "In order to attempt an evaluation, you must provide a valid evaluation identifier.";

	echo display_error();

	application_log("error", "Failed to provide an evaluation_id identifier when attempting to take an evaluation.");
}