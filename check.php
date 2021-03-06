<?
if (!function_exists("ssh2_connect")) {
    echo ("function ssh2_connect doesn't exist");
    exit;
}
require("settings.php");

if (!is_dir(__DIR__."/data")) {
    mkdir(__DIR__."/data");
}

$all = glob(__DIR__."/data/*");
foreach ($all as $file) {
    $time_sec=time(); 
    $time_file=filemtime($file); 
    $time=$time_sec-$time_file; 
    if ($time>60*60*24*3) {
	unlink($file);
    }
}

$result = array();

foreach ($servers as  $host=>$hostparam) {
    $result[$host] = array();
    if(!($con = ssh2_connect($host, 22))){
	$result[$host]['state'] = "offline";
    } else {
	$result[$host]['state'] = "online";
	if(!ssh2_auth_pubkey_file($con, $hostparam['login'], $pubkey, $privkey)) {
	    $result[$host]['state'] = "auth error";
	} else {
	    foreach ($services as $name=>$cmd) {
		$max = $cmd['max'];
		if (!$max) $max=$defaultMax;
		$result[$host][$name] = exec_ssh($cmd['cmd']);
		$n = preg_match("/([\d\.]+)%/",$result[$host][$name],$matches);
		if ($n>0){
		    for ($i=1;$i<=$n;$i++) {
			if (
			    (!is_numeric($max) && $matches[$i] != $max) ||
			    (is_numeric($max) && $matches[$i] >= $max)
			) {
			    alert($host,$name,$i,$matches[$i],$max);
			}
		    }
		}
	    }
	}
    }
}


$date = date_create("now");
$f = fopen (__DIR__."/data/".date_format($date,"Ymd-H"),"w");
fprintf($f,"%s",json_encode($result));
fclose($f);

function alert($host,$name,$n,$value,$max) {
    global $email;
    global $servers;
    $date = date_create("now");
    date_add($date,date_interval_create_from_date_string("-1 hour"));
    $f = fopen (__DIR__."/data/".date_format($date,"Ymd-H"),"r");
    $j = fgets($f);
    $oh = json_decode($j);
    fclose($f);
    $k = preg_match("/([\d\.]+)%/",$oh->{$host}->{$name},$matches);
    if ($k>0) {
//	if ($value > $matches[$n]) {
	//if ($matches[$n] < $max) {
	if(
	    (!is_numeric($max) && $matches[$n] == $max) ||
	    (is_numeric($max) && $matches[$n] < $max)
	){
	    print "ALARM!!!!!!!!!!!!!!!!!!!\n";
	    mail($email,"ALARM! $host - $name - $value%","ALARM! $host - $name - $value%");
	    if ($servers[$host]['email']) mail($servers[$host]['email'],"ALARM! $host - $name - $value%","ALARM! $host - $name - $value%");
	}
    }
    
}

function exec_ssh($command){
    global $con;
    if (!$stream = ssh2_exec($con, $command)){
	return ("fuck");
    }else{
	stream_set_blocking($stream, true);
	$data = "";
	while($o = fgets($stream)){
	    $data .= $o;
	}
	fclose($stream);
    }
    return $data;
}




?>