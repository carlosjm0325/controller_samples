<?php namespace Hipokrates\Http\Controllers;

use Hipokrates\Http\Requests;
use Hipokrates\Http\Requests\SymptomsRequest;
use Hipokrates\Http\Controllers\Controller;
use Hipokrates\PatientSymptoms;

use Illuminate\Http\Request;

class SymptomsController extends Controller {

	private $user;

	public function __construct()
	{
		$this->user = \Auth::user();
		
	}

	public function store(SymptomsRequest $request)
    {
		$createarray = 	array(
							'userid' => $this->user->id
						);
		
		$patientsymptoms = PatientSymptoms::firstOrCreate(
			$createarray
		);
		
		if($patientsymptoms->save())
		{
			$patientsymptomsupdate = \DB::table('patientsymptoms')->where('userid', $this->user->id)->update(['symptoms' => $request->get('complaints')]);
	
			if($patientsymptomsupdate){
				return response()->json('Symptoms Updated!');
			}else{
				return response()->json('Could Not Update Symptoms! Please try again...');
			}
		}else{
			return response()->json('Could Not Update Symptoms! Please try again...');
		}
	}
	
	public function patientsymptoms($patientId)
	{
		$createarray = 	array(
							'userid' => $patientId
						);
		
		$patientsymptoms = PatientSymptoms::firstOrCreate(
			$createarray
		);
		
		return $patientsymptoms->symptoms;
	}
	
	public function fetchlist(Request $request)
	{
		$symptoms = \DB::connection('mongodb')->collection('symptoms')->where('symptom', 'regex', new \MongoRegex("/.*".$request->input('q')."/i"))->get(array('symptom'));
		$x = 0;
		foreach($symptoms as $symptom)
		{
			$response[$x]['data'] = $symptom['symptom'];
			$x++;
		}
		
		return $response[0]['data'];
	}

}
