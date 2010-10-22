<STYLE><!--
  
  #example{scrollbar-3d-light-color: '#0084d8'; scrollbar-arrow-color: 'black'; scrollbar-base-  color:'#00568c'; scrollbar-dark-shadow-color:''; scrollbar-face-color:''; scrollbar-highlight-color:''; scrollbar-shadow-color:''; text-align:left; position:relative; width: 404px; 
padding:2px; height:300px; overflow:scroll; border-width:2px; border-style:outset; background-color:lightgrey;}

 --></STYLE>
<?php
// This file is part of Moodle - http://moodle.org/
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
 * compare.php -  compares two submissions side by side
 *
 * @since 2.0
 * @package    plagiarism_crot
 * @subpackage plagiarism
 * @author     Sergey Butakov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

	require_once("../../config.php");
	global $CFG, $DB;
	require_once($CFG->dirroot."/plagiarism/crot/lib.php");
	require_once($CFG->dirroot."/plagiarism/crot/locallib.php");
	require_once($CFG->dirroot."/course/lib.php");
	require_once($CFG->dirroot."/mod/assignment/lib.php");
	
	// globals
	$plagiarismsettings = (array)get_config('plagiarism');
	$minclustersize = $plagiarismsettings['crot_clustersize'];
	$distfragments  = $plagiarismsettings['crot_clusterdist'];
	$allColors	= explode(",", $plagiarismsettings['crot_colours']);

	$ida = required_param('ida', PARAM_INT);   // submission A
	$idb = required_param('idb', PARAM_INT);   // submission B

	if (! $submA = $DB->get_record("crot_documents", array("id"=> $ida))) {
		error("Doc A ID is incorrect");
	}
	if ($submA->crot_submission_id == 0)
	{
		$isWebA = true;
	} else {
		$isWebA = false;
	}
	
	if (! $submB = $DB->get_record("crot_documents", array("id"=> $idb))) {
		error("Doc B ID is incorrect");
	}

	if ($submB->crot_submission_id == 0)
	{
		$isWebB = true;
	} else  {
		$isWebB = false;
	}

    	// TODO get global assignment id
	$a1 = $DB->get_record("crot_submissions", array("id"=>$submA->crot_submission_id));
	$b1 = get_record("crot_submissions", "id", $submB->crot_submission_id);
	if (!$isWebA) {
		if (! $subA = $DB->get_record("assignment_submissions", array("id"=> $a1->submissionid)))  {
			error("Submission A ID is incorrect");
		}
		if (! $assignA = $DB->get_record("assignment", array("id"=>$subA->assignment))) {
			error("Assignment A ID is incorrect");
		}
		if (! $courseA = $DB->get_record("course", array("id"=>$assignA->course))) {
			error("Course A ID is incorrect");
		}

		require_course_login($courseA);
		if (!isteacher($courseA->id)) {
			error(get_string('have_to_be_a_teacher', 'block_crot'));
		}
	}
	if (!$isWebB) {
		if (! $subB = $DB->get_record("assignment_submissions", array("id"=>$b1->submissionid))) {
			error("Submission B ID is incorrect");
		}
		if (! $assignB = $DB->get_record("assignment", array("id"=> $subB->assignment))) {
			error("Assignment B ID is incorrect");
		}

	if (! $courseB = $DB->get_record("course", array("id"=> $assignB->course))) {
			error("Course B ID is incorrect");
		}
		
		require_course_login($courseB);
		//TODO: need to change to use a has_capability call here.
		if (!isteacher($courseB->id)) {
			error(get_string('have_to_be_a_teacher', 'block_crot'));
		}
	}
	// end of checking permissions

	// built navigation	
	$strmodulename = get_string("block_name", "block_crot");
	$strassignment  = get_string("assignments", "block_crot");
	$navlinks = array();
	$navlinks[] = array('name' => $strmodulename. " - " . $strassignment, 'link' => '', 'type' => 'activity');
	$navigation = build_navigation($navlinks);
	// end of top navigation

	print_header_simple($strmodulename. " - " . $strassignment, "", $navigation, "", "", true, "", navmenu($courseA));
	
	// TODO add to log
	//add_to_log($course->id, "antiplagiarism", "view all", "index.php?id=$course->id", "");

	
	// get content of the 1st document
	$textA = stripslashes($submA->content);
	$textA = ($submA->content);

	// get all hashes for docA
	$sql_query = "SELECT * FROM {$CFG->prefix}crot_fingerprint f WHERE crot_doc_id = $ida ORDER BY position asc";
	$hashesA = $DB->get_records_sql($sql_query);
	// get all hashes for document B
	$sql_query = "SELECT * FROM {$CFG->prefix}crot_fingerprint f WHERE crot_doc_id = $idb ORDER BY position asc";
	$hashesB = $DB->get_records_sql($sql_query);

	// TODO create separate function for coloring ?
	$sameHashA = array ();

	// coloring: step 1 - get same hashes	
	foreach ($hashesA as $hashA) {
		// look for same hash in the array  B
		foreach ($hashesB as $hashB){
			if ($hashA->value == $hashB->value) {
				// same hash found!
				$sameHashA [] = $hashA;
				break;
			}
		}
	}
/*
	// TODO - remove
	// print the fingerprint A
	$j=0;
	echo "<br>CAB A";
	foreach ($sameHashA as $hashA) {
		// look for same hash in the array  B
		echo "<br>$j &nbsp $hashA->position $hashA->gramm";
		$j++;
	}
	echo "<br>End CAB";
*/
	// coloring: step 2 - put hashes into clusters
	$clustersA = array();
	$newcluster = array();
	$sizeA = sizeof($sameHashA);
	for ($i=0; $i<$sizeA; $i++)	{
		if ($i >0 ) {
			if (($sameHashA[$i]->position - $sameHashA[$i-1]->position) <= $distfragments)		{	
			// the hashes are close to each other - put hash into the cluster
				$newcluster[] = $sameHashA[$i];
			}
			else {	// hashes are far from each other - wrap up the  old cluster
				if (sizeof($newcluster) >= $minclustersize)	{
					$clustersA[]= $newcluster;
				}								
				// create a new cluster	
				$newcluster = array();		
				// put the orphan into the new cluster
				$newcluster[] = $sameHashA[$i];
						
			}
			if (($i == ($sizeA -1)) and (sizeof($newcluster) >= $minclustersize)) {
				// last hash
				$clustersA[]= $newcluster;
			}
		} else {	
			// put the first hash into the cluster
			$newcluster[] = $sameHashA[0];			
		}
	}
		
	// coloring: step 3 - add colors to each cluster
	$colorsA = array ();
		// initilize colors
	$i=0;
	foreach ($clustersA as $clusterA) {
		$colorsA[]=$allColors[$i];
		$i++;
	}

	// loop backward to add colors
	for ($i = sizeof ($clustersA) -1; $i>=0; $i--) {
		$clusterA = $clustersA[$i];
		// get borders
		$startPos = $clusterA[0]->position;
		$endPos   = $clusterA[sizeof($clusterA)-1]->position;
		// add colors to the cluster
		$textA = colorer($textA, $startPos, $endPos, $colorsA[$i]);		
	}
	
	// get the content of the second document 

	$textB = stripslashes($submB->content);
	$textB = ($submB->content);
	
	// add colors to doc B
	$sameHashB = array ();
	
	// coloring for doc B: step 1 - get same hashes	
	foreach ($hashesB as $hashB) {		// look for same hash in the array  B
		foreach ($sameHashA as $hashA){
			if ($hashA->value == $hashB->value) {
				// same hash found!
				$sameHashB [] = $hashB;
				break;
			}
		}
	}
	
	// coloring for Doc B - set colors for each hash
	$coloredB = array();
	foreach ($sameHashB as $hashB)	{
		$found = false;
		$oneHashB = new FpWithColors;
		$oneHashB->position =$hashB->position;
		$oneHashB->value =$hashB->value;
		$oneHashB->colors = array ();	// hash might have more than one color
		$i=0;
		$j=0;
		foreach ($clustersA as $clusterA) {
			foreach($clusterA as $hashA) {
				if ($hashA->value == $hashB->value) {
					$found = true;
					$oneHashB->colors[] = $colorsA[$i];
					break;				
				}
			}
			$i++;
		}
		if ($found) {
			// color all non-certains on to the color of the previous hash
			// TODO develop a better procedure for color selecton
			if (sizeof($oneHashB->colors)>1) {
				$oneHashB->colors = $coloredB[$j-1]->colors;
			}
			// inset itno an array
			$coloredB [] = $oneHashB;
			$j++;			
		}
	}
	
	// do clustering
	for ($j = sizeof($coloredB)-1; $j>=0; $j--) {
		$oneHashB = $coloredB[$j];
		if ($j< (sizeof($coloredB)-1)) {
			if (($currcolor == $oneHashB->colors[0]) and(($begpos - $oneHashB->position ) <= $distfragments)) {	// they are close to each other and have same color
				$begpos = $oneHashB->position;
			}
			else {	// wrap up the cluster
				if (($endpos - $begpos)>=$distfragments)  {
					// coloring suspended as the algorithm is not well programmed. need help here!
					//$textB = colorer($textB, $begpos, $endpos, $currcolor);									
					$length = $endpos - $begpos;
				}
				$endpos = $oneHashB->position +1;
				$begpos = $oneHashB->position;
				$currcolor = $oneHashB->colors[0];
			}
			if ($j==0) 	{
				// coloring suspended as the algorithm is not well programmed. need help here!
				//$textB = colorer($textB, $begpos, $endpos, $currcolor);		
			}
		}
		else  {
			// first cluster
			$endpos = $oneHashB->position +1;
			$begpos = $oneHashB->position;
			$currcolor = $oneHashB->colors[0];
		}
	}



	// create and display  2-column table to compare two documents
	// get name A
    	if (!$isWebA)
    	{
		if (! $studentA = get_record("user", "id", $subA->userid)) {
			$strstudentA = "name is unknown";
	    	} else {
			$strstudentA = $studentA->lastname." ".$studentA->firstname.":<br> ".$courseA->shortname.",<br> ".$assignA->name;
		}
	}
	else {
		$wdoc = $db->get_record("crot_web_documents", array("document_id"=>$ida));
		if (strlen($wdoc->link)>40) {
			$linkname = substr($wdoc->link,0,40);
		}
		else  {
			$linkname = $wdoc->link;
		}
		$strstudentA = "Web document:<br>"."<a href=\"$wdoc->link\">$linkname</a>";;
	}
	
	// get name B
	if (!$isWebB) {
	if (! $studentB = $DB->get_record("user", array("id"=>$subB->userid))) {
		$strstudentB = "name is unknown";
		} 
		else {
		$strstudentB = $studentB->lastname." ".$studentB->firstname.":<br> ".$courseB->shortname.",<br> ".$assignB->name;
		}
	}
	else {
		$wdoc = $DB->get_record("crot_web_documents", array("document_id"=>$idb));
		if (strlen($wdoc->link)>40) {
			$linkname = substr($wdoc->link,0,40);
		}
		else {
			$linkname = $wdoc->link;
		}
		$strstudentB = "Web document:<br>"."Source: <a href=\"".urldecode($wdoc->link)."\" target=\"_blank\">".urldecode($linkname)."</a>";;
	}

	$textA = "<div id=\"example\"><FONT SIZE=1>".ereg_replace("\n","<br>",$textA)."</font> </div>";
	$textB = "<div id=\"example\"><FONT SIZE=1><b>".ereg_replace("\n","<br>",$textB)."</font></div>";
	$table->head  = array ($strstudentA, $strstudentB);
	$table->align = array ("center", "center");
	$table->data[] = array ($textA, $textB);
	print_table($table);

	// footer 
	print_footer($courseA);

?>
