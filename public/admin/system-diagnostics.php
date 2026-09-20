<?php
declare(strict_types=1);session_start();require __DIR__.'/auth.php';
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$p=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require$p;});
use MyGame\Infrastructure\Database\Connection;
$error=null;$checks=[];$rows=[];$gameDate='—';
try{
 $db=Connection::make();$gameDate=(string)($db->query("SELECT game_date FROM game_clock WHERE id=1")->fetchColumn()?:'—');
 $tables=['companies','company_bank_accounts','company_inventory','sales_ledger','company_monthly_cycles','bank_account_transactions','company_employees','company_properties','company_utility_contracts'];
 foreach($tables as $t){try{$db->query("SELECT 1 FROM ".$t." LIMIT 1");$checks[]=['level'=>'ok','title'=>"Lentelė ".$t,'detail'=>'Pasiekiama'];}catch(Throwable $e){$checks[]=['level'=>'error','title'=>"Lentelė ".$t,'detail'=>$e->getMessage()];}}
 $sql="SELECT c.id,c.name,periods.period,m.id cycle_id,ROUND(COALESCE(m.revenue,0),2) cycle_revenue,
 ROUND(COALESCE((SELECT SUM(s.revenue) FROM sales_ledger s WHERE s.company_id=c.id AND s.period=periods.period),0),2) ledger_revenue,
 ROUND(COALESCE((SELECT SUM(t.amount) FROM bank_account_transactions t JOIN company_bank_accounts a ON a.id=t.account_id WHERE a.company_id=c.id AND t.transaction_type='sales_revenue' AND t.description LIKE CONCAT('%',periods.period,'%')),0),2) bank_revenue
 FROM (SELECT company_id,period FROM company_monthly_cycles UNION SELECT company_id,period FROM sales_ledger) periods
 JOIN companies c ON c.id=periods.company_id
 LEFT JOIN company_monthly_cycles m ON m.company_id=periods.company_id AND m.period=periods.period
 ORDER BY periods.period DESC,c.name LIMIT 100";
 $rows=$db->query($sql)->fetchAll();
 foreach($rows as &$r){$lr=(float)$r['ledger_revenue'];$cr=(float)$r['cycle_revenue'];$br=(float)$r['bank_revenue'];$r['level']=($r['cycle_id']===null||abs($lr-$cr)>.01||abs($lr-$br)>.01)?'error':'ok';}unset($r);
 $neg=(int)$db->query("SELECT COUNT(*) FROM company_inventory WHERE quantity<0")->fetchColumn();
 $checks[]=['level'=>$neg?'error':'ok','title'=>'Neigiamos atsargos','detail'=>$neg?"Rasta: ".$neg:'Nerasta'];
 $orphan=(int)$db->query("SELECT COUNT(*) FROM sales_ledger s LEFT JOIN company_monthly_cycles m ON m.company_id=s.company_id AND m.period=s.period WHERE m.id IS NULL")->fetchColumn();
 $checks[]=['level'=>$orphan?'error':'ok','title'=>'Pardavimai be mėnesio ciklo','detail'=>$orphan?"Rasta: ".$orphan:'Nerasta'];
 $zero=(int)$db->query("SELECT COUNT(*) FROM company_monthly_cycles m WHERE m.revenue=0 AND EXISTS(SELECT 1 FROM sales_ledger s WHERE s.company_id=m.company_id AND s.period=m.period AND s.revenue>0)")->fetchColumn();
 $checks[]=['level'=>$zero?'error':'ok','title'=>'0 € ciklas su realiais pardavimais','detail'=>$zero?"Rasta: ".$zero:'Nerasta'];
}catch(Throwable $e){$error=$e->getMessage();}
require __DIR__.'/../../src/Presentation/admin/system-diagnostics.php';
