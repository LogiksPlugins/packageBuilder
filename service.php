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
        $dirList = [
               $appPath."pluginsDev/modules/"=>true,
               //$appPath."plugins/modules/"=>false,
            ];
        
        $counter = 0;
        $tableCount = 0;
        $finalTableUsedList = [];
        $finalModuleInfo = [];
        
        $tableList = _db()->get_tableList();
        
        foreach($dirList as $dir=>$scanModule) {
            $fss = scandir($dir);
            $fss = array_slice($fss,2);
            
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
                if($scanModule) {
                    foreach($reqs as $a=>$b) {
                        if(!file_exists($b)) {
                            $out[] = "Missing : ".basename($b);
                        }
                    }
                }
                
                $tables = array_filter($tableList, "checkTableName", ARRAY_FILTER_USE_BOTH);
                if(count($tables)>0) {
                    $tableCount+=count($tables);
                    $finalTableUsedList = array_merge($finalTableUsedList,$tables);
                    
                    if($scanModule) {
                        if(!file_exists($dir."{$f}/.install/") || !file_exists($dir."{$f}/.install/sql/schema.sql")) {
                            $out[] = "Missing : .install (".count($tables)." Tables)";
                        }
                    }
                }
                
                if($scanModule) {
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
                }
                if(count($out)>0) {
                    $finalModuleInfo[] = "<b>$f</b><br>".implode("<br>",$out);
                    $counter++;
                }
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
    case "findDependency":
        if(isset($_GET['package']) && strlen($_GET['package'])>0) {
            echo "XXX";
        } else {
            echo "Sorry, PackageID not defined";
        }
        break;
    case "checkIssues":
        if(isset($_GET['package']) && strlen($_GET['package'])>0) {
            $packageID = $_GET['package'];
            $packageDir = $appPath."pluginsDev/{$packageID}/";
            $tableList = _db()->get_tableList();
            
            echo getModuleIssues(basename($packageID), $packageDir, $tableList);
        } else {
            echo "Sorry, PackageID not defined";
        }
        break;
    case "refreshInstallFolder":
        if(isset($_GET['package']) && strlen($_GET['package'])>0) {
            $packageID = $_GET['package'];
            $packageDir = $appPath."pluginsDev/{$packageID}/";
            
            if(is_dir($packageDir)) {
                $htmlInstaller = $packageDir.".install/";
                if(file_exists($htmlInstaller) && is_dir($htmlInstaller)) {
                    $fssInstallFiles = scanSubdirFiles($htmlInstaller);
                    
                    $fssCount = count($fssInstallFiles);
                    
                    $htmlInstallerContent = [];
                    foreach($fssInstallFiles as $a) {
                        $a = str_replace("#".$htmlInstaller,"","#".$a);
                        $htmlInstallerContent[] = "<li class='list-group-item'>{$a}</li>";
                    }
                    $htmlInstaller = "Total <b>{$fssCount}</b> files found<ul class='list-group list-group-installer text-left'>".implode("",$htmlInstallerContent)."</ul>";
                } else {
                    $htmlInstaller = "No install folder found in package";
                }
                
                echo $htmlInstaller;
            } else {
                echo "Sorry, PackageID not found";
            }
        } else {
            echo "Sorry, PackageID not defined";
        }
        break;
    case "purgeInstallFolder":
        if(isset($_GET['package']) && strlen($_GET['package'])>0) {
            $packageID = $_GET['package'];
            $packageDir = $appPath."pluginsDev/{$packageID}/";
            
            if(is_dir($packageDir)) {
                $dirInstaller = $packageDir.".install";
                if(is_dir($dirInstaller)) {
                    deleteDir($dirInstaller);
                    echo "Install folder purged successfully";
                } else {
                    echo "Install folder not found";
                }
            } else {
                echo "Sorry, PackageID not found";
            }
        } else {
            echo "Sorry, PackageID not defined";
        }
        break;
    case "buildInstallFolder":
        if(isset($_GET['package']) && strlen($_GET['package'])>0) {
            $packageID = $_GET['package'];
            $packageDir = $appPath."pluginsDev/{$packageID}/";
            
            if(is_dir($packageDir)) {
                $ans = buildInstallFolder($packageDir, $appPath);
                
                echo $ans;
            } else {
                echo "Sorry, PackageID not found";
            }
        } else {
            echo "Sorry, PackageID not defined";
        }
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
    if(!isset($_ENV['PACKAGE_CONFIG'])) $_ENV['PACKAGE_CONFIG'] = [];
    if(isset($_ENV['PACKAGE_CONFIG'][$key])) {
        if($key=="repository") {
            if(isset($_ENV['PACKAGE_CONFIG'][$key]['url'])) return $_ENV['PACKAGE_CONFIG'][$key]['url'];
            else return "";
        }
        return $_ENV['PACKAGE_CONFIG'][$key];
    }
    else return "";
}

function scanSubdirFiles($dir) {
    if(!file_exists($dir) || !is_dir($dir)) return [];
    
    $fss = scandir($dir);
    $fss = array_slice($fss,2);
    
    $list = [];
    foreach($fss as $f) {
        if(is_dir($dir.$f)) {
            $fss1 = scanSubdirFiles($dir.$f."/");
            
            foreach($fss1 as $f1) {
                $list[] = $f1;
            }
        } else {
            $list[] = $dir.$f;
        }
    }
    
    return $list;
}

function getModuleIssues($mod, $packageDir, $tableList = []) {
    $out = [];
    $finalTableUsedList = [];
    
    $_ENV['MODNAME'] = $mod;
    
    $dir = dirname($packageDir)."/";
    $f = $mod;
    
    $reqs = [
        $dir."{$f}/Readme.md",
        $dir."{$f}/logiks.json",
        // $dir."{$f}/.install/",
        $dir."{$f}/.git/",
    ];
    
    foreach($reqs as $a=>$b) {
        if(!file_exists($b)) {
            $out[] = "Missing : ".basename($b);
        }
    }
    
    $tables = array_filter($tableList, "checkTableName", ARRAY_FILTER_USE_BOTH);
    if(count($tables)>0) {
        $tableCount+=count($tables);
        $finalTableUsedList = array_merge($finalTableUsedList,$tables);
        
        if(!file_exists($dir."{$f}/.install/") || !file_exists($dir."{$f}/.install/sql/schema.sql")) {
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
    
    return "<b>Module : $f</b><br><br>".implode("<br>",$out);
}

function buildInstallFolder($packageDir, $appPath) {
    $packageID = basename($packageDir);
    
    $tables = [];
    $tablesData = [];
    
    $tableList = _db()->get_tableList();
    $_ENV['MODNAME'] = $packageID;
    $tables = array_filter($tableList, "checkTableName", ARRAY_FILTER_USE_BOTH);

    $tblKeys = ["do"=>true,"data"=>true,"logs"=>false,"logs"=>false,"sys"=>true];
    foreach($tblKeys as $tblKey=>$withData) {
        $_ENV['MODNAME'] = "{$tblKey}_{$packageID}";
        $tables1 = array_filter($tableList, "checkTableName", ARRAY_FILTER_USE_BOTH);
        
        $tables = array_merge($tables,$tables1);
        if($withData) {
            $tablesData = array_merge($tablesData,$tables1);
        }
    }

    //File System Analysis
    $dirList = [
            "misc/",
            "css/",
            "js/",
            "media/",
            "pluginsDev/dashlets/",
            "pluginsDev/widgets/",
            // "config/features/",
            // "services/",
        ];

    $finalFileList = [];
    foreach($dirList as $d) {
        $baseDir = $appPath.$d;
        $searchDir = scandir($baseDir);
        $searchDir = array_slice($searchDir,2);
        //printArray([$baseDir,$searchDir]);
        
        switch($d) {
            case "media/":
                foreach($searchDir as $dir) {
                    $fss = scandir($baseDir.$dir."/");
                    $fss = array_slice($fss,2);
                    
                    foreach($fss as $ff) {
                        if($ff==$packageID) {
                            $finalFileList[str_replace($appPath,"",$baseDir.$dir."/$ff/")]= "MEDIA";
                        }
                    }
                }
                break;
            case "css/":case "js/":
                foreach($searchDir as $ff) {
                    $fname = basename($ff);
                    
                    if(strpos($fname,"{$packageID}_")===0) {
                        $finalFileList["{$d}{$ff}"]= "FILE";
                    } elseif(strpos($fname,"{$packageID}.")===0) {
                        $finalFileList["{$d}{$ff}"]= "FILE";
                    }
                }
                break;
            default:
                foreach($searchDir as $dir) {
                    if(is_dir($baseDir.$dir)) {
                        $fss = scanSubdirFiles($baseDir.$dir."/");
                    
                        foreach($fss as $ff) {
                            $fname = basename($ff);
                            $dname = basename(dirname($ff));
                            
                            if(in_array(basename($baseDir),["widgets","dashlets","vendors"])) {
                                if(strpos($fname,"{$packageID}_")===0) {
                                    $finalFileList[str_replace($appPath,"",$ff)]= strtoupper(basename($baseDir));
                                } elseif(strpos($fname,"{$packageID}.")===0) {
                                    $finalFileList[str_replace($appPath,"",$ff)]= strtoupper(basename($baseDir));
                                }
                            } else {
                                if($dname==$packageID) {
                                    $finalFileList[str_replace($appPath,"",dirname($ff))]= "DIR";
                                } elseif(strpos($fname,"{$packageID}_")===0) {
                                    $finalFileList[str_replace($appPath,"",$ff)]= "FILE";
                                } elseif(strpos($fname,"{$packageID}.")===0) {
                                    $finalFileList[str_replace($appPath,"",$ff)]= "FILE";
                                }
                            }
                            
                            // printArray([
                            //         $ff,
                            //         $fname,
                            //         $dname
                            //     ]);
                        }
                    } else {
                        $ff = $baseDir.$dir;
                        $fname = basename($ff);
                        
                        if(in_array(basename($baseDir),["widgets","dashlets","vendors"])) {
                            if(strpos($fname,"{$packageID}_")===0) {
                                $finalFileList[str_replace($appPath,"",$ff)]= strtoupper(basename($baseDir));
                            } elseif(strpos($fname,"{$packageID}.")===0) {
                                $finalFileList[str_replace($appPath,"",$ff)]= strtoupper(basename($baseDir));
                            }
                        } else {
                            if(strpos($fname,"{$packageID}_")===0) {
                                $finalFileList[str_replace($appPath,"",$ff)]= "FILE";
                            } elseif(strpos($fname,"{$packageID}.")===0) {
                                $finalFileList[str_replace($appPath,"",$ff)]= "FILE";
                            }
                        }
                    }
                }
                break;
        }
    }
    $featureFile = $appPath."config/features/{$packageID}.cfg";
    if(file_exists($featureFile)) {
        $finalFileList[str_replace($appPath,"",$featureFile)] = "CONFIG";
    }
    $featureFile = $appPath."config/features/".strtolower($packageID).".cfg";
    if(file_exists($featureFile)) {
        $finalFileList[str_replace($appPath,"",$featureFile)] = "CONFIG";
    }
    $featureFile = $appPath."config/features/{$packageID}.json";
    if(file_exists($featureFile)) {
        $finalFileList[str_replace($appPath,"",$featureFile)] = "CONFIG";
    }
    $featureFile = $appPath."config/features/".strtolower($packageID).".json";
    if(file_exists($featureFile)) {
        $finalFileList[str_replace($appPath,"",$featureFile)] = "CONFIG";
    }

    //printArray($finalFileList);exit();

    if(count($finalFileList)>0) {
        $errorFiles = [];
        foreach($finalFileList as $ff=>$type) {
            if($type=="DIR") {
                $fss = scanSubdirFiles($appPath.$ff."/");
                
                foreach($fss as $src) {
                    $fff = str_replace("#".$appPath, "", "#".$src);
                    $dest = $packageDir.".install/{$fff}";
                    
                    @mkdir(dirname($dest), 0777, true);
                    if(is_dir(dirname($dest))) {
                        $a = copy($src, $dest);
                        
                        if(!$a) {
                            $errorFiles[] = $src;
                        }
                    } else {
                        $errorFiles[] = $src;
                    }
                }
            } elseif($type=="FILE") {
                $src = $appPath . $ff;
                $dest = $packageDir.".install/{$ff}";
                
                @mkdir(dirname($dest), 0777, true);
                if(is_dir(dirname($dest))) {
                    $a = copy($src, $dest);
                    if(!$a) {
                        $errorFiles[] = $src;
                    }
                } else {
                    $errorFiles[] = $src;
                }
            } elseif($type=="CONFIG") {
                $src = $appPath . $ff;
                $ext = explode(".",$src);
                $ext = end($ext);
                $dest = $packageDir."feature.{$ext}";
                
                $a = copy($src, $dest);
                if(!$a) {
                    $errorFiles[] = $src;
                }
            } elseif($type=="MEDIA") {
                $fss = scanSubdirFiles($appPath.$ff."/");
                
                foreach($fss as $src) {
                    $fff = str_replace("#".$appPath, "", "#".$src);
                    $dest = $packageDir.".install/{$fff}";
                    
                    @mkdir(dirname($dest), 0777, true);
                    if(is_dir(dirname($dest))) {
                        $a = copy($src, $dest);
                        
                        if(!$a) {
                            $errorFiles[] = $src;
                        }
                    } else {
                        $errorFiles[] = $src;
                    }
                }
            } elseif($type=="DASHLETS" || $type=="WIDGETS") {
                $src = $appPath . $ff;
                $dest = $packageDir.".install/".str_replace("pluginsDev/","plugins/",$ff);
                
                @mkdir(dirname($dest), 0777, true);
                if(is_dir(dirname($dest))) {
                    $a = copy($src, $dest);
                    if(!$a) {
                        $errorFiles[] = $src;
                    }
                } else {
                    $errorFiles[] = $src;
                }
            } else {
                
            }
        }
    }
    
    //DB Schema
    if(count($tables)>0) {
        $sqlSchema = [];
        foreach($tables as $tbl) {
            $data = _db()->_RAW("SHOW CREATE TABLE {$tbl}")->_GET();
            if(isset($data[0])) {
              $sqlSchema[] = $data[0]['Create Table'];
            }
        }
        if(count($sqlSchema)>0) {
            $sqlSchema = implode(";\n\n",$sqlSchema).";\n\n";
            $srcFile = $packageDir."sql/schema.sql";
            
            if(!is_dir(dirname($srcFile))) {
                mkdir(dirname($srcFile), 0777, true);
            }
            
            $a = file_put_contents($srcFile, $sqlSchema);
            
            if(!$a) {
                $errorFiles[] = $srcFile;
            }
        }
        //printArray($sqlSchema);
    }
    //DB Data
    if(count($tablesData)>0) {
        $sqlData = [];$jsonData = [];
        foreach($tablesData as $tbl) {
            $data = _db()->_RAW("SELECT * FROM {$tbl} LIMIT 1000")->_GET();
            if($data) {
                $sqlData[$tbl] = [];
                foreach($data as $row) {
                    $sqlData[$tbl][] = _db()->_insertQ1($tbl,$row)->_SQL();
                }
                $jsonData[$tbl] = $data;
            }
        }
        if(count($sqlSchema)>0) {
            $srcFile1 = $packageDir."sql/data.sql";
            $srcFile2 = $packageDir."sql/dbdata.json";
            
            if(!is_dir(dirname($srcFile1))) {
                mkdir(dirname($srcFile1), 0777, true);
            }
            
            $sqlFinalData = [];
            foreach($sqlData as $tbl=>$records) {
                $sqlFinalData[] = implode(";\n",$records).";\n";
            }
            
            $sqlFinalData = implode("\n",$sqlFinalData)."\n";
            
            $a1 = file_put_contents($srcFile1, $sqlFinalData);
            $a2 = file_put_contents($srcFile2, json_encode($jsonData, JSON_PRETTY_PRINT));
            
            if(!$a1) {
                $errorFiles[] = $srcFile1;
            }
            if(!$a2) {
                $errorFiles[] = $srcFile2;
            }
        }
    }
    
    // printArray([
    //         count($errorFiles),
    //         $errorFiles
    //     ]);
    if(count($errorFiles)>0) {
        return "Some Source File could not be packaged:<br><pre>".implode("\n",$errorFiles)."</pre>";
    } else {
        return "<h3 align=center>Install Folder Successfully Built</h3><br>";
    }
}

function deleteDir($path) {
    return is_file($path) ?
            @unlink($path) :
            array_map(__FUNCTION__, glob($path.'/*')) == @rmdir($path);
}
?>