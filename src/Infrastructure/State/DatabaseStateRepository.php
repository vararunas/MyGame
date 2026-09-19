<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\State;
use PDO;
final class DatabaseStateRepository{
 public function __construct(private PDO $pdo){}
 public function dashboard(int $companyId):array{
  $company=$this->one('SELECT * FROM companies WHERE id=?',[$companyId]); if(!$company)throw new \RuntimeException('Įmonė nerasta.');
  $reg=$this->one('SELECT * FROM company_registrations WHERE company_id=?',[$companyId]);
  $emp=$this->one('SELECT * FROM company_employment WHERE company_id=?',[$companyId]);
  $props=$this->all("SELECT * FROM company_properties WHERE company_id=? AND status='active' ORDER BY id DESC",[$companyId]);
  $utils=$this->all("SELECT * FROM company_utility_contracts WHERE company_id=? AND is_active=1 ORDER BY utility_type",[$companyId]);
  $trade=$this->one('SELECT * FROM company_trade_profiles WHERE company_id=?',[$companyId]);
  $obligations=$this->all("SELECT *,GREATEST(0,DATEDIFF(NOW(),due_at)) late_days FROM company_obligations WHERE company_id=? ORDER BY status='paid',due_at",[$companyId]);
  return compact('company','reg','emp','props','utils','trade','obligations');
 }
 public function register(int $companyId):void{$exists=$this->one('SELECT id FROM company_registrations WHERE company_id=?',[$companyId]);if($exists)return;$code='LT'.str_pad((string)$companyId,9,'0',STR_PAD_LEFT);$this->pdo->prepare('INSERT INTO company_registrations(company_id,registration_code) VALUES(?,?)')->execute([$companyId,$code]);$this->pdo->prepare('INSERT IGNORE INTO company_employment(company_id,employees,average_salary) VALUES(?,0,0)')->execute([$companyId]);$this->pdo->prepare('INSERT IGNORE INTO company_trade_profiles(company_id) VALUES(?)')->execute([$companyId]);}
 public function addProperty(int $companyId,string $type,string $city,float $area):void{$key=['office'=>'commercial_rent','shop'=>'commercial_rent','warehouse'=>'warehouse_rent','factory'=>'industrial_rent'][$type]??null;if(!$key||$area<=0)throw new \RuntimeException('Neteisingi patalpų duomenys.');$rate=$this->parameter($key);$rent=round($area*$rate,2);$this->pdo->prepare('INSERT INTO company_properties(company_id,property_type,city,area_m2,monthly_rent) VALUES(?,?,?,?,?)')->execute([$companyId,$type,$city,$area,$rent]);}
 public function setEmployees(int $companyId,int $count,float $salary):void{if($count<0||$salary<0)throw new \RuntimeException('Neteisingi darbuotojų duomenys.');$min=$this->parameter('minimum_wage');if($count>0&&$salary<$min)throw new \RuntimeException('Atlyginimas negali būti mažesnis už valstybės minimumą.');$this->pdo->prepare('INSERT INTO company_employment(company_id,employees,average_salary) VALUES(?,?,?) ON DUPLICATE KEY UPDATE employees=VALUES(employees),average_salary=VALUES(average_salary)')->execute([$companyId,$count,$salary]);}
 public function toggleTrade(int $companyId,bool $import,bool $export):void{$this->pdo->prepare('INSERT INTO company_trade_profiles(company_id,import_enabled,export_enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE import_enabled=VALUES(import_enabled),export_enabled=VALUES(export_enabled)')->execute([$companyId,$import?1:0,$export?1:0]);}
 public function addUtility(int $companyId,string $type,float $usage):void{if(!in_array($type,['electricity','gas','water','sewerage'],true)||$usage<0)throw new \RuntimeException('Neteisinga komunalinė paslauga.');$this->pdo->prepare('INSERT INTO company_utility_contracts(company_id,utility_type,monthly_usage) VALUES(?,?,?) ON DUPLICATE KEY UPDATE monthly_usage=VALUES(monthly_usage),is_active=1')->execute([$companyId,$type,$usage]);}
 public function monthlyEstimate(int $companyId):array{$d=$this->dashboard($companyId);$rent=array_sum(array_map(fn($x)=>(float)$x['monthly_rent'],$d['props']));$utilities=0;foreach($d['utils'] as $x)$utilities+=(float)$x['monthly_usage']*$this->parameter((string)$x['utility_type']);$payroll=($d['emp']?(int)$d['emp']['employees']*(float)$d['emp']['average_salary']:0);$burden=$payroll*$this->parameter('payroll_burden')/100;return ['rent'=>$rent,'utilities'=>round($utilities,2),'payroll'=>$payroll,'payroll_tax'=>round($burden,2),'total'=>round($rent+$utilities+$payroll+$burden,2)];}
 private function parameter(string $key):float{$r=$this->one('SELECT value FROM economic_parameters WHERE parameter_key=? ORDER BY id DESC LIMIT 1',[$key]);return (float)($r['value']??0);}
 private function one(string $sql,array $p=[]):array|false{$s=$this->pdo->prepare($sql);$s->execute($p);return $s->fetch();}
 private function all(string $sql,array $p=[]):array{$s=$this->pdo->prepare($sql);$s->execute($p);return $s->fetchAll();}
}
