<?php

  //error out if not ajax request
  if (!isset($_REQUEST['ajax'])) { 
    echo "not ajax request";
    return FALSE;
  }
    
  call_user_func($_REQUEST['function'], $_REQUEST);  
  
  //get all unique job names
  function get_job_names()  {  
    try  {
      //create or open the database
      $dbh = new PDO('sqlite:sql/union_wage.sqlite');
  	}
    catch(Exception $e)  {
      die($e->GetMessage());
    }
     
    $sql = "SELECT uid,job_title_normalized FROM job_title";

	$html_output = "<select id=\"job_title\">\n";
	//to prevent duplicate positions
	//instead of searching against the whole array, use last job_title
	$last_job_title = "";
	foreach ($dbh->query($sql) as $row) {
	    if (!strcmp($last_job_title, $row['job_title_normalized'])) {
	    	continue;
	    }
	    $last_job_title = $row['job_title_normalized'];
    	$html_output .= sprintf("<option value=\"%d\">%s</option>\n", 
						$row['uid'], $row['job_title_normalized']);
    }
    $html_output .=  "</select>";
	echo $html_output;   
  } 
  
  //Get the salery for a job at all hospitals with that number of years experience.
  function get_salery(&$args) {
    if (!isset($args['years']) && !isset($args['jid']) ) { 
      echo "malformed request";
      return False;
    }
    if (!is_numeric($args['years']) || !is_numeric($args['jid']) ||
        !$args['years'] || strlen($args['years']) >2 ) {
      echo "malformed request";
      return False;     
    }
  
    try  {
      //create or open the database
      $dbh = new PDO('sqlite:sql/union_wage.sqlite');
  	}
    catch(Exception $e)  {
      die($e->GetMessage());
    }
       
    $step = sprintf('step%02d', $args['years']);
    $jid = $args['jid'];

    //check data range of years
    $sql = "SELECT name FROM sqlite_master WHERE type='table' ".
           "AND name NOT LIKE 'sqlite_%' ORDER BY name DESC LIMIT 1;";
           
    $result = $dbh->query($sql)->fetch();
    $highest_step = $result['name'];
    
    //so ugly
    if (0 >= strcmp($highest_step, $step)) {
      $step =$highest_step;
    }   
    
    $sql = sprintf("SELECT job_title, hospital_name, salery FROM job_title, hospital, %s ".  
           "WHERE job_title.uid = %s.uid AND job_title.hospital_id = hospital.uid AND " . 
           "job_title.job_title_normalized = " .
           "(SELECT job_title_normalized FROM job_title WHERE job_title.uid  = %d)",
            $step, $step, $jid);   
    
    $formatted_data = array();
    $html_output = "";
    foreach ($dbh->query($sql) as $row) {
       if (!$row['hospital_name']) {
           break;
       }
       if (!$row['salery']) {
         $row['salery'] = get_highest_salery($dbh, $jid, $step, $row['hospital_name']); 
       }
       $html_output .= sprintf("<li>A <span class=\"title\">%s</span> at <span class=\"facility\">".
                       "%s</span> makes <span class=\"pay\">$%01.2f</span> per hour.</li>\n",
                       $row['job_title'], $row['hospital_name'], $row['salery']);                
    }
    if (!$html_output) {
      echo "no matching job_titles found.\n";
    }
    else {
	    echo $html_output;
	}
  }
  
  //Get the highest salery for given job at a given hospital
  function get_highest_salery($dbh, $jid, $step, $hospital_name) {
    $countdown = (int) substr($step, 4, 2);
    $salery = 0;
    for ($i = $countdown-1; $i > 0; $i--) {
      //$sql = sprintf("SELECT salery FROM step%d WHERE uid = %d", $i, $jid);
      $sql = sprintf("SELECT step%s.salery FROM step%s, job_title WHERE job_title.uid = step%s.uid AND " .
                     "job_title.job_title_normalized =  " .
                     "(SELECT job_title_normalized FROM job_title WHERE job_title.uid  = %d) AND " .
                     "job_title.hospital_id = " .
                     "(SELECT uid FROM hospital WHERE hospital_name = \"%s\")",
                     $i, $i, $i, $jid, $hospital_name);                    
      
      $result = $dbh->query($sql)->fetch();
      if (!$result['salery']) {
        continue;
      }
      $salery = $result['salery'];
      break;
    }          
    return $salery;
  }
?>