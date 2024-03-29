<?php
if(!defined('ROOT')) exit('No direct script access allowed');
loadModule("pages");

function pageContentArea() {
    return "<div id='contentArea'><h2 class='text-center'>Please load package to view its configuration</h2></div>";
}
function pageSidebar() {
    return "<div id='sidebarArea'></div>";
}

_css(["packageBuilder"]);
printPageComponent(false,[
		"toolbar"=>[
			"reloadPage"=>["icon"=>"<i class='fa fa-refresh'></i>"],
			"findAllIssues"=>["icon"=>"<i class='fa fa-dashboard'></i>","tips"=>"Find all issues in the Packages"],
			//['type'=>"bar"],
			//"rename"=>["icon"=>"<i class='fa fa-terminal'></i>","class"=>"onsidebarSelect onOnlyOneSelect","tips"=>"Rename Content"],
// 			"deleteTemplate"=>["icon"=>"<i class='fa fa-trash'></i>","class"=>"onsidebarSelect"],
		],
		"sidebar"=>"pageSidebar",
		"contentArea"=>"pageContentArea"
	]);
_js(["packageBuilder"]);
?>
<style>
.paddedInfo {
    padding: 20px;
}
hr {
    margin: 0px;
    margin-top: 5px;
    margin-bottom: 5px;
}
.list-group {
    margin: 0px;
}
.list-group-item:first-child, .list-group-item:last-child {
    border-radius: 0px !important;
}
.list-group-item {
    cursor: pointer;
}
.toolbar .fa {
    margin: 10px;
    font-size: 20px;
}
.list-group-item span.label {
    font-size: 8px;
}
</style>
<script>
$(function() {
    $("#sidebarArea").delegate(".list-group-item","click", function() {
        $("#sidebarArea").find(".list-group-item.active").removeClass("active");
        $(this).addClass("active");
        loadPackage($(this).data('refid'));
    });
    listPackages();
});
function reloadPage() {
    //window.location.reload();
    listPackages();
}
function listPackages() {
    $("#sidebarArea").html("<div class='ajaxloading ajaxloading5'></div>");
    processAJAXQuery(_service("packageBuilder","listPackages"),function(data) {
        $("#sidebarArea").html("<ul class='list-group list-group-one'></ul><ul class='list-group list-group-two'></ul>");
        $.each(data.Data, function(key,dataSet) {
            if(typeof dataSet == "object" && dataSet!=null) {
                $.each(dataSet, function(f,a) {
                    clz = "";
                    if(a.indexOf("nogit")>=0) {
                        clz = "<span class='label label-danger pull-right'>nogit</span>";
                    } else {
                        clz = "<span class='label label-success pull-right'>git</span>";
                    }
                    
                    if(a.indexOf("nok")>=0) {
                        $("#sidebarArea>ul.list-group-one").append("<li class='list-group-item list-group-item-warning' data-refid='"+f+"' title='Error found in package'>"+clz+f+"</li>");
                    } else {
                        $("#sidebarArea>ul.list-group-two").append("<li class='list-group-item' data-refid='"+f+"'>"+clz+f+"</li>");
                    }
                });
            }
        });
    },"json");
}
function findAllIssues() {
    $("#contentArea").html("<div class='ajaxloading ajaxloading5'></div>");
    processAJAXQuery(_service("packageBuilder","findIssues","html"),function(data) {
        $("#contentArea").html("<div class='paddedInfo1'>"+data+"</div>");
        
        $('.nav-tabs a').click(function(){
              $(this).tab('show');
            })
    });
}
function loadPackage(pckage) {
    $("#contentArea").html("<div class='ajaxloading ajaxloading5'></div>");
    processAJAXQuery(_service("packageBuilder","loadPackage","html")+"&package="+pckage,function(data) {
        $("#contentArea").html("<div class='paddedInfo1'>"+data+"</div>");
        
        $('.nav-tabs a').click(function(){
              $(this).tab('show');
            });
    });
}
</script>