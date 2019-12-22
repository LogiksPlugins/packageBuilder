<?php
if(!defined('ROOT')) exit('No direct script access allowed');

$appPath = ROOT.APPS_FOLDER.CMS_SITENAME."/";
$configFile = "logiks.json";

$noShow = ["z","z1"];

switch($_GET['action']) {
    case "listPackages":
        $fs = [
                "modules"=>$appPath."pluginsDev/modules/",
                "vendors"=>$appPath."pluginsDev/vendors/",
            ];
        $fss = [];
        foreach($fs as $a=>$dir) {
            $fs0 = scandir($dir);
            $fs0 = array_slice($fs0,2);
            
            foreach($fs0 as $k=>$f) {
                unset($fs0[$k]);
                if(in_array($f,$noShow)) {
                    continue;
                }
                $fs0["{$a}/{$f}"] = "ok";
                
                if($a=="modules") {
                    if(!file_exists($dir."{$f}/logiks.json")) {
                        $fs0["{$a}/{$f}"] = "nok";
                    }
                } elseif($a=="vendors") {
                    if(!file_exists($dir."{$f}/boot.php")) {
                        $fs0["{$a}/{$f}"] = "nok";
                    }
                }
            }
            
            $fss[$a] = $fs0;//array_merge($fss, $fs0);
        }
        
        printServiceMsg($fss);
        break;
    case "loadPackage":
        if(isset($_GET['package'])) {
            include_once __DIR__."/comps/viewer.php";
        } else {
            echo "Package ID Not Found";
        }
        break;
    case "updatePackage":
        if(isset($_GET['package']) && isset($_POST) && count($_POST)>0) {
            $packageConfig = $appPath."pluginsDev/{$_GET['package']}/{$configFile}";
            $_POST['type'] = dirname($_GET['package']);
            
            $config = [];
            if(file_exists($packageConfig)) {
                $config = json_decode(file_get_contents($packageConfig),true);
            }
            
            if(!$config) {
                $config = [];
            }
            
            if(isset($_POST['private'])) {
                $_POST['private'] = ($_POST['private']=="true")?true:false;
            }
            if(isset($_POST['dependencies'])) {
                $dependencies = $_POST['dependencies'];
                $_POST['dependencies'] = [];
                foreach($dependencies['package'] as $a=>$b) {
                    $_POST['dependencies'][$b]=$dependencies['vers'][$a];
                }
            }
            if(isset($_POST['authors'])) {
                $authors = $_POST['authors'];
                $_POST['authors'] = [];
                
                foreach($authors['name'] as $a=>$b) {
                    $_POST['authors'][]=[
                            "name"=>$authors['name'][$a],
                            "email"=>$authors['email'][$a],
                            "authorid"=>$authors['authorid'][$a],
                        ];
                }
            }
            if(isset($_POST['repository'])) {
                $url = $_POST['repository'];
                $_POST['repository'] = [];
                $_POST['repository']['url'] = $url;
                $_POST['repository']['type'] = "git";
            }
            
            
            $config = array_merge($config, $_POST);
            $a = file_put_contents($packageConfig, json_encode($config,JSON_PRETTY_PRINT));
            
            if($a) printServiceMsg(["status"=>"success"]);
            else {
                printServiceMsg(["status"=>"error","msg"=>"Error updating properties"]);
            }
        } else {
            printServiceMsg(["status"=>"error","msg"=>"Package not defined"]);
        }
        break;
    case "findIssues":
        $dir = $appPath."pluginsDev/modules/";
        $fss = scandir($dir);
        $fss = array_slice($fss,2);
        $tableList = _db()->get_tableList();
        
        $counter = 0;
        $tableCount = 0;
        $finalTableUsedList = [];
        
        $finalModuleInfo = [];
        foreach($fss as $f) {
            if($f=="z1") continue;
            // $dir1 = $dir."{$f}/.git/";
            // if(file_exists($dir1) && is_dir($dir1)) {
            //     $fc = $dir1."config";
            //     $dataConfig = file_get_contents($fc);
            //     $dataConfig = explode("\n",$dataConfig);
            //     println($f.",".trim(str_replace("url = ","",$dataConfig[6])));
            // }
            $_ENV['MODNAME'] = $f;
            $reqs = [
                $dir."{$f}/Readme.md",
                $dir."{$f}/logiks.json",
                // $dir."{$f}/.install/",
                $dir."{$f}/.git/",
            ];
            $out = [];
            foreach($reqs as $a=>$b) {
                if(!file_exists($b)) {
                    $out[] = "Missing : ".basename($b);
                }
            }
            $tables = array_filter($tableList, "checkTableName", ARRAY_FILTER_USE_BOTH);
            if(count($tables)>0) {
                $tableCount+=count($tables);
                $finalTableUsedList = array_merge($finalTableUsedList,$tables);
                if(!file_exists($dir."{$f}/.install/") || !file_exists($dir."{$f}/.install/schema.sql")) {
                    $out[] = "Missing : .install (".count($tables)." Tables)";
                }
            }
            
            $f1 = $dir."{$f}/logiks.json";
            $f2 = $dir."{$f}/.git/config";
            if(file_exists($f1)) {
                $fdata1 = json_decode(file_get_contents($f1),true);
                
                if(strtolower($f)!=strtolower(str_replace(".git","",basename($fdata1['homepage'])))) {
                    $out[] = "Logiks.JSON : HOMEPAGE URL Error";
                }
                if(strtolower($f)!=strtolower(str_replace(".git","",basename($fdata1['repository']['url'])))) {
                    $out[] = "Logiks.JSON : REPO URL Error";
                }
            }
            if(file_exists($f2)) {
                $fdata2 = file_get_contents($f2);
                
                $uri = explode("\n",substr($fdata2,strpos($fdata2,'[remote "origin"]'),strpos($fdata2,'fetch = ')));
                $uri = explode("git@",trim($uri[1]));
                $repoName = str_replace(".git","", basename(end($uri)));
                if(strtolower($repoName)!=strtolower($f)) {
                    $out[] = "Repo Mismatch : found {$repoName}, seeking {$f}";
                }
            }
            
            if(count($out)>0) {
                $finalModuleInfo[] = "<b>$f</b><br>".implode("<br>",$out);
                $counter++;
            }
        }
        
        $errorTables = array_diff($tableList,$finalTableUsedList);
        
        $_ENV['MODNAME'] = "do";
        $errorTables = array_filter($errorTables, "checkTableNoName", ARRAY_FILTER_USE_BOTH);
        $_ENV['MODNAME'] = "data";
        $errorTables = array_filter($errorTables, "checkTableNoName", ARRAY_FILTER_USE_BOTH);
        $_ENV['MODNAME'] = "user";
        $errorTables = array_filter($errorTables, "checkTableNoName", ARRAY_FILTER_USE_BOTH);
        $_ENV['MODNAME'] = "temp";
        $errorTables = array_filter($errorTables, "checkTableNoName", ARRAY_FILTER_USE_BOTH);
        $_ENV['MODNAME'] = "my";
        $errorTables = array_filter($errorTables, "checkTableNoName", ARRAY_FILTER_USE_BOTH);
        $_ENV['MODNAME'] = "log";
        $errorTables = array_filter($errorTables, "checkTableNoName", ARRAY_FILTER_USE_BOTH);
        $_ENV['MODNAME'] = "sys";
        $errorTables = array_filter($errorTables, "checkTableNoName", ARRAY_FILTER_USE_BOTH);
        
        echo "
            <h3 style='text-align: center;'>&nbsp;Package System Analysis</h3>
            <ul class='nav nav-tabs nav-justified'>
              <li class='active'><a href='#tab1'>Summary</a></li>
              <li><a href='#tab2'>Module Issues</a></li>
              <li><a href='#tab3'>Database</a></li>
              <li><a href='#tab4'>More</a></li>
            </ul>
            <div class='tab-content'>
                <div id='tab1' class='tab-pane fade paddedInfo in active'>
                    <h5>Summary</h5>
                    <div class='well'>
                        Error Found : {$counter}<br>
                        Total Tables : ".count($tableList)."<br>
                        Total Table Used : ".(count($tableList)-count($errorTables))."<br>
                    </div>
                </div>
                <div id='tab2' class='tab-pane fade paddedInfo'>
                    ".((count($finalModuleInfo)>0)?"<h5 class='text-center'>Modules with issues found</h5>".implode("<hr>",$finalModuleInfo):"<h5 class='text-center'>No modules found with issues</h5>")."
                </div>
                <div id='tab3' class='tab-pane fade paddedInfo'>
                    ".((count($errorTables)>0)?"<h5 class='text-center'>Table whose connection could not be found to any module</h5><ul class='list-group'><li class='list-group-item col-md-3'>".implode("</li><li class='list-group-item col-md-3'>",array_values($errorTables))."</li></ul>":"<h5 class='text-center'>No db tables with issue found</h5>")."
                    
                </div>
                <div id='tab4' class='tab-pane fade paddedInfo'>
                    Nothing yet
                </div>
            </div>
            ";
        break;
}



function checkTableName($a,$b) {
    if($_ENV['MODNAME']=="editorInvoice") $_ENV['MODNAME'] = "invoices";
    elseif($_ENV['MODNAME']=="editorPurchase") $_ENV['MODNAME'] = "purchase";
    elseif($_ENV['MODNAME']=="editorQuotation") $_ENV['MODNAME'] = "quotations";
    elseif($_ENV['MODNAME']=="docman") $_ENV['MODNAME'] = "docs";
    
    $pieces = preg_split('/(?=[A-Z])/',$_ENV['MODNAME']);
    $pieces = strtolower(implode("_",$pieces));
    $_ENV['MODNAME'] = $pieces;
    
    if(((strpos($a,"{$_ENV['MODNAME']}_")===0) || (strpos($a,"{$_ENV['MODNAME']}tbl_")===0) || $a==$_ENV['MODNAME'] || $a=="{$_ENV['MODNAME']}tbl" || $a=="{$_ENV['MODNAME']}_tbl")) {
        return true;
    }
    
    $_ENV['MODNAME'] = str_replace("_manager","",$_ENV['MODNAME']);
    if(((strpos($a,"{$_ENV['MODNAME']}_")===0) || (strpos($a,"{$_ENV['MODNAME']}tbl_")===0) || $a==$_ENV['MODNAME'] || $a=="{$_ENV['MODNAME']}tbl" || $a=="{$_ENV['MODNAME']}_tbl")) {
        return true;
    }
    
    return false;
}
function checkTableNoName($a,$b) {
    $pieces = preg_split('/(?=[A-Z])/',$_ENV['MODNAME']);
    $pieces = strtolower(implode("_",$pieces));
    $_ENV['MODNAME'] = $pieces;
    
    return !(((strpos($a,"{$_ENV['MODNAME']}_")===0) || (strpos($a,"{$_ENV['MODNAME']}tbl_")===0) || $a==$_ENV['MODNAME'] || $a=="{$_ENV['MODNAME']}tbl" || $a=="{$_ENV['MODNAME']}_tbl"));
}
function getPackageConfig($key) {
    if(!isset($_ENV['PACKAGE_CONFIG'])) $_ENV['PACKAGE_CONFIG'] =[];
    if(isset($_ENV['PACKAGE_CONFIG'][$key])) {
        if($key=="repository") {
            if(isset($_ENV['PACKAGE_CONFIG'][$key]['url'])) return $_ENV['PACKAGE_CONFIG'][$key]['url'];
            else return "";
        }
        return $_ENV['PACKAGE_CONFIG'][$key];
    }
    else return "";
}
?>