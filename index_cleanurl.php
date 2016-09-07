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

require_once "class.cleanurl.php";
$cleanUrlConf = include_once "cleanurl.conf.php";


if (!is_array($cleanUrlConf) || !count($cleanUrlConf)) {

    die("Please, give a config file for cleanurl module");

}

if (!extension_loaded ( "xml" )) {

    die("CleanUrl needs extension xml to be loaded");

}


function getOption($name, $default)
{

    global $cleanUrlConf;
    return isset($cleanUrlConf[$name]) ? $cleanUrlConf[$name] : $default;

}


$basePath =  getOption("BasePath", "/");
$serverBaseUrl = getOption("ServerBaseUrl", "http://" . $_SERVER["SERVER_NAME"] . "/");
$canonicalBaseUrl = getOption("CanonicalBaseUrl", "http://" . $_SERVER["SERVER_NAME"] . "/");
$getVarsMap = getOption("GetVarsMap", array());
$nameOfCacheFile = getOption("CacheFileName", "cu_cache.php");
$options = array(
    "ReplaceUnderscoreWithSlash" => false,
    "ReplaceToBase" => getOption("ReplaceToBase", array("index.php")),
    "IndexFile" => getOption("IndexFile", "index.php"),
    "FileExt" => getOption("FileExt", ".php"),
    "UseCache" => getOption("UseCache", true),
    "CreateCanonical" => getOption("CreateCanonical", true),
    "debug" => getOption("Debug", false),
);
$file404 =  getOption("404File", "404.php");


$oCleanUrl = new CleanUrl($nameOfCacheFile,
    $basePath,
    $serverBaseUrl,
    $canonicalBaseUrl,
    $getVarsMap,
    $options);

$rc = $oCleanUrl->OnRequest();

if ($rc === FALSE) {
    if (is_file($file404) && is_readable($file404)) {

        include $file404;
    }
}


?>