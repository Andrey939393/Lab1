<?php


//error_reporting(E_ALL);
error_reporting(0);
include('comments_config.php');
include('Z:\home\cp\www\Galleries\config.php');
$id=file_get_contents('Z:\home\cp\www\Galleries\Cars\id.txt');

header("Content-type: text/html; charset=windows-1251");

session_start();

define('SVOTING', 'txt');
define('NRVOT', 0); // 0 - голосовать один раз по каждому, 1 - голосовать один раз по всем
define('USRVOTE', 1); // 1 - голосовать могут все, 0 - только зарег. пользователи

if (USRVOTE !== 1) {
	if (!isset($_SESSION)) session_start();
	if (isset($_SESSION['username'])) define('VOTER', $_SESSION['username']);
}
if (!headers_sent()) header('Content-type: text/html; charset=windows-1251');

class Voting {
	protected $voter = ''; // Имена или IP, которые могут голосовать
	public $votitems = 'vote_items'; // Table or file_name to store items that are voted
	public $votusers = 'vote_users'; // Table or filename that stores the users who voted in current day
	protected $tdy; // will store the number of current day
	public $eror = false; // to store and check for errors
	public function __construct() {
		if (defined('NRVOT')) $this->nrvot = NRVOT;
		if (defined('SVOTING')) $this->svoting = SVOTING;
		if (defined('USRVOTE') && USRVOTE === 0) {
			if (defined('VOTER')) $this->voter = VOTER;
		} else
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$this->voter = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$this->voter = $_SERVER['REMOTE_ADDR'];
		}
		$this->tdy = date('j');
		$this->votitems =  'comments/'.$this->votitems.'.txt';
		$this->votusers =  'comments/'.$this->votusers.'.txt';
	}
	public function getVoting($items, $vote = '') {
		$votstdy = $this->votstdyCo($items);
		if (!empty($vote)) {
			if ($this->voter==='') {
				return "alert('Голосовать могут только зарегистрированные пользователи!')";
			} else {
				if ($this->svoting == 'txt') {
					$all_votstdy = $this->votstdyTxt();
					$votstdy = array_unique(array_merge($votstdy, $all_votstdy[$this->tdy]));
				}
				if (in_array($items[0], $votstdy) || ($this->nrvot === 1 && count($votstdy) > 0)) {
					$votstdy[] = $items[0];
					setcookie("votings", implode(',', array_unique($votstdy)), strtotime('tomorrow'));
					return '{"'.$items[0].'":[0,0,3]}';
				} else
				$this->setVotTxt($items, $vote, $all_votstdy);
				array_push($votstdy, $items[0]);
			}
		}
		$setvoted = ($this->nrvot === 1 && count($votstdy) > 0) ? 1 : 0;
		$votitems = $this->getVotTxt($items, $votstdy, $setvoted);
		return json_encode($votitems);
	}
	protected function setVotTxt($items, $vote, $all_votstdy) {
		if (file_exists($this->votitems)) {
			$rows = file($this->votitems, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$nrrows = count($rows);
			if ($nrrows > 0) {
				for($i=0; $i<$nrrows; $i++) {
					$row = explode('|', $rows[$i]);
					if ($row[0] == $items[0]) {
						$rows[$i] = $items[0].'|'.($row[1] + $vote).'|'.($row[2] + 1);
						$rowup = 1; break;
					}
				}
			}
		}
		if (!isset($rowup)) $rows[] = $items[0].'|'.$vote.'|1';
		file_put_contents($this->votitems, implode(PHP_EOL, $rows));
		$all_votstdy['all'][] = $this->tdy.'|'.$this->voter.'|'.$items[0];
		file_put_contents($this->votusers, implode(PHP_EOL, $all_votstdy['all']));
		$all_votstdy[$this->tdy][] = $items[0];
		setcookie("votings", implode(',', array_unique($all_votstdy[$this->tdy])), strtotime('tomorrow'));
	}
	protected function getVotTxt($items, $votstdy, $setvoted) {
		$re = array_fill_keys($items, array(0,0,$setvoted));
		if (file_exists($this->votitems)) {
			$rows = file($this->votitems, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$nrrows = count($rows);
			if ($nrrows > 0) {
				for($i=0; $i<$nrrows; $i++) {
					$row = explode('|', $rows[$i]);
					$voted = in_array($row[0], $votstdy) ? $setvoted + 1 : $setvoted;
					if (in_array($row[0], $items)) $re[$row[0]] = array($row[1], $row[2], $voted);
				}
			}
		}
		return $re;
	}
	protected function votstdyCo() {
		$votstdy = array();
		if (isset($_COOKIE['votings'])) {$votstdy = array_filter(explode(',', $_COOKIE['votings']));}
		return $votstdy;
	}
	protected function votstdyTxt() {
		$re['all'] = array();
		$re[$this->tdy] = array();
		if (file_exists($this->votusers)) {
			$rows = file($this->votusers, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$nrrows = count($rows);
			if ($nrrows > 0) {
				for($i=0; $i<$nrrows; $i++) {
					$row = explode('|', $rows[$i]);
					if ($row[0] == $this->tdy) {
						$re['all'][] = $rows[$i];
						if ($row[1] == $this->voter) $re[$this->tdy][] = $row[2];
					}
				}
			}
		}
		return $re;
	}
}
$obVot = new Voting();

if (isset($_POST['elm']) && isset($_POST['vote'])) {
	$_POST['elm'] = array_map('strip_tags', $_POST['elm']);
	$_POST['elm'] = array_map('trim', $_POST['elm']);
	if (!empty($_POST['vote'])) $_POST['vote'] = intval($_POST['vote']);
	echo $obVot->getVoting($_POST['elm'], $_POST['vote']);
}


///////////////////// Антимат
function removeBadWords($text) {
	global $badwords, $cons;
	$mat=count($badwords);
	for($i=0; $i<$mat; $i++)
	$text=preg_replace("/".$badwords[$i]."/si", $cons, $text);
	return $text;
}

///////////////////// Определение IP
function getIpAddress() {
	$check = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
	$ip = '0.0.0.0';
	foreach ($check as $akey) {
		if (isset($_SERVER[$akey])) {list($ip)=explode(',', $_SERVER[$akey]); break;}
	}
	return $ip;
}

///////////////////// Очистка кода
function replacer($text) {
	$text=str_replace("&#032;", ' ', $text);
	$text=str_replace(">", '&gt;', $text);
	$text=str_replace("<", '&lt;', $text);
	$text=str_replace("\"", '&quot;', $text);
	$text=str_replace("'", '&apos;', $text);
	$text=str_replace("\n", ' ', $text);
	$text=str_replace("\t", '', $text);
	$text=str_replace("\r", '', $text);
	$text=str_replace("|", '', $text);
	$text=preg_replace("/\n/", ' ', $text);
	$text=preg_replace("/\r/", '', $text);
	$text=preg_replace("/\\\$/", '&#036;', $text);
	$text=preg_replace("/\\\/", '&#092;', $text);
	$text=str_replace("   ", ' ', $text);
	$text=str_replace("", ' ', $text);
	if (get_magic_quotes_gpc()) {
		$text=str_replace("&#092;&quot;", '&quot;', $text);
		$text=str_replace("&#092;'", '\'', $text);
		$text=str_replace("&#092;&#092;", '&#092;', $text);
	}
	//$text=stripslashes($text);
	return $text;
}

///////////////////// Автолинкование
function autolink($str, $attributes=array()) {
	$attrs = '';
	foreach ($attributes as $attribute => $value) {$attrs .= " {$attribute}=\"{$value}\"";}
	$str = ' ' . $str;
	$str = preg_replace('`([^"=\'>])((http|https|ftp)://[^\s<]+[^\s<\.)])`i', '$1<a href="$2"'.$attrs.' target=_new>$2</a>', $str);
	$str = substr($str, 1);
	return $str;
}

///////////////////// Кодировка Utf-8 to Win
function Utf8ToWin($fcontents) {
	$out='';
	$c1='';
	$byte2=false;
	for ($c=0; $c<strlen($fcontents);$c++) {
		$i=ord($fcontents[$c]);
		if ($i<=127) {$out .=$fcontents[$c];}
		if ($byte2) {
			$new_c2=($c1 & 3)*64+($i & 63);
			$new_c1=($c1 >> 2) & 5;
			$new_i=$new_c1*256+$new_c2;
			if ($new_i==1025) {
				$out_i=168;
			} else {
				if ($new_i==1105) {$out_i=184;} else {$out_i=$new_i - 848;}
			}
			$out .=chr($out_i);
			$byte2=false;
		}
		if (($i >> 5)==6) {$c1=$i; $byte2=true;}
	}
	return $out;
}


///////////////////// 

if (isset($_REQUEST['form'])) {
	//$page_id=$id;
	$page_id=$_REQUEST['page_id'];
	$page_id=$id;
	
	
$form=<<<EOD
	<form id="commentForm" action="/comments.php" method="post">
	<link rel="stylesheet" type="text/css" href="/comments_img/flags.css" />
	<link rel="stylesheet" type="text/css" href="/comments.css" />
	<input type="hidden" name="page_id" value="$page_id" />
	<input type="hidden" name="add" value="" />
	<table border="0" class="form">
	<tr>
		<td><b>$login </b>
	</tr>
	<tr>
		<td colspan="2"><textarea class="com" name="comment" id="comment" maxlength="$maxmes" onkeypress="return isNotMax(event)" /></textarea></td>
	</tr>
	<tr>
		<td </ttd align="center"><input type="submit" class="inputbutton" value="Отправить"/> &nbsp;<input type="reset" class="inputbutton" value="Очистить"/></td>
	</tr>
	</table>
	</form>
EOD;
	
	echo $form;
	exit();
}




///////////////////// Блок голосования для сообщений
$votelink = "";
$uniq_id = substr(md5(sha1(md5(uniqid(rand(1,99))))),0,10);
//$uniq_id = uniqid('vt_'); //Выведет vt_4bd67d6cd8b8f

if ($votemes == TRUE) {
	$votelink = "• <div class='vote_up' id='vt_$uniq_id'></div>";
}


///////////////////// Блок личных сообщений для админа (шепнуть)
//$memolink="";
if ($memo==0 && $_GET['event']=="message") {
	print"<br><br><br><br><br><center><font size=2 face=tahoma><b>В данный момент личные сообщения отключены!</b><br><br><br><a href='' onClick='self.close()'>Закрыть окно</a></font></center>";
}
if ($memo==TRUE) {
	//$memolink="<a href='#' onclick=\"window.open('/comments.php?event=message','email','width=640,height=480,left=170,top=100,resizable=1,toolbar=0,status=0,border=0,scrollbars=1'); return false\">жалоба</a> • ";
	if ($_GET['event']=="message") {
		$date=gmdate('d.m.Y', time() + 3600*($timezone+(date('I')==1?0:1)));
		$time=gmdate('H:i', time() + 3600*($timezone+(date('I')==1?0:1)));
		$go=admin;
		$mesdat="comments/memo.php";
		$ip=getIpAddress();

		if ($_GET['action']==null or $_GET['action']=="") {$_GET['action']="inbox";}

		print "<html><head><meta http-equiv='Content-Type' content='text/html; charset=windows-1251'><title>Личные сообщения</title><link rel='stylesheet' type='text/css' href='/comments.css' /></head><style>table,td{border: 1px solid black;border-collapse:collapse;}</style><body bgcolor=#E5E5E5 text=#000000 link=#006699 vlink=#5493B4><center><font size=2 face=tahoma><a href=\"comments.php?event=message&action=inbox\" class='com'>Входящие</a>&nbsp;|&nbsp;<a href=\"comments.php?event=message&action=write\" class='com'>Отправить</a></small></font><br><br>";

		if ($memoread==TRUE) {
			if ($_GET['action']=="inbox") {
				if (isset($adminname) && $adminname==$_POST['mname']) {
					print "<table cellpadding=3 width=100%><tr><td colspan=2 align=center bgcolor=#cccccc><font size=2 face=tahoma><b>Личные сообщения!</b></font></td></tr>";
					$alinks=array();
					if (!isset($linkFile)) $linkFile=$mesdat; $lines=file($linkFile) or die("Can't open $linkFile");
					while ($line=array_shift($lines)) {
						list($a['id'], $a['tema'], $a['mess'], $a['otn'], $a['otip'], $a['who'])=explode("|",$line);
						array_push($alinks,$a);
					}
					if (!empty($go)) foreach($alinks as $lk) {
						if ($lk['id']==$go) {
							$lk=str_replace("
", "", $lk);
							echo "<tr><td width=130 valign=top><font size=2 face=tahoma><center>".$lk['otn']."<small><br><br>".$lk['otip']."</small></center></font></td><td><font size=2 face=tahoma><b>".$lk['tema']."</b><br>".$lk['mess']."</font></td></tr>";
						}
					}
					exit;
				}
				$mname=$login;
				print "<br><br><br><br><form action=\"/comments.php?event=message&action=inbox\" method=post><p><font size=2 face=tahoma><b>Введите логин админа</b></font></p><table><tr><td>&nbsp;<input type=text class=inputname size=23 name=mname> <input type=submit class=inputbutton value='Войти'></td></tr></table></form>";
			}
		} else {
			if ($_GET['action']=="inbox") {
				print "<table cellpadding=3 width=100%><tr><td colspan=2 align=center bgcolor=#cccccc><font size=2 face=tahoma><b>Личные сообщения!</b></font></td></tr>";
				$alinks=array();
				if (!isset($linkFile)) $linkFile=$mesdat; $lines=file($linkFile) or die("Can't open $linkFile");
				while ($line=array_shift($lines)) {
					list($a['id'], $a['tema'], $a['mess'], $a['otn'], $a['otip'], $a['who'])=explode("|",$line);
					array_push($alinks,$a);
				}
				if (!empty($go)) foreach($alinks as $lk) {
					if ($lk['id']==$go) {
						$lk=str_replace("
", "", $lk);
						echo "<tr><td width=130 valign=top><font size=2 face=tahoma><center>".$lk['otn']."<small><br><br>".$lk['otip']."</small></center></font></td><td><font size=2 face=tahoma><b>".$lk['tema']."</b><br>".$lk['mess']."</font></td></tr>";
					}
				}
				exit;
			}
		}
		print "</table></body></html>";

		if ($_GET['action']=="write") {
			if ($_GET['function']=="submit") {
				$msg=str_replace("|", '', substr(replacer(strip_tags($_POST['msg'])), 0, 2000));
				$theme=str_replace("|", '', substr(replacer(strip_tags($_POST['theme'])), 0, 120));
				$who=str_replace("|", '', substr(replacer(strip_tags($_POST['who'])), 0, 25));
				$from=str_replace("|", '', substr(replacer(strip_tags($_POST['from'])), 0, 25));

				if (strlen($theme)<3 or strlen($msg)<3) {@header(print "<br><br><center><font size=2 face=arial color=red>Тема или сообщение очень короткое!<br><br><a href=javascript:history.back(1);>назад</a></font></center>"); exit;};

				$text=$who."|".$theme."|".$msg."[sm]$date в $time [/sm]|".$from."|".replacer($ip);
				$text=trim(stripslashes($text));
				$text=preg_replace("#\[sm\](.*?)\[/sm\]#", "<small><br><div align=right>\\1</div></small>", $text);
				$fp=fopen($mesdat,"a+");
				flock($fp,LOCK_EX);
				fputs($fp,"$text\r\n");
				fflush($fp);
				flock($fp,LOCK_UN);
				fclose($fp);

				print "<br><br><br><center><font size=2 face=tahoma><b>Сообщение отправленно!</b></font></center><meta HTTP-EQUIV='Refresh' CONTENT='1; URL=comments.php?event=message'>";
				exit;
			}
			print "<form action=\"/comments.php?event=message&action=write&function=submit\" method=post><table cellpadding=2 align=center>
<tr><td align=right><font size=2 face=tahoma>Ваше имя:</font>&nbsp;<input type=text maxlength=25 name=from class='inputname' style='width:420' title='Не более 25 символов'></td></tr>
<tr><td align=right><font size=2 face=tahoma>Заголовок:</font>&nbsp;<input type=hidden name=who value='$go'><input type=text maxlength=120 name=theme class='inputname' style='width:420' title='от 3 до 120 символов'></td></tr>
<tr><td><textarea name=msg cols=90 rows=10 class='com' style='width:500px;height:150px' title='от 3 до 2000 символов'></textarea></td></tr>
<tr><td align=center><input type=reset value='Очистить' class='inputbutton' style='width:90px'>&nbsp;<input type=submit value='Отправить' class='inputbutton' style='width:90px'></td></tr></table></form>";
		}
		
		exit;
	}
}




if (isset($_REQUEST['add'])) {
	$name=Utf8ToWin(substr(replacer(strip_tags($_REQUEST['name'])), 0, $maxname));
	$comment=Utf8ToWin(str_replace("\n", '<br>', substr(replacer($_REQUEST['comment']), 0, $maxmes)));
	$name=wordwrap($name, $namewrap,' ',1);
	$comment=wordwrap($comment, $comwrap,' ',1);
	$timezone=floor($timezone);

	if ($timezone<-12 || $timezone>12) $timezone = 0;
	$date=gmdate('d.m.Y', time() + 3600*($timezone+(date('I')==1?0:1)));
	$time=gmdate('H:i', time() + 3600*($timezone+(date('I')==1?0:1)));
	//$datetime=date('d.m.Y H:i');

	//if ($liteurl==1) {$comment=preg_replace("#([^\[img\]])(http|https|ftp|goper):\/\/([a-zA-Z0-9\.\?&=\;\-\/_]+)([\W\s<\[]+)#i", "\\1<a href=\"\\2://\\3\" target=\"_blank\">\\2://\\3</a>\\4", $comment);}
	if ($liteurl==1) {$comment=autolink($comment);}
	 
	if ($antimat==1) {
		$name=removeBadWords($name);
		$email=removeBadWords($email);
		$comment=removeBadWords($comment);
	}

	$ip = getIpAddress();

	if ($ipinfodb==1) {
		$url = "http://api.ipinfodb.com/v3/ip-city/?key=$key&ip=$ip&format=json";
		$data = json_decode(utf8_encode(file_get_contents($url)));
		$country_code = $data->countryCode;
		$country_city = ucwords(strtolower($data->cityName.', '.$data->countryName));
		//$image = strtolower($country_code) . ".png";
		$image = strtolower($country_code);
		$country_img = "<div class=\"$image\" title=\"$country_city\"></div>";
	} else {
		//$ip = $_SERVER['REMOTE_ADDR'];
		//$ip = getIpAddress();
		$host = gethostbyaddr($ip);

$country = array("localhost" => "Localhost", "ad" => "Andorra", "ae" => "United Arab Emirates", "af" => "Afghanistan", "ag" => "Antigua and Barbuda", "ai" => "Anguilla", "al" => "Albania", "am" => "Armenia", "an" => "Netherlands Antilles", "ao" => "Angola", "aq" => "Antarctica", "ar" => "Argentina", "as" => "American Samoa", "at" => "Austria", "au" => "Australia", "aw" => "Aruba", "az" => "Azerbaijan", "ba" => "Bosnia and Herzegovina", "bb" => "Barbados", "bd" => "Bangladesh", "be" => "Belgium", "bf" => "Burkina faso", "bg" => "Bulgaria", "bh" => "Bahrain", "bi" => "Burundi", "bj" => "Benin", "bm" => "Bermuda", "bn" => "Brunei darussalam", "bo" => "Bolivia", "br" => "Brazil", "bs" => "Bahamas", "bt" => "Bhutan", "bv" => "Bouvet Island", "bw" => "Botswana", "by" => "Belarus", "bz" => "Belize", "ca" => "Canada", "cc" => "Cocos (keeling) islands", "cd" => "Congo the democratic republic of the", "cf" => "Central african republic", "cg" => "Congo", "ch" => "Switzerland", "ci" => "Cote DIvoire", "ck" => "Cook Islands", "cl" => "Chile", "cm" => "Cameroon", "cn" => "China", "co" => "Colombia", "cr" => "Costa Rica", "cu" => "Cuba", "cv" => "Cape Verde", "cx" => "Christmas island", "cy" => "Cyprus", "cz" => "Czech republic", "de" => "Germany", "dj" => "Djibouti", "dk" => "Denmark", "dm" => "Dominica", "do" => "Dominican republic", "dz" => "Algeria", "ec" => "Ecuador", "ee" => "Estonia", "eg" => "Egypt", "eh" => "Western sahara", "er" => "Eritrea", "es" => "Spain", "et" => "Ethiopia", "fi" => "Finland", "fj" => "Fiji", "fk" => "Falkland islands (malvinas)", "fm" => "Micronesia federated states of", "fo" => "Faroe islands", "fr" => "France", "ga" => "Gabon", "gb" => "United Kingdom", "gd" => "Grenada", "ge" => "Georgia", "gf" => "French Guiana", "gh" => "Ghana", "gi" => "Gibraltar", "gl" => "Greenland", "gm" => "Gambia", "gn" => "Guinea", "gp" => "Guadeloupe", "gq" => "Equatorial guinea", "gr" => "Greece", "gs" => "South georgia and the south sandwich islands", "gt" => "Guatemala", "gu" => "Guam", "gw" => "Guinea-Bissau", "gy" => "Guyana", "hk" => "Hong Kong", "hm" => "Heard island and mcdonald islands", "hn" => "Honduras", "hr" => "Croatia", "ht" => "Haiti", "hu" => "Hungary", "id" => "Indonesia", "ie" => "Ireland", "il" => "Israel", "in" => "India", "io" => "British indian ocean territory", "iq" => "Iraq", "ir" => "Iran, islamic republic of", "is" => "Iceland", "it" => "Italy", "jm" => "Jamaica", "jo" => "Jordan", "jp" => "Japan", "ke" => "Kenya", "kg" => "Kyrgyzstan", "kh" => "Cambodia", "ki" => "Kiribati", "km" => "Comoros", "kn" => "Saint kitts and nevis", "kp" => "Korea democratic people's republic of", "kr" => "Korea republic of", "kw" => "Kuwait", "ky" => "Cayman islands", "kz" => "Kazakstan", "la" => "Lao people's democratic republic", "lb" => "Lebanon", "lc" => "Saint Lucia", "li" => "Liechtenstein", "lk" => "Sri Lanka", "lr" => "Liberia", "ls" => "Lesotho", "lt" => "Lithuania", "lu" => "Luxembourg", "lv" => "Latvia", "ly" => "Libyan Arab Jamahiriya", "ma" => "Morocco", "mc" => "Monaco", "md" => "Moldova republic of", "mg" => "Madagascar", "mh" => "Marshall islands", "mk" => "Macedonia the former yugoslav republic of", "ml" => "Mali", "mm" => "Myanmar", "mn" => "Mongolia", "mo" => "Macau", "mp" => "Northern mariana islands", "mq" => "Martinique", "mr" => "Mauritania", "ms" => "Montserrat", "mt" => "Malta", "mu" => "Mauritius", "mv" => "Maldives", "mw" => "Malawi", "mx" => "Mexico", "my" => "Malaysia", "mz" => "Mozambique", "na" => "Namibia", "nc" => "New Caledonia", "ne" => "Niger", "nf" => "Norfolk island", "ng" => "Nigeria", "ni" => "Nicaragua", "nl" => "Netherlands", "no" => "Norway", "np" => "Nepal", "nr" => "Nauru", "nu" => "Niue", "nz" => "New Zealand", "om" => "Oman", "pa" => "Panama", "pe" => "Peru", "pf" => "French Polynesia", "pg" => "Papua New Guinea", "ph" => "Philippines", "pk" => "Pakistan", "pl" => "Poland", "pm" => "Saint pierre and miquelon", "pn" => "Pitcairn", "pr" => "Puerto Rico", "ps" => "Palestinian territory occupied", "pt" => "Portugal", "pw" => "Palau", "py" => "Paraguay", "qa" => "Qatar", "re" => "Reunion", "ro" => "Romania", "ru" => "Russian Federation", "rw" => "Rwanda", "sa" => "Saudi Arabia", "sb" => "Solomon Islands", "sc" => "Seychelles", "sd" => "Sudan", "se" => "Sweden", "sg" => "Singapore", "sh" => "Saint Helena", "si" => "Slovenia", "sj" => "Svalbard and Jan Mayen", "sk" => "Slovakia", "sl" => "Sierra Leone", "sm" => "San Marino", "sn" => "Senegal", "so" => "Somalia", "sr" => "Suriname", "st" => "Sao Tome and principe", "sv" => "EL Salvador", "sy" => "Syrian Arab Republic", "sz" => "Swaziland", "tc" => "Turks and Caicos Islands", "td" => "Chad", "tf" => "French southern territories", "tg" => "Togo", "th" => "Thailand", "tj" => "Tajikistan", "tk" => "Tokelau", "tm" => "Turkmenistan", "tn" => "Tunisia", "to" => "Tonga", "tp" => "East Timor", "tr" => "Turkey", "tt" => "Trinidad and Tobago", "tv" => "Tuvalu", "tw" => "Taiwan province of China", "tz" => "Tanzania united republic of", "ua" => "Ukraine", "ug" => "Uganda", "um" => "United states minor outlying islands", "us" => "United States", "uy" => "Uruguay", "uz" => "Uzbekistan", "va" => "Holy See (Vatican city state)", "vc" => "Saint Vincent and the Grenadines", "ve" => "Venezuela", "vg" => "Virgin islands British", "vi" => "Virgin islands U.S", "vn" => "Viet Nam", "vu" => "Vanuatu", "wf" => "Wallis and Futuna", "ws" => "Samoa", "ye" => "Yemen", "yt" => "Mayotte", "yu" => "Yugoslavia", "za" => "South africa", "zm" => "Zambia", "zw" => "Zimbabwe");

		$array = array_reverse(explode('.',$host));
		$flag_img = strtolower($array[0]);
		$country_img = "<div class=\"$flag_img\" title=\"".$country[$array[0]]."\"></div>";
	}
	
	$page_id=str_replace(array('\\', '//'), '', strip_tags($_REQUEST['page_id']));
	
	$filedat=file(dirname(__FILE__)."/comments/$page_id");
	$ii=count($filedat);
	$num=intval(($ii/4)+1);


	if (isset($_COOKIE['postdate'])) {
		if (stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml")) {header("Content-type: application/xhtml+xml; charset=windows-1251");} else {header("Content-type: text/xml; charset=windows-1251");}
		$et='>';
		$lost_time=ceil(($_COOKIE['postdate']-time())/60);
		echo "<?xml version='1.0' encoding='windows-1251'?$et\n";
		echo "<answer><result>error</result><error>Вы можете добавить новое сообщение только через $lost_time мин.</error></answer>";
		exit();
	}
	if ($comment !="") {
		if (stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml")) {header("Content-type: application/xhtml+xml; charset=windows-1251");} else {header("Content-type: text/xml; charset=windows-1251");}
		foreach ($blockip as $a) {
			if ($_SERVER["REMOTE_ADDR"]==$a) {
				$et='>';
				echo "<?xml version='1.0' encoding='windows-1251'?$et\n";
				echo "<answer><result>error</result><error>Вы не можете добавлять комментарии, вас заблокировал админ!</error></answer>";
				exit();
			}
		}
		$mystring=strtoupper($comment);
		foreach ($spam as $a) {
			if (strpos($mystring, strtoupper($a))===false) { } else {
				$et='>';
				echo "<?xml version='1.0' encoding='windows-1251'?$et\n";
				echo "<answer><result>error</result><error>Ваш комментарий содержит недопустимые слова!</error></answer>";
				exit();
			}
		}
		foreach ($blockname as $a) {
			if (strtoupper($name)==strtoupper($a)) {
				$et='>';
				echo "<?xml version='1.0' encoding='windows-1251'?$et\n";
				echo "<answer><result>error</result><error>Вы не можете добавлять комментарии, вас заблокировали!</error></answer>";
				exit();
			}
		}
	}

$name=$login;
$body=<<<EOD
<table class=comm><tr><td><div class=titleflag>$country_img</div><div class=title><b><a "mailto:">$name</a></b></div><div class=titler><!--$ip--><font class=date>$date $time #$num $votelink</font></div></td></tr>
<tr><td class=content>$comment</td></tr>
<tr><td></td></tr>
<tr><td></td><tr></table><br>
EOD;
	


	if ($mailmess==TRUE) {
		$headers=null;
		$headers.="From: $name\n";
		$headers.="X-Mailer: Comments v1.5\n";
		$headers.="Content-type: text/plain; charset=windows-1251";
		mail("$adminmail", "Комментарий от $name", $body, $headers);
	}
	
	
	$f=fopen(dirname(__FILE__)."/comments/$page_id", 'r');
	$content=fread($f, filesize(dirname(__FILE__)."/comments/$page_id"));
	fclose($f);
	$f=fopen(dirname(__FILE__)."/comments/$page_id", 'w');
	fwrite($f, $body."\n".$content);
	fclose($f);

	if ($comlimit==1) {
		$maxline=$maxcom*4;
		$filedat=file(dirname(__FILE__)."/comments/$page_id");
		$i=count($filedat);

		if ($i > $maxline) {
			$fp=fopen(dirname(__FILE__)."/comments/$page_id","w");
			flock($fp,LOCK_EX);
			unset($filedat[$maxline+3], $filedat[$maxline+2], $filedat[$maxline+1], $filedat[$maxline]);
			fputs($fp, implode("",$filedat));
			fflush($fp);
			flock($fp,LOCK_UN);
			fclose($fp);
		}
	}
	setcookie("postdate", time()+$timer, time()+$timer);


	// Запись последних комментариев в файл lastcom.dat
	if ($lastcom==1) {
		$adres=getenv('HTTP_REFERER');
		$lastcomfile="./comments/lastcom.dat";
		//$comsubstr=substr($comment, 0, 200);
		$valuelast="$name|$email|$date|$time|$comment|$country_img|$ip|$num|$adres|||";

		$fp=fopen("$lastcomfile","a+");
		flock($fp,LOCK_EX);
		fputs($fp,"$valuelast\r\n");
		fflush($fp);
		flock($fp,LOCK_UN);
		fclose($fp);

		$file=file($lastcomfile);
		$i=count($file);

		if ($i>=$lastlines) {
			$fp=fopen("$lastcomfile","w");
			flock($fp,LOCK_EX);
			unset($file[0]);
			fputs($fp, implode("",$file));
			fflush($fp);
			flock($fp,LOCK_UN);
			fclose($fp);
		}
	}
	if (stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml")) {header("Content-type: application/xhtml+xml; charset=windows-1251");} else {header("Content-type: text/xml; charset=windows-1251");}
	$et='>';
	echo "<?xml version='1.0' encoding='windows-1251'?$et\n";
	echo "<answer><result>success</result></answer>";
	exit();
}
if (isset($_GET['secpic'])) {
	if ($captcha==1) {$letters=array('0','1','2','3','4','5','6','7','8','9');} else {$letters=array('a','b','c','d','e','f','g','h','j','k','m','n','p','q','r','s','t','u','v','w','x','y','z','2','3','4','5','6','7','9');}
	$colors=array('10','30','50','70','90','110','130','150','170','190','210');
	$src=imagecreatetruecolor($width,$height);
	$fon=imagecolorallocate($src,255,255,255);
	imagefill($src,0,0,$fon);
	$fonts=array();
	$dir=opendir($path_fonts);
	while($fontName=readdir($dir)) {
		if($fontName != "." && $fontName !="..") {
			$fonts[]=$fontName;
		}
	}
	closedir($dir);
	for($i=0;$i<$fon_let_amount;$i++) {
		$color=imagecolorallocatealpha($src,rand(0,255),rand(0,255),rand(0,255),100);
		$font=$path_fonts.$fonts[rand(0,sizeof($fonts)-1)];
		$letter=$letters[rand(0,sizeof($letters)-1)];
		$size=rand($font_size-2,$font_size+2);
		imagettftext($src,$size,rand(0,45),rand($width*0.1,$width-$width*0.1),rand($height*0.2,$height),$color,$font,$letter);
	}
	for($i=0;$i<$let_amount;$i++) {
		$color=imagecolorallocatealpha($src,$colors[rand(0,sizeof($colors)-1)],$colors[rand(0,sizeof($colors)-1)],$colors[rand(0,sizeof($colors)-1)],rand(20,40));
		$font=$path_fonts.$fonts[rand(0,sizeof($fonts)-1)];
		$letter=$letters[rand(0,sizeof($letters)-1)];
		$size=rand($font_size*2.1-2,$font_size*2.1+2);
		$x=($i+1)*$font_size + rand(4,7);
		$y=(($height*2)/3) + rand(0,5);
		$cod[]=$letter;
		imagettftext($src,$size,rand(0,15),$x,$y,$color,$font,$letter);
	}
	$_SESSION['secpic']=implode('',$cod);
	//header("Content-type: image/gif");
	//imagegif($src);
	header("Content-type: image/png");
	imagepng($src);
}

if (isset($_GET['page_id'])) {
	$f=fopen($s='comments/'.$_GET['page_id'], 'r');
	$content=fread($f, filesize('comments/'.$_GET['page_id']));
	fclose($f);
	echo $content;
}

if (isset($_GET['script'])) {

?>




/*

var ipinfokey = '<?php echo $key;?>';

function geolocate(timezone, cityPrecision, objectVar) {
	var api = (cityPrecision)?"ip-city":"ip-country";
	var url = "http://api.ipinfodb.com/v3/"+api+"/?key="+ipinfokey+"&format=json"+"&callback="+objectVar+".setGeoCookie";
	var geodata;
	var callbackFunc;
	var JSON = JSON || {};
	JSON.stringify = JSON.stringify || function(obj) {
		var t = typeof(obj);
		if (t !="object" || obj===null) {
			if (t == "string") obj = '"'+obj+'"'; return String(obj);
		} else {
			var n, v, json = [], arr = (obj && obj.constructor == Array);
			for (n in obj) {
				v = obj[n];
				t = typeof(v);
				if (t == "string") v='"'+v+'"';
				else if (t=="object" && v !==null) v=JSON.stringify(v);
				json.push((arr ? "" : '"' +n+ '":') + String(v));
			}
			return (arr ? "[" : "{") + String(json) + (arr ? "]" : "}");
		}
	};
	JSON.parse = JSON.parse || function(str) {
		if (str==="") str='""';
		eval("var p=" + str + ";");
		return p;
	};
	this.checkcookie = function(callback) {
		geolocationCookie = getCookie('geolocation');
		callbackFunc = callback;
		if (!geolocationCookie) {
			getGeolocation();
		} else {
			geodata = JSON.parse(geolocationCookie);
			callbackFunc();
		}
	};
 	this.setGeoCookie = function(answer) {
		if (answer['statusCode'] == 'OK') {
			JSONString = JSON.stringify(answer);
			setCookie('geolocation', JSONString, 365);
			geodata = answer;
			callbackFunc();
		}
	};
	this.getField = function(field) {
		try {return geodata[field];} catch(err) {}
	}
	function getGeolocation() {
		try {
			script = document.createElement('script');
			script.src = url;
			document.body.appendChild(script);
		} catch(err) {}
	}
	function setCookie(c_name, value, expire) {
		var exdate=new Date();
		exdate.setDate(exdate.getDate()+expire);
		document.cookie = c_name+ "=" +escape(value) + ((expire==null) ? "" : ";expires="+exdate.toGMTString());
	}
	function getCookie(c_name) {
		if (document.cookie.length > 0 ) {
			c_start=document.cookie.indexOf(c_name + "=");
			if (c_start != -1) {
				c_start=c_start + c_name.length+1;
				c_end=document.cookie.indexOf(";",c_start);
				if (c_end == -1) {c_end=document.cookie.length;}
				return unescape(document.cookie.substring(c_start,c_end));
			}
		}
		return '';
	}
};

var visitorGeolocation = new geolocate(false, true, 'visitorGeolocation');
var callback = function(){
	try {
		user_ip = visitorGeolocation.getField('ipAddress');
		country_code = visitorGeolocation.getField('countryCode');
		country_name = visitorGeolocation.getField('countryName');
		city_name = visitorGeolocation.getField('cityName');
		time_zone = visitorGeolocation.getField('timeZone');
		city_latitude = visitorGeolocation.getField('latitude');
		city_longitude = visitorGeolocation.getField('longitude');
	} catch (e) {}
};
visitorGeolocation.checkcookie(callback);
*/


var pagecom = '<?php echo $pagecom;?>';
var maxmes = '<?php echo $maxmes;?>';
var page_id= '<?php echo $id;?>';


$(document).ready(function() {
	$.ajax({
		type: "GET",
		url: '/comments.php?form&page_id='+page_id,
		success: function(html) {
			document.getElementById('commentFormContent').innerHTML= html;
			$('#commentForm').submit(function() {
				if ($('#name').val()=='') {alert("Введите свое имя!"); return false;}
				if ($('#comment').val()=='') {alert("Введите текст сообщения!"); return false;}
				window.location.reload(true);
				$.blockUI();
				$(this).ajaxSubmit({success: processJson, dataType: 'xml'});
				return false;
			});
		}
	});
	$.ajax({
		type: 'POST',
		dataType: 'html',
		url: '/comments.php?page_id='+page_id,
		success: function(html) {
			//alert(html);
			$.getScript('/comments.js');
			//$("<script>").append($(html));
			showComment(html);
		}
	});
});

function showComment(content) {
	content = '<br><div id="com_top"></div><br><table id="comments_table" class=com border=0>'+content+'</table><div id="com_bottom"></div><br><small></small>';
	document.getElementById('comments_div').innerHTML = content;
	var table = document.getElementById('comments_table');
	count_page = Math.ceil(table.getElementsByTagName('tr').length/(4 * pagecom));
	if (count_page > 1) {
		pager_content = '';
		for(var i=0; i<count_page; i++) {
			pager_content = pager_content + '&nbsp;<a href="javascript:showPage('+(i+1)+')" class="pagination">'+(i+1)+'</a>';
		}
		document.getElementById('com_top').innerHTML = pager_content;
		document.getElementById('com_bottom').innerHTML = pager_content;
	}
	showPage(1);
}

function showPage(page) {
	var table = document.getElementById('comments_table');
	var trList = table.getElementsByTagName('tr');
	s = (page - 1) * pagecom * 4;
	e = s + (pagecom * 4);
	npost=1;
	for (var i=0; i<trList.length; i++) {
		if ((i)%4 == 4) {trList[i].getElementsByTagName('td')[0].innerHTML='<i>'+npost+'<i>'; npost++;}
		if (i<s || i>=e) {trList[i].style.display='none';} else {trList[i].style.display='';}
	}
}

function processJson(answer) {
	result = $(answer).find('result').text();
	$.unblockUI();
	if (result == 'success') {
		$("#name").val('');
		$("#email").val('');
		$("#comment").val('');
		$.ajax({
			type: "GET",
			url: '/comments/'+page_id+'?'+Math.random(),
			success: function(html) {
				showComment(html);
			}
		});

		window.location.reload(true);
	} else {
		error = $(answer).find('error').text();
		alert(error);
	}
}

function isNotMax(e) {
	e = e || window.event;
	var target = e.target || e.srcElement;
	var code=e.keyCode?e.keyCode:(e.which?e.which:e.charCode)
	switch(code) {
		case 13:
		case 8:
		case 9:
		case 46:
		case 37:
		case 38:
		case 39:
		case 40:
		document.getElementById('countchars').innerHTML = maxmes - target.value.length;
		return true;
	}
	document.getElementById('countchars').innerHTML = maxmes - target.value.length;
	return target.value.length < target.getAttribute('maxlength');
}

<?php
}
?>
