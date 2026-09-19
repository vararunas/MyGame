<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Dashboard;
use PDO;
final class DatabaseDashboardRepository{
 public function __construct(private PDO $pdo){}
 public function data(int $id):array{
  $company=$this->one('SELECT * FROM companies WHERE id=?',[$id]);if(!$company)throw new \RuntimeException('Įmonė nerasta.');
  $balance=(float)($this->one('SELECT COALESCE(SUM(balance),0) v FROM company_bank_accounts WHERE company_id=? AND is_active=1',[$id])['v']??0);
  $employees=(int)($this->one('SELECT employees FROM company_employment WHERE company_id=?',[$id])['employees']??0);
  $properties=(int)($this->one("SELECT COUNT(*) v FROM company_properties WHERE company_id=? AND status='active'",[$id])['v']??0);
  $loans=$this->one("SELECT COUNT(*) c,COALESCE(SUM(outstanding_principal),0) total,COALESCE(SUM(monthly_payment),0) monthly FROM company_loans WHERE company_id=? AND status='active'",[$id]);
  $due=$this->one("SELECT COUNT(*) c,COALESCE(SUM(amount+penalty_amount),0) total FROM company_obligations WHERE company_id=? AND paid_at IS NULL",[$id]);
  $late=$this->all("SELECT description,amount+penalty_amount total,due_at FROM company_obligations WHERE company_id=? AND status='late' ORDER BY due_at LIMIT 5",[$id]);
  $cycles=$this->all('SELECT * FROM company_monthly_cycles WHERE company_id=? ORDER BY period DESC LIMIT 6',[$id]);
  $market=$this->one('SELECT * FROM market_demand WHERE city=? AND industry=?',[(string)$company['city'],(string)$company['industry']]);
  $competition=(int)($this->one("SELECT COUNT(*) v FROM companies WHERE city=? AND industry=? AND status='active' AND id<>?",[$company['city'],$company['industry'],$id])['v']??0);
  $clock=$this->pdo->query('SELECT * FROM game_clock WHERE id=1')->fetch()?:[];
  return compact('company','balance','employees','properties','loans','due','late','cycles','market','competition','clock');
 }
 private function one(string $sql,array $p=[]):array|false{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetch();}
 private function all(string $sql,array $p=[]):array{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll();}
}
