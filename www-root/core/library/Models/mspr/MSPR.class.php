<?php

require_once("Models/users/User.class.php");
require_mspr_models();

class MSPR implements ArrayAccess, AttentionRequirable {
	private $closed;
	private $generated;
	private $last_update;
	private $user_id;
	private $models = array ( // Title => Class
							"Internal Awards" => "InternalAwardReceipts",
							"External Awards" => "ExternalAwardReceipts",
							"Studentships" => "Studentships",
							"Clinical Performance Evaluation Comments" => "ClinicalPerformanceEvaluations",
							"Contributions to Medical School" => "Contributions",
							"Disciplinary Actions" => "DisciplinaryActions",
							"Leaves of Absence" => "LeavesOfAbsence",
							"Formal Remediation Received" => "FormalRemediations",
							"Student-Run Electives" => "StudentRunElectives",
							"Observerships" => "Observerships",
							"International Activities" => "InternationalActivities",
							"Critical Enquiry" => "CriticalEnquiry",
							"Community Health and Epidemiology" => "CommunityHealthAndEpidemiology",
							"Research" => "ResearchCitations",
							"Clerkship Core Completed" => "ClerkshipCoreCompleted",
							"Clerkship Core Pending" => "ClerkshipCorePending",
							"Clerkship Electives Completed" => "ClerkshipElectivesCompleted"
							);
	
	function __construct($user_id, $last_update, $closed = NULL, $generated = NULL) {
		$this->user_id = $user_id;
		$this->last_update = $last_update;
		$this->closed = $closed;
		$this->generated = $generated;
	}
	
	/**
	 * @return User
	 */
	function getUser() {
		return User::get($this->user_id);
	}
	
	/**
	 * Returns true if the closed timestamp/class deadline exceeds the current time
	 */
	function isClosed() {
		//first check the local timestamp
		if (!is_null($this->closed)) {
			return $this->closed < time();
		} elseif ($class_data = MSPRClassData::get($this->getUser()->getGradYear())) {		
			if ($class_data) {
				$class_closed = $class_data->getClosedTimestamp(); //check the class data
				return $class_closed && ($class_closed < time());
			}
		} 
		return false; //no close date
	}
	
	/**
	 * Sets the scheduled closed timestamp
	 * alias for setClosedTimestamp
	 * @param $timestamp
	 */
	function close($timestamp) {
		$this->setClosedTimestamp($timestamp);
	}
	
	/**
	 * Clears the scheduled closed timestamp
	 * alias for setClosedTimestamp(null)
	 */
	function open() {
		$this->setClosedTimestamp(null);
	}
	
	function isGenerated() {
		return (!is_null($this->generated) && $this->generated < time());
	}
		
	/**
	 * Returns a timestamp of submission closure
	 */
	function getClosedTimestamp() {
		return $this->closed;
	}
	
	/**
	 * Returns a timestamp of the last mspr generation
	 */
	function getGeneratedTimestamp() {
		return $this->generated;
	}
	
	function getComponent($component) {
		if (array_key_exists($component, $this->models)) {
			$component_class = $this->models[$component];
			return call_user_func($component_class."::get", $this->getUser());
		}
	}
	
	function isAttentionRequired() {
		$user = $this->getUser();
		//get all student entered data;
		$att_reqs[] = CriticalEnquiry::get($user);
		$att_reqs[] = ExternalAwardReceipts::get($user);
		$att_reqs[] = Contributions::get($user);
		$att_reqs[] = CommunityHealthAndEpidemiology::get($user);
		$att_reqs[] = ResearchCitations::get($user);
		foreach ($att_reqs as $att_req) {
			if ($att_req && $att_req->isAttentionRequired()) return true;
		}
		return false;	
	}
	
	public function offsetSet($offset, $value) {
        //cannot set
    }
    public function offsetExists($key) {
        return array_key_exists($key, $this->models);
    }
    public function offsetUnset($offset) {
       //cannot unset
    }
    public function offsetGet($key) {
        return $this->getComponent($key);
    }
	
    /**
     * 
     * @param $user
     * @return MSPR
     */
    public static function get(User $user) {
    	global $db;
		$user_id = $user->getID();
		$query		= "SELECT * FROM `student_mspr` WHERE `user_id` = ".$db->qstr($user_id);
		$result = $db->getRow($query);
		if ($result) {
			$mspr =  new self($result['user_id'], $result['last_update'], $result['closed'], $result['generated']);
			return $mspr;
		}    	
    }
    
    public static function create(User $user, $closed_ts = NULL) {
    	global $db;

		$user_id = $user->getID();
		$query = "insert into `student_mspr` (`user_id`, `closed`) value (".$db->qstr($user_id).", ".(isset($closed) && $closed ? $db->qstr($closed) : "NULL").")";
		
		if(!$db->Execute($query)) {
			application_log("error", "Unable to update a student_mspr record. Database said: ".$db->ErrorMsg());
			return false;
		} else {
			return true;
		}
    }

	public function setClosedTimestamp($timestamp) {
		global $db,$ERROR,$ERRORSTR;
		$query = "update `student_mspr` set
				 `closed`=".$db->qstr($timestamp)."
				 where `user_id`=".$db->qstr($this->user_id);
		
		if(!$db->Execute($query)) {
			$ERROR++;
			$ERRORSTR[] = "Failed to update Submission Deadline.".$db->ErrorMsg();
			application_log("error", "Unable to update a student_mspr record. Database said: ".$db->ErrorMsg());
		}
	}
	
	public function setGeneratedTimestamp($timestamp) {
		global $db,$ERROR,$ERRORSTR;
		$query = "update `student_mspr` set
				 `generated`=".$db->qstr($timestamp)."
				 where `user_id`=".$db->qstr($this->user_id);
				
		if(!$db->Execute($query)) {
			$ERROR++;
			$ERRORSTR[] = "Failed to update MSPR Generation Time.";
			application_log("error", "Unable to update a student_mspr record. Database said: ".$db->ErrorMsg());
		}
	}
	
	/**
	 * Uses htmldoc to generate a pdf file from the provided html. returns the pdf as text. 
	 * @param unknown_type $timestamp
	 * @param unknown_type $html
	 * @return string
	 */
	private function generatePDF($html) {
		return generatePDF($html);
	}
	
	/**
	 * Returns html
	 * @param int $timestamp
	 * @return string
	 */
	public function generateHTML($timestamp) {
		require_once("Entrada/mspr/mspr_gen.php");
		return generateMSPRHTML($this);
	}
	
	public function saveMSPRFiles($timestamp=null,$location=null) {
		global $SUCCESS,$SUCCESSSTR,$ERROR,$ERRORSTR;
		if (!$location) {
			$location = MSPR_STORAGE; //use default
		}
		if (!$timestamp) {
			$timestamp = time();
		}
		
		//generate HTML file first, then
		//use the result to make the pdf
		
		$html = $this->generateHTML($timestamp);
		$pdf = $this->generatePDF($html);

		//prepare filename
		$user = $this->getUser();
		$number = $user->getNumber();
		
		$filebase = $number."-".$timestamp;
		
		//now write the files and return success/fail (true/false)
		$wroteHTML = writeFile($location."/".$filebase.".html",$html);
		$wrotePDF = writeFile($location."/".$filebase.".pdf",$pdf);
		
		if ($wroteHTML && $wrotePDF) {
			$this->setGeneratedTimestamp($timestamp);
			return true;
		}	
		return false;
	}
	
	public function getMSPRFile($type = "pdf", $timestamp = null, $location = null) {
		if (!$location) {
			$location = MSPR_STORAGE; //use default
		}
		$number = $this->getUser()->getNumber();
		$revisions = $this->getMSPRRevisions($type);
		if ($revisions) {
			if (!$timestamp) {
				$revision = $revisions[0];
			} else {
				if (in_array($timestamp,$revisions)) {
					$revision = $timestamp;
				} else {
					return false;
				}
			}
			return @file_get_contents($location."/".$number."-".$revision.".".$type);
		}
		return false;
	}

	/**
	 * 
	 * @param string $type default: pdf
	 * @param string $location default: [internally specified]
	 * @return array An empty array indicates no revisions are present
	 */
	public function getMSPRRevisions($type="pdf", $location = null) {
		if (!$location) {
			$location = MSPR_STORAGE; //use default
		}
		$user = $this->getUser();
		$search_string = $location . "/" .$user->getNumber()."-*.".$type; 
		$files = glob($search_string);
		
		//extract timestamps - the only part we care about
		$revisions = array();
		foreach ($files as $file) {
			$basename = basename($file,".".$type);
			$parts = explode("-",$basename);
			$revisions[] = $parts[1];
		}
		//sort by timestamp (newest first)
		sort($revisions,SORT_NUMERIC);
		return array_reverse($revisions);
	}
	

}