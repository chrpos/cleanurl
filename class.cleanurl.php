<?php
/** Overlay clean-url module for CMS-less, php based websites
 *
 * @file      class.cleanurl.php
 * @author    Christian Poms (cp@csoft-it.at)
 * @url       www.csoft-it.at
 * @date      Apr 8, 2015
 *
 * @see README.txt for installation instructions
 *
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Dieses Programm ist Freie Software: Sie können es unter den Bedingungen
 * der GNU General Public License, wie von der Free Software Foundation,
 * Version 3 der Lizenz oder (nach Ihrer Wahl) jeder neueren
 * veröffentlichten Version, weiterverbreiten und/oder modifizieren.
 * 
 * Dieses Programm wird in der Hoffnung, dass es nützlich sein wird, aber
 * OHNE JEDE GEWÄHRLEISTUNG, bereitgestellt; sogar ohne die implizite
 * Gewährleistung der MARKTFÄHIGKEIT oder EIGNUNG FÜR EINEN BESTIMMTEN ZWECK.
 * Siehe die GNU General Public License für weitere Details.
 *
 * Sie sollten eine Kopie der GNU General Public License zusammen mit diesem
 * Programm erhalten haben. Wenn nicht, siehe <http://www.gnu.org/licenses/>.
 *
 */

  
/** CleanUrl
 *
 * ### Prerequisites ###
 * 
 *  - HTML must use UTF-8 encoding
 *  - Add "<meta http-equiv="content-type" content="text/html; charset=utf-8">" to header tag of all your HTML pages
 *  - The cleanurl module will search for the following line to generate the verbose uri from the content:
 *       <meta name="cleanurl" data-details="Text that will be processed to a valid URI"  />
 *    If this special meta tag is not found, the module will use the content of the header <title> tags.
 *    Properly set values will make your uris nicely.
 *  - Make sure, the webserver has write access to the cache file (configured via CleanUrl constructor)
 *  - Add a .htaccess file into your web root directory, use the .htaccess.tmpl as a template (which will work quite out of the box)
 *
 *
 * ### Workflow ###
 * 
 *   Analyse Request (*.php or clean)
 *      If (*.php):
 *          CacheLookup()
 *               No Hit: CreateCache(phpFile)
 *          301 Redirect to clean url
 *      Else (clean):
 *          TranslateToPHPFile(cleanUrl)
 *          RenderPHPFile(phpFile);
 *
 *   CreateCache():
 *   Parse PHP file for <meta cleanurl>/<title> tags.
 *   Analyze GET parameters and pick the ones, configured in mGETMap.
 *   Generate URL in the following way:
 *      [NameOfPhpFileWithoutExtension]/GETvars/title/
 *   Save this url in cache file.
 *
 *   RenderPHPFile():
 *   Parse PHP file for internal <a> tags.
 *   Replace all occurrences of internal links with clean urls:
 *        CacheLookup()
 *              No Hit: CreateCache(phpFile)
 *
 *
 * 
 * ### Example Usage ###
 *
 * @code
 *  require_once class.cleanurl.php
 *
 *  $cleanUrlObj = new CleanUrl($nameOfCacheFile, $basePath, $baseHref, $baseHrefCanonical, $getMap, $options);
 *  $cleanUrlObj->OnRequest();
 *
 * @endcode
 *
 */
class CleanUrl
{
	private $mBaseDir;
	private $mCacheFileName;
	private $mCacheEntries;
	private $mBaseHref;
	private $mBaseHrefCanonical;
	private $mGETMap;
	private $mOptions;
	const VERSION = "0.5";


	function __construct($cacheFileName,
						 $baseDir = "/",
						 $baseHref = "",
						 $baseHrefCanonical = "",
						 $GETMap = array(),
						 $options = array()) {
	
		$this->mBaseDir = $baseDir;
		$this->mCacheFileName = $cacheFileName;
		$this->mCacheEntries = array();
		$this->mBaseHref = $baseHref;
		$this->mBaseHrefCanonical = $baseHrefCanonical;
		$this->mGETMap = is_array($GETMap) ? $GETMap : array();

		$this->mOptions = array(
			"ReplaceUnderscoreWithSlash" => false,
			"IndexFile" => "index.php",
			"FileExt" => ".php",
			"ReplaceToBase" => array("index.php"),
			"UseCache" => true,
			"debug" => false,
		);
		$this->mOptions = array_merge($this->mOptions, $options);

		$this->InitCache();
	}



	
	function __destruct() {
	
		$this->SaveCache();
	
	}
	


	public function OnRequest() {

		$requestUri = $_SERVER["REQUEST_URI"];
		$requestUriStripped = $this->NoQueryString($requestUri);
		$this->DBG(__FUNCTION__, "Start cleanurl mdoule for page: " . $requestUri);
				
		if (pathinfo($requestUriStripped, PATHINFO_EXTENSION) == ltrim($this->mOptions["FileExt"], ".")) {

			/* this is a direct link -> send a 301 redirect to clean url variant */
			$this->DBG(__FUNCTION__, "Processing direct request");
			$cleanUrl = $this->GetCleanUrl($requestUri);

			if ($cleanUrl !== FALSE) {
			
				header("Location: " . $this->mBaseDir . $cleanUrl, TRUE, 301);
				return true;
			
			}
		} else {
		
			if (!strlen(pathinfo($requestUriStripped, PATHINFO_EXTENSION))) {

				/* this is a clean url -> translate to php file and render it */
				$this->DBG(__FUNCTION__, "Processing a clean url");

				if (!strlen($requestUriStripped) || $requestUriStripped == $this->mBaseDir) {   /* @todo support missing slashes */
				
					/* set index.php, if request uri is empty */
					$this->DBG(__FUNCTION__, "Set request to index");
					$phpFile = $this->mOptions["IndexFile"];
					
				} else {
				
					$phpFile = $this->TranslateToPhpFile($requestUri);

					/* fill global GET array with values from cleanurl */
					$_GET = array_merge($_GET, $this->GetVarsFromCleanUri($requestUri));
					$this->DBG(__FUNCTION__, "Content of global _GET array:");
					$this->DBG(__FUNCTION__, print_r($_GET, true));
				}

				$this->ResetServer_PHP_SELF($phpFile);
				$this->DBG(__FUNCTION__, "PHP_SELF set to : " . $_SERVER["PHP_SELF"]);
				
				if ($phpFile != FALSE) {
				
					$output = $this->RenderPhpFile($phpFile);

					if ($output != FALSE) {
					
						echo $output;
						return true;
					}
				}
			} else {
			
				/* this is a file name - do not process any further, but deliver */
				/* -> this is done in .htaccess */
				$this->TriggerError(__FUNCTION__, "A non-php file has been requested through clean url. (" . $requestUri . ") - abort processing");
			}
		
		}

		header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
		return FALSE;
	}
	
	


	private function InitCache() {
		
		if ($this->mOptions["UseCache"]) {

			if (is_file($this->mCacheFileName)) {

				if (is_readable($this->mCacheFileName) && is_writeable($this->mCacheFileName)) {

					include $this->mCacheFileName;

					if (isset($CacheObj)) {

						$this->mCacheEntries = $CacheObj;
						$this->DBG(__FUNCTION__, "Cache successfully initialized");
					}
				} else {

					$this->TriggerError(__FUNCTION__, "Cache file not read/writeable.");
				}
			} else {

				$this->TriggerError("No cache file found");
			}
		} else {

			$this->TriggerError(__FUNCTION__, "Cache is disabled. Please, turn on cache for better performance");
		}
		
		/* if file not exists, mCacheEntries is simply an empty array */
	}




	private function SaveCache() {

		if ($this->mOptions["UseCache"]) {

			$f = fopen($this->mCacheFileName, "w", TRUE);

			if ($f !== FALSE) {

				ftruncate($f, 0);
				fwrite($f, "<?php\n\n\$CacheObj = unserialize('" . serialize($this->mCacheEntries) . "');\n\n?>");
				fclose($f);
				$this->DBG(__FUNCTION__, "Cache written");

			} else {

				$this->TriggerError(__FUNCTION__, "Could not write cache file.");
			}
		}
	}



	
	private function CreateCleanUrl($requestUri) {

		$phpFileName = $this->GetFilenameFromUri($requestUri);
		$this->DBG(__FUNCTION__, "Filename to parse: " . $phpFileName);

		if (is_file($phpFileName) && is_readable($phpFileName)) {

			$titleRaw = "";
			$titlePart = "";
			$getPart = "";
			$cleanUri = "";

			/* adapt global GET array with values from requestUri to parse */
			$_GET = array_merge($_GET, $this->GetVarsFromRequestUri($requestUri));
			$htmlSource = $this->BufferedInclude($phpFileName);

			$doc = new DOMDocument();
			// set error level to not output dom errors
			// @see http://stackoverflow.com/questions/1685277/warning-domdocumentloadhtml-htmlparseentityref-expecting-in-entity
			$internalErrors = libxml_use_internal_errors(true);
			$doc->loadHTML($htmlSource);
			// restore error level
			libxml_use_internal_errors($internalErrors);

			/* First, search for the special meta tag, named "cleanurl" */
			$meta = $doc->getElementsByTagName('meta');

			if ($meta->length) {

				foreach ($meta as $node) {

					if ($node->hasAttribute("name") && $node->getAttribute("name") == "cleanurl") {

						$titleRaw = $node->getAttribute("data-details");
						$this->DBG(__FUNCTION__, "Found meta cleanurl: " . $titleRaw);
						break;
					}
				}
			}

			if (!strlen($titleRaw)) {

				/* Fallback: use <title> content for clean uri source */
				$title = $doc->getElementsByTagName('title');
				if ($title->length) {

					$element = $title->item(0);
					$titleRaw = $element->nodeValue;
					$this->DBG(__FUNCTION__, "Found title tag: " . $titleRaw);
				}
			}

			if (strlen($titleRaw)) {

				$titlePart = $this->ConvertToUrlString($titleRaw);
				if (strlen($titlePart)) {

					$titlePart .= "/";
				}
			} else {

				/* No title info found - clean url will be short */
				/* If it will work, depends on your local configuration */
				$this->DBG(__FUNCTION__, "WARNING: Cleanurl without any title information");
			}

			/* Bring GET vars in place, if configured */
			if (count($this->mGETMap)) {

				foreach ($this->mGETMap as $var => $default) {

					if (isset($_GET[$var])) {

						$getPart .= $_GET[$var] . "/";

					} else {

						$getPart .= $default . "/";
					}
				}
			}

			/* @todo ReplaceUnderscoreWithSlash is deprecated and should not be set to true! */
			if ($this->mOptions["ReplaceUnderscoreWithSlash"] == true) {

				$cleanUri = str_replace("_", "/", basename($phpFileName, ".php")) . "/" . $getPart . $titlePart;

			} else {

				$cleanUri = basename($phpFileName, ".php") . "/" . $getPart . $titlePart;
			}

			if (in_array($cleanUri, $this->mOptions["ReplaceToBase"])) {

				$this->DBG(__FUNCTION__, "Generated uri will be reset to base: " . $cleanUri);
				$cleanUri = "";
			}

			return $cleanUri;

		} else {

			$this->TriggerError(__FUNCTION__, "Requested URL not found.");
		}

		return FALSE;
	}




	private function RenderPhpFile($phpFileName) {

		if (is_file($phpFileName) && is_readable($phpFileName)) {

			$htmlSource = $this->BufferedInclude($phpFileName);
			/* setting the encoding here is pretty useless. needs to be set inside the html header tag */
			$doc = new DOMDocument("1.0", "utf-8");
			$doc->encoding = "utf-8";

			if (!$this->mOptions["debug"]) {
				$internalErrors = libxml_use_internal_errors(true);
			}

			$doc->loadHTML($htmlSource);

			if (!$this->mOptions["debug"]) {
				libxml_use_internal_errors($internalErrors);
			}
			$this->InsertBaseHref($doc);
			$this->ReplaceCanonical($doc, $this->GetCleanUrl($phpFileName));
			$this->ReplaceAlternate($doc, $phpFileName, $this->GetCleanUrl($phpFileName));

			/* remove POST vars, when rendering linked pages */
			$_POST = array();

			$aNodes = $doc->getElementsByTagName('a');

			foreach ($aNodes as $node) {

				/* is internal link? */
				$attribs = $node->attributes;

				for ($i = 0; $i < $attribs->length; ++$i) {

					if ($attribs->item($i)->name == "href") {

						$fileLink = $attribs->item($i)->value;
						$fileLink = html_entity_decode($fileLink);

						if (strstr($fileLink, "http://") === FALSE) {

							/* this is an internal link, in case it is a .php file -> replace it to clean url */
							if (pathinfo($this->NoQueryString($fileLink), PATHINFO_EXTENSION) == ltrim($this->mOptions["FileExt"], ".")) {

								$this->DBG(__FUNCTION__, "Going to process embedded link: " . $fileLink);
								$node->setAttribute("href", $this->GetCleanUrl($fileLink));
							}
						}

						break;
					}
				}
			}

			return $doc->saveHTML();

		} else {

			$this->TriggerError(__FUNCTION__, "Php file to render not found. (" . $phpFileName . ")");
		}

		return FALSE;
	}




	private function NoQueryString($requestUri)
	{
		if (($p = strpos($requestUri, "?")) !== FALSE) {
			
			return substr($requestUri, 0, $p);
			
		}
		
		return $requestUri;
	}



	
	private function GetCleanUrl($requestUri) {
	
		if (!isset($this->mCacheEntries[$requestUri])) {
		
			$cleanUrl = $this->CreateCleanUrl($requestUri);

			if ($cleanUrl !== FALSE) {

				$this->mCacheEntries[$requestUri] = $cleanUrl;
				return $cleanUrl;

			} else {

				$this->TriggerError(__FUNCTION__, "Creation of clean url failed");
			}
		} else {

			$this->DBG(__FUNCTION__, "Cache hit for " . $requestUri);
			return $this->mCacheEntries[$requestUri];
		}

		return FALSE;
	}
	
	

	
	private function TranslateToPhpFile($cleanUrl) {
		
		$phpFile = "";
		
		if (strlen($cleanUrl)) {

			$cleanUrl = $this->GetFilenameFromUri($cleanUrl);

			/* @todo ReplaceUnderscoreWithSlash is deprecated and should not be set to true! */
			if ($this->mOptions["ReplaceUnderscoreWithSlash"]) {

				$cleanUrl = str_replace("/", "_", $cleanUrl);
			}

			$p = strlen($cleanUrl);
			$phpFile = $cleanUrl;
			$this->DBG(__FUNCTION__, "Probing filename: " . $phpFile . $this->mOptions["FileExt"]);

			while (!is_file($phpFile . $this->mOptions["FileExt"]) && $p !== FALSE) {

				$p = strrpos($cleanUrl, "/", (strlen($cleanUrl) - $p)  * (-1));

				if ($p !== FALSE) {

					$phpFile = substr($cleanUrl, 0, $p);
					$p -= 1;
				}

				$this->DBG(__FUNCTION__, "Probing filename: " . $phpFile . $this->mOptions["FileExt"]);
			}

			$this->DBG(__FUNCTION__, "Filename returning ($p): " . $phpFile);
			return $phpFile . $this->mOptions["FileExt"];
			
		} else {
		
			$this->TriggerError(__FUNCTION__, "REQUEST_URI is empty.");
		}
		
		return FALSE;		
	}
	

	

	private function BufferedInclude($phpFileName) {
		
		if (is_file($phpFileName) && is_readable($phpFileName)) {
		
			ob_start();
			include $phpFileName;
			return ob_get_clean();			
		
		} else {

			$this->TriggerError(__FUNCTION__, "File not found: " . $phpFileName);
		}
		
		return FALSE;
	}

	
	
	/* thanks to http://stackoverflow.com/questions/4783802/converting-string-into-web-safe-uri */
	private function ConvertToUrlString($str) {
		
		$delimiter = "-";
		setlocale(LC_ALL, 'en_US.UTF8');
		$clean = preg_replace(array('/Ä/', '/Ö/', '/Ü/', '/ä/', '/ö/', '/ü/'), array('Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue'), $str);
		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
		$clean = strtolower(trim($clean, '-'));
		$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

		return $clean;
	}
	


	private function InsertBaseHref($doc)
	{
		assert($doc, "doc error");
		$baseHref = $doc->createElement("base");
		$baseHref->setAttribute("href", $this->mBaseHref);
		$node = $doc->getElementsByTagName('head')->item(0);
		//$node->appendChild($baseHref);

		if ($node) {

			$node->insertBefore($baseHref, $node->firstChild);

		} else {

			$this->DBG(__FUNCTION__, "head node not found");

		}
		
		return TRUE;
	}
	


	private function ReplaceCanonical($doc, $cleanUrl)
	{

		assert($doc, "param error");
		$linkNodes = $doc->getElementsByTagName('link');

		foreach ($linkNodes as $node) {
		
			/* is <link rel="canonical" >? */
			$attribs = $node->attributes;

			if (($attribNode = $attribs->getNamedItem('rel')) !== NULL && $attribNode->nodeValue == 'canonical') {
				
				$node->setAttribute('href', $this->mBaseHrefCanonical . $cleanUrl);
				return TRUE;
			}
		}
		
		return FALSE;
	}




	private function ReplaceAlternate($doc, $phpFileName, $cleanUrl)
	{

		assert($doc, "param error");
		$linkNodes = $doc->getElementsByTagName('link');

		foreach ($linkNodes as $node) {
		
			/* is <link rel="canonical" >? */
			$attribs = $node->attributes;

			if (($attribNode = $attribs->getNamedItem('rel')) !== NULL && $attribNode->nodeValue == 'alternate') {
				
				if (($attribHref = $attribs->getNamedItem('href')) !== NULL && ($fileName = strrchr($attribHref->nodeValue, "/"))) {
					
					if (ltrim($fileName, "/") == $phpFileName) {
						
						$node->setAttribute('href', $this->mBaseHrefCanonical . $cleanUrl);
						return TRUE;
					}
				}
			}
		}
		
		return FALSE;
	}




	private function GetFilenameFromUri($requestUri)
	{

		$fileName = trim($this->NoQueryString($requestUri), "/");
		$baseDir = ltrim($this->mBaseDir, "/");

		if (strpos($fileName, $baseDir) === 0) {

			return substr($fileName, strlen($baseDir));
		}

		return $fileName;
	}




	private function GetVarsFromRequestUri($requestUri)
	{
		$aGet = array();

		if (($p = strpos($requestUri, "?")) !== FALSE) {

			$sGetvars = substr($requestUri, $p + 1);
			$aGetvars = explode("&", $sGetvars);

			if (is_array($aGetvars) && count($aGetvars)) {

				foreach ($aGetvars as $keyvalue) {

					$pair = explode("=", $keyvalue);

					if (count($pair)) {

						$aGet += array($pair[0] => isset($pair[1]) ? $pair[1] : "");
					}
				}
			}
		}

		return $aGet;
	}




	private function GetVarsFromCleanUri($requestUri)
	{
		$aGet = array();
		$requestFile = $this->GetFilenameFromUri($this->NoQueryString($requestUri));
		$phpFileName = $this->TranslateToPhpFile($requestUri);
		$requestParams = substr($requestFile, strlen($phpFileName) - strlen($this->mOptions["FileExt"]) + 1);
		$requestParams = explode("/", $requestParams);
		$i = 0;

		foreach ($this->mGETMap as $key => $default) {

			//if ($i < count($requestParams) - 1) {   // count() -1, because last element is the title
			if ($i < count($requestParams)) {         // ignore, if title does not exist

				$aGet[$key] = $requestParams[$i];
				$i++;

			} else {

				break;
			}
		}

		return $aGet;
	}




	private function ResetServer_PHP_SELF($phpFileName)
	{
		$_SERVER["PHP_SELF"] = str_replace($this->GetFilenameFromUri($_SERVER["PHP_SELF"]),
									$phpFileName,
									$_SERVER["PHP_SELF"]);
	}



	private function DBG($module, $str)
	{
		if ($this->mOptions["debug"] == true) {

			file_put_contents('php://stderr', sprintf("[%s] %-20s:  %s\n", date(DATE_RFC822), $module, $str));
		}
	}



	private function TriggerError($module, $str)
	{
		$this->DBG($module, "[ERROR]: " . $str);
		trigger_error($str, E_USER_NOTICE);
	}
}
 
 
 
 
 
 

?>