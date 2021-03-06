<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * Library for documents generation
 *
 * @package TestLink
 * @author	Martin Havlat <havlat@users.sourceforge.net>
 * @copyright 2007-2009, TestLink community 
 * @version $Id: print.inc.php,v 1.106 2010/08/04 03:08:24 amkhullar Exp $
 * @uses printDocument.php
 *
 *
 * @internal 
 *
 * Revisions:
 *  20100803 - amitkhullar - Added condition check for null req. custom fields @line 230/346  
 *  20100723 - asimon - BUGID 3459: added platform ID to renderTestCaseForPrinting(),
 *                                  renderTestSpecTreeForPrinting() and
 *                                  renderTestPlanForPrinting()
 *  20100723 - asimon - BUGID 3451 and related: solved by changes in renderTestCaseForPrinting()
 *  20100326 - asimon - started refactoring and moving of requirement printing functions from 
 *                      req classes to this file for generation of req spec document
 *                      like it is done for testcases (BUGID 3067)
 *  20100306 - contribution by romans - BUGID 0003235: Printing Out Test Report Shows
 *                                     empty Column Headers for "Steps" and "Step Actions"
 *
 *  20100106 - franciscom - Multiple Test Case Steps Feature
 *  20100105 - franciscom - added tableColspan,firstColWidth config 
 *  20090906 - franciscom - added contribution by Eloff:
 *                          - regarding platforms feature
 *                          - Moved toc to be outside of report content
 *                          - Changed the anchor ids
 *
 *  20090902 - franciscom - preconditions (printed only if not empty).
 *  20090719 - franciscom - added Test Case CF location management 
 *                          added utility functions to clean up code
 *                          and have  a more modular design
 *
 *  20090330 - franciscom - fixed internal bug when decoding user names
 *	20090410 - amkhullar - BUGID 2368
 *  20090330 - franciscom - renderTestSpecTreeForPrinting() - 
 *                          added logic to print ALWAYS test plan custom fields
 *  20090329 - franciscom - renderTestCaseForPrinting() refactoring of code regarding custom fields
 *                          renderTestSuiteNodeForPrinting() - print ALWAYS custom fields
 * 	20090326 - amkhullar - BUGID 2207 - Code to Display linked bugs to a TC in Test Report
 *	20090322 - amkhullar - added check box for Test Case Custom Field display on Test Plan/Report
 *  20090223 - havlatm - estimated execution moved to extra chapter, refactoring a few functions
 * 	20090129 - havlatm - removed base tag from header (problems with internal links for some browsers)
 *  20081207 - franciscom - BUGID 1910 - changes on display of estimated execution time
 *                          added code to display CF with scope='execution'
 * 
 *  20080820 - franciscom - added contribution (BUGID 1670)
 *                         Test Plan report:
 *                         Total Estimated execution time will be printed
 *                         on table of contents. 
 *                         Compute of this time can be done if: 
 *                         - Custom Field with Name CF_ESTIMATED_EXEC_TIME exists
 *                         - Custom Field is managed at design time
 *                         - Custom Field is assigned to Test Cases
 *                         
 *                         Important Note:
 *                         Lots of controls must be developed to avoid problems 
 *                         presenting with results, when user use time with decimal part.
 *                         Example:
 *                         14.6 minuts what does means? 
 *                         a) 14 min and 6 seconds?  
 *                         b) 14 min and 6% of 1 minute => 14 min 3.6 seconds ?
 *
 *                         Implementation at (20080820) is very simple => is user
 *                         responsibility to use good times (may be always interger values)
 *                         to avoid problems.
 *                         Another choice: TL must round individual times before doing sum.
 *
 *	20080819 - franciscom - renderTestCaseForPrinting() - removed mysql only code
 *	20080602 - franciscom - display testcase external id
 *	20080525 - havlatm - fixed missing test result
 *	20080505 - franciscom - renderTestCaseForPrinting() - added custom fields
 *	20080418 - franciscom - document_generation configuration .
 *                             removed tlCfg global coupling
 *	20071014 - franciscom - renderTestCaseForPrinting() added printing of test case version
 *	20070509 - franciscom - changes in renderTestSpecTreeForPrinting() interface
 *
 */ 

/** uses get_bugs_for_exec() */
require_once("exec.inc.php");


/**
 * render a requirement as HTML code for printing
 * 
 * @author Andreas Simon
 * 
 * @param resource $db
 * @param array $node the node to be printed
 * @param array $printingOptions
 * @param string $tocPrefix Prefix to be printed in TOC before title of node
 * @param int $level
 * @param int $tprojectID
 * 
 * @return string $output HTML Code
 */
function renderRequirementNodeForPrinting(&$db,$node, &$printingOptions, $tocPrefix, $level, $tprojectID) {
	
	static $tableColspan;
	static $firstColWidth;
	static $labels;
	static $title_separator;
	static $req_mgr;
	static $tplan_mgr;
	static $req_cfg;
	static $req_spec_cfg;
	static $reqStatusLabels;
	static $reqTypeLabels;
	
	if (!$req_mgr) {
		$req_cfg = config_get('req_cfg');
		$req_spec_cfg = config_get('req_spec_cfg');
		$firstColWidth = '20%';
		$tableColspan = 2;
		$labels = array('requirement' => 'requirement', 'status' => 'status', 
		                'scope' => 'scope', 'type' => 'type', 'author' => 'author',
		                'relations' => 'relations',
		                'coverage' => 'coverage',
		                'custom_field' => 'custom_field', 'relation_project' => 'relation_project',
		                'related_tcs' => 'related_tcs');
		$labels = init_labels($labels);
		$reqStatusLabels = init_labels($req_cfg->status_labels);
	    $reqTypeLabels = init_labels($req_cfg->type_labels);
		$title_separator = config_get('gui_title_separator_1');
		$req_mgr = new requirement_mgr($db);
		$tplan_mgr = new testplan($db);
	}
	
	$arrReq = $req_mgr->get_by_id($node['id']);
	$req = $arrReq[0];
	$name =  htmlspecialchars($req["req_doc_id"] . $title_separator . $req['title']);
		
	$output = "<table class=\"req\"><tr><th colspan=\"$tableColspan\">" .
	          "<span class=\"label\">{$labels['requirement']}:</span> " . $name . "</th></tr>\n";	
	
	if ($printingOptions['toc']) {
		$printingOptions['tocCode'] .= '<p style="padding-left: ' . 
	                                     (15*$level).'px;"><a href="#' . prefixToHTMLID('req'.$node['id']) . '">' .
	       	                             $name . '</a></p>';
		$output .= '<a name="' . prefixToHTMLID('req'.$node['id']) . '"></a>';
	}

	if ($printingOptions['req_author']) {
		$author = tlUser::getById($db,$req['author_id']);
		$output .=  '<tr><td width="' . $firstColWidth . '"><span class="label">' . 
		            $labels['author'] . "</span></td><td> " . 
		            htmlspecialchars($author->getDisplayName()) . "</td></tr>\n";
	}
	          	
	if ($printingOptions['req_status']) {
		$output .= '<tr><td width="' . $firstColWidth . '"><span class="label">' . 
		           $labels['status'] . "</span></td>" .
		           "<td>" . $reqStatusLabels[$req['status']] . "</td></tr>";
	}
	
	if ($printingOptions['req_type']) {
		$output .= '<tr><td width="' . $firstColWidth . '"><span class="label">' . 
		           $labels['type'] . "</span></td>" .
		           "<td>" . $reqTypeLabels[$req['type']] . "</td></tr>";
	} 
	
	if ($printingOptions['req_coverage']) {
		$current = count($req_mgr->get_coverage($req['id']));
		$expected = $req['expected_coverage'];
    	$coverage = lang_get('not_aplicable') . " ($current/0)";
    	if ($expected) {
    		$percentage = round(100 / $expected * $current, 2);
			$coverage = "{$percentage}% ({$current}/{$expected})";
    	}
    	
    	$output .= "<tr><td width=\"$firstColWidth\"><span class=\"label\">" . $labels['coverage'] . "</span></td>" .
		           "<td>$coverage</td></tr>";
	} 
	
	if ($printingOptions['req_scope']) {
		$output .= "<tr><td colspan=\"$tableColspan\"><span class=\"label\">" . $labels['scope'] . 
		           "</span><br/>" . $req['scope'] . "</td></tr>";
	}
		
	if ($printingOptions['req_relations']) {
		$relations = $req_mgr->get_relations($req['id']);

		if ($relations['num_relations']) {
			$output .= "<tr><td width=\"$firstColWidth\"><span class=\"label\">" . $labels['relations'] . "</span></td>" .
			           "<td>";
			
			foreach ($relations['relations'] as $rel) {
				$output .= "{$rel['type_localized']}: <br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . 
				           htmlspecialchars($rel['related_req']['req_doc_id']) . $title_separator .
			               htmlspecialchars($rel['related_req']['title']) . "</br>" .
				           "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{$labels['status']}: " .
				           "{$reqStatusLabels[$rel['related_req']['status']]} <br/>";
				if ($req_cfg->relations->interproject_linking) {
					$output .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{$labels['relation_project']}: " .
					           htmlspecialchars($rel['related_req']['testproject_name']) . " <br/>";
				}
			}
			
			$output .= "</td></tr>";
		}
	} 
	
	if ($printingOptions['req_linked_tcs']) {
		$req_coverage = $req_mgr->get_coverage($req['id']);
		
		if (count($req_coverage)) {
			$output .= "<tr><td width=\"$firstColWidth\"><span class=\"label\">" . $labels['related_tcs'] . "</span></td>" .
			           "<td>";
			           
			foreach ($req_coverage as $tc) {
				$output .= htmlentities($tc['tc_external_id'] . $title_separator .
				                        $tc['name']) . "<br/>";
			}
			           
			$output .= "</td></tr>";
		}
	}
	
	if ($printingOptions['req_cf']) 
	{
		$linked_cf = $req_mgr->get_linked_cfields($req['id']);
		if ($linked_cf)
		{
			foreach ($linked_cf as $key => $cf) 
			{
				$cflabel = htmlspecialchars($cf['label']);
				$value = htmlspecialchars($cf['value']);
				
				$output .= "<tr><td width=\"$firstColWidth\"><span class=\"label\">" . 
				           $cflabel . "</span></td>" .
				           "<td>$value</td></tr>";
			}
		}
	}
	
	$output .= "</table><br/>";

	return $output;
}


/**
 * render a requirement specification node as HTML code for printing
 * 
 * @author Andreas Simon
 * 
 * @param resource $db
 * @param array $node the node to be printed
 * @param array $printingOptions
 * @param string $tocPrefix Prefix to be printed in TOC before title of node
 * @param int $level
 * @param int $tprojectID
 * 
 * @return string $output HTML Code
 */
function renderReqSpecNodeForPrinting(&$db, &$node, &$printingOptions, $tocPrefix, $level, $tprojectID) {
	static $tableColspan;
	static $firstColWidth;
	static $labels;
	static $title_separator;
	static $req_spec_mgr;
	static $tplan_mgr;
	static $req_spec_cfg;
	static $reqSpecTypeLabels;
	
	if (!$req_spec_mgr) {
		$req_spec_cfg = config_get('req_spec_cfg');
		$firstColWidth = '20%';
		$tableColspan = 2;
		$labels = array('requirements_spec' => 'requirements_spec', 
		                'scope' => 'scope', 'type' => 'type', 'author' => 'author',
		                'relations' => 'relations', 'overwritten_count' => 'req_total',
		                'coverage' => 'coverage',
		                'custom_field' => 'custom_field', 'not_aplicable' => 'not_aplicable');
		$labels = init_labels($labels);
		$reqSpecTypeLabels = init_labels($req_spec_cfg->type_labels);
		$title_separator = config_get('gui_title_separator_1');
		$req_spec_mgr = new requirement_spec_mgr($db);
		$tplan_mgr = new testplan($db);
	}
	
	$spec = $req_spec_mgr->get_by_id($node['id']);
	
	$name = htmlspecialchars($spec['doc_id'] . $title_separator . $spec['title']);
	
	$docHeadingNumbering = '';
	if ($printingOptions['headerNumbering']) {
		$docHeadingNumbering = "$tocPrefix. ";
	}
	
	$output = "<table class=\"req_spec\"><tr><th colspan=\"$tableColspan\">" .
	        "<span class=\"label\">{$docHeadingNumbering}{$labels['requirements_spec']}:</span> " .
 			$name . "</th></tr>\n";
 		
	if ($printingOptions['toc'])
	{
		$spacing = ($level == 2) ? "<br>" : "";
	 	$printingOptions['tocCode'] .= $spacing.'<b><p style="padding-left: '.(10*$level).'px;">' .
				'<a href="#' . prefixToHTMLID($tocPrefix) . '">' . $docHeadingNumbering . $name . "</a></p></b>\n";
		$output .= "<a name='". prefixToHTMLID($tocPrefix) . "'></a>\n";
	}
	
	if ($printingOptions['req_spec_author']) {
		// get author name for node
		$author = tlUser::getById($db, $spec['author_id']);
		$output .=  '<tr><td width="' . $firstColWidth . '"><span class="label">' . 
		            $labels['author'] . "</span></td><td> " . 
		            htmlspecialchars($author->getDisplayName()) . "</td></tr>\n";
	}
	
	if ($printingOptions['req_spec_type']) {
		$output .= '<tr><td width="' . $firstColWidth . '"><span class="label">' . 
		           $labels['type'] . "</span></td>" .
		           "<td>" . $reqSpecTypeLabels[$spec['type']] . "</td></tr>";
	}
	
	if ($printingOptions['req_spec_overwritten_count_reqs']) {
		$current = $req_spec_mgr->get_requirements_count($spec['id']);
		$expected = $spec['total_req'];
    	$coverage = $labels['not_aplicable'] . " ($current/0)";
    	if ($expected) {
    		$percentage = round(100 / $expected * $current, 2);
			$coverage = "{$percentage}% ({$current}/{$expected})";
    	}
		
		$output .= '<tr><td width="' . $firstColWidth . '"><span class="label">' . 
		           $labels['overwritten_count'] . " (" . $labels['coverage'] . ")</span></td>" .
		           "<td>" . $coverage . "</td></tr>";
	}

	if ($printingOptions['req_spec_scope']) {
		$output .= "<tr><td colspan=\"$tableColspan\"><span class=\"label\">" . $labels['scope'] . 
		           "</span><br/>" . $spec['scope'] . "</td></tr>";
	}
	
	if ($printingOptions['req_spec_cf']) {
		$linked_cf = $req_spec_mgr->get_linked_cfields($spec['id']);
		if ($linked_cf){
			foreach ($linked_cf as $key => $cf) {
				$cflabel = htmlspecialchars($cf['label']);
				$value = htmlspecialchars($cf['value']);
				
				$output .= "<tr><td width=\"$firstColWidth\"><span class=\"label\">" . 
				           $cflabel . "</span></td>" .
				           "<td>$value</td></tr>";
			}
		}
	}
	
	$output .= "</table><br/>\n";
	
	return $output;
}


/**
 * render a complete tree, consisting of mixed requirement and req spec nodes, 
 * as HTML code for printing
 * 
 * @author Andreas Simon
 * 
 * @param resource $db
 * @param array $node the node to be printed
 * @param array $printingOptions
 * @param string $tocPrefix Prefix to be printed in TOC before title of each node
 * @param int $level
 * @param int $tprojectID
 * @param int $user_id ID of user which shall be printed as author of the document
 * 
 * @return string $output HTML Code
 */
function renderReqSpecTreeForPrinting(&$db, &$node, &$printingOptions,
                                       $tocPrefix, $rsCnt, $level, $user_id,
                                       $tplan_id = 0, $tprojectID = 0) {
	
	static $tree_mgr;
	static $map_id_descr;
	static $tplan_mgr;
 	$code = null;

	if(!$tree_mgr)
	{ 
 	    $tplan_mgr = new testplan($db);
	    $tree_mgr = new tree($db);
 	    $map_id_descr = $tree_mgr->node_types;
 	}
 	$verbose_node_type = $map_id_descr[$node['node_type_id']];
 	
    switch($verbose_node_type)
	{
		case 'testproject':

			break;

		case 'requirement_spec':
            $tocPrefix .= (!is_null($tocPrefix) ? "." : '') . $rsCnt;
            $code .= renderReqSpecNodeForPrinting($db,$node,$printingOptions,
                               $tocPrefix, $level, $tprojectID);
		break;

		case 'requirement':
			$tocPrefix .= (!is_null($tocPrefix) ? "." : '') . $rsCnt;
			$code .= renderRequirementNodeForPrinting($db, $node, $printingOptions,
			                              $tocPrefix, $level, $tprojectID);
	    break;
	}
	
	if (isset($node['childNodes']) && $node['childNodes'])
	{
	  
		$childNodes = $node['childNodes'];
		$rsCnt = 0;
   	    $children_qty = sizeof($childNodes);
		for($i = 0;$i < $children_qty ;$i++)
		{
			$current = $childNodes[$i];
			if(is_null($current))
			{
				continue;
            }
            
			if (isset($current['node_type_id']) && 
			    $map_id_descr[$current['node_type_id']] == 'requirement_spec')
			{
			    $rsCnt++;
			}
			
			$code .= renderReqSpecTreeForPrinting($db, $current, $printingOptions,
			                                       $tocPrefix, $rsCnt, $level+1, $user_id,
			                                       $tplan_id, $tprojectID);
		}
	}
	
	if ($verbose_node_type == 'testproject')
	{
		if ($printingOptions['toc'])
		{
			$code = str_replace("{{INSERT_TOC}}",$printingOptions['tocCode'],$code);
		}
	}

	return $code;
}


/**
 * render HTML header
 * Standard: HTML 4.01 trans (because is more flexible to bugs in user data)
 * 
 * @param string $title
 * @param string $base_href Base URL
 * 
 * @return string html data
 */
function renderHTMLHeader($title,$base_href)
{
	// BUGID 3424
	$themeDir = config_get('theme_dir');
	$docCfg = config_get('document_generator');
    $cssFile = $base_href . $themeDir . $docCfg->css_template;

	$output = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>\n";
	$output .= "<html>\n<head>\n";
	$output .= '<meta http-equiv="Content-Type" content="text/html; charset=' . config_get('charset') . '">';
	$output .= '<title>' . htmlspecialchars($title). "</title>\n";
	// $output .= '<link type="text/css" rel="stylesheet" href="'. $base_href . $docCfg->css_template ."\" />\n";
	$output .= '<link type="text/css" rel="stylesheet" href="'. $cssFile ."\" />\n";
	
	// way to add CSS directly to the exported file (not used - test required)
    // $docCss = file_get_contents(TL_ABS_PATH . $docCfg->css_template);
    // $output .= '<style type="text/css" media="all">'."\n<!--\n".$docCss."\n-->\n</style>\n";
	$output .= '<style type="text/css" media="print">.notprintable { display:none;}</style>';
	$output .= "\n</head>\n";

	return $output;
}


/**
 * Generate initial page of document
 * 
 * @param object $doc_info data with the next string values: 
 *                  title
 *                  type_name: what does this means ???
 *                  author, tproject_name, testplan_name  
 * @return string html
 * @author havlatm
 */
function renderFirstPage($doc_info)
{
    $docCfg = config_get('document_generator');
    $date_format_cfg = config_get('date_format');
  	$output = "<body>\n<div>\n";

	// Print header
	if ($docCfg->company_name != '' )
	{
		$output .= '<div style="float:right;">' . htmlspecialchars($docCfg->company_name) ."</div>\n";
	}
	$output .= '<div>'. $doc_info->tproject_name . "</div><hr />\n";
    
	if ($docCfg->company_logo != '' )
	{
		$output .= '<p style="text-align: center;"><img alt="TestLink logo" ' .
		           'title="configure using $tlCfg->company->logo_image"'.
        	     ' src="' . $_SESSION['basehref'] . TL_THEME_IMG_DIR . $docCfg->company_logo . '" /></p>';
	}
	$output .= "</div>\n";
	$output .= '<div class="doc_title"><p>' . $doc_info->title . '</p>';
	$output .= '<p>'.$doc_info->type_name.'</p>';
	$output .= "</div>\n";
    
	// Print summary on the first page
	$output .= '<div class="summary">' .
		         '<p id="prodname">'. lang_get('project') .": " . $doc_info->tproject_name . "</p>\n";
    
	$output .= '<p id="author">' . lang_get('author').": " . $doc_info->author . "</p>\n" .
		         '<p id="printedby">' . lang_get('printed_by_TestLink_on')." ".
		         strftime($date_format_cfg, time()) . "</p></div>\n";
    
	// Print legal notes
	if ($docCfg->company_copyright != '')
	{
		$output .= '<div class="pagefooter" id="copyright">' . $docCfg->company_copyright."</div>\n";
	}
		           
	if ($docCfg->confidential_msg != '')
	{
		$output .= '<div class="pagefooter" id="confidential">' .	$docCfg->confidential_msg . "</div>\n";
	}
	
	return $output;
}


/**
 * Generate a chapter to a document
 * 
 * @param string $title
 * @param string $content
 * 
 * @return string html
 * @author havlatm
 */
function renderSimpleChapter($title, $content)
{
	$output = '';
	if ($content != "")
	{
		$output .= '<h1 class="doclevel">'.$title."</h1>\n";
		$output .= '<div class="txtlevel">' .$content . "</div>\n <br/>";
	}
	return $output;
}


/*
  function: renderTestSpecTreeForPrinting
  args :
  returns:

  rev :
       20100723 - asimon - BUGID 3459 - $added platform_id
       20070509 - franciscom - added $tplan_id in order to refactor and
                               add contribution BUGID
*/
function renderTestSpecTreeForPrinting(&$db,&$node,$item_type,&$printingOptions,
                                       $tocPrefix,$tcCnt,$level,$user_id,
                                       $tplan_id = 0,$tcPrefix = null,
                                       $tprojectID = 0, $platform_id = 0)
{
	static $tree_mgr;
	static $map_id_descr;
	static $tplan_mgr;
 	$code = null;
 	
	if(!$tree_mgr)
	{ 
 	    $tplan_mgr = new testplan($db);
	    $tree_mgr = new tree($db);
 	    $map_id_descr = $tree_mgr->node_types;
 	}
 	$verbose_node_type = $map_id_descr[intval($node['node_type_id'])];
    switch($verbose_node_type)
	{
		case 'testproject':
		    if($tplan_id != 0)
		    {
		        // 20090330 - franciscom
		        // we are printing a test plan, get it's custom fields
                $cfieldFormatting=array('table_css_style' => 'class="cf"');
                if ($printingOptions['cfields'])
        		{
	            	$cfields = $tplan_mgr->html_table_of_custom_field_values($tplan_id,'design',null,$cfieldFormatting);
	            	$code .= '<p>' . $cfields . '</p>';
	       		}
		    }
			// platform changes - $code .= renderTOC($printingOptions);
		break;

		case 'testsuite':
            $tocPrefix .= (!is_null($tocPrefix) ? "." : '') . $tcCnt;
            $code .= renderTestSuiteNodeForPrinting($db,$node,$printingOptions,
                                                    $tocPrefix,$level,$tplan_id,$tprojectID);
		break;

		case 'testcase':
			// 3459 - added $platform_id
			$code .= renderTestCaseForPrinting($db, $node, $printingOptions, $level,
			                                   $tplan_id, $tcPrefix, $tprojectID, $platform_id);
	    break;
	}
	
	if (isset($node['childNodes']) && $node['childNodes'])
	{
	  
		$childNodes = $node['childNodes'];
		$tsCnt = 0;
   	    $children_qty = sizeof($childNodes);
		for($i = 0;$i < $children_qty ;$i++)
		{
			$current = $childNodes[$i];
			if(is_null($current))
			{
				continue;
            }
            
			if (isset($current['node_type_id']) && 
			    $map_id_descr[$current['node_type_id']] == 'testsuite')
			{
			    $tsCnt++;
			}
			// 3459 - added $platform_id
			$code .= renderTestSpecTreeForPrinting($db, $current, $item_type, $printingOptions,
			                                       $tocPrefix, $tsCnt, $level+1, $user_id,
			                                       $tplan_id, $tcPrefix, $tprojectID, $platform_id);
		}
	}
	
	if ($verbose_node_type == 'testproject')
	{
		if ($printingOptions['toc'])
		{
			// remove for platforms feature  
			// $printingOptions['tocCode'] .= '</div><hr />';
			$code = str_replace("{{INSERT_TOC}}",$printingOptions['tocCode'],$code);
		}
	}

	return $code;
}


/**
 * get user name from pool (save used names in session to improve performance)
 * 
 * @param integer $db DB connection identifier 
 * @param integer $userId
 * 
 * @return string readable user name
 * @author havlatm
 */
function gendocGetUserName(&$db, $userId)
{
	$authorName = null;
	      
	if(isset($_SESSION['userNamePool'][$userId]))
	{
		$authorName	= $_SESSION['userNamePool'][$userId];
	}
	else
	{
		$user = tlUser::getByID($db,$userId);
		if ($user)
		{
			$authorName = $user->getDisplayName();
			$authorName = htmlspecialchars($authorName);
			$_SESSION['userNamePool'][$userId] = $authorName;
		}
		else
		{
			$authorName = lang_get('undefined');
			tLog('tlUser::getByID($db,$userId) failed', 'ERROR');
		}
	}
	
	return $authorName;	
}


/**
 * render Test Case content for generated documents
 * 
 * @param $integer db DB connection identifier 
 * @return string generated html code
 * @internal 
 *      20100724 - asimon - BUGID 3459 - added platform ID
 *      20100723 - asimon - BUGID 3451 and related finally solved
 *      20090517 - havlatm - fixed execution layot; added tester name
 *      20080819 - franciscom - removed mysql only code
 *      20071014 - franciscom - display test case version
 *      20070509 - franciscom - added Contribution
 */
function renderTestCaseForPrinting(&$db, &$node, &$printingOptions, $level, $tplan_id = 0,
                                   $prefix = null, $tprojectID = 0, $platform_id = 0)
{
    static $req_mgr;
	static $tc_mgr;
	static $labels;
	static $tcase_prefix;
    static $userMap = array();
    static $cfg;
    static $locationFilters;
    static $tables = null;
    
    if (!$tables)
    {
    	$tables = tlDBObject::getDBTables(array('executions','builds'));
    }
    
	$code = null;
	$tcInfo = null;
    $tcResultInfo = null;
    $tcase_pieces = null;
	$cfieldFormatting = array('td_css_style' => '','add_table' => false);
    
    // init static elements
    $id = $node['id'];
	if(!$cfg)
	{
 	    $tc_mgr = new testcase($db);
 	    list($cfg,$labels) = initRenderTestCaseCfg($tc_mgr);
	    if(!is_null($prefix))
	    {
	        $tcase_prefix = $prefix;
	    }
	    else
	    {
	    	list($tcase_prefix,$dummy) = $tc_mgr->getPrefix($id);
	    }
	    $tcase_prefix .= $cfg['testcase']->glue_character;
	}
	$versionID = isset($node['tcversion_id']) ? $node['tcversion_id'] : testcase::LATEST_VERSION;
    $tcInfo = $tc_mgr->get_by_id($id,$versionID);
    if ($tcInfo)
    {
    	$tcInfo = $tcInfo[0];
    }
    $external_id = $tcase_prefix . $tcInfo['tc_external_id'];
	$name = htmlspecialchars($node['name']);

	// ----- BUGID 3451 and related ---------------------------------------
	// asimon: I finally found the real problem here:
	// $versionID was used in the following "dirty" SQL statement, but was still set to "-1" 
	//(the value to load all tc versions) instead of a real testcase version ID.
	$versionID = $tcInfo['id'];
	// This still does not change the fact that this marked SQL statement below
	// should be removed and replaced by existing functions.
	// ----- BUGID 3451 and related ---------------------------------------
	
  	$cfields = array('specScope' => null, 'execScope' => null);

  	// get custom fields that has specification scope
  	if ($printingOptions['cfields'])
	{
		if (!$locationFilters)
        	$locationFilters = $tc_mgr->buildCFLocationMap();
		// 20090719 - franciscom - cf location
     	foreach($locationFilters as $fkey => $fvalue)
		{ 
			$cfields['specScope'][$fkey] = 
					$tc_mgr->html_table_of_custom_field_values($id,'design',$fvalue,null,$tplan_id,
			                                               $tprojectID,$cfieldFormatting);
		}	                                               
	}

/** 
 * @TODO THIS IS NOT THE WAY TO DO THIS IS ABSOLUTELY WRONG AND MUST BE REFACTORED, 
 * using existent methods - franciscom - 20090329 
 * Need to get CF with execution scope
 */
	$exec_info = null;
	$bGetExecutions = false;
	if ($printingOptions["docType"] != DOC_TEST_SPEC)
		$bGetExecutions = ($printingOptions['cfields'] || $printingOptions['passfail']);
	if ($bGetExecutions)
	{
		$sql =  " SELECT E.id AS execution_id, E.status, E.execution_ts, E.tester_id," .
		        " E.notes, E.build_id, E.tcversion_id,E.tcversion_number,E.testplan_id,E.has_attach," .
		        " B.name AS build_name " .
		        " FROM {$tables['executions']} E, {$tables['builds']} B" .
		        " WHERE E.build_id= B.id " . 
		        " AND E.tcversion_id = {$versionID} " .
		        " AND E.testplan_id = {$tplan_id} " .
		        " AND E.platform_id = {$platform_id} " .
		  		" ORDER BY execution_id DESC";
		$exec_info = $db->get_recordset($sql,null,1);
	}
	// Added condition for the display on/off of the custom fields on test cases.
    if ($printingOptions['cfields'] && !is_null($exec_info))
    {
    	$execution_id = $exec_info[0]['execution_id'];
        $cfields['execScope'] = $tc_mgr->html_table_of_custom_field_values($versionID,'execution',null,
                                                                           $execution_id, $tplan_id,
                                                                           $tprojectID,$cfieldFormatting);
    }
	  
	if ($printingOptions['toc'])
	{
		$printingOptions['tocCode'] .= '<p style="padding-left: ' . 
	                                     (15*$level).'px;"><a href="#' . prefixToHTMLID('tc'.$id) . '">' .
	       	                             $name . '</a></p>';
		$code .= '<a name="' . prefixToHTMLID('tc'.$id) . '"></a>';
	}
      
 	$code .= '<p>&nbsp;</p><div> <table class="tc" width="90%">';
 	$code .= '<tr><th colspan="' . $cfg['tableColspan'] . '">' . $labels['test_case'] . " " . 
 			htmlspecialchars($external_id) . ": " . $name;

    
	// add test case version
	if($cfg['doc']->tc_version_enabled && isset($node['version'])) 
	{
		$code .= '&nbsp;<span style="font-size: 80%;"' . $cfg['gui']->role_separator_open . 
        	   	$labels['version'] . $cfg['gui']->title_separator_1 .  $node['version'] . 
           		$cfg['gui']->role_separator_close . '</span>';
  	}
   	$code .= "</th></tr>\n";

  	if ($printingOptions['author'])
  	{
		$authorName = gendocGetUserName($db, $tcInfo['author_id']);
		$code .= '<tr><td width="' . $cfg['firstColWidth'] . '" valign="top">' . 
		         '<span class="label">'.$labels['author'].':</span></td>';
        $code .= '<td colspan="' .  ($cfg['tableColspan']-1) . '">' . $authorName;


		if (($tcInfo['updater_id'] > 0) && $tcInfo['updater_id'] != $tcInfo['author_id']) 
		{
		    // add updater if available and differs from author
			$updaterName = gendocGetUserName($db, $tcInfo['updater_id']);
			$code .= '<br />' . $labels['last_edit'] . " " . $updaterName;
		}
		$code .= "</td></tr>\n";
  	}

    if ($printingOptions['body'] || $printingOptions['summary'])
    {
        $tcase_pieces = array('summary');
    }
    
    if ($printingOptions['body'])
    {
        $tcase_pieces[] = 'preconditions';
        $tcase_pieces[] = 'steps';
        // $tcase_pieces[] = 'expected_results';        
    }
    
    if(!is_null($tcase_pieces))
    {
    	// Multiple Test Case Steps Feature
        foreach($tcase_pieces as $key)
        {
            // 20090719 - franciscom - cf location
            if( $key == 'steps' )
            {
            	if( isset($cfields['specScope']['before_steps_results']) )
            	{
        		 	$code .= $cfields['specScope']['before_steps_results'];    
                }
                if ($tcInfo[$key] != '')
                {
            		$code .= '<tr>' .
            		         '<td><span class="label">' . $labels['step_number'] .':</span></td>' .
            		         '<td><span class="label">' . $labels['step_actions'] .':</span></td>' .
            		         '<td><span class="label">' . $labels['expected_results'] .':</span></td></tr>';
                	
                	$loop2do = count($tcInfo[$key]);
                	for($ydx=0 ; $ydx < $loop2do; $ydx++)
                	{
            			$code .= '<tr>' .
            			         '<td width="5">' .  $tcInfo[$key][$ydx]['step_number'] . '</td>' .
            			         '<td>' .  $tcInfo[$key][$ydx]['actions'] . '</td>' .
            			         '<td>' .  $tcInfo[$key][$ydx]['expected_results'] . '</td>' .
            				     '</tr>';
                	}

                }
                
            }
        	else
        	{
        		// disable the field if it's empty
        		if ($tcInfo[$key] != '')
        		{
            		$code .= '<tr><td colspan="' .  $cfg['tableColspan'] . '"><span class="label">' . $labels[$key] .
            	         ':</span><br />' .  $tcInfo[$key] . "</td></tr>";
            	}
            }         
        }
    }
    // Spacer
    $code .= '<tr><td colspan="' .  $cfg['tableColspan'] . '">' . "</td></tr>";
    
    // 20090719 - franciscom - cf location
    $code .= $cfields['specScope']['standard_location'] . $cfields['execScope'];
	
	// generate test results data for test report 
	if ($printingOptions['passfail'])
	{
		if ($exec_info) 
		{
			$code .= buildTestExecResults($db,$cfg,$labels,$exec_info);
		}
		else
		{
		  	$code .= '<tr><td width="' . $cfg['firstColWidth'] . '" valign="top">' . 
		  			'<span class="label">' . $labels['last_exec_result'] . '</span></td>' . 
		  			'<td colspan="' . ($cfg['tableColspan']-1) . '"><b>' . $labels["test_status_not_run"] . 
		  			"</b></td></tr>\n";

		}
	}

	// collect REQ for TC
	// based on contribution by JMU (#1045)
	if ($printingOptions['requirement'])
	{
	    if(!$req_mgr)
	    {
	        $req_mgr = new requirement_mgr($db);
	  	}
	  	$requirements = $req_mgr->get_all_for_tcase($id);
	  	$code .= '<tr><td width="' . $cfg['firstColWidth'] . '" valign="top"><span class="label">'. 
	  	         $labels['reqs'].'</span>'; 
	  	$code .= '<td colspan="' . ($cfg['tableColspan']-1) . '">';

	  	if (sizeof($requirements))
	  	{
	  		foreach ($requirements as $req)
	  		{
	  			$code .=  htmlspecialchars($req['req_doc_id'] . ":  " . $req['title']) . "<br />";
	  		}
	  	}
	  	else
	  	{
	  		$code .= '&nbsp;' . $labels['none'] . '<br />';
	  	}
	  	$code .= "</td></tr>\n";
	}
	  
	// collect keywords for TC
	// based on contribution by JMU (#1045)
	if ($printingOptions['keyword'])
	{
	  	$code .= '<tr><td width="' . $cfg['firstColWidth'] . '" valign="top"><span class="label">'. 
	  	         $labels['keywords'].':</span>';
      	$code .= '<td colspan="' . ($cfg['tableColspan']-1) . '">';
	  	$arrKeywords = $tc_mgr->getKeywords($id);
	  	if (sizeof($arrKeywords))
	  	{
	  		foreach ($arrKeywords as $kw)
	  		{
	  			$code .= htmlspecialchars($kw['keyword']) . "<br />";
	  		}
	  	}
	  	else
	  	{
	  		$code .= '&nbsp;' . $labels['none'] . '<br>';
	  	}
	  	$code .= "</td></tr>\n";
	  }

	  $code .= "</table>\n</div>\n";
	  return $code;
}


/**
 * Remaining part of renderProjectNodeForPrinting
 * 
 * @todo havlatm: refactor
 */
function renderTOC(&$printingOptions)
{
	$code = '';
	$printingOptions['toc_numbers'][1] = 0;
	if ($printingOptions['toc'])
	{
		$printingOptions['tocCode'] = '<h1 class="doclevel">' . lang_get('title_toc').'</h1><div class="toc">';
		$code .= "{{INSERT_TOC}}";
	}

	return $code;
}


/*
  function: renderTestSuiteNodeForPrinting
  args :
  returns:
  
  rev: 20090329 - franciscom - added ALWAYS Custom Fields
       20081207 - franciscom - refactoring using static to decrease exec time.
*/
function renderTestSuiteNodeForPrinting(&$db,&$node,&$printingOptions,$tocPrefix,$level,$tplan_id,$tproject_id)
{
    static $tsuite_mgr;
    $labels = array('test_suite' => lang_get('test_suite'),'details' => lang_get('details'));
  
	$code = null;
	$name = isset($node['name']) ? htmlspecialchars($node['name']) : '';
	$title_separator = config_get('gui_title_separator_1');
  	$cfields = array('design' => '');
    $cfieldFormatting=array('table_css_style' => 'class="cf"');
    
	$docHeadingNumbering = '';
	if ($printingOptions['headerNumbering']) {
		$docHeadingNumbering = "$tocPrefix. ";
	}
    
	if ($printingOptions['toc'])
	{
		$spacing = ($level == 2 && $tocPrefix != 1) ? "<br>" : "";
	 	$printingOptions['tocCode'] .= $spacing.'<b><p style="padding-left: '.(10*$level).'px;">' .
				'<a href="#' . prefixToHTMLID($tocPrefix) . '">' . $docHeadingNumbering . $name . "</a></p></b>\n";
		$code .= "<a name='". prefixToHTMLID($tocPrefix) . "'></a>\n";
	}
	$docHeadingLevel = $level - 1; //we would like to have html top heading H1 - H6
	$docHeadingLevel = ($docHeadingLevel > 6) ? 6 : $docHeadingLevel;
 	$code .= "<h{$docHeadingLevel} class='doclevel'>" . $docHeadingNumbering . $labels['test_suite'] .
 			$title_separator . $name . "</h{$docHeadingLevel}>\n";

	// ----- get Test Suite text -----------------
	if ($printingOptions['header'])
    {
        if( !$tsuite_mgr)
        { 
		    $tsuite_mgr = new testsuite($db);
		}
		$tInfo = $tsuite_mgr->get_by_id($node['id']);
		if ($tInfo['details'] != '')
		{
	   	    $code .= '<div>'.$tInfo['details']. '</div>';
		}
   	
   	    // get Custom fields    
   	    // Attention: for test suites custom fields can not be edited during execution,
   	    //            then we need to get just custom fields with scope  'design'
        foreach($cfields as $key => $value)
        {
            $cfields[$key] = $tsuite_mgr->html_table_of_custom_field_values($node['id'],$key,null,
	                                                                       $tproject_id,$cfieldFormatting);
   	        if($cfields[$key] != "")
   	        {
   	            $add_br = true;
   	            $code .= '<p>' . $cfields[$key] . '</p>';    
   	        }
   	    }
 	}

	return $code;
}



/*
  function: renderTestPlanForPrinting
  args:
  returns:
  
  @internal revisions:
     20100723 - asimon - BUGID 3459: added $platform_id
*/
function renderTestPlanForPrinting(&$db, &$node, $item_type, &$printingOptions, $tocPrefix,
                                   $tcCnt, $level, $user_id, $tplan_id, $tprojectID, $platform_id)

{
	$tProjectMgr = new testproject($db);
	$tcPrefix = $tProjectMgr->getTestCasePrefix($tprojectID);
	$code =  renderTestSpecTreeForPrinting($db, $node, $item_type, $printingOptions,
                                           $tocPrefix, $tcCnt, $level, $user_id,
                                           $tplan_id, $tcPrefix, $tprojectID, $platform_id);
	return $code;
}


/** 
 * Render HTML for estimated and real execute duration 
 * based on contribution (BUGID 1670)
 * 
 * @param array_of_strings $statistics
 * @return string HTML code
 */
function renderTestDuration($statistics)
{
    $output = '';
	$estimated_string = '';
	$real_string = '';
	$bEstimatedTimeAvailable = isset($statistics['estimated_execution']);
	$bRealTimeAvailable = isset($statistics['real_execution']);
    
	if( $bEstimatedTimeAvailable || $bRealTimeAvailable)
	{ 
	    $output = "<div>\n";
	    
		if($bEstimatedTimeAvailable) 
		{
			$estimated_minutes = $statistics['estimated_execution']['minutes'];
	    	$tcase_qty = $statistics['estimated_execution']['tcase_qty'];
		         
    	   	if($estimated_minutes > 60)
    	   	{
				$estimated_string = lang_get('estimated_time_hours') . round($estimated_minutes/60,2) ;
			}
			else
			{
				$estimated_string = lang_get('estimated_time_min') . $estimated_minutes;
            }
			$estimated_string = sprintf($estimated_string,$tcase_qty);

			$output .= '<p>' . $estimated_string . "</p>\n";
		}
		  
		if($bRealTimeAvailable) 
		{
			$real_minutes = $statistics['real_execution']['minutes'];
			$tcase_qty = $statistics['real_execution']['tcase_qty'];
			if($real_minutes > 0)
		    {
	        	if($real_minutes > 60)
	        	{
		        	$real_string = lang_get('real_time_hours') . round($real_minutes/60,2) ;
			    }
			    else
			    {
			      	$real_string = lang_get('real_time_min') . $real_minutes;
                } 
				$real_string = sprintf($real_string,$tcase_qty);    
			}
			$output .= '<p>' . $real_string . "</p>\n";
		}
    $output .= "</div>\n";
	}

	return $output;	
}


/** 
 * get final markup for HTML
 * 
 * @return string HTML 
 **/
function renderEOF()
{
	return "\n</body>\n</html>";
}


/**
 * compose html text for metrics (meantime estimated time only)
 * 
 * @return string html
 */
function buildTestPlanMetrics($statistics)
{
    $output = '<h1 class="doclevel">'.lang_get('title_nav_results')."</h1>\n";
    $output .= renderTestDuration($statistics);
    
	return $output;	
}


/**
 * utility function to allow easy reading of code
 * on renderTestCaseForPrinting()
 * 
 * @return map with configuration and labels
 */

function initRenderTestCaseCfg(&$tcaseMgr)
{
	$config = null;
	$config['firstColWidth'] = '20%';
	$config['tableColspan'] = 3;
	$config['doc'] = config_get('document_generator');
	$config['gui'] = config_get('gui');
	$config['testcase'] = config_get('testcase_cfg');
	$config['results'] = config_get('results');
    
    foreach($config['results']['code_status'] as $key => $value)
    {
        $config['status_labels'][$key] = 
        	"check your \$tlCfg->results['status_label'] configuration ";
        if( isset($config['results']['status_label'][$value]) )
        {
            $config['status_labels'][$key] = lang_get($config['results']['status_label'][$value]);
        }    
    }

    // 20100306 - contribution by romans
	// BUGID 0003235: Printing Out Test Report Shows empty Column Headers for "Steps" and "Step Actions"
    $labelsKeys=array('last_exec_result', 'testnotes', 'none', 'reqs','author', 'summary',
                      'steps', 'expected_results','build', 'test_case', 'keywords','version', 
                      'test_status_not_run', 'not_aplicable', 'bugs','tester','preconditions',
                      'step_number', 'step_actions', 'last_edit');
    $labelsQty=count($labelsKeys);         
    for($idx=0; $idx < $labelsQty; $idx++)
    {
        $labels[$labelsKeys[$idx]] = lang_get($labelsKeys[$idx]);
    }
    return array($config,$labels);
}


/**
 * 
 *
 */
function buildTestExecResults(&$dbHandler,$cfg,$labels,$exec_info)
{
	$out='';
	$testStatus = $cfg['status_labels'][$exec_info[0]['status']];
	$testerName = gendocGetUserName($dbHandler, $exec_info[0]['tester_id']);
	$executionNotes = $exec_info[0]['notes'];
	if( $exec_info[0]['has_attach'] == 1){
	$has_attach = "Yes";
	}
	else{
	$has_attach = "No";
	}
	$out .= '<tr><td width="20%" valign="top">' .
			'<span class="label">' . $labels['last_exec_result'] . ':</span></td>' .
			'<td><b>' . $testStatus . "</b> Attachments:" . $has_attach  .  "</td></tr>\n" .
    		'<tr><td width="' . $cfg['firstColWidth'] . '" valign="top">' . $labels['build'] .'</td>' . 
    		'<td>' . htmlspecialchars($exec_info[0]['build_name']) . "</b></td></tr>\n" .
    		'<tr><td width="' . $cfg['firstColWidth'] . '" valign="top">' . $labels['tester'] .'</td>' . 
    		'<td>' . $testerName . "</b></td></tr>\n";

    if ($executionNotes != '') // show exection notes is not empty
    {
		$out .= '<tr><td width="' . $cfg['firstColWidth'] . '" valign="top">'.$labels['testnotes'] . '</td>' .
			    '<td>' . nl2br($executionNotes)  . "</td></tr>\n"; 
    }

	$bug_interface = config_get('bugInterface');
	if ($bug_interface != 'NO') 
	{
    	// amitkhullar-BUGID 2207 - Code to Display linked bugs to a TC in Test Report
		$bugs = get_bugs_for_exec($dbHandler,$bug_interface,$exec_info[0]['execution_id']);
		if ($bugs) 
		{
			$bugString = '';
			foreach($bugs as $bugID => $bugInfo) 
			{
				$bugString .= $bugInfo['link_to_bts']."<br />";
			}
			$out .= '<tr><td colspan="' .  $cfg['tableColspan'] . 
			        '" width="' . $cfg['firstColWidth'] . '" valign="top">' . 
			        $labels['bugs'] . '</td><td>' . $bugString ."</td></tr>\n"; 
					
		}
	}
	
	return $out;
}


/**
 * Render HTML header for a given platform. 
 * Also adds code to $printingOptions['tocCode']
 */
function renderPlatformHeading($tocPrefix, $platform_id, $platform_name, &$printingOptions)
{
	$platformLabel = lang_get('platform');
	$platform_name = htmlspecialchars($platform_name);
	$printingOptions['tocCode'] .= '<p><a href="#' . prefixToHTMLID($tocPrefix) . '">' .
	                               $platformLabel . ':' . $platform_name . '</a></p>';
	return '<h1 class="doclevel" id="' . prefixToHTMLID($tocPrefix) . "\">$tocPrefix $platformLabel: $platform_name</h1>";
}


/**
 * simple utility function, to avoid lot of copy and paste
 * given an string, return an string useful to jump to an anchor on document
 */
function prefixToHTMLID($string2convert,$anchor_prefix='toc_')
{
	return $anchor_prefix . str_replace('.', '_', $string2convert);
}

?>
