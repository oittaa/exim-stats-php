<?php

$dbh = new PDO('sqlite:/var/lib/exim-stats-php/db.sqlite');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : "day";

switch ($q) {
    case "year":
        $startpoint = "-365days";
        $step = "+1day";
        $num_steps = 365;
        $sqltimestr = "%Y-%m-%d 00:00";
        $phptimestr = "Y-m-d 00:00";
        $jtimestr = "Y-m-d";
        break;
    case "month":
        $startpoint = "-30days";
        $step = "+1day";
        $num_steps = 30;
        $sqltimestr = "%Y-%m-%d 00:00";
        $phptimestr = "Y-m-d 00:00";
        $jtimestr = "Y-m-d";
        break;
    case "week":
        $startpoint = "-7days";
        $step = "+1hour";
        $num_steps = 24*7;
        $sqltimestr = "%Y-%m-%d %H:00";
        $phptimestr = "Y-m-d H:00";
        $jtimestr = "Y-m-d H:00";
        break;
    case "day":
        $startpoint = "-1day";
        $step = "+1hour";
        $num_steps = 24;
        $sqltimestr = "%Y-%m-%d %H:00";
        $phptimestr = "Y-m-d H:00";
        $jtimestr = "Y-m-d H:00";
        break;
    case "hour":
    default:
        $startpoint = "-1hour";
        $step = "+1minute";
        $num_steps = 60;
        $sqltimestr = "%Y-%m-%d %H:%M";
        $phptimestr = "Y-m-d H:i";
        $jtimestr = "Y-m-d H:i";
}


$s = array();
array_push($s,array("spamtag","Tagged Spam"));
array_push($s,array("greylistdefer","Greylisted"));
array_push($s,array("out","Delivered mail"));
array_push($s,array("in","Received mail"));
array_push($s,array("rcptrejec","Rejected RCPT"));
array_push($s,array("spamrejec","Rejected Spam"));
array_push($s,array("malware","Rejected Malware"));

$cols = array();
$rows = array();
$col = array();
$row = array();

$col["id"]="";
$col["label"]="Date";
$col["pattern"]="";
$col["type"]="datetime";
array_push($cols,$col);
foreach ($s as $v) {
    $col["label"]=$v[1];
    $col["type"]="number";
    array_push($cols,$col);
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
  $row[0]["v"] = $datestr;
  $row[0]["f"] = date($jtimestr, strtotime($timestamp));
  $j = 1;
  foreach ($s as $v) {
    $row[$j]["v"] = 0;
    $row[$j]["f"] = null;
    $j++;
  }
  while ($result && $result["timestamp"] == $timestamp) {
    $j = 1;
    foreach ($s as $v) {
      if ($result["event"] == $v[0]) {
        $row[$j]["v"] = (int)$result["counter"];
      }
      $j++;
    }
    $result = $sth->fetch(PDO::FETCH_ASSOC);
  }
  array_push($rows,array("c"=>$row));
  $timestamp = date("Y-m-d H:i", strtotime("$timestamp $step"));
}
$data=array("cols"=>$cols,"rows"=>$rows);
header('Content-Type: application/json');
echo json_encode($data);
