<?php
declare(strict_types=1);session_start();require __DIR__.'/auth.php';
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$p=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require$p;});
use MyGame\Infrastructure\Database\Connection;
$error=null;$checks=[];$rows=[];$gameDate='—';$issues=[];$stats=[];$salesReadiness=[];$salesEngine=[];
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

 // Pardavimų paleidimo diagnostika – parodo ne tik apskaitą, bet ir kodėl pardavimai gali būti 0.
 $companyRows=$db->query("SELECT id,name,city,industry,status FROM companies WHERE status='active' ORDER BY company_type,name")->fetchAll();
 $propertyByIndustry=['retail'=>'shop','logistics'=>'warehouse','manufacturing'=>'factory','services'=>'office'];
 $rolesByIndustry=['retail'=>['seller'],'logistics'=>['driver','warehouse'],'manufacturing'=>['production','warehouse'],'services'=>['manager']];
 $utilitiesByIndustry=['retail'=>['electricity','water'],'logistics'=>['electricity'],'manufacturing'=>['electricity','water'],'services'=>['electricity']];
 $households=(float)($db->query("SELECT COALESCE(balance,0) FROM economy_sectors WHERE code='HOUSEHOLDS' LIMIT 1")->fetchColumn()?:0);
 foreach($companyRows as $co){
  $id=(int)$co['id'];$industry=(string)$co['industry'];$city=(string)$co['city'];
  $propertyType=$propertyByIndustry[$industry]??'office';
  $s=$db->prepare("SELECT COUNT(*) FROM company_properties WHERE company_id=? AND status='active' AND property_type=? AND city=?");$s->execute([$id,$propertyType,$city]);$propertyOk=(int)$s->fetchColumn()>0;
  $roleCodes=$rolesByIndustry[$industry]??['manager'];$marks=implode(',',array_fill(0,count($roleCodes),'?'));
  $s=$db->prepare("SELECT COUNT(*) FROM company_employees e JOIN job_roles r ON r.id=e.role_id WHERE e.company_id=? AND e.status='active' AND r.code IN ($marks)");$s->execute([$id,...$roleCodes]);$employeeOk=(int)$s->fetchColumn()>0;
  $s=$db->prepare("SELECT utility_type FROM company_utility_contracts WHERE company_id=? AND is_active=1 AND monthly_usage>0");$s->execute([$id]);$activeUtilities=array_column($s->fetchAll(),'utility_type');
  $requiredUtilities=$utilitiesByIndustry[$industry]??['electricity'];$missingUtilities=array_values(array_diff($requiredUtilities,$activeUtilities));$utilitiesOk=!$missingUtilities;
  $s=$db->prepare("SELECT COALESCE(SUM(quantity),0) qty,COUNT(*) products,COALESCE(SUM(quantity*COALESCE(NULLIF(sale_price,0),0)),0) stock_value,SUM(CASE WHEN quantity>0 AND sale_price<=0 THEN 1 ELSE 0 END) unpriced FROM company_inventory WHERE company_id=? AND quantity>0");$s->execute([$id]);$inv=$s->fetch()?:[];$stockQty=(float)($inv['qty']??0);$stockOk=$stockQty>0;$unpriced=(int)($inv['unpriced']??0);
  $s=$db->prepare("SELECT demand_index,purchasing_power FROM market_demand WHERE city=? AND industry=? LIMIT 1");$s->execute([$city,$industry]);$demand=$s->fetch()?:null;$demandOk=(bool)$demand;
  $blockers=[];if(!$propertyOk)$blockers[]='nėra tinkamų patalpų tame pačiame mieste';if(!$employeeOk)$blockers[]='nėra operacinio darbuotojo';if(!$utilitiesOk)$blockers[]='trūksta komunalinių: '.implode(', ',$missingUtilities);if(!$stockOk)$blockers[]='nėra atsargų';if($unpriced>0)$blockers[]='nenustatyta pardavimo kaina '.$unpriced.' prekėms';if(!$demandOk)$blockers[]='nėra market_demand įrašo';if($households<=0)$blockers[]='HOUSEHOLDS sektorius neturi pinigų';
  $salesReadiness[]=['name'=>$co['name'],'city'=>$city,'industry'=>$industry,'property'=>$propertyOk,'employee'=>$employeeOk,'utilities'=>$utilitiesOk,'missing_utilities'=>$missingUtilities,'stock'=>$stockOk,'stock_qty'=>$stockQty,'unpriced'=>$unpriced,'demand'=>$demandOk,'demand_index'=>(float)($demand['demand_index']??0),'purchasing_power'=>(float)($demand['purchasing_power']??0),'households'=>$households,'ready'=>!$blockers,'blockers'=>$blockers];
  if($blockers)$issues[]=['area'=>'Pardavimų paleidimas','company'=>$co['name'],'period'=>'—','detail'=>implode('; ',$blockers),'level'=>'warn'];
 }

 // Pardavimų variklio skaičiavimas – ta pati formulė kaip GameEconomyEngine::salesRevenue(), tik READ ONLY.
 foreach($companyRows as $co){
  $id=(int)$co['id'];$industry=(string)$co['industry'];$city=(string)$co['city'];
  $s=$db->prepare("SELECT i.product_id,i.quantity,i.average_cost,i.sale_price,p.name,p.base_price,p.demand_weight,p.price_elasticity FROM company_inventory i JOIN products p ON p.id=i.product_id WHERE i.company_id=? AND i.quantity>0 ORDER BY p.name");$s->execute([$id]);$products=$s->fetchAll();
  $s=$db->prepare("SELECT demand_index,purchasing_power FROM market_demand WHERE city=? AND industry=? LIMIT 1");$s->execute([$city,$industry]);$md=$s->fetch()?:[];
  $s=$db->prepare("SELECT monthly_budget,brand_awareness,reputation FROM company_marketing WHERE company_id=? LIMIT 1");$s->execute([$id]);$mk=$s->fetch()?:[];
  $d=(float)($md['demand_index']??100);$power=(float)($md['purchasing_power']??100);$brand=(float)($mk['brand_awareness']??50);$budget=(float)($mk['monthly_budget']??0);
  $plannedRevenue=0;$plannedQty=0;$productCalc=[];
  foreach($products as $x){$price=(float)($x['sale_price']?:$x['base_price']);$ratio=(float)$x['base_price']/max(.01,$price);$elasticity=max(.20,(float)($x['price_elasticity']??1));$priceEffect=max(.20,min(2.00,pow($ratio,$elasticity)));$weight=max(.10,(float)($x['demand_weight']??1));$units=max(1,round((20+$brand*.4+sqrt(max(0,$budget)))*($d/100)*($power/100)*$weight*$priceEffect));$qty=min((float)$x['quantity'],$units);$rev=round($qty*$price,2);$plannedQty+=$qty;$plannedRevenue+=$rev;$productCalc[]=['name'=>$x['name'],'stock'=>(float)$x['quantity'],'price'=>$price,'base_price'=>(float)$x['base_price'],'weight'=>$weight,'elasticity'=>$elasticity,'price_effect'=>$priceEffect,'demand_units'=>$units,'planned_qty'=>$qty,'planned_revenue'=>$rev];}
  $competition=(int)(function()use($db,$city,$industry,$id){$q=$db->prepare("SELECT COUNT(*) FROM companies WHERE city=? AND industry=? AND status='active' AND id<>?");$q->execute([$city,$industry,$id]);return$q->fetchColumn();})();
  $latest=$db->prepare("SELECT period,revenue FROM company_monthly_cycles WHERE company_id=? ORDER BY period DESC LIMIT 1");$latest->execute([$id]);$lastCycle=$latest->fetch()?:[];
  $salesEngine[]=['name'=>$co['name'],'city'=>$city,'industry'=>$industry,'demand'=>$d,'power'=>$power,'brand'=>$brand,'budget'=>$budget,'competition'=>$competition,'products'=>$productCalc,'planned_qty'=>$plannedQty,'planned_revenue'=>round($plannedRevenue,2),'households'=>$households,'payable_revenue'=>round(min($plannedRevenue,$households),2),'last_period'=>$lastCycle['period']??'—','last_revenue'=>(float)($lastCycle['revenue']??0)];
 }
 // AI tiekimo diagnostika: skiria banko, katalogo, likučio ir kainos problemas.
 $aiSupply=$db->query("SELECT c.id,c.name,c.industry,c.status,COALESCE((SELECT SUM(a.balance) FROM company_bank_accounts a WHERE a.company_id=c.id AND a.is_active=1),0) cash,COALESCE((SELECT SUM(i.quantity) FROM company_inventory i WHERE i.company_id=c.id),0) stock,(SELECT COUNT(*) FROM products p WHERE p.industry=c.industry) catalog,(SELECT COUNT(*) FROM company_bank_accounts a WHERE a.company_id=c.id AND a.is_primary=1 AND a.is_active=1) primary_accounts FROM companies c WHERE c.company_type='ai' ORDER BY c.name")->fetchAll();
 foreach($aiSupply as $ai){$reasons=[];if($ai['status']!=='active')$reasons[]='įmonės būsena: '.$ai['status'];if((int)$ai['primary_accounts']===0)$reasons[]='nėra aktyvios pagrindinės banko sąskaitos';if((int)$ai['catalog']===0)$reasons[]='industrijoje nėra produktų';if((float)$ai['cash']<1500)$reasons[]='banke '.number_format((float)$ai['cash'],2,',',' ').' €: dabartinis AI papildymas reikalauja bent 1 500 €';if((float)$ai['stock']<=0){$issues[]=['area'=>'AI tiekimas','company'=>$ai['name'],'period'=>'—','detail'=>'Atsargos 0 · bankas '.number_format((float)$ai['cash'],2,',',' ').' € · katalogo prekės '.$ai['catalog'].' · '.($reasons?implode('; ',$reasons):'AI turi lėšų ir prekių katalogą, bet papildymas neįvyko; tikrinti mėnesio paleidimą / pirkimo klaidą'),'level'=>'warn'];}}
 // Pardavimų neatitikimus perkeliam ir į bendrą problemų sąrašą
 foreach($rows as $x)if($x['level']!=='ok')$issues[]=['area'=>'Pardavimai','company'=>$x['name'],'period'=>$x['period'],'detail'=>'sales_ledger '.$x['ledger_revenue'].' € · ciklas '.$x['cycle_revenue'].' € · bankas '.$x['bank_revenue'].' €','level'=>'error'];
 $stats=['companies'=>(int)$db->query("SELECT COUNT(*) FROM companies")->fetchColumn(),'errors'=>count(array_filter($issues,fn($x)=>$x['level']==='error')),'warnings'=>count(array_filter($issues,fn($x)=>$x['level']==='warn')),'cycles'=>(int)$db->query("SELECT COUNT(*) FROM company_monthly_cycles")->fetchColumn()];

}catch(Throwable $e){$error=$e->getMessage();}
require __DIR__.'/../../src/Presentation/admin/system-diagnostics.php';
