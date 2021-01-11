<?php 
namespace Hipokrates\Http\Controllers;

use Hipokrates\Http\Requests;
use Hipokrates\Http\Controllers\Controller;
use Hipokrates\AIEntry;
use Hipokrates\Http\Requests\AIInjectRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class DatagenController extends Controller {

	public function __construct(Request $request){
		$this->middleware('auth');
		$this->user = \Auth::user();
	}
	
	public function index(){
		$title = "Hipokrates AI Datagen";
		$component = "datagen";
		$user = $this->user;
		$patient = null;
		
		$existingDatasets = array();
		
		foreach(AIEntry::get() as $entry){
			array_push($existingDatasets, array('section' => $entry['section'], 'name' => $entry['original_filename'], 'fileid' => $entry['id']));
		}
		
		return view('ai.datagen', compact('title','component','user','patient', 'existingDatasets'));
	}	
	
	public function submitintel(AIInjectRequest $request){
		$datastored = false;
		
		$datafile = $request->file('datafile');
		$section = $request->get('section');
		$description = $request->get('description');
		$token = $request->get('_token');
		
		if(!is_null($datafile))
		{
			$extension = $datafile->getClientOriginalExtension();
			$entry = new AIEntry();
			$entry->section = $section;
			$entry->description = $description;
			$entry->mime = $datafile->getClientMimeType();
			$entry->original_filename = $datafile->getClientOriginalName();
			$entry->filename = $this->user->id ."-". $section ."-". $datafile->getFilename().'.'.$extension;
			$filename = $entry->filename;
			Storage::disk('local')->put($filename,  File::get($datafile));
			$entry->save();
			$savedentry = AIEntry::where('filename', '=', $this->user->id ."-". $section ."-". $datafile->getFilename().'.'.$extension)->first();
			if($savedentry){
				$datastored = true;
			}
		}
		
		if($datastored){
			$filelocation = getcwd()."/../storage/app/".$filename;
			$this->injectintel($filelocation, $section);
			return array('data' => 'Successfully Imported Dataset');
		}else{
			return array('data' => '0');
		}
	}
	
	private function injectintel($filelocation, $section){
		$section = $section;
		
		$file = $filelocation;
		$lines = file($file);
				
		$i = 0;
		foreach($lines as $line){
			$returnspider = $this->runthrough(trim($line));
			foreach($returnspider as $content){
				$grabandstuffresults = $this->grabandstuff($content, 'drugdbsectioncontent', $section, false);
				
				//var_dump($grabandstuffresults);
				$encodedtext = base64_encode(gzcompress(serialize($grabandstuffresults['content'])));
				
				\DB::connection('mongodb')->collection('ai')->insert(array('cat'=> $section, 'subcat'=> $grabandstuffresults['subcat'], 'pagegroup' => $grabandstuffresults['group'],'text'=> $encodedtext));
				
				//TO DECOMPRESS (READABLE)
				//unserialize(gzuncompress(base64_decode($encodedtext)));
			}
			//TEST THROUGH ONE SOURCE
			//DIE;
		}
		//RELEASE THE BEAST
		//DIE;
	}

	private function grabandstuff($content, $class, $section, $readable) {
		$doc = new \DOMDocument();
		@$doc->loadHTML($content);
		
		$classname = $class;
		$finder = new \DomXPath($doc);
		$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
		$tmp_dom = new \DOMDocument(); 
		$new_dom = new \DOMDocument(); 
		
		//MEDSCAPE CRAWL
			$pattern = "/<h1>(.*?)<\/h1>/";
			preg_match_all($pattern, $content, $matches);
			
			$subcattext = $matches[1][0];
				
			$pattern2 = "/&nbsp(.*?)/";	
			$replacement = '';
			$subcattext = strtolower(strstr($subcattext, '&nbsp;', true));
			$subcattextgroup = substr(strstr($matches[1][0], '&nbsp;'), 6);
			$subcatcheck = strstr($subcattextgroup, ' & ', true);
			if($subcatcheck){
				$subcattextgroup = $subcatcheck;
			}

			$subcattextgroup = strtolower($subcattextgroup);
			
			$finder2 = new \DomXPath($doc);
			$classname="refsection_content";
			$nodesref = $finder2->query("//*[contains(@class, '$classname')]");
			
			foreach($nodesref as $noderef){
				$prev = $finder2->evaluate('./preceding-sibling::*[1]', $noderef);
				
				if($prev->item(0)->textContent != NULL){
					$nodecontent = $new_dom->importNode($noderef, true);
					$new_dom->appendChild($nodecontent);
				}
				$contenthtml = trim($new_dom->saveHTML());
				$contenthtml = preg_replace(array('"<a href(.*?)>"', '"</a>"'), array('',''), $contenthtml);
				$contenthtml = preg_replace(array('"<sup>(.*?)</sup>"'), array(''), $contenthtml);
				$contenthtml = preg_replace(array('"<div(.*?)>"', '"</div>"'), array('',' '), $contenthtml);
				$contenthtml = preg_replace(array('"<h(.*?)>(.*?)</h(.*?)>"'), array(' '), $contenthtml);
				$contenthtml = preg_replace(array('"<script(.*?)>(.*?)</script>"'), array(''), $contenthtml);
				$contenthtml = preg_replace(array('"<ul(.*?)>"', '"</ul>"'), array(' ',' '), $contenthtml);
				$contenthtml = preg_replace(array('"<p(.*?)>"', '"</p>"'), array('',' '), $contenthtml);
				$contenthtml = preg_replace(array('"<li(.*?)>"', '"</li>"'), array('',', '), $contenthtml);
							
				$classarray['subcat']=trim($subcattext);
				$classarray['group']=trim($subcattextgroup);
				$classarray['content']=trim($contenthtml);
				  while ($new_dom->hasChildNodes()) {
					$new_dom->removeChild($new_dom->firstChild);
				  }
			}
		//END MEDSCAPE CRAWL
		
		return $classarray;
		
	}

	private function runthrough($url){
		$curl = new Curl();
		
		$medscape = array(
			'clinical',
			'workup',
			'treatment',
			//'differential',
		);

		foreach($medscape as $section){
			$spidered[] = $curl->get($url."-".$section."#showall");
		}
		
		return $spidered;
	}

}

class Curl
{       

	public $cookieJar = "";

	public function __construct($cookieJarFile = 'cookies.txt') {
		$this->cookieJar = $cookieJarFile;
	}

	function setup()
	{


		$header = array();
		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
		$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$header[] =  "Cache-Control: max-age=0";
		$header[] =  "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: "; // browsers keep this blank.


		curl_setopt($this->curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.8.1.7) Gecko/20070914 Firefox/2.0.0.7');
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($this->curl,CURLOPT_COOKIEJAR, $this->cookieJar); 
		curl_setopt($this->curl,CURLOPT_COOKIEFILE, $this->cookieJar);
		curl_setopt($this->curl,CURLOPT_AUTOREFERER, true);
		curl_setopt($this->curl,CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->curl,CURLOPT_RETURNTRANSFER, true);  
	}


	function get($url)
	{ 
		$this->curl = curl_init($url);
		$this->setup();

		return $this->request();
	}

	function getAll($reg,$str)
	{
		preg_match_all($reg,$str,$matches);
		return $matches[1];
	}

	function postForm($url, $fields, $referer='')
	{
		$this->curl = curl_init($url);
		$this->setup();
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_REFERER, $referer);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $fields);
		return $this->request();
	}

	function getInfo($info)
	{
		$info = ($info == 'lasturl') ? curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL) : curl_getinfo($this->curl, $info);
		return $info;
	}

	function request()
	{
		return curl_exec($this->curl);
	}
}