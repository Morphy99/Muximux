<?php
/*
* DO NOT CHANGE THIS FILE!
*/
ini_set("log_errors", 1);
ini_set('max_execution_time', 300);
error_reporting(E_ERROR);
$errorLogPath = "muximux.log";
ini_set("error_log", $errorLogPath);
date_default_timezone_set((date_default_timezone_get() ? date_default_timezone_get() : "America/Chicago"));
defined("CONFIG") ? null : define('CONFIG', 'settings.ini.php');
defined("CONFIGEXAMPLE") ? null : define('CONFIGEXAMPLE', 'settings.ini.php-example');
require dirname(__FILE__) . '/vendor/autoload.php';
require_once 'iconindex.php';


// Check if this is our first run and do some things.
if(!file_exists(CONFIG)){
    copy(CONFIGEXAMPLE, CONFIG);
    checksetSHA();
}

$config = new Config_Lite(CONFIG);
$_SESSION['secret'] = $config->get("general","secret",false);
if (!$_SESSION['secret']) {
	$_SESSION['secret'] = createSecret();
}

if (isset($_POST['function']) && isset($_POST['secret'])) {
	if ($_POST['secret'] == file_get_contents(SECRET)) write_ini();
} 

// Check if we can open a file.
function openFile($file, $mode) {
    if ((file_exists($file) && (!is_writable(dirname($file)) || !is_writable($file))) || !is_writable(dirname($file))) { // If file exists, check both file and directory writeable, else check that the directory is writeable.
        $message = 'Either the file '. $file .' and/or it\'s parent directory is not writable by the PHP process. Check the permissions & ownership and try again.';
	if (PHP_SHLIB_SUFFIX === "so") { //Check for POSIX systems.
            $message .= "  Current permission mode of ". $file. " is " .decoct(fileperms($file) & 0777);
            $message .= "  Current owner of " . $file . " is ". posix_getpwuid(fileowner($file))['name'];
            $message .= "  Refer to the README on instructions how to change permissions on the aforementioned files.";
        } else if (PHP_SHLIB_SUFFIX === "dll") {
            $message .= "  Detected Windows system, refer to guides on how to set appropriate permissions."; //Can't get fileowner in a trivial manner.
        }
	    write_log($message,'E');
	    setStatus($message);
        exit;
    }
    return fopen($file, $mode);
}

// Create a secret for communication to the server
function createSecret() {
	$config = new Config_Lite('settings.ini.php');
	write_log("Creating server secret.");
    $text = uniqid("muximux-", true);
    $config->set("general","secret",$text);
    saveConfig($config);
    return $text;
}

// Save our settings on submit
function write_ini()
{
    $config = new Config_Lite(CONFIG);
    $oldHash = getPassHash();
    $oldBranch = getBranch();
    $terminate = false;
    $authentication = $config->getBool('general','authentication',false);
	
    // Double check that a username post didn't sneak through
    foreach ($_POST as $parameter => $value) {
    	$splitParameter = explode('_-_', $parameter);
	if ($splitParameter[1] == "username") {
	    die;
	}
    }
	unlink(CONFIG);
    $config = new Config_Lite(CONFIG);
    foreach ($_POST as $parameter => $value) {
        $splitParameter = explode('_-_', $parameter);
        $value = (($value == "on") ? "true" : $value );
		switch ($splitParameter[1]) {
			case "password":
				if ($value != $oldHash) {
					write_log('Successfully updated password.','I');
					$value = password_hash($value, PASSWORD_BCRYPT);
					$terminate = true;
				}
			break;
			case "authentication":
			    if ($value != $authentication) {
					$terminate = true;
				}
			break;
			case "theme":
			    $value = strtolower($value);
			break;
			case "branch":
				if ($value != $oldBranch) {
					$config->set('settings','branch_changed',true);
					$config->set('settings','sha','00');
				} else {
					$config->set('settings','branch_changed',false);
				}
			break;
		}
        
        if ($parameter !== 'function' && $parameter !== 'secret')$config->set($splitParameter[0], $splitParameter[1], $value);
    }
    // save object to file
    saveConfig($config);
    if ($terminate) {
        session_start();
        session_destroy();
    }
}

// Parse settings.php and create the Muximux elements
function parse_ini()
{
	mapIcons('css/font-muximux.css','.muximux-');
    $config = new Config_Lite(CONFIG);
	checksetSHA();
    fetchBranches(false);
    $branchArray = getBranches();
    $branchList = "";
    $css = getThemeFile();
    $tabColorEnabled = $config->getBool('general', 'tabcolor', false);
    $updatePopup = $config->getBool('general', 'updatepopup', false);
    $mobileOverride = $config->getBool('general', 'mobileoverride', false);
    $cssColor = ((parseCSS($css,'.colorgrab','color') != false) ? parseCSS($css,'.colorgrab','color') : '#FFFFFF');
    $themeColor = $config->get('general','color',$cssColor);
    $autoHide = $config->getBool('general', 'autohide', false);
    $splashScreen = $config->getBool('general', 'splashscreen', false);
    $userName = $config->get('general', 'userNameInput', 'admin');
    $passHash = $config->get('general', 'password', 'Muximux');
    $authentication = $config->getBool('general', 'authentication', false);
    $rss = $config->getBool('general', 'rss', false);
	$rssUrl = $config->get('general','rssUrl','https://www.wired.com/feed/');
    $myBranch = getBranch();
	
    foreach ($branchArray as $branchName => $shaSum ) {
        $branchList .= "
                                <option value='".$branchName."' ".(($myBranch == $branchName) ? 'selected' : '' ).">". $branchName ."</option>";
    }
    $title = $config->get('general', 'title', 'Muximux - Application Management Console');
    $pageOutput = "<form class='form-inline'>
	
						<div class='applicationContainer card generalContainer' style='cursor:default;'>
                        <h3>General</h3>
                        <div class='row justify-content-center'>
	                        <div class='appDiv form-group'>
	                            <label for='titleInput' class='col-xs-6 col-sm-4 col-lg-3 control-label left-label'>Main Title: </label>
	                            <div class='appInput col-xs-6 col-sm-8 col-md-4 col-lg-8'>
									<input id='titleInput' class='form-control form-control-sm settingInput' general_-_value' name='general_-_title' value='" . $title . "'>
								</div>
	                        </div>
	                        <div class='appDiv form-group'>
								<label for='branch'  class='col-xs-6 col-sm-4 col-lg-3 control-label left-label'>Git branch: </label>
								<div class='appInput col-xs-6 col-sm-2 col-md-4 col-lg-6'>
									<select id='branch' class='form-control form-control-sm settingInput' name='general_-_branch'>".
										$branchList ."
									</select>
								</div>
	                        </div>
							<div class='appDiv form-group'>
								<label for='theme' class='col-xs-6 col-sm-4 col-lg-3 control-label left-label'>Theme: </label>
								<div class='appInput col-xs-6 col-sm-2 col-md-4 col-lg-7'>
									<select id='theme' class='form-control form-control-sm general_-_value settingInput' name='general_-_theme'>".
										listThemes() . "
									</select>
								</div>
							</div>
							<div class='appDiv form-group'>
								<label for='general_-_color' class='control-label left-label col-xs-6 col-sm-4 col-lg-3'>Color:</label>
								<div class='appInput col-xs-6 col-sm-8 col-md-4 col-lg-3'>
									<input id='general_-_default' class='appsColor col-xs-6 col-sm-2 generalColor general_-_color settingInput' value='" .$themeColor."' name='general_-_color'>
								</div>
	                        </div>
	                        </div>
	                        <div class='row justify-content-center'>
	                        	<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                            <label for='updatepopupCheckbox' data-toggle='tooltip' data-placement='top' title='Enable this to receive notifications of updates to Muximux.' class='btn btn-primary btn-sm btn-block". ($updatePopup ? ' active' : '') ."'>
										<input id='updatepopupCheckbox' name='general_-_updatepopup' type='checkbox'". ($updatePopup ? ' checked' : '') .">Update Alerts
									</label>
		                        </div>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                            <label for='splashscreenCheckbox' data-toggle='tooltip' data-placement='top' title='Show the splash screen when Muximux loads.' class='btn btn-primary btn-sm btn-block". ($splashScreen ? ' active' : '') ."'>
										<input id='splashscreenCheckbox' class='settingInput' name='general_-_splashscreen' type='checkbox'".($splashScreen ? ' checked' : '') .">Splash Screen
									</label>
		                        </div>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                            <label for='mobileoverrideCheckbox' class='btn btn-primary btn-sm btn-block". ($mobileOverride ? ' active' : '') ."'>
		                                <input id='mobileoverrideCheckbox class='settingInput' name='general_-_mobileoverride' type='checkbox'".($mobileOverride ? ' checked' : '').">Mobile Override
									</label>
		                        </div>
		                        <div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                            <label for='tabcolor' class='btn btn-primary btn-sm btn-block". ($tabColorEnabled ? ' active' : '') ."'>
										<input id='tabcolor' class='settingInput' name='general_-_tabcolor' type='checkbox'" . ($tabColorEnabled ? ' checked' : '').">App Colors
									</label>
		                        </div>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                            <label for='autohide' class='btn btn-primary btn-sm btn-block". ($autoHide ? ' active' : '') ."'>
										<input id='autohide' class='settingInput' name='general_-_autohide' type='checkbox'".($autoHide ? ' checked' : '').">Auto-hide Bar
									</label>
								</div>
		                        <div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                            <label for='authentication' class='btn btn-primary btn-sm btn-block". ($authentication ? ' active' : '') ."'>
		                            	<input id='authentication' class='settingInput' name='general_-_authentication' type='checkbox'".($authentication ? ' checked' : '').">Authentication
									</label>
		                        </div>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                        	<label for='rss' class='btn btn-primary btn-sm btn-block". ($rss ? ' active' : '') ."'>
		                        		<input id='rss' class='settingInput' name='general_-_rss' type='checkbox' ".($rss ? 'checked' : ''). ">Splash RSS
									</label>
								</div>
							</div>
							<div class='row justify-content-center'>
								<div class='userinput appDiv form-group rssUrlGroup". ($rss ? '' : ' hidden') ."'>
									<label for='rssUrl' class='col-xs-4 control-label right-label'>Feed Url: </label>
										<div class='col-xs-7 col-sm-5 col-md-3 col-lg-8'>
										<input id='rssUrl' class='form-control settingInput' general_-_value' name='general_-_rssUrl' value='" . $rssUrl . "'>
									</div>
								</div>
								<div class='inputdiv appDiv form-group". ($authentication ? '' : ' hidden') ."'>
									<div class='userinput appDiv form-group'>
										<label for='userNameInput' class='col-xs-4 control-label right-label'>Username: </label>
											<div class='col-xs-7 col-sm-5 col-md-8'>
											<input id='userNameInput' class='form-control settingInput' general_-_value' name='general_-_userNameInput' value='" . $userName . "'>
										</div>
									</div>
									<div class='userinput appDiv form-group'>
										<label for='password' class='col-xs-4 control-label right-label'>Password: </label>
										<div class='col-xs-7 col-sm-5 col-md-8'>
											<input id='password' type='password' autocomplete='new-password' class='form-control settingInput' general_-_value' name='general_-_password' value='" . $passHash . "'>
										</div>
									</div>
								</div>
							</div>
	                    </div>
                	
					<input type='hidden' class='settings_-_value' name='settings_-_enabled' value='true'>
					<input type='hidden' class='settings_-_value' name='settings_-_default' value='false'>
					<input type='hidden' class='settings_-_value' name='settings_-_name' value='Settings'>
					<input type='hidden' class='settings_-_value' name='settings_-_url' value='muximux.php'>
					<input type='hidden' class='settings_-_value' name='settings_-_landingpage' value='false'>
					<input type='hidden' class='settings_-_value' name='settings_-_icon' value='muximux-cog'>
					<input type='hidden' class='settings_-_value' name='settings_-_dd' value='true'>
					<div id='sortable'>";
    foreach ($config as $section => $name) {
        if (is_array($name) && $section != "settings" && $section != "general") {
            $name = $config->get($section, 'name', '');
            $url = $config->get($section, 'url', 'http://www.plex.com');
            $color = $config->get($section, 'color', '#000');
            $icon = $config->get($section, 'icon', 'muximux-play');
	        $icon = str_replace('fa-','muximux-',$icon);
	        $scale = $config->get($section, 'scale', 1) * 100;
            $default = $config->getBool($section, 'default', false);
            $enabled = $config->getBool($section, 'enabled', true);
            $landingpage = $config->getBool($section, 'landingpage', false);
            $dd = $config->getBool($section, 'dd', false);
            $pageOutput .= "
						<div class='applicationContainer card' id='" . $section . "'>
							<span class='bars fa muximux-bars'></span>
							<div class='row justify-content-center'>
							<div class='appDiv form-group'>
									<label for='" . $section . "_-_url' class='col-xs-6 col-sm-4 control-label left-label'>Name: </label>
									<div class='col-xs-6 col-sm-8 col-md-4 col-lg-8'>
										<input class='form-control form-control-sm settingInput" . $section . "_-_value' name='" . $section . "_-_name' value='" . $section . "'>
									</div>
								</div>
								<div class='appDiv form-group'>
									<label for='" . $section . "_-_url' class='col-xs-6 col-sm-4 control-label left-label'>URL: </label>
									<div class='col-xs-6 col-sm-8 col-md-4 col-lg-8'>
										<input class='form-control form-control-sm settingInput" . $section . "_-_value' name='" . $section . "_-_url' value='" . $url . "'>
									</div>
								</div>
								<div  class='appDiv form-group'>
									<label for='" . $section . "_-_scale' class='col-xs-6 col-sm-4 control-label col-form-label left-label'>Zoom: </label>
									<div class='slider-outer col-xs-6 col-md-4 col-lg-3'>
										<div class='slider'>
											<div class='ui-slider-handle custom-handle' value='".$scale."'></div>
										</div>
									</div>
								</div>
								<div class='appDiv form-group'>
									<label for='" . $section . "_-_icon' class='col-xs-6 col-sm-4 control-label left-label'>Icon: </label>
									<input data-bv-notempty='true' class='iconpicker settingInput' data-bv-notempty-message='You must pick a font' type='text' name='" . $section . "_-_icon' id='fip_1' value='".$icon."' />
								</div>
								<div class='appDiv form-group colorDiv'>
									<label for='" . $section . "_-_color' class='col-xs-6 col-sm-4 col-lg-3 control-label color-label left-label'>Color:</label>
									<div class='appInput col-xs-6 col-sm-8 col-md-4 col-lg-3'>
										<input id='" . $section . "_-_color' class='form-control form-control-sm appsColor settingInput" . $section . "_-_color' value='" . $color . "' name='" . $section . "_-_color'>
									</div>
								</div>
							</div>
							<div class='row justify-content-center'>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                        	<label for='" . $section . "_-_enabled' class='btn btn-primary btn-sm btn-block ".($enabled ? 'active' : ''). "'>
										<input type='checkbox' class='settingInput' id='" . $section . "_-_enabled' name='" . $section . "_-_enabled'".($enabled ? ' checked' : '') .">Enabled
									</label>
								</div>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                        	<label for='" . $section . "_-_landingpage' class='btn btn-primary btn-sm btn-block ".($landingpage ? 'active' : ''). "'>Splash Item
										<input type='checkbox' class='settingInput' id='" . $section . "_-_landingpage' name='" . $section . "_-_landingpage'".($landingpage ? ' checked' : '') .">
									</label>
								</div>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
		                        	<label for='" . $section . "_-_dd' class='btn btn-primary btn-sm btn-block".($dd ? 'active' : ''). "'>
										<input type='checkbox' class='settingInput' id='" . $section . "_-_dd' name='" . $section . "_-_dd'".($dd ? ' checked' : '') .">Dropdown
									</label>
								</div>
								<div class='col-xs-6 col-md-2 med-gutters btn-group' data-toggle='buttons'>
									<label class='btn btn-primary btn-sm".($default ? ' active' : ''). "' for='" . $section . "_-_default' >
										<input type='radio' class='settingInput' name='" . $section . "_-_default' id='" . $section . "_-_default' autocomplete='off' ".($default ? ' checked' : '') .">Default
									</label>
								</div>
							</div>
							<div class='row justify-content-center'>
								<button type='button' class='btn btn-danger btn-sm col-xs-6 col-md-2' value='Remove' id='remove-" . $section . "'>Remove</button>
							</div>
						</div>";
        }
    }
    $pageOutput .= "
                </div>
                <div class='text-center' style='margin-top: 15px;'>
                    <div class='btn-group' role='group' aria-label='Buttons'>
                        <a class='btn btn-primary btn-sm btn-block btn-md' id='addApplication'><span class='fa muximux-plus'></span> Add new</a>
                        <a class='btn btn-danger btn-md' id='removeAll'><span class='fa muximux-trash'></span> Remove all</a>
                    </div>
                </div>
            </form>";
    return $pageOutput;
}

// Generate our splash screen contents (basically a very little version of parse_ini).
function splashScreen() {
    $config = new Config_Lite(CONFIG);
    $css = getThemeFile();
    $cssColor = ((parseCSS($css,'.colorgrab','color') != false) ? parseCSS($css,'.colorgrab','color') : '#FFFFFF');
    $themeColor = $config->get('general','color',$cssColor);
    $tabColor = $config->getBool('general','tabcolor',false);
    
    $splash = "";
    
    foreach ($config as $keyname => $section) {
	$enabled = $config->getBool($keyname,'enabled',false);
	if (($keyname != "general") && ($keyname != "settings") && $enabled) {
    	$color = ($tabColor===true ? $section["color"] : $themeColor);
		$icon = $config->get($keyname,'icon','muximux-play');
		$icon = str_replace('fa-','muximux-',$icon);
			
			$splash .= "
									<div class='btnWrap'>
										<div class='card card-inverse splashBtn' data-content='" . $keyname . "'>
											<a class='panel-heading' data-title='" . $section["name"] . "'>
												<br><i class='fa fa-3x " . $icon . "' style='color:".$color."'></i><br>
												<p class='splashBtnTitle' style='color:#ddd'>".$section["name"]."</p>
											</a>
										</div>
									</div>";
		}
	}
	return $splash;
}

// Generate the contents of the log
function log_contents() {
    $out = '<ul>
                <div id="logContainer">
    ';
    $filename = 'muximux.log';
	$file = file($filename);
	$file = array_reverse($file);
	$lineOut = "";
	$concat = false;
	foreach($file as $line){
		$color = 'alert alert-info';
		$lvl = preg_match("/\[[^\]]*\]/", $line)[0];
		switch ($lvl) {
			case "ERROR":
				$color = 'alert alert-danger';
				break;
			case "DEBUG":
				$color = 'alert alert-warning';
				break;
			case "INFO":
				$color = 'alert alert-success';
				break;
		}

		if ($concat) {
			$out .='
                    <li class="logLine alert alert-info">'.
                        $lineOut.'
                    </li>';
		}
		$concat = false;
		

		if (! $concat) {
			$out .='
                    <li class="logLine '.$color.'">'.
                        $lineOut.'
                    </li>';
		}
        
    }
    $out .= '</div>
            </ul>
    ';
    return $out;
}


// Check if the user changes tracking branch, which will change the SHA and trigger an update notification
function checkBranchChanged() {
    $config = new Config_Lite(CONFIG);
    if ($config->getBool('settings', 'branch_changed', false)) {
        saveConfig($config);
	checksetSHA();
        return true;
    } else {
        return false;
    }
}

function appColors() {
	$config = new Config_Lite(CONFIG);
	$vars = $config;
	if (isset($vars['general'])) unset($vars['general']);
	if (isset($vars['settings'])) unset($vars['settings']);
	write_log("Vars: ".json_encode($vars));
	$tabColors = $config->getBool("general","tabcolor",true);
	$themeColor = $config->get("general","color","#31ac63");
	$colors = [];
	foreach ($vars as $title => $settings) {
		$colors[strtolower($title)] = $tabColors ? $settings['color'] : $themeColor;
	}
	return $colors;
}

// Quickie to get the theme from settings
function getTheme()
{
    $config = new Config_Lite(CONFIG);
    $item = $config->get('general', 'theme', 'classic');
	return strtolower($item);
}

function getThemeFile() {
	$config = new Config_Lite(CONFIG);
    $item = $config->get('general', 'theme', 'classic');
	if (file_exists('css/theme/'.$item.'.css')) {
		return 'css/theme/'.$item.'.css';
	} else {
		$item=strtolower($item);
	}
	if (file_exists('css/theme/'.$item.'.css')) {
		return 'css/theme/'.$item.'.css';
	} else {
		$item=ucfirst($item);
	}
	if (file_exists('css/theme/'.$item.'.css')) {
		return 'css/theme/'.$item.'.css';
	} else {
		$item='theme_default.css';
		return 'css/'.$item.'.css';
	}	
}

// List all available themes in directory
function listThemes() {
    $dir    = './css/theme';
    $themelist ="";
    $themes = scandir($dir);
    foreach($themes as $value){
        $splitName = explode('.', $value);
		if  (!empty($splitName[0])) {
			$name = ucfirst($splitName[0]);
            $themelist .="
                                <option value='".$name."' ".(($name == ucfirst(getTheme())) ? 'selected' : '').">".$name."</option>";
        }
    }
    return $themelist;
}

// Build the contents of our menu
function menuItems() {
    $config = new Config_Lite(CONFIG);
    $standardmenu = "<ul class='cd-tabs-navigation'>
                <nav>";
    $dropdownmenu = "
							<li>
								<a data-toggle='modal' data-target='#settingsModal' data-title='Settings'>
									<span class='fa muximux-cog'></span>Settings
								</a>
							</li>
							<li>
								<a id='logModalBtn' data-toggle='modal' data-target='#logModal' data-title='Log Viewer'>
									<span class='fa muximux-file-text-o'></span> Log
								</a>
							</li>";
    $int = 0;
	$autohide = $config->getBool('general', 'autohide', false);
	$dropdown = $config->getBool('general', 'enabledropdown', true);
	$mobileoverride = $config->getBool('general', 'mobileoverride', false);
	$authentication = $config->getBool('general', 'authentication', false);
    $drawerdiv = '';
	foreach ($config as $keyname => $section) {
        if (($keyname != "general") && ($keyname != "settings")) {
            $name = $config->get($keyname, 'name', '');
            $color = $config->get($keyname, 'color', '#000');
            $icon = $config->get($keyname, 'icon', 'muximux-play');
		    $icon = str_replace('fa-','muximux-',$icon);
		    $default = $config->getBool($keyname, 'default', false);
            $enabled = $config->getBool($keyname, 'enabled', false);
            $dd = $config->getBool($keyname, 'dd', false);
			        
			if ($enabled) {
				if ($dropdown) {
					if (!$dd) {
						$standardmenu .= "
							<li class='cd-tab' data-index='".$int."'>
								<a data-content='" . $keyname . "' data-title='" . $name . "' data-color='" . $color . "' class='".($default ? 'selected' : '')."'>
									<span class='fa " . $icon . " fa-lg'></span> " . $name . "
								</a>
							</li>";
						$int++;
					} else {
						$dropdownmenu .= "
							<li>
								<a data-content='" . $keyname . "' data-color='" . $color . "' data-title='" . $name . "'>
									<span class='fa " . $icon . "'></span> " . $name . "
								</a>
							</li>";
					}
				}
			}
		}	
	}
	$standardmenu .= "</nav>
            </ul>";
	$splashScreen = $config->getBool('general', 'splashscreen', false);
    
    $moButton = "
			<ul class='main-nav'>
                <li class='navbtn ".(($mobileoverride == "true") ? '' : 'hidden')."'>
                    <a id='override' title='Click this button to disable mobile scaling on tablets or other large-resolution devices.'>
                        <span class='fa muximux-mobile fa-lg'></span>
                    </a>
                </li>
                <li class='navbtn ".(($splashScreen == "true") ? '' : 'hidden')."'>
					<a id='showSplash' data-toggle='modal' data-target='#splashModal' data-title='Show Splash'>
                		<span class='fa muximux-home4 fa-lg'></span>
                    </a>
                </li>
                <li class='navbtn ".(($authentication == "true") ? '' : 'hidden')."'>
                    <a id='logout' title='Click this button to log out of Muximux.'>
                        <span class='fa muximux-sign-out fa-lg'></span>
                    </a>
                </li>
				<li class='navbtn'>
                    <a id='reload' title='Double click your app in the menu, or press this button to refresh the current app.'>
                        <span class='fa muximux-refresh fa-lg'></span>
                    </a>
                </li>
				
			
    ";


    $drawerdiv .= "<div class='cd-tabs-bar ".(($autohide == "true")? 'drawer' : '')."'>";

    if ($dropdown == "true") {
        $item = 
			$drawerdiv . 
            $moButton ."
                <li class='dd navbtn'>
                    <a id='hamburger'>
                        <span class='fa muximux-bars fa-lg'></span>
                    </a>
                    <ul class='drop-nav'>" .
                                $dropdownmenu ."
                    </ul>
                </li>
            </ul>".
            $standardmenu ."
                
        </div>
        ";
    } else {
        $item =  
			$drawerdiv . 
			$moButton .
			$standardmenu;
    }
    return $item;
}

// Quickie fetch the main title
function getTitle() {
    $config = new Config_Lite(CONFIG);
    $item = $config->get('general', 'title', 'Muximux - Application Management Console');
    return $item;

}
// Quickie fetch of the current selected branch
function getBranch() {
    $config = new Config_Lite(CONFIG);
    $branch = $config->get('general', 'branch', 'master');
    return $branch;
}

// Reads for "branches" from settings.  If not found, fetches list from github, saves, parses, and returns
function getBranches() {
    $config = new Config_Lite(CONFIG);
    $branches = [];
    $branches = $config->get('settings', 'branches',$branches);
    if ($branches == []) {
        fetchBranches(true);
    } else {
        $branches = $config->get('settings', 'branches');
    }
    return $branches;
}
// Fetch a list of branches from github, along with their current SHA
function fetchBranches($skip) {
    $config = new Config_Lite(CONFIG);
    $last = $config->get('settings', 'last_check', "0");
    if ((time() >= $last + 3600) || $skip) { // Check to make sure we haven't checked in an hour or so, to avoid making GitHub mad
        if (time() >= $last + 3600) {
            write_log('Refreshing branches from github - automatically triggered.');
        } else {
            write_log('Refreshing branches from github - manually triggered.');
        }
        $url = 'https://api.github.com/repos/mescon/Muximux/branches';
            $options = array(
          'http'=>array(
            'method'=>"GET",
            'header'=>"Accept-language: en\r\n" .
                      "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad
          )
        );

        $context = stream_context_create($options);
        $json = file_get_contents($url,false,$context);
        if ($json == false) {
            write_log('Error fetching JSON from Github.','E');
            $result = false;
        } else {
            $array = json_decode($json,true);
            $names = array();
            $shas = array();
            foreach ($array as $value) {
                foreach ($value as $key => $value2) {
                    if ($key == "name") {
                            array_push($names,$value2);
                    } else {
                        foreach ($value2 as $key2 => $value3) {
                            if ($key2 == "sha" ) {
                                array_push($shas,$value3);
                            }
                        }
                    }
                }
            }
            $outP = array_combine($names,$shas);
            $config ->set('settings','branches',$outP);
            $config ->set('settings','last_check',time());
            saveConfig($config);
            $result = true;
        }

    } else {
        $result = false;
    }
    return $result;

}

// Echos php information to the java console
function console_log( $data ) {
  $output  = "<script>console.log( 'PHP debugger: ";
  $output .= json_encode(print_r($data, true));
  $output .= "' );</script>";
  echo $output;
}

// This checks whether we have a SHA, and if not, whether we are using git or zip updates and stores
// the data accordingly
function checksetSHA() {
	$config = new Config_Lite('settings.ini.php');
	$shaOut = $branchOut = $git = false;
    $shaIn = $config->get('settings','sha',false);
	$branchIn = getBranch();
	$git = can_git();
	if ($git) {
		$shaOut = exec('git rev-parse HEAD');
		$branchOut = exec('git rev-parse --abbrev-ref HEAD');
	} else {
		if (! $shaIn) {
			$branchArray = getBranches();
			$branchOut = $branchIn();
			foreach ($branchArray as $branchName => $shaVal) {
				if ($branchName==$branchOut) {
					$shaOut = $shaVal;
				}
			}
		} 
	}
	$changed = false;
	if ($branchIn != $branchOut) {
		$config->set('settings', 'branch', $branchOut);
		$changed = true;
	}
	if ($shaIn != $shaOut) {
		$config->set('settings', 'sha', $shaOut);
		$changed = true;
	}
	if ($changed) {
		saveConfig($config);
        
	}
}

// Read SHA from settings and return it's value.
function getSHA() {
    $config = new Config_Lite(CONFIG);
    $item = $config->get('settings', 'sha', '00');
    return $item;
}

// Retrieve password hash from settings and return it for "stuff".
function getPassHash() {
    $config = new Config_Lite(CONFIG);
    $item = $config->get('general', 'password', 'foo');
    return $item;
}

// This little gem helps us replace a whome bunch of AJAX calls by sorting out the info and
// writing it to meta-tags at the bottom of the page.  Might want to look at calling this via one AJAX call.
function metaTags() {
	$inipath = false;
    $config = new Config_Lite(CONFIG);
    $iniPath = php_ini_loaded_file();
        if (! $inipath) {
            $inipath = "php.ini";
        }
    $created = filectime(CONFIG);
	$branchChanged = (checkBranchChanged() ? 'true' : 'false');
	$tags = "";

	$general = $config->get('general');
	$general['cwd'] = getcwd();
	$general['sha'] = getSHA();
	$general['phpini'] = $iniPath;
	$general['created'] = $created;
	foreach ($general as $id=>$value) {
		$id .= "-data";
		if (is_bool($value)) $value = boolval($value);
		$tags .= '<meta id="'.$id.'" data='.$value.' class="metatag">'.PHP_EOL;
	}

    return $tags;
}

// Set up the actual iFrame contents, as the name implies.
function frameContent() {
    $config = new Config_Lite(CONFIG);
    if (empty($item)) $item = '';
    foreach ($config as $keyname => $section) {
    $landingpage = $config->getBool($keyname,'landingpage',false);
    $enabled = $config->getBool($keyname,'enabled',true);
    $default = $config->getBool($keyname,'default',false);
    $scale = $config->get($keyname,'scale',1);
    $url = $section["url"];
    $urlArray = parse_url($url);
	if (!$urlArray) {
		$protocol = preg_match("/http/",explode("://",$url)[0]) ? explode("://",$url)[0] . "://" : serverProtocol();
		$url = str_replace($protocol,"",$url);
		$port = explode(":",$url)[1] ? ":".explode(":",$url)[1] : "";
		$url = str_replace($port,"",$url);
		$host = $url ? $url : $_SERVER['HTTP_HOST'];
		$url = $protocol.$host.$port;
		$urlArray = parse_url($url);
	}
    // TODO: Make sure this still works
    if ($landingpage) $urlArray['query'] = isset($urlArray['query']) ? $urlArray['query']."&" : ""."landing=" . urlencode($keyname.":".$url);
    $url = $urlArray ? build_url($urlArray) : $url;
    if ($enabled && ($keyname != 'settings') && ($keyname != 'general')) {
		$item .= "
				<li data-content='" . $keyname . "' data-scale='" . $scale ."' ".($default ? "class='selected'" : '').">
					<iframe sandbox='allow-forms allow-same-origin allow-pointer-lock allow-scripts allow-popups allow-modals allow-top-navigation'
					allowfullscreen='true' webkitallowfullscreen='true' mozallowfullscreen='true' scrolling='auto' data-title='" . $section["name"] . "' ".($default ? 'src' : 'data-src')."='" . $url . "'></iframe>
				</li>";
        }
    }
    return $item;
}

function serverProtocol() {
	return (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')	|| $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://');
}

function build_url($parsed_url) {
	$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : serverProtocol();
	$host     = isset($parsed_url['host']) ? $parsed_url['host'] : "";
	$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
	$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
	$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
	$pass     = ($user || $pass) ? "$pass@" : '';
	$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
	$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
	$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
	return "$scheme$user$pass$host$port$path$query$fragment";
}
// Build a landing page.
function landingPage($keyname) {
    $config = new Config_Lite(CONFIG);
    $item = "
    <html lang='en'>
    <head>
    <title>" . $config->get($keyname, 'name') . "</title>
    <link rel='stylesheet' href='css/landing.css'>
    </head>
    <body>
    <div class='login'>
        <div class='heading'>
            <h2><span class='fa " . $config->get($keyname, 'icon') . " fa-3x'></span></h2>
            <section>
                <a href='" . $config->get($keyname, 'url') . "' target='_self' title='Launch " . $config->get($keyname, 'name') . "!'><button class='float'>Launch " . $config->get($keyname, 'name') . "</button></a>
            </section>
        </div>
     </div>
     </body></html>";
    if (empty($item)) $item = '';
    return $item;
}

// This method checks whether we can execute, if the directory is a git, and if git is installed
function can_git()
{
	if ((exec_enabled() == true) && (file_exists('.git'))) {
		$whereIsCommand = (PHP_OS == 'WINNT') ? 'where git' : 'which git'; 	// Establish the command for our OS
		$gitPath = shell_exec($whereIsCommand); 							// Find where git is
		$git = (empty($gitPath) ? false : true); 							// Make sure we have a path
		if ($git) {															// Double-check git is here and executable
			exec($gitPath . ' --version', $output);
			preg_match('#^(git version)#', current($output), $matches);
			$git = (empty($matches[0]) ? $gitPath : false);  				// If so, return path.  If not, return false.
		}
	} else {
		$git = false;
	}
	return $git;
}

// Can we execute commands?
function exec_enabled() {
    $disabled = explode(', ', ini_get('disable_functions'));
    return !in_array('exec', $disabled);
}

// URL parameters
if (isset($_GET['landing'])) {
    $keyname = $_GET['landing'];
    echo landingPage($keyname);
    die();
}


// Things wrapped inside this are protected by a secret hash.
if (isset($_GET['secret'])) {
	if ($_GET['secret'] !== $_SESSION['secret']) {
		write_log("Invalid secret sent, dying.","ERROR");
		die();
	}
	write_log("We have a secret request!");

	if (isset($_GET['get']) && $_GET['get'] == 'hash') {
        if (exec_enabled() == true) {
		$git = can_git();
            if ($git !== false) {
                $hash = 'unknown';
            } else {
                $hash = exec($git . ' log --pretty="%H" -n1 HEAD');
            }
        } else {
            $hash = 'noexec';
        }
        echo $hash;
        die();
    }

    if (isset($_GET['colors'])) {
    	write_log("Got a request for colors.");
    	echo json_encode(appColors());
    	die;
    }

    if (isset($_GET['id']) && isset($_GET['value'])) {
		$key = $_GET['id'];
		$value = $_GET['value'];
	    $section = 'general';
    	if (preg_match("/_-_/",$key)) {
    		$sections = explode("_-_",$key);
    		$section = $sections[0];
    		$key = $sections[1];
	    }
	    if ($key == 'scale') $value = $value/100;
	    write_log("Updating value for $key : $value in $section","INFO");
    	$config->set($section,$key,$value);
    	saveConfig($config);
    	die();
    }

    if(isset($_GET['remove']) && $_GET['remove'] == "backup") {
        unlink('backup.ini.php');
        echo "deleted";
        die();
    }

    if(isset($_GET['action']) && $_GET['action'] == "update") {
        $sha = $_GET['sha'];
        $results = downloadUpdate($sha);
		if ($results === true) {
			echo $results;
			die();
		} else {
			$data = array('type' => 'error', 'message' => $results);
			header('HTTP/1.1 400 Bad Request');
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode($data);
			die();
		}
    }

    if(isset($_GET['action']) && $_GET['action'] == "branches") {
        $results = fetchBranches(true);
        echo $results;
        die();
    }

    if(isset($_GET['action']) && $_GET['action'] == "log") {
        echo log_contents();
        die();
    }

    if(isset($_GET['action']) && $_GET['action'] == "writeLog") {
        $msg = $_GET['msg'];
        if(isset($_GET['lvl'])) {
            $lvl = $_GET['lvl'];
            write_log($msg,$lvl);
        } else {
            write_log($msg);
        }
        die();
    }
}

// This downloads updates from git if available and able, otherwise, from zip.
function downloadUpdate($sha) {
	$git = can_git();
	if ($git !== false) {
		$branch = getBranch();
		if ($sha == $branch) {
			$resultshort = exec('git status');
			$result = (preg_match('/clean/',$resultshort));
				if ($result !== true) {
				$resultmsg = shell_exec('git status');
				$result ='Install Failed!  Local instance has files that will interfer with branch change - please manually stash changes and try again. Result message: "' . $resultshort.'"';
				write_log($result ,'E');
				$result ='Install Failed!  Local instance has files that will interfer with branch changed - please manually stash changes and try again. See log for details.';
				return $result;
			}
			$result = exec('git checkout '. $branch);
			write_log('Changing git branch, command result is ' . $result,'D');
			$result = (preg_match('/up-to-date/',$result));
			if ($result) {
				$mySha = exec('git rev-parse HEAD');
				$config = new Config_Lite(CONFIG);
				if (!preg_match('/about a specific subcommand/',$mySha)) { // Something went wrong with the command to get our SHA, fall back to using the passed value.
					$config->set('settings','sha',$mySha);
					$config->set("settings","branch_changed",false);
					saveConfig($config);
				} else {
					$config->set('settings','sha',$sha);
				}
				saveConfig($config);
			} else {
				$result = 'Branch change failed!  An unknown error occurred attempting to update.  Please manually check git status and fix.';
			}
		} else {
			$resultshort = exec('git status');
			$result = (preg_match('/clean/',$resultshort));
			if ($result !== true) {
				$result ='Install Failed!  Local instance has files that will interfer with git pull - please manually stash changes and try again. Result message: "' . $resultshort.'"';
				write_log($result ,'E');
				$result ='Install Failed!  Local instance has files that will interfer with git pull - please manually stash changes and try again. See log for details.';
				return $result;
			}
			$result = exec('git pull');
			write_log('Updating via git, command result is ' . $result,'D');
			$result = (preg_match('/Updating/',$result));
			if ($result) {
				$mySha = exec('git rev-parse HEAD');
				$config = new Config_Lite(CONFIG);
				if (!preg_match('/about a specific subcommand/',$mySha)) { // Something went wrong with the command to get our SHA, fall back to using the passed value.
					$config->set('settings','sha',$mySha);
				} else {
					$config->set('settings','sha',$sha);
				}
				saveConfig($config);
			} else {
				$result = 'Install Failed!  An unknown error occurred attempting to update.  Please manually check git status and fix.';
			}
		}
	} else {
		$result = false;
		$zipFile = "Muximux-".$sha. ".zip";
		$f = file_put_contents($zipFile, fopen("https://github.com/mescon/Muximux/archive/". $sha .".zip", 'r'), LOCK_EX);
		if(FALSE === $f) {
			$result = 'Install Failed!  An error occurred saving the update.  Please check directory permissions and try again.';
		} else {
			$zip = new ZipArchive;
			$res = $zip->open($zipFile);
			if ($res === TRUE) {
				$result = $zip->extractTo('./.stage');
				$zip->close();

				if ($result === TRUE) {
					cpy("./.stage/Muximux-".$sha, "./");
					deleteContent("./.stage");
					$gone = unlink($zipFile);
				} else {
					$result = 'Install Failed!  Unable to extract zip file.  Please check directory permissions and try again.';
				}
				$config = new Config_Lite(CONFIG);
				$config->set('settings','sha',$sha);
				saveConfig($config);
			} else {
				$result = 'Install Failed!  Unable to open zip file.  Check directory permissions and try again.';
			}
		}
	}
	if ($result === true) {
		deleteContent('./cache');
		write_log('Update Succeeded.','I');
	} else {
		write_log($result ,'E');
	}
    
    return $result;
}

// Copy a directory recursively - used to move updates after extraction
function cpy($source, $dest){
    if(is_dir($source)) {
        $dir_handle=opendir($source);
        while($file=readdir($dir_handle)){
            if($file!="." && $file!=".."){
                if(is_dir($source."/".$file)){
                    if(!is_dir($dest."/".$file)){
                        mkdir($dest."/".$file);
                    }
                    cpy($source."/".$file, $dest."/".$file);
                } else {
                    copy($source."/".$file, $dest."/".$file);
                }
            }
        }
        closedir($dir_handle);
    } else {
        copy($source, $dest);
    }
}

// Recursively delete the contents of a directory
function deleteContent($path){
    try{
        $iterator = new DirectoryIterator($path);
        foreach ( $iterator as $fileinfo ) {
        if($fileinfo->isDot())continue;
        if($fileinfo->isDir()){
            if(deleteContent($fileinfo->getPathname()))
                @rmdir($fileinfo->getPathname());
            }
            if($fileinfo->isFile()){
                @unlink($fileinfo->getPathname());
                }
        }
    } catch ( Exception $e ){
        // write log
        return false;
    }
    return true;
}

// This is used by our login script to determine session state 
function is_session_started() {
    if ( php_sapi_name() !== 'cli' ) {
        if ( version_compare(phpversion(), '5.4.0', '>=') ) {
            return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
        } else {
            return session_id() === '' ? FALSE : TRUE;
        }
    }
    return FALSE;
}

/**
 * Generate HTML SELECT OPTION along with OPTGROUP
 *
 * You will need to provide the SELECT element yourself as this only generates the optgroup and option elements
 *
 * @param  array  $icomoon_icons The original variable generated by this script
 * @param  string $by            What to print the value by. Possibilities are class, unicode, hex|hexadecimal or key
 * @return string                The optgroup and option for you to echo
 */
function imii_generate_select_options( $icomoon_icons, $by = 'class' ) {
	$return = '';
	$by = strtolower( $by );
	foreach ( $icomoon_icons as $icons ) {
		$return .= '<optgroup label="' . htmlspecialchars( $icons['label'] ) . '">';
		if ( isset( $icons['elements'] ) ) {
			foreach ( $icons['elements'] as $ic_key => $ic_name ) {
				$val = $ic_key;
				if ( $by == 'class' ) {
					$val = htmlspecialchars( $icons['element_classes'][$ic_key] );
				} else if ( $by == 'unicode' ) {
					$val = '&#x' . dechex( $ic_key ) . ';';
				} else if ( $by == 'hex' || $by == 'hexadecimal' ) {
					$val = dechex( $ic_key );
				}
				$return .= '<option value="' . $val . '">' . $ic_name . '</option>';
			}
		}
		$return .= '</optgroup>';
	}
	return $return;
}

/**
 * Generate the JSON variable for the <code>source</code> option for fontIconPicker
 *
 * You will need to assign it to a variable inside JavaScript code
 * @link https://github.com/micc83/fontIconPicker fontIconPicker Project Page
 *
 * @param  array  $icomoon_icons The original variable generated by this script
 * @param  string $by            What to print the value by. Possibilities are class or key
 * @return string                The JSON which can be assigned to a variable. See example
 */
function imii_generate_fip_source_json( $icomoon_icons, $by = 'class' ) {
	$json = array();
	$by = strtolower( $by );
	foreach ( $icomoon_icons as $icons ) {
		$icon_set = array();
		if ( isset( $icons['elements'] ) ) {
			foreach ( $icons['elements'] as $ic_key => $ic_name ) {
				$val = $ic_key;
				if ( $by == 'class' ) {
					$val = htmlspecialchars( $icons['element_classes'][$ic_key] );
				}
				$icon_set[] = $val;
			}
		}
		$json[$icons['label']] = $icon_set;
	}
	return json_encode( $json );
}

/**
 * Generate the JSON variable for the <code>searchSource</code> option for fontIconPicker
 *
 * You will need to assign it to a variable inside JavaScript code
 * @link https://github.com/micc83/fontIconPicker fontIconPicker Project Page
 *
 * @param  array  $icomoon_icons The original variable generated by this script
 * @return string                The JSON which can be assigned to a variable. See example
 */
function imii_generate_fip_search_json( $icomoon_icons ) {
	$json = array();
	foreach ( $icomoon_icons as $icons ) {
		$icon_set = array();
		if ( isset( $icons['elements'] ) ) {
			foreach ( $icons['elements'] as $ic_key => $ic_name ) {
				$icon_set[] = $ic_name;
			}
		}
		$json[$icons['label']] = $icon_set;
	}
	return json_encode( $json );
}


// This might be excessive for just grabbing one theme value from CSS,
// but if we ever wanted to make a full theme editor, it could be handy.

function parseCSS($file,$searchSelector,$searchAttribute){
    $css = file_get_contents($file);
    preg_match_all( '/(?ims)([a-z0-9\s\.\:#_\-@,]+)\{([^\}]*)\}/', $css, $arr);
    $result = false;
    foreach ($arr[0] as $i => $x){
        $selector = trim($arr[1][$i]);
        if ($selector == $searchSelector) {
            $rules = explode(';', trim($arr[2][$i]));
            $rules_arr = array();
            foreach ($rules as $strRule){
                if (!empty($strRule)){
                    $rule = explode(":", $strRule);
                    if (trim($rule[0]) == $searchAttribute) {
                        $result = trim($rule[1]);
                    }
                }
            }
        }
    }
    return $result;
}


// Currently just used outside of the project to generate the iconset names
// In the future, consider using this to dynamically update the actual .js file that 
// icon picker uses.

function mapIcons($file,$classSelector){
	$iconHash = md5_file($file);
	$config = new Config_Lite(CONFIG);
	$fileName = basename($file);
	$storedHash = $config->get('settings','hash_'.$fileName,'');
    if ($iconHash !== $storedHash) {
		$css = file_get_contents($file);
		preg_match_all( '/(?ims)([a-z0-9\s\.\:#_\-@,]+)\{([^\}]*)\}/', $css, $arr);
		$result = '"",';
		foreach ($arr[0] as $i => $x){
			$selector = trim($arr[1][$i]);
			if (strpos($selector, $classSelector) !== false) {
				$selector = str_replace($classSelector,'',$selector);
				$selector = str_replace(':before','',$selector);
				$result .='"'.$selector.'", ';
			}
		}
		$result = substr_replace($result ,"",-2);
		$result = '!function($){$.iconset_muximux={iconClass:"muximux",iconClassFix:"muximux-",icons:['.$result.']}}(jQuery);';
		$file = openFile('js/iconset-muximux.js', "w");
		fwrite($file, $result);
		//$config->set('settings','hash_'.$fileName,$iconHash);
        //saveConfig($config);
	}
}

// Appends lines to file and makes sure the file doesn't grow too much
// You can supply a level, which should be a one-letter code (E for error, D for debug, I for information)
// If a level is not supplied, it will be assumed to be Informative.
function write_log($text,$level=null,$caller=false) {
	if ($level === null) {
		$level = 'DEBUG';
	}
	if (isset($_GET['pollPlayer'])) return;
	$caller = $caller ? $caller : getCaller();
	$filename = 'muximux.log';
	$text = '['.date(DATE_RFC2822) . '] ['.$level.'] ['.$caller . "] - " . trim($text) . PHP_EOL;
	if (!file_exists($filename)) { touch($filename); chmod($filename, 0666); }
	if (filesize($filename) > 2*1024*1024) {
		$filename2 = "$filename.old";
		if (file_exists($filename2)) unlink($filename2);
		rename($filename, $filename2);
		touch($filename); chmod($filename,0666);
	}
	if (!is_writable($filename)) die;
	if (!$handle = fopen($filename, 'a+')) die;
	if (fwrite($handle, $text) === FALSE) die;
	fclose($handle);
}

// Get the name of the function calling write_log
function getCaller() {
	$trace = debug_backtrace();
	$useNext = false;
	$caller = false;
	//write_log("TRACE: ".print_r($trace,true),null,true);
	foreach ($trace as $event) {
		if ($useNext) {
			if (($event['function'] != 'require') && ($event['function'] != 'include')) {
				$caller .= "::" . $event['function'];
				break;
			}
		}
		if ($event['function'] == 'write_log') {
			$useNext = true;
			// Set our caller as the calling file until we get a function
			$file = pathinfo($event['file']);
			$caller = $file['filename'] . "." . $file['extension'];
		}
	}
	return $caller;
}

// Coalesce hack
function c() {
	$args = func_get_args();
	foreach ($args as $arg) {
		if (!empty($arg)) {
			return $arg;
		}
	}
	return NULL;
}


function saveConfig(Config_Lite $inConfig) {
	try {
		$inConfig->save();
	} catch (Config_Lite_Exception $e) {
		echo "\n" . 'Exception Message: ' . $e->getMessage();
		write_log('Error saving configuration.','ERROR');
	}
	$configFile = dirname(__FILE__)."/settings.ini.php";
	$cache_new = "'; <?php die('Access denied'); ?>"; // Adds this to the top of the config so that PHP kills the execution if someone tries to request the config-file remotely.
	$cache_new .= file_get_contents($configFile);
	file_put_contents($configFile,$cache_new);
}


// Echo a message to the user
function setStatus($message) {
	$scriptBlock = "<script language='javascript'>alert(\"" . $message . "\");</script>";
	echo $scriptBlock;
}