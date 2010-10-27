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
 * locallib.php - Contains Crot specific functions.
 *
 * @since 2.0
 * @package    plagiarism_crot
 * @subpackage plagiarism
 * @author     Sergey Butakov and Vlad Shcherbinin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot.'/plagiarism/crot/textlib.php');


//
// class fingerprint
class Fingerprint {
	public $value;		// value
	public $position;	// original position in the document
} 	// end of Fingeprint

class FpWithColors extends Fingerprint {
	public $colors;	// array of possible colors
}
//
// to store one URL
//

class oneUrl {
	public $mainUrl;
	public $queryID;
	public $msUrl;
	public $counter;
}

//
// Class URLs stores list of URLs and helps to recognize similar and retrieve top search results
//

class Urls {
	public $list = array();
	
	function addUrl ($newURL)
	{
		// look for the same ID
		$found = false;
		foreach ($this->list as $anURL) {
			if ($anURL->queryID == $newURL->queryID) {
				// if found increase counter for this url
				$found = true;
				$anURL->counter++;
				break;
			}
		}
		if (!$found) {		// if not found insert new
			if (trim($newURL->mainUrl)!=""){
				$this->list[]=$newURL;
			}
		}
		return true;
	} // end addURL


	function getMax ($howmany)
	{
		// select the most popular links
		$maxs = array();
		$selected = array();
		for ($j=0; $j<$howmany; $j++) {
			$max=-100;
			$k=0;
			foreach ($this->list as $ml) {	
				if (($ml->counter > $max) and (!in_array($k, $maxs))) {
					$max = $ml->counter;
					$maxs[$j]=$k;
				}
				$k++;
			}
			$selected[] = $this->list[$maxs[$j]];
		}
		return $selected;
	}	//end getmax
	
	function getTotal()
	{
		$k=0;
		foreach ($this->list as $ml) {	
			$k=$k+$ml->counter;
		}
		return $k;
	} // end getTotal
	
}	// end of class


/*
* function tokenizer
* it takes a path to a file and returns a string variable that contains plain text extracted from the file
*/
function tokenizer($path) {
	global $CFG;
	if (is_readable($path)){
		// USE extension to choose tokenizer
		$path_parts = pathinfo($path);
		switch (strtolower($path_parts['extension'])):
			case "pdf":
				$result = pdf2text($path);
				return $result;
			case "doc":
		    		$result = html_entity_decode(doc2text($path),null,'UTF-8');     
				return $result;
			case "docx":
				$result = getTextFromZippedXML($path, "word/document.xml");
				return $result;
			case "odt":
				$result = getTextFromZippedXML($path, "content.xml");
				return $result;
			case "rtf":
				$result = rtf2text($path);
				return $result;
			case "txt":
				return file_get_contents($path);
			case "cpp":
				return file_get_contents($path);
			case "java":
				return file_get_contents($path);
			default:
    				return "unknown file type";
		endswitch;

	}
}// end of function tokenizer

//
//
// function StripText
// it takes a text and return the text without deliiters
//
function StripText ($atext, $subst)
{
	//TODO: extend the list of delimiters
	$delimiters = array(",",";"," ",".","\n","\t","|","\'","*", "-","'","?");
	foreach ($delimiters as $delimiter)
	{
		$substitutions[]=$subst;
	}
	return str_replace($delimiters, $substitutions, $atext);	
}	// end stripText

//
// function GetFingerprint
// it takes plain text w/o delimiters and returns fingerprint
//
function GetFingerprint ($atext)
{
	global $CFG;
	$gram_size 	= $CFG->block_crot_grammarsize; 
	$window_size	= $CFG->block_crot_windowsize;
	$hashes = array();
	try{
		$stripped_text = StripText($atext,"");
	}
  	catch (Exception $e)  {
    		echo "exception with stripping\n"; flush();
     	}
	$text_len = mb_strlen($stripped_text, "utf-8") - $gram_size;

	// get the original positions
	$offset=0;
	$curtext = $atext;
	preg_match_all('/.|\n/u', $stripped_text, $matches);
	$curstripped=$matches[0];
	preg_match_all('/.|\n/u', $atext, $matches2);
	$btext=$matches2[0];
	$bpos=0;
	$offset=0;
	$values = array();
	for ($i=0; $i<$text_len; $i++){
		while ($btext[$offset]!=$curstripped[$i]){
			$offset++;
		}
		$orig_positions[$i]=$offset;
		$offset++;
		$values[$i]=hash('md5', mb_substr($stripped_text, $i, $gram_size, "utf-8"));
	}
	// compiling fingerprint
	$fingers = array();
	$fp = array();
	$up = $text_len - $window_size + 1;
	$i=0;
	$minHashPos  = $window_size -1;
	while ($i<$up){
		if ($i==0 || $minHashPos == $i-1 ){
			$minHashPos = $i+$window_size -1;
			$hash = new Fingerprint();
			$hash->value=$values[$minHashPos];
			$hash->position=$orig_positions[$minHashPos];
			$min_hash = $hash;
			for ($j=$i+$window_size -1; $j>=$i;$j--){
				if ($values[$j] < $min_hash->value){
					$hash = new Fingerprint();
					$hash->value=$values[$j];
					$hash->position=$orig_positions[$j];
					$min_hash = $hash;
					$minHashPos = $j;
				}
			}
			$i = $minHashPos+1;
			$fingers[] = $min_hash;
		} else {
			if ($values[$i+$window_size -1] < $min_hash->value){
				$minHashPos = $i+$window_size -1;
				$hash = new Fingerprint();
				$hash->value=$values[$minHashPos];
				$hash->position=$orig_positions[$minHashPos];
				$min_hash = $hash;
				$fingers[] = $min_hash;
				$i = $minHashPos+1;	
			}
		}
	}

	return $fingers;
} // end of GetFingerprint


/*
* it replaces part of the text from $start to $end with the same text but colored with $color
*/
function colorer($text, $start, $end, $color) {
	return mb_substr($text,0,$start, "utf-8")."<font color=\"$color\">".mb_substr($text,$start,$end-$start+1, "utf-8")."</font>".mb_substr($text,$end+1, "utf-8");
}// end of function colorer



///new search
// fetches from search.msn.com results for the query $query, using the API
// thanks to http://www.bing.com/community/blogs/developer/archive/2009/05/28/php-and-xml.aspx
// return array of urls

//  	$searchres = fetchBingResults($query, $todown, $msnkey, $culture_info);

function fetchBingResults($query, $querysize, $msnsoapkey, $culture_info) {
	// set proxy enviroment
	global $CFG;

	if (!empty($CFG->proxyhost)){
		$r_default_context = stream_context_get_default
	    		(
	    			array
				(
				'http' => array
		    			( // TODO add user name and password
		    			'proxy' => $CFG->proxyhost.':'.$CFG->proxyport,
		    			'request_fulluri' => True,
		    			),
				)
	    		);

		// Though we said system wide, some extensions need a little coaxing.
		libxml_set_streams_context($r_default_context);
	}
	
	$results = array();
	$request = 'http://api.bing.net/xml.aspx?Appid=' . $msnsoapkey . 
		'&sources=web&Query=' . urlencode( $query) . 
//		TODO add query count
		'&culture='.$culture_info. 
//		'&Web.Count='.$querysize.
		'&Web.Options=DisableHostCollapsing+DisableQueryAlterations'.
		'&Options=DisableLocationDetection&Version=2.2';
		$response = new DOMDocument();
		$response->load($request);
		$webResults = $response->getElementsByTagName("WebResult");
     		if ($webResults->length<>0){
	       		foreach($webResults as $value){
				$results[]=$value->childNodes->item(2)->nodeValue;
	       		}
		}	
	return $results;
}

				

///
// this function removes html tags from the text 
function strip_html_tags( $text )
{
	// PHP's strip_tags() function. Modified though
	// TODO add try / catch
	$text = preg_replace("@<script[^>]*?>.*?</script>@si", " ", $text );
	$text = preg_replace("@<style[^>]*?>.*?</style>@siU", " ", $text );
/*	$text = preg_replace("@<head[^>]*?>.*?</head>@siu", " ", $text );
	$text = preg_replace("@<object[^>]*?.*?</object>@siu", " ", $text );
	$text = preg_replace("@<embed[^>]*?.*?</embed>@siu", " ", $text );
	$text = preg_replace("@<applet[^>]*?.*?</applet>@siu", " ", $text );
	$text = preg_replace("@<noframes[^>]*?.*?</noframes>@siu", " ", $text );
	$text = preg_replace("@<noscript[^>]*?.*?</noscript>@siu", " ", $text );
	$text = preg_replace("@<noembed[^>]*?.*?</noembed>@siu", " ", $text );
*/
	// Remove all remaining tags and comments and return.
	return strip_tags( $text );
}

// function getremotecontent 
// takes a path to the remote resoutce
// returns plain text (hopefully)

function getremotecontent($url)
{
	global $CFG;
    	// analyze the extension (type) of the resource
    	// TODO it would be better to define type by the content marker in the stream
    	$splittedurl = parse_url($url);
    	$path = $splittedurl["path"];
		$path_parts = pathinfo($path);
		$tmpdir = ini_get('upload_tmp_dir');
		$tmpfilename = $tmpdir."/remove.me";
		if (!isset($path_parts['extension'])){
			$path_parts['extension'] ='';
		}
		// set user agent to trick some web sites
		ini_set('user_agent', 
		  'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-GB; rv:1.9.0.3) Gecko/2008092417 Firefox/3.5.2');
		switch (strtolower($path_parts['extension'])):
			case "doc":
        			// download and save;     			
        			$infile = @file_get_contents($url, FILE_BINARY);
				if (strlen($infile)>0){
					file_put_contents($tmpfilename, $infile, FILE_BINARY);	
					//check if file size is too large then don't download it
					//TODO adjust max size in settings
					if (filesize($tmpfilename)<1000000){
					    $result = html_entity_decode(doc2text($tmpfilename),null,'UTF-8');     
					}else{
					    echo"\nFile $url was not dowloaded because of its large size\n";
					    $result="the file is  too large";
					}
					unlink($tmpfilename);
				} else{
			    		$result = "can't read TEXT from the remote MS-Word file located at ".$url;
				}
				return $result;			
			case "docx":
        			// download and save;
        			$infile = @file_get_contents($url, FILE_BINARY);
				file_put_contents($tmpfilename, $infile, FILE_BINARY);	
				$result = getTextFromZippedXML($tmpfilename, "word/document.xml");
				unlink($tmpfilename);
				return $result;
			case "txt":
				return file_get_contents($url);
			case "java":
				return file_get_contents($url);
			case "cpp":
				return file_get_contents($url);
			case "c":
				return file_get_contents($url);
			case "pdf":
				return pdf2text($url);
			case "ppt":
				return ppt2text($url);
			default:
            			// assuming it is html file
            			$idt=0;
            			$text2 = file_get_contents( $url);
            			while ((empty($text2) && $idt<3)){
            			    $idt++;
            			    echo "\nTrying to download $url. Attempt $idt\n";
            			    $text2 = file_get_contents( $url);
            			}
			   	preg_match( '@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s+charset=([^\s"]+))?@i',$text2, $matches );
			    	if ( isset( $matches[1] ) ) $mime = $matches[1];
			    	if ( isset( $matches[3] ) ) {
				      $charset = $matches[3];
				} else{
				      $charset = mb_detect_encoding($text2);
				      $text2 = "Unknown Encoding! You might need to check the direct link".$text2;
				}
			    	$text2 = str_replace("<br>", "\n", $text2);
			    	$text2 = str_replace("<br >", "\n", $text2);
			    	$text2 = str_replace("<br/>", "\n", $text2);
			    	$text2 =  strip_html_tags($text2);
			    	$text2 = @iconv( $charset, "utf-8", $text2);       
			    	return $text2;
		endswitch;
    
    // get it and put in to temporary file
    // send to to tokenizer
}

