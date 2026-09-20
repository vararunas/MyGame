<?php
declare(strict_types=1);session_start();require __DIR__.'/auth.php';
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$p=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require$p;});
use MyGame\Infrastructure\Database\Connection;
$error=null;$checks=[];$rows=[];$gameDate='—';$issues=[];$stats=[];
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
 // Banko sąskaitų vientisumas
 $noBank=$db->query("SELECT c.name FROM companies c LEFT JOIN company_bank_accounts a ON a.company_id=c.id AND a.is_active=1 WHERE c.status='active' GROUP BY c.id,c.name HAVING COUNT(a.id)=0")->fetchAll();
 $checks[]=['level'=>$noBank?'error':'ok','title'=>'Aktyvios įmonės be banko sąskaitos','detail'=>$noBank?'Rasta: '.count($noBank):'Nerasta'];
 foreach($noBank as $x)$issues[]=['area'=>'Bankai','company'=>$x['name'],'period'=>'—','detail'=>'Aktyvi įmonė neturi aktyvios banko sąskaitos.','level'=>'error'];

 // Atsargos ir užsakymai
 $badInv=$db->query("SELECT c.name,p.name product,i.quantity,i.average_cost,i.sale_price FROM company_inventory i JOIN companies c ON c.id=i.company_id JOIN products p ON p.id=i.product_id WHERE i.quantity<0 OR i.average_cost<0 OR i.sale_price<0")->fetchAll();
 foreach($badInv as $x)$issues[]=['area'=>'Atsargos','company'=>$x['name'],'period'=>'—','detail'=>$x['product'].' turi neteisingą likutį/kainą: likutis '.$x['quantity'].', savikaina '.$x['average_cost'].', kaina '.$x['sale_price'],'level'=>'error'];
 $lateOrders=$db->query("SELECT c.name,p.name product,o.arrives_at,o.quantity FROM purchase_orders o JOIN companies c ON c.id=o.company_id JOIN products p ON p.id=o.product_id JOIN game_clock g ON g.id=1 WHERE o.status='ordered' AND o.arrives_at<g.game_date ORDER BY o.arrives_at")->fetchAll();
 $checks[]=['level'=>$lateOrders?'warn':'ok','title'=>'Vėluojantys nepristatyti užsakymai','detail'=>$lateOrders?'Rasta: '.count($lateOrders):'Nerasta'];
 foreach($lateOrders as $x)$issues[]=['area'=>'Tiekimas','company'=>$x['name'],'period'=>(string)$x['arrives_at'],'detail'=>$x['product'].' · '.$x['quantity'].' vnt. turėjo būti pristatyta.','level'=>'warn'];

 // Mėnesinių ciklų dublikatai ir įmonės su atsargomis, bet be ciklo
 $dupes=$db->query("SELECT c.name,m.period,COUNT(*) cnt FROM company_monthly_cycles m JOIN companies c ON c.id=m.company_id GROUP BY m.company_id,m.period HAVING COUNT(*)>1")->fetchAll();
 $checks[]=['level'=>$dupes?'error':'ok','title'=>'Dubliuoti mėnesio ciklai','detail'=>$dupes?'Rasta: '.count($dupes):'Nerasta'];
 foreach($dupes as $x)$issues[]=['area'=>'Mėnesio ciklai','company'=>$x['name'],'period'=>$x['period'],'detail'=>'Tam pačiam mėnesiui rasti '.$x['cnt'].' ciklai.','level'=>'error'];

 // Darbuotojai ir DU
 $payrollMismatch=$db->query("SELECT c.name,m.period,ROUND(m.payroll_cost,2) cycle_payroll,ROUND(COALESCE((SELECT SUM(e.salary) FROM company_employees e WHERE e.company_id=c.id AND e.status='active'),0),2) current_payroll FROM company_monthly_cycles m JOIN companies c ON c.id=m.company_id WHERE m.period=(SELECT MAX(m2.period) FROM company_monthly_cycles m2 WHERE m2.company_id=m.company_id) AND m.payroll_cost<0")->fetchAll();
 foreach($payrollMismatch as $x)$issues[]=['area'=>'Darbuotojai','company'=>$x['name'],'period'=>$x['period'],'detail'=>'Neigiamas darbo užmokesčio kaštas: '.$x['cycle_payroll'].' €.','level'=>'error'];

 // Mokesčiai / įsipareigojimai
 $badOb=$db->query("SELECT c.name,o.description,o.amount,o.penalty_amount,o.status FROM company_obligations o JOIN companies c ON c.id=o.company_id WHERE o.amount<0 OR o.penalty_amount<0")->fetchAll();
 $checks[]=['level'=>$badOb?'error':'ok','title'=>'Neteisingos mokesčių prievolės','detail'=>$badOb?'Rasta: '.count($badOb):'Nerasta'];
 foreach($badOb as $x)$issues[]=['area'=>'Mokesčiai','company'=>$x['name'],'period'=>'—','detail'=>$x['description'].' turi neigiamą sumą.','level'=>'error'];

 // Paskolos
 $badLoans=$db->query("SELECT c.name,l.id,l.principal,l.outstanding_principal,l.status FROM company_loans l JOIN companies c ON c.id=l.company_id WHERE l.outstanding_principal<0 OR l.outstanding_principal>l.principal+0.01")->fetchAll();
 $checks[]=['level'=>$badLoans?'error':'ok','title'=>'Paskolų likučių vientisumas','detail'=>$badLoans?'Rasta: '.count($badLoans):'Gerai'];
 foreach($badLoans as $x)$issues[]=['area'=>'Paskolos','company'=>$x['name'],'period'=>'—','detail'=>'Paskola #'.$x['id'].' turi neteisingą likutį '.$x['outstanding_principal'].' / '.$x['principal'].' €.','level'=>'error'];

 // Veiklos pasirengimas: aktyvi įmonė su pardavimų ciklu, bet be bazinių resursų
 $notReady=$db->query("SELECT c.name,c.industry,
 (SELECT COUNT(*) FROM company_properties p WHERE p.company_id=c.id AND p.status='active') properties,
 (SELECT COUNT(*) FROM company_employees e WHERE e.company_id=c.id AND e.status='active') employees,
 (SELECT COUNT(*) FROM company_utility_contracts u WHERE u.company_id=c.id AND u.is_active=1) utilities
 FROM companies c WHERE c.status='active'")->fetchAll();
 foreach($notReady as $x){if((int)$x['properties']===0||(int)$x['employees']===0||(int)$x['utilities']===0)$issues[]=['area'=>'Veiklos paruošimas','company'=>$x['name'],'period'=>'—','detail'=>'Patalpos: '.$x['properties'].' · darbuotojai: '.$x['employees'].' · komunalinės: '.$x['utilities'],'level'=>'warn'];}

 // Pardavimų neatitikimus perkeliam ir į bendrą problemų sąrašą
 foreach($rows as $x)if($x['level']!=='ok')$issues[]=['area'=>'Pardavimai','company'=>$x['name'],'period'=>$x['period'],'detail'=>'sales_ledger '.$x['ledger_revenue'].' € · ciklas '.$x['cycle_revenue'].' € · bankas '.$x['bank_revenue'].' €','level'=>'error'];
 $stats=['companies'=>(int)$db->query("SELECT COUNT(*) FROM companies")->fetchColumn(),'errors'=>count(array_filter($issues,fn($x)=>$x['level']==='error')),'warnings'=>count(array_filter($issues,fn($x)=>$x['level']==='warn')),'cycles'=>(int)$db->query("SELECT COUNT(*) FROM company_monthly_cycles")->fetchColumn()];

}catch(Throwable $e){$error=$e->getMessage();}
require __DIR__.'/../../src/Presentation/admin/system-diagnostics.php';
