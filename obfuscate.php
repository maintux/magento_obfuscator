<?php
define('DS', DIRECTORY_SEPARATOR);
require_once(__DIR__ . DS . "PhpObfuscator.php");
$obfuscator = new \Fluency\Component\Obfuscator\PhpObfuscator();
$shortopts = "d:p:n:m:ah";
$longopts  = array("project:", "pool:", "namespace:", "module:", "all_modules", "help");
$options = getopt($shortopts, $longopts);

function help(){
  echo "Usage:\n";
  echo "php ./obfuscate.php [switches]\n";
  echo "  -d, --project\t\tThe project base directory\n";
  echo "  -p, --pool\t\tThe code pool [core|community|local]\n";
  echo "  -n, --namespace\tThe modules namespace\n";
  echo "  -m, --module\t\tThe module name - Optional if all_modules specified\n";
  echo "  -a, --all_modules\tObfuscate all modules in namespace - Optional\n";
  echo "\n";
}

if(isset($options['h']) || isset($options['help'])){
  help();
  exit();
}

$project = isset($options['d']) ? $options['d'] : (isset($options['project']) ? $options['project'] : null);
$pool = isset($options['p']) ? $options['p'] : (isset($options['pool']) ? $options['pool'] : null);
$namespace = isset($options['n']) ? $options['n'] : (isset($options['namespace']) ? $options['namespace'] : null);
$module = isset($options['m']) ? $options['m'] : (isset($options['module']) ? $options['module'] : null);
$all_modules = (isset($options['a']) || isset($options['all_modules']));

if(!$project){
  echo "You must specify a project\n";
  help();
  exit();
}
if(!$pool){
  echo "You must specify a pool\n";
  help();
  exit();
}
if(!$namespace){
  echo "You must specify a namespace\n";
  help();
  exit();
}
if(!file_exists($project)){
  echo "Project {$project} not found!\n\n";
  exit();
}
$base_path = $project . DS . "app" . DS . "code" . DS . $pool . DS . $namespace;
if(!file_exists($base_path)){
  echo "Namespace {$namespace} not found in pool {$pool}!\n\n";
  exit();
}
if($module && !file_exists($base_path . DS . $module)){
  echo "Module {$module} not found!\n";
  exit();
}
if(!$module && !$all_modules){
  echo "You must specify a module or use --all_modules flag.\n\n";
  help();
  exit();
}

function rrmdir($dir) {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (is_dir($dir."/".$object))
          rrmdir($dir."/".$object);
        else
          unlink($dir."/".$object);
      }
    }
    rmdir($dir);
  }
}

$modules = $all_modules ? array_slice(scandir($base_path), 2) : array($module);
foreach($modules as $_m){
  $org_path = $base_path . DS . $_m . "-org";
  if(is_dir($base_path . DS . $_m)){
    rename($base_path . DS . $_m, $org_path);
    $obf_path = $base_path . DS . $_m;
    rrmdir($obf_path);
    @mkdir($obf_path);
    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($org_path, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST,
      RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
    );
    foreach($iter as $path => $dir){
      if(preg_match("/-obf$/", $dir)) continue;
      if (is_dir($path)){
        mkdir(str_replace($org_path, $obf_path, $path));
      }
      else{
        if(strstr($path, '.php')){
          $obfuscated_file_name = $obfuscator->obfuscate($path);
          $new_obfuscated_file_name = str_replace('.obf', '', str_replace($org_path, $obf_path, $obfuscated_file_name));
          rename($obfuscated_file_name, $new_obfuscated_file_name);
        }
        else{
          copy($path, str_replace($org_path, $obf_path, $path));
        }
      }
    }
  }
}