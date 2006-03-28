<?php 
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('save_user') && $_REQUEST['a']==32) {
	$e->setError(3);
	$e->dumpError();	
}
?>
<?php

// Web alert -  sends an alert to web browser
function webAlert($msg){
	global $id,$modx;
	global $dbase, $table_prefix;
	$mode = $_POST['mode'];
	$url = "index.php?a=$mode".($mode=='12' ? "&id=".$id:"");
	$modx->manager->saveFormValues($mode);
	include_once "header.inc.php";
	$modx->webAlert($msg,$url);
	include_once "footer.inc.php";
}

// Generate password
function generate_password($length = 10) {
	$allowable_characters = "abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
	$ps_len = strlen($allowable_characters);
	mt_srand((double)microtime()*1000000);
	$pass = "";
	for($i = 0; $i < $length; $i++) {
		$pass .= $allowable_characters[mt_rand(0,$ps_len-1)];
	}
	return $pass;
}

$id = intval($_POST['id']);
$oldusername = $_POST['oldusername'];
$newusername = !empty($_POST['newusername']) ? $_POST['newusername'] : "New User";
$fullname = mysql_escape_string($_POST['fullname']);
$genpassword = $_POST['newpassword'];
$passwordgenmethod = $_POST['passwordgenmethod'];
$passwordnotifymethod = $_POST['passwordnotifymethod'];
$specifiedpassword = $_POST['specifiedpassword'];
$email = mysql_escape_string($_POST['email']);
$oldemail = $_POST['oldemail'];
$phone = mysql_escape_string($_POST['phone']);
$mobilephone = mysql_escape_string($_POST['mobilephone']);
$fax = mysql_escape_string($_POST['fax']);
$dob = ConvertDate($_POST['dob']);
$country = $_POST['country'];
$state = mysql_escape_string($_POST['state']);
$zip = mysql_escape_string($_POST['zip']);
$gender = $_POST['gender'];
$photo = mysql_escape_string($_POST['photo']);
$comment = mysql_escape_string($_POST['comment']);
$roleid = $_POST['role'];
$failedlogincount = $_POST['failedlogincount'];
$blocked = $_POST['blocked'];
$blockeduntil = ConvertDate($_POST['blockeduntil']);
$blockedafter = ConvertDate($_POST['blockedafter']);
$user_groups = $_POST['user_groups'];


// verify password
if ($passwordgenmethod=="spec" && $_POST['specifiedpassword']!=$_POST['confirmpassword']) {
	webAlert("Password typed is mismatched");
	exit;
}

// verify email
if($email=='' || !ereg("^[-!#$%&'*+./0-9=?A-Z^_`a-z{|}~]+", $email)){
	webAlert("E-mail address doesn't seem to be valid!");
	exit;
}

switch ($_POST['mode']) {
    case '11':		// new user
		// check if this user name already exist
		$sql = "SELECT id FROM $dbase.".$table_prefix."manager_users WHERE username='$newusername'";
		if(!$rs = mysql_query($sql)){
			webAlert("An error occured while attempting to retreive all users with username $newusername.");
			exit;
		} 
		$limit = mysql_num_rows($rs);
		if($limit>0) {
			webAlert("User name is already in use!");
			exit;
		}
	
		// check if the email address already exist
		$sql = "SELECT id FROM $dbase.".$table_prefix."user_attributes WHERE email='$email'";
		if(!$rs = mysql_query($sql)){
			webAlert("An error occured while attempting to retreive all users with email $email.");
			exit;
		} 
		$limit = mysql_num_rows($rs);
		if($limit>0) {
			$row=mysql_fetch_assoc($rs);
			if($row['id']!=$id) {
				webAlert("Email is already in use!");
				exit;
			}
		}
		
		// generate a new password for this user
		if($specifiedpassword!="" && $passwordgenmethod=="spec") {
			if(strlen($specifiedpassword) < 6 ) {
				webAlert("Password is too short!");
				exit;
			} else {
				$newpassword = $specifiedpassword;
			}			
		} elseif($specifiedpassword=="" && $passwordgenmethod=="spec") {
			webAlert("You didn't specify a password for this user!");
			exit;		
		} elseif($passwordgenmethod=='g') {
			$newpassword = generate_password(8);		
		} else {
			webAlert("No password generation method specified!");
			exit;
		}
		
		// invoke OnBeforeUserFormSave event
		$modx->invokeEvent("OnBeforeUserFormSave",
								array(
									"mode"	=> "new",
									"id"	=> $id
							));	  
							
		// build the SQL
		$sql = "INSERT INTO $dbase.".$table_prefix."manager_users(username, password)
				VALUES('".$newusername."', md5('".$newpassword."'));";
		$rs = mysql_query($sql);
		if(!$rs){
			webAlert("An error occured while attempting to save the user.");
			exit;
		} 		
		// now get the id
		if(!$key=mysql_insert_id()) {
			//get the key by sql
		}

		$sql = "INSERT INTO $dbase.".$table_prefix."user_attributes(internalKey, fullname, role, email, phone, mobilephone, fax, zip, state, country, gender, dob, photo, comment, blocked, blockeduntil, blockedafter)
				VALUES($key, '$fullname', '$roleid', '$email', '$phone', '$mobilephone', '$fax', '$zip', '$state', '$country', '$gender', '$dob', '$photo', '$comment', '$blocked', '$blockeduntil', '$blockedafter');";
		$rs = mysql_query($sql);
		if(!$rs){
			webAlert("An error occured while attempting to save the user's attributes.");
			exit;
		}
	
		// Save User Settings
		saveUserSettings($key);


		// invoke OnManagerSaveUser event
		$modx->invokeEvent("OnManagerSaveUser",
							array(
								"mode"			=> "new",
								"userid"		=> $key,
								"username"		=> $newusername,
								"userpassword"	=> $newpassword,
								"useremail"		=> $email,
								"userfullname"	=> $fullname,
								"userroleid"	=> $roleid
							));

		// invoke OnUserFormSave event
		$modx->invokeEvent("OnUserFormSave",
								array(
									"mode"	=> "new",
									"id"	=> $key
							));	
							
		/*******************************************************************************/
		// put the user in the user_groups he/ she should be in
		// first, check that up_perms are switched on!
		if($use_udperms==1) {
			if(count($user_groups)>0) {
				for ($i=0;$i<count($user_groups);$i++) {
					$sql = "INSERT INTO $dbase.".$table_prefix."member_groups(user_group, member) values('".intval($user_groups[$i])."', $key)";
					$rs = mysql_query($sql);
					if(!$rs){
						webAlert("An error occured while attempting to add the user to a user_group.");
						exit;
					}
				}
			}
		}
		// end of user_groups stuff!
	
		if($passwordnotifymethod=='e') {
			sendMailMessage($email,$newusername,$newpassword,$fullname);
			if ($_POST['stay']!='') {
				$a = ($_POST['stay']=='2') ? "12&id=$id":"11";
				$header="Location: index.php?a=".$a."&r=2&stay=".$_POST['stay'];
				header($header);
			}
			else {
				$header="Location: index.php?a=75&r=2";
				header($header);
			}
		} else {
			include_once "header.inc.php";		
		?>
			<div class="subTitle">
			<span class="right"><img src="media/images/_tx_.gif" width="1" height="5"><br /><?php echo $_lang['web_user_title']; ?></span>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td id="Button1" onclick="window.location.href='index.php?a=75&r=2'"><img src="media/images/icons/save.gif" align="absmiddle"> <?php echo $_lang['close']; ?></td>
						<script>createButton(document.getElementById("Button1"));</script>
					</td>
				</tr>
			</table>
			</div>
			<div class="sectionHeader"><img src='media/style/<?php echo $manager_theme ? "$manager_theme/":""; ?>images/misc/dot.gif' alt="." />&nbsp;<?php echo $_lang['web_user_title']; ?></div>
			<div class="sectionBody">
			<div id="disp">
			<p /><br />
			<?php echo sprintf($_lang["password_msg"], $newusername, $newpassword); ?>
			<p />
			</div>
			</div>
		<?php
			include_once "footer.inc.php";
		}
 	   break;
 	   
    case '12':		// edit user
		// generate a new password for this user
		if($genpassword==1) {
			if($specifiedpassword!="" && $passwordgenmethod=="spec") {
				if(strlen($specifiedpassword) < 6 ) {
					webAlert("Password is too short!");
					exit;
				} 
				else {
					$newpassword = $specifiedpassword;
				}			
			} 
			elseif($specifiedpassword=="" && $passwordgenmethod=="spec") {
				webAlert("You didn't specify a password for this user!");
				exit;		
			} 
			elseif($passwordgenmethod=='g') {
				$newpassword = generate_password(8);		
			} 
			else {
				webAlert("No password generation method specified!");
				exit;
			}
			$updatepasswordsql=", password=MD5('$newpassword') ";
		}
		if($passwordnotifymethod=='e') {
			sendMailMessage($email,$newusername,$newpassword,$fullname);
		}
		
		// check if the username already exist
		$sql = "SELECT id FROM $dbase.".$table_prefix."manager_users WHERE username='$newusername'";
		if(!$rs = mysql_query($sql)){
			webAlert("An error occured while attempting to retreive all users with username $newusername.");
			exit;
		} 
		$limit = mysql_num_rows($rs);
		if($limit>0) {
			$row=mysql_fetch_assoc($rs);
			if($row['id']!=$id) {
				webAlert("User name is already in use!");
				exit;
			}
		}

		// check if the email address already exists
		$sql = "SELECT internalKey FROM $dbase.".$table_prefix."user_attributes WHERE email='$email'";
		if(!$rs = mysql_query($sql)){
			webAlert("An error occured while attempting to retreive all users with email $email.");
			exit;
		} 
		$limit = mysql_num_rows($rs);
		if($limit>0) {
			$row=mysql_fetch_assoc($rs);
			if($row['internalKey']!=$id) {
				webAlert("Email is already in use!");
				exit;
			}
		}

		// invoke OnBeforeUserFormSave event
		$modx->invokeEvent("OnBeforeUserFormSave",
								array(
									"mode"	=> "upd",
									"id"	=> $id
							));
							
		// update user name and password
		$sql = "UPDATE $dbase.".$table_prefix."manager_users SET username='$newusername'".$updatepasswordsql." WHERE id=$id";
		if(!$rs = mysql_query($sql)){
			webAlert("An error occured while attempting to update the user's data.");
			exit;
		} 
		
		$sql = "UPDATE $dbase.".$table_prefix."user_attributes SET 
			fullname='".mysql_escape_string($fullname)."', 
			role='$roleid', 
			email='$email', 
			phone='$phone',
			mobilephone='$mobilephone', 
			fax='$fax', 
			zip='$zip' , 
			state='$state', 
			country='$country', 
			gender='$gender', 
			dob='$dob', 
			photo='$photo', 
			comment='$comment',
			failedlogincount='$failedlogincount', 
			blocked=$blocked, 
			blockeduntil='$blockeduntil', 
			blockedafter='$blockedafter' 
			WHERE internalKey=$id";
		if(!$rs = mysql_query($sql)){
			webAlert("An error occured while attempting to update the user's attributes.");
			exit;
		}
		
		// Save user settings
		saveUserSettings($id);

		// invoke OnManagerSaveUser event
		$modx->invokeEvent("OnManagerSaveUser",
							array(
								"mode"			=> "upd",
								"userid"		=> $id,
								"username"		=> $newusername,
								"userpassword"	=> $newpassword,
								"useremail"		=> $email,
								"userfullname"	=> $fullname,
								"userroleid"	=> $roleid,
								"oldusername"	=> (($oldusername!=$newusername) ? $oldusername : ""),
								"olduseremail"	=> (($oldemail!=$email) ? $oldemail : "")
							));

		// invoke OnManagerChangePassword event
		if($updatepasswordsql) 
			$modx->invokeEvent("OnManagerChangePassword",
							array(
								"userid"		=> $id,
								"username"		=> $newusername,
								"userpassword"	=> $newpassword
							));

		// invoke OnUserFormSave event
		$modx->invokeEvent("OnUserFormSave",
								array(
									"mode"	=> "upd",
									"id"	=> $id
							));	  
							
		/*******************************************************************************/
		// put the user in the user_groups he/ she should be in
		// first, check that up_perms are switched on!
		if($use_udperms==1) {
			// as this is an existing user, delete his/ her entries in the groups before saving the new groups
			$sql = "DELETE FROM $dbase.".$table_prefix."member_groups WHERE member=$id;";
			$rs = mysql_query($sql);
			if(!$rs){
				webAlert("An error occured while attempting to delete previous user_groups entries.");
				exit;
			}
			if(count($user_groups)>0) {
				for ($i=0;$i<count($user_groups);$i++) {
					$sql = "INSERT INTO $dbase.".$table_prefix."member_groups(user_group, member) values(".intval($user_groups[$i]).", $id)";
					$rs = mysql_query($sql);
					if(!$rs){
						webAlert("An error occured while attempting to add the user to a user_group.<br />$sql;");
						exit;
					}
				}
			}
		}
		// end of user_groups stuff!
		/*******************************************************************************/		
		if($id==$modx->getLoginUserID()) {
		?>
			<body bgcolor='#efefef'>
			<script language="JavaScript">
			alert("Your data has been changed.\nPlease log in again.");
			top.location.href='index.php?a=8';		
			</script>
			</body>
		<?php
			exit;
		}
		if($genpassword==1 && $passwordnotifymethod=='s') {
			include_once "header.inc.php";		
		?>
			<div class="subTitle">
			<span class="right"><img src="media/images/_tx_.gif" width="1" height="5"><br /><?php echo $_lang['web_user_title']; ?></span>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td id="Button1" onclick="window.location.href='index.php?a=75&r=2'"><img src="media/images/icons/save.gif" align="absmiddle"> <?php echo $_lang['close']; ?></td>
						<script>createButton(document.getElementById("Button1"));</script>
					</td>
				</tr>
			</table>
			</div>
			<div class="sectionHeader"><img src='media/style/<?php echo $manager_theme ? "$manager_theme/":""; ?>images/misc/dot.gif' alt="." />&nbsp;<?php echo $_lang['web_user_title']; ?></div>
			<div class="sectionBody">
			<div id="disp">
			<p /><br />
			<?php echo sprintf($_lang["password_msg"], $newusername, $newpassword); ?>
			<p />
			</div>
			</div>
		<?php
			include_once "footer.inc.php";
		} else {
			if ($_POST['stay']!='') {
				$a = ($_POST['stay']=='2') ? "12&id=$id":"11";
				$header="Location: index.php?a=".$a."&r=2&stay=".$_POST['stay'];
				header($header);
			}
			else {		
				$header="Location: index.php?a=75&r=2";
				header($header);
			}
		}
    break;
    default:
		webAlert("Unauthorized access");
		exit;		
}

// Send an email to the user
function sendMailMessage($email,$uid,$pwd,$ufn){
	global $mailto;
	global $signupemail_message;
	global $emailsubject, $emailsender;
	global $site_name, $site_start, $site_url;
	$manager_url = $site_url . "manager/";
	$message = sprintf($signupemail_message, $uid, $pwd); // use old method
	// replace placeholders
	$message = str_replace("[+uid+]",$uid,$message);
	$message = str_replace("[+pwd+]",$pwd,$message);
	$message = str_replace("[+ufn+]",$ufn,$message);
	$message = str_replace("[+sname+]",$site_name,$message);
	$message = str_replace("[+semail+]",$emailsender,$message);
	$message = str_replace("[+surl+]",$manager_url,$message);
	if(!mail($email, $emailsubject, $message, "From: ".$emailsender."\r\n"."X-Mailer: Content Manager - PHP/".phpversion(), "-f $emailsender")) {
		webAlert("Error while sending mail to $mailto");
		exit;	
	}		
}

// Save User Settings
function saveUserSettings($id) {
	global $modx;
		
	$ignore = array(	// form field to be ignored 
		'id',			// when saving user settings
		'newusername',
		'fullname',
		'newpassword',
		'passwordgenmethod',
		'passwordnotifymethod',
		'specifiedpassword',
		'confirmpassword',
		'email',
		'phone',
		'mobilephone',
		'fax',
		'dob',
		'country',
		'state',
		'zip',
		'gender',
		'photo',
		'comment',
		'role',
		'failedlogincount',
		'blocked',
		'blockeduntil',
		'blockedafter',
		'user_groups',
		'mode',
		'blockedmode',
		'stay',
		'save'
	);

	// get user setting field names
	foreach($_POST as $n => $v) {
		if(!in_array($n,$ignore)) $settings[] = $n;
	}
	
	mysql_query("DELETE FROM ".$modx->getFullTableName("user_settings")." WHERE user='$id'");
	
	for($i=0;$i<count($settings);$i++){
		$n = $settings[$i]; 
		$vl = ($GLOBALS[$n]!=$_POST[$n])? $_POST[$n]: "";
		if (is_array($vl)) $vl = implode(",",$vl);
		if ($vl!='') {
			$sql = "INSERT INTO ".$modx->getFullTableName("user_settings")." (user,setting_name,setting_value) VALUES($id,'$n','".mysql_escape_string($vl)."')";
			mysql_query($sql);
		}
	}
}

// converts date format dd-mm-yyyy to php date
function ConvertDate($date){
	if($date=="") return "0";
	list($d, $m, $Y, $H, $M, $S) = sscanf($date, "%2d-%2d-%4d %2d:%2d:%2d");
	if (!$H && !$M && !$S) return strtotime("$m/$d/$Y");
	else return strtotime("$m/$d/$Y $H:$M:$S");
}

?>
