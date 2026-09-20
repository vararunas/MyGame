<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Economy;
use PDO;
final class AiCompanyEngine{
 public function __construct(private PDO $pdo){}
 public function seed():void{
  $country=(int)$this->pdo->query("SELECT id FROM countries WHERE code='LT' LIMIT 1")->fetchColumn();
  $rows=[['Baltic Retail','Vilnius','retail','growth',6],['Kauno Prekyba','Kaunas','retail','balanced',4],['Transa LT','Kaunas','logistics','growth',8],['Klaipėdos Logistika','Klaipėda','logistics','balanced',7],['Nord Gamyba','Šiauliai','manufacturing','cautious',10],['Aukštaitijos Gamyba','Panevėžys','manufacturing','balanced',8],['Miesto Paslaugos','Vilnius','services','growth',5],['Verslo Servisas','Kaunas','services','cautious',3]];
  foreach($rows as $x){
   $exists=$this->one('SELECT id FROM companies WHERE name=?',[$x[0]]);
   if($exists){$id=(int)$exists['id'];$current=$this->one('SELECT company_type FROM companies WHERE id=?',[$id]);if(($current['company_type']??'')!=='ai')throw new \RuntimeException('AI įmonės pavadinimas jau naudojamas žaidėjo.');$bank=(float)($this->one('SELECT COALESCE(SUM(balance),0) b FROM company_bank_accounts WHERE company_id=? AND is_active=1',[$id])['b']??0);if($bank>0){$this->pdo->prepare("UPDATE companies SET status='active' WHERE id=? AND company_type='ai' AND status='bankrupt'")->execute([$id]);$this->pdo->prepare("UPDATE ai_company_profiles SET months_in_loss=0 WHERE company_id=?")->execute([$id]);}}
   else{$capital=25000+$x[4]*4000;$s=$this->pdo->prepare("INSERT INTO companies(country_id,name,city,industry,starting_capital,company_type,status,cash,assets) VALUES(?,?,?,?,?,'ai','active',?,?)");$s->execute([$country,$x[0],$x[1],$x[2],$capital,$capital,$capital]);$id=(int)$this->pdo->lastInsertId();}
   $this->pdo->prepare('INSERT INTO company_employment(company_id) VALUES(?) ON DUPLICATE KEY UPDATE company_id=VALUES(company_id)')->execute([$id]);
   $this->pdo->prepare('INSERT INTO company_trade_profiles(company_id,import_enabled,export_enabled) VALUES(?,1,1) ON DUPLICATE KEY UPDATE import_enabled=1,export_enabled=1')->execute([$id]);
   $this->pdo->prepare('INSERT INTO ai_company_profiles(company_id,strategy,target_employees) VALUES(?,?,?) ON DUPLICATE KEY UPDATE strategy=VALUES(strategy)')->execute([$id,$x[3],$x[4]]);
   $this->ensureBank($id,(float)($this->one('SELECT starting_capital FROM companies WHERE id=?',[$id])['starting_capital']??0));
   $this->ensureEmployees($id,(int)$x[4]);
   $this->ensureAccountant($id);
   $this->ensureInventory($id,(string)$x[2]);
   $this->ensureOperations($id,(string)$x[1],(string)$x[2]);
   $this->syncEmployment($id);
  }
 }
 public function runMonth():void{
  $this->seed();$s=$this->pdo->query("SELECT c.*,a.strategy,a.target_employees,a.months_in_loss FROM companies c JOIN ai_company_profiles a ON a.company_id=c.id WHERE c.company_type='ai' AND c.status='active'");
  foreach($s as $c){
   $id=(int)$c['id'];$profit=(float)$c['monthly_profit'];$q=$this->pdo->prepare('SELECT COALESCE(SUM(balance),0) FROM company_bank_accounts WHERE company_id=? AND is_active=1');$q->execute([$id]);$cash=(float)$q->fetchColumn();$months=$profit<0?(int)$c['months_in_loss']+1:0;$target=(int)$c['target_employees'];
   if($profit>2500&&$c['strategy']==='growth')$target++;if(($profit<0||$cash<5000)&&$target>1)$target--;
   $this->resizeEmployees($id,$target);$this->ensureAccountant($id);$this->restock($id,(string)$c['industry'],$cash);$this->syncEmployment($id);
   $q=$this->pdo->prepare('SELECT COALESCE(SUM(balance),0) FROM company_bank_accounts WHERE company_id=? AND is_active=1');$q->execute([$id]);$cashAfter=(float)$q->fetchColumn();
   $this->pdo->prepare('UPDATE ai_company_profiles SET target_employees=?,months_in_loss=? WHERE company_id=?')->execute([$target,$months,$id]);
   if($cashAfter<=0&&$months>=6)$this->pdo->prepare("UPDATE companies SET status='bankrupt' WHERE id=?")->execute([$id]);
  }
 }
 private function ensureBank(int $id,float $capital):void{
  if($this->one('SELECT id FROM company_bank_accounts WHERE company_id=? AND is_active=1',[$id]))return;
  $b=$this->pdo->query("SELECT id,code FROM banks WHERE is_active=1 ORDER BY id LIMIT 1")->fetch();if(!$b)return;
  $code=strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',(string)$b['code']),0,2));$number='LT'.str_pad((string)(10+($id%89)),2,'0',STR_PAD_LEFT).str_pad($code,2,'0').str_pad((string)$b['id'],4,'0',STR_PAD_LEFT).str_pad((string)$id,8,'0',STR_PAD_LEFT);
  $this->pdo->prepare('INSERT INTO company_bank_accounts(company_id,bank_id,account_number,currency,balance,is_primary) VALUES(?,?,?,?,?,1)')->execute([$id,$b['id'],$number,'EUR',$capital]);$aid=(int)$this->pdo->lastInsertId();
  $this->pdo->prepare("INSERT INTO bank_account_transactions(account_id,transaction_type,amount,balance_after,reference_type,description,game_date) VALUES(?,?,?,?,?,?,(SELECT game_date FROM game_clock WHERE id=1))")->execute([$aid,'capital_contribution',$capital,$capital,'ai_seed','AI pradinis kapitalas']);
 }
 private function ensureEmployees(int $id,int $target):void{if((int)($this->one("SELECT COUNT(*) c FROM company_employees WHERE company_id=? AND status='active'",[$id])['c']??0)>0)return;$this->resizeEmployees($id,$target);}
 private function ensureAccountant(int $id):void{
  if($this->one("SELECT e.id FROM company_employees e JOIN job_roles r ON r.id=e.role_id WHERE e.company_id=? AND e.status='active' AND r.automation_type='accounting' LIMIT 1",[$id]))return;
  $role=$this->one("SELECT id,base_salary FROM job_roles WHERE automation_type='accounting' LIMIT 1");if(!$role)return;$date=(string)$this->pdo->query('SELECT game_date FROM game_clock WHERE id=1')->fetchColumn();$salary=max((float)$role['base_salary'],$this->param('minimum_wage'));
  $this->pdo->prepare("INSERT INTO company_employees(company_id,role_id,full_name,salary,skill,status,hired_at) VALUES(?,?,?,?,85,'active',?)")->execute([$id,$role['id'],'AI buhalteris',$salary,$date]);
 }
 private function resizeEmployees(int $id,int $target):void{
  $current=(int)($this->one("SELECT COUNT(*) c FROM company_employees e JOIN job_roles r ON r.id=e.role_id WHERE e.company_id=? AND e.status='active' AND (r.automation_type IS NULL OR r.automation_type<>'accounting')",[$id])['c']??0);$industry=(string)($this->one('SELECT industry FROM companies WHERE id=?',[$id])['industry']??'retail');$roleCode=['retail'=>'seller','logistics'=>'driver','manufacturing'=>'production','services'=>'manager'][$industry]??'seller';$role=(int)($this->one('SELECT id FROM job_roles WHERE code=?',[$roleCode])['id']??0);if(!$role)return;$salary=max($this->param('minimum_wage'),$this->param('average_wage'));
  while($current<$target){$date=(string)$this->pdo->query('SELECT game_date FROM game_clock WHERE id=1')->fetchColumn();$this->pdo->prepare("INSERT INTO company_employees(company_id,role_id,full_name,salary,skill,status,hired_at) VALUES(?,?,?,?,70,'active',?)")->execute([$id,$role,'AI darbuotojas '.($current+1),$salary,$date]);$current++;}
  while($current>$target){$e=$this->one("SELECT e.id FROM company_employees e JOIN job_roles r ON r.id=e.role_id WHERE e.company_id=? AND e.status='active' AND (r.automation_type IS NULL OR r.automation_type<>'accounting') ORDER BY e.id DESC LIMIT 1",[$id]);if(!$e)break;$this->pdo->prepare("UPDATE company_employees SET status='dismissed' WHERE id=?")->execute([$e['id']]);$current--;}
 }
 private function ensureInventory(int $id,string $industry):void{
  $p=$this->one('SELECT * FROM products WHERE industry=? ORDER BY id LIMIT 1',[$industry]);if(!$p)return;
  $inv=$this->one('SELECT quantity FROM company_inventory WHERE company_id=? AND product_id=?',[$id,$p['id']]);if($inv)return;
  $qty=200;$this->pdo->prepare('INSERT INTO company_inventory(company_id,product_id,quantity,average_cost,sale_price) VALUES(?,?,?,?,?)')->execute([$id,$p['id'],$qty,$p['base_cost'],$p['base_price']]);
 }
 private function ensureOperations(int $id,string $city,string $industry):void{
  $types=['retail'=>'shop','logistics'=>'warehouse','manufacturing'=>'factory','services'=>'office'];$type=$types[$industry]??'office';
  $roleCode=['retail'=>'seller','logistics'=>'driver','manufacturing'=>'production','services'=>'manager'][$industry]??'seller';
  if(!$this->one("SELECT e.id FROM company_employees e JOIN job_roles r ON r.id=e.role_id WHERE e.company_id=? AND e.status='active' AND r.code=? LIMIT 1",[$id,$roleCode])){
   $role=$this->one('SELECT id,base_salary FROM job_roles WHERE code=?',[$roleCode]);if($role)$this->pdo->prepare("INSERT INTO company_employees(company_id,role_id,full_name,salary,skill,status,hired_at) VALUES(?,?,?,?,70,'active',(SELECT game_date FROM game_clock WHERE id=1))")->execute([$id,$role['id'],'AI darbuotojas',max((float)$role['base_salary'],$this->param('minimum_wage'))]);
  }
  if(!$this->one("SELECT id FROM company_properties WHERE company_id=? AND status='active' AND property_type=?",[$id,$type]))$this->pdo->prepare('INSERT INTO company_properties(company_id,property_type,city,area_m2,monthly_rent) VALUES(?,?,?,?,?)')->execute([$id,$type,$city,100,500]);
  $needed=in_array($industry,['retail','manufacturing'],true)?['electricity','water']:['electricity'];
  foreach($needed as $utility)if(!$this->one('SELECT id FROM company_utility_contracts WHERE company_id=? AND utility_type=? AND is_active=1 AND monthly_usage>0',[$id,$utility]))$this->pdo->prepare('INSERT INTO company_utility_contracts(company_id,utility_type,monthly_usage) VALUES(?,?,100) ON DUPLICATE KEY UPDATE is_active=1,monthly_usage=IF(monthly_usage>0,monthly_usage,100)')->execute([$id,$utility]);
 }
 private function restock(int $id,string $industry,float $cash):void{
  if($cash<5000)return;$p=$this->one('SELECT p.*,COALESCE(i.quantity,0) qty FROM products p LEFT JOIN company_inventory i ON i.product_id=p.id AND i.company_id=? WHERE p.industry=? ORDER BY p.id LIMIT 1',[$id,$industry]);if(!$p||(float)$p['qty']>=80)return;
  $qty=150;$cost=round($qty*(float)$p['base_cost'],2);if($cost>$cash*.25)return;
  $flow=new MoneyFlowService($this->pdo);$own=!$this->pdo->inTransaction();try{if($own)$this->pdo->beginTransaction();$flow->companyToSector($id,'DOMESTIC_BUSINESS',$cost,'supplier_payment','AI atsargų papildymas');$old=(float)$p['qty'];$avg=(float)($this->one('SELECT average_cost FROM company_inventory WHERE company_id=? AND product_id=?',[$id,$p['id']])['average_cost']??$p['base_cost']);$new=$old+$qty;$newAvg=(($old*$avg)+$cost)/$new;$this->pdo->prepare('INSERT INTO company_inventory(company_id,product_id,quantity,average_cost,sale_price) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),average_cost=VALUES(average_cost)')->execute([$id,$p['id'],$new,$newAvg,$p['base_price']]);if($own)$this->pdo->commit();}catch(\Throwable$e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();}
 }
 private function syncEmployment(int $id):void{$x=$this->one("SELECT COUNT(*) c,COALESCE(AVG(salary),0) a FROM company_employees WHERE company_id=? AND status='active'",[$id]);$this->pdo->prepare('INSERT INTO company_employment(company_id,employees,average_salary) VALUES(?,?,?) ON DUPLICATE KEY UPDATE employees=VALUES(employees),average_salary=VALUES(average_salary)')->execute([$id,$x['c'],$x['a']]);}
 private function param(string $k):float{$r=$this->one('SELECT value FROM economic_parameters WHERE parameter_key=?',[$k]);return(float)($r['value']??0);}
 private function one(string $sql,array $p=[]):array|false{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetch();}
}
