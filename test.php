<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");
?>
<pre><?

$res = \My\TestTable::getList();
while ($row = $res->fetch())
{
	print_r($row);
}

?></pre>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>