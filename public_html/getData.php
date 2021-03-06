<?php
// Compress output if possible
ob_start("ob_gzhandler");

$dbh = new PDO('sqlite:/var/lib/exim-stats-php/db.sqlite');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : "day";

switch ($q) {
    case "year":
        $startpoint = "-365days";
        $step = "+1day";
        $num_steps = 365+1;
        $sqltimestr = "%Y-%m-%d 00:00";
        $phptimestr = "Y-m-d 00:00";
        $jtimestr = "Y-m-d";
        break;
    case "month":
        $startpoint = "-30days";
        $step = "+1day";
        $num_steps = 30+1;
        $sqltimestr = "%Y-%m-%d 00:00";
        $phptimestr = "Y-m-d 00:00";
        $jtimestr = "Y-m-d";
        break;
    case "week":
        $startpoint = "-7days";
        $step = "+1hour";
        $num_steps = 24*7+1;
        $sqltimestr = "%Y-%m-%d %H:00";
        $phptimestr = "Y-m-d H:00";
        $jtimestr = "Y-m-d H:00";
        break;
    case "day":
        $startpoint = "-1day";
        $step = "+1hour";
        $num_steps = 24+1;
        $sqltimestr = "%Y-%m-%d %H:00";
        $phptimestr = "Y-m-d H:00";
        $jtimestr = "Y-m-d H:00";
        break;
    case "hour":
    default:
        $startpoint = "-1hour";
        $step = "+1minute";
        $num_steps = 60; // Current minute isn't needed, because there's no data for it
        $sqltimestr = "%Y-%m-%d %H:%M";
        $phptimestr = "Y-m-d H:i";
        $jtimestr = "Y-m-d H:i";
}

header('Content-Type: application/json');
if (function_exists('apc_fetch') && $data = apc_fetch($startpoint)) {
  echo $data;
  exit();
}

$events = array();
array_push($events,array("spamtag","Tagged Spam"));
array_push($events,array("greylistdefer","Greylisted"));
array_push($events,array("out","Delivered mail"));
array_push($events,array("in","Received mail"));
array_push($events,array("rcptrejec","Rejected RCPT"));
array_push($events,array("spamrejec","Rejected Spam"));
array_push($events,array("malware","Rejected Malware"));

$cols = array();
$rows = array();
$col = array();
$emptyrow = array();

$col["id"]="";
$col["label"]="Date";
$col["pattern"]="";
$col["type"]="datetime";

// Initialize columns and rows for date
array_push($cols,$col);
array_push($emptyrow, array("v"=>0,"f"=>null));

// Initialize columns and rows for every event type. Add event index search.
$i = 1;
$idx = array();
foreach ($events as $v) {
    $col["label"]=$v[1];
    $col["type"]="number";
    array_push($cols,$col);
    array_push($emptyrow, array("v"=>0,"f"=>null));
    $idx[$v[0]] = $i++;
}


$timestamp = date($phptimestr, strtotime($startpoint));
$sql = "SELECT strftime(?, timestamp) AS timestamp, event, sum(counter) AS counter";
$sql .= " FROM events WHERE timestamp >= ?";
$sql .= " GROUP BY strftime(?, timestamp), event";
$sql .= " ORDER BY timestamp ASC";
$sth = $dbh->prepare($sql);
$sth->execute(array($sqltimestr, $timestamp, $sqltimestr));
$result = $sth->fetch(PDO::FETCH_ASSOC);

for ($i = 0; $i < $num_steps; $i++) {
  $month = (int)date("m", strtotime($timestamp)) - 1; // Javascript months start from zero
  $datestr = "Date(".date("Y,", strtotime($timestamp));
  $datestr .= str_pad($month, 2, '0', STR_PAD_LEFT);
  $datestr .= date(",d,H,i,s", strtotime($timestamp)).")";
  $row = $emptyrow;
  $row[0]["v"] = $datestr;
  $row[0]["f"] = date($jtimestr, strtotime($timestamp));
  while ($result && $result["timestamp"] == $timestamp) {
    if (isset($idx[$result["event"]])) {
      $row[$idx[$result["event"]]]["v"] = (int)$result["counter"];
    }
    $result = $sth->fetch(PDO::FETCH_ASSOC);
  }
  array_push($rows,array("c"=>$row));
  $timestamp = date("Y-m-d H:i", strtotime("$timestamp $step"));
}
$data=json_encode(array("cols"=>$cols,"rows"=>$rows));
if (function_exists('apc_add')) {
  // Fresh data becomes available every minute
  apc_add($startpoint, $data, 60 - date('s'));
}
echo $data;
