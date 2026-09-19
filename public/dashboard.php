<?php
declare(strict_types=1);session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$p=__DIR__.'/../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require$p;});
use MyGame\Infrastructure\Database\Connection;use MyGame\Infrastructure\Dashboard\DatabaseDashboardRepository;use MyGame\Infrastructure\Economy\GameEconomyEngine;use MyGame\Infrastructure\Economy\AiCompanyEngine;
$error=null;$notice=null;$db=Connection::make();$companyId=(int)($_SESSION['company_id']??1);$_SESSION['dashboard_csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['dashboard_csrf'];$engine=new GameEconomyEngine($db);$engine->sync();(new AiCompanyEngine($db))->seed();$engine->refreshObligations($companyId);
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Neteisinga saugos užklausa.');
  $action=(string)($_POST['action']??'');if($action==='pay_obligation'){$engine->payObligation($companyId,(int)($_POST['obligation_id']??0));$notice='Mokėjimas atliktas. Pinigai pervesti gavėjo institucijai.';$engine->refreshObligations($companyId);}elseif($action==='pay_loan_installment'){$loans=new \MyGame\Infrastructure\Banking\DatabaseLoanRepository($db);$loans->refreshLoanPayments($companyId);$loans->payInstallment($companyId,(int)($_POST['payment_id']??0));$notice='Paskolos įmoka apmokėta.';}
 }
 $d=(new DatabaseDashboardRepository($db))->data($companyId);
}catch(Throwable$e){$error=$e->getMessage();$d=null;}
require __DIR__.'/../src/Presentation/dashboard.php';
