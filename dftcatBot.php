<?php

ini_set('memory_limit','1G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once( 'C:\Users\Maximilian Doerr\Documents\NetBeansProjects\Peachy/Peachy/Init.php' );

$site = Peachy::newWiki( "cyberbotii" );
$pages = 0;

$infoboxes = array( "Infobox closed London station", "London stations", "Closed London stations", "Infobox Closed London station", "Infobox london station", "Infobox London station", "Infobox GB station", "Infobox British Railway Station", "Infobox UK major railway station", "Infobox UK railway station", "Infobox UK minor railway station", "Infobox UK medium railway station", "British stations", "UK stations PTE", "UK stations", "Infobox UK station" );

$pagesToModify = array_merge( $site->categorymembers( "Category:DfT Category A stations", false, null, -1 ), $site->categorymembers( "Category:DfT Category B stations", false, null, -1 ), $site->categorymembers( "Category:DfT Category C1 stations", false, null, -1 ), $site->categorymembers( "Category:DfT Category C2 stations", false, null, -1 ), $site->categorymembers( "Category:DfT Category D stations", false, null, -1 ), $site->categorymembers( "Category:DfT Category E stations", false, null, -1 ), $site->categorymembers( "Category:DfT Category F1 stations", false, null, -1 ), $site->categorymembers( "Category:DfT Category F2 stations", false, null, -1 ) );

foreach( $pagesToModify as $page ) {   
	$page = $site->initPage( null, $page['pageid'] );
	$oldtext = $newtext = $page->get_text();
	$template = getInfoBox( $newtext );
	if( $template === false ) continue;
	if( isset( $template['parameters']['dft_category'] ) && !empty( $template['parameters']['dft_category'] ) ) {
		if( ($template['parameters']['dft_category'] == "A" || $template['parameters']['dft_category'] == "B" || $template['parameters']['dft_category'] == "C1" || $template['parameters']['dft_category'] == "C2" || $template['parameters']['dft_category'] == "D" || $template['parameters']['dft_category'] == "E" || $template['parameters']['dft_category'] == "F1" || $template['parameters']['dft_category'] == "F2") ) {
			$newtext = preg_replace( '/\[\[\:?Category\:DfT Category (A|B|C1|C2|D|E|F1|F2) stations.*?\]\]/i', "", $newtext );
			if( $newtext != $oldtext ) {$page->edit( $newtext, "Cleaning up redundant categories.  Category defined in infobox." );
			$pages++;}
		} else {
			continue;
			$talk = $site->initPage( $page->get_discussion() );
			$talk->newsection( "The {{param|dft_category}} in the infobox is being improperly used.  It needs to be a single letter, or the automatic categorization will not work.~~~~", "Incorrect usage of the {{param|dft_category}} detected", "Notification of improper parameter usage" );
			$pages++; 
		}
	} else {
		preg_match_all( '/\[\[\:?Category\:DfT Category (A|B|C1|C2|D|E|F1|F2) stations.*?\]\]/i', $newtext, $matches );
		if( count( $matches[0] ) > 1 ) {
			continue;
			$talk = $site->initPage( $page->get_discussion() );
			$talk->newsection( "There are multiple DfT Categories present in the article.  Therefore, I left the article alone.~~~~", "Multiple DfT categories present", "Notification of multiple DfT categories" );
			$pages++;
		} elseif( count( $matches[0] ) < 1 ) continue;
		else {
			$category = $matches[1][0];
			$newtext = preg_replace( '/\[\[\:?Category\:DfT Category (A|B|C1|C2|D|E|F1|F2) stations.*?\]\]/i', "", $newtext );
			$template['parameters']['dft_category'] = $category;
			$templatestring = "{{".$template['name']."\n";
			$maxlen = 1;
			foreach( $template['parameters'] as $parameter=>$value ) if( strlen( $parameter ) > $maxlen ) $maxlen = strlen( $parameter ); 
			foreach( $template['parameters'] as $parameter=>$value ) {
				$templatestring .= "| $parameter".str_repeat(" ", $maxlen - strlen($parameter))." = $value\n";
			} 
			$templatestring .= "}}";
			$newtext = preg_replace( '/\{\{(Template\:)?('.implode( "|", $infoboxes ).')((\{\{(\n|.)*?\}\})|.|\n)*?\|?((\{\{(\n|.)*?\}\})|.|\n)*?\}\}/i', $templatestring, $newtext );
			$page->edit( $newtext, "Moving category to infobox parameter" );  
			$pages++;
		}
	}
}

function getInfoBox( $text ) {
	global $infoboxes;
	$returnArray = array();
	preg_match( '/\{\{(Template\:)?('.implode( "|", $infoboxes ).')((\{\{(\n|.)*?\}\})|.|\n)*?\|?((\{\{(\n|.)*?\}\})|.|\n)*?\}\}/i', $text, $match );
	if( empty($match) ) return false;
	$returnArray['name'] = $match[2];
	$match = preg_replace( '/\{\{(Template:)?('.$match[2].')((\{\{.*?\}\})|.|\n)*?\|/i', "", $match[0] );
	$returnArray['parameters'] = getTemplateParameters( $match );
	return $returnArray;
}

//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.
function getTemplateParameters( $templateString ) {
	$templateString = trim( substr_replace( $templateString, "", strrpos( $templateString, "}}" ) ) );
	$returnArray = array();
	$tArray = array();
	while( true ) {
		$offset = 0;
		$pipepos = strpos( $templateString, "|", $offset);
		$tstart = strpos( $templateString, "{{", $offset );   
		$tend = strpos( $templateString, "}}", $offset );
		$lstart = strpos( $templateString, "[[", $offset );
		$lend = strpos( $templateString, "]]", $offset );
		while( true ) {
			if( $lend !== false && $tend !== false ) $offset = min( array( $tend, $lend ) ) + 1;
			elseif( $lend === false ) $offset = $tend + 1;
			else $offset = $lend + 1;	 
			while( ( $tstart < $pipepos && $tend > $pipepos ) || ( $lstart < $pipepos && $lend > $pipepos ) ) $pipepos = strpos( $templateString, "|", $pipepos + 1 );
			$tstart = strpos( $templateString, "{{", $offset );   
			$tend = strpos( $templateString, "}}", $offset );
			$lstart = strpos( $templateString, "[[", $offset );
			$lend = strpos( $templateString, "]]", $offset );
			if( ( $pipepos < $tstart || $tstart === false ) && ( $pipepos < $lstart || $lstart === false ) ) break;
		}
		if( $pipepos !== false ) {  
			$tArray[] = substr( $templateString, 0, $pipepos  );
			$templateString = substr_replace( $templateString, "", 0, $pipepos + 1 );
		} else {
			$tArray[] = $templateString;
			break;
		}
	}
	$count = 0;
	foreach( $tArray as $tid => $tstring ) $tArray[$tid] = explode( '=', $tstring, 2 );
	foreach( $tArray as $array ) {
		$count++;
		if( count( $array ) == 2 ) $returnArray[trim( $array[0] )] = trim( $array[1] );
		else $returnArray[ $count ] = trim( $array[0] );
	}
	return $returnArray;
}
   
?>
