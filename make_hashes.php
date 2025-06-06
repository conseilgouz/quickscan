<?php

define('DS', DIRECTORY_SEPARATOR);
define('FILE', str_replace('/', DIRECTORY_SEPARATOR, basename((string) $_SERVER['SCRIPT_FILENAME'])));

// No max execution time
@ini_set('max_execution_time', '0');

// No limit
@ini_set('memory_limit', '-1');

// Remove time limit; avoid 504 HTTP errors
@set_time_limit(0);

/**
 * Remove recursively folders
 * (f.i. rrmdir(__DIR__/hashes/joomla/2.5.27) will kill the full tree below the specified folder).
 *
 * @param type $folder
 * @param type $killroot       If true, the folder himself will be removed.
 *                             rrmdir(__DIR__/hashes/joomla/2.5.27, true) ==> remove folder 2.5.27 too and not only his children
 * @param type $arrIgnoreFiles
 *
 * @return boolean
 */
function rrmdir($folder, $killroot = false, $arrIgnoreFiles = ['.htaccess', 'index.html'])
{
    $return = true;

    if (is_dir($folder)) {
        $dir_handle = opendir($folder);
    }

    if (!$dir_handle) {
        return false;
    }

    while ($file = readdir($dir_handle)) {
        if ('.' != $file && '..' != $file) {
            if (!is_dir($folder . '/' . $file)) {
                if (!in_array($file, $arrIgnoreFiles)) {
                    @unlink($folder . '/' . $file);
                }
            } else {
                rrmdir($folder . '/' . $file, true, $arrIgnoreFiles);
            }
        }
    }

    closedir($dir_handle);

    if (true === $killroot) {
        @rmdir($folder);
    }

    return $return;
}

function json_val($string)
{
    // decode the JSON data
    $result = json_decode($string);

    // switch and check possible JSON errors
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            // JSON is valid // No error has occurred
            $error = '';

            break;
        case JSON_ERROR_DEPTH:
            $error = 'The maximum stack depth has been exceeded.';

            break;
        case JSON_ERROR_STATE_MISMATCH:
            $error = 'Invalid or malformed JSON.';

            break;
        case JSON_ERROR_CTRL_CHAR:
            $error = 'Control character error, possibly incorrectly encoded.';

            break;
        case JSON_ERROR_SYNTAX:
            $error = 'Syntax error, malformed JSON.';

            break;
        case JSON_ERROR_UTF8:
            $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';

            break;
        case JSON_ERROR_RECURSION:
            $error = 'One or more recursive references in the value to be encoded.';

            break;
        case JSON_ERROR_INF_OR_NAN:
            $error = 'One or more NAN or INF values in the value to be encoded.';

            break;
        case JSON_ERROR_UNSUPPORTED_TYPE:
            $error = 'A value of a type that cannot be encoded was given.';

            break;
        default:
            $error = 'Unknown JSON error occurred.';

            break;
    }

    if ('' !== $error) {
        // throw the Exception or exit // or whatever :)
        exit($error);
    }

    // everything is OK
    return $result;
}

function recurceZip($path)
{
    $all_path = [];
    $files = null;
    if (file_exists($path) && is_dir($path)) {
        $files = array_diff(scandir($path), array('..', '.'));
    }
    if ($files) {
        foreach ($files as $key => $value) {
            $file_info = pathinfo($value);
            if (isset($file_info['extension']) && $file_info['extension'] === 'zip') {
                $file_name = $path . '/' . $file_info['basename'];
                $zip = new ZipArchive();
                if ($zip->open($file_name) === true) {
                    if (is_dir($path . '/' . $file_info['filename'])) { // zip déjà traité
                        echo '<p class="text-danger">'.$file_name.' already unzipped.</p>';
                        $zip->close();
                        unlink($file_name);
                        continue;// ignore
                    }
                    $all_path[] = $path . '/' . $file_info['filename'];
                    $zip->extractTo($path . '/' . $file_info['filename']);
                    $zip->close();
                    unlink($file_name);
                } else {
                    echo '<p class="text-danger">Error unzipping '.$file_name.'.</p>';
                }
            }
            if (!isset($file_info['extension'])) {
                $all_path[] = $path . '/' . $file_info['filename'];
            }
        }
    }
    foreach ($all_path as $k => $v) {
        $path = $v;
        recurceZip($path);
    }
    return;
}

function makeJSON($folder, $filename)
{
    // Make a JSON file to retrieve all MD5 hashes
    $arrFiles = [];

    $dir   = new RecursiveDirectoryIterator($folder . DS, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($files as $name => $object) {
        $arrFiles[] = $name;
    }

    if (count($arrFiles) > 0) {
        if (file_exists($filename)) {
            $json = json_decode(file_get_contents($filename), true);
            if (0 == count($json)) {
                echo '<h1>Problem reading file ' . $filename . '.  Error reading json</h1>';
                die();
            }

            // Keep the filesize before any changes
            clearstatcache();
            $fsize = filesize($filename);
        } else {
            $json = [];
            $fsize = 0;
        }

        foreach ($arrFiles as $file) {
            // Skippe readme.txt file in the root folder
            if ($file == $folder . DS . 'readme.txt') {
                continue;
            }
            // ignore installation directory
            if (str_starts_with($file, $folder . DS .'installation')) {
                continue;
            }
            $relativefName = str_replace($folder, '', $file);

            // If the filename is called aesecure_quickscan.whitelist.json or
            // just whitelist.json, open it and process every entries of it
            if (in_array($file, [$folder . DS . 'aesecure_quickscan.whitelist.json', $folder . DS . 'whitelist.json'])) {
                $white = json_decode(file_get_contents($file), true);
                foreach ($white as $md5 => $fname) {
                    if (!(isset($json[$md5]))) {
                        $json[$md5] = $relativefName;
                    }
                }
            } else {
                // It's a normal file, calculate the md5 of the file itself
                $md5 = @md5_file($file);

                if (false !== $md5) {
                    if (!(isset($json[$md5]))) {
                        $json[$md5] = '1';
                    }

                    // Get the hash for both UNIX and WINDOWS system
                    $md5 = md5(str_replace("\r\n", "\n", file_get_contents($file)));
                    if (!(isset($json[$md5]))) {
                        $json[$md5] = '1';
                    }

                    $md5 = md5(str_replace("\n", "\r\n", file_get_contents($file)));
                    if (!(isset($json[$md5]))) {
                        $json[$md5] = '1';
                    }
                }
            }

            // and kill the file, no more needed
            if (in_array(basename($filename), ['blacklist.json', 'other.json'])) {
                @unlink($file);
            }
        }

        // Security, check that we've at least one file
        if (count($json) > 0) {
            if (json_val(json_encode($json))) {
                asort($json);

                if (file_exists($filename)) {
                    copy($filename, $filename . '.backup');
                }

                // Output the file with all hashes
                $fp = fopen($filename, 'w');
                fwrite($fp, json_encode($json));
                fclose($fp);
                unset($fp);

                clearstatcache();

                if (filesize($filename) < $fsize) {
                    unlink($filename);
                    rename($filename . '.backup', $filename);
                    echo '<h1 class=‘text-danger’>There has been a problem with updating the ' . $filename . '.  The file prior to the changes has been restored.</h1>';
                } else {
                    // Ok, backup file isn't needed, everything was ok.
                    if (file_exists($filename . '.backup')) {
                        unlink($filename . '.backup');
                    }
                }
            }
        }
    }

    if (in_array(basename($filename), ['blacklist.json', 'other.json'])) {
        rrmdir($folder = $folder, $killroot = false, $arrIgnoreFiles = ['readme.txt']);
    }
}
function toDo()
{
    $arrFolder = [
        ['joomla'      => 'J!'],
        ['wordpress'   => 'WP'],
        ['J!extensions'   => ''],
        ['WPextensions'   => '']
    ];
    $out = '';

    foreach ($arrFolder as $arr) {
        $Folder = key($arr);      // For instance "joomla"; name of the CMS; name of the subfolder
        $prefix = $arr[$Folder];  // For instance "J!"; prefix to use for naming files (f.i. "J!3.4.1" for the Joomla file for version 3.4.1)

        $hashFolder = __DIR__ . DS . 'hashes';
        if (!is_dir($hashFolder)) {
            @mkdir($hashFolder);
        }

        // Check if we have a folder called __DIR__/hashes/joomla (or wordpress or ...)
        $hashFolder = __DIR__ . DS . 'hashes' . DS . $Folder;

        if (!is_dir($hashFolder)) {
            @mkdir($hashFolder);
        }

        $files = array_diff(scandir($hashFolder), array('..', '.'));
        foreach ($files as $key => $value) {
            $file_info = pathinfo($value);
            if (isset($file_info['extension']) && $file_info['extension'] === 'zip') {
                $out .= '<p class="text-info">'.$hashFolder.DS.$file_info['filename'].' to be processed.</p>';
            }
        }
    }
    return $out;
}

function process()
{
    if (!isset($_GET['task'])) {
        return "";
    }

    $arrFolder = [
        ['joomla'      => 'J!'],
        ['wordpress'   => 'WP'],
        ['J!extensions'   => ''],
        ['WPextensions'   => '']
    ];

    $out = '';

    foreach ($arrFolder as $arr) {
        $Folder = key($arr);      // For instance "joomla"; name of the CMS; name of the subfolder
        $prefix = $arr[$Folder];  // For instance "J!"; prefix to use for naming files (f.i. "J!3.4.1" for the Joomla file for version 3.4.1)

        // Check if we have a folder called __DIR__/hashes/joomla (or wordpress or ...)
        $hashFolder = __DIR__ . DS . 'hashes' . DS . $Folder;

        if (!is_dir($hashFolder)) {
            @mkdir($hashFolder);
        }

        if (is_dir($hashFolder)) {

            recurceZip($hashFolder); // unzip all zip files

            if (!in_array($Folder, ['blacklist', 'other'])) {
                // This is a folder like "Joomla" : one json file by subfolder since a subfolder contain a specific version of that CMS
                $subfolders = array_filter(glob($hashFolder . DS . '*'), 'is_dir');
            } else {
                // Only one single file for everything present in the "other" folder
                $subfolders = [$Folder];
            }

            if (count($subfolders) > 0) {
                $tmp = '';

                foreach ($subfolders as $folder) {
                    // The file with the hashes will be something like hashes/joomla/J!2.5.27.json
                    if (!in_array($Folder, ['blacklist', 'other'])) {
                        $filename = $hashFolder . DS . $prefix . str_replace($hashFolder . DS, '', $folder) . '.json';
                    } else {
                        $filename = dirname(dirname($hashFolder)) . DS . $Folder . '.json';
                        $folder  = $hashFolder;
                    }

                    if ((!file_exists($filename)) || (in_array($Folder, ['blacklist', 'other']))) {
                        makeJSON($folder, $filename);
                        $out .= '<p class="text-success">'.$filename.' has been created.</p>';
                    } else {
                        $out .= '<p class="text-danger">'.$filename.' has been ignored.</p>';
                    }
                    if (is_dir($folder)) { // unzipped folder ?
                        rrmdir($folder, true, []);
                    }
                }

                if ('' != $tmp) {
                    $out .= '<h3>Scan ' . $hashFolder . '</h3>';
                    $out .= '<ol>' . $tmp . '</ol>';
                }
            }
        } else {
            $out .=  '<p>Folder ' . $hashFolder . ' not found</p>';
        }
    }
    if ($out == "") {
        $out = '<p>Nothing to do.....</p>';
    }
    return $out.'<hr/>';
}
$showInfo = process();
if ($showInfo) {
    $res = json_encode(['result' => $showInfo]);
    exit($res);
}
$todo = toDo();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html  xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr">
  <head>
      <meta charset="utf-8" />
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <meta http-equiv="X-UA-Compatible" content="IE=edge" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet" />
      <title>aeSecure - Make hash</title>
  </head>
  <body class="container">
    <h1>aeSecure QuickScan - Make hash</h1>
    <p>To add a new version of a CMS or an extension, you need to</p>
    <ol>
        <li>create the folder corresponding to this CMS (hashes/joomla, hashes/wordpress, hashes/J!extensions, hashes/WPextensions)</li>
        <li>copy zip files in the directory.
        <li>note : in case of Joomla or WP, rename zip file so it just contains its version number (e.g. hashes/joomla/5.2.0.zip)</li>
    </ol>
    <p>The JSON files will be generated automatically.</p>
    <hr/>
    <div id="todo"><?php echo $todo;?></div>
	<button type="button" id="btnSubmit" class="btn btn-warning" 
			data-toggle="popover" data-placement="bottom" data-html="true"
            data-content="<strong class='text-danger'>GO</strong>">
			<span class="glyphicon glyphicon-trash">&nbsp;</span>
			GO
			</button>
      <div id="result"><?php echo $showInfo;?></div>
   </body>
   <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js"></script>
   <script defer="defer">
    document.addEventListener('DOMContentLoaded', function() {
        button = document.getElementById('btnSubmit');
        button.addEventListener('click',function (e) {
                document.getElementById("btnSubmit").disabled = true;             
                e.stopImmediatePropagation();
                var xhr = new XMLHttpRequest();
                xhr.responseType = 'text';
                url = "?task=submit";
                xhr.open('GET', url);
                xhr.onload = function() {
                    if (xhr.readyState === xhr.DONE) {
                        if (this.status === 200) {
                            var info = JSON.parse(this.response);
                            if (info.result) {
                                result = document.getElementById('result');
                                result.innerHTML = result.innerHTML + info.result;
                                todo = document.getElementById('todo');
                                todo.innerHTML = "";
                                document.getElementById("btnSubmit").style.display = "none"; 
                            }
                        }  else {
                            ocument.getElementById("btnSubmit").disabled = false;
                        }
                    }
                };
                xhr.send();
		    })
        });
	</script>
</html>
