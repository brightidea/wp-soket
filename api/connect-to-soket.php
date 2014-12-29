<?php
/*
* Soket API connect
*/

class Soket_API{
	protected $Soket_API_URL='https://access.soket.com/1'; 
	protected $Soket_Consumer_ID='YBLQRBBWRXNUWHQPJNUC';
	protected $Soket_Secret='YMHZYDYUABZLOHPHSICE';
	protected $Soket_Community_ID='100012741';
	//* constructor function
	function __construct(){
		
	}
	
	/*
	* Retrieve all events from the current date to the last 30 days
	*/
	function get_all_events(){
		//* loop and get all the data
		  $prev = '';
		  $pathApi = $this->Soket_API_URL.'/community/'.$this->Soket_Community_ID.'/events';
		  $last30days = gmdate('m/d/Y', strtotime('-30 days'));
		  $curdate = gmdate('m/d/Y', strtotime('+1 day'));
		  //* set the parameters
		  $parameters = "?start_date=$last30days&end_date=$curdate&count=1000000"; 
		  //* process and add the event datas
		  $responseData = $this->createRequest($pathApi, $parameters , $this->Soket_Secret, $this->Soket_Consumer_ID);		
		  foreach($responseData['data'] as $event){			 		
			  $storeObjs[] = $event;
		  }	
		  return $storeObjs;
	}
			
	//* create Soket request
	function createRequest($base_url, $query, $secret, $consumerid)
	{	
		$url = $base_url . $query; 
		$contentLength = strlen($post_data);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		
		$date = gmdate('m/d/Y H:i:s \G\M\T', strtotime(date( 'D, d M Y H:i:s', time() )));
		
		//echo $date;	
		$msg = $this->createMessage('Get', $base_url, 'application/json', $date);
		$accesstoken = $this->generateToken($msg, $secret);
		// Override the default headers
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('AccessKeyId: '.$consumerid, 'Signature: '.$accesstoken, 'Timestamp:'.$date, 'Content-Type: application/json', 'Accept: application/json', "Expect: 100-continue"));
			// 0 do not include header in output, 1 include header in output
		curl_setopt($ch, CURLOPT_HEADER, 0);   
		
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
		
		// if you are not running with SSL or if you don't have valid SSL
		$verify_peer = false;
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_peer);
		
		// Disable HOST (the site you are sending request to) SSL Verification,
		// if Host can have certificate which is invalid / expired / not signed by authorized CA.
		$verify_host = false;
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify_host);
		
		// Set the post variables
		//curl_setopt($ch, CURLOPT_POST, 1);
		
		// Set so curl_exec returns the result instead of outputting it.
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
		//echo $ch;
		// Get the response and close the channel.
		$response = curl_exec($ch);
		curl_close($ch);
		
		//echo $response;
		$json_obj = json_decode($response, TRUE);	
		
		return $json_obj;
	}		

	function createMessage($httpMethod, $url, $contentType, $date)
	{
		if (strtoupper($httpMethod) == "GET")
				$contentType = "";
		$msg =  strtoupper($httpMethod).$url.$contentType.$date;
		
		//echo $msg;
		return $msg;
	}

	function generateToken($message, $secret)
	{	
		$s = hash_hmac('sha256', $message, $secret, true);
		$token = base64_encode($s);	
		//echo $token;
		return $token;
	}
	
	
	function exp_to_dec($float_str)
	// formats a floating point number string in decimal notation, supports signed floats, also supports non-standard formatting e.g. 0.2e+2 for 20
	// e.g. '1.6E+6' to '1600000', '-4.566e-12' to '-0.000000000004566', '+34e+10' to '340000000000'
	// Author: Bob
	{
		// make sure its a standard php float string (i.e. change 0.2e+2 to 20)
		// php will automatically format floats decimally if they are within a certain range
		$float_str = (string)((float)($float_str));
	
		// if there is an E in the float string
		if(($pos = strpos(strtolower($float_str), 'e')) !== false)
		{
			// get either side of the E, e.g. 1.6E+6 => exp E+6, num 1.6
			$exp = substr($float_str, $pos+1);
			$num = substr($float_str, 0, $pos);
		   
			// strip off num sign, if there is one, and leave it off if its + (not required)
			if((($num_sign = $num[0]) === '+') || ($num_sign === '-')) $num = substr($num, 1);
			else $num_sign = '';
			if($num_sign === '+') $num_sign = '';
		   
			// strip off exponential sign ('+' or '-' as in 'E+6') if there is one, otherwise throw error, e.g. E+6 => '+'
			if((($exp_sign = $exp[0]) === '+') || ($exp_sign === '-')) $exp = substr($exp, 1);
			else trigger_error("Could not convert exponential notation to decimal notation: invalid float string '$float_str'", E_USER_ERROR);
		   
			// get the number of decimal places to the right of the decimal point (or 0 if there is no dec point), e.g., 1.6 => 1
			$right_dec_places = (($dec_pos = strpos($num, '.')) === false) ? 0 : strlen(substr($num, $dec_pos+1));
			// get the number of decimal places to the left of the decimal point (or the length of the entire num if there is no dec point), e.g. 1.6 => 1
			$left_dec_places = ($dec_pos === false) ? strlen($num) : strlen(substr($num, 0, $dec_pos));
		   
			// work out number of zeros from exp, exp sign and dec places, e.g. exp 6, exp sign +, dec places 1 => num zeros 5
			if($exp_sign === '+') $num_zeros = $exp - $right_dec_places;
			else $num_zeros = $exp - $left_dec_places;
		   
			// build a string with $num_zeros zeros, e.g. '0' 5 times => '00000'
			$zeros = str_pad('', $num_zeros, '0');
		   
			// strip decimal from num, e.g. 1.6 => 16
			if($dec_pos !== false) $num = str_replace('.', '', $num);
		   
			// if positive exponent, return like 1600000
			if($exp_sign === '+') return $num_sign.$num.$zeros;
			// if negative exponent, return like 0.0000016
			else return $num_sign.'0.'.$zeros.$num;
		}
		// otherwise, assume already in decimal notation and return
		else return $float_str;
	}
	
	function renderEvents($listEvent)
	{
		if(isset($listEvent))
		{		
			foreach ($listEvent as $value) {
				//echo 'Result: ' . $value['title'] . "<br />";	
				$html = $this->renderEvent($value);
					echo "$html\n";
			}
			
			
		}	
	}
	
	function renderEvent($event)
	{
		$htmlEvent ='<div class="soket-result-date-range soket-round5">
									</div><!-- End: soket-result-date-range --> 
	<div class="soket-result soket-round5 soket-clearfix">
	<div class="soket-result-left-column soket-pull-left">
																					<a href="event_test.php?event_id='.$event['id'].'" title="View full details of this event."><img src="'.$event['event_image'].'" class="soket-result-image soket-round5" width="75" /></a>
																	</div><!-- End: soket-result-left-column -->
																					<div class="soket-result-right-column">
																   
																					<div class="soket-result-title">
																									<a href="event_test.php?event_id='.$event['id'].'" title="View full details of this event.">'.$event['title'].'</a>
																					</div><!-- End: soket-result-title -->
																				   
																					<div class="soket-result-description">
																									<p>'.$event['description_raw'].'</p>
																					</div><!-- End: soket-result-description -->
																				   
																					<div class="soket-result-content">
																				   
																									Type: <strong>'.$event['event_type'].'</strong><br />';
																									
	if($event['start_date_string']!= $event['end_date_string'])
	{
		$htmlEvent .='When: <strong>'.$event['start_date_string'].' '.$event['start_time_string'].'</strong> to <strong>'.$event['end_date_string'].' '.$event['end_time_string'].'</strong>';
		
	}else
	{
																									
	  $htmlEvent .='When: <strong>'.$event['start_time_string'].'</strong> to <strong>'.$event['end_time_string'].'</strong><br />
				   Date: <strong>'.$event['start_date_string'].'</strong>';
	}
																									
	if(isset($event['location']))
	{																							
		$htmlEvent .='<br /> Location: <strong>'.$event['location']['name'].'</strong><br />';
	}
																								   
	
	/* $htmlEvent .='<div class="soket-result-buttons">
							  <a href="@eventdetailurl()" class="soket-btn light-grey" title="View full details of this event.">View Details</a><BR/>
																													@sharewidget()
							  <a href="#" onClick="@AddToCalendarClick()" class="" style="padding:4px;" title="Save event to calendar."><img src="http://plugengine.soket.com/Content/images/btn-add-to-calendar.png"  /></a>
																												   
																									</div><!-- End: soket-result-buttons -->
																									 */
																								   
	$htmlEvent .=' </div><!-- End: soket-result-content -->
					</div><!-- End: soket-result-right-column -->
	</div><!-- End: soket-result -->';
	
	 
	 return $htmlEvent;
		
	}

}