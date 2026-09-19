<?php
declare(strict_types=1);session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$p=__DIR__.'/../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require$p;});
use MyGame\Infrastructure\Database\Connection;use MyGame\Infrastructure\Dashboard\DatabaseDashboardRepository;use MyGame\Infrastructure\Economy\GameEconomyEngine;use MyGame\Infrastructure\Economy\AiCompanyEngine;
$error=null;$db=Connection::make();$companyId=(int)($_SESSION['company_id']??1);$engine=new GameEconomyEngine($db);(new AiCompanyEngine($db))->seed();$engine->refreshObligations($companyId);try{$d=(new DatabaseDashboardRepository($db))->data($companyId);}catch(Throwable$e){$error=$e->getMessage();$d=null;}require __DIR__.'/../src/Presentation/dashboard.php';
