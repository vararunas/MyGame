<?php
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','1');
register_shutdown_function(function():void{$e=error_get_last();if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){http_response_code(200);echo '<pre style="background:#28151a;color:#ff9aa8;padding:20px;font:14px monospace;white-space:pre-wrap">PHP klaida: '.htmlspecialchars($e['message']).'\nFailas: '.htmlspecialchars($e['file']).'\nEilutė: '.(int)$e['line'].'</pre>';}});
session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$path=__DIR__.'/../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;});
use MyGame\Infrastructure\Database\Connection;use MyGame\Infrastructure\State\DatabaseStateRepository;
$error=null;$notice=null;$_SESSION['csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];
try{$repo=new DatabaseStateRepository(Connection::make());$companyId=1;$repo->register($companyId);
if($_SERVER['REQUEST_METHOD']==='POST'){if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Neteisinga saugos užklausa.');$a=(string)($_POST['action']??'');
if($a==='property'){$repo->addProperty($companyId,(string)$_POST['type'],trim((string)$_POST['city']),(float)str_replace(',','.',(string)$_POST['area']);$notice='Patalpos išnuomotos.';}
elseif($a==='employment'){$repo->setEmployees($companyId,(int)$_POST['employees'],(float)str_replace(',','.',(string)$_POST['salary']);$notice='Darbo duomenys atnaujinti.';}
elseif($a==='utility'){$repo->addUtility($companyId,(string)$_POST['utility'],(float)str_replace(',','.',(string)$_POST['usage']);$notice='Komunalinė sutartis atnaujinta.';}
elseif($a==='trade'){$repo->toggleTrade($companyId,isset($_POST['import']),isset($_POST['export']));$notice='Prekybos leidimai atnaujinti.';}}
$d=$repo->dashboard($companyId);$costs=$repo->monthlyEstimate($companyId);}catch(Throwable $e){$error=$e->getMessage();$d=['company'=>['name'=>'Įmonė'],'reg'=>null,'emp'=>null,'props'=>[],'utils'=>[],'trade'=>null,'obligations'=>[]];$costs=['rent'=>0,'utilities'=>0,'payroll'=>0,'payroll_tax'=>0,'total'=>0];}
require __DIR__.'/../src/Presentation/state.php';
