<?php

    if (!defined('MOODLE_INTERNAL')) {
        die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
    }

// overrides php limits
$maxtimelimit = ini_get('max_execution_time');
ini_set('max_execution_time', 18000);
$maxmemoryamount = ini_get('memory_limit');
// set large amount of memory for the processing
// fingeprint calcualtion mey be very memory consuming especially for large documents from the internet
ini_set('memory_limit', '1024M');


// store current time for perf measurements
$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;


global $CFG;

require_once($CFG->dirroot.'/plagiarism/crot/lib.php');
require_once($CFG->dirroot.'/plagiarism/crot/locallib.php');
require_once($CFG->dirroot."/course/lib.php");
require_once($CFG->dirroot."/mod/assignment/lib.php");

$gram_size 	= $CFG->block_crot_grammarsize; 
$window_size	= $CFG->block_crot_windowsize;
$query_size 	= $CFG->block_crot_global_search_query_size;
$msnkey 	= $CFG->block_crot_live_key;
$culture_info	= $CFG->block_crot_culture_info;
$globs = $CFG->block_crot_percentage_of_search_queries;
$todown = $CFG->block_crot_number_of_web_documents;
//
if (empty($gram_size)||empty($window_size)) {
        die('The block is not properly set. Please set the block in admin/modules/blocks menu');    /// the initial settigns were not properly set up
}

// main loop on assignment_submission table - check if there are assignments marked for the check up 
$sql_query = "SELECT s.*, c.is_local, c.is_global FROM {$CFG->prefix}assignment_submissions s, {$CFG->prefix}crot_assignments c WHERE s.assignment = c.assignment_id AND (c.is_local = 1 OR c.is_global = 1)";
$submissions = get_records_sql($sql_query);
if (!empty($submissions)){
foreach ($submissions as $asubmission){
	//if the respctive record exists in the crot_submission database then skip

	if (! $unprocessedsubm = get_record("crot_submissions", "submissionid", $asubmission->id)) {
        	echo "\nsubmission $asubmission->id was not processed yet. start processing now ... \n" ;
		$atime = microtime();
        $atime = explode(" ",$atime);
        $atime = $atime[1] + $atime[0];
        $astarttime = $atime;
		// get assignment
		if (! $assignment = get_record("assignment", "id", $asubmission->assignment)) {
        		error("Assignment ID is incorrect");
   		}

		// get file name
		$apath= $CFG->dataroot."/$assignment->course/moddata/assignment/$asubmission->assignment/$asubmission->userid";
		$files = scandir($apath, 1);
		//TODO add loop on the documents folder as well as loop for unzipping
		$apath = $apath."/$files[0]";
		// call tokenizer to get plain text and store it in crot_submissions
		$atext = tokenizer ($apath);
		// update the crot_submissions table
			// delete if exists
		delete_records("crot_submissions", "submissionid", $asubmission->id);
			// insert the new record
		$record->submissionid=$asubmission->id;
		$record->updated = time();
		$submid = insert_record("crot_submissions", $record);
		// insert into documents
		$docrecord->crot_submission_id = $submid;	
		// at this point we cut the text to 999700 bytes.
		// the actual number of chars can be much shorter as we are working with mbstring
		// this should be changed to the solution provided below (see TODO few lines below)
		// at this time we  decided just to truncate the text as text large than 1 MB will require lots of memory and time for fingerprinting
		$atext=substr($atext, 0, 999000);
		$docrecord->content = addslashes($atext);

		// get max_allowed_packet
		/*
		// TODO complete this check up
		//compare it with size of docrecord
		if ($map<sizeof($docrecord->content)){
		    //if docrecord is bigger try to change max allowed packet for the current session
		    $sql_query ="SET session max_allowed_packet=16000000";
		    $s1 = get_records_sql($sql_query);
		    $sql_query ="show session variables like 'max_allowed_packet'";
		    $s1 = get_records_sql($sql_query);
		    echo "<br>\nwas trying to set 16M. Got ".$s1["max_allowed_packet"]->Value;
		}
		//if successful try to insert record
		// overwise truncate text
		// restore default max_allowed_packett
		*/
		
		$docid = insert_record("crot_documents", $docrecord);	

		// figerprinting - calculate and store the fingerprints into the table
		$atext=mb_strtolower($atext, "utf-8");
		
		// get f/print
		$fingerp = array();
		$fingerp = GetFingerprint($atext);

		// store fingerprint
		foreach ($fingerp as $fp) {
			$hashrecord->position = $fp->position;
			$hashrecord->crot_doc_id = $docid;
			$hashrecord->value = $fp->value;
			insert_record("crot_fingerprint", $hashrecord);	
		}
		
		
		if ($asubmission->is_local==1) {
			// comparing fingerprints and updating crot_submission_pair table
			// select all submissions that has at least on common f/print with the current document
			$sql_query ="SELECT id
				FROM {$CFG->prefix}crot_documents asg
					WHERE exists (
						select * from
						{$CFG->prefix}crot_fingerprint fp1
						inner join {$CFG->prefix}crot_fingerprint fp2 on fp1.value = fp2.value
							where fp2.crot_doc_id = asg.id and fp1.crot_doc_id = $docid
					)";
			$pair_submissions = get_records_sql($sql_query);
		
			foreach ($pair_submissions as $pair_submission){
				// check if id exists in web_doc table then don't compare because
				// we consider only local documents here
				if ($webdoc = get_record("crot_web_documents", "document_id", $pair_submission->id))
					continue;
				//compare two fingerprints to get the number of same hashes
				if ($docid!=$pair_submission->id){
					$sql_query = "select sum(case when cnt1 < cnt2 then cnt1 else cnt2 end) cnt
						from
						(
							select count(*) as cnt1, (
								select count(*)
								from {$CFG->prefix}crot_fingerprint fp2
								where 		fp2.crot_doc_id 	= $docid  
									and	fp2.value		= fp1.value
							) as cnt2
							from {$CFG->prefix}crot_fingerprint fp1
							where fp1.crot_doc_id = $pair_submission->id
							group by fp1.value
						) t";
					$similarnumber = get_record_sql($sql_query);
					// takes id1 id2 and create/update record with the number of similar hashes
					$sql_query ="SELECT * FROM {$CFG->prefix}crot_submission_pair 
							where (submission_a_id = $asubmission->id and submission_b_id = $pair_submission->id) 
							OR (submission_a_id = $pair_submission->id and submission_b_id = $asubmission->id)";
					$pair = get_record_sql($sql_query);
					if (! $pair){
						// insert
						$pair_record->submission_a_id = $docid;
						$pair_record->submission_b_id = $pair_submission->id;
						$pair_record->number_of_same_hashes = $similarnumber->cnt;
						insert_record("crot_submission_pair", $pair_record);	
					} else {
						// TODO update		
					}
				}	// end of comparing with local documents
			}
		} // end for local search
		if ($asubmission->is_global==1) {
			// global search
			echo "\n $asubmission->id is selected for global search. Starting global search\n";
			// strip text
			$atext = StripText($atext," ");
			// create search queries
			$words = array();
			$words = preg_split("/[\s]+/", trim(StripText($atext, " ")));
			$max = sizeof($words) - $query_size +1;
			$queries = array ();
			for ($i=0; $i < $max; $i++) {
				$query = "";
				for ($j=$i; ($j-$i)<$query_size; $j++){
					$query = $query." ".$words[$j];
				}
				$queries[] = $query;
			} 	// queries are ready!
			// create list of URLs
			$allURLs = new Urls;	
			$i=0;
			srand((float) microtime() * 10000000);

			// randomly select x% of queries
			$rand_keys = array_rand($queries, (sizeof($queries)/100)*$globs);
			$narr = array();
			foreach ($rand_keys as $mkey) {
				$narr[]=$queries[$mkey];
			}
			$queries = $narr;
						
			foreach ($queries as $query) {
				$query = mb_ereg_replace("/[^\w\d]/g","",$query);
				$query = "'".trim($query)."'";
				$i++;
				try {
					$searchres = fetchBingResults($query, $todown, $msnkey, $culture_info);
				}
				catch (Exception $e) {
					print_error("exception in querying MSN!\n");
				}
				foreach($searchres as $hit) 	{
					$ahit = new oneUrl;
					$ahit->mainUrl = $hit;
					$ahit->queryID = md5($hit);
					$ahit->msUrl = $hit;
					$ahit->counter = 1;
					$allURLs->addUrl($ahit);
				}// end parsing results
			}// end sending queries: we have top x results

			$tarr = $allURLs->getMax($todown);
			$k=0;
			// get top results
			foreach($tarr as $manUrl) {	
				//get content of downloaded web document
				// in php ini allow_url_fopen = On
				$path = $manUrl->mainUrl;
				// get content from the remote file
				$mega = array ();

				// get content  and get encoding
				if (trim($path)!="")  {
					try {
					  $result = getremotecontent( $path );
					  if (trim($result)==""){
					  	continue;
					  }
					}
					catch (Exception $e) {
					  print_error("exception in downloading!\n");
					  $result = "Was not able to download the respective resource";
					}
				}
				else {
					continue;
				}

				$result = mb_ereg_replace('#\s{2,}#',' ',$result);  

				// split into strings and remove empty ones
				$strs  = explode ("\n", $result);
				$result = "";  
				foreach ($strs as $st) 	{
					$st = trim($st);
					if ($st!="")  {
						$result = $result.mb_ereg_replace('/\s\s+/', ' ', $st)." \n";
					}
				}
				// insert doc into crot_doc table
				$wdocrecord->crot_submission_id = 0;	
				$wdocrecord->content = addslashes($result);
				$wdocid = insert_record("crot_documents", $wdocrecord);	
				// insert doc into web_doc table
				$webdocrecord->document_id = $wdocid;
				$webdocrecord->related_doc_id = $docid;
				$webdocrecord->link=urlencode($manUrl->mainUrl);
				$webdocrecord->link_live=urlencode($manUrl->msUrl);
				$webdocrecord->is_from_cache=false;
				$webdocid = insert_record("crot_web_documents", $webdocrecord);			

				// figerprinting - calculate and store the fingerprints into the table
				$result=mb_convert_case($result, MB_CASE_LOWER, "UTF-8");

				$fingerp = array();
				try {
					$fingerp = GetFingerprint($result);
				}
				catch (Exception $e)
				{
					print_error("exception in FP calc\n");
					continue;
				}

				// store fingerprint
				foreach ($fingerp as $fp)
				{
					$hashrecord->position = $fp->position;
					$hashrecord->crot_doc_id = $wdocid;		
					$hashrecord->value = $fp->value;
					insert_record("crot_fingerprint", $hashrecord);	
				}

				//compare two fingerprints to get the number of same hashes
				$sql_query = "select sum(case when cnt1 < cnt2 then cnt1 else cnt2 end) cnt
					from
					(
						select count(*) as cnt1, (
							select count(*)
							from {$CFG->prefix}crot_fingerprint fp2
							where 		fp2.crot_doc_id 	= $docid  
								and	fp2.value		= fp1.value
						) as cnt2
						from {$CFG->prefix}crot_fingerprint fp1
						where fp1.crot_doc_id = $wdocid
						group by fp1.value
					) t";
				try {
					$similarnumber = get_record_sql($sql_query);
				}
				catch (Exception $e) {
					print_error("exception in query\n");
					continue;
				}
				// check that the number of same hashes is not null
				if(!is_null($similarnumber->cnt) && $similarnumber->cnt!=0 ){
					// add record to pair table
					$pair_record->submission_a_id = $docid;
					$pair_record->submission_b_id = $wdocid;
					$pair_record->number_of_same_hashes = $similarnumber->cnt;
					$ppair = insert_record("crot_submission_pair", $pair_record);
				} else {
					//if null then remove the web document and its fingerprint
					// remove from doc
					delete_records("crot_documents", "id", $wdocid);
					// remove from web_doc
					//delete_records("crot_web_documents", "id", $webdocid);
					// remove from fingerprints
					delete_records("crot_fingerprint", "crot_doc_id", $wdocid);
				}					
			}	
		
		} // end global search
		
		
		// done !!!
		echo "\nsubmission $asubmission->id was sucessfully processed\n" ;
    	}else{
    		// submission was already processed!
	}
	//end of search

} // end of the main loop
}
else{
    echo "Nothing to process!";
    }    
// set back normal values for php limits
ini_set('max_execution_time', $maxtimelimit);
ini_set('memory_limit', $maxmemoryamount);

// calc and display exec time
$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);
echo "\nThe assignments were processesd by crot in ".$totaltime." seconds\n";
?>
