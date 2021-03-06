{* 
TestLink Open Source Project - http://testlink.sourceforge.net/ 
$Id: testPlanWithCF.tpl,v 1.3 2010/05/02 09:38:10 franciscom Exp $

Purpose: For a test plan, list test cases with Custom Fields at Execution

rev:  
*}

{lang_get var="labels" 
          s='no_uncovered_testcases,testproject_has_no_reqspec,
             testproject_has_no_requirements,no_linked_tc_cf,generated_by_TestLink_on,
             test_case,build,th_owner,date,status'}
{include file="inc_head.tpl" openHead="yes"}
</head>
<body>
<h1 class="title">{$gui->pageTitle|escape}</h1>
<div class="workBack" style="overflow-y: auto;">

 {include file="inc_result_tproject_tplan.tpl" 
          arg_tproject_name=$gui->tproject_name arg_tplan_name=$gui->tplan_name}	



{if $gui->warning_msg == ''}
    {if $gui->resultSet}
        <table class="simple">
	          <tr>
	          <th> {$labels.test_case}</th>

	          {foreach from=$gui->cfields item=cfield}
	              <th>{$cfield.label|escape}</th>
	          {/foreach}
	          </tr>
            
	          {foreach from=$gui->resultSet item=arrData}
	            <tr bgcolor="{cycle values="#eeeeee,#d0d0d0"}">  
	            <td>	<a href="lib/testcases/archiveData.php?edit=testcase&id={$arrData.tcase_id}">
	          	{$gui->tcasePrefix}{$arrData.tc_external_id|escape}:{$arrData.tcase_name|escape}</a>
	            </td>
	          	{foreach from=$arrData.cfields item=cfield_value}
	          		<td> {$cfield_value}</td>
	          	{/foreach}	
	            </tr>
	          {/foreach}
                 
        </table>

      {$labels.generated_by_TestLink_on} {$smarty.now|date_format:$gsmarty_timestamp_format}
    {else}
    	<h2>{$labels.no_linked_tc_cf}</h2>
    {/if}
{else}
    {$gui->warning_msg}
{/if}    
</div>
</body>
</html>
