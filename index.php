<?php
if(!defined('ROOT')) exit('No direct script access allowed');
loadModule("pages");

function pageContentArea() {
    return "<div id='contentArea'>A</div>";
}
function pageSidebar() {
    return "<div id='sidebarArea'>B</div>";
}

_css(["packageGenerator"]);
printPageComponent(false,[
		"toolbar"=>[
			//"loadTextEditor"=>["title"=>"Template","align"=>"right"],
			//"loadInfoComponent"=>["title"=>"About","align"=>"right"],
// 			["title"=>"Search Roles","type"=>"search","align"=>"right"],
			
			"reloadPage"=>["icon"=>"<i class='fa fa-refresh'></i>"],
// 			"generateRoles"=>["icon"=>"<i class='fa fa-gears'></i>","tips"=>"Generate New Roles"],
			//"createTemplate"=>["icon"=>"<i class='fa fa-plus'></i>","tips"=>"Create New"],
			//"openExternal"=>["icon"=>"<i class='fa fa-external-link'></i>","class"=>"onsidebarSelect"],
			//"preview"=>["icon"=>"<i class='fa fa-eye'></i>","class"=>"onsidebarSelect onOnlyOneSelect","tips"=>"Preview Content"],
			//['type'=>"bar"],
			//"rename"=>["icon"=>"<i class='fa fa-terminal'></i>","class"=>"onsidebarSelect onOnlyOneSelect","tips"=>"Rename Content"],
// 			"deleteTemplate"=>["icon"=>"<i class='fa fa-trash'></i>","class"=>"onsidebarSelect"],
		],
		"sidebar"=>"pageSidebar",
		"contentArea"=>"pageContentArea"
	]);
_js(["packageGenerator"]);
?>
<script>
$(function() {
    
});
</script>