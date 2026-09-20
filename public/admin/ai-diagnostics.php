<?php
declare(strict_types=1);session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$p=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require$p;});
use MyGame\Infrastructure\Database\Connection;use MyGame\Infrastructure\Economy\GameEconomyEngine;use MyGame\Infrastructure\Economy\AiCompanyEngine;
$error=null;$rows=[];$audit=[];$gameDate='—';
try{$db=Connection::make();$engine=new GameEconomyEngine($db);$gameDate=$engine->sync();(new AiCompanyEngine($db))->seed();$audit=$engine->auditAndRepairAiCycles($gameDate);$engine->runAutoAccountingForAll();
$sql="SELECT c.id,c.name,c.city,c.industry,c.status,
COALESCE((SELECT SUM(a.balance) FROM company_bank_accounts a WHERE a.company_id=c.id AND a.is_active=1),0) bank_balance,
(SELECT COUNT(*) FROM company_employees e WHERE e.company_id=c.id AND e.status='active') employees,
(SELECT COUNT(*) FROM company_employees e JOIN job_roles r ON r.id=e.role_id WHERE e.company_id=c.id AND e.status='active' AND r.automation_type='accounting') accountants,
(SELECT COUNT(*) FROM company_obligations o WHERE o.company_id=c.id AND o.paid_at IS NULL) unpaid_count,
COALESCE((SELECT SUM(o.amount+o.penalty_amount) FROM company_obligations o WHERE o.company_id=c.id AND o.paid_at IS NULL),0) unpaid_total,
(SELECT COUNT(*) FROM company_obligations o WHERE o.company_id=c.id AND o.paid_at IS NOT NULL) paid_count,
COALESCE((SELECT SUM(t.amount) FROM state_bank_transactions t WHERE t.company_id=c.id AND t.amount>0),0) state_paid,
(SELECT MIN(o.due_at) FROM company_obligations o WHERE o.company_id=c.id AND o.paid_at IS NULL) next_due,
(SELECT cm.period FROM company_monthly_cycles cm WHERE cm.company_id=c.id ORDER BY cm.period DESC LIMIT 1) cycle_period,
COALESCE((SELECT cm.payroll_cost FROM company_monthly_cycles cm WHERE cm.company_id=c.id ORDER BY cm.period DESC LIMIT 1),0) cycle_payroll,
COALESCE((SELECT cm.payroll_tax FROM company_monthly_cycles cm WHERE cm.company_id=c.id ORDER BY cm.period DESC LIMIT 1),0) cycle_payroll_tax,
COALESCE((SELECT cm.revenue FROM company_monthly_cycles cm WHERE cm.company_id=c.id ORDER BY cm.period DESC LIMIT 1),0) cycle_revenue
FROM companies c WHERE c.company_type='ai' ORDER BY c.name";
$rows=$db->query($sql)->fetchAll();
}catch(Throwable $e){$error=$e->getMessage();}
require __DIR__.'/../../src/Presentation/admin/ai-diagnostics.php';