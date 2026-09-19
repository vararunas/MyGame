<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Dashboard;
use PDO;
final class DatabaseDashboardRepository{
 public function __construct(private PDO $pdo){}
 public function data(int $id):array{
  $company=$this->one('SELECT * FROM companies WHERE id=?',[$id]);if(!$company)throw new \RuntimeException('Įmonė nerasta.');
  $balance=(float)($this->one('SELECT COALESCE(SUM(balance),0) v FROM company_bank_accounts WHERE company_id=? AND is_active=1',[$id])['v']??0);
  $employees=(int)($this->one("SELECT COUNT(*) employees FROM company_employees WHERE company_id=? AND status='active'",[$id])['employees']??0);
  $properties=(int)($this->one("SELECT COUNT(*) v FROM company_properties WHERE company_id=? AND status='active'",[$id])['v']??0);
  $loans=$this->one("SELECT COUNT(*) c,COALESCE(SUM(outstanding_principal),0) total,COALESCE(SUM(monthly_payment),0) monthly FROM company_loans WHERE company_id=? AND status='active'",[$id]);
  $due=$this->one("SELECT COUNT(*) c,COALESCE(SUM(amount+penalty_amount),0) total FROM company_obligations WHERE company_id=? AND paid_at IS NULL",[$id]);
  $late=$this->all("SELECT 'state' item_type,id,institution,description,amount+penalty_amount total,amount,penalty_amount,due_at,status FROM company_obligations WHERE company_id=? AND paid_at IS NULL UNION ALL SELECT 'loan' item_type,lp.id,b.name institution,CONCAT('Paskolos įmoka #',lp.installment_no) description,lp.scheduled_amount+lp.penalty_amount total,lp.scheduled_amount amount,lp.penalty_amount,lp.due_at,lp.status FROM loan_payments lp JOIN company_loans l ON l.id=lp.loan_id JOIN banks b ON b.id=l.bank_id WHERE l.company_id=? AND l.status='active' AND lp.paid_at IS NULL AND lp.status IN ('due','late') ORDER BY CASE WHEN status='late' THEN 0 ELSE 1 END,due_at LIMIT 12",[$id,$id]);
  $cycles=$this->all('SELECT * FROM company_monthly_cycles WHERE company_id=? ORDER BY period DESC LIMIT 6',[$id]);
  $market=$this->one('SELECT * FROM market_demand WHERE city=? AND industry=?',[(string)$company['city'],(string)$company['industry']]);
  $competition=(int)($this->one("SELECT COUNT(*) v FROM companies WHERE city=? AND industry=? AND status='active' AND id<>?",[$company['city'],$company['industry'],$id])['v']??0);
  $clock=$this->pdo->query('SELECT * FROM game_clock WHERE id=1')->fetch()?:[];
  return compact('company','balance','employees','properties','loans','due','late','cycles','market','competition','clock');
 }
 private function one(string $sql,array $p=[]):array|false{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetch();}
 private function all(string $sql,array $p=[]):array{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll();}
}
