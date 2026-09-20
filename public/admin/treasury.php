<?php
declare(strict_types=1);session_start();require __DIR__.'/auth.php';
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$p=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require$p;});
use MyGame\Infrastructure\Database\Connection;use MyGame\Infrastructure\State\StateTreasuryRepository;use MyGame\Infrastructure\Economy\GameEconomyEngine;
$error=null;$summary=['institutions'=>[],'total'=>0,'income'=>0,'month'=>0];$transactions=[];$breakdown=[];
try{$db=Connection::make();$engine=new GameEconomyEngine($db);$engine->sync();$engine->runAutoAccountingForAll();$repo=new StateTreasuryRepository($db);$summary=$repo->summary();$transactions=$repo->transactions(100);$breakdown=$repo->breakdown();}catch(Throwable$e){$error=$e->getMessage();}
require __DIR__.'/../../src/Presentation/admin/treasury.php';
