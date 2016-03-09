<?php
/*
 * Copyright (C) 2014	   Patrick DELCROIX     <pmpdelcroix@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
//global $db;     
global $langs;
// to get the whitlist object
require_once 'class/timesheetwhitelist.class.php';
require_once 'class/userTimesheet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';


 /*
 * function to genegate list of the subordinate ID
 * 
  *  @param    object           	$db             database objet
 *  @param    array(int)/int        $id    		    array of manager id 
 *  @param     int              	$depth          depth of the recursivity
 *  @param    array(int)/int 		$ecludeduserid  exection that shouldn't be part of the result ( to avoid recursive loop)
 *  @param     int              	$entity         entity where to look for
  *  @return     string                                                   html code
 */
function get_subordinate($db,$userid, $depth=5,$ecludeduserid=array(),$entity='1'){
    if($userid=="")
    {
        return array();
    }

    $sql="SELECT usr.rowid FROM ".MAIN_DB_PREFIX.'user AS usr WHERE';
    if(is_array($userid)){
        $ecludeduserid=array_merge($userid,$ecludeduserid);
        $sql.=' usr.fk_user in (';
        foreach($userid as $id)
        {
            $sql.='"'.$id.'",';
        }
        $sql.='-999)';
    }else{
        $ecludeduserid[]=$userid;
        $sql.=' usr.fk_user ="'.$userid.'"';
    }
    if(is_array($ecludeduserid)){
        $sql.=' AND usr.rowid not in (';
        foreach($ecludeduserid as $id)
        {
            $sql.='"'.$id.'",';
        }
        $sql.='0)';
    }else if (!empty($ecludeduserid)){
        $sql.=' AND usr.rowid <>"'.$ecludeduserid.'"';
    } 

    dol_syslog("form::get_subordinate sql=".$sql, LOG_DEBUG);
    $list=array();
    $resql=$db->query($sql);
    
    if ($resql)
    {
        $i=0;
        $num = $db->num_rows($resql);
        while ( $i<$num)
        {
            $obj = $db->fetch_object($resql);
            
            if ($obj)
            {
                $list[]=$obj->rowid;        
            }
            $i++;
        }
        if(count($list)>0 && $depth>1){
            //this will get the same result plus the subordinate of the subordinate
            $result=get_subordinate($db,$list,$depth-1,$ecludeduserid, $entity);
            if(is_array($result))
            {
                $list=array_merge($list,$result);
            }
        }
        if(is_array($userid))
        {
            
            $list=array_merge($list,$userid);
        }else
        {
            $list[]=$userid;
        }
        
    }
    else
    {
        $error++;
        dol_print_error($db);
        $list= array();
    }
      //$select.="\n";
      return $list;
 }

 /*
 * function to get the name from a list of ID
 * 
  *  @param    object           	$db             database objet
 *  @param    array(int)/int        $userids    	array of manager id 
  *  @return  array (int => String)  				array( ID => userName)
 */
function get_userName($userids){
    global $db;
	if($userids=="")
    {
        return array();
    }

    $sql="SELECT usr.rowid, CONCAT(usr.firstname,' ',usr.lastname) as userName FROM ".MAIN_DB_PREFIX.'user AS usr WHERE';

	$sql.=' usr.rowid in (';
	$nbIds=(is_array($userids))?count($userids)-1:0;
	for($i=0; $i<$nbIds ; $i++)
	{
		$sql.='"'.$userids[$i].'",';
	}
	$sql.=((is_array($userids))?('"'.$userids[$i].'"'):('"'.$userids.'"')).')';
        $sql.='ORDER BY usr.lastname ASC';

    dol_syslog("form::get_userName sql=".$sql, LOG_DEBUG);
    $list=array();
    $resql=$db->query($sql);
    
    if ($resql)
    {
        $i=0;
        $num = $db->num_rows($resql);
        while ( $i<$num)
        {
            $obj = $db->fetch_object($resql);
            
            if ($obj)
            {
                $list[$obj->rowid]=$obj->userName;        
            }
            $i++;
        }

    }
    else
    {
        $error++;
        dol_print_error($db);
        $list= array();
    }
      //$select.="\n";
      return $list;
 }

 /*
 * function to genegate the timesheet table header
 * 
  *  @param    array(string)           $headers            array of the header to show
 *  @param    array(int)              	$headersWidth    array defining the header width
 *  @param     int              	$yearWeek           timesheetweek
 *  @param     int              	$timestamp         timestamp
  *  @return     string                                                   html code
 */
 function timesheetHeader($headers,$headersWidth , $yearWeek,$tmstp){
     global $langs;
    

     $html='<tr class="liste_titre" id="">'."\n";
     
     foreach ($headers as $key => $value){
         $html.="\t<th ";
         if ($headersWidth[$key]){
                $html.='width="'.$headersWidth[$key].'"';
         }
         $html.=">".$langs->trans($value)."</th>\n";
     }
    
    for ($i=0;$i<7;$i++)
    {
        $curDay=strtotime( $yearWeek.' +'.$i.' day');
        $weekDays[$i]=date('d-m-Y',$curDay);
        $html.="\t".'<th width="60px">'.$langs->trans(date('l',$curDay)).'<br>'.date('d/m/y',$curDay)."</th>\n";
    }
        $_SESSION["timestamps"][$tmstp]["YearWeek"]=$yearWeek;
        $_SESSION["timestamps"][$tmstp]["weekDays"]=$weekDays;
     
     $html.="</tr>\n";
     $html.='<tr id="hiddenParam" style="display:none;"><td colspan="'.($headers.lenght+7).'"> ';
    $html .= '<input type="hidden" name="timestamp" value="'.$tmstp."\"/>\n";
    $html .= '<input type="hidden" name="yearWeek" value="'.$yearWeek.'" />';        
    $html .='</td></tr>';
     return $html;
     
 }
 
  /*
 * function to genegate the timesheet list
 * 
 *  @param    object             	$db                 db Object to do the querry
 *  @param    array(string)           $headers            array of the header to show
 *  @param    int              	$user                   user id to fetch the timesheets
 *  @param     int              	$yearWeek           timesheetweek
 *  @param    array(int)              	$whiteList    array defining the header width
 *  @param     int              	$timestamp         timestamp
 *  @param     int              	$whitelistemode           0-whiteliste,1-blackliste,2-non impact
 *  @return     string                                                   html code
 */
 function timesheetList($db,$headers,$userid,$yearWeek,$timestamp,$whitelistmode=0){

        $i=0;
        $staticTimesheet=New timesheet($db,0);
        $tab=$staticTimesheet->timesheetTab($headers,$userid,$yearWeek,$timestamp);
        foreach ($tab as $timesheet) {
            $row=unserialize($timesheet);
            $Lines.=$row->getFormLine( $yearWeek,$i,$headers,$whitelistmode);
            $_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=array();
            $_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=$row->getTaskTab(); 
            $i++;
        }
        
        return $Lines;
 }    
  /*
 * function to post the all actual submitted
 * 
 *  @param    object             	$db                      db Object to do the querry
 *  @param    int              	$user                   user id to fetch the timesheets
 *  @param    array(int)              	$tabPost               array sent by POST with all info about the task
 *  @param     int              	$timestamp          timestamp
 *  @return     int                                                        number of tasktime creatd/changed
 */
 function postActuals($db,$user,$tabPost,$timestamp)
{
    
    $storedTab=array();
    $storedTab=$_SESSION["timestamps"][$timestamp];
    if(isset($storedTab["YearWeek"])) {
        $yearWeek=$storedTab["YearWeek"];
    }else {
        return -1;
    }
    $storedTasks=array();
    if(isset($storedTab['tasks'])) {
        $storedTasks=$storedTab['tasks'];
    }else {
        return -2;
    }
    if(isset($storedTab['weekDays'])) {
        $storedWeekdays=$storedTab['weekDays'];
    }else {
        return -3;
    }
        
    $ret=0;
    $tmpRet=0;
    $_SESSION['timeSpendCreated']=0;
    $_SESSION['timeSpendDeleted']=0;
    $_SESSION['timeSpendModified']=0;
        /*
         * For each task store in matching the session timestamp
         */
$userid=  is_object($user)?$user->id:$user;
foreach($storedTasks as  $taskId => $taskItem)
    {
      //  $taskId=$taskItem["id"];
        $tasktimeIds=array();
        $tasktimeIds=$taskItem["taskTimeId"];
        $tasktime=new timesheet($db,$taskId);
        $tasktime->timespent_fk_user=$userid;
        $tasktime->fetch($taskId);
        dol_syslog("Timesheet::Submit.php::postActualsSecured  task=".$tasktime->id, LOG_DEBUG);
        //use the data stored
        //$tasktime->initTimeSheet($taskItem['weekWorkLoad'], $taskItem['taskTimeId']);
        //refetch actuals
        $tasktime->getActuals($yearWeek, $userid); 
        /*
         * for each day of the task store in matching the session timestamp
         */
        //foreach($taskItem['taskTimeId'] as $dayKey => $tasktimeid)
        foreach($tabPost[$taskId] as $dayKey => $wkload)
        {
            dol_syslog("Timesheet::Submit.php::postActualsSecured  task = ".$taskId." tabPost[".$dayKey."]=".$wkload, LOG_DEBUG);

            $tasktimeid=$tasktimeIds[$dayKey];
            
            $ret+=postTaskTimeActual($user,$tasktime,$tasktimeid,$wkload,$storedWeekdays[$dayKey]);
        }
        if($ret!=$tmpRet){ // something changed so need to updae the total duration
            $tasktime->updateTimeUsed();
        }
        $tmpRet=$ret;
    } 
//    unset($_SESSION["timestamps"][$timestamp]);
    return $ret;
}

/*
 * function to post on task_time
 * 
 *  @param    int              	$user                    user id to fetch the timesheets
 *  @param    object             	$tasktime             timesheet object, (task)
 *  @param    array(int)              	$tasktimeid          the id of the tasktime if any
 *  @param     int              	$timestamp          timesheetweek
 *  @return     int                                                       1 => succes , 0 => Failure
 */
function postTaskTimeActual($user,$tasktime,$tasktimeid,$wkload,$date)
{

   $ret=0;
   if(TIMESHEET_TIME_TYPE=="days")
   {
      $duration=$wkload*TIMESHEET_DAY_DURATION*3600;
   }else
   {
    $durationTab=date_parse($wkload);
    $duration=$durationTab['minute']*60+$durationTab['hour']*3600;
   }
    dol_syslog("Timesheet::Submit.php::postTaskTimeActualSecured   timespent_duration=".$duration." taskTimeId=".$tasktimeid, LOG_DEBUG);

    if($tasktimeid>0)
    {
        $tasktime->fetchTimeSpent($tasktimeid); ////////////////////////////
        $tasktime->timespent_old_duration=$tasktime->timespent_duration;
        $tasktime->timespent_duration=$duration; 
        if($tasktime->timespent_old_duration!=$duration)
        {
            if($tasktime->timespent_duration>0){ 
                dol_syslog("Timesheet::Submit.php  taskTimeUpdate", LOG_DEBUG);
                if($tasktime->updateTimeSpent($user,0)>=0)
                {
                    $ret++; 
                    $_SESSION['timeSpendModified']++;
                }
            }else {
                dol_syslog("Timesheet::Submit.php  taskTimeDelete", LOG_DEBUG);
                if($tasktime->delTimeSpent($user,0)>=0)
                {
                    $ret++;
                    $_SESSION['timeSpendDeleted']++;
                }
            }
        }
    } elseif ($duration>0)
    { 
        $tasktime->timespent_duration=$duration; 
        //FIXME
        $tasktime->timespent_date=strtotime($date);
        if(isset( $tasktime->timespent_datehour))
        {
            $tasktime->timespent_datehour=strtotime($date.'+ 8 hours');
        }
        if($tasktime->addTimeSpent($user,0)>=0)
        {
            $ret++;
            $_SESSION['timeSpendCreated']++;
        }
    }
    return $ret;
}

/*
 * function to post on task_time
 * 
 *  @param    object              	$db                  database object
 *  @param    int                       $userid              timesheet object, (task)
 *  @param    string              	$yearWeek            year week like 2015W09
 *  @param     int              	$whitelistmode        whitelist mode, shows favoite o not 0-whiteliste,1-blackliste,2-non impact
 *  @return     string                                         XML result containing the timesheet info
 */
function GetTimeSheetXML($userids,$yearWeek,$whitelistmode)
{
    global $langs;
    global $db;

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $timestamp=time();



    $xml.= "<timesheet yearWeek=\"{$yearWeek}\" timestamp=\"{$timestamp}\" timetype=\"".TIMESHEET_TIME_TYPE."\"";
    $xml.=' nextWeek="'.date('Y\WW',strtotime($yearWeek."+3 days +1 week")).'" prevWeek="'.date('Y\WW',strtotime($yearWeek."+3 days -1 week")).'">';
    //error handling
    $xml.=getEventMessagesXML();
    //$xml.=' <eventMessage style="warning"> test ok</eventMessage>';
    //$xml.=' <eventMessage > test ok 2</eventMessage>';
    //$xml.=' <eventMessage style="error"> test error</eventMessage>';

    $headers=explode('||', TIMESHEET_HEADERS);
    //header
    $i=0;
    $xmlheaders=''; 
    foreach($headers as $header){
        if ($header=='Project'){
            $link=' link="'.DOL_URL_ROOT.'/projet/card.php?id="';
        }elseif ($header=='Tasks' || $header=='TaskParent'){
            $link=' link="'.DOL_URL_ROOT.'/projet/tasks/task.php?withproject=1&amp;id="';
        }elseif ($header=='Company'){
            $link=' link="'.DOL_URL_ROOT.'/societe/soc.php?socid="';
        }else{
            $link='';
        }
        $xmlheaders.= "<header col=\"{$i}\" name=\"{$header}\" {$link}>{$langs->transnoentitiesnoconv($header)}</header>";
        $i++;
    }
    $xml.= "<headers>{$xmlheaders}</headers>";
        //days
    $xmldays='';
    for ($i=0;$i<7;$i++)
    {
       $curDay=strtotime( $yearWeek.' +'.$i.' day');
       $weekDays[$i]=date('d-m-Y',$curDay);
       $curDayTrad=$langs->trans(date('l',$curDay)).'  '.date('d/m/y',$curDay);
       $xmldays.="<day col=\"{$i}\">{$curDayTrad}</day>";
    }
    $_SESSION["timestamps"][$timestamp]['YearWeek']=$yearWeek;
    $_SESSION["timestamps"][$timestamp]["weekDays"]=$weekDays;
    $xml.= "<days>{$xmldays}</days>";
        //FIXME unset timestamp
    $staticTimesheet=New timesheet($db,0);
    if (!is_array($userids))$userids=array('0'=>$userids);
    $userNames=get_userName($userids);
    
    foreach($userNames as $userid => $userName)
    {
        $tab=$staticTimesheet->timesheetTab($headers,$userid,$yearWeek,$timestamp,$whitelistmode);
        $i=0;
        $xml.="<userTs userid=\"{$userid}\"  count=\"".count($tab)."\" userName=\"{$userName}\" >";
        foreach ($tab as $timesheet) {
            $row=unserialize($timesheet);
            $xml.= $row->getXML($yearWeek);//FIXME
           // $Lines.=$row->getFormLine( $yearWeek,$i,$headers);
            $_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=array();
            $_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=$row->getTaskTab(); 
            $i++;
        }  
        $xml.="</userTs>";
    }
    $xml.="</timesheet>";

    return $xml;

}
/*
 * function to print the timesheet navigation header
 * 
 *  @param    string              	$yearWeek            year week like 2015W09
 *  @param     int              	$whitelistmode        whitelist mode, shows favoite o not 0-whiteliste,1-blackliste,2-non impact
 *  @param     object             	$form        		form object
 *  @return     string                                         HTML
 */
function pintNavigationHeader($yearWeek,$whitelistmode,$optioncss,$form){
	global $langs;
        $Nav=  '<table class="noborder" width="50%">'."\n\t".'<tr>'."\n\t\t".'<th>'."\n\t\t\t";
	//$Nav.=  '<a href="?action=list&wlm='.$whitelistmode.'&yearweek='.date('Y\WW',strtotime($yearWeek."+3 days  -1 week"));
	//if ($optioncss != '')$Nav.=   '&amp;optioncss='.$optioncss;
	$Nav.=  '<a id="navPrev" onClick="loadXMLTimesheet(\''.date('Y\WW',strtotime($yearWeek."+3 days  -1 week")).'\',0);';
        $Nav.=  '">  &lt;&lt; '.$langs->trans("PreviousWeek").' </a>'."\n\t\t</th>\n\t\t<th>\n\t\t\t";
	$Nav.=  '<form name="goToDate" onsubmit="return toDateHandler();" action="?action=goToDate&wlm='.$whitelistmode.'" method="POST">'."\n\t\t\t";
	//$Nav.=  '<form name="goToDate" action="?action=goToDate&wlm='.$whitelistmode.'" method="POST" >'."\n\t\t\t";
	$Nav.=   $langs->trans("GoToDate").': '.$form->select_date(-1,'toDate',0,0,0,"",1,0,1)."\n\t\t\t";;
	$Nav.=  '<input type="submit" value="Go" /></form>'."\n\t\t</th>\n\t\t<th>\n\t\t\t";
//	$Nav.=  '<a href="?action=list&wlm='.$whitelistmode.'&yearweek='.date('Y\WW',strtotime($yearWeek."+3 days +1 week"));
//	if ($optioncss != '') $Nav.=   '&amp;optioncss='.$optioncss;
	$Nav.=  '<a id="navNext" onClick="loadXMLTimesheet(\''.date('Y\WW',strtotime($yearWeek."+3 days  +1 week")).'\',0);';
	$Nav.=  '">'.$langs->trans("NextWeek").' &gt;&gt; </a>'."\n\t\t</th>\n\t</tr>\n </table>\n";
        return $Nav;
}


if (!is_callable(setEventMessages)){
    // function from /htdocs/core/lib/function.lib.php in Dolibarr 3.8
    function setEventMessages($mesg, $mesgs, $style='mesgs')
    {
            if (! in_array((string) $style, array('mesgs','warnings','errors'))) dol_print_error('','Bad parameter for setEventMessage');
            if (empty($mesgs)) setEventMessage($mesg, $style);
            else
            {
                    if (! empty($mesg) && ! in_array($mesg, $mesgs)) setEventMessage($mesg, $style);	// Add message string if not already into array
                    setEventMessage($mesgs, $style);

            }
    }
}

/*
 * function retrive the dolibarr eventMessages ans send then in a XML format
 * 
 *  @return     string                                         XML
 */
function getEventMessagesXML(){
    $xml='';
       // Show mesgs
   if (isset($_SESSION['dol_events']['mesgs'])) {
     $xml.=getEventMessageXML( $_SESSION['dol_events']['mesgs']);
     unset($_SESSION['dol_events']['mesgs']);
   }

   // Show errors
   if (isset($_SESSION['dol_events']['errors'])) {
     $xml.=getEventMessageXML(  $_SESSION['dol_events']['errors'], 'error');
     unset($_SESSION['dol_events']['errors']);
   }

   // Show warnings
   if (isset($_SESSION['dol_events']['warnings'])) {
     $xml.=getEventMessageXML(  $_SESSION['dol_events']['warnings'], 'warning');
     unset($_SESSION['dol_events']['warnings']);
   }
   return $xml;
}

/*
 * function convert the dolibarr eventMessage in a XML format
 * 
 *  @param    string              	$message           message to show
 *  @param    string              	$style            style of the message error | ok | warning
 *  @return     string                                         XML
 */
function getEventMessageXML($messages,$style='ok'){
    $msg='';
    
    if(is_array($messages)){
        $count=count($messages);
        foreach ($messages as $message){
            $msg.=$message;
            if($count>1)$msg.="<br/>";
            $count--;
        }
    }else
        $msg=$messages;
    $ret='';
    if($msg!=""){  
        if($style!='error' && $style!='warning')$style='ok';
        $ret= "<eventMessage style=\"{$style}\"> {$msg}</eventMessage>";
    }
    return $ret;
}