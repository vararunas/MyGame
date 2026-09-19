<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Economy;
use PDO;
final class AiCompanyEngine{
 public function __construct(private PDO $pdo){}
 public function seed():void{
  $count=(int)$this->pdo->query("SELECT COUNT(*) FROM companies WHERE company_type='ai'")->fetchColumn();if($count>=8)return;
  $country=(int)$this->pdo->query("SELECT id FROM countries WHERE code='LT' LIMIT 1")->fetchColumn();
  $rows=[['Baltic Retail','Vilnius','retail','growth',6],['Kauno Prekyba','Kaunas','retail','balanced',4],['Transa LT','Kaunas','logistics','growth',8],['Klaipėdos Logistika','Klaipėda','logistics','balanced',7],['Nord Gamyba','Šiauliai','manufacturing','cautious',10],['Aukštaitijos Gamyba','Panevėžys','manufacturing','balanced',8],['Miesto Paslaugos','Vilnius','services','growth',5],['Verslo Servisas','Kaunas','services','cautious',3]];
  foreach($rows as $x){$exists=$this->one('SELECT id FROM companies WHERE name=?',[$x[0]]);if($exists)continue;$capital=25000+$x[4]*4000;$s=$this->pdo->prepare("INSERT INTO companies(country_id,name,city,industry,starting_capital,company_type,status,cash,assets) VALUES(?,?,?,?,?,'ai','active',?,?)");$s->execute([$country,$x[0],$x[1],$x[2],$capital,$capital,$capital]);$id=(int)$this->pdo->lastInsertId();$this->pdo->prepare('INSERT INTO company_employment(company_id,employees,average_salary) VALUES(?,?,?)')->execute([$id,$x[4],2200]);$this->pdo->prepare('INSERT INTO company_trade_profiles(company_id,import_enabled,export_enabled) VALUES(?,1,1)')->execute([$id]);$this->pdo->prepare('INSERT INTO ai_company_profiles(company_id,strategy,target_employees) VALUES(?,?,?)')->execute([$id,$x[3],$x[4]]);}
 }
 public function runMonth():void{
  $this->seed();$s=$this->pdo->query("SELECT c.*,a.strategy,a.target_employees,a.months_in_loss FROM companies c JOIN ai_company_profiles a ON a.company_id=c.id WHERE c.company_type='ai' AND c.status='active'");
  foreach($s as $c){$profit=(float)$c['monthly_profit'];$cash=(float)$c['cash'];$months=$profit<0?(int)$c['months_in_loss']+1:0;$target=(int)$c['target_employees'];if($profit>2500&&$c['strategy']==='growth')$target++;if(($profit<0||$cash<5000)&&$target>1)$target--;$salary=$this->param('average_wage');$this->pdo->prepare('UPDATE company_employment SET employees=?,average_salary=? WHERE company_id=?')->execute([$target,$salary,$c['id']]);$this->pdo->prepare('UPDATE ai_company_profiles SET target_employees=?,months_in_loss=? WHERE company_id=?')->execute([$target,$months,$c['id']]);if($cash<0||$months>=6)$this->pdo->prepare("UPDATE companies SET status='bankrupt' WHERE id=?")->execute([$c['id']]);}
 }
 private function param(string $k):float{$r=$this->one('SELECT value FROM economic_parameters WHERE parameter_key=?',[$k]);return(float)($r['value']??0);}
 private function one(string $sql,array $p=[]):array|false{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetch();}
}
