<?php namespace Hipokrates\Http\Controllers;

use Hipokrates\Http\Requests;
use Hipokrates\Http\Controllers\Controller;
use Hipokrates\Http\Controllers\WebServicesController as WebService;
use Hipokrates\Http\Controllers\HistoryController as HistoryController;
use Hipokrates\Http\Controllers\SymptomsController as SymptomsController;
use Hipokrates\Http\Controllers\DeviceController as DeviceController;
use Hipokrates\Http\Controllers\AiController as AiController;
use Hipokrates\Http\Controllers\CcdaController as Ccda;
use Hipokrates\Http\Controllers\ProviderController as ProviderController;
use Hipokrates\ProviderDetail as ProviderDetail;
use Hipokrates\ProviderPatient as ProviderPatient;
use Hipokrates\RecommendedNutrients as RecommendedNutrients;
use Hipokrates\User as User;
use Hipokrates\Profile as Profile;
use Hipokrates\Provider as Provider;
use Hipokrates\Practice as Practice;
use Hipokrates\Fileentry as Fileentry;
use Hipokrates\Messages as Messages;
use Hipokrates\MessageRecipient as MessageRecipient;
use Hipokrates\Consults as Consults;
use Hipokrates\Synopsis as Synopsis;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class CaseController extends Controller {

	private $hstnum;
	private $patient;
	private $history;
	private $symptoms;
	private $userispatient;
	private $webservice;
	private $userpatientquery;
	
	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->user = \Auth::user();
		
		if($this->user){
			$this->userispatient = false;
			$this->userisprovider = false;
			
			$webservice = new WebService;
			
			
			$userpatientquery = ProviderPatient::where('user', '=', intval($this->user->id))->first();
			
			$this->userpatientquery = $userpatientquery;
			
			$userproviderquery = Provider::where('user', '=', intval($this->user->id))->first();
			
			$avatarcheck = Fileentry::raw('user', '=', intval($this->user->id))->first();
			$avatarcheck = Fileentry::whereRaw('user = '.intval($this->user->id).' and type = "avatar"')->first();
			if(is_object($avatarcheck)){
				$this->avatar = '/avatars/get/'.$avatarcheck->filename;
			}else{
				$this->avatar = "/img/placeholders/avatars/avatar.jpg";
			}
			
			if(is_object($userpatientquery)){
				
				$this->hstnum = $userpatientquery->identifier;
				$this->userispatient = true;
				$this->patient = \DB::connection('mongodb')->collection('entry')->where('hstnum',intval($this->hstnum))->first();
				
			}elseif(is_object($userproviderquery)){
				
				
				
				$this->userisprovider = true;
				$this->provider = $userproviderquery;
				
			}else{
				return redirect()->route('profile');
			}
			
			$this->history = new HistoryController;
			$this->symptoms = new SymptomsController;
			
			$brain = \DB::connection('mongodb')->collection('entry')->get();			
		}
		
	}
	
	public function readpatientccda($patientxml) {
		$patientxml = new Ccda($patientxml);
		
		//var_dump($patient);
		//DIE;
		
		/*
		//RENAME FILES TO MATCH REQUIRED STRUCTURE
		
		$directory = '/var/www/practiceportal/imports/temp/';
		if ($handle = opendir($directory)) { 
			$count = 0;
			$filearray = array();
			 while (false !== ($fileName = readdir($handle))) {
				if($fileName != "." && $fileName != ".."){
					$dd = explode('.xml', $fileName);
					
					//FILENAME WITH PREFIX REMOVED
					$extracted_filename = substr($dd[0],8);
					$extracted_filename = str_replace(".", "", $extracted_filename);
					
					preg_match_all('!\d+!', $extracted_filename, $matches);
					
					//IDENTIFIER FROM FILE
					echo $matches[0][0]."<br />";
					
					if(count($matches[0]) > 0){
						$hst_id_prefix = $matches[0][0];
						$hst_id = strstr($extracted_filename,$hst_id_prefix);
						$extracted_pat_name = strstr($extracted_filename,$hst_id_prefix, true);
						//$extracted_pat_name = explode(" ",strtolower($extracted_pat_name));
						$extracted_pat_name = strtolower($extracted_pat_name);
						$extracted_pat_name = str_replace("jr", "",$extracted_pat_name);
						$extracted_pat_name = str_replace("sr", "",$extracted_pat_name);
						$extracted_pat_name = str_replace("md_", "",$extracted_pat_name);
						$extracted_pat_name = str_replace("m.d.", "",$extracted_pat_name);
						$extracted_pat_name = str_replace(" ", "",$extracted_pat_name);
						$extracted_pat_name = explode("_",$extracted_pat_name);
						
						$hst_lastname = $extracted_pat_name[0];
						
						if(count($extracted_pat_name) > 1){
							$hst_firstname = $extracted_pat_name[1];
						}else{
							$hst_firstname = "null";
						}
						
						$newfile = ucfirst(strtolower($hst_firstname)).ucfirst(strtolower($hst_lastname)).'.'.$hst_id;
						$filearray[] = $newfile;
						echo $newfile."--".$count."<br />";
						
						rename("/var/www/practiceportal/imports/temp/".$fileName, "/var/www/practiceportal/imports/renamed/".$newfile);
					}else{
					}
				//var_dump(count($filearray));
					//rename($directory . $fileName, $directory.$newfile);
					$count++;
				}
				
			}
			closedir($handle);
		}
		DIE;
		*/
		
		// Construct and echo JSON 
		$xmlreport = $patientxml->xml->component->structuredBody->component;
		$xmldemo = $patientxml->xml->recordTarget->patientRole;
		
		$identifier = (array)$xmldemo->id['extension'];
		
		$patient = array();
		$patient['hst'] = $identifier[0];
		$patient['demo'] = array();
		$patient['chiefcomplaint'] = array();
		$patient['encounterlist'] = array();
		$patient['rxlist'] = array();
		$patient['allergylist'] = array();
		$patient['lablist'] = array();
		$patient['diaglist'] = array();
		$patient['procedurelist'] = array();
		$patientGivenName = trim($xmldemo->patient->name->given);
		$patientGivenNameCleaned = explode(" ", $patientGivenName);
		$patient['demo']['given'] = (count($patientGivenNameCleaned) > 1) ? $patientGivenNameCleaned[1] : $patientGivenName;
		$patient['demo']['family'] = implode(" ", (array)trim($xmldemo->patient->name->family));
		$patient['demo']['gender'] = implode(" ", (array)$xmldemo->patient->administrativeGenderCode['code']);
		$dobarray = (array)$xmldemo->patient->birthTime;
		if(array_key_exists('value', $dobarray['@attributes'])){
			$dob = date_parse_from_format('Ymd', $dobarray['@attributes']['value']);
			$patient['demo']['dob'] = $dob['month']."/".$dob['day']."/".$dob['year'];
		}else{
			$patient['demo']['dob'] = "01/01/1500";			
		}
		
		$patient['demo']['smoke'] = 'X';
		$patient['demo']['height'] = 1;
		$patient['demo']['weight'] = 1;
		$patient['demo']['race'] = implode(" ", (array)$xmldemo->patient->raceCode['displayName']);
		$patient['demo']['ethnicity'] = implode(" ", (array)$xmldemo->patient->ethnicGroupCode['displayName']);
		$patient['demo']['address'] = (is_array($xmldemo->addr->streetAddressLine)) ? implode(" ", $xmldemo->addr->streetAddressLine) : $xmldemo->addr->streetAddressLine;
		$patient['demo']['city'] =$xmldemo->addr->city;
		$patient['demo']['state'] = $xmldemo->addr->state;
		$patient['demo']['zip'] = $xmldemo->addr->postalCode;
		$telecommpref = (array)$xmldemo->telecom;
		if(count($telecommpref) > 0){
			if(!empty($telecommpref['@attributes'])){
				if(array_key_exists('value', $telecommpref['@attributes'])){
					$telecommnum = explode(":",$telecommpref['@attributes']['value']);
					$patient['demo']['phone'] = $telecommnum[1];
				}else{
					$patient['demo']['phone'] = 0000000000;
				}
			}
		}else{
			$patient['demo']['phone'] = 0000000000;
		}
		
		
		foreach($xmlreport as $i => $component){
			//2.16.840.1.113883.10.20.22.2.1.1 - Medications
			//2.16.840.1.113883.10.20.22.2.6.1 - Allergies
			//2.16.840.1.113883.10.20.22.2.22 || 2.16.840.1.113883.10.20.22.2.22.1 - Encounters
			//2.16.840.1.113883.10.20.22.2.2.1 || 2.16.840.1.113883.10.20.22.2.2 - Immunizations
			//2.16.840.1.113883.10.20.22.2.3.1 - Labs
			//2.16.840.1.113883.10.20.22.2.5.1 || 2.16.840.1.113883.10.20.22.2.5 - Problems
			//2.16.840.1.113883.10.20.22.2.7.1 || 2.16.840.1.113883.10.20.22.2.7 - Procedures
			//2.16.840.1.113883.10.20.22.2.4.1 - Vitals
			//2.16.840.1.113883.10.20.22.2.10 - Careplan
			//2.16.840.1.113883.10.20.22.2.13 - Chief Complaint
			$tId = $component->section->templateId->attributes()->root;
			$title = (array)$component->section->title;
			$entry = (array)$component->section->entry;
			$entryarray = (array)$entry;
			
			//CHIEF COMPLAINT
			if($tId == "2.16.840.1.113883.10.20.22.2.13"){
				$complaint = (array)$component->section->text->table->tbody->tr->td;
				$patient['chiefcomplaint'] = (!empty($complaint[0])) ? $complaint[0] : "NI";
			}
			
			//SOCIAL INFO
			if($tId == "2.16.840.1.113883.10.20.22.2.17"){
				//SMOKING STATUS
				$sTid = (array)$component->section->entry->observation->templateId;
				if($sTid['@attributes']['root'] == "2.16.840.1.113883.10.20.22.4.78"){
					$smokestatusvalue = (array)$component->section->entry->observation->value;
					if(array_key_exists('displayName', $smokestatusvalue['@attributes'])){
						$patient['demo']['smoke'] = $smokestatusvalue['@attributes']['displayName'];													
					}else{
						$patient['demo']['smoke'] = "NI";						
					}
				}
			}

			//ENCOUNTERS
			if($tId == "2.16.840.1.113883.10.20.22.2.22" || $tId == "2.16.840.1.113883.10.20.22.2.22.1"){
				foreach($component->section->entry->encounter as $encounter){
					$encounterObject = (array)$encounter->text;
					$encounterText = (!empty((array)$encounterObject[0])) ? (array)$encounterObject[0] : "NI";
					$encounterValue = ($encounterText != "NI") ? (array)$encounterText[0] : "NI";
					$encounterTime = (array)$encounter->effectiveTime;
					$patient['encounterlist'][] = ($encounterValue != "NI") ? $encounterValue['@attributes']['value'] : "NI";
				}
			}			
			
			//MEDICATIONS
			if($tId == '2.16.840.1.113883.10.20.22.2.1.1'){
				$medentry = $component->section->text;
				$count = 0;
				foreach($medentry->table->tbody->tr as $i => $medlabel){
					$details = (array)$medlabel->td;
					if(!empty($details)){						
						$split_by_space_rx = (!strstr($details[1],"Rx")) ? "Not Indicated" : explode(" ",$details[1]);
						$rxcode = (int)$split_by_space_rx[1];
						$patient['rxlist'][$count]['code']['code'] = ($rxcode == 0) ? "Not Indicated" : $rxcode;
						$patient['rxlist'][$count]['code']['name'] = $details[0]." (".$details[2].")";
						$patient['rxlist'][$count]['daterange']['start'] = $details[4];
						$patient['rxlist'][$count]['daterange']['end'] = $details[5];
						$patient['rxlist'][$count]['strength'] = $details[3];
						$patient['rxlist'][$count]['instructions'] = $details[6];
						$count++;
					}
				}
				//var_dump($rxlist);
			}
			
			//ALLERGIES
			if($tId == '2.16.840.1.113883.10.20.22.2.6.1'){
				$count = 0;
				foreach($entry as $allergy){
					if(isset($allergy['act'])){
						$patient['allergylist'][] = $allergy->act;
					}
				}
				//var_dump($allergylist);
			}
			
			//LABS
			if($tId == '2.16.840.1.113883.10.20.22.2.3.1'){
				$count = 0;
				foreach($component->section->entry as $lab){
					//var_dump($lab->organizer->component->observation);
					
					$code = (array)$lab->organizer->component->observation->code['code'];
					$name = (array)$lab->organizer->component->observation->code['displayName'];
					$labeffdate = (array)$lab->organizer->component->observation->effectiveTime['value'];
					$result = $lab->organizer->component->observation->value['value'].$lab->organizer->component->observation->value['unit'];
					
					$patient['lablist'][$count]['code'] = (!empty($code[0])) ? $code[0] : "NI";
					$patient['lablist'][$count]['name'] = (!empty($name[0])) ? $name[0] : "NI";
					$patient['lablist'][$count]['labeffdate'] = (!empty($labeffdate[0])) ? $labeffdate[0] : "NI";
					$patient['lablist'][$count]['result'] = ($result != "") ? $result : "NI";
					
					$count++;
				}
				//var_dump($lablist);
			}
			
			//PROBLEMS
			if($tId == '2.16.840.1.113883.10.20.22.2.5.1' || $tId == '2.16.840.1.113883.10.20.22.2.5'){
				//var_dump($problem->act->entryRelationship->observation);
				$count = 0;
				foreach($component->section->entry as $problem){
					$problemDetail = $problem->act->entryRelationship->observation->value->translation;
					
					//var_dump($problemDetail['codeSystemName']);
					
					if($problemDetail['codeSystemName'] == "ICD9CM" || $problemDetail['codeSystemName'] == "ICD10CM" || $problemDetail['codeSystemName'] == "SNOMED-CT"){
						$code = (array)$problemDetail['code'];
						$displayName = (array)$problemDetail['displayName'];
						$codeSystemName = (array)$problemDetail['codeSystemName'];
						$patient['diaglist'][$count]['code'] = (!empty($code[0])) ? str_replace("-", ".", $code[0]) : "NI";
						$patient['diaglist'][$count]['name'] = (!empty($displayName[0])) ? $displayName[0] : "NI";
						$patient['diaglist'][$count]['codetype'] = (!empty($codeSystemName[0])) ? $codeSystemName[0] : "NI";
					}
					$count++;
				}
				//var_dump($diaglist);
			}
			
			//PROCEDURES
			if($tId == '2.16.840.1.113883.10.20.22.2.7.1' || $tId == '2.16.840.1.113883.10.20.22.2.7'){
				foreach((array)$component->section->text->table->tbody->tr->td as $procedureNote){
					$patient['procedurelist'][] = $procedureNote;
				}
			}
		}
		$patientMap = array();
		foreach($patient as $mapItem => $data){
			$patient['recordMap'][] = $mapItem;
		}
		return $patient;
	}
	
	private function get_attr($xml, $attr) {
		if (is_object($xml)) {
			return (string) $xml->attributes()->{$attr};
		}
		else {
			return '';
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$title = "Case Dashboard";
		$component = "dashboard";
		$user = $this->user;
		$patient = null;
		$avatar = $this->avatar;
		
		if($user->hasRole('provider')){
			return redirect()->route('profile');
		}
		
		if($user->hasRole('patient')){
			
			$providerTool = new ProviderController;
			$patient = $providerTool->getpatient($this->user->id);
			
			if(is_object($this->history)){
				$userhistory = $this->history->fetchhistory($patient['profile']['user']);
			}else{
				$userhistory = array();
			}
		
			if(is_object($this->symptoms)){
				$usersymptoms = $this->symptoms->patientsymptoms($patient['profile']['user']);
			}
			
			
			$recentSynopsis = Synopsis::where('patient','=',$this->user->id)->orderBy('created_at','DESC')->first();
					
			$consults = Consults::whereRaw('patient = '.intval($patient['profile']['user'])." AND date >= CURDATE( ) AND date <= (CURDATE( ) + INTERVAL 6 DAY)")->get();
			
			foreach($userhistory as $key => $historyitem){
				if($historyitem['fileid'] != NULL){
					$userhistory[$key]['file'] = Fileentry::where('id', '=', $historyitem['fileid'])->first();
				}
			}
			
			if(!array_key_exists('ehrentry',$patient)){
				return redirect()->route('profile')->with('message', 'Currently no EHR entry, please ensure your provider has imported your record and you have associated with an existing provider or provider network.');
			}
			
			return view('dashboard', compact('title','component','user','avatar','patient', 'userhistory','usersymptoms','consults','recentSynopsis'));
		}
		
	}
	
	public function requestconsult(Request $request){
		if($request['provider'] != "NULL" && $request['patient'] != "NULL" && $request['date'] != "NULL"){
			//var_dump($request['date']." ".$request['time']);
			if(intval($request['initiated']) != 1){
				return 0;
			}else{				
				$formatteddate = date_format(date_create_from_format('m/d/y h:i:s A', $request['date']." ".$request['time']), 'Y-m-d h:i:s A');
				$patient = $request['patient'];
				$provider = $request['provider'];
				$date = $formatteddate;

				$consult = new Consults;
				$consult->patient = intval($patient);
				$consult->provider = intval($provider);
				$consult->request = 1;
				$consult->date = $date;		

				$consult->save();
				return 1;			
			}
		}else{
			return 0;
		}
	}
	
	
	public function cloudspot(Route $route)
	{
		$title = "Cloudspot";
		$component = "cloudspot";
		$user = $this->user;
		$patient = null;
		$avatar = $this->avatar;
		
		if($this->userispatient){
			$provider = new ProviderController();
			$patient = $provider->getpatient($this->user->id);
		}else{
			return redirect()->route('profile')->with('message', 'Currently no EHR entry, please ensure your provider has imported your record and you have associated with an existing provider or provider network.');
		}
		
		return view('case.cloudspot', compact('title','component','user','avatar','patient'));
	}
	
	public function live()
	{
		$title = "Live Data";
		$component = "live";
		$user = $this->user;
		$patient = null;
		$avatar = $this->avatar;
		
		$connectdevice = new DeviceController;
		$devicedata = $connectdevice->connectdevice('fitbit');
		
		//FOR DEVICES
		$stepvaltodate = null;
		$todayssteps = null;
		
		
		foreach($devicedata['steps'] as $steps){
			$stepvaltodate = $stepvaltodate + intval($steps->value);
			if($steps->dateTime == date('Y-m-d')){
				$todayssteps = $steps->value;
			}
		}
		
		//$summary = $devicedata['recentActivities']->summary;
		
		//var_dump($summary);
		
		//$activitysummary = $this->webservice->xmltoarray((object)$summary);
		
		$activitysummary = array();
		
		if($this->userispatient){
			$patient = $this->patient;
		}
		
		return view('case.live', compact('title','component','user','avatar','patient','devicedata','activitysummary'));
	}
	
	public function messages()
	{
		$title = "Mailbox";
		$component = "mailbox";
		$user = $this->user;
		$patient = null;
		$avatar = $this->avatar;
		$userhistory = array();
		$messages = \DB::table('message')
            ->join('messagerecipient', 'message.id', '=', 'messagerecipient.message')
            ->select('message.id', 'message.author', 'message.subject', 'message.message', 'message.created_at', 'messagerecipient.user', 'messagerecipient.folder')
			->whereRaw("`message`.`author` = ".$this->user->id." OR `messagerecipient`.`user` = ".$this->user->id." ORDER BY `message`.`created_at` DESC")
            ->paginate(5);
			
		foreach($messages as $message){
			if($message->author == $this->user->id){
				$message->authorname = "Me";
			}else{
				$authornamequery = User::find($message->author);
				$message->authorname = ($user->hasRole('patient')) ? "Dr.".$authornamequery->firstname." ".$authornamequery->lastname : $authornamequery->firstname." ".$authornamequery->lastname;
			}
		}
			
		$countarray = array();
		$countarray[1] = array();
		$countarray[2] = array();
		$countarray[3] = array();
		$countarray[4] = array();
		$countarray[5] = array();
		$countarray[6] = array();
		
		foreach($messages as $key => $message){
			if($message->author == $this->user->id){
				$message->folder = "Sent";
			}
			switch($message->folder){
				case "Inbox":
					$status = 1;
					break;
				case "Important":
					$status = 2;
					break;
				case "Reference":
					$status = 3;
					break;
				case "Sent":
					$status = 4;
					break;
				case "Draft":
					$status = 5;
					break;
				case "Trash":
					$status = 6;
					break;
			}
			$countarray[$status][] = $key;
		}
		
		if($user->hasRole('patient')){
			$providerTools = new ProviderController();
			$patient = $providerTools->getpatient($this->user->id);
			if(array_key_exists("primaryrelationship", $patient)){
				$provideruserobject = Provider::find($patient['primaryrelationship']);
				$provideruser = $provideruserobject->user;
				$providerpatient = ProviderPatient::where('user', '=', intval($this->user->id))->first();
	
				if(is_object($this->history)){
					$userhistory = $this->history->fetchhistory($this->user->id);
				}else{
					$userhistory = array();
				}
				
				if(is_object($this->symptoms)){
					$usersymptoms = $this->symptoms->patientsymptoms($this->user->id);
				}else{
					$usersymptoms = array();
				}
				
			}else{
				return redirect()->route('profile')->with('message', 'Currently no EHR entry, please ensure your provider has imported your record and you have associated with an existing provider or provider network.');
			}
			
			$role = "patient";
			
			return view('case.mailbox', compact('title','role','messages','component','user','avatar','patient','countarray','userhistory','usersymptoms','provideruser','authorname'));
		}
		
		if($user->hasRole('provider')){
			$providerpatients = \DB::table('providerpatients')->join('users', 'providerpatients.user', '=', 'users.id')->select('providerpatients.user','users.firstname','users.lastname')->where('provider', '=', intval($this->provider->id))->orderBy('lastname', 'ASC')->get();
				
				foreach($providerpatients as $patient){
					$userhistory = $this->history->fetchhistory($patient->user);
					
					foreach($userhistory as $key => $historyitem){
						if($historyitem['fileid'] != NULL){
							$userhistory[$key]['file'] = Fileentry::where('id', '=', $historyitem['fileid'])->firstOrFail();
						}
						$userhistory[$key]['userid'] = $patient->user;
					}
				}	
			$role = "provider";
			return view('case.mailbox', compact('title','role','patient','messages','providerpatients','component','user','avatar','countarray','userhistory'));
		}
		
	}
	
	public function lifestyle(Request $request)
	{
		$title = "Body & Lifestyle";
		$component = "lifestyle";
		$user = $this->user;
		$avatar = $this->avatar;
		$patient = null;
		
		//FOR DEVICES
		$stepvaltodate = null;
		$todayssteps = null;
		
		if($this->userispatient){
			$patient = $this->patient;
		}
		
		$profile = Profile::where('user', '=', $this->user->id)->first();
		
		$connectdevice = new DeviceController;
		$devicedata = $connectdevice->connectdevice('fitbit');
		
		$stepvaltodate = 0;
		foreach($devicedata['steps'] as $steps){
			$stepvaltodate = $stepvaltodate + intval($steps->value);
			if($steps->dateTime == date('Y-m-d')){
				$todayssteps = $steps->value;
			}
		}
		
		/* foreach ($results as $item) {
			echo $item['volumeInfo']['title'], "<br /> \n";
		} */
		return view('case.lifestyle', compact('title','component','user','profile','avatar','patient','remotedata','stepvaltodate','todayssteps'));
	}
	
	public function nutrition($patientid = null)
	{
		$title = "Nutrition Management";
		$component = "nutrition";
		$user = $this->user;
		$avatar = $this->avatar;
		$patient = null;
		$usermeals = null;
		
		
		$recommendedNutritionLevels = \DB::connection('mongodb')->collection('recommendednutrients')->get();
		if($user->hasRole('provider')){
			if(!is_null($patientid)){
				return redirect('/provider/get/patient/'.$this->user->id);
			}else{
				return redirect('/profile');
			}
		}
			
		if($this->userispatient){
			$recommendednutrientsresult = RecommendedNutrients::firstOrNew(array('user' => $this->user->id));
			
			$recommendednutrients = (!is_null($recommendednutrientsresult->nutrients)) ? unserialize(gzuncompress(base64_decode($recommendednutrientsresult->nutrients))) : array();
			$providerTools = new ProviderController();
			$patient = $providerTools->getpatient($this->user->id);
		}elseif(!$this->userispatient && !$user->hasRole('provider')){
			$recommendednutrientsresult = RecommendedNutrients::firstOrNew(array('user' => $this->user->id));
			$recommendednutrients = (!is_null($recommendednutrientsresult->nutrients)) ? unserialize(gzuncompress(base64_decode($recommendednutrientsresult->nutrients))) : array();
		}
		
		return view('case.nutrition', compact('title','component','user','avatar','patient','usermeals','recommendedNutritionLevels','recommendednutrients'));
	}
	
	public function oneonone()
	{
		$title = "Direct Consultation";
		$component = "oneonone";
		$user = $this->user;
		$avatar = $this->avatar;
		$patient = null;
		
		if($this->userispatient){
			$patient = $this->patient;
			$consults = Consults::whereRaw('patient = '.intval($this->user->id)." AND date >= CURDATE( ) AND date <= (CURDATE( ) + INTERVAL 6 DAY)")->get();
			$provider = intval($this->userpatientquery->provider);
			//$providerdetail = Practice::where('owner','=',$provider)->first()->toArray();
			$providerdetail = \DB::table('practice')->join('providers','practice.owner','=','providers.id')->join('users','providers.user','=','users.id')->where('practice.owner','=',$provider)->first();
			
		}else{
			return redirect()->route('profile')->with('message', 'Currently no EHR entry, please ensure your provider has imported your record and you have associated with an existing provider or provider network.');	
		}
		
		return view('case.oneonone', compact('title','component','user','avatar','patient','consults','providerdetail'));
	}
	
	public function sendmessage(Request $request){
		if($request['user'] != null && $request['cto'] != null && $request['cmessage'] != null && $request['csubject'] != null){
			
			if($request['emailpatient'] == '1'){
				$practicedetail = Practice::where('owner','=',$this->user->id)->first()->toArray();
				$mail = new \PHPMailer(true); // defaults to using php "mail()"
				$body = "Practice Portal Message<br />------------------------------------<br />".$request['cmessage']."<br />------------------------------------<br />END MESSAGE";
				$mail->From = "no-reply@hippokrates.io";
				$mail->FromName = "My CDS - Powered By Hippokrates";

				$mail->AddReplyTo("no-reply@hippokrates.io","OnDemand - Powered By Hippokrates");
				
				$mail->AddAddress($practicedetail['email']);     
				$mail->Subject    = "Practice Portal Message RE: ".$request['csubject'];       
				$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test

				$mail->MsgHTML($body);
				
				$mail->Send();
				
				//$messagedata = $this->sanitize(array('to'=>$this->user->id, 'subject' => "PM: ".$request['csubject'], 'message' => $request['cmessage']));
			}
			
			
			$messagedata = $this->sanitize(array('to'=>$request['cto'], 'subject' => $request['csubject'], 'message' => $request['cmessage']));

			if($request['copyoffice'] == '1'){
				$providertools = new ProviderController;
				$providertools->notifyofficeofnote($this->user->id, $messagedata['message']);
			}
			
			$message = new Messages();
			$messagerecipient = new MessageRecipient();
			
			$message->author = $this->user->id;
			$message->subject = $messagedata['subject'];
			$message->message = $messagedata['message'];
			
			$message->save();
			
			$messagerecipient->message = $message->id;
			$messagerecipient->user = $messagedata['to'];
			$messagerecipient->folder = "Inbox";
			
			$messagerecipient->save();
			
			return 1;
		}else{
			return 0;
		}
	}
	
	public function sendreply(Request $request){
		if($request['user'] != null && $request['rmessage'] != null && $request['mid'] != null){
			$messagedata = $this->sanitize(array('message' => $request['rmessage']));
			
			$replytomessage = Messages::find($request['mid']);
			
			$message = new Messages();
			$messagerecipient = new MessageRecipient();
			
			$message->author = $this->user->id;
			$message->subject = "Re: ".$replytomessage->subject;
			$message->message = $messagedata['message'];
			$message->parent = $replytomessage->id;
			$message->save();
			
			$messagerecipient->message = $message->id;
			$messagerecipient->user = $replytomessage->author;
			$messagerecipient->folder = "Inbox";
			
			$messagerecipient->save();
			
			return 1;
		}else{
			return 0;
		}
	}
	
	public function getmessage(Request $request){
		if($request['mid'] != null && intval($request['recipient']) > 0){
			$message = \DB::table('message')
			->join('messagerecipient', 'message.id', '=', 'messagerecipient.message')
			->select('message.id','message.author','message.subject','message.message','messagerecipient.user')
			->where('message.id','=',intval($request['mid']))
			->first();
			
			$messagedata['author'] = $message->author;
			$messagedata['subject'] = $message->subject;
			$messagedata['message'] = $message->message;
			$messagedata['recipient'] = $message->user;
			
			return $messagedata;
		}else{
			
			return 0;
		}
	}
	
	public function messageupdatefolder(Request $request){
		if($request['msid'] != null && intval($request['user']) > 0){
			$message = \DB::table('message')
			->join('messagerecipient', 'message.id', '=', 'messagerecipient.message')
			->select('message.id','message.author','message.subject','message.message','messagerecipient.user')
			->where('message.id','=',intval($request['msid']))
			->where('messagerecipient.user','=',intval($this->user->id))
			->first();
			
			
			$messagerecipient = MessageRecipient::where('message','=',$message->id)->where('user','=',intval($this->user->id))->first();
			
			$status = $request['status'];
			switch($status){
				case 1:
					$messagerecipient->folder = "Inbox";
					break;
				case 2:
					$messagerecipient->folder = "Important";
					break;
				case 3:
					$messagerecipient->folder = "Reference";
					break;
				case 4:
					$messagerecipient->folder = "Sent";
					break;
				case 5:
					$messagerecipient->folder = "Draft";
					break;
				case 6:
					$messagerecipient->folder = "Trash";
					break;
				default:
					$messagerecipient->folder = "Inbox";
					break;
			}
			
			if($messagerecipient->save()){
				return $status;
			}else{
				return 0;
			}
		}else{
			
			return 0;
		}
	}
	
	public function goals()
	{
		$title = "Goals & Achievements";
		$component = "goals";
		$user = $this->user;
		$avatar = $this->avatar;
		$patient = null;
				
		if($this->userispatient){
			$provider = new ProviderController();
			$patient = $provider->getpatient($user->id);	
		}else{
			return redirect()->route('profile')->with('message', 'Currently no EHR entry, please ensure your provider has imported your record and you have associated with an existing provider or provider network.');
		}
		
		return view('case.goals', compact('title','component','user','avatar','patient'));
	}
	
	public function analyze($patientid)
	{
		$title = "Case Analysis";
		$component = "analyze";
		$user = $this->user;
		$avatar = $this->avatar;
		$patient = null;
		
		if(!($user->hasRole('provider'))){
			return redirect('/profile');
		}else{
			$provider = new ProviderController();
			$patient = $provider->getpatient($patientid);		
			$patient['patientid'] = $patientid;
		}
		
		return view('case.analyze', compact('title','component','user','avatar','patient','hstnum'));
	}
	
	private function fixdate($dob){
		if(strlen($dob) > 5){
			if(substr($dob,4) < date('y')){
				$year = '20'.substr($dob,4);
			}else{
				$year = '19'.substr($dob,4);
			}
			$dob = date('m/d/Y', strtotime(substr($dob,0,2).'/'.substr($dob,2,2).'/'.$year));
		}else{
			if(substr($dob,3) < date('y')){
				$year = '20'.substr($dob,3);
			}else{
				$year = '19'.substr($dob,3);
			}
			$dob = date('m/d/Y', strtotime('0'.substr($dob,0,1).'/'.substr($dob,1,2).'/'.$year));
		}
		
		return $dob;
	}

	public function sanitize($input)
    {
		$cleaned = array();
        foreach($input as $key => $field){
			$cleaned[$key] = filter_var($field, FILTER_SANITIZE_STRING);			
		}
        return $cleaned;   
    }
	
	public function updateuserpass(Request $request){
		$donesignal = 0;
		
		$email = $request['email'];
		$newpass = $request['pass'];
		
		$user = User::where('email','=',$email)->first();
		
		if($newpass != null && $newpass != '' && strlen($newpass) > 5){
			$user->password = \Hash::make( $newpass );
		}
		
		if($email != null && $email != '' && strlen($email) > 5){
			$user->email = $email;
		}
		
		if($request->input('isprovider') != NULL){
			$provider = Provider::where('user','=',$this->user->id)->first();
			$practice = Practice::where('owner', '=', $provider->id)->first();
			$practicedata = array();
			foreach($request->input() as $key => $posted){
				if($posted != null && $posted != '' && $key != '_token' && $key != 'email' && $key != 'isprovider' && $key != 'pass'){
					$practicedata[$key] = $posted; 
				}
			}
			$sanitized = $this->sanitize($practicedata);
			
			//address, city, state, zip, phone, fax, supportemail, supportphone
			if(array_key_exists('address', $sanitized)){
				($sanitized['address'] != '' && $sanitized['address'] != null) ? $practice->address = $sanitized['address'] : "" ;
			}
			if(array_key_exists('city', $sanitized)){
				($sanitized['city'] != '' && $sanitized['city'] != null) ? $practice->city = $sanitized['city'] : "" ;
			}
			if(array_key_exists('state', $sanitized)){
				($sanitized['state'] != '' && $sanitized['state'] != null) ? $practice->state = $sanitized['state'] : "" ;
			}
			if(array_key_exists('zip', $sanitized)){
				($sanitized['zip'] != '' && $sanitized['zip'] != null) ? $practice->zip = $sanitized['zip'] : "" ;
			}
			if(array_key_exists('phone', $sanitized)){
				($sanitized['phone'] != '' && $sanitized['phone'] != null) ? $practice->phone = $sanitized['phone'] : "" ;
			}
			if(array_key_exists('fax', $sanitized)){
				($sanitized['fax'] != '' && $sanitized['fax'] != null) ? $practice->fax = $sanitized['fax'] : "" ;
			}
			if(array_key_exists('supportemail', $sanitized)){
				($sanitized['supportemail'] != '' && $sanitized['supportemail'] != null) ? $practice->supportemail = $sanitized['supportemail'] : "" ;
			}
			if(array_key_exists('supportphone', $sanitized)){
				($sanitized['supportphone'] != '' && $sanitized['supportphone'] != null) ? $practice->supportphone = $sanitized['supportphone'] : "" ;
			}
			if(array_key_exists('documentpassword', $sanitized)){
				($sanitized['documentpassword'] != '' && $sanitized['documentpassword'] != null) ? $practice->password = $sanitized['documentpassword'] : "" ;
			}
			$practice->save();
		}
		
		if($user->save()){
			return array('status' => 1);
		}else{
			return array('status' => 0);
		}
	}
	
}