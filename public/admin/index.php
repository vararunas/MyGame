<?php
declare(strict_types=1);
session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$path=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;});
$admin=require __DIR__.'/../../config/admin.php';
use MyGame\Infrastructure\Economy\EconomyRepository;
$repo=new EconomyRepository(); $section=$_GET['section']??'economy'; if(!isset($admin['sections'][$section]))$section='economy'; $parameters=$repo->section($section);
require __DIR__.'/../../src/Presentation/admin/country.php';
