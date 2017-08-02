<?
$tnt = new Tarantool();
echo $tnt->ping()."\n";

$r = $tnt->evaluate("return box.schema.nonexiststable");
var_dump($r);

$sql = "select * from bt1";
$r = $tnt->evaluate("return box.sql.execute([[$sql]])");
var_dump($r);

echo "\n";
