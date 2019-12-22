<?php
if(!defined('ROOT')) exit('No direct script access allowed');

$package = $_GET['package'];
$packageName = basename($package);

$packageDir = $appPath."pluginsDev/{$package}/";

if(!(file_exists($packageDir) && is_dir($packageDir))) {
    echo "<h3 style='text-align: center;'>Sorry Package `{$package}` Not Found</h3>";
    return;
}

$packageConfig = $packageDir.$configFile;
if(!file_exists($packageConfig)) {
    
} else {
    try {
        $_ENV['PACKAGE_CONFIG'] = json_decode(file_get_contents($packageConfig),true);
        // printArray($_ENV['PACKAGE_CONFIG']);
    } catch(Exception $e) {
        $_ENV['PACKAGE_CONFIG'] = [];
    }
}

$formFields = [
        "name"=>"text",
        "version"=>"number",
        "description"=>"text",
        "keywords"=>"text",
        "status"=>"select",
        "type"=>"text",
        "package"=>"text",
        "license"=>"text",
        "marketid"=>"text",
        "homepage"=>"text",
        "bugs"=>"text",
        "private"=>"boolean",
    ];
$formSelectors = [
        "status"=>[
                "alpha"=>"alpha",
                "beta"=>"beta",
                "dev"=>"dev",
                "stable"=>"stable",
            ],
    ];
    
$tableList = _db()->get_tableList();
$_ENV['MODNAME'] = $packageName;
$tables = array_filter($tableList, "checkTableName", ARRAY_FILTER_USE_BOTH);
?>
<h3 style="margin-top: 9px;">&nbsp;Package : <?=$package?></h3>
<ul class='nav nav-tabs nav-justified'>
  <li class='active'><a href='#tab1'>Properties</a></li>
  <li><a href='#tab2'>Dependencies</a></li>
  <li><a href='#tab3'>Authors</a></li>
  <li><a href='#tab4'>Tables</a></li>
  <li><a href='#tab5'>Installer</a></li>
  <li><a href='#tab6'>Issues</a></li>
</ul>
<div class='tab-content'>
    <div id='tab1' class='tab-pane fade paddedInfo in active'>
        <form id='packageConfigForm' class="form-horizontal">
            <?php
                foreach($formFields as $key=>$type) {
                    if($type=="boolean") {
                        ?>
                        <div class="form-group">
                            <label class="control-label col-sm-2" for="<?=$key?>"><?=toTitle(_ling($key))?>:</label>
                            <div class="col-sm-10">
                                <select class='form-control' id="<?=$key?>" name="<?=$key?>" value='<?=(getPackageConfig($key)?"true":"false")?>'>
                                    <option value='true'>True</option>
                                    <option value='false'>False</option>
                                </select>
                            </div>
                        </div>
                        <?php
                    } elseif($type=="select" && isset($formSelectors[$key])) {
                        ?>
                        <div class="form-group">
                            <label class="control-label col-sm-2" for="<?=$key?>"><?=toTitle(_ling($key))?>:</label>
                            <div class="col-sm-10">
                                <select class='form-control' id="<?=$key?>" name="<?=$key?>" value='<?=getPackageConfig($key)?>'>
                                    <?php
                                        foreach($formSelectors[$key] as $a=>$b) {
                                            echo "<option value='$b'>$a</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="form-group">
                            <label class="control-label col-sm-2" for="<?=$key?>"><?=toTitle(_ling($key))?>:</label>
                            <div class="col-sm-10">
                              <input type="<?=$type?>" class="form-control" id="<?=$key?>" name="<?=$key?>" placeholder="Enter <?=$key?>" value='<?=getPackageConfig($key)?>'>
                            </div>
                        </div>
                        <?php
                    }
                }
            ?>
            <div class="form-group">
                <label class="control-label col-sm-2" for="repository"><?=toTitle(_ling("repository"))?>:</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="repository" name="repository" placeholder="Enter repository" value='<?=getPackageConfig("repository")?>'>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button type="reset" class="btn btn-warning">Reset</button>
                    <button type="button" class="btn btn-success" onclick="updatePackageConfig(this)">Submit</button>
                </div>
            </div>
        </form>
    </div>
    <div id='tab2' class='tab-pane fade paddedInfo'>
        <div class='toolbar text-right'>
            <i class='fa fa-magic' title='Find Dependency Automatically' onclick="findDependecy(this)"></i>
            <i class='fa fa-plus' title='Add Dependency' onclick="addBlankDependecy(this)"></i>
        </div>
        <form>
            <table class='table table-stripped table-hover'>
                <thead>
                    <tr>
                        <th width=100px>SL#</th>
                        <th>Package</th>
                        <th>Version</th>
                        <th width=100px></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        if(isset($_ENV['PACKAGE_CONFIG']['dependencies'])) {
                            $count = 1;
                            foreach($_ENV['PACKAGE_CONFIG']['dependencies'] as $pack=>$ver) {
                                echo "<tr><td width=100px>{$count}</td><td><input name='dependencies[package][]' type='text' class='form-control' value='{$pack}' /></td><td><input name='dependencies[vers][]' type='text' class='form-control' value='{$ver}' /></td><td class='text-right'><i class='fa fa-times' onclick='removeMe(this)'></i></td></tr>";
                                $count++;
                            }
                        }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class='text-center' colspan=100>
                            <button type="button" class="btn btn-success" onclick="updatePackageConfig(this)">Update</button>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </form>
    </div>
    <div id='tab3' class='tab-pane fade paddedInfo'>
        <div class='toolbar text-right'>
            <i class='fa fa-plus' title='Add Dependency' onclick="addBlankAuthor(this)"></i>
        </div>
        <form>
            <table class='table table-stripped table-hover'>
                <thead>
                    <tr>
                        <th width=100px>SL#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Authorid</th>
                        <th width=100px></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        if(isset($_ENV['PACKAGE_CONFIG']['authors'])) {
                            $count = 1;
                            foreach($_ENV['PACKAGE_CONFIG']['authors'] as $author) {
                                echo "<tr><td width=100px>{$count}</td><td><input name='authors[name][]' type='text' class='form-control' value='{$author['name']}' /></td><td><input name='authors[email][]' type='email' class='form-control' value='{$author['email']}' /></td><td><input name='authors[authorid][]' type='text' class='form-control' value='{$author['authorid']}' /></td><td class='text-right'><i class='fa fa-times' onclick='removeMe(this)'></i></td></tr>";
                                $count++;
                            }
                        }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class='text-center' colspan=100>
                            <button type="button" class="btn btn-success" onclick="updatePackageConfig(this)">Update</button>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </form>
    </div>
    <div id='tab4' class='tab-pane fade paddedInfo'>
        <?php
            if(count($tables)>0)
                echo "<ul class='list-group'><li class='list-group-item col-md-3'>".implode("</li><li class='list-group-item col-md-3'>",array_values($tables))."</li></ul>";
            else
                echo "<h5 align=center>No database tables used by this module</h5>";
        ?>
    </div>
    <div id='tab5' class='tab-pane fade paddedInfo'>
        <h3 class='text-center'>Coming Soon ...</h3>
    </div>
    <div id='tab5' class='tab-pane fade paddedInfo'>
        <h3 class='text-center'>Coming Soon ...</h3>
    </div>
</div>
<script>
$(function() {
    $("#packageConfigForm select").each(function() {
        $(this).val($(this).attr("value"));
    });
});
function updatePackageConfig(btn) {
    qData = $(btn).closest("form").serialize();
    processAJAXPostQuery(_service("packageBuilder","updatePackage")+"&package=<?=$package?>", qData, function(data) {
        if(data.Data.status=="success") lgksToast("Package Updated Successfully");
        else lgksToast(data.Data.msg);
    },"json");
}
function addBlankDependecy(btn) {
    $(btn).closest(".tab-pane").find("table tbody").append(
            "<tr><td width=100px>0</td><td><input name='dependencies[package][]' type='text' class='form-control' /></td><td><input name='dependencies[vers][]' type='text' class='form-control' /></td><td class='text-right'><i class='fa fa-times' onclick='removeMe(this)'></i></td></tr>"
        );
}
function addBlankAuthor(btn) {
    $(btn).closest(".tab-pane").find("table tbody").append(
            "<tr><td width=100px>0</td><td><input name='authors[name][]' type='text' class='form-control' /></td><td><input name='authors[email][]' type='email' class='form-control' /></td><td><input name='authors[authorid][]' type='text' class='form-control' /></td><td class='text-right'><i class='fa fa-times' onclick='removeMe(this)'></i></td></tr>"
        );
}
function findDependecy(btn) {
    
}
function removeMe(btn) {
    $(btn).closest("tr").detach();
}
</script>